<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupabasePhoneLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('VITE_SUPABASE_ANON_KEY=test-anon-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
    }

    public function test_supabase_login_authenticates_by_normalized_phone_without_email(): void
    {
        Http::fake([
            'https://example.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'phone-session-token',
                'user' => ['id' => 'auth-user-phone-1', 'phone' => '+639171234567'],
            ], 200),
            'https://example.supabase.co/auth/v1/user' => Http::response([
                'id' => 'auth-user-phone-1',
                'phone' => '+639171234567',
                'user_metadata' => ['role' => 'parent'],
            ], 200),
        ]);

        $this->postJson('/supabase/login', [
            'phone' => '09171234567',
            'password' => 'CorrectPassword123!',
        ])->assertOk()
            ->assertJsonPath('user.phone', '+639171234567');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/auth/v1/token')) {
                return false;
            }

            parse_str($request->body(), $credentials);

            return ($credentials['phone'] ?? null) === '+639171234567'
                && ! array_key_exists('email', $credentials)
                && ($credentials['password'] ?? null) === 'CorrectPassword123!';
        });
    }

    public function test_email_only_login_requests_are_rejected(): void
    {
        Http::fake();

        $this->postJson('/supabase/login', [
            'email' => 'parent@example.com',
            'password' => 'CorrectPassword123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        Http::assertNothingSent();
    }

    public function test_staff_authorization_check_uses_auth_user_id(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([
                ['id' => 'staff-auth-user-1', 'email' => 'contact@example.com', 'is_approved' => true, 'is_active' => true],
            ], 200),
        ]);

        $this->getJson('/supabase/staff-check?user_id=staff-auth-user-1')
            ->assertOk()
            ->assertJsonPath('authorized', true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/staff')
                && ($request->data()['id'] ?? null) === 'eq.staff-auth-user-1'
                && ! array_key_exists('email', $request->data());
        });
    }

    public function test_signup_requires_phone_verification_and_defers_auth_creation_until_approval(): void
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
        config([
            'services.sms.driver' => 'twilio',
            'services.sms.twilio.account_sid' => 'AC123',
            'services.sms.twilio.auth_token' => 'test-token',
            'services.sms.twilio.from' => '+15005550006',
        ]);

        Http::fake(function ($request) use (&$otp) {
            if (str_contains($request->url(), 'api.twilio.com')) {
                preg_match('/code is ([0-9]{6})\./', $request->data()['Body'], $matches);
                $otp = $matches[1] ?? null;

                return Http::response(['sid' => 'SM555'], 201);
            }
            return Http::response([], 200);
        });

        $flowId = $this->postJson('/supabase/phone-verification/request', [
            'account_type' => 'staff',
            'phone_number' => '09171234568',
        ])->assertOk()
            ->assertJsonPath('masked_phone', '+63******4568')
            ->json('flow_id');

        $this->assertNotNull($otp);
        $this->postJson('/supabase/phone-verification/confirm', [
            'flow_id' => $flowId,
            'otp' => $otp,
        ])->assertOk()
            ->assertJsonPath('message', 'Mobile number verified. You can now create your account.');

        $this->postJson('/supabase/register-user', [
            'full_name' => 'Staff User',
            'username' => 'staffuser1',
            'password' => 'secret123',
            'mobile_number' => '09171234569',
            'role' => 'staff',
            'phone_verification_flow_id' => $flowId,
        ])->assertUnprocessable();

        $this->postJson('/supabase/register-user', [
            'full_name' => 'Staff User',
            'username' => 'staffuser1',
            'password' => 'secret123',
            'mobile_number' => '09171234568',
            'role' => 'staff',
            'phone_verification_flow_id' => $flowId,
        ])->assertStatus(202)
            ->assertJsonPath('message', 'Registration submitted. Your account will be created after administrator approval.');

        $this->assertDatabaseHas('pending_registrations', [
            'phone_number' => '+639171234568',
            'role' => 'staff',
            'status' => 'pending',
        ]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users'));
        $this->assertTrue(Cache::missing('signup-phone-verification:'.$flowId));
    }

    public function test_signup_cannot_create_an_auth_user_without_a_verified_phone(): void
    {
        Http::fake();

        $this->postJson('/supabase/register-user', [
            'full_name' => 'Staff User',
            'password' => 'secret123',
            'mobile_number' => '09171234568',
            'role' => 'staff',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone_verification_flow_id');

        Http::assertNothingSent();
    }

    public function test_signup_rejects_email_fields_for_phone_only_accounts(): void
    {
        $this->postJson('/supabase/register-user', [
            'full_name' => 'Staff User',
            'username' => 'staffuser1',
            'email' => 'staff@example.com',
            'password' => 'secret123',
            'mobile_number' => '09171234568',
            'role' => 'staff',
            'phone_verification_flow_id' => str_repeat('a', 48),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
