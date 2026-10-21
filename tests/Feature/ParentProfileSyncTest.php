<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParentProfileSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
    }

    public function test_parent_profile_is_saved_and_students_are_linked_through_the_junction(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents_students')) {
                return Http::response([], 201);
            }

            return Http::response([
                ['id' => 42, 'full_name' => 'Jane Doe', 'mobile_number' => '09170001111', 'relationship' => 'Mother'],
            ], 201);
        });

        $response = $this->postJson('/supabase/parent-profile', [
            'user_id' => 'auth-user-123',
            'full_name' => 'Jane Doe',
            'mobile_number' => '09170001111',
            'relationship' => 'Mother',
            'student_ids' => [13, 14],
            'student_name' => 'Sam Doe',
            'student_class' => 'Grade 3',
            'student_qr_code' => 'QR-001',
        ]);

        $response->assertStatus(200);
        Http::assertSentCount(3);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/parents')
                && ! str_contains($request->url(), '/rest/v1/parents_students')
                && $request->data()['first_name'] === 'Jane'
                && $request->data()['last_name'] === 'Doe'
                && ! array_key_exists('student_id', $request->data())
                && $request->data()['phone_number'] === '+639170001111'
                && $request->data()['relationship'] === 'Mother'
                && ! array_key_exists('student_name', $request->data())
                && ! array_key_exists('student_class', $request->data())
                && ! array_key_exists('student_qr_code', $request->data());
        });
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/rest/v1/parents_students')) {
                return false;
            }

            $link = $request->data()[0] ?? [];

            return str_contains($request->url(), 'on_conflict=parents_id,students_id')
                && $link['parents_id'] === 42
                && ! array_key_exists('student_id', $link)
                && ! array_key_exists('parent_id', $link)
                && in_array($link['students_id'], [13, 14], true)
                && $link['relationship'] === 'Mother';
        });
    }

    public function test_parent_profile_update_does_not_read_or_write_a_profile_email(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents_students')) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents') && $request->method() === 'GET') {
                return Http::response([['id' => 42]], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents') && ! str_contains($request->url(), '/rest/v1/parents_students') && $request->method() === 'PATCH') {
                return Http::response([['id' => 42]], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/auth-user-123')) {
                return Http::response([
                    'id' => 'auth-user-123',
                    'user_metadata' => ['role' => 'parent'],
                ], 200);
            }

            return Http::response([], 200);
        });

        $this->withSession([
            'supabase_user' => [
                'id' => 'auth-user-123',
                'email' => 'parent@accounts.orion.invalid',
                'user_metadata' => ['role' => 'parent', 'username' => 'jane-doe'],
            ],
        ])->post('/parent/profile/update', [
            'full_name' => 'Jane Doe',
            'mobile_number' => '+639170001111',
            'relationship' => 'Mother',
        ])->assertRedirect(route('parent.profile'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/parents')
                && $request->method() === 'PATCH'
                && ! array_key_exists('email', $request->data());
        });
    }

    public function test_student_list_is_available_via_backend_route(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/students*' => Http::response([
                ['id' => 13, 'first_name' => 'Sam', 'middle_name' => null, 'last_name' => 'Doe', 'grade_level' => 'Grade 3', 'section' => null],
                ['id' => 14, 'first_name' => 'Lia', 'middle_name' => null, 'last_name' => 'Smith', 'grade_level' => 'Grade 4', 'section' => null],
            ], 200),
        ]);

        $response = $this->getJson('/supabase/students');

        $response->assertStatus(200)
            ->assertJsonPath('0.name', 'Sam Doe')
            ->assertJsonPath('1.class', 'Grade 4');
    }

    public function test_parent_qr_verification_authorizes_students_from_the_junction(): void
    {
        $encodedPayload = rtrim(strtr(base64_encode(json_encode([
            'parent_id' => 42,
            'student_id' => 14,
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $token = $encodedPayload.'.'.hash_hmac('sha256', $encodedPayload, (string) env('APP_KEY'));

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents_students')) {
                return Http::response([
                    ['parents_id' => 42, 'students_id' => 13],
                    ['parents_id' => 42, 'students_id' => 14],
                ], 200);
            }
            if (str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'id' => 42,
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'relationship' => 'Mother',
                ]], 200);
            }
            if (str_contains($request->url(), '/rest/v1/students')) {
                return Http::response([[
                    'id' => 14,
                    'first_name' => 'Lia',
                    'last_name' => 'Doe',
                ]], 200);
            }

            return Http::response([], 200);
        });

        $this->getJson('/supabase/verify-parent-qr?token='.urlencode($token))
            ->assertOk()
            ->assertJsonPath('student.id', 14)
            ->assertJsonPath('parent.id', 42);
    }

    public function test_parent_registration_is_staged_until_admin_approval(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('role', 20);
            $table->string('phone_number', 20)->unique();
            $table->longText('encrypted_password');
            $table->json('details');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['role', 'status']);
        });

        $otp = null;
        config(['services.sms.driver' => 'gsm']);
        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('send')
            ->once()
            ->with('+639170001111', \Mockery::type('string'), \Mockery::type(\DateTimeInterface::class))
            ->andReturnUsing(function (string $phone, string $message, \DateTimeInterface $expiresAt) use (&$otp): array {
                preg_match('/verification code is ([0-9]{6})\./', $message, $matches);
                $otp = $matches[1] ?? null;

                return ['driver' => 'gsm', 'provider_id' => null];
            });
        $this->app->instance(SmsService::class, $smsService);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/auth/v1/admin/users')) {
                return Http::response(['users' => []], 200);
            }

            return Http::response([], 200);
        });

        $flowId = $this->postJson('/supabase/phone-verification/request', [
            'account_type' => 'parent',
            'phone_number' => '09170001111',
        ])->assertOk()->json('flow_id');
        $this->assertNotNull($otp);
        $this->postJson('/supabase/phone-verification/confirm', [
            'flow_id' => $flowId,
            'otp' => $otp,
        ])->assertOk();

        $response = $this->postJson('/supabase/register-user', [
            'full_name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'secret123',
            'phone_number' => '09170001111',
            'role' => 'parent',
            'phone_verification_flow_id' => $flowId,
            'relationship' => 'Mother',
            'student_ids' => [13, 14],
            'student_name' => 'Sam Doe',
            'student_class' => 'Grade 3',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('message', 'Registration submitted. Your account will be created after administrator approval.');

        $this->assertDatabaseHas('pending_registrations', [
            'phone_number' => '+639170001111',
            'role' => 'parent',
            'status' => 'pending',
        ]);
        $pendingDetails = json_decode((string) DB::table('pending_registrations')
            ->where('phone_number', '+639170001111')
            ->value('details'), true);
        $this->assertSame([13, 14], $pendingDetails['student_ids']);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_parent_can_update_their_password_from_the_profile_page(): void
    {
        Http::fake([
            'https://example.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'new-access-token',
                'user' => ['id' => 'auth-user-123', 'email' => 'parent@example.com'],
            ], 200),
            'https://example.supabase.co/auth/v1/admin/users/auth-user-123' => Http::response([
                'id' => 'auth-user-123',
                'email' => 'parent@example.com',
            ], 200),
            'https://example.supabase.co/rest/v1/staff*' => Http::response([
                ['id' => 'auth-user-123', 'is_approved' => false, 'is_active' => true],
            ], 200),
            'https://example.supabase.co/rest/v1/parents*' => Http::response([
                ['id' => 99, 'auth_users_id' => 'auth-user-123', 'is_approved' => true, 'is_active' => true],
            ], 200),
        ]);

        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'auth-user-123',
                'phone' => '+639170001111',
                'user_metadata' => ['role' => 'parent'],
            ],
        ])->from('/parent/profile')->post('/parent/profile/password', [
            'current_password' => 'old-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/parent/profile');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/auth/v1/token')
                && $request->data()['phone'] === '+639170001111'
                && $request->data()['password'] === 'old-password';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/auth/v1/admin/users/auth-user-123')
                && $request->method() === 'PUT'
                && $request->data()['password'] === 'NewPassword123!';
        });
    }

    public function test_parent_can_view_their_current_session(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([], 200),
            'https://example.supabase.co/rest/v1/parents*' => Http::response([
                ['id' => 99, 'auth_users_id' => 'auth-user-123', 'is_approved' => true, 'is_active' => true],
            ], 200),
        ]);

        $response = $this->withHeaders([
            'User-Agent' => 'Example Browser/1.0',
        ])->withSession([
            'supabase_user' => [
                'id' => 'auth-user-123',
                'email' => 'parent@example.com',
                'user_metadata' => ['role' => 'parent'],
            ],
        ])->get('/parent/profile/sessions');

        $response->assertOk()
            ->assertSee('Current Session')
            ->assertSee('This device')
            ->assertSee('Example Browser/1.0')
            ->assertSee('Sign Out This Device')
            ->assertSee('does not provide a list of sessions on other devices');
    }
}
