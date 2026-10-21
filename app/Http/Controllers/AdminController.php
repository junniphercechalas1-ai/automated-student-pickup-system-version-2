<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Services\SmsService;
use App\Support\NameParts;
use App\Support\ParentStudentLinks;
use App\Support\PhoneNumber;
use App\Support\UsernameIdentity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    private function syncParentStudentLinks(string $supabaseUrl, string $serviceKey, int|string $parentId, array $studentIds, ?string $relationship): ?Response
    {
        $existingResponse = ParentStudentLinks::forParent($supabaseUrl, $serviceKey, $parentId);
        if ($existingResponse->failed()) {
            return $existingResponse;
        }

        $existingLinks = collect($existingResponse->json());
        $studentIds = collect($studentIds)
            ->map(fn ($studentId): int => (int) $studentId)
            ->filter(fn (int $studentId): bool => $studentId > 0)
            ->unique()
            ->values();

        foreach ($studentIds as $studentId) {
            $existingRelationship = $existingLinks
                ->first(fn (array $link): bool => (int) ($link['students_id'] ?? 0) === $studentId)['relationship'] ?? null;
            $response = ParentStudentLinks::upsert(
                $supabaseUrl,
                $serviceKey,
                $parentId,
                $studentId,
                $relationship ?? $existingRelationship
            );
            if ($response->failed()) {
                return $response;
            }
        }

        $desired = $studentIds->map(fn (int $studentId): string => (string) $studentId);
        foreach ($existingLinks->pluck('students_id') as $existingStudentId) {
            if (! $desired->contains((string) $existingStudentId)) {
                $response = ParentStudentLinks::delete($supabaseUrl, $serviceKey, $parentId, $existingStudentId);
                if ($response->failed()) {
                    return $response;
                }
            }
        }

        return null;
    }

    private function authUsernamesById(array $userIds, string $context): array
    {
        $userIds = array_fill_keys(array_filter(array_map('strval', $userIds)), true);
        if ($userIds === []) {
            return [];
        }

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return [];
        }

        $usernames = [];
        $page = 1;
        do {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(10)
                ->get("{$supabaseUrl}/auth/v1/admin/users", [
                    'page' => $page,
                    'per_page' => 100,
                ]);

            if ($response->failed()) {
                Log::warning("Unable to load Auth usernames for {$context}.", [
                    'status' => $response->status(),
                    'account_count' => count($userIds),
                ]);

                break;
            }

            $users = $response->json('users');
            if (! is_array($users)) {
                Log::warning("Supabase returned an unexpected Auth user list for {$context}.", [
                    'status' => $response->status(),
                ]);

                break;
            }

            foreach ($users as $user) {
                if (! is_array($user) || empty($user['id']) || ! isset($userIds[(string) $user['id']])) {
                    continue;
                }

                $username = UsernameIdentity::normalize((string) data_get($user, 'user_metadata.username', ''));
                if ($username !== '') {
                    $usernames[(string) $user['id']] = $username;
                }
            }

            $page++;
        } while (count($users) === 100 && count($usernames) < count($userIds));

        return $usernames;
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
            $smsService->sendNotification($phoneNumber, $message, 'sms_account_decision');

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
                ? (config('services.sms.driver') === 'supabase_queue'
                    ? ' SMS notification queued for delivery.'
                    : ' SMS notification sent.')
                : ' SMS notification could not be sent; check SMS configuration and delivery.'),
            'notification_sent' => $notificationSent,
        ]);
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
                    'auth_users_id' => $authUserId,
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
                'auth_users_id' => $authUserId,
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
        $validated = $request->validate([
            'username' => 'required|string|max:30',
            'password' => 'required|string',
        ]);

        $username = UsernameIdentity::normalize($validated['username']);
        if (! UsernameIdentity::isValid($username)) {
            return back()->withErrors(['username' => 'Enter a valid username (3–30 letters, numbers, dots, underscores, or hyphens).'])->withInput();
        }

        $authUser = $this->authenticateSupabaseAdmin(
            UsernameIdentity::authEmail($username),
            $validated['password']
        );
        $authUserId = is_array($authUser) ? (string) ($authUser['id'] ?? '') : '';
        $authUsername = UsernameIdentity::normalize((string) data_get($authUser, 'user_metadata.username', ''));
        if ($authUserId === ''
            || $authUsername !== $username
            || data_get($authUser, 'user_metadata.role') !== 'admin') {
            return back()->withErrors(['username' => 'Invalid username or password.'])->withInput();
        }

        $admin = $this->findSupabaseAdminByAuthUserId($authUserId);
        if (! $admin || ! $admin->is_active) {
            Log::warning('Admin login rejected: Auth user has no active admin profile.', [
                'auth_users_id' => $authUserId,
            ]);

            return back()->withErrors(['username' => 'Invalid username or password.'])->withInput();
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

        if (empty($admin->auth_users_id)) {
            Log::error('Admin password reset rejected because the profile is not linked to a Supabase Auth user.', [
                'admin_id' => $admin->id,
            ]);

            return response()->json(['message' => 'This administrator account is not linked to Supabase Auth. Contact the system administrator.'], 503);
        }

        $authUpdate = Http::withHeaders($this->getHeaders())
            ->timeout(10)
            ->put("{$supabaseUrl}/auth/v1/admin/users/".urlencode((string) $admin->auth_users_id), [
                'password' => $validated['password'],
            ]);
        if ($authUpdate->failed()) {
            Log::warning('Unable to update admin Auth password after SMS verification.', [
                'admin_id' => $admin->id,
                'status' => $authUpdate->status(),
            ]);

            return response()->json(['message' => 'Unable to update the password. Please try again.'], 503);
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
                    'select' => 'id,first_name,middle_name,last_name',
                ]);
            if ($parentsResponse->failed()) {
                return response()->json(['error' => 'Failed to fetch parent records'], 500);
            }
            $parents = collect($parentsResponse->json())
                ->map(fn (array $parent) => $this->decorateName($parent))
                ->all();
            $parentLinksResponse = ParentStudentLinks::forParents(
                $supabaseUrl,
                (string) $this->serviceKey(),
                collect($parents)->pluck('id')->all()
            );
            if ($parentLinksResponse->failed()) {
                return response()->json(['error' => 'Failed to fetch parent-student associations'], 500);
            }
            $parentLookup = collect($parents)->keyBy(fn ($parent) => (string) ($parent['id'] ?? ''));

            $parentIdsByStudent = collect($parentLinksResponse->json())
                ->groupBy(fn (array $link) => (string) ($link['students_id'] ?? ''));

            $students = collect($students)->map(function (array $student) use ($parentLookup, $parentIdsByStudent): array {
                $student = $this->decorateName($student);
                $parentIds = collect($student['parent_ids'] ?? [])
                    ->map(fn ($parentId) => (string) $parentId);
                $parentIds = $parentIds->merge(
                    $parentIdsByStudent->get((string) ($student['id'] ?? ''), collect())
                        ->pluck('parents_id')
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
            ->withHeaders(['Prefer' => 'return=representation'])
            ->delete("{$supabaseUrl}/rest/v1/students?id=eq.{$id}");

        if ($response->failed()) {
            Log::error('Unable to delete student profile from Supabase.', [
                'student_id' => $id,
                'status' => $response->status(),
            ]);

            return response()->json(['error' => $response->json('message') ?? 'Failed to delete student from Supabase.'], 502);
        }

        if (! is_array($response->json()) || $response->json() === []) {
            return response()->json(['error' => 'Student was not found in Supabase; no record was deleted.'], 404);
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
            $parentLinksResponse = ParentStudentLinks::forParents(
                $supabaseUrl,
                (string) $this->serviceKey(),
                $parentsData->pluck('id')->all()
            );
            if ($parentLinksResponse->failed()) {
                Log::error('Unable to fetch parent-student associations while loading parent records.', [
                    'status' => $parentLinksResponse->status(),
                ]);

                return response()->json([
                    'message' => 'Unable to load parent-student associations from Supabase.',
                ], 502);
            }
            $linksByParent = collect($parentLinksResponse->json())
                ->groupBy(fn (array $link): string => (string) ($link['parents_id'] ?? ''));
            $parentUsernamesById = $this->authUsernamesById(
                $parentsData->pluck('auth_users_id')->all(),
                'parent profiles'
            );

            $parents = $parentsData->map(function (array $parent) use ($students, $linksByParent, $parentUsernamesById): array {
                $parent['username'] = $parentUsernamesById[(string) ($parent['auth_users_id'] ?? '')] ?? null;
                $parent['is_pending_registration'] = ($parent['is_approved'] ?? false) !== true
                    && ($parent['is_active'] ?? true) !== false;

                $phoneNumber = $parent['phone_number'] ?? $parent['mobile_number'] ?? null;
                if (in_array(strtolower(trim((string) $phoneNumber)), ['null', 'undefined'], true)) {
                    $phoneNumber = null;
                }
                $parent['mobile_number'] = $phoneNumber;
                $parent['phone_number'] = $phoneNumber;
                $linkedStudents = $linksByParent
                    ->get((string) ($parent['id'] ?? ''), collect())
                    ->map(function (array $link) use ($students): ?array {
                        $student = $students->get($link['students_id'] ?? null);
                        if (! is_array($student)) {
                            return null;
                        }

                        return [
                            'id' => (int) $student['id'],
                            'name' => trim(implode(' ', array_filter([
                                $student['first_name'] ?? null,
                                $student['middle_name'] ?? null,
                                $student['last_name'] ?? null,
                            ]))),
                        ];
                    })
                    ->filter()
                    ->values();
                $parent['student_ids'] = $linkedStudents->pluck('id')->all();
                $parent['student_id'] = $parent['student_ids'][0] ?? null;
                $parent['linked_student_name'] = $linkedStudents->pluck('name')->implode(', ') ?: null;

                return $this->decorateName($parent);
            });
            $parents = $parents->all();
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

    public function getPendingRegistrations(): View
    {
        return view('admin.pending-registrations');
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
            'student_ids' => 'required_without:student_id|array|min:1',
            'student_ids.*' => 'integer',
            'student_id' => 'required_without:student_ids|integer',
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
            ->withHeaders(['Prefer' => 'return=representation'])
            ->post("{$supabaseUrl}/rest/v1/parents", [
                ...$nameParts,
                'phone_number' => PhoneNumber::normalize($request->input('phone_number') ?? $request->input('mobile_number')),
                'relationship' => $request->relationship,
                'is_active' => true,
                'is_approved' => false,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => $response->json('message') ?? 'Failed to create parent'], 500);
        }

        $parent = collect($response->json())->first();
        if (! is_array($parent) || empty($parent['id'])) {
            return response()->json(['error' => 'Parent profile was created but Supabase returned no parent ID.'], 502);
        }

        $studentIds = $request->input('student_ids', [$request->input('student_id')]);
        $linkError = $this->syncParentStudentLinks(
            $supabaseUrl,
            (string) $this->serviceKey(),
            (int) $parent['id'],
            is_array($studentIds) ? $studentIds : [],
            (string) $request->input('relationship')
        );
        if ($linkError) {
            Log::error('New parent profile student links could not be saved.', [
                'parent_id' => $parent['id'],
                'response' => $linkError->body(),
            ]);

            return response()->json(['error' => 'Parent profile was created, but its student links could not be saved.'], 502);
        }

        $parent['student_ids'] = collect($studentIds)->map(fn ($studentId): int => (int) $studentId)->unique()->values()->all();

        return response()->json($parent, 201);
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
            'student_ids' => 'sometimes|array|min:1',
            'student_ids.*' => 'integer',
            'student_id' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
            'is_approved' => 'sometimes|boolean',
        ]);

        if (array_key_exists('is_approved', $data)) {
            if (! (bool) $data['is_approved']) {
                return $this->deleteDeclinedAccount('parents', $id, 'auth_users_id', 'parent', $smsService);
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

        $studentIds = $data['student_ids'] ?? (isset($data['student_id']) ? [$data['student_id']] : null);
        unset($data['student_ids'], $data['student_id']);

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($this->getHeaders())
            ->patch("{$supabaseUrl}/rest/v1/parents?id=eq.{$id}", $data);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to update parent'], 500);
        }

        if (is_array($studentIds) || array_key_exists('relationship', $data)) {
            if (! is_array($studentIds)) {
                $currentLinksResponse = ParentStudentLinks::forParent($supabaseUrl, (string) $this->serviceKey(), $id);
                if ($currentLinksResponse->failed()) {
                    return response()->json(['error' => 'Parent details were updated, but their student relationships could not be loaded.'], 502);
                }
                $studentIds = collect($currentLinksResponse->json())->pluck('students_id')->all();
            }

            $linkError = $this->syncParentStudentLinks(
                $supabaseUrl,
                (string) $this->serviceKey(),
                $id,
                $studentIds,
                isset($data['relationship']) ? (string) $data['relationship'] : null
            );
            if ($linkError) {
                Log::error('Parent profile student links could not be updated.', [
                    'parent_id' => $id,
                    'response' => $linkError->body(),
                ]);

                return response()->json(['error' => 'Parent details were updated, but their student links could not be saved.'], 502);
            }
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
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return response()->json(['error' => 'Supabase configuration is missing'], 500);
        }

        $profileResponse = Http::withHeaders($this->getHeaders())
            ->get("{$supabaseUrl}/rest/v1/parents", [
                'select' => 'id,auth_users_id',
                'id' => 'eq.'.urlencode($id),
                'limit' => 1,
            ]);

        if ($profileResponse->failed()) {
            Log::error('Unable to load parent profile before account deletion.', [
                'parent_id' => $id,
                'status' => $profileResponse->status(),
            ]);

            return response()->json(['error' => 'Unable to verify the linked parent account before deleting it.'], 502);
        }

        $parent = collect($profileResponse->json())->first();
        if (! is_array($parent)) {
            return response()->json(['error' => 'Parent account not found.'], 404);
        }

        $authUserId = $parent['auth_users_id'] ?? null;
        if (is_string($authUserId) && $authUserId !== '') {
            $authDeleteResponse = Http::withHeaders($this->getHeaders())
                ->delete("{$supabaseUrl}/auth/v1/admin/users/".urlencode($authUserId));

            if ($authDeleteResponse->failed() && $authDeleteResponse->status() !== 404) {
                Log::error('Unable to delete parent Auth user.', [
                    'parent_id' => $id,
                    'auth_users_id' => $authUserId,
                    'status' => $authDeleteResponse->status(),
                ]);

                return response()->json([
                    'error' => 'The parent sign-in account could not be deleted. The parent profile was kept; please retry or contact an administrator.',
                ], 502);
            }
        }

        $profileDeleteResponse = Http::withHeaders($this->getHeaders())
            ->withHeaders(['Prefer' => 'return=representation'])
            ->delete("{$supabaseUrl}/rest/v1/parents?id=eq.".urlencode($id));

        if ($profileDeleteResponse->failed()) {
            Log::error('Parent Auth user was deleted but the parent profile could not be removed.', [
                'parent_id' => $id,
                'auth_users_id' => $authUserId,
                'status' => $profileDeleteResponse->status(),
            ]);

            return response()->json([
                'error' => 'The parent sign-in account was deleted, but the profile could not be removed. Contact an administrator.',
            ], 502);
        }

        if (! is_array($profileDeleteResponse->json()) || $profileDeleteResponse->json() === []) {
            $remainingProfileResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/parents", [
                    'select' => 'id',
                    'id' => 'eq.'.urlencode($id),
                    'limit' => 1,
                ]);

            if ($remainingProfileResponse->failed() || $remainingProfileResponse->json() !== []) {
                Log::error('Unable to confirm parent profile deletion in Supabase.', [
                    'parent_id' => $id,
                    'auth_users_id' => $authUserId,
                    'status' => $remainingProfileResponse->status(),
                ]);

                return response()->json([
                    'error' => 'The parent sign-in account was deleted, but the parent profile could not be confirmed as removed. Refresh the list and retry if the profile remains.',
                ], 502);
            }
        }

        $authUserDeleted = is_string($authUserId) && $authUserId !== ''
            && $authDeleteResponse->status() !== 404;

        return response()->json([
            'message' => 'Parent account and linked sign-in account deleted successfully.',
            'auth_user_deleted' => $authUserDeleted,
        ]);
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

            $staffRecords = collect($response->json());
            $staffUsernamesById = $this->authUsernamesById($staffRecords->pluck('auth_users_id')->all(), 'staff profiles');
            $staff = $staffRecords->map(function (array $member) use ($staffUsernamesById): array {
                $member['username'] = $staffUsernamesById[(string) ($member['auth_users_id'] ?? '')] ?? null;
                $member['is_pending_registration'] = ($member['is_approved'] ?? false) !== true
                    && ($member['is_active'] ?? true) !== false;

                return $this->decorateName($member);
            });
            return response()->json($staff->all());
        }

        $staffStats = $this->getStaffStats();

        return view('admin.staff', $staffStats);
    }

    public function updateStaff(Request $request, string $id, SmsService $smsService): JsonResponse
    {
        $data = $request->validate([
            'full_name' => 'sometimes|required|string|max:255',
            'phone_number' => 'sometimes|nullable|string|max:50',
            'is_approved' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'role' => 'sometimes|in:staff,supervisor',
        ]);

        if (array_key_exists('is_approved', $data)) {
            if (! (bool) $data['is_approved']) {
                return $this->deleteDeclinedAccount('staff', $id, 'auth_users_id', 'staff', $smsService);
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
                'select' => 'is_approved,phone_number',
                'id' => 'eq.'.urlencode($id),
                'limit' => 1,
            ]);
        if ($existingResponse->failed()) {
            return response()->json(['error' => 'Unable to load the staff account before updating it.'], 500);
        }
        $existingStaff = collect($existingResponse->json())->first();
        if (! is_array($existingStaff)) {
            return response()->json(['error' => 'Staff account not found.'], 404);
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders($this->getHeaders())
            ->patch("{$supabaseUrl}/rest/v1/staff?id=eq.{$id}", $data);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to update staff'], 500);
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
                'select' => 'id,auth_users_id,is_approved',
                'id' => 'eq.'.urlencode($id),
                'limit' => 1,
            ]);

        if ($staffResponse->failed()) {
            Log::error('Unable to load staff profile before account deletion.', [
                'staff_id' => $id,
                'status' => $staffResponse->status(),
            ]);

            return response()->json(['error' => 'Unable to verify the linked staff account before deleting it.'], 502);
        }

        $staff = collect($staffResponse->json())->first();
        if (! is_array($staff)) {
            return response()->json(['error' => 'Staff account not found'], 404);
        }

        if (($staff['is_approved'] ?? false) !== true) {
            return response()->json(['error' => 'Only approved staff accounts can be deleted.'], 422);
        }

        $authUserId = (string) ($staff['auth_users_id'] ?? '');
        if ($authUserId === '') {
            return response()->json(['error' => 'Staff profile has no linked Supabase Auth user.'], 409);
        }

        $deleteAuthResponse = Http::withHeaders($this->getHeaders())
            ->delete("{$supabaseUrl}/auth/v1/admin/users/".urlencode($authUserId));

        if ($deleteAuthResponse->failed() && $deleteAuthResponse->status() !== 404) {
            Log::error('Unable to delete staff Auth user.', [
                'staff_id' => $id,
                'auth_users_id' => $authUserId,
                'status' => $deleteAuthResponse->status(),
            ]);

            return response()->json([
                'error' => 'The staff sign-in account could not be deleted. The staff profile was kept; please retry or contact an administrator.',
            ], 502);
        }

        $deleteProfileResponse = Http::withHeaders($this->getHeaders())
            ->withHeaders(['Prefer' => 'return=representation'])
            ->delete("{$supabaseUrl}/rest/v1/staff?id=eq.".urlencode($id));

        if ($deleteProfileResponse->failed()) {
            Log::error('Staff Auth user was deleted but the staff profile could not be removed.', [
                'staff_id' => $id,
                'auth_users_id' => $authUserId,
                'status' => $deleteProfileResponse->status(),
            ]);

            return response()->json([
                'error' => 'The staff sign-in account was deleted, but the profile could not be removed. Contact an administrator.',
            ], 502);
        }

        if (! is_array($deleteProfileResponse->json()) || $deleteProfileResponse->json() === []) {
            $remainingProfileResponse = Http::withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/staff", [
                    'select' => 'id',
                    'id' => 'eq.'.urlencode($id),
                    'limit' => 1,
                ]);

            if ($remainingProfileResponse->failed() || $remainingProfileResponse->json() !== []) {
                Log::error('Unable to confirm staff profile deletion in Supabase.', [
                    'staff_id' => $id,
                    'auth_users_id' => $authUserId,
                    'status' => $remainingProfileResponse->status(),
                ]);

                return response()->json([
                    'error' => 'The staff sign-in account was deleted, but the staff profile could not be confirmed as removed. Refresh the list and retry if the profile remains.',
                ], 502);
            }
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
                'select' => 'id,students_id,parents_id,picked_at,students(id,first_name,middle_name,last_name,grade_level,section)',
                'order' => 'picked_at.desc',
            ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch pickups'], 500);
        }

        $pickups = collect($response->json())->map(function (array $pickup): array {
            $pickup['student_id'] = $pickup['students_id'] ?? null;
            $pickup['parent_id'] = $pickup['parents_id'] ?? null;
            unset($pickup['students_id']);
            unset($pickup['parents_id']);

            return $pickup;
        })->all();
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
                'select' => 'id,students_id,parents_id,picked_at',
                'order' => 'picked_at.desc',
            ]);

        if (! $pickupsResponse->successful()) {
            $pickupsResponse = Http::timeout(10)->withHeaders($this->getHeaders())
                ->get("{$supabaseUrl}/rest/v1/pickups", [
                    'select' => 'id,students_id,picked_at',
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
                    'parent_id' => $pickup['parents_id'] ?? null,
                    'student_id' => $pickup['students_id'] ?? null,
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
        $username = $admin && $admin->auth_users_id
            ? $this->getSupabaseAdminAuthUsername((string) $admin->auth_users_id)
            : null;

        return view('admin.profile', ['admin' => $admin, 'username' => $username]);
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

        if (empty($admin->auth_users_id)) {
            Log::error('Admin password change rejected because the profile is not linked to a Supabase Auth user.', [
                'admin_id' => $admin->id,
            ]);

            return back()->withErrors(['password' => 'This administrator account is not linked to Supabase Auth. Contact the system administrator.']);
        }

        $authUsername = $this->getSupabaseAdminAuthUsername((string) $admin->auth_users_id);
        if (! $authUsername) {
            return back()->withErrors(['password' => 'Unable to verify the linked Supabase Auth account. Contact the system administrator.']);
        }

        $authUser = $this->authenticateSupabaseAdmin(
            UsernameIdentity::authEmail($authUsername),
            $request->current_password,
            (string) $admin->auth_users_id
        );
        if (! $authUser) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            return back()->withErrors(['password' => 'Supabase Auth is not configured. Please contact the system administrator.']);
        }

        $authUpdate = Http::withHeaders($this->getHeaders())
            ->timeout(10)
            ->put("{$supabaseUrl}/auth/v1/admin/users/".urlencode((string) $admin->auth_users_id), [
                'password' => $request->new_password,
            ]);
        if ($authUpdate->failed()) {
            Log::warning('Unable to update admin Auth password from the profile page.', [
                'admin_id' => $admin->id,
                'status' => $authUpdate->status(),
            ]);

            return back()->withErrors(['password' => 'Unable to save the new password. Please try again or contact an administrator.']);
        }

        return back()->with('status', 'Password updated successfully.');
    }

    private function authenticateSupabaseAdmin(string $authEmail, string $password, ?string $expectedUserId = null): ?array
    {
        $supabaseUrl = $this->supabaseUrl();
        $anonKey = env('VITE_SUPABASE_ANON_KEY') ?: env('SUPABASE_ANON_KEY') ?: getenv('VITE_SUPABASE_ANON_KEY') ?: getenv('SUPABASE_ANON_KEY');

        if (! $supabaseUrl || ! $anonKey) {
            return null;
        }

        $response = Http::withHeaders([
            'apikey' => $anonKey,
            'Authorization' => 'Bearer '.$anonKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("{$supabaseUrl}/auth/v1/token?grant_type=password", [
            'email' => $authEmail,
            'password' => $password,
        ]);

        if ($response->failed()) {
            Log::warning('Failed to authenticate admin with Supabase Auth: '.$response->body());

            return null;
        }

        $user = $response->json('user');
        $actualUserId = is_array($user) && isset($user['id']) ? (string) $user['id'] : null;

        if (! is_array($user)
            || ! $actualUserId
            || ($expectedUserId !== null && $actualUserId !== $expectedUserId)) {
            return null;
        }

        return $user;
    }

    private function getSupabaseAdminAuthUsername(string $authUserId): ?string
    {
        $supabaseUrl = $this->supabaseUrl();
        if (! $supabaseUrl || ! $this->serviceKey()) {
            Log::error('Unable to resolve admin Auth username because Supabase is not configured.');

            return null;
        }

        $response = Http::withHeaders($this->getHeaders())
            ->timeout(10)
            ->get("{$supabaseUrl}/auth/v1/admin/users/".urlencode($authUserId));
        if ($response->failed()) {
            Log::warning('Unable to fetch linked admin Auth user.', [
                'auth_users_id' => $authUserId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $authUser = $response->json();
        $username = UsernameIdentity::normalize((string) data_get($authUser, 'user_metadata.username', ''));
        if (! is_array($authUser)
            || (string) ($authUser['id'] ?? '') !== $authUserId
            || ! UsernameIdentity::isValid($username)
            || data_get($authUser, 'user_metadata.role') !== 'admin') {
            Log::warning('Linked admin Auth user has no valid admin username metadata.', [
                'auth_users_id' => $authUserId,
            ]);

            return null;
        }

        return $username;
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
            $candidates[] = substr($phoneNumber, 1);
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

    private function findSupabaseAdminByAuthUserId(string $authUserId): ?Admin
    {
        return $this->findSupabaseAdminWithQuery([
            'auth_users_id' => 'eq.'.$authUserId,
        ]);
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