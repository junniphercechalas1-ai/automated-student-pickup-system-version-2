<?php

use App\Http\Controllers\PickerController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureAdminIsLoggedIn;
use App\Services\SmsService;
use App\Support\NameParts;
use App\Support\PhoneNumberRegistrationGuard;
use App\Support\PendingRegistrationSchema;
use App\Support\UsernameIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\PhoneNumber;

$makeParentQrToken = static function (int|string $parentId, int|string $studentId, ?string $refreshId = null): array {
    $generatedAt = now();
    $expiresAt = $generatedAt->copy()->addMinutes(10);
    $payload = [
        'parent_id' => (int) $parentId,
        'student_id' => (int) $studentId,
        'refresh_id' => $refreshId ?: Str::uuid()->toString(),
        'generated_at' => $generatedAt->toIso8601String(),
        'expires_at' => $expiresAt->toIso8601String(),
    ];
    $encodedPayload = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encodedPayload, (string) env('APP_KEY'));

    return [
        'token' => $encodedPayload.'.'.$signature,
        'expires_at' => $expiresAt->toIso8601String(),
    ];
};

if (! function_exists('saveParentProfileToSupabase')) {
    function saveParentProfileToSupabase(string $supabaseUrl, string $serviceKey, array $payload, ?string $parentId = null)
    {
        $normalizedPhoneNumber = PhoneNumber::normalize($payload['phone_number'] ?? $payload['mobile_number'] ?? null);
        $attempts = [
            'phone_number',
            'mobile_number',
        ];

        $lastResponse = null;

        foreach ($attempts as $fieldName) {
            $requestPayload = $payload;
            unset($requestPayload['mobile_number'], $requestPayload['phone_number']);
            $requestPayload[$fieldName] = $normalizedPhoneNumber;

            $headers = [
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Prefer' => 'return=representation',
            ];

            if ($parentId !== null) {
                $response = Http::withHeaders($headers)
                    ->patch("{$supabaseUrl}/rest/v1/parents?id=eq.".$parentId, $requestPayload);
            } else {
                $response = Http::withHeaders($headers)
                    ->post("{$supabaseUrl}/rest/v1/parents", $requestPayload);
            }

            $lastResponse = $response;

            if ($response->successful()) {
                return $response;
            }

            $message = strtolower((string) ($response->json('message') ?? $response->body() ?? ''));
            if (str_contains($message, 'mobile_number') && $fieldName === 'mobile_number') {
                continue;
            }

            if (str_contains($message, 'phone_number') && $fieldName === 'phone_number') {
                continue;
            }

            return $response;
        }

        return $lastResponse;
    }
}

// ============================================
// ADMIN ROUTES
// ============================================
Route::prefix('admin')->group(function () {
    // Public admin routes (no auth required)
    Route::get('/login', [AdminController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminController::class, 'login'])->name('admin.login.post');
    Route::post('/password-reset/request', [AdminController::class, 'requestPasswordReset'])->name('admin.password-reset.request');
    Route::post('/password-reset/complete', [AdminController::class, 'completePasswordReset'])->name('admin.password-reset.complete');

    // Protected admin routes (auth required)
    Route::middleware(EnsureAdminIsLoggedIn::class)->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/logout', [AdminController::class, 'logout'])->name('admin.logout');

        // Students management
        Route::get('/students', [AdminController::class, 'getStudents'])->name('admin.students');
        Route::post('/students', [AdminController::class, 'createStudent'])->name('admin.students.store');
        Route::patch('/students/{id}', [AdminController::class, 'updateStudent'])->name('admin.students.update');
        Route::delete('/students/{id}', [AdminController::class, 'deleteStudent'])->name('admin.students.delete');

        // Parents management
        Route::get('/parents', [AdminController::class, 'getParents'])->name('admin.parents');
        Route::post('/parents', [AdminController::class, 'createParent'])->name('admin.parents.store');
        Route::patch('/parents/{id}', [AdminController::class, 'updateParent'])->name('admin.parents.update');
        Route::delete('/parents/{id}', [AdminController::class, 'deleteParent'])->name('admin.parents.delete');

        // Parent account support messages
        Route::get('/support-messages', [AdminController::class, 'getSupportMessages'])
            ->middleware(\App\Http\Middleware\EnsureSupportMessageTablesExist::class)
            ->name('admin.support-messages');
        Route::post('/support-messages/{supportMessage}/replies', [AdminController::class, 'replyToSupportMessage'])
            ->middleware(\App\Http\Middleware\EnsureSupportMessageTablesExist::class)
            ->name('admin.support-messages.reply');
        Route::patch('/support-messages/{supportMessage}/status', [AdminController::class, 'updateSupportMessageStatus'])
            ->middleware(\App\Http\Middleware\EnsureSupportMessageTablesExist::class)
            ->name('admin.support-messages.status');
        Route::delete('/support-messages/{supportMessage}', [AdminController::class, 'deleteSupportMessage'])
            ->middleware(\App\Http\Middleware\EnsureSupportMessageTablesExist::class)
            ->name('admin.support-messages.delete');

        // Staff management
        Route::get('/staff', [AdminController::class, 'getStaff'])->name('admin.staff');
        Route::patch('/staff/{id}', [AdminController::class, 'updateStaff'])->name('admin.staff.update');
        Route::delete('/staff/{id}', [AdminController::class, 'deleteStaff'])->name('admin.staff.delete');

        // Entry Records
        Route::get('/entry-records', [AdminController::class, 'getEntryRecords'])->name('admin.entry-records');
        Route::delete('/entry-records/{source}/{id}', [AdminController::class, 'deleteEntryRecord'])->name('admin.entry-records.delete');
        Route::delete('/entry-records', [AdminController::class, 'deleteAllEntryRecords'])->name('admin.entry-records.delete-all');

        // Profile management
        Route::get('/profile', [AdminController::class, 'getProfile'])->name('admin.profile');
        Route::patch('/profile', [AdminController::class, 'updateProfile'])->name('admin.profile.update');
        Route::patch('/profile/password', [AdminController::class, 'updatePassword'])->name('admin.profile.password');

        // Reports
        Route::get('/reports/pickups', [AdminController::class, 'getPickupReports'])->name('admin.reports.pickups');
    });
});

// ============================================
// EXISTING ROUTES
// ============================================
Route::get('/supabase/students', function () {
    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase URL or service role key is not configured.',
        ], 500);
    }

    $response = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/students", [
        'select' => 'id,first_name,middle_name,last_name,grade_level,section',
        'order' => 'first_name,last_name',
    ]);

    if ($response->failed()) {
        return response()->json([
            'message' => $response->json('message') ?? 'Failed to load students.',
            'details' => $response->json(),
        ], 500);
    }

    $students = collect($response->json())->map(function (array $student): array {
        $student['full_name'] = NameParts::display($student);
        $student['name'] = $student['full_name'];
        $student['class'] = collect([
            $student['grade_level'] ?? null,
            $student['section'] ?? null,
        ])->filter()->implode(' - ');

        return $student;
    });

    return response()->json($students);
});

Route::post('/supabase/students', function (Request $request) {
    $request->validate([
        'first_name' => 'required|string|max:100',
        'middle_name' => 'nullable|string|max:100',
        'last_name' => 'required|string|max:100',
        'grade_level' => 'required|string|max:100',
        'section' => 'nullable|string|max:100',
    ]);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase URL or service role key is not configured.',
        ], 500);
    }

    $studentPayload = [
        'first_name' => $request->input('first_name'),
        'middle_name' => $request->input('middle_name'),
        'last_name' => $request->input('last_name'),
        'grade_level' => $request->input('grade_level'),
        'section' => $request->input('section'),
    ];

    $studentResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Prefer' => 'return=representation',
    ])->post("{$supabaseUrl}/rest/v1/students", $studentPayload);

    if ($studentResponse->failed()) {
        return response()->json([
            'message' => $studentResponse->json('message') ?? 'Failed to save student.',
            'details' => $studentResponse->json(),
        ], 500);
    }

    return response()->json($studentResponse->json());
});

Route::get('/supabase/student', function (Request $request) {
    $request->validate([
        'id' => 'required|integer',
    ]);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase URL or service role key is not configured.',
        ], 500);
    }

    $response = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/students", [
        'select' => 'id,first_name,middle_name,last_name,grade_level,section',
        'id' => 'eq.'.$request->query('id'),
    ]);

    if ($response->failed()) {
        return response()->json([
            'message' => $response->json('message') ?? 'Unable to resolve student.',
            'details' => $response->json(),
        ], 500);
    }

    $student = collect($response->json())->first();
    if (! $student) {
        return response()->json(['message' => 'Student not found.'], 404);
    }

    $student['full_name'] = NameParts::display($student);
    $student['name'] = $student['full_name'];
    $student['class'] = collect([
        $student['grade_level'] ?? null,
        $student['section'] ?? null,
    ])->filter()->implode(' - ');

    return response()->json($student);
});

Route::get('/supabase/parent', function (Request $request) {
    $request->validate([
        'id' => 'nullable|integer',
    ]);

    if (! $request->filled('id')) {
        return response()->json(['message' => 'Parent ID is required.'], 422);
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase URL or service role key is not configured.',
        ], 500);
    }

    $parentQuery = [
        'select' => 'id,first_name,middle_name,last_name,phone_number,relationship',
    ];
    $parentQuery['id'] = 'eq.'.(string) $request->query('id');

    $response = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/parents", $parentQuery);

    if ($response->failed()) {
        return response()->json([
            'message' => $response->json('message') ?? 'Unable to resolve parent.',
            'details' => $response->json(),
        ], 500);
    }

    $parent = collect($response->json())->first();
    if (! $parent) {
        return response()->json(['message' => 'Parent not found.'], 404);
    }

    $parent['full_name'] = NameParts::display($parent);

    return response()->json($parent);
});

Route::get('/supabase/verify-parent-qr', function (Request $request) {
    $token = (string) $request->query('token', '');
    [$encodedPayload, $signature] = array_pad(explode('.', $token, 2), 2, '');
    $expectedSignature = hash_hmac('sha256', $encodedPayload, (string) env('APP_KEY'));

    if ($encodedPayload === '' || ! hash_equals($expectedSignature, $signature)) {
        return response()->json(['message' => 'Invalid QR code.'], 422);
    }

    $payload = json_decode(base64_decode(strtr($encodedPayload, '-_', '+/')), true);
    if (! is_array($payload) || empty($payload['parent_id']) || empty($payload['student_id'])) {
        return response()->json(['message' => 'Invalid QR payload.'], 422);
    }

    if (! isset($payload['expires_at']) || strtotime((string) $payload['expires_at']) <= time()) {
        return response()->json(['message' => 'QR code expired. Ask the parent to refresh it.'], 410);
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');
    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json(['message' => 'Supabase configuration is missing.'], 500);
    }

    $headers = ['apikey' => $serviceKey, 'Authorization' => 'Bearer '.$serviceKey, 'Accept' => 'application/json'];
    $parentResponse = Http::withHeaders($headers)->get("{$supabaseUrl}/rest/v1/parents", [
        'select' => 'id,first_name,middle_name,last_name,phone_number,relationship,student_id',
        'id' => 'eq.'.(int) $payload['parent_id'],
    ]);
    $studentResponse = Http::withHeaders($headers)->get("{$supabaseUrl}/rest/v1/students", [
        'select' => 'id,first_name,middle_name,last_name,grade_level,section',
        'id' => 'eq.'.(int) $payload['student_id'],
    ]);

    if ($parentResponse->failed() || $studentResponse->failed()) {
        return response()->json(['message' => 'Unable to verify QR code.'], 500);
    }

    $parent = collect($parentResponse->json())->first();
    $student = collect($studentResponse->json())->first();
    if (! $parent || ! $student) {
        return response()->json(['message' => 'Parent or student not found.'], 404);
    }

    if ((int) ($parent['student_id'] ?? 0) !== (int) $student['id']) {
        return response()->json(['message' => 'This student is not linked to the parent.'], 403);
    }

    $parent['full_name'] = NameParts::display($parent);
    $student['full_name'] = NameParts::display($student);

    return response()->json(['parent' => $parent, 'student' => $student]);
});

Route::post('/supabase/pickups', function (Request $request) {
    $request->validate([
        'student_id' => 'required|integer',
        'parent_id' => 'nullable|integer',
    ]);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase URL or service role key is not configured.',
        ], 500);
    }

    $pickupPayload = [
        'student_id' => $request->input('student_id'),
        'picked_at' => now()->toIso8601String(),
    ];

    // Include parent_id if provided (from QR scan)
    if ($request->filled('parent_id')) {
        $pickupPayload['parent_id'] = $request->input('parent_id');
    }

    $pickupResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Prefer' => 'return=representation',
    ])->post("{$supabaseUrl}/rest/v1/pickups", $pickupPayload);

    if ($pickupResponse->failed()) {
        return response()->json([
            'message' => $pickupResponse->json('message') ?? 'Unable to mark pickup.',
            'details' => $pickupResponse->json(),
        ], 500);
    }

    return response()->json($pickupResponse->json());
});

Route::post('/supabase/parent-profile', function (Request $request) {
    $request->validate([
        'user_id' => 'nullable|string',
        'full_name' => 'nullable|string|max:255',
        'first_name' => 'required_without:full_name|string|max:100',
        'middle_name' => 'nullable|string|max:100',
        'last_name' => 'required_without:full_name|string|max:100',
        'mobile_number' => 'required|string',
        'relationship' => 'required|string',
        'student_id' => 'required|string',
        'student_name' => 'nullable|string',
        'student_class' => 'nullable|string',
    ]);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase URL or service role key is not configured. Add SUPABASE_SERVICE_KEY (or SUPABASE_SERVICE_ROLE_KEY) to your .env file from the Supabase project API settings.',
        ], 500);
    }

    $nameParts = $request->filled('full_name')
        ? NameParts::split((string) $request->input('full_name'))
        : $request->only(['first_name', 'middle_name', 'last_name']);

    $parentPayload = [
        ...$nameParts,
        'auth_user_id' => $request->input('user_id'),
        'student_id' => (int) $request->input('student_id'),
        'mobile_number' => PhoneNumber::normalize($request->input('mobile_number')),
        'relationship' => $request->input('relationship'),
        'is_active' => true,
        'is_approved' => false,
    ];

    $parentResponse = saveParentProfileToSupabase($supabaseUrl, $serviceKey, $parentPayload);

    if ($parentResponse->failed()) {
        return response()->json([
            'message' => $parentResponse->json('message') ?? 'Parent insert failed.',
            'details' => $parentResponse->json(),
        ], 500);
    }

    $parentRecord = $parentResponse->json();
    $parent = is_array($parentRecord) ? ($parentRecord[0] ?? null) : $parentRecord;

    if (! $parent || ! isset($parent['id'])) {
        return response()->json([
            'message' => 'Parent was created but no ID was returned by Supabase.',
            'details' => $parentRecord,
        ], 500);
    }

    return response()->json([
        'parent' => $parent,
    ]);
});

Route::post('/supabase/cleanup-stale-parent-user', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
    ]);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase URL or service role key is not configured.',
        ], 500);
    }

    $email = $request->input('email');

    $userResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/auth/v1/admin/users", [
        'email' => 'eq.'.$email,
    ]);

    if ($userResponse->failed()) {
        $userResponse = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ])->get("{$supabaseUrl}/auth/v1/admin/users");
    }

    if ($userResponse->failed()) {
        return response()->json([
            'message' => $userResponse->json('message') ?? 'Unable to query Supabase auth users.',
            'details' => $userResponse->json(),
        ], 500);
    }

    $users = $userResponse->json('users') ?? $userResponse->json();
    if (is_array($users) && isset($users['id'])) {
        $users = [$users];
    }

    if (! is_array($users)) {
        return response()->json([
            'message' => 'Unexpected response from Supabase auth users endpoint.',
            'details' => $users,
        ], 500);
    }

    $authUser = collect($users)
        ->first(fn ($user) => strtolower($user['email'] ?? $user->email ?? '') === strtolower($email));

    if (! is_array($authUser) || empty($authUser['id'])) {
        return response()->json(['exists' => false]);
    }

    $metadata = $authUser['user_metadata'] ?? [];
    $role = strtolower($metadata['role'] ?? $authUser['role'] ?? '');

    if ($role !== 'parent') {
        return response()->json(['exists' => true, 'message' => 'This email is already registered with a non-parent account.']);
    }

    $parentSearch = [
        'select' => 'id',
    ];

    if (! empty($metadata['full_name']) && ! empty($metadata['mobile_number'])) {
        $parentSearch['phone_number'] = 'eq.'.$metadata['mobile_number'];
    }

    if (count($parentSearch) > 1) {
        $parentResponse = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ])->get("{$supabaseUrl}/rest/v1/parents", $parentSearch);

        if ($parentResponse->ok() && ! empty($parentResponse->json())) {
            return response()->json(['exists' => true]);
        }
    }

    $deleteResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->delete("{$supabaseUrl}/auth/v1/admin/users/{$authUser['id']}");

    if ($deleteResponse->failed()) {
        return response()->json([
            'message' => $deleteResponse->json('message') ?? 'Unable to delete stale Supabase auth user.',
            'details' => $deleteResponse->json(),
        ], 500);
    }

    return response()->json(['cleaned' => true, 'deleted_user_id' => $authUser['id']]);
});

Route::post('/supabase/username-login', function (Request $request) {
    $validated = $request->validate([
        'username' => 'required|string|max:30',
        'password' => 'required|string',
        'account_type' => 'required|in:parent,staff',
    ]);
    $username = UsernameIdentity::normalize($validated['username']);
    if (! UsernameIdentity::isValid($username)) {
        return response()->json(['message' => 'Enter a username using 3–30 letters, numbers, dots, underscores, or hyphens.'], 422);
    }

    $ipKey = 'username-login-ip:'.hash('sha256', (string) $request->ip());
    $usernameKey = 'username-login-name:'.hash('sha256', $validated['account_type'].'|'.$username);
    if (RateLimiter::tooManyAttempts($ipKey, 10) || RateLimiter::tooManyAttempts($usernameKey, 5)) {
        return response()->json(['message' => 'Too many sign-in attempts. Please wait before trying again.'], 429);
    }
    RateLimiter::hit($ipKey, 60);
    RateLimiter::hit($usernameKey, 300);

    $supabaseUrl = env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL');
    $anonKey = env('VITE_SUPABASE_ANON_KEY')
        ?: env('SUPABASE_ANON_KEY')
        ?: getenv('VITE_SUPABASE_ANON_KEY')
        ?: getenv('SUPABASE_ANON_KEY');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (! $supabaseUrl || ! $anonKey || ! $serviceKey) {
        Log::error('Username sign-in is unavailable because Supabase configuration is missing.');

        return response()->json(['message' => 'Sign-in is temporarily unavailable. Please contact the administrator.'], 503);
    }

    try {
        $authResponse = Http::withHeaders([
            'apikey' => $anonKey,
            'Authorization' => 'Bearer '.$anonKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(10)->post(rtrim($supabaseUrl, '/').'/auth/v1/token?grant_type=password', [
            'email' => UsernameIdentity::authEmail($username),
            'password' => $validated['password'],
        ]);

        if ($authResponse->failed()) {
            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        $authUser = $authResponse->json('user');
        $authUserId = is_array($authUser) ? ($authUser['id'] ?? null) : null;
        $authRole = is_array($authUser) ? ($authUser['user_metadata']['role'] ?? null) : null;
        if (! is_string($authUserId) || $authRole !== $validated['account_type']) {
            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        $table = $validated['account_type'] === 'parent' ? 'parents' : 'staff';
        $userIdColumn = $validated['account_type'] === 'parent' ? 'auth_user_id' : 'id';
        $profileResponse = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ])->timeout(10)->get(rtrim($supabaseUrl, '/')."/rest/v1/{$table}", [
            'select' => $userIdColumn,
            $userIdColumn => 'eq.'.$authUserId,
            'is_approved' => 'eq.true',
            'is_active' => 'eq.true',
            'limit' => 1,
        ]);

        if ($profileResponse->failed()) {
            Log::error('Unable to verify approved profile during username sign-in.', [
                'role' => $validated['account_type'],
                'status' => $profileResponse->status(),
            ]);

            return response()->json(['message' => 'Sign-in is temporarily unavailable. Please try again.'], 503);
        }

        $profile = collect($profileResponse->json())->first();
        if (! is_array($profile) || (string) ($profile[$userIdColumn] ?? '') !== $authUserId) {
            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        RateLimiter::clear($usernameKey);

        return response()->json([
            'access_token' => $authResponse->json('access_token'),
        ]);
    } catch (\Throwable $exception) {
        Log::error('Username sign-in request failed.', [
            'role' => $validated['account_type'],
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        return response()->json(['message' => 'Sign-in is temporarily unavailable. Please try again.'], 503);
    }
});

Route::post('/supabase/username-activate', function (Request $request) {
    $validated = $request->validate([
        'username' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{2,29}$/'],
        'account_type' => 'required|in:parent,staff',
        'phone_verification_flow_id' => 'required|string|size:48',
    ]);
    $username = UsernameIdentity::normalize($validated['username']);
    $role = $validated['account_type'];
    $flowId = $validated['phone_verification_flow_id'];
    $verificationCacheKey = 'signup-phone-verification:'.$flowId;
    $phoneVerification = Cache::get($verificationCacheKey);
    if (! is_array($phoneVerification)
        || empty($phoneVerification['verified_at'])
        || ($phoneVerification['account_type'] ?? null) !== $role
        || now()->timestamp >= (int) ($phoneVerification['expires_at'] ?? 0)) {
        return response()->json(['message' => 'Verify your registered mobile number before setting a username.'], 422);
    }

    $phoneNumber = (string) ($phoneVerification['phone'] ?? '');
    $supabaseUrl = env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (! $supabaseUrl || ! $serviceKey) {
        Log::error('Username activation is unavailable because Supabase configuration is missing.');

        return response()->json(['message' => 'Username setup is temporarily unavailable. Please contact the administrator.'], 503);
    }

    foreach (DB::table('pending_registrations')->whereIn('status', ['pending', 'processing'])->pluck('details') as $pendingDetails) {
        $pending = json_decode((string) $pendingDetails, true);
        if (is_array($pending)
            && UsernameIdentity::normalize((string) ($pending['username'] ?? '')) === $username) {
            return response()->json(['message' => 'That username is already in use or awaiting approval. Choose another username.'], 409);
        }
    }

    $table = $role === 'parent' ? 'parents' : 'staff';
    $userIdColumn = $role === 'parent' ? 'auth_user_id' : 'id';
    $profile = null;
    $phoneCandidates = [$phoneNumber];
    if (str_starts_with($phoneNumber, '+639')) {
        $phoneCandidates[] = '0'.substr($phoneNumber, 3);
        $phoneCandidates[] = substr($phoneNumber, 1);
    }
    foreach ($role === 'parent' ? ['phone_number', 'mobile_number'] : ['phone_number'] as $phoneColumn) {
        foreach (array_unique($phoneCandidates) as $phoneCandidate) {
            $profileResponse = Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get(rtrim($supabaseUrl, '/')."/rest/v1/{$table}", [
                'select' => $userIdColumn.',is_approved,is_active,'.$phoneColumn,
                $phoneColumn => 'eq.'.$phoneCandidate,
                'is_approved' => 'eq.true',
                'is_active' => 'eq.true',
                'limit' => 1,
            ]);
            if ($profileResponse->successful()) {
                $candidate = collect($profileResponse->json())->first();
                if (is_array($candidate)
                    && PhoneNumber::normalize((string) ($candidate[$phoneColumn] ?? '')) === $phoneNumber) {
                    $profile = $candidate;
                    break 2;
                }
            } elseif (! in_array((int) $profileResponse->status(), [400, 404], true)) {
                Log::warning('Unable to look up approved profile during username activation.', [
                    'role' => $role,
                    'status' => $profileResponse->status(),
                ]);

                return response()->json(['message' => 'Username setup is temporarily unavailable. Please try again.'], 503);
            }
        }
    }

    $authUserId = is_array($profile) ? (string) ($profile[$userIdColumn] ?? '') : '';
    if ($authUserId === '') {
        return response()->json(['message' => 'No approved account was found for that verified number.'], 404);
    }

    $authEmail = UsernameIdentity::authEmail($username);
    $existingAuthResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->timeout(10)->get(rtrim($supabaseUrl, '/').'/auth/v1/admin/users/'.urlencode($authUserId));
    if ($existingAuthResponse->failed()) {
        Log::warning('Unable to fetch existing Auth account during username activation.', [
            'role' => $role,
            'status' => $existingAuthResponse->status(),
        ]);

        return response()->json(['message' => 'Username setup is temporarily unavailable. Please try again.'], 503);
    }

    $existingAuthUser = $existingAuthResponse->json();
    if (! is_array($existingAuthUser)
        || (string) ($existingAuthUser['id'] ?? '') !== $authUserId
        || PhoneNumber::normalize((string) ($existingAuthUser['phone'] ?? '')) !== $phoneNumber) {
        return response()->json(['message' => 'The verified number does not match the account. Contact the administrator.'], 409);
    }

    $existingMetadata = $existingAuthUser['user_metadata'] ?? [];
    $existingUsername = UsernameIdentity::normalize((string) (is_array($existingMetadata) ? ($existingMetadata['username'] ?? '') : ''));
    if ($existingUsername !== '' && $existingUsername !== $username) {
        return response()->json(['message' => 'This account already has a username. Contact the administrator to change it.'], 409);
    }

    $metadata = array_merge(is_array($existingMetadata) ? $existingMetadata : [], [
        'role' => $role,
        'username' => $username,
        'phone_number' => $phoneNumber,
    ]);
    $updateResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ])->timeout(10)->put(rtrim($supabaseUrl, '/').'/auth/v1/admin/users/'.urlencode($authUserId), [
        'email' => $authEmail,
        'email_confirm' => true,
        'user_metadata' => $metadata,
    ]);
    if ($updateResponse->failed()) {
        Log::warning('Supabase rejected the username update for an existing Auth account.', [
            'role' => $role,
            'status' => $updateResponse->status(),
        ]);
        $status = in_array($updateResponse->status(), [409, 422], true) ? 409 : 503;

        return response()->json([
            'message' => $status === 409
                ? 'That username is already in use. Choose another username.'
                : 'Username setup is temporarily unavailable. Please try again.',
        ], $status);
    }

    Cache::forget($verificationCacheKey);

    return response()->json(['message' => 'Username set. You can now sign in with your username and existing password.']);
});

Route::post('/supabase/register-user', function (Request $request, PhoneNumberRegistrationGuard $phoneNumberGuard) {
    $request->validate([
        'full_name' => 'required|string',
        'password' => 'required|string|min:8',
        'username' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{2,29}$/'],
        'phone_number' => 'required_without:mobile_number|string|not_in:null,NULL',
        'mobile_number' => 'nullable|string',
        'email' => 'prohibited',
        'role' => 'required|string|in:parent,staff',
        'relationship' => 'nullable|string',
        'student_id' => 'nullable|string',
        'student_name' => 'nullable|string',
        'student_class' => 'nullable|string',
        'phone_verification_flow_id' => 'required|string|size:48',
    ]);

    $phoneNumber = PhoneNumber::normalize((string) ($request->input('phone_number') ?? $request->input('mobile_number') ?? ''));

    $role = strtolower($request->input('role'));
    $username = UsernameIdentity::normalize((string) $request->input('username'));
    $verificationCacheKey = 'signup-phone-verification:'.$request->input('phone_verification_flow_id');
    $phoneVerification = Cache::get($verificationCacheKey);
    if (! is_array($phoneVerification)
        || empty($phoneVerification['verified_at'])
        || ($phoneVerification['account_type'] ?? null) !== $role
        || ($phoneVerification['phone'] ?? null) !== $phoneNumber
        || now()->timestamp >= (int) ($phoneVerification['expires_at'] ?? 0)) {
        return response()->json(['message' => 'Verify this mobile number before creating your account.'], 422);
    }

    if ($role === 'parent' && ! $request->filled('student_id')) {
        return response()->json(['message' => 'Please select a student before registering.'], 422);
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase URL or service role key is not configured.',
        ], 500);
    }

    try {
        if ($phoneNumberGuard->isRegistered($phoneNumber, $supabaseUrl, $serviceKey)) {
            return response()->json([
                'message' => 'This mobile number is already registered or has a registration awaiting review. Sign in or contact the administrator.',
            ], 409);
        }
    } catch (\Throwable $exception) {
        Log::error('Unable to check for duplicate phone number during registration.', [
            'role' => $role,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => 'Registration is temporarily unavailable while we check this mobile number. Please try again.',
        ], 503);
    }

    $metadata = [
        'full_name' => $request->input('full_name'),
        'username' => $username,
        'phone_number' => $phoneNumber,
        'role' => $role,
    ];

    if ($role === 'parent') {
        $metadata = array_merge($metadata, [
            'relationship' => $request->input('relationship'),
            'student_id' => $request->input('student_id'),
            'student_name' => $request->input('student_name'),
            'student_class' => $request->input('student_class'),
        ]);
    }

    $pendingUsernames = DB::table('pending_registrations')
        ->where('status', 'pending')
        ->pluck('details');
    foreach ($pendingUsernames as $pendingDetails) {
        $pending = json_decode((string) $pendingDetails, true);
        if (is_array($pending)
            && UsernameIdentity::normalize((string) ($pending['username'] ?? '')) === $username) {
            return response()->json(['message' => 'That username is already in use or awaiting approval. Choose another username.'], 409);
        }
    }

    $details = [
        ...$metadata,
        'relationship' => $role === 'parent' ? $request->input('relationship') : null,
        'student_id' => $role === 'parent' ? (int) $request->input('student_id') : null,
    ];
    $registrationId = (string) Str::uuid();

    try {
        DB::table('pending_registrations')->insert([
            'id' => $registrationId,
            'role' => $role,
            'phone_number' => $phoneNumber,
            'encrypted_password' => Crypt::encryptString((string) $request->input('password')),
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Illuminate\Database\QueryException $exception) {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if (in_array($sqlState, ['23000', '23505'], true)) {
            return response()->json(['message' => 'A registration request for this mobile number already exists.'], 409);
        }

        throw $exception;
    }

    Cache::forget($verificationCacheKey);

    return response()->json([
        'request_id' => $registrationId,
        'message' => 'Registration submitted. Your account will be created after administrator approval.',
    ], 202);
});

Route::post('/supabase/phone-verification/request', function (Request $request, SmsService $smsService, PhoneNumberRegistrationGuard $phoneNumberGuard) {
    $validated = $request->validate([
        'account_type' => 'required|in:parent,staff',
        'phone_number' => 'required|string|max:50',
    ]);

    $phoneNumber = PhoneNumber::normalize($validated['phone_number']);
    if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $phoneNumber)) {
        return response()->json(['message' => 'Enter a valid mobile number.'], 422);
    }

    $supabaseUrl = env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (! $supabaseUrl || ! $serviceKey) {
        Log::error('Phone verification is unavailable because Supabase configuration is missing.');

        return response()->json(['message' => 'Phone verification is temporarily unavailable. Please try again.'], 503);
    }

    try {
        if ($phoneNumberGuard->isRegistered($phoneNumber, $supabaseUrl, $serviceKey)) {
            return response()->json([
                'message' => 'This mobile number is already registered or has a registration awaiting review. Sign in or contact the administrator.',
            ], 409);
        }
    } catch (\Throwable $exception) {
        Log::error('Unable to check for duplicate phone number before verification.', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => 'Phone verification is temporarily unavailable while we check this number. Please try again.',
        ], 503);
    }

    if (config('services.sms.driver') === 'log') {
        return response()->json(['message' => 'SMS delivery is not configured.'], 503);
    }

    $ipKey = 'signup-phone-verify-ip:'.hash('sha256', (string) $request->ip());
    $phoneHash = hash('sha256', $phoneNumber);
    $phoneKey = 'signup-phone-verify-number:'.$phoneHash;
    if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($phoneKey, 3)) {
        return response()->json(['message' => 'Too many verification requests. Please wait before trying again.'], 429);
    }
    RateLimiter::hit($ipKey, 60);
    RateLimiter::hit($phoneKey, 600);

    $flowId = Str::random(48);
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = now()->addMinutes(5);
    $cacheKey = 'signup-phone-verification:'.$flowId;
    Cache::put($cacheKey, [
        'phone' => $phoneNumber,
        'phone_hash' => $phoneHash,
        'account_type' => $validated['account_type'],
        'otp_hash' => Hash::make($otp),
        'attempts' => 0,
        'expires_at' => $expiresAt->timestamp,
    ], $expiresAt);

    try {
        $smsService->send($phoneNumber, 'Your Orion account verification code is '.$otp.'. It expires in 5 minutes. Do not share this code.');
    } catch (\Throwable $exception) {
        Cache::forget($cacheKey);
        Log::warning('Unable to send signup phone verification SMS.', ['exception' => get_class($exception)]);

        return response()->json(['message' => 'Unable to send the verification code. Please try again later.'], 503);
    }

    $maskedPhone = substr($phoneNumber, 0, 3).str_repeat('*', max(0, strlen($phoneNumber) - 7)).substr($phoneNumber, -4);

    return response()->json([
        'flow_id' => $flowId,
        'masked_phone' => $maskedPhone,
        'message' => 'Verification code sent to '.$maskedPhone.'. Enter it here to verify your number before creating your account.',
    ]);
});

Route::post('/supabase/phone-verification/confirm', function (Request $request) {
    $validated = $request->validate([
        'flow_id' => 'required|string|size:48',
        'otp' => 'required|digits:6',
        'email' => 'prohibited',
    ]);

    $cacheKey = 'signup-phone-verification:'.$validated['flow_id'];
    $verification = Cache::get($cacheKey);
    if (! is_array($verification) || now()->timestamp >= (int) ($verification['expires_at'] ?? 0)) {
        Cache::forget($cacheKey);

        return response()->json(['message' => 'Verification code is invalid or expired. Request a new code.'], 422);
    }

    if (($verification['attempts'] ?? 0) >= 5) {
        Cache::forget($cacheKey);

        return response()->json(['message' => 'Too many incorrect codes. Request a new code.'], 429);
    }

    if (! Hash::check($validated['otp'], $verification['otp_hash'])) {
        $verification['attempts']++;
        Cache::put($cacheKey, $verification, now()->addSeconds(max(1, (int) $verification['expires_at'] - now()->timestamp)));

        return response()->json(['message' => 'Verification code is incorrect.'], 422);
    }

    $verification['verified_at'] = now()->timestamp;
    Cache::put($cacheKey, $verification, now()->addSeconds(max(1, (int) $verification['expires_at'] - now()->timestamp)));

    return response()->json(['message' => 'Mobile number verified. You can now create your account.']);
});

Route::post('/supabase/login', function (Request $request) {
    $request->validate([
        'phone' => 'required|string|max:50',
        'password' => 'required|string',
    ]);

    $phone = PhoneNumber::normalize($request->input('phone'));
    if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
        return response()->json(['message' => 'Enter a valid mobile number.'], 422);
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $anonKey = env('VITE_SUPABASE_ANON_KEY');

    if (! $supabaseUrl || ! $anonKey) {
        return response()->json([
            'message' => 'Supabase URL or anon key is not configured.',
        ], 500);
    }

    $formData = [
        'grant_type' => 'password',
        'phone' => $phone,
        'password' => $request->input('password'),
    ];

    $headersToSend = [
        'apikey' => $anonKey,
        'Authorization' => 'Bearer '.$anonKey,
        'Accept' => 'application/json',
    ];

    $formBody = http_build_query($formData);
    $headersToSendWithType = array_merge($headersToSend, [
        'Content-Type' => 'application/x-www-form-urlencoded',
    ]);
    $loginResponse = Http::withHeaders($headersToSendWithType)
        ->withBody($formBody, 'application/x-www-form-urlencoded')
        ->post("{$supabaseUrl}/auth/v1/token?grant_type=password");

    if ($loginResponse->failed()) {
        Log::warning('Supabase phone sign-in failed.', [
            'status' => $loginResponse->status(),
        ]);

        $message = 'Unable to sign in.';
        try {
            $jsonErr = $loginResponse->json();
            if (is_array($jsonErr)) {
                $message = $jsonErr['error_description'] ?? $jsonErr['error'] ?? $message;
            }
        } catch (\Exception $e) {
            // ignore json parse errors
        }

        return response()->json([
            'message' => $message,
            'details' => [
                'status' => $loginResponse->status(),
            ],
        ], 401);
    }

    $loginPayload = $loginResponse->json();
    $accessToken = $loginPayload['access_token'] ?? null;

    if (! $accessToken) {
        return response()->json([
            'message' => 'Sign-in succeeded but no access token was returned.',
            'details' => $loginPayload,
        ], 500);
    }

    $userResponse = Http::withHeaders([
        'apikey' => $anonKey,
        'Authorization' => 'Bearer '.$accessToken,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/auth/v1/user");

    if ($userResponse->failed()) {
        return response()->json([
            'message' => 'Unable to verify Supabase user after login.',
            'details' => $userResponse->json(),
        ], 500);
    }

    $user = $userResponse->json();
    $request->session()->put('supabase_user', $user);

    return response()->json(['user' => $user])->cookie('supabase_token', $accessToken, 60 * 24, '/', null, false, true);
});

Route::post('/staff/password-reset/request', function (Request $request, SmsService $smsService) {
    $validated = $request->validate([
        'phone_number' => 'required|string|max:50',
        'email' => 'prohibited',
    ]);

    $phoneNumber = PhoneNumber::normalize($validated['phone_number']);
    if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $phoneNumber)) {
        return response()->json(['message' => 'Enter a valid mobile number.'], 422);
    }

    if (! env('VITE_SUPABASE_URL') || ! (env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY'))) {
        return response()->json(['message' => 'Staff password reset is not configured.'], 503);
    }

    if (config('services.sms.driver') === 'log') {
        return response()->json(['message' => 'SMS delivery is not configured.'], 503);
    }

    $ipKey = 'staff-password-reset-ip:'.hash('sha256', (string) $request->ip());
    $phoneHash = hash('sha256', $phoneNumber);
    $phoneKey = 'staff-password-reset-phone:'.$phoneHash;
    if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($phoneKey, 3)) {
        return response()->json(['message' => 'Too many code requests. Please wait before trying again.'], 429);
    }
    RateLimiter::hit($ipKey, 60);
    RateLimiter::hit($phoneKey, 600);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');
    $staffResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/staff", [
        'select' => 'id,phone_number',
        'phone_number' => 'eq.'.$phoneNumber,
        'limit' => 1,
    ]);

    if ($staffResponse->failed()) {
        Log::warning('Unable to look up staff account for SMS password reset.', ['status' => $staffResponse->status()]);

        return response()->json(['message' => 'Unable to verify this number right now. Please try again.'], 503);
    }

    $staff = collect($staffResponse->json())->first();
    if (! is_array($staff)
        || empty($staff['id'])
        || PhoneNumber::normalize($staff['phone_number'] ?? null) !== $phoneNumber) {
        return response()->json(['message' => 'No staff account is registered with this number.'], 404);
    }

    $activeFlowKey = 'staff-password-reset-active:'.$phoneHash;
    if ($previousFlow = Cache::pull($activeFlowKey)) {
        Cache::forget('staff-password-reset:'.$previousFlow);
    }

    $flowId = Str::random(48);
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = now()->addMinutes(5);
    $cacheKey = 'staff-password-reset:'.$flowId;
    Cache::put($cacheKey, [
        'staff_id' => (string) $staff['id'],
        'phone_hash' => $phoneHash,
        'otp_hash' => Hash::make($otp),
        'attempts' => 0,
        'phase' => 'otp',
        'expires_at' => $expiresAt->timestamp,
    ], $expiresAt);
    Cache::put($activeFlowKey, $flowId, $expiresAt);

    try {
        $smsService->send($phoneNumber, 'Your Student Pickup System verification code is '.$otp.'. It expires in 5 minutes. Do not share this code.');
    } catch (\Throwable $exception) {
        Cache::forget($cacheKey);
        Cache::forget($activeFlowKey);
        Log::warning('Unable to send staff password reset SMS.', ['exception' => get_class($exception)]);

        return response()->json(['message' => 'Unable to send the verification code. Please try again later.'], 503);
    }

    $maskedPhone = substr($phoneNumber, 0, 3).str_repeat('*', max(0, strlen($phoneNumber) - 7)).substr($phoneNumber, -4);

    return response()->json([
        'flow_id' => $flowId,
        'masked_phone' => $maskedPhone,
        'message' => 'Verification code sent to your registered mobile number.',
    ]);
});

Route::post('/staff/password-reset/verify', function (Request $request) {
    $validated = $request->validate([
        'flow_id' => 'required|string|size:48',
        'otp' => 'required|digits:6',
    ]);

    $cacheKey = 'staff-password-reset:'.$validated['flow_id'];
    $reset = Cache::get($cacheKey);
    if (! is_array($reset)
        || now()->timestamp >= (int) ($reset['expires_at'] ?? 0)) {
        Cache::forget($cacheKey);

        return response()->json(['message' => 'The verification code is invalid, expired, or already used.'], 422);
    }

    if (($reset['phase'] ?? null) !== 'otp' || empty($reset['otp_hash'])) {
        return response()->json(['message' => 'The verification code is invalid, expired, or already used.'], 422);
    }

    if (($reset['attempts'] ?? 0) >= 5) {
        Cache::forget($cacheKey);
        Cache::forget('staff-password-reset-active:'.$reset['phone_hash']);

        return response()->json(['message' => 'Too many incorrect codes. Request a new code.'], 429);
    }

    if (! Hash::check($validated['otp'], $reset['otp_hash'])) {
        $reset['attempts']++;
        Cache::put($cacheKey, $reset, now()->addSeconds(max(1, (int) $reset['expires_at'] - now()->timestamp)));

        return response()->json(['message' => 'The verification code is incorrect.'], 422);
    }

    $resetToken = Str::random(64);
    $reset['otp_hash'] = null;
    $reset['phase'] = 'password';
    $reset['reset_token_hash'] = Hash::make($resetToken);
    Cache::put($cacheKey, $reset, now()->addSeconds(max(1, (int) $reset['expires_at'] - now()->timestamp)));

    return response()->json([
        'reset_token' => $resetToken,
        'message' => 'Verification successful. Create a new password.',
    ]);
});

Route::post('/staff/password-reset/password', function (Request $request) {
    $validated = $request->validate([
        'flow_id' => 'required|string|size:48',
        'reset_token' => 'required|string|size:64',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $cacheKey = 'staff-password-reset:'.$validated['flow_id'];
    $reset = Cache::get($cacheKey);
    if (! is_array($reset)
        || ($reset['phase'] ?? null) !== 'password'
        || empty($reset['reset_token_hash'])
        || now()->timestamp >= (int) ($reset['expires_at'] ?? 0)
        || ! Hash::check($validated['reset_token'], $reset['reset_token_hash'])) {
        Cache::forget($cacheKey);

        return response()->json(['message' => 'Verification expired. Request a new code.'], 422);
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');
    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json(['message' => 'Staff password reset is not configured.'], 503);
    }

    $updateResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])->put("{$supabaseUrl}/auth/v1/admin/users/{$reset['staff_id']}", [
        'password' => $validated['password'],
    ]);

    if ($updateResponse->failed()) {
        Log::warning('Unable to update staff password after SMS verification.', [
            'staff_id' => $reset['staff_id'],
            'status' => $updateResponse->status(),
        ]);

        return response()->json(['message' => 'Unable to update the password. Please try again.'], 503);
    }

    Cache::forget($cacheKey);
    Cache::forget('staff-password-reset-active:'.$reset['phone_hash']);

    return response()->json([
        'message' => 'Password reset successfully. Redirecting to Staff Login.',
        'redirect' => '/staff/login',
    ]);
});

Route::post('/supabase/reset-password', function (Request $request, SmsService $smsService) {
    $method = $request->input('method', 'email');
    $accountType = $request->input('account_type', 'parent');
    $request->merge(['method' => $method, 'account_type' => $accountType]);
    $request->validate([
        'method' => 'required|in:email,sms',
        'account_type' => 'sometimes|in:parent',
        'email' => 'required_if:method,email|prohibited_if:method,sms|nullable|email',
        'phone' => 'required_if:method,sms|prohibited_if:method,email|nullable|string|max:50',
        'redirect_to' => 'nullable|url',
    ]);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $anonKey = env('VITE_SUPABASE_ANON_KEY') ?: env('SUPABASE_ANON_KEY');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');

    if ($method === 'sms') {
        $submittedPhone = PhoneNumber::normalize($request->input('phone'));
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $submittedPhone)) {
            return response()->json(['message' => 'Enter a valid mobile number.'], 422);
        }

        if (! $supabaseUrl || ! $serviceKey) {
            return response()->json(['message' => 'Phone password reset is not configured.'], 500);
        }

        $ipKey = 'password-reset-sms-ip:'.hash('sha256', (string) $request->ip());
        $phoneKey = 'password-reset-sms-phone:'.hash('sha256', $submittedPhone);
        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($phoneKey, 3)) {
            return response()->json(['message' => 'Too many code requests. Please wait before trying again.'], 429);
        }
        RateLimiter::hit($ipKey, 60);
        RateLimiter::hit($phoneKey, 600);

        $headers = [
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ];
        $accountTable = 'parents';
        $phoneFields = ['mobile_number', 'phone_number'];
        $accountRecord = null;
        foreach ($phoneFields as $phoneField) {
            $accountResponse = Http::withHeaders($headers)->get("{$supabaseUrl}/rest/v1/{$accountTable}", [
                'select' => 'id,auth_user_id,is_approved,email,'.$phoneField,
                $phoneField => 'eq.'.$submittedPhone,
                'limit' => 1,
            ]);

            if ($accountResponse->successful()) {
                $candidate = collect($accountResponse->json())->first();
                $candidatePhone = is_array($candidate) ? PhoneNumber::normalize($candidate[$phoneField] ?? null) : '';
                if ($candidatePhone === $submittedPhone) {
                    $accountRecord = $candidate;
                    break;
                }
            }
        }

        $flowId = Str::random(48);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $accountUserId = $accountRecord['auth_user_id'] ?? null;
        $registeredPhone = PhoneNumber::normalize($accountRecord['mobile_number'] ?? $accountRecord['phone_number'] ?? null);
        $canReset = is_array($accountRecord)
            && ! empty($accountUserId)
            && preg_match('/^\+[1-9][0-9]{7,14}$/', $registeredPhone);
        $cacheKey = 'password-reset-sms:'.$flowId;
        $expiresAt = now()->addMinutes(5);
        Cache::put($cacheKey, [
            'auth_user_id' => $canReset ? (string) $accountUserId : null,
            'code_hash' => $canReset ? Hash::make($code) : null,
            'attempts' => 0,
            'expires_at' => $expiresAt->timestamp,
        ], $expiresAt);

        if ($canReset) {
            try {
                $smsService->send($registeredPhone, 'Your Orion account password reset code is '.$code.'. It expires in 5 minutes. Do not share this code.');
            } catch (\Throwable $exception) {
                Cache::forget($cacheKey);
                Log::warning('Unable to send a parent password reset SMS.', ['exception' => get_class($exception)]);

                return response()->json(['message' => 'Unable to send an SMS code right now. Please try again later.'], 503);
            }
        }

        $accountLabel = 'parent account';
        $maskedPhone = $canReset
            ? substr($registeredPhone, 0, 3).str_repeat('*', max(0, strlen($registeredPhone) - 7)).substr($registeredPhone, -4)
            : null;

        return response()->json([
            'flow_id' => $flowId,
            'masked_phone' => $maskedPhone,
            'message' => $canReset
                ? 'A reset code was sent to '.$maskedPhone.'. The code expires in 5 minutes.'
                : 'If a matching '.$accountLabel.' exists, a reset code will be sent to its registered mobile number.',
        ]);
    }

    if (! $supabaseUrl || ! $anonKey) {
        return response()->json([
            'message' => 'Supabase URL or anon key is not configured.',
        ], 500);
    }

    $resetResponse = Http::withHeaders([
        'apikey' => $anonKey,
        'Authorization' => 'Bearer '.$anonKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])->post("{$supabaseUrl}/auth/v1/recover", [
        'email' => $request->input('email'),
        'redirect_to' => $request->input('redirect_to'),
    ]);

    if ($resetResponse->failed()) {
        return response()->json([
            'message' => $resetResponse->json('message') ?? 'Unable to send password reset email.',
            'details' => $resetResponse->json(),
        ], 500);
    }

    return response()->json(['message' => 'Password reset email sent.']);
});

Route::post('/supabase/reset-password/phone', function (Request $request) {
    $validated = $request->validate([
        'flow_id' => 'required|string|size:48',
        'code' => 'required|digits:6',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $cacheKey = 'password-reset-sms:'.$validated['flow_id'];
    $reset = Cache::get($cacheKey);
    if (! is_array($reset)
        || empty($reset['auth_user_id'])
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

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');
    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json(['message' => 'Phone password reset is not configured.'], 500);
    }

    $updateResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])->put("{$supabaseUrl}/auth/v1/admin/users/{$reset['auth_user_id']}", [
        'password' => $validated['password'],
    ]);

    if ($updateResponse->failed()) {
        Log::warning('Unable to update a parent password after SMS verification.', [
            'user_id' => $reset['auth_user_id'],
            'status' => $updateResponse->status(),
        ]);

        return response()->json(['message' => 'Unable to update your password right now. Please try again.'], 500);
    }

    Cache::forget($cacheKey);

    return response()->json(['message' => 'Your password has been changed. You can now sign in.']);
});

\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::except(['/supabase/staff-profile']);
Route::post('/supabase/staff-profile', function (Request $request) {
    $request->validate([
        'user_id' => 'required|string',
        'full_name' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone_number' => 'nullable|string|max:50',
        'is_approved' => 'nullable|boolean',
        'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
    ]);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json([
            'message' => 'Supabase service role key is not configured.',
        ], 500);
    }

    $photoUrl = null;

    $uploadedFile = $request->file('profile_photo') ?: $request->file('photo');

    if ($uploadedFile) {
        $userId = (string) $request->input('user_id');
        $safeUserId = preg_replace('/[^A-Za-z0-9_-]/', '_', $userId) ?: 'staff';
        $fileName = $safeUserId.'-'.time().'-'.Str::random(8).'.'.$uploadedFile->getClientOriginalExtension();
        $storedPath = $uploadedFile->storeAs('staff-profiles', $fileName, 'public');

        if ($storedPath) {
            $publicStorage = Storage::disk('public');
            if ($publicStorage instanceof \Illuminate\Filesystem\FilesystemAdapter) {
                $photoUrl = $publicStorage->url($storedPath);
            } else {
                $photoUrl = url('storage/'.$storedPath);
            }
        }
    } elseif ($request->filled('photo_data') && str_contains((string) $request->input('photo_data'), 'data:image')) {
        $dataUri = $request->input('photo_data');
        $matches = [];
        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.*)$/', $dataUri, $matches)) {
            $decoded = base64_decode($matches[2], true);
            if ($decoded !== false) {
                $extension = str_replace('image/', '', $matches[1]);
                $safeExt = in_array($extension, ['jpeg', 'png', 'jpg', 'gif', 'webp'], true) ? $extension : 'png';
                $fileName = 'staff-profiles/'.Str::uuid()->toString().'.'.$safeExt;
                Storage::disk('public')->put($fileName, $decoded);

                $publicStorage = Storage::disk('public');
                if ($publicStorage instanceof \Illuminate\Filesystem\FilesystemAdapter) {
                    $photoUrl = $publicStorage->url($fileName);
                } else {
                    $photoUrl = url('storage/'.$fileName);
                }
            }
        }
    } elseif ($request->filled('photo_url')) {
        $photoUrl = $request->input('photo_url');
    }

    $existingStaffResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/staff", [
        'select' => 'id,is_approved,is_active',
        'id' => 'eq.'.urlencode((string) $request->input('user_id')),
        'limit' => 1,
    ]);
    $existingStaff = $existingStaffResponse->successful()
        ? collect($existingStaffResponse->json())->first()
        : null;

    $payload = [
        'id' => $request->input('user_id'),
        ...NameParts::split((string) $request->input('full_name')),
        'email' => $request->input('email'),
        'phone_number' => PhoneNumber::normalize($request->input('phone_number')),
        'is_approved' => is_array($existingStaff) ? (bool) ($existingStaff['is_approved'] ?? false) : false,
        'is_active' => is_array($existingStaff) ? (bool) ($existingStaff['is_active'] ?? true) : true,
    ];

    if ($photoUrl) {
        $payload['photo_url'] = $photoUrl;
    }

    $payloadForSupabase = $payload;

    $response = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Prefer' => 'resolution=merge-duplicates,return=representation',
    ])->post("{$supabaseUrl}/rest/v1/staff?on_conflict=id", $payloadForSupabase);

    if ($response->failed()) {
        $details = $response->json();
        $message = $response->json('message') ?? 'Failed to save the staff record.';

        $missingPhotoColumn = is_string($message) && str_contains(strtolower($message), 'photo_url')
            && str_contains(strtolower($message), 'column');

        if ($missingPhotoColumn && array_key_exists('photo_url', $payloadForSupabase)) {
            unset($payloadForSupabase['photo_url']);

            $fallbackResponse = Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Prefer' => 'resolution=merge-duplicates,return=representation',
            ])->post("{$supabaseUrl}/rest/v1/staff?on_conflict=id", $payloadForSupabase);

            if (! $fallbackResponse->failed()) {
                return response()->json([
                    'success' => true,
                    'data' => $fallbackResponse->json(),
                    'warning' => 'Profile saved without photo_url because the Supabase staff table does not currently include that column.',
                ]);
            }

            $details = $fallbackResponse->json();
            $message = $fallbackResponse->json('message') ?? $message;
        }

        return response()->json([
            'message' => $message,
            'details' => $details,
            'payload' => $payloadForSupabase,
        ], 500);
    }

    return response()->json([
        'success' => true,
        'data' => $response->json(),
    ]);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/supabase/staff-check', function (Request $request) {
    $userId = trim((string) $request->query('user_id', ''));
    if ($userId === '') {
        return response()->json(['authorized' => false], 400);
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return response()->json(['authorized' => false, 'message' => 'Supabase service key missing.'], 500);
    }

    $response = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/staff", [
        'select' => 'id,email,is_approved,is_active',
        'id' => 'eq.'.$userId,
    ]);

    if ($response->failed()) {
        return response()->json(['authorized' => false, 'details' => $response->json()], 500);
    }

    $rows = $response->json();
    $record = collect($rows)->first();

    return response()->json([
        'authorized' => ! empty($record)
            && ($record['is_approved'] ?? false) === true
            && ($record['is_active'] ?? false) === true,
        'record' => $record,
    ]);
});

Route::post('/supabase/session', function (Request $request) {
    $request->validate([
        'access_token' => 'required|string',
    ]);

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $anonKey = env('VITE_SUPABASE_ANON_KEY');

    if (! $supabaseUrl || ! $anonKey) {
        return response()->json(['message' => 'Supabase is not configured.'], 500);
    }

    $response = Http::withHeaders([
        'apikey' => $anonKey,
        'Authorization' => 'Bearer '.$request->input('access_token'),
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/auth/v1/user");

    if ($response->failed()) {
        return response()->json(['message' => 'Invalid token.'], 401);
    }

    $request->session()->put('supabase_user', $response->json());

    return response()->json($response->json())
        ->cookie('supabase_token', $request->input('access_token'), 60 * 24, '/', null, false, true);
});

Route::post('/logout', function (Request $request) {
    // Always redirect to account selection page after logout
    $request->session()->forget('supabase_user');
    $request->session()->invalidate();

    return redirect('/')->withoutCookie('supabase_token');
});

// Account Selection - MAIN ENTRY POINT
Route::get('/', function () {
    return view('account-selection');
})->name('account.selection');

// Parent/Guardian Authentication Routes
Route::get('/parent/login', function () {
    return view('parent.login');
})->name('parent.login');

// Old login route - redirect to account selection
Route::get('/login', function () {
    return redirect('/');
});

Route::get('/register', function () {
    return view('parent-auth');
});

Route::get('/parent/register', function () {
    return view('parent-auth');
});

Route::get('/forgot-password', function () {
    return view('parent-auth');
});

// Staff Authentication Routes
Route::get('/staff/login', function () {
    return view('staff.login');
})->name('staff.login');

Route::get('/staff/register', function () {
    return view('staff-auth');
});

Route::get('/staff/forgot-password', function () {
    return redirect('/staff/login#forgot-password');
});

Route::get('/picker', function (Request $request) {
    $token = $request->cookie('supabase_token');
    if (! $token) {
        return redirect('/');
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $anonKey = env('VITE_SUPABASE_ANON_KEY');
    $response = Http::withHeaders([
        'apikey' => $anonKey,
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/auth/v1/user");

    if ($response->failed()) {
        return redirect('/');
    }

    $user = $response->json();
    $role = strtolower($user['user_metadata']['role'] ?? $user['role'] ?? 'parent');

    return in_array($role, ['admin', 'staff'], true)
        ? redirect('/staff/dashboard')
        : redirect('/parent/dashboard');
});

// Helper function to get parent data
$getParentData = function (Request $request) use ($makeParentQrToken) {
    $user = $request->session()->get('supabase_user');

    if (! $user) {
        $token = $request->cookie('supabase_token');

        if ($token) {
            $supabaseUrl = env('VITE_SUPABASE_URL');
            $anonKey = env('VITE_SUPABASE_ANON_KEY');

            if ($supabaseUrl && $anonKey) {
                $response = Http::withHeaders([
                    'apikey' => $anonKey,
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ])->get("{$supabaseUrl}/auth/v1/user");

                if ($response->ok()) {
                    $user = $response->json();
                    $request->session()->put('supabase_user', $user);
                }
            }
        }
    }

    $student = [
        'name' => $user['user_metadata']['student_name'] ?? 'Unassigned student',
        'class' => $user['user_metadata']['student_class'] ?? 'N/A',
        'id' => $user['user_metadata']['student_id'] ?? 'N/A',
    ];

    $relationship = $user['user_metadata']['relationship'] ?? 'Parent/Guardian';
    $mobile = $user['user_metadata']['mobile_number'] ?? $user['email'] ?? 'N/A';
    $fullName = $user['user_metadata']['full_name'] ?? 'N/A';
    $email = $user['email'] ?? 'N/A';

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');
    $parentId = null;
    $parentQrToken = null;
    $parentQrExpiresAt = null;

    if ($supabaseUrl && $serviceKey) {
        // Match the profile using the stable mobile number from auth metadata.
        $parentName = trim((string) ($user['user_metadata']['full_name'] ?? $user['full_name'] ?? $fullName ?? ''));
        $parentMobile = trim((string) ($user['user_metadata']['mobile_number'] ?? $mobile));
        
        if ($parentMobile !== '') {
            $parentResponse = Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Accept' => 'application/json',
            ])->get("{$supabaseUrl}/rest/v1/parents", [
                'select' => 'id,student_id,first_name,middle_name,last_name,phone_number,relationship,email',
                'auth_user_id' => 'eq.'.urlencode((string) ($user['id'] ?? '')),
            ]);

            if ($parentResponse->failed() || empty($parentResponse->json())) {
                $parentResponse = Http::withHeaders([
                    'apikey' => $serviceKey,
                    'Authorization' => 'Bearer '.$serviceKey,
                    'Accept' => 'application/json',
                ])->get("{$supabaseUrl}/rest/v1/parents", [
                    'select' => 'id,student_id,first_name,middle_name,last_name,phone_number,relationship,email',
                    'phone_number' => 'eq.'.urlencode($parentMobile),
                ]);
            }

            if ($parentResponse->ok() && empty($parentResponse->json()) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $parentResponse = Http::withHeaders([
                    'apikey' => $serviceKey,
                    'Authorization' => 'Bearer '.$serviceKey,
                    'Accept' => 'application/json',
                ])->get("{$supabaseUrl}/rest/v1/parents", [
                    'select' => 'id,student_id,first_name,middle_name,last_name,phone_number,relationship,email',
                    'email' => 'eq.'.urlencode($email),
                ]);
            }

            if ($parentResponse->ok()) {
                $parentProfile = collect($parentResponse->json())->first();

                if ($parentProfile) {
                    $parentId = $parentProfile['id'] ?? null;
                    $relationship = $parentProfile['relationship'] ?? $relationship;
                    $mobile = $parentProfile['phone_number'] ?? $parentProfile['mobile_number'] ?? $mobile;

                    // Get the student assigned directly to this parent
                    $studentResponse = Http::withHeaders([
                        'apikey' => $serviceKey,
                        'Authorization' => 'Bearer '.$serviceKey,
                        'Accept' => 'application/json',
                    ])->get("{$supabaseUrl}/rest/v1/students", [
                        'id' => 'eq.'.((int) ($parentProfile['student_id'] ?? 0)),
                        'select' => 'id,first_name,middle_name,last_name,grade_level,section',
                    ]);

                    if ($studentResponse->ok()) {
                        $linkedStudent = collect($studentResponse->json())->first();

                        if ($linkedStudent) {
                            $student = [
                                'id' => $linkedStudent['id'] ?? 'N/A',
                                'name' => NameParts::display($linkedStudent) ?: 'Unassigned student',
                                'class' => collect([$linkedStudent['grade_level'] ?? null, $linkedStudent['section'] ?? null])->filter()->implode(' - ') ?: 'N/A',
                            ];
                        }
                    }
                } else {
                    // Parent profile doesn't exist - create it from user metadata
                    $mobileNumber = trim((string) ($user['user_metadata']['mobile_number'] ?? $mobile ?? 'N/A'));
                    $relationship_meta = trim((string) ($user['user_metadata']['relationship'] ?? 'Parent/Guardian'));
                    
                    $createParentResponse = Http::withHeaders([
                        'apikey' => $serviceKey,
                        'Authorization' => 'Bearer '.$serviceKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Prefer' => 'return=representation',
                    ])->post("{$supabaseUrl}/rest/v1/parents", [
                        ...NameParts::split($parentName),
                        'auth_user_id' => $user['id'] ?? null,
                        'student_id' => (int) ($student['id'] ?? 0),
                        'mobile_number' => $mobileNumber,
                        'relationship' => $relationship_meta,
                        'email' => $email !== 'N/A' ? $email : null,
                    ]);

                    if ($createParentResponse->successful()) {
                        $createdParent = collect($createParentResponse->json())->first();
                        if ($createdParent && isset($createdParent['id'])) {
                            $parentId = $createdParent['id'];
                            $relationship = $createdParent['relationship'] ?? $relationship;
                            $mobile = $createdParent['phone_number'] ?? $createdParent['mobile_number'] ?? $mobile;
                        }
                    }
                }
            }
        }
    }

    if ($parentId && is_numeric($student['id'] ?? null)) {
        $temporaryQr = $makeParentQrToken($parentId, $student['id'], $request->query('qr_refresh'));
        $parentQrToken = $temporaryQr['token'];
        $parentQrExpiresAt = $temporaryQr['expires_at'];
    }

    return [
        'user' => $user,
        'student' => $student,
        'relationship' => $relationship,
        'mobile' => $mobile,
        'fullName' => $fullName,
        'email' => $email,
        'parent_id' => $parentId,
        'parent_qr_token' => $parentQrToken,
        'parent_qr_expires_at' => $parentQrExpiresAt,
    ];
};

Route::get('/parent/dashboard', function (Request $request) {
    return redirect('/parent/student');
})->name('parent.dashboard')->middleware(EnsureUserHasRole::class.':parent');

Route::get('/parent/student', function (Request $request) use ($getParentData) {
    $data = $getParentData($request);

    return view('parent.student', $data);
})->name('parent.student')->middleware(EnsureUserHasRole::class.':parent');

Route::get('/parent/qr-code', function (Request $request) use ($getParentData) {
    $data = $getParentData($request);

    return view('parent.qr-code', $data);
})->name('parent.qr-code')->middleware(EnsureUserHasRole::class.':parent');


Route::get('/parent/pickup-history', function (Request $request) use ($getParentData) {
    $data = $getParentData($request);
    $student = $data['student'] ?? [];
    $studentId = $student['id'] ?? null;
    $pickupRows = [];

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');

    if ($supabaseUrl && $serviceKey && $studentId && $studentId !== 'N/A') {
        $pickupResponse = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ])->get("{$supabaseUrl}/rest/v1/pickups", [
            'select' => 'id,student_id,picked_at',
            'student_id' => 'eq.'.(string) $studentId,
            'order' => 'picked_at.desc',
        ]);

        if ($pickupResponse->ok()) {
            $pickupRows = collect($pickupResponse->json())->map(function ($pickup) {
                $pickedAt = $pickup['picked_at'] ?? null;
                $dateTime = $pickedAt
                    ? \Carbon\Carbon::parse($pickedAt)->setTimezone(config('app.timezone'))
                    : null;

                return [
                    'id' => $pickup['id'] ?? null,
                    'date' => $dateTime ? $dateTime->format('d M Y') : 'N/A',
                    'date_key' => $dateTime ? $dateTime->format('Y-m-d') : '',
                    'time' => $dateTime ? $dateTime->format('h:i A') : 'N/A',
                    'verified_by' => $pickup['verified_by'] ?? 'N/A',
                    'status' => 'Verified',
                    'details' => '—',
                    'sort_timestamp' => $pickedAt ?? '',
                ];
            })->values()->all();
        }
    }

    $totalPickups = count($pickupRows);
    $verifiedCount = collect($pickupRows)->filter(fn ($row) => strtolower((string) ($row['status'] ?? '')) === 'verified')->count();
    $lastPickup = collect($pickupRows)->first();

    $data['pickupRecords'] = $pickupRows;
    $data['totalPickups'] = $totalPickups;
    $data['verifiedCount'] = $verifiedCount;
    $data['lastPickup'] = $lastPickup;

    return view('parent.pickup-history', $data);
})->name('parent.pickup-history')->middleware(EnsureUserHasRole::class.':parent');

Route::get('/parent/notifications', function (Request $request) use ($getParentData) {
    $data = $getParentData($request);

    return view('parent.notifications', $data);
})->name('parent.notifications')->middleware(EnsureUserHasRole::class.':parent');

Route::get('/parent/profile', function (Request $request) use ($getParentData) {
    $data = $getParentData($request);

    return view('parent.profile', $data);
})->name('parent.profile')->middleware(EnsureUserHasRole::class.':parent');

Route::get('/parent/profile/sessions', function (Request $request) {
    return view('parent.sessions', [
        'ipAddress' => $request->ip(),
        'userAgent' => $request->userAgent(),
    ]);
})->name('parent.profile.sessions')->middleware(EnsureUserHasRole::class.':parent');

Route::get('/parent/contact-admin', function (Request $request) {
    $user = $request->session()->get('supabase_user');
    $messages = \App\Models\SupportMessage::with('replies')->where('parent_user_id', $user['id'])->latest()->get();

    return view('parent.contact-admin', compact('messages'));
})->name('parent.contact-admin')->middleware([
    \App\Http\Middleware\EnsureSupportMessageTablesExist::class,
    EnsureUserHasRole::class.':parent',
]);

Route::post('/parent/contact-admin', function (Request $request) {
    $validated = $request->validate([
        'subject' => 'required|string|max:120',
        'message' => 'required|string|max:5000',
    ]);

    $user = $request->session()->get('supabase_user');
    \App\Models\SupportMessage::create([
        'parent_user_id' => $user['id'],
        'parent_name' => $user['user_metadata']['full_name'] ?? $user['full_name'] ?? null,
        'parent_email' => $user['email'] ?? null,
        'subject' => $validated['subject'],
        'message' => $validated['message'],
    ]);

    return redirect()->route('parent.contact-admin')->with('status', 'Your message has been sent to the admin.');
})->name('parent.contact-admin.store')->middleware([
    \App\Http\Middleware\EnsureSupportMessageTablesExist::class,
    EnsureUserHasRole::class.':parent',
]);

Route::post('/parent/contact-admin/{supportMessage}/replies', function (Request $request, string $supportMessage) {
    $validated = $request->validate([
        'reply' => 'required|string|max:5000',
    ]);

    $user = $request->session()->get('supabase_user');
    $message = \App\Models\SupportMessage::where('parent_user_id', $user['id'])->findOrFail($supportMessage);
    $message->replies()->create([
        'sender_type' => 'parent',
        'sender_id' => (string) $user['id'],
        'body' => $validated['reply'],
    ]);
    $message->update(['status' => 'open']);

    return redirect()->route('parent.contact-admin')->with('status', 'Your reply has been sent to the admin.');
})->name('parent.contact-admin.reply')->middleware([
    \App\Http\Middleware\EnsureSupportMessageTablesExist::class,
    EnsureUserHasRole::class.':parent',
]);

Route::get('/parent/profile/edit', function (Request $request) use ($getParentData) {
    $data = $getParentData($request);

    return view('parent.profile-edit', $data);
})->name('parent.profile.edit')->middleware(EnsureUserHasRole::class.':parent');

Route::post('/parent/profile/update', function (Request $request) use ($getParentData) {
    $request->validate([
        'full_name' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'mobile_number' => 'required|string|max:50|not_in:null,NULL',
        'relationship' => 'required|string|max:100',
    ]);

    $user = $request->session()->get('supabase_user');
    if (! $user) {
        return redirect('/parent/login');
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return back()->withErrors(['error' => 'Supabase is not configured.'])->withInput();
    }

    $fullName = $request->input('full_name');
    $email = $request->input('email');
    $mobileNumber = PhoneNumber::normalize($request->input('mobile_number'));
    $relationship = $request->input('relationship');

    $parentUpdatePayload = [
        ...NameParts::split($fullName),
        'phone_number' => $mobileNumber,
        'relationship' => $relationship,
        'email' => $email ?: null,
    ];

    Log::debug('parent.profile.update.request', $request->all());
    Log::debug('parent.profile.update.payload', $parentUpdatePayload);

    $supabaseHeaders = [
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Prefer' => 'return=representation',
    ];

    $existingParentSearch = [
        'select' => 'id',
        'auth_user_id' => 'eq.'.($user['id'] ?? ''),
    ];

    $parentResponse = null;
    $parentProfile = null;

    if (! empty($user['id'])) {
        $searchResponse = Http::withHeaders($supabaseHeaders)
            ->get("{$supabaseUrl}/rest/v1/parents", $existingParentSearch);

        Log::debug('parent.profile.update.search.status', ['status' => $searchResponse->status()]);
        Log::debug('parent.profile.update.search.body', ['body' => $searchResponse->body()]);

        if ($searchResponse->ok()) {
            $parentProfile = collect($searchResponse->json())->first();
        }
    }

    if (is_array($parentProfile) && isset($parentProfile['id'])) {
        $parentResponse = Http::withHeaders($supabaseHeaders)
            ->patch("{$supabaseUrl}/rest/v1/parents?id=eq.".$parentProfile['id'], $parentUpdatePayload);
    } else {
        $parentResponse = Http::withHeaders($supabaseHeaders)
            ->post("{$supabaseUrl}/rest/v1/parents", $parentUpdatePayload);
    }

    Log::debug('parent.profile.update.save.status', ['status' => $parentResponse->status()]);
    Log::debug('parent.profile.update.save.body', ['body' => $parentResponse->body()]);

    if ($parentResponse->failed()) {
        return back()->withErrors(['error' => 'Unable to save parent profile.'])->withInput();
    }

    $parentProfile = collect($parentResponse->json())->first();
    if (! $parentProfile) {
        return back()->withErrors(['error' => 'Unable to save parent profile.'])->withInput();
    }

    $userMetadataUpdate = [
        'user_metadata' => array_merge($user['user_metadata'] ?? [], [
            'full_name' => $fullName,
            'mobile_number' => $mobileNumber,
            'relationship' => $relationship,
        ]),
    ];

    if (! empty($user['id'])) {
        $userId = $user['id'];
        $userPatchResponse = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Prefer' => 'return=representation',
        ])->put("{$supabaseUrl}/auth/v1/admin/users/{$userId}", $userMetadataUpdate);

        Log::debug('parent.profile.update.userPatch.status', ['status' => $userPatchResponse->status()]);
        Log::debug('parent.profile.update.userPatch.body', ['body' => $userPatchResponse->body()]);

        if ($userPatchResponse->failed()) {
            return back()->withErrors(['error' => 'Unable to update user metadata.'])->withInput();
        }

        $updatedUser = $userPatchResponse->json();
        if (is_array($updatedUser) && isset($updatedUser['id'])) {
            $request->session()->put('supabase_user', $updatedUser);
        }
    }

    return redirect()->route('parent.profile')->with('status', 'Profile updated successfully.');
})->name('parent.profile.update')->middleware(EnsureUserHasRole::class.':parent');

Route::post('/parent/profile/password', function (Request $request) {
    $request->validate([
        'current_password' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = $request->session()->get('supabase_user');
    if (! $user || empty($user['id']) || empty($user['phone'])) {
        return redirect('/parent/login');
    }

    $supabaseUrl = env('VITE_SUPABASE_URL');
    $anonKey = env('VITE_SUPABASE_ANON_KEY') ?: env('SUPABASE_ANON_KEY') ?: env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $anonKey || ! $serviceKey) {
        return back()->withErrors(['error' => 'Supabase is not configured.'])->withInput();
    }

    $tokenResponse = Http::withHeaders([
        'apikey' => $anonKey,
        'Authorization' => 'Bearer '.$anonKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])->post("{$supabaseUrl}/auth/v1/token?grant_type=password", [
        'phone' => PhoneNumber::normalize($user['phone']),
        'password' => $request->input('current_password'),
    ]);

    if ($tokenResponse->failed()) {
        return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
    }

    $updateResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Prefer' => 'return=representation',
    ])->put("{$supabaseUrl}/auth/v1/admin/users/{$user['id']}", [
        'password' => $request->input('password'),
    ]);

    if ($updateResponse->failed()) {
        return back()->withErrors(['password' => $updateResponse->json('message') ?? 'Unable to update password.'])->withInput();
    }

    return redirect()->route('parent.profile')->with('status', 'Password updated successfully.');
})->name('parent.profile.password')->middleware(EnsureUserHasRole::class.':parent');

Route::get('/staff/dashboard', function () {
    return view('staff.dashboard');
})->name('staff.dashboard')->middleware(EnsureUserHasRole::class.':admin');

Route::get('/staff/students', function () {
    return view('staff.students');
})->name('staff.students')->middleware(EnsureUserHasRole::class.':admin');

// Supabase admin endpoints: list, update, delete parents
Route::get('/supabase/admin/parents', function () {
    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY') ?? env('SUPABASE_SERVICE_KEY');
    if (empty($serviceKey) || empty($supabaseUrl)) {
        return response()->json(['message' => 'Supabase not configured'], 500);
    }

    $headers = [
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ];

    $parentResp = Http::withHeaders($headers)->get("{$supabaseUrl}/rest/v1/parents?select=*&order=id.desc&limit=200");
    if ($parentResp->failed()) {
        return response()->json(['message' => 'Failed to fetch parents', 'body' => $parentResp->body()], $parentResp->status());
    }

    $parents = $parentResp->json();
    if (! is_array($parents)) {
        return response()->json([]);
    }

    return response()->json(collect($parents)->map(fn (array $parent) => array_merge(
        $parent,
        ['full_name' => NameParts::display($parent)]
    ))->all());
})->name('supabase.admin.parents')->middleware(EnsureUserHasRole::class.':admin');

Route::put('/supabase/admin/parents/{id}', function ($id, Request $request) {
    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY') ?? env('SUPABASE_SERVICE_KEY');
    if (empty($serviceKey) || empty($supabaseUrl)) {
        return response()->json(['message' => 'Supabase not configured'], 500);
    }
    $payload = array_merge(
        NameParts::split((string) $request->input('full_name')),
        $request->only(['mobile_number', 'relationship'])
    );
    $headers = [
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
        'Prefer' => 'return=representation',
    ];

    $resp = Http::withHeaders($headers)->patch("{$supabaseUrl}/rest/v1/parents?id=eq.{$id}", $payload);
    if ($resp->failed()) {
        return response()->json(['message' => 'Failed to update parent', 'body' => $resp->body()], $resp->status());
    }
    return response()->json(collect($resp->json())->first());
})->name('supabase.admin.parents.update')->middleware(EnsureUserHasRole::class.':admin');

Route::delete('/supabase/admin/parents/{id}', function ($id, Request $request) {
    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY') ?? env('SUPABASE_SERVICE_KEY');
    if (empty($serviceKey) || empty($supabaseUrl)) {
        return response()->json(['message' => 'Supabase not configured'], 500);
    }

    // First fetch the parent row to find linked user email or id
    $headers = [
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ];
    $search = Http::withHeaders($headers)->get("{$supabaseUrl}/rest/v1/parents?id=eq.{$id}&select=*");
    if ($search->failed() || empty($search->json())) {
        return response()->json(['message' => 'Parent not found'], 404);
    }
    $parent = collect($search->json())->first();

    // Delete parent row
    $del = Http::withHeaders(array_merge($headers, ['Prefer' => 'return=representation']))->delete("{$supabaseUrl}/rest/v1/parents?id=eq.{$id}");
    if ($del->failed()) {
        return response()->json(['message' => 'Failed to delete parent', 'body' => $del->body()], $del->status());
    }

    // If there is an auth user id or email in metadata, attempt to delete the auth user too
    $deleted = ['parent' => collect($del->json())->first() ?? null];
    $userId = $parent['auth_user_id'] ?? null;
    $email = $parent['email'] ?? null;
    if (! empty($userId)) {
        $userDel = Http::withHeaders($headers)->delete("{$supabaseUrl}/auth/v1/admin/users/{$userId}");
        $deleted['auth_user'] = $userDel->ok() ? 'deleted' : 'failed';
    } elseif (! empty($email)) {
        // try to find by email
        $usersResp = Http::withHeaders($headers)->get("{$supabaseUrl}/auth/v1/admin/users?email=eq.{$email}");
        if ($usersResp->ok()) {
            $users = is_array($usersResp->json()) ? $usersResp->json() : ($usersResp->json()['users'] ?? []);
            $first = collect($users)->first();
            if ($first && isset($first['id'])) {
                $userDel = Http::withHeaders($headers)->delete("{$supabaseUrl}/auth/v1/admin/users/{$first['id']}");
                $deleted['auth_user'] = $userDel->ok() ? 'deleted' : 'failed';
            }
        }
    }

    return response()->json($deleted);
})->name('supabase.admin.parents.delete')->middleware(EnsureUserHasRole::class.':admin');

Route::get('/supabase/admin', function () {
    return view('supabase-admin');
})->name('supabase.admin.ui')->middleware('auth');

Route::get('/staff/pickup-verification', function () {
    return view('staff.pickup-verification');
})->name('staff.pickup-verification')->middleware(EnsureUserHasRole::class.':admin');

Route::get('/staff/parents', function () {
    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY') ?? env('SUPABASE_SERVICE_KEY');
    $parents = [];

    if ($supabaseUrl && $serviceKey) {
        try {
            $response = Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Accept' => 'application/json',
            ])->get("{$supabaseUrl}/rest/v1/parents?select=*&order=id.desc&limit=200");

            if ($response->ok()) {
                $parents = $response->json();
            }
        } catch (\Throwable $e) {
            // ignore and show empty list
        }
    }

    return view('staff.parents', ['parents' => $parents]);
})->name('staff.parents')->middleware(EnsureUserHasRole::class.':admin');


Route::get('/staff/pickup-records', function () {
    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY')
        ?: env('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SERVICE_KEY')
        ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

    if (!$supabaseUrl || !$serviceKey) {
        return view('staff.pickup-records', ['records' => collect()]);
    }

    // Fetch pickups table directly (simple query without complex joins)
    $pickupsResponse = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/pickups", [
        'select' => 'id,student_id,parent_id,picked_at,created_at',
        'order' => 'picked_at.desc',
        'limit' => '100',
    ]);

    $records = collect();
    
    if ($pickupsResponse->successful()) {
        $pickups = $pickupsResponse->json();
        
        // Fetch all students at once
        $studentsResponse = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ])->get("{$supabaseUrl}/rest/v1/students", [
            'select' => 'id,first_name,middle_name,last_name,grade_level,section',
        ]);
        $students = $studentsResponse->successful() ? collect($studentsResponse->json())->keyBy('id') : collect();

        // Fetch all parents at once
        $parentsResponse = Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ])->get("{$supabaseUrl}/rest/v1/parents", [
            'select' => 'id,first_name,middle_name,last_name',
        ]);
        $parents = $parentsResponse->successful()
            ? collect($parentsResponse->json())
                ->map(fn (array $parent) => array_merge(
                    $parent,
                    ['full_name' => NameParts::display($parent)]
                ))
                ->keyBy('id')
            : collect();

        // Map pickups to display format
        $records = collect($pickups)->map(function ($pickup) use ($students, $parents) {
            $student = $students->get($pickup['student_id']) ?? null;
            $parent = $parents->get($pickup['parent_id']) ?? null;
            $studentName = is_array($student) ? NameParts::display($student) : '';
            $studentClass = is_array($student)
                ? collect([$student['grade_level'] ?? null, $student['section'] ?? null])->filter()->implode(' - ')
                : '';
            $guardianName = is_array($parent) ? NameParts::display($parent) : '';
            
            return [
                'id' => $pickup['id'] ?? null,
                'time' => $pickup['picked_at'] ? date('h:i A', strtotime($pickup['picked_at'])) : '-',
                'date' => $pickup['picked_at'] ? date('M d, Y', strtotime($pickup['picked_at'])) : '-',
                'student' => $studentName ?: 'Unknown Student',
                'student_id' => $student['id'] ?? $pickup['student_id'] ?? '-',
                'student_class' => $studentClass ?: '-',
                'guardian' => $guardianName ?: 'No Guardian',
                'guardian_id' => $parent['id'] ?? ($pickup['parent_id'] ?? '-'),
                'verified_by' => 'Staff Scanner',
                'result' => 'Successful',
                'picked_at' => $pickup['picked_at'] ?? null,
            ];
        });
    }

    return view('staff.pickup-records', ['records' => $records]);
})->name('staff.pickup-records')->middleware(EnsureUserHasRole::class.':admin');

// Load only parent records that can receive an SMS, supporting both phone column names used by older data.
$loadSmsParents = static function (): array {
    $supabaseUrl = env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return [];
    }

    $response = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/parents", [
        'select' => '*',
        'order' => 'first_name.asc,last_name.asc',
        'limit' => 500,
    ]);

    if (! $response->successful()) {
        return [];
    }

    return collect($response->json())
        ->map(function (array $parent): array {
            $parent['full_name'] = NameParts::display($parent);
            $parent['sms_phone'] = PhoneNumber::normalize($parent['phone_number'] ?? $parent['mobile_number'] ?? '');

            return $parent;
        })
        ->filter(fn (array $parent): bool => ($parent['is_active'] ?? true) !== false && $parent['sms_phone'] !== '')
        ->values()
        ->all();
};

$loadSmsStaff = static function (): array {
    $supabaseUrl = env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');

    if (! $supabaseUrl || ! $serviceKey) {
        return [];
    }

    $response = Http::withHeaders([
        'apikey' => $serviceKey,
        'Authorization' => 'Bearer '.$serviceKey,
        'Accept' => 'application/json',
    ])->get("{$supabaseUrl}/rest/v1/staff", [
        'select' => 'id,first_name,middle_name,last_name,phone_number,email,is_active',
        'order' => 'first_name.asc,last_name.asc',
        'limit' => 500,
    ]);

    if (! $response->successful()) {
        return [];
    }

    return collect($response->json())
        ->map(function (array $staff): array {
            $staff['full_name'] = NameParts::display($staff);
            $staff['sms_phone'] = PhoneNumber::normalize($staff['phone_number'] ?? '');

            return $staff;
        })
        ->filter(fn (array $staff): bool => ($staff['is_active'] ?? true) !== false && $staff['sms_phone'] !== '')
        ->values()
        ->all();
};

Route::get('/staff/notifications', function () use ($loadSmsParents, $loadSmsStaff) {
    return view('staff.notifications', [
        'smsDriver' => config('services.sms.driver', 'log'),
        'smsParents' => $loadSmsParents(),
        'smsStaff' => $loadSmsStaff(),
    ]);
})->name('staff.notifications')->middleware(EnsureUserHasRole::class.':admin');

$sendParentSms = static function (Request $request, SmsService $smsService) use ($loadSmsParents) {
    $data = $request->validate([
        'parent_ids' => ['required', 'array', 'min:1'],
        'parent_ids.*' => ['integer'],
        'message' => ['required', 'string', 'max:320'],
    ]);

    $selectedIds = collect($data['parent_ids'])->map(fn ($id) => (int) $id)->unique();
    $parents = collect($loadSmsParents())->filter(fn (array $parent): bool => $selectedIds->contains((int) ($parent['id'] ?? 0)));
    $sent = 0;
    $failed = [];
    $notifications = [];

    foreach ($parents as $parent) {
        try {
            $smsService->send($parent['sms_phone'], $data['message']);
            $sent++;
            $notifications[] = [
                'student_id' => $parent['student_id'] ?? null,
                'notification_type' => 'sms_reminder',
                'recipient_email' => $parent['email'] ?? null,
                'recipient_phone' => $parent['sms_phone'],
                'message' => $data['message'],
                'sent_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $exception) {
            $failed[] = $parent['full_name'] ?: $parent['sms_phone'];
            Log::error('Bulk SMS reminder failed.', [
                'parent_id' => $parent['id'] ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    $supabaseUrl = env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');
    if ($notifications && $supabaseUrl && $serviceKey) {
        Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=minimal',
        ])->post("{$supabaseUrl}/rest/v1/notifications", $notifications);
    }

    $status = config('services.sms.driver') === 'supabase_queue'
        ? "Queued {$sent} SMS reminder(s) for the laptop GSM modem."
        : "Sent {$sent} SMS reminder(s).";
    if ($failed) {
        $status .= ' Failed: '.implode(', ', $failed).'.';
    }

    return back()->with($sent > 0 ? 'sms_success' : 'sms_error', $status);
};

Route::post('/staff/notifications/send-to-parents', $sendParentSms)
    ->name('staff.notifications.send-to-parents')
    ->middleware(EnsureUserHasRole::class.':admin');

$sendStaffSms = static function (Request $request, SmsService $smsService) use ($loadSmsStaff) {
    $data = $request->validate([
        'staff_ids' => ['required', 'array', 'min:1'],
        'staff_ids.*' => ['string'],
        'message' => ['required', 'string', 'max:320'],
    ]);

    $selectedIds = collect($data['staff_ids'])->map(fn ($id) => (string) $id)->unique();
    $staffMembers = collect($loadSmsStaff())->filter(fn (array $staff): bool => $selectedIds->contains((string) ($staff['id'] ?? '')));
    $sent = 0;
    $failed = [];

    foreach ($staffMembers as $staff) {
        try {
            $smsService->send($staff['sms_phone'], $data['message']);
            $sent++;
        } catch (\Throwable $exception) {
            $failed[] = $staff['full_name'] ?: $staff['sms_phone'];
            Log::error('Staff SMS notification failed.', [
                'staff_id' => $staff['id'] ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    $status = config('services.sms.driver') === 'supabase_queue'
        ? "Queued {$sent} staff SMS notification(s) for the laptop GSM modem."
        : "Sent {$sent} staff SMS notification(s).";
    if ($failed) {
        $status .= ' Failed: '.implode(', ', $failed).'.';
    }

    return back()->with($sent > 0 ? 'sms_success' : 'sms_error', $status);
};

Route::post('/staff/notifications/send-to-staff', $sendStaffSms)
    ->name('staff.notifications.send-to-staff')
    ->middleware(EnsureUserHasRole::class.':admin');

Route::middleware(EnsureAdminIsLoggedIn::class)->group(function () use ($loadSmsParents, $loadSmsStaff, $sendParentSms, $sendStaffSms) {
    Route::get('/admin/notifications', function () use ($loadSmsParents, $loadSmsStaff) {
        return view('admin.notifications', [
            'smsDriver' => config('services.sms.driver', 'log'),
            'smsParents' => $loadSmsParents(),
            'smsStaff' => $loadSmsStaff(),
        ]);
    })->name('admin.notifications');

    Route::post('/admin/notifications/send-to-parents', $sendParentSms)
        ->name('admin.notifications.send-to-parents');

    Route::post('/admin/notifications/send-to-staff', $sendStaffSms)
        ->name('admin.notifications.send-to-staff');
});

Route::post('/staff/notifications/test-sms', function (Request $request, SmsService $smsService) {
    $data = $request->validate([
        'recipient_phone' => ['required', 'string', 'max:30'],
        'message' => ['required', 'string', 'max:320'],
    ]);

    try {
        $result = $smsService->send($data['recipient_phone'], $data['message']);
        $supabaseUrl = env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL');
        $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');

        if ($supabaseUrl && $serviceKey) {
            Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=minimal',
            ])->post("{$supabaseUrl}/rest/v1/notifications", [
                'notification_type' => 'sms_test',
                'recipient_phone' => $data['recipient_phone'],
                'message' => $data['message'],
                'sent_at' => now()->toIso8601String(),
            ]);
        }

        $message = $result['driver'] === 'supabase_queue'
            ? 'SMS test queued for the laptop GSM modem.'
            : 'SMS test completed using the '.ucfirst($result['driver']).' driver.';

        return back()->with('sms_success', $message);
    } catch (\Throwable $exception) {
        Log::error('SMS test failed.', ['message' => $exception->getMessage()]);

        return back()->withInput()->withErrors(['sms' => $exception->getMessage()]);
    }
})->name('staff.notifications.test-sms')->middleware(EnsureUserHasRole::class.':admin');

Route::get('/staff/profile', function (Request $request) {
    $user = $request->session()->get('supabase_user');

    // try cookie token if session missing
    if (! $user) {
        $token = $request->cookie('supabase_token');
        if ($token) {
            $supabaseUrl = env('VITE_SUPABASE_URL');
            $anonKey = env('VITE_SUPABASE_ANON_KEY');
            if ($supabaseUrl && $anonKey) {
                $resp = Http::withHeaders([
                    'apikey' => $anonKey,
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ])->get("{$supabaseUrl}/auth/v1/user");

                if (! $resp->failed()) {
                    $user = $resp->json();
                    $request->session()->put('supabase_user', $user);
                }
            }
        }
    }

    $staffRow = null;
    $supabaseUrl = env('VITE_SUPABASE_URL');
    $serviceKey = env('SUPABASE_SERVICE_ROLE_KEY') ?? env('SUPABASE_SERVICE_KEY');

    if ($user && $supabaseUrl && $serviceKey) {
        try {
            $headers = [
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Accept' => 'application/json',
            ];

            $userId = $user['id'] ?? ($user['sub'] ?? null);
            if ($userId) {
                $resp = Http::withHeaders($headers)->get("{$supabaseUrl}/rest/v1/staff", [
                    'select' => '*',
                    'id' => 'eq.'.$userId,
                    'limit' => 1,
                ]);

                if ($resp->ok()) {
                    $rows = $resp->json();
                    $staffRow = collect($rows)->first();
                    if (is_array($staffRow)) {
                        $staffRow['full_name'] = NameParts::display($staffRow);
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore and show empty profile
        }
    }

    return view('staff.profile', ['user' => $user, 'staff' => $staffRow]);
})->name('staff.profile')->middleware(EnsureUserHasRole::class.':admin');
