<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Schema\Blueprint;
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

    public function test_parent_profile_is_saved_with_direct_student_id(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/parents' => Http::response([
                ['id' => 42, 'full_name' => 'Jane Doe', 'mobile_number' => '09170001111', 'relationship' => 'Mother'],
            ], 201),
        ]);

        $response = $this->postJson('/supabase/parent-profile', [
            'user_id' => 'auth-user-123',
            'full_name' => 'Jane Doe',
            'mobile_number' => '09170001111',
            'relationship' => 'Mother',
            'student_id' => '13',
            'student_name' => 'Sam Doe',
            'student_class' => 'Grade 3',
            'student_qr_code' => 'QR-001',
        ]);

        $response->assertStatus(200);
        Http::assertSentCount(1);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/parents')
                && $request->data()['first_name'] === 'Jane'
                && $request->data()['last_name'] === 'Doe'
                && $request->data()['student_id'] === 13
                && $request->data()['phone_number'] === '+639170001111'
                && $request->data()['relationship'] === 'Mother'
                && ! array_key_exists('student_name', $request->data())
                && ! array_key_exists('student_class', $request->data())
                && ! array_key_exists('student_qr_code', $request->data());
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
            ->with('+639170001111', \Mockery::type('string'))
            ->andReturnUsing(function (string $phone, string $message) use (&$otp): array {
                preg_match('/verification code is ([0-9]{6})\./', $message, $matches);
                $otp = $matches[1] ?? null;

                return ['driver' => 'gsm', 'provider_id' => null];
            });
        $this->app->instance(SmsService::class, $smsService);

        Http::fake();

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
            'password' => 'secret123',
            'phone_number' => '09170001111',
            'role' => 'parent',
            'phone_verification_flow_id' => $flowId,
            'relationship' => 'Mother',
            'student_id' => '13',
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
                ['id' => 'auth-user-123', 'email' => 'parent@example.com', 'is_approved' => false, 'is_active' => true],
            ], 200),
            'https://example.supabase.co/rest/v1/parents*' => Http::response([
                ['id' => 99, 'auth_user_id' => 'auth-user-123', 'email' => 'parent@example.com', 'is_approved' => true, 'is_active' => true],
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
                ['id' => 99, 'auth_user_id' => 'auth-user-123', 'email' => 'parent@example.com', 'is_approved' => true, 'is_active' => true],
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
