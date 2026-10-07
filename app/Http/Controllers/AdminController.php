<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\SupportMessage;
use App\Services\SmsService;
use App\Support\NameParts;
use App\Support\PhoneNumber;
use App\Support\UsernameIdentity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    private function supabaseUrl(): ?string
    {
        return env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL');
    }

    private function serviceKey(): ?string
    {
        return env('SUPABASE_SERVICE_KEY')
            ?: env('SUPABASE_SERVICE_ROLE_KEY')
            ?: getenv('SUPABASE_SERVICE_KEY')
            ?: getenv('SUPABASE_SERVICE_ROLE_KEY');
    }

    private function getHeaders(): array
    {
        $key = $this->serviceKey();

        return [
            'apikey' => $key,
            'Authorization' => 'Bearer ' . $key,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    private function decorateName(array $record): array
    {
        $record['full_name'] = NameParts::display($record);

        return $record;
    }

    private function pendingRegistrationRows(string $role): array
    {
        return DB::table('pending_registrations')
            ->where('role', $role)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(function ($registration): array {
                $details = json_decode((string) $registration->details, true);

                return [
                    'id' => $registration->id,
                    'phone_number' => $registration->phone_number,
                    'details' => is_array($details) ? $details : [],
                    'created_at' => $registration->created_at,
                ];
            })
            ->all();
    }

    private function sendAccountDecisionSms(SmsService $smsService, ?string $phoneNumber, string $role, bool $approved): bool
    {
        $phoneNumber = PhoneNumber::normalize($phoneNumber);
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $phoneNumber)) {
            Log::warning('Account decision SMS was not sent because the registered phone number is missing or invalid.', [
                'role' => $role,
                'phone_hash' => hash('sha256', $phoneNumber),
                'approved' => $approved,
            ]);

            return false;
        }

        if (config('services.sms.driver') === 'log') {
            Log::warning('Account decision SMS was not sent because SMS delivery is configured for log mode.', [
                'role' => $role,
                'phone_hash' => hash('sha256', $phoneNumber),
                'approved' => $approved,
            ]);

            return false;
        }

        $decision = $approved ? 'approved' : 'declined';
        $message = $approved
            ? "Your Orion Christian Academy {$role} account has been approved. You may now sign in using your mobile number and password."
            : "Your Orion Christian Academy {$role} registration was declined. Please contact the school administration if you need assistance.";

        try {
            $smsService->send($phoneNumber, $message);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Account decision SMS could not be sent.', [
                'role' => $role,
                'phone_hash' => hash('sha256', $phoneNumber),
                'decision' => $decision,
                'exception' => get_class($exception),
            ]);

            return false;
        }
    }

    private function accountDecisionResponse(SmsService $smsService, ?string $phoneNumber, string $role, bool $approved, string $baseMessage, array $extra = []): JsonResponse
    {
        $notificationSent = $this->sendAccountDecisionSms($smsService, $phoneNumber, $role, $approved);

        return response()->json([
            ...$extra,
            'message' => $baseMessage.($notificationSent
                ? ' SMS notification sent.'
                : ' SMS notification could not be sent; check SMS configuration and delivery.'),
            'notification_sent' => $notificationSent,
        ]);
    }

    private function reviewPendingRegistration(string $id, string $role, bool $approved, SmsService $smsService): ?JsonResponse
    {
        $registration = DB::table('pending_registrations')
            ->where('id', $id)
            ->where('role', $role)
            ->where('status', 'pending')
            ->first();

        if (! $registration) {
            return null;
        }

        if (! $approved) {
            DB::table('pending_registrations')->where('id', $id)->delete();

            return $this->accountDecisionResponse(
                $smsService,
                (string) $registration->phone_number,
                $role,
                false,
                ucfirst($role).' registration declined and deleted.'
            );
        }

        $claimed = DB::table('pending_registrations')
            ->where('id', $id)
            ->where('status', 'pending')
            ->update(['status' => 'processing', 'updated_at' => now()]);

        if ($claimed !== 1) {
            return response()->json(['error' => 'This registration is already being reviewed. Please refresh and try again.'], 409);
        }

        $details = json_decode((string) $registration->details, true);
        if (! is_array($details)) {
            DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

            return response()->json(['error' => 'The registration details could not be read.'], 500);
        }

        try {
            $password = Crypt::decryptString((string) $registration->encrypted_password);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {
            Log::error('Pending registration password could not be decrypted.', [
                'registration_id' => $id,
                'role' => $role,
                'message' => $exception->getMessage(),
            ]);
            DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

            return response()->json(['error' => 'Unable to create the account because its encrypted password could not be read.'], 500);
        }

        $supabaseUrl = $this->supabaseUrl();
        $serviceKey = $this->serviceKey();
        if (! $supabaseUrl || ! $serviceKey) {
            DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        $metadata = [
            ...$details,
            'role' => $role,
            'phone_number' => (string) $registration->phone_number,
            'username' => UsernameIdentity::normalize((string) ($details['username'] ?? '')),
        ];
        $authEmail = UsernameIdentity::authEmail($metadata['username']);
        $existingAuthUser = null;
        $usernameAuthUser = null;
        $page = 1;
        do {
            $usersResponse = Http::withHeaders($this->getHeaders())
                ->timeout(10)
                ->get("{$supabaseUrl}/auth/v1/admin/users", [
                    'page' => $page,
                    'per_page' => 100,
                ]);

            if ($usersResponse->failed()) {
                Log::error('Unable to check for an existing Auth account before approval.', [
                    'registration_id' => $id,
                    'role' => $role,
                    'status' => $usersResponse->status(),
                    'response' => $usersResponse->body(),
                ]);
                DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

                return response()->json(['error' => 'Unable to check for an existing account. Please retry approval.'], 503);
            }

            $users = $usersResponse->json('users');
            if (! is_array($users)) {
                Log::error('Supabase returned an unexpected Auth user list during approval.', [
                    'registration_id' => $id,
                    'role' => $role,
                    'status' => $usersResponse->status(),
                ]);
                DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

                return response()->json(['error' => 'Unable to check for an existing account. Please retry approval.'], 503);
            }

            foreach ($users as $user) {
                if (! is_array($user) || empty($user['id'])) {
                    continue;
                }

                if (strtolower((string) ($user['email'] ?? '')) === $authEmail) {
                    $usernameAuthUser = $user;
                }

                if (PhoneNumber::normalize((string) ($user['phone'] ?? '')) === (string) $registration->phone_number) {
                    $existingAuthUser = $user;
                }
            }

            $page++;
        } while (count($users) === 100 && (! $existingAuthUser || ! $usernameAuthUser));

        if ($usernameAuthUser && (! $existingAuthUser || (string) $usernameAuthUser['id'] !== (string) $existingAuthUser['id'])) {
            DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

            return response()->json(['error' => 'This username is already linked to a different account. Choose another username before approving.'], 409);
        }

        $reusedAuthUser = $existingAuthUser !== null;
        if ($existingAuthUser) {
            $existingRole = strtolower((string) data_get($existingAuthUser, 'user_metadata.role', ''));
            $existingUsername = UsernameIdentity::normalize((string) data_get($existingAuthUser, 'user_metadata.username', ''));
            if (PhoneNumber::normalize((string) ($existingAuthUser['phone'] ?? '')) !== (string) $registration->phone_number
                || $existingRole !== $role
                || ($existingUsername !== '' && $existingUsername !== $metadata['username'])) {
                Log::warning('A pending registration matched an incompatible existing Auth account.', [
                    'registration_id' => $id,
                    'role' => $role,
                    'auth_user_id' => $existingAuthUser['id'],
                ]);
                DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

                return response()->json(['error' => 'This mobile number is already attached to a different account. Contact the administrator to resolve the duplicate account.'], 409);
            }
        }

        if ($existingAuthUser) {
            $authUserId = (string) $existingAuthUser['id'];
            $existingMetadata = is_array($existingAuthUser['user_metadata'] ?? null) ? $existingAuthUser['user_metadata'] : [];
            $authResponse = Http::withHeaders($this->getHeaders())
                ->timeout(10)
                ->put("{$supabaseUrl}/auth/v1/admin/users/".urlencode($authUserId), [
                    'email' => $authEmail,
                    'email_confirm' => true,
                    'phone' => (string) $registration->phone_number,
                    'phone_confirm' => true,
                    'password' => $password,
                    'user_metadata' => array_merge($existingMetadata, $metadata),
                ]);
        } else {
            $authResponse = Http::withHeaders($this->getHeaders())
                ->timeout(10)
                ->post("{$supabaseUrl}/auth/v1/admin/users", [
                    'email' => $authEmail,
                    'email_confirm' => true,
                    'phone' => (string) $registration->phone_number,
                    'password' => $password,
                    'phone_confirm' => true,
                    'user_metadata' => $metadata,
                ]);
        }

        if ($authResponse->failed()) {
            Log::warning('Supabase rejected Auth account creation or repair during approval.', [
                'registration_id' => $id,
                'role' => $role,
                'status' => $authResponse->status(),
                'response' => $authResponse->body(),
            ]);
            DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

            $status = in_array($authResponse->status(), [409, 422], true) ? 409 : 500;

            return response()->json([
                'error' => $authResponse->json('message')
                    ?? $authResponse->json('msg')
                    ?? 'Supabase could not create the approved account. Check the application log for the Supabase error.',
            ], $status);
        }

        $authUser = $authResponse->json();
        $authUserId = $existingAuthUser
            ? (string) $existingAuthUser['id']
            : (is_array($authUser) ? ($authUser['id'] ?? null) : null);
        if (! is_string($authUserId) || $authUserId === '') {
            DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

            return response()->json(['error' => 'Supabase created no usable account ID.'], 500);
        }

        $existingProfileId = null;
        if ($role === 'parent') {
            $existingProfileResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/parents", [
                    'select' => 'id',
                    'auth_user_id' => 'eq.'.$authUserId,
                    'limit' => 1,
                ]);
            if ($existingProfileResponse->failed()) {
                Log::error('Unable to check for an existing parent profile during approval.', [
                    'registration_id' => $id,
                    'auth_user_id' => $authUserId,
                    'response' => $existingProfileResponse->body(),
                ]);
                DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

                return response()->json(['error' => 'Unable to check for an existing parent profile. Please retry approval.'], 500);
            }
            $existingProfile = collect($existingProfileResponse->json())->first();
            $existingProfileId = is_array($existingProfile) ? (string) ($existingProfile['id'] ?? '') : null;
            $existingProfileId = $existingProfileId !== '' ? $existingProfileId : null;

            $profileResponse = saveParentProfileToSupabase($supabaseUrl, $serviceKey, [
                ...NameParts::split((string) ($details['full_name'] ?? '')),
                'auth_user_id' => $authUserId,
                'student_id' => (int) ($details['student_id'] ?? 0),
                'phone_number' => (string) $registration->phone_number,
                'relationship' => (string) ($details['relationship'] ?? ''),
                'email' => null,
                'is_active' => true,
                'is_approved' => true,
            ], $existingProfileId);
        } else {
            $existingProfileResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/staff", [
                    'select' => 'id',
                    'id' => 'eq.'.$authUserId,
                    'limit' => 1,
                ]);
            if ($existingProfileResponse->failed()) {
                Log::error('Unable to check for an existing staff profile during approval.', [
                    'registration_id' => $id,
                    'auth_user_id' => $authUserId,
                    'response' => $existingProfileResponse->body(),
                ]);
                DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

                return response()->json(['error' => 'Unable to check for an existing staff profile. Please retry approval.'], 500);
            }
            $existingProfile = collect($existingProfileResponse->json())->first();
            $hasExistingProfile = is_array($existingProfile);
            $profilePayload = [
                'id' => $authUserId,
                    ...NameParts::split((string) ($details['full_name'] ?? '')),
                    'email' => null,
                    'phone_number' => (string) $registration->phone_number,
                    'is_approved' => true,
                    'is_active' => true,
            ];
            $staffRequest = Http::withHeaders($this->getHeaders())
                ->withHeaders(['Prefer' => 'return=representation']);
            $profileResponse = $hasExistingProfile
                ? $staffRequest->patch("{$supabaseUrl}/rest/v1/staff?id=eq.".urlencode($authUserId), $profilePayload)
                : $staffRequest->post("{$supabaseUrl}/rest/v1/staff", $profilePayload);
        }

        $profile = $profileResponse->successful() ? collect($profileResponse->json())->first() : null;
        if ($profileResponse->failed() || ! is_array($profile)) {
            $deleteAuthResponse = $reusedAuthUser
                ? null
                : Http::withHeaders($this->getHeaders())
                    ->delete("{$supabaseUrl}/auth/v1/admin/users/".urlencode($authUserId));

            if ($deleteAuthResponse && $deleteAuthResponse->failed()) {
                Log::error('Approved account profile creation failed and the new Auth user could not be rolled back.', [
                    'registration_id' => $id,
                    'auth_user_id' => $authUserId,
                    'role' => $role,
                    'profile_response' => $profileResponse->body(),
                    'rollback_response' => $deleteAuthResponse->body(),
                ]);

                return response()->json([
                    'error' => 'The account was created, but its profile failed and automatic cleanup also failed. Contact an administrator before retrying.',
                ], 500);
            }

            if ($reusedAuthUser) {
                Log::error('A reused Auth account was updated but its profile could not be created or updated.', [
                    'registration_id' => $id,
                    'auth_user_id' => $authUserId,
                    'role' => $role,
                    'profile_response' => $profileResponse->body(),
                ]);
            }

            DB::table('pending_registrations')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);

            return response()->json([
                'error' => $profileResponse->json('message')
                    ?? ($reusedAuthUser
                        ? 'The existing sign-in account was updated, but its profile could not be saved. Retry approval.'
                        : 'The approved account profile could not be created. No Auth account was retained.'),
            ], 500);
        }

        DB::table('pending_registrations')->where('id', $id)->delete();

        return $this->accountDecisionResponse(
            $smsService,
            (string) $registration->phone_number,
            $role,
            true,
            ucfirst($role).' account approved and created.',
            ['user_id' => $authUserId]
        );
    }

    private function deleteDeclinedAccount(string $table, string $id, ?string $authUserIdColumn, string $role, SmsService $smsService): JsonResponse
    {
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        $profileResponse = Http::withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/{$table}", [
                'select' => '*',
                'id' => 'eq.'.urlencode($id),
                'limit' => 1,
            ]);

        if ($profileResponse->failed()) {
            Log::error('Unable to load account profile before decline deletion.', [
                'table' => $table,
                'profile_id' => $id,
                'response' => $profileResponse->body(),
            ]);

            return response()->json(['error' => 'Unable to load the account before deleting it.'], 500);
        }

        $profile = collect($profileResponse->json())->first();
        if (! is_array($profile)) {
            return response()->json(['error' => 'Account not found.'], 404);
        }

        $authUserId = $authUserIdColumn
            ? ($profile[$authUserIdColumn] ?? null)
            : $id;
        $phoneNumber = $profile['phone_number'] ?? $profile['mobile_number'] ?? null;

        if (is_string($authUserId) && $authUserId !== '') {
            $authDeleteResponse = Http::withHeaders($this->getHeaders())
                ->delete("{$supabaseUrl}/auth/v1/admin/users/".urlencode($authUserId));

            if ($authDeleteResponse->failed()) {
                Log::error('Declined account Auth user could not be deleted.', [
                    'table' => $table,
                    'profile_id' => $id,
                    'auth_user_id' => $authUserId,
                    'response' => $authDeleteResponse->body(),
                ]);

                return response()->json(['error' => 'The account could not be deleted from Supabase Auth.'], 500);
            }
        }

        $deleteProfileResponse = Http::withHeaders($this->getHeaders())
            ->delete("{$supabaseUrl}/rest/v1/{$table}?id=eq.".urlencode($id));

        if ($deleteProfileResponse->failed()) {
            Log::error('Declined account Auth user was deleted but its profile cleanup failed.', [
                'table' => $table,
                'profile_id' => $id,
                'auth_user_id' => $authUserId,
                'response' => $deleteProfileResponse->body(),
            ]);

            return response()->json([
                'error' => 'The sign-in account was deleted, but profile cleanup failed. Contact an administrator.',
            ], 500);
        }

        return $this->accountDecisionResponse(
            $smsService,
            is_string($phoneNumber) ? $phoneNumber : null,
            $role,
            false,
            'Account declined and permanently deleted.'
        );
    }

    public function showLoginForm(): View
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:50',
            'password' => 'required|string',
        ]);

        $phoneNumber = PhoneNumber::normalize($request->input('phone'));
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $phoneNumber)) {
            return back()->withErrors(['phone' => 'Enter a valid mobile number.'])->withInput();
        }

        $admin = $this->findSupabaseAdminByPhone($phoneNumber);

        if (! $admin || ! $admin->is_active) {
            Log::warning('Admin login rejected: account missing or inactive.', [
                'phone_hash' => hash('sha256', $phoneNumber),
                'account_found' => $admin !== null,
            ]);

            return back()->withErrors(['phone' => 'Invalid mobile number or password.'])->withInput();
        }

        $passwordIsValid = false;

        if (! empty($admin->auth_user_id)) {
            $passwordIsValid = $this->authenticateSupabaseAdmin($phoneNumber, $request->password, (string) $admin->auth_user_id, 'phone');

            if (! $passwordIsValid && filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
                $passwordIsValid = $this->authenticateSupabaseAdmin((string) $admin->email, $request->password, (string) $admin->auth_user_id, 'email');
            }
        }

        if (! $passwordIsValid) {
            $passwordIsValid = $admin->verifyPassword($request->password);
        }

        if (! $passwordIsValid) {
            Log::warning('Admin login rejected: password verification failed.', [
                'phone_hash' => hash('sha256', $phoneNumber),
                'auth_user_id' => $admin->auth_user_id,
                'supabase_auth_checked' => ! empty($admin->auth_user_id),
            ]);

            return back()->withErrors(['phone' => 'Invalid mobile number or password.'])->withInput();
        }

        Session::put('admin_id', $admin->id);
        Session::put('admin_name', $admin->full_name ?: $admin->phone_number);
        Session::put('admin_role', 'admin');

        return redirect()->route('admin.dashboard');
    }

    public function requestPasswordReset(Request $request, SmsService $smsService): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:50',
        ]);
        $phoneNumber = PhoneNumber::normalize($validated['phone']);
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $phoneNumber)) {
            return response()->json(['message' => 'Enter a valid mobile number.'], 422);
        }

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['message' => 'Admin SMS password reset is not configured.'], 503);
        }
        if (config('services.sms.driver') === 'log') {
            return response()->json(['message' => 'SMS delivery is not configured.'], 503);
        }

        $ipKey = 'admin-password-reset-ip:'.hash('sha256', (string) $request->ip());
        $phoneKey = 'admin-password-reset-phone:'.hash('sha256', $phoneNumber);
        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($phoneKey, 3)) {
            return response()->json(['message' => 'Too many code requests. Please wait before trying again.'], 429);
        }
        RateLimiter::hit($ipKey, 60);
        RateLimiter::hit($phoneKey, 600);

        $admin = $this->findSupabaseAdminByPhone($phoneNumber);
        $flowId = Str::random(48);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(5);
        $canReset = $admin !== null && (bool) $admin->is_active;
        $cacheKey = 'admin-password-reset:'.$flowId;
        Cache::put($cacheKey, [
            'admin_id' => $canReset ? (string) $admin->id : null,
            'phone_hash' => hash('sha256', $phoneNumber),
            'code_hash' => $canReset ? Hash::make($code) : null,
            'attempts' => 0,
            'expires_at' => $expiresAt->timestamp,
        ], $expiresAt);

        if ($canReset) {
            try {
                $smsService->send($phoneNumber, 'Your administrator password reset code is '.$code.'. It expires in 5 minutes. Do not share this code.');
            } catch (\Throwable $exception) {
                Cache::forget($cacheKey);
                Log::warning('Unable to send admin password reset SMS.', [
                    'exception' => get_class($exception),
                ]);

                return response()->json(['message' => 'Unable to send an SMS code right now. Please try again later.'], 503);
            }
        }

        return response()->json([
            'flow_id' => $flowId,
            'message' => 'If this number belongs to an active administrator, a verification code will be sent by SMS.',
        ]);
    }

    public function completePasswordReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flow_id' => 'required|string|size:48',
            'code' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $cacheKey = 'admin-password-reset:'.$validated['flow_id'];
        $reset = Cache::get($cacheKey);
        if (! is_array($reset)
            || empty($reset['admin_id'])
            || empty($reset['code_hash'])
            || now()->timestamp >= (int) ($reset['expires_at'] ?? 0)) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'The code is invalid or expired. Request a new code.'], 422);
        }
        if (($reset['attempts'] ?? 0) >= 5) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'Too many incorrect codes. Request a new code.'], 429);
        }
        if (! Hash::check($validated['code'], $reset['code_hash'])) {
            $reset['attempts'] = ($reset['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $reset, now()->addSeconds(max(1, (int) $reset['expires_at'] - now()->timestamp)));

            return response()->json(['message' => 'The code is invalid or expired.'], 422);
        }

        $admin = $this->findSupabaseAdmin((string) $reset['admin_id']);
        if (! $admin || ! $admin->is_active
            || hash('sha256', PhoneNumber::normalize($admin->phone_number)) !== $reset['phone_hash']) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'The administrator account could not be verified. Request a new code.'], 422);
        }

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['message' => 'Admin password reset is not configured.'], 503);
        }

        $usesSupabaseAuth = ! empty($admin->auth_user_id);
        if ($usesSupabaseAuth) {
            $authUpdate = Http::withHeaders($this->getHeaders())
                ->put("{$supabaseUrl}/auth/v1/admin/users/".urlencode((string) $admin->auth_user_id), [
                    'password' => $validated['password'],
                ]);
            if ($authUpdate->failed()) {
                Log::warning('Unable to update admin Auth password after SMS verification.', [
                    'admin_id' => $admin->id,
                    'status' => $authUpdate->status(),
                ]);

                return response()->json(['message' => 'Unable to update the password. Please try again.'], 503);
            }
        }

        if (! $usesSupabaseAuth) {
            $admin->password_hash = $validated['password'];
            if (! $this->updateSupabaseAdmin($admin->id, ['password_hash' => $admin->password_hash])) {
                return response()->json(['message' => 'Unable to save the new password. Please try again or contact an administrator.'], 500);
            }
        }

        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Password reset successfully. You can now sign in.',
            'redirect' => '/admin/login',
        ]);
    }

    public function logout()
    {
        Session::forget(['admin_id', 'admin_name', 'admin_role']);

        return redirect()->route('admin.login');
    }

    public function dashboard(): View
    {
        $supabaseUrl = $this->supabaseUrl();
        $students = [];
        $parents = [];
        $entries = [];

        if ($supabaseUrl && $this->serviceKey()) {
            /** @var \Illuminate\Http\Client\Response $studentsResponse */
                $studentsResponse = Http::withHeaders($this->getHeaders())
                    ->get("{$supabaseUrl}/rest/v1/students", ['select' => 'id,first_name,middle_name,last_name,grade_level,section']);
            /** @var \Illuminate\Http\Client\Response $parentsResponse */
            $parentsResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/parents", ['select' => 'id,first_name,middle_name,last_name,phone_number']);

            try {
                [$entries] = $this->fetchEntryRecordsData($supabaseUrl);
            } catch (\Throwable $exception) {
                Log::warning('Unable to load recent dashboard entries.', [
                    'message' => $exception->getMessage(),
                ]);
                $entries = [];
            }

            $students = $studentsResponse->successful() ? collect($studentsResponse->json())->map(fn (array $student) => $this->decorateName($student))->all() : [];
            $parents = $parentsResponse->successful() ? collect($parentsResponse->json())->map(fn (array $parent) => $this->decorateName($parent))->all() : [];
            $entries = collect($entries)->sortByDesc(fn ($entry) => $entry['entry_time'] ?? '')->take(10)->values()->all();
        }

        $totalStudents = count($students);
        $totalParents = count($parents);
        $successfulEntries = collect($entries)->filter(function ($entry) {
            $status = strtolower((string) ($entry['status'] ?? 'successful'));
            return in_array($status, ['successful', 'success', 'verified'], true);
        })->count();

        $recentEntries = collect($entries)
            ->sortByDesc(fn ($entry) => $entry['entry_time'] ?? '')
            ->take(5)
            ->values()
            ->all();

        $parentLookup = collect($parents)->keyBy('id')->all();
        $studentLookup = collect($students)->keyBy('id')->all();

        foreach ($recentEntries as $idx => $entry) {
            $parentId = (int) ($entry['parent_id'] ?? 0);
            $studentId = (int) ($entry['student_id'] ?? 0);
            $recentEntries[$idx]['parent'] = $parentLookup[$parentId] ?? null;
            $recentEntries[$idx]['student'] = $studentLookup[$studentId] ?? null;
        }

        return view('admin.dashboard', [
            'totalStudents' => $totalStudents,
            'totalParents' => $totalParents,
            'successfulEntries' => $successfulEntries,
            'recentEntries' => $recentEntries,
        ]);
    }

    public function getStudents(Request $request)
    {
        $isJsonRequest = $request->expectsJson()
            || $request->ajax()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || str_contains((string) $request->header('Accept', ''), 'application/json')
            || $request->boolean('format.json')
            || $request->query('format') === 'json';

        if ($isJsonRequest) {
            $supabaseUrl = $this->supabaseUrl();

            if (! $supabaseUrl || ! $this->serviceKey()) {
                return response()->json([
                    'students' => [],
                    'counts' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'graduated' => 0],
                ], 200);
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/students", [
                    'select' => '*',
                    'order' => 'first_name.asc,last_name.asc',
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch students'], 500);
            }

            $students = $response->json();

            $parentsResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/parents", [
                    'select' => 'id,student_id,first_name,middle_name,last_name',
                ]);
            $parents = $parentsResponse->successful()
                ? collect($parentsResponse->json())->map(fn (array $parent) => $this->decorateName($parent))->all()
                : [];
            $parentLookup = collect($parents)->keyBy(fn ($parent) => (string) ($parent['id'] ?? ''));

            $parentIdsByStudent = collect($parents)->groupBy(fn (array $parent) => (string) ($parent['student_id'] ?? ''));

            $students = collect($students)->map(function (array $student) use ($parentLookup, $parentIdsByStudent): array {
                $student = $this->decorateName($student);
                $parentIds = collect($student['parent_ids'] ?? [])
                    ->map(fn ($parentId) => (string) $parentId);
                $parentIds = $parentIds->merge(
                    $parentIdsByStudent->get((string) ($student['id'] ?? ''), collect())
                        ->pluck('id')
                        ->map(fn ($parentId) => (string) $parentId)
                )->unique()->values();

                if (! empty($student['parent_id'])) {
                    $parentIds->push((string) $student['parent_id']);
                }

                $student['parent_name'] = $parentIds
                    ->map(fn ($parentId) => $parentLookup->get($parentId)['full_name'] ?? null)
                    ->filter()
                    ->unique()
                    ->implode(', ');

                return $student;
            })->all();

            $totalStudents = count($students);
            $activeStudents = collect($students)->filter(fn ($student) => (bool) ($student['is_active'] ?? true))->count();
            $inactiveStudents = max($totalStudents - $activeStudents, 0);
            $graduatedStudents = 0;

            return response()->json([
                'students' => $students,
                'counts' => [
                    'total' => $totalStudents,
                    'active' => $activeStudents,
                    'inactive' => $inactiveStudents,
                    'graduated' => $graduatedStudents,
                ],
            ]);
        }

        return view('admin.students');
    }

    public function createStudent(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'required_without:full_name|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required_without:full_name|string|max:100',
            'grade_level' => 'required|in:Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6',
            'section' => 'nullable|in:Section A,Section B,Section C,Section D,Section E',
        ]);

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $nameParts = $request->filled('full_name')
            ? NameParts::split((string) $request->input('full_name'))
            : $request->only(['first_name', 'middle_name', 'last_name']);

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$supabaseUrl}/rest/v1/students", [
                ...$nameParts,
                'grade_level' => $request->grade_level,
                'section' => $request->section,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => $response->json()['message'] ?? 'Failed to create student'], 500);
        }

        return response()->json($response->json(), 201);
    }

    public function updateStudent(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'sometimes|required_without:full_name|string|max:100',
            'middle_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|required_without:full_name|string|max:100',
            'grade_level' => 'sometimes|in:Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6',
            'section' => 'sometimes|nullable|in:Section A,Section B,Section C,Section D,Section E',
            'is_active' => 'sometimes|boolean',
        ]);

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        if (array_key_exists('full_name', $data)) {
            $data = array_merge(NameParts::split((string) $data['full_name']), collect($data)->except('full_name')->all());
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($this->getHeaders())
            ->patch("{$supabaseUrl}/rest/v1/students?id=eq.{$id}", $data);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to update student'], 500);
        }

        return response()->json(['message' => 'Student updated successfully']);
    }

    public function deleteStudent(int $id): JsonResponse
    {
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($this->getHeaders())
            ->delete("{$supabaseUrl}/rest/v1/students?id=eq.{$id}");

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to delete student'], 500);
        }

        return response()->json(['message' => 'Student deleted successfully']);
    }

    public function getParents(Request $request)
    {
        $isJsonRequest = $request->expectsJson()
            || $request->ajax()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || str_contains((string) $request->header('Accept', ''), 'application/json')
            || $request->boolean('format.json')
            || $request->query('format') === 'json';

        if ($isJsonRequest) {
            $supabaseUrl = $this->supabaseUrl();

            if (! $supabaseUrl || ! $this->serviceKey()) {
                return response()->json([
                    'message' => 'Supabase is not configured for parent records. Set SUPABASE_URL and SUPABASE_SERVICE_KEY in the Render environment, then redeploy.',
                ], 503);
            }

            /** @var \Illuminate\Http\Client\Response $parentsResponse */
            $parentsResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/parents", [
                    'select' => '*',
                    'order' => 'first_name.asc,last_name.asc',
                ]);

            if ($parentsResponse->failed()) {
                Log::error('Unable to fetch parent records from Supabase.', [
                    'status' => $parentsResponse->status(),
                ]);

                return response()->json([
                    'message' => 'Unable to load parent records from Supabase. Check the server logs for the request failure.',
                ], 502);
            }

            $studentsResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/students", [
                    'select' => 'id,first_name,middle_name,last_name',
                ]);
            if ($studentsResponse->failed()) {
                Log::error('Unable to fetch student records while loading parent records.', [
                    'status' => $studentsResponse->status(),
                ]);

                return response()->json([
                    'message' => 'Unable to load linked student records from Supabase. Check the server logs for the request failure.',
                ], 502);
            }
            $students = collect($studentsResponse->json())->keyBy('id');

            $parentsData = collect($parentsResponse->json());
            $parentAuthIds = $parentsData
                ->filter(fn (array $parent) => trim((string) ($parent['email'] ?? '')) === '' && ! empty($parent['auth_user_id']))
                ->pluck('auth_user_id')
                ->map(fn ($id) => (string) $id)
                ->flip();
            $authEmailsById = [];

            if ($parentAuthIds->isNotEmpty()) {
                $page = 1;
                do {
                    $authUsersResponse = Http::withHeaders($this->getHeaders())
                        ->timeout(10)
                        ->get("{$supabaseUrl}/auth/v1/admin/users", [
                            'page' => $page,
                            'per_page' => 100,
                        ]);

                    if ($authUsersResponse->failed()) {
                        Log::warning('Unable to load Auth emails for parent profiles.', [
                            'status' => $authUsersResponse->status(),
                            'parent_count' => $parentAuthIds->count(),
                        ]);
                        break;
                    }

                    $authUsers = $authUsersResponse->json('users');
                    if (! is_array($authUsers)) {
                        Log::warning('Supabase returned an unexpected Auth user list for parent profiles.', [
                            'status' => $authUsersResponse->status(),
                        ]);
                        break;
                    }

                    foreach ($authUsers as $authUser) {
                        if (! is_array($authUser) || empty($authUser['id']) || ! $parentAuthIds->has((string) $authUser['id'])) {
                            continue;
                        }

                        $authEmail = trim((string) ($authUser['email'] ?? ''));
                        if ($authEmail !== '') {
                            $authEmailsById[(string) $authUser['id']] = $authEmail;
                        }
                    }

                    $page++;
                } while (count($authUsers) === 100 && count($authEmailsById) < $parentAuthIds->count());
            }

            $parents = $parentsData->map(function (array $parent) use ($students, $authEmailsById): array {
                $email = trim((string) ($parent['email'] ?? ''));
                if ($email === '' && ! empty($parent['auth_user_id'])) {
                    $parent['email'] = $authEmailsById[(string) $parent['auth_user_id']] ?? null;
                }

                $phoneNumber = $parent['phone_number'] ?? $parent['mobile_number'] ?? null;
                if (in_array(strtolower(trim((string) $phoneNumber)), ['null', 'undefined'], true)) {
                    $phoneNumber = null;
                }
                $parent['mobile_number'] = $phoneNumber;
                $parent['phone_number'] = $phoneNumber;
                $student = $students->get($parent['student_id'] ?? null);
                $parent['linked_student_name'] = is_array($student)
                    ? trim(implode(' ', array_filter([
                        $student['first_name'] ?? null,
                        $student['middle_name'] ?? null,
                        $student['last_name'] ?? null,
                    ])))
                    : null;

                return $this->decorateName($parent);
            });
            $pendingParents = collect($this->pendingRegistrationRows('parent'))
                ->map(function (array $registration) use ($students): array {
                    $details = $registration['details'];
                    $student = $students->get($details['student_id'] ?? null);
                    $nameParts = NameParts::split((string) ($details['full_name'] ?? ''));

                    return $this->decorateName([
                        'id' => $registration['id'],
                        'parent_id' => $registration['id'],
                        'auth_user_id' => null,
                        ...$nameParts,
                        'student_id' => $details['student_id'] ?? null,
                        'linked_student_name' => is_array($student)
                            ? trim(implode(' ', array_filter([
                                $student['first_name'] ?? null,
                                $student['middle_name'] ?? null,
                                $student['last_name'] ?? null,
                            ])))
                            : ($details['student_name'] ?? null),
                        'relationship' => $details['relationship'] ?? null,
                        'phone_number' => $registration['phone_number'],
                        'mobile_number' => $registration['phone_number'],
                        'email' => $details['email'] ?? null,
                        'is_approved' => false,
                        'is_active' => true,
                        'is_pending_registration' => true,
                        'created_at' => $registration['created_at'],
                    ]);
                });
            $parents = $parents->concat($pendingParents)->all();
            $totalParents = count($parents);
            $approvedParents = collect($parents)->filter(fn ($p) => (bool) ($p['is_approved'] ?? false))->count();
            $pendingParents = collect($parents)
                ->filter(fn ($p) => ! (bool) ($p['is_approved'] ?? false) && ($p['is_active'] ?? true) !== false)
                ->count();

            return response()->json([
                'parents' => $parents,
                'counts' => [
                    'total' => $totalParents,
                    'total_parents' => $totalParents,
                    'approved_parents' => $approvedParents,
                    'pending_parents' => $pendingParents,
                    'linked_to_students' => collect($parents)->filter(fn ($parent) => ! empty($parent['linked_student_name']))->count(),
                ],
            ]);
        }

        return view('admin.parents');
    }

    public function createParent(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'required_without:full_name|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required_without:full_name|string|max:100',
            'phone_number' => 'required_without:mobile_number|string|max:20',
            'mobile_number' => 'nullable|string|max:20',
            'relationship' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'student_id' => 'required|integer',
        ]);

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $nameParts = $request->filled('full_name')
            ? NameParts::split((string) $request->input('full_name'))
            : $request->only(['first_name', 'middle_name', 'last_name']);

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$supabaseUrl}/rest/v1/parents", [
                ...$nameParts,
                'phone_number' => PhoneNumber::normalize($request->input('phone_number') ?? $request->input('mobile_number')),
                'relationship' => $request->relationship,
                'email' => $request->input('email'),
                'student_id' => $request->student_id,
                'is_active' => true,
                'is_approved' => false,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to create parent'], 500);
        }

        return response()->json($response->json(), 201);
    }

    public function updateParent(Request $request, string $id, SmsService $smsService): JsonResponse
    {
        $data = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'sometimes|required_without:full_name|string|max:100',
            'middle_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|required_without:full_name|string|max:100',
            'phone_number' => 'sometimes|required_without:mobile_number|string|max:20',
            'mobile_number' => 'sometimes|nullable|string|max:20',
            'relationship' => 'sometimes|string|max:50',
            'email' => 'sometimes|nullable|email|max:255',
            'student_id' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
            'is_approved' => 'sometimes|boolean',
        ]);

        if (array_key_exists('is_approved', $data)) {
            $pendingResponse = $this->reviewPendingRegistration($id, 'parent', (bool) $data['is_approved'], $smsService);
            if ($pendingResponse) {
                return $pendingResponse;
            }

            if (! (bool) $data['is_approved']) {
                return $this->deleteDeclinedAccount('parents', $id, 'auth_user_id', 'parent', $smsService);
            }
        }

        if (array_key_exists('phone_number', $data)) {
            $data['phone_number'] = $data['phone_number'] ?? ($data['mobile_number'] ?? null);
        }

        if (array_key_exists('mobile_number', $data) && ! array_key_exists('phone_number', $data)) {
            $data['phone_number'] = $data['mobile_number'];
        }

        if (array_key_exists('phone_number', $data)) {
            $data['phone_number'] = PhoneNumber::normalize($data['phone_number']);
        }

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        $existingParent = null;
        if (array_key_exists('is_approved', $data)) {
            $existingResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/parents", [
                    'select' => '*',
                    'id' => 'eq.'.urlencode($id),
                    'limit' => 1,
                ]);
            if ($existingResponse->failed()) {
                return response()->json(['error' => 'Unable to load the parent phone number before approval.'], 500);
            }
            $existingParent = collect($existingResponse->json())->first();
            if (! is_array($existingParent)) {
                return response()->json(['error' => 'Parent account not found.'], 404);
            }
        }

        if (array_key_exists('full_name', $data)) {
            $data = array_merge(NameParts::split((string) $data['full_name']), collect($data)->except('full_name')->all());
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($this->getHeaders())
            ->patch("{$supabaseUrl}/rest/v1/parents?id=eq.{$id}", $data);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to update parent'], 500);
        }

        if (array_key_exists('is_approved', $data)
            && (bool) ($existingParent['is_approved'] ?? false) !== (bool) $data['is_approved']) {
            return $this->accountDecisionResponse(
                $smsService,
                isset($existingParent['phone_number'])
                    ? (string) $existingParent['phone_number']
                    : (isset($existingParent['mobile_number']) ? (string) $existingParent['mobile_number'] : null),
                'parent',
                (bool) $data['is_approved'],
                'Parent account status updated.'
            );
        }

        return response()->json(['message' => 'Parent updated successfully']);
    }

    public function deleteParent(string $id): JsonResponse
    {
        $pendingDeleted = DB::table('pending_registrations')
            ->where('id', $id)
            ->where('role', 'parent')
            ->delete();
        if ($pendingDeleted > 0) {
            return response()->json(['message' => 'Pending parent registration deleted.']);
        }

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($this->getHeaders())
            ->delete("{$supabaseUrl}/rest/v1/parents?id=eq.{$id}");

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to delete parent'], 500);
        }

        return response()->json(['message' => 'Parent deleted successfully']);
    }

    private function getStaffStats(): array
    {
        try {
            $supabaseUrl = $this->supabaseUrl();
            if (! $supabaseUrl || ! $this->serviceKey()) {
                return ['total' => 0, 'approved' => 0, 'pending' => 0, 'administrators' => 0];
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/staff", ['select' => '*']);

            if ($response->failed()) {
                return ['total' => 0, 'approved' => 0, 'pending' => 0, 'administrators' => 0];
            }

            $staff = $response->json();
            $total = count($staff);
            $approved = 0;
            $pending = 0;
            $administrators = 0;

            foreach ($staff as $member) {
                if ((bool) ($member['is_approved'] ?? false)) {
                    $approved++;
                } else {
                    $pending++;
                }

                if (in_array($member['role'] ?? '', ['admin', 'supervisor'], true)) {
                    $administrators++;
                }
            }

            $pendingRegistrations = count($this->pendingRegistrationRows('staff'));

            $total += $pendingRegistrations;
            $pending += $pendingRegistrations;

            return ['total' => $total, 'approved' => $approved, 'pending' => $pending, 'administrators' => $administrators];
        } catch (\Exception $e) {
            Log::warning('Error fetching staff stats: ' . $e->getMessage());

            return ['total' => 0, 'approved' => 0, 'pending' => 0, 'administrators' => 0];
        }
    }

    public function getStaff(Request $request)
    {
        $isJsonRequest = $request->expectsJson()
            || $request->ajax()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || str_contains((string) $request->header('Accept', ''), 'application/json')
            || $request->boolean('format.json')
            || $request->query('format') === 'json';

        if ($isJsonRequest) {
            $supabaseUrl = $this->supabaseUrl();
            if (! $supabaseUrl || ! $this->serviceKey()) {
                return response()->json(['staff' => []], 200);
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/staff", [
                    'select' => '*',
                    'order' => 'first_name.asc,last_name.asc',
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch staff'], 500);
            }

            $staff = collect($response->json())->map(fn (array $member) => $this->decorateName($member));
            $pendingStaff = collect($this->pendingRegistrationRows('staff'))
                ->map(function (array $registration): array {
                    $details = $registration['details'];
                    $nameParts = NameParts::split((string) ($details['full_name'] ?? ''));

                    return $this->decorateName([
                        'id' => $registration['id'],
                        ...$nameParts,
                        'email' => null,
                        'phone_number' => $registration['phone_number'],
                        'is_approved' => false,
                        'is_active' => true,
                        'is_pending_registration' => true,
                        'created_at' => $registration['created_at'],
                    ]);
                });

            return response()->json($staff->concat($pendingStaff)->all());
        }

        $staffStats = $this->getStaffStats();

        return view('admin.staff', $staffStats);
    }

    public function updateStaff(Request $request, string $id, SmsService $smsService): JsonResponse
    {
        $data = $request->validate([
            'full_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'phone_number' => 'sometimes|nullable|string|max:50',
            'is_approved' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'role' => 'sometimes|in:staff,supervisor',
        ]);

        if (array_key_exists('is_approved', $data)) {
            $pendingResponse = $this->reviewPendingRegistration($id, 'staff', (bool) $data['is_approved'], $smsService);
            if ($pendingResponse) {
                return $pendingResponse;
            }

            if (! (bool) $data['is_approved']) {
                return $this->deleteDeclinedAccount('staff', $id, null, 'staff', $smsService);
            }
        }

        if (array_key_exists('full_name', $data)) {
            $data = array_merge(NameParts::split($data['full_name']), collect($data)->except('full_name')->all());
        }

        if (array_key_exists('phone_number', $data)) {
            $data['phone_number'] = PhoneNumber::normalize($data['phone_number']);
        }

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        $existingResponse = Http::withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/staff", [
                'select' => 'email,is_approved,phone_number',
                'id' => 'eq.'.urlencode($id),
                'limit' => 1,
            ]);
        $existingStaff = $existingResponse->successful() ? collect($existingResponse->json())->first() : null;
        if (array_key_exists('is_approved', $data) && ! is_array($existingStaff)) {
            return response()->json(['error' => 'Unable to load the staff account before approval.'], 500);
        }

        $oldEmail = is_array($existingStaff) ? ($existingStaff['email'] ?? null) : null;
        $emailChanged = array_key_exists('email', $data)
            && strtolower(trim((string) $data['email'])) !== strtolower(trim((string) $oldEmail));

        if ($emailChanged) {
            if (! $oldEmail) {
                return response()->json(['error' => 'Unable to verify the current staff email.'], 404);
            }

            $authResponse = Http::withHeaders($this->getHeaders())
                ->put("{$supabaseUrl}/auth/v1/admin/users/".urlencode($id), [
                    'email' => $data['email'],
                    'email_confirm' => true,
                ]);

            if ($authResponse->failed()) {
                return response()->json(['error' => 'Failed to update staff sign-in email.'], 500);
            }
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($this->getHeaders())
            ->patch("{$supabaseUrl}/rest/v1/staff?id=eq.{$id}", $data);

        if ($response->failed()) {
            if ($emailChanged) {
                $rollbackResponse = Http::withHeaders($this->getHeaders())
                    ->put("{$supabaseUrl}/auth/v1/admin/users/".urlencode($id), [
                        'email' => $oldEmail,
                        'email_confirm' => true,
                    ]);

                if ($rollbackResponse->failed()) {
                    Log::error('Staff profile update failed and the Auth email could not be restored.', [
                        'staff_id' => $id,
                        'response' => $rollbackResponse->body(),
                    ]);
                }
            }

            return response()->json(['error' => 'Failed to update staff'], 500);
        }

        if (array_key_exists('is_approved', $data) && is_array($existingStaff)
            && (bool) $existingStaff['is_approved'] !== (bool) $data['is_approved']
            && ! empty($existingStaff['email'])) {
            $approved = (bool) $data['is_approved'];
            $subject = $approved ? 'Your staff account was approved' : 'Your staff account was declined';
            $message = $approved
                ? "Your Orion Christian Academy staff account has been approved. You can now sign in at ".url('/staff/login').'.'
                : 'Your Orion Christian Academy staff account was declined. Please contact an administrator if you believe this is incorrect.';

            try {
                Mail::raw($message, function ($mail) use ($existingStaff, $subject): void {
                    $mail->to($existingStaff['email'])->subject($subject);
                });
            } catch (\Throwable $mailError) {
                Log::warning('Staff approval email could not be sent: '.$mailError->getMessage());
            }
        }

        if (array_key_exists('is_approved', $data) && is_array($existingStaff)
            && (bool) ($existingStaff['is_approved'] ?? false) !== (bool) $data['is_approved']) {
            return $this->accountDecisionResponse(
                $smsService,
                isset($existingStaff['phone_number']) ? (string) $existingStaff['phone_number'] : null,
                'staff',
                (bool) $data['is_approved'],
                'Staff account status updated.'
            );
        }

        return response()->json(['message' => 'Staff member updated successfully']);
    }

    public function deleteStaff(string $id): JsonResponse
    {
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        $staffResponse = Http::withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/staff", [
                'select' => 'id,is_approved',
                'id' => 'eq.'.urlencode($id),
                'limit' => 1,
            ]);

        $staff = $staffResponse->successful() ? collect($staffResponse->json())->first() : null;
        if (! is_array($staff)) {
            return response()->json(['error' => 'Staff account not found'], 404);
        }

        if (($staff['is_approved'] ?? false) !== true) {
            return response()->json(['error' => 'Only approved staff accounts can be deleted.'], 422);
        }

        $deleteProfileResponse = Http::withHeaders($this->getHeaders())
            ->delete("{$supabaseUrl}/rest/v1/staff?id=eq.".urlencode($id));

        if ($deleteProfileResponse->failed()) {
            return response()->json(['error' => 'Failed to delete staff profile'], 500);
        }

        $deleteAuthResponse = Http::withHeaders($this->getHeaders())
            ->delete("{$supabaseUrl}/auth/v1/admin/users/".urlencode($id));

        if ($deleteAuthResponse->failed()) {
            Log::warning('Staff profile deleted but Auth user deletion failed: '.$deleteAuthResponse->body());

            return response()->json([
                'error' => 'Staff profile deleted, but the Supabase Auth account could not be deleted.',
            ], 500);
        }

        return response()->json(['message' => 'Staff account deleted successfully.']);
    }

    public function getPickupReports(Request $request): JsonResponse
    {
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/pickups", [
                'select' => 'id,student_id,picked_at,students(id,first_name,middle_name,last_name,grade_level,section)',
                'order' => 'picked_at.desc',
            ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch pickups'], 500);
        }

        $pickups = $response->json();
        $grouped = collect($pickups)->groupBy(function ($pickup) {
            return Carbon::parse($pickup['picked_at'])->setTimezone(config('app.timezone'))->format('Y-m-d');
        });

        return response()->json([
            'total' => count($pickups),
            'by_date' => $grouped,
        ]);
    }

    public function getQRCodes(Request $request): View
    {
        return view('admin.qr-codes');
    }

    public function generateQRCode(Request $request): JsonResponse
    {
        $request->validate(['parent_id' => 'required|integer']);

        try {
            $supabaseUrl = $this->supabaseUrl();
            if (! $supabaseUrl || ! $this->serviceKey()) {
                return response()->json(['error' => 'Supabase configuration is missing'], 500);
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$supabaseUrl}/rest/v1/qr_codes", [
                    'parent_id' => $request->parent_id,
                    'status' => 'active',
                    'created_at' => now()->toIso8601String(),
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to generate QR code'], 500);
            }

            return response()->json(['message' => 'QR code generated successfully', 'data' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('QR code generation failed: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to generate QR code'], 500);
        }
    }

    public function deleteQRCode(int $id): JsonResponse
    {
        try {
            $supabaseUrl = $this->supabaseUrl();
            if (! $supabaseUrl || ! $this->serviceKey()) {
                return response()->json(['error' => 'Supabase configuration is missing'], 500);
            }

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders($this->getHeaders())
                ->delete("{$supabaseUrl}/rest/v1/qr_codes?id=eq.{$id}");

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to delete QR code'], 500);
            }

            return response()->json(['message' => 'QR code deleted successfully']);
        } catch (\Exception $e) {
            Log::error('QR code deletion failed: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to delete QR code'], 500);
        }
    }

    private function filterEntryRecords(array $records, string $selectedDate, string $selectedParentId, string $selectedStudentId, string $selectedStaff, string $searchTerm, array $parentsById = [], array $studentsById = []): array
    {
        return collect($records)->filter(function (array $record) use ($selectedDate, $selectedParentId, $selectedStudentId, $selectedStaff, $searchTerm, $parentsById, $studentsById): bool {
            return $this->recordMatchesFilter($record, $selectedDate, $selectedParentId, $selectedStudentId, $selectedStaff, $searchTerm, $parentsById, $studentsById);
        })->values()->all();
    }

    private function studentDisplayName(array $student): string
    {
        return NameParts::display($student);
    }

    private function studentDisplayClass(array $student): string
    {
        return collect([
            $student['grade_level'] ?? null,
            $student['section'] ?? null,
        ])->filter()->implode(' - ');
    }

    private function recordMatchesFilter(array $record, string $selectedDate, string $selectedParentId, string $selectedStudentId, string $selectedStaff, string $searchTerm, array $parentsById = [], array $studentsById = []): bool
    {
        $entryTime = $record['entry_time'] ?? null;
        if (! empty($selectedDate) && $selectedDate !== 'all') {
            $recordDate = $entryTime ? Carbon::parse($entryTime)->setTimezone(config('app.timezone'))->format('Y-m-d') : '';
            if ($recordDate !== $selectedDate) {
                return false;
            }
        }

        if ($selectedParentId !== '' && (string) ($record['parent_id'] ?? '') !== (string) $selectedParentId) {
            return false;
        }

        if ($selectedStudentId !== '' && (string) ($record['student_id'] ?? '') !== (string) $selectedStudentId) {
            return false;
        }

        if ($selectedStaff !== '' && (string) ($record['verified_by'] ?? '') !== (string) $selectedStaff) {
            return false;
        }

        if ($searchTerm !== '') {
            $normalized = strtolower($searchTerm);
            $studentId = (int) ($record['student_id'] ?? 0);
            $parentId = (int) ($record['parent_id'] ?? 0);
            $parent = $parentsById[$parentId] ?? [];
            $student = $studentsById[$studentId] ?? [];
            $parentName = $parent['full_name'] ?? '';
            $studentName = $this->studentDisplayName($student);
            $verifiedBy = (string) ($record['verified_by'] ?? '');
            $haystack = strtolower(implode(' ', [$parentName, $studentName, $verifiedBy]));

            if (! str_contains($haystack, $normalized)) {
                return false;
            }
        }

        return true;
    }

    private function emptyEntryRecordsView(array $overrides = []): array
    {
        $defaults = [
            'entryRows' => [],
            'totalSuccessfulEntries' => 0,
            'todayEntries' => 0,
            'averageEntryTime' => '0:00 AM',
            'uniqueParents' => 0,
            'dateOptions' => [],
            'parentOptions' => [],
            'studentOptions' => [],
            'staffOptions' => [],
            'selectedDate' => '',
            'selectedParentId' => '',
            'selectedStudentId' => '',
            'selectedStaff' => '',
            'searchTerm' => '',
        ];

        return array_merge($defaults, $overrides);
    }

    private function fetchEntryRecordsData(string $supabaseUrl): array
    {
        /** @var \Illuminate\Http\Client\Response $recordsResponse */
        $recordsResponse = Http::timeout(10)->withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/entry_records", [
                'select' => 'id,entry_time,parent_id,student_id,verified_by,status',
                'order' => 'entry_time.desc',
            ]);

        $records = $recordsResponse->successful() ? $recordsResponse->json() : [];
        $records = collect($records)->map(function (array $record): array {
            $record['source'] = 'entry-records';

            return $record;
        })->all();

        // Staff scans are stored in pickups. Merge them during the transition
        // so both portals show the same history without duplicating exact rows.
        $pickupsResponse = Http::timeout(10)->withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/pickups", [
                'select' => 'id,student_id,parent_id,picked_at',
                'order' => 'picked_at.desc',
            ]);

        if (! $pickupsResponse->successful()) {
            $pickupsResponse = Http::timeout(10)->withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/pickups", [
                    'select' => 'id,student_id,picked_at',
                    'order' => 'picked_at.desc',
                ]);
        }

        if ($pickupsResponse->successful()) {
            $existingKeys = collect($records)->mapWithKeys(function (array $record): array {
                $key = implode('|', [
                    (string) ($record['student_id'] ?? ''),
                    (string) ($record['parent_id'] ?? ''),
                    (string) ($record['entry_time'] ?? ''),
                ]);

                return [$key => true];
            });

            $pickupRecords = collect($pickupsResponse->json())->map(function (array $pickup): array {
                return [
                    'id' => $pickup['id'] ?? 0,
                    'entry_time' => $pickup['picked_at'] ?? null,
                    'parent_id' => $pickup['parent_id'] ?? null,
                    'student_id' => $pickup['student_id'] ?? null,
                    'verified_by' => 'Staff Scanner',
                    'status' => 'successful',
                    'source' => 'pickups',
                ];
            })->reject(function (array $record) use ($existingKeys): bool {
                $key = implode('|', [
                    (string) ($record['student_id'] ?? ''),
                    (string) ($record['parent_id'] ?? ''),
                    (string) ($record['entry_time'] ?? ''),
                ]);

                return $existingKeys->has($key);
            })->all();

            $records = array_merge($records, $pickupRecords);
        }

        /** @var \Illuminate\Http\Client\Response $parentsResponse */
        $parentsResponse = Http::timeout(10)->withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/parents", [
                'select' => 'id,first_name,middle_name,last_name,phone_number',
                'order' => 'first_name.asc,last_name.asc',
            ]);

        /** @var \Illuminate\Http\Client\Response $studentsResponse */
        $studentsResponse = Http::timeout(10)->withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/students", [
                'select' => 'id,first_name,middle_name,last_name,grade_level,section',
                'order' => 'first_name.asc,last_name.asc',
            ]);

        return [
            $records,
            $parentsResponse->successful() ? collect($parentsResponse->json())->map(fn (array $parent) => $this->decorateName($parent))->all() : [],
            $studentsResponse->successful() ? collect($studentsResponse->json())->map(fn (array $student) => $this->decorateName($student))->all() : [],
        ];
    }

    private function resolveEntryRecordFilters(Request $request): array
    {
        return [
            'selectedDate' => trim((string) $request->input('date_range', '')),
            'selectedParentId' => trim((string) $request->input('parent_id', '')),
            'selectedStudentId' => trim((string) $request->input('student_id', '')),
            'selectedStaff' => trim((string) $request->input('verified_by', '')),
            'searchTerm' => trim((string) $request->input('search', '')),
        ];
    }

    private function buildEntryRows(array $filteredRecords, array $studentsById, array $parentsById): array
    {
        $entryRows = [];

        foreach ($filteredRecords as $record) {
            $entryTime = $record['entry_time'] ?? null;
            $dateTime = $entryTime ? Carbon::parse($entryTime)->setTimezone(config('app.timezone')) : null;
            $studentId = (int) ($record['student_id'] ?? 0);
            $parentId = (int) ($record['parent_id'] ?? 0);
            $student = $studentsById[$studentId] ?? [];
            $parent = $parentsById[$parentId] ?? [];
            $status = strtolower((string) ($record['status'] ?? 'successful'));
            $statusLabel = in_array($status, ['successful', 'success', 'verified'], true) ? 'Successful' : ucfirst($status);

            $entryRows[] = [
                'id' => $record['id'] ?? 0,
                'source' => $record['source'] ?? 'entry-records',
                'date' => $dateTime ? $dateTime->format('F j, Y') : 'N/A',
                'date_iso' => $dateTime ? $dateTime->format('Y-m-d') : '',
                'time' => $dateTime ? $dateTime->format('g:i A') : 'N/A',
                'parent_name' => $parent['full_name'] ?? 'N/A',
                'parent_mobile' => $parent['mobile_number'] ?? '',
                'student_name' => $this->studentDisplayName($student) ?: 'N/A',
                'student_class' => $this->studentDisplayClass($student),
                'verified_by' => $record['verified_by'] ?? 'N/A',
                'status' => $statusLabel,
                'status_key' => $status,
                'parent_id' => $parentId,
                'student_id' => $studentId,
                'entry_time' => $entryTime,
            ];
        }

        return $entryRows;
    }

    private function buildEntryRecordOptions(array $records, array $parents, array $students): array
    {
        $dateOptions = collect($records)
            ->map(function (array $record): ?array {
                $entryTime = $record['entry_time'] ?? null;
                if (! $entryTime) {
                    return null;
                }

                $formatted = Carbon::parse($entryTime)->setTimezone(config('app.timezone'));

                return [
                    'value' => $formatted->format('Y-m-d'),
                    'label' => $formatted->format('F j, Y'),
                ];
            })
            ->filter()
            ->unique('value')
            ->sortByDesc('value')
            ->values()
            ->all();

        $parentOptions = collect($parents)
            ->map(function (array $parent): array {
                return [
                    'value' => (string) ($parent['id'] ?? ''),
                    'label' => $parent['full_name'] ?? 'N/A',
                ];
            })
            ->filter(fn ($option) => $option['value'] !== '')
            ->sortBy('label')
            ->values()
            ->all();

        $studentOptions = collect($students)
            ->map(function (array $student): array {
                return [
                    'value' => (string) ($student['id'] ?? ''),
                    'label' => (($this->studentDisplayName($student) ?: 'N/A') . ' - ' . ($this->studentDisplayClass($student) ?: 'N/A')),
                ];
            })
            ->filter(fn ($option) => $option['value'] !== '')
            ->sortBy('label')
            ->values()
            ->all();

        $staffOptions = collect($records)
            ->pluck('verified_by')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'dateOptions' => $dateOptions,
            'parentOptions' => $parentOptions,
            'studentOptions' => $studentOptions,
            'staffOptions' => $staffOptions,
        ];
    }

    private function calculateEntryRecordStats(array $entryRows): array
    {
        $totalSuccessfulEntries = collect($entryRows)->filter(function ($row) {
            $status = strtolower((string) ($row['status_key'] ?? 'successful'));

            return in_array($status, ['successful', 'success', 'verified'], true);
        })->count();

        $todayEntries = collect($entryRows)->filter(function ($row) {
            return ! empty($row['date_iso']) && $row['date_iso'] === Carbon::today()->format('Y-m-d');
        })->count();

        $averageEntryTime = '0:00 AM';
        if (! empty($entryRows)) {
            $seconds = collect($entryRows)
                ->map(function ($row) {
                    if (empty($row['entry_time'])) {
                        return 0;
                    }

                    $entryTime = Carbon::parse($row['entry_time'])->setTimezone(config('app.timezone'));

                    return $entryTime->hour * 3600 + $entryTime->minute * 60 + $entryTime->second;
                })
                ->average();

            if ($seconds !== null && is_numeric($seconds)) {
                $averageSeconds = (int) round((float) $seconds);
                $averageEntryTime = gmdate('g:i A', $averageSeconds);
            }
        }

        $uniqueParents = collect($entryRows)->pluck('parent_id')->filter(fn ($value) => (int) $value > 0)->unique()->count();

        return [
            'totalSuccessfulEntries' => $totalSuccessfulEntries,
            'todayEntries' => $todayEntries,
            'averageEntryTime' => $averageEntryTime,
            'uniqueParents' => $uniqueParents,
        ];
    }

    public function getEntryRecords(Request $request): View
    {
        try {
            $supabaseUrl = $this->supabaseUrl();
            if (! $supabaseUrl || ! $this->serviceKey()) {
                return view('admin.entry-records', $this->emptyEntryRecordsView());
            }

            [$records, $parents, $students] = $this->fetchEntryRecordsData($supabaseUrl);
            $studentsById = collect($students)->keyBy('id')->all();
            $parentsById = collect($parents)->keyBy('id')->all();
            $selectedOptions = $this->resolveEntryRecordFilters($request);
            $filteredRecords = $this->filterEntryRecords(
                $records,
                $selectedOptions['selectedDate'],
                $selectedOptions['selectedParentId'],
                $selectedOptions['selectedStudentId'],
                $selectedOptions['selectedStaff'],
                $selectedOptions['searchTerm'],
                $parentsById,
                $studentsById
            );

            $entryRows = $this->buildEntryRows($filteredRecords, $studentsById, $parentsById);
            $stats = $this->calculateEntryRecordStats($entryRows);
            $options = $this->buildEntryRecordOptions($records, $parents, $students);

            return view('admin.entry-records', array_merge(
                $this->emptyEntryRecordsView($selectedOptions),
                $stats,
                $options,
                ['entryRows' => $entryRows]
            ));
        } catch (\Exception $e) {
            Log::warning('Supabase connection error in getEntryRecords: ' . $e->getMessage());

            return view('admin.entry-records', $this->emptyEntryRecordsView());
        }
    }

    public function getProfile(Request $request): View
    {
        $adminId = Session::get('admin_id');
        $admin = $adminId ? $this->findSupabaseAdmin($adminId) : null;

        return view('admin.profile', ['admin' => $admin]);
    }

    public function getSupportMessages(): View
    {
        return view('admin.support-messages', [
            'messages' => SupportMessage::with('replies')->latest()->paginate(20),
            'openCount' => SupportMessage::whereIn('status', ['open', 'in_progress'])->count(),
        ]);
    }

    public function replyToSupportMessage(Request $request, SupportMessage $supportMessage)
    {
        $validated = $request->validate([
            'reply' => 'required|string|max:5000',
        ]);

        $supportMessage->replies()->create([
            'sender_type' => 'admin',
            'sender_id' => (string) Session::get('admin_id'),
            'body' => $validated['reply'],
        ]);
        $supportMessage->update(['status' => 'in_progress']);

        return back()->with('status', 'Reply sent to the parent.');
    }

    public function updateSupportMessageStatus(Request $request, SupportMessage $supportMessage)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved',
        ]);

        $supportMessage->update(['status' => $validated['status']]);

        return back()->with('status', 'Support message status updated.');
    }

    public function deleteSupportMessage(SupportMessage $supportMessage)
    {
        $supportMessage->delete();

        return back()->with('status', 'Support message deleted.');
    }

    public function deleteEntryRecord(string $source, int $id)
    {
        $supabaseUrl = $this->supabaseUrl();
        $table = match ($source) {
            'entry-records' => 'entry_records',
            'pickups' => 'pickups',
            default => null,
        };

        if (! $supabaseUrl || ! $this->serviceKey() || ! $table) {
            return back()->with('error', 'Unable to delete the entry record.');
        }

        $response = Http::withHeaders($this->getHeaders())
            ->delete("{$supabaseUrl}/rest/v1/{$table}?id=eq.{$id}");

        if ($response->failed()) {
            Log::warning('Failed to delete entry record: '.$response->body());

            return back()->with('error', 'Unable to delete the entry record.');
        }

        return back()->with('success', 'Entry record deleted successfully.');
    }

    public function deleteAllEntryRecords()
    {
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return back()->with('error', 'Supabase configuration is missing.');
        }

        foreach (['entry_records', 'pickups'] as $table) {
            $response = Http::withHeaders($this->getHeaders())
                ->delete("{$supabaseUrl}/rest/v1/{$table}?id=not.is.null");

            if ($response->failed()) {
                $supabaseError = $response->json();
                if ($table === 'entry_records' && ($supabaseError['code'] ?? null) === 'PGRST205') {
                    continue;
                }

                Log::warning("Failed to delete all {$table}: ".$response->body());

                return back()->with('error', 'Unable to delete all entry records.');
            }
        }

        return back()->with('success', 'All entry records deleted successfully.');
    }

    public function updateProfile(Request $request)
    {
        $adminId = Session::get('admin_id');
        $admin = $adminId ? $this->findSupabaseAdmin($adminId) : null;

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:50',
        ]);

        $phoneNumber = PhoneNumber::normalize((string) $request->input('phone_number'));
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $phoneNumber)) {
            return back()->withErrors(['phone_number' => 'Enter a valid mobile number.'])->withInput();
        }

        $data = array_merge(
            NameParts::split((string) $request->input('full_name')),
            ['phone_number' => $phoneNumber]
        );
        if (! $this->updateSupabaseAdmin($admin->id, $data)) {
            return back()->withErrors(['phone_number' => 'Unable to update the admin profile. Confirm the phone_number column exists and the number is not already in use.'])->withInput();
        }
        $admin->fill($data);
        Session::put('admin_name', $admin->full_name ?: $admin->phone_number);

        return redirect()->back()->with('status', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $adminId = Session::get('admin_id');
        $admin = $adminId ? $this->findSupabaseAdmin($adminId) : null;

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (! $admin->verifyPassword($request->current_password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $admin->password_hash = $request->new_password;
        $this->updateSupabaseAdmin($admin->id, ['password_hash' => $admin->password_hash]);

        return back()->with('status', 'Password updated successfully.');
    }

    private function authenticateSupabaseAdmin(string $identifier, string $password, string $expectedUserId, string $type): bool
    {
        $supabaseUrl = $this->supabaseUrl();
        $anonKey = env('VITE_SUPABASE_ANON_KEY') ?: env('SUPABASE_ANON_KEY') ?: getenv('VITE_SUPABASE_ANON_KEY') ?: getenv('SUPABASE_ANON_KEY');

        if (! $supabaseUrl || ! $anonKey) {
            return false;
        }

        $response = Http::withHeaders([
            'apikey' => $anonKey,
            'Authorization' => 'Bearer '.$anonKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("{$supabaseUrl}/auth/v1/token?grant_type=password", [
            $type => $identifier,
            'password' => $password,
        ]);

        if ($response->failed()) {
            Log::warning('Failed to authenticate admin with Supabase Auth: '.$response->body());

            return false;
        }

        $user = $response->json('user');
        $actualUserId = is_array($user) && isset($user['id']) ? (string) $user['id'] : null;

        return $actualUserId !== null && $actualUserId === $expectedUserId;
    }

    private function findSupabaseAdmin(string $id): ?Admin
    {
        return $this->findSupabaseAdminWithQuery(['id' => 'eq.'.$id]);
    }

    private function findSupabaseAdminByPhone(string $phoneNumber): ?Admin
    {
        $phoneNumber = PhoneNumber::normalize($phoneNumber);
        $candidates = [$phoneNumber];
        if (str_starts_with($phoneNumber, '+639')) {
            $candidates[] = '0'.substr($phoneNumber, 3);
        }

        foreach (array_unique($candidates) as $candidatePhone) {
            $admin = $this->findSupabaseAdminWithQuery(['phone_number' => 'eq.'.$candidatePhone]);
            if ($admin && PhoneNumber::normalize($admin->phone_number) === $phoneNumber) {
                return $admin;
            }
        }

        return null;
    }

    private function findSupabaseAdminWithQuery(array $query): ?Admin
    {
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return null;
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(10)
                ->get("{$supabaseUrl}/rest/v1/admin", array_merge(['select' => '*', 'limit' => 1], $query));
        } catch (\Throwable $exception) {
            Log::warning('Failed to fetch admin from Supabase: '.$exception->getMessage());

            return null;
        }

        if ($response->failed()) {
            Log::warning('Failed to fetch admin from Supabase: '.$response->body());
            return null;
        }

        $record = collect($response->json())->first();
        return is_array($record) ? (new Admin)->forceFill($record) : null;
    }

    private function updateSupabaseAdmin(string $id, array $data): bool
    {
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return false;
        }

        return Http::withHeaders($this->getHeaders())
            ->timeout(10)
            ->patch("{$supabaseUrl}/rest/v1/admin?id=eq.{$id}", $data)
            ->successful();
    }
}