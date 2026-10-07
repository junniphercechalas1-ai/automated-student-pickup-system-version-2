<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParentPasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('VITE_SUPABASE_ANON_KEY=test-anon-key');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
    }

    public function test_parent_can_reset_password_with_an_sms_code(): void
    {
        $otp = null;
        config([
            'services.sms.driver' => 'twilio',
            'services.sms.twilio.account_sid' => 'AC123',
            'services.sms.twilio.auth_token' => 'test-token',
            'services.sms.twilio.from' => '+15005550006',
        ]);

        Http::fake(function ($request) use (&$otp) {
            if (str_contains($request->url(), '/rest/v1/parents')) {
                if (($request->data()['mobile_number'] ?? null) !== 'eq.+639171234567') {
                    return Http::response([], 200);
                }

                return Http::response([[
                    'auth_user_id' => 'parent-user-1',
                    'mobile_number' => '+639171234567',
                ]], 200);
            }

            if (str_contains($request->url(), 'api.twilio.com')) {
                preg_match('/code is ([0-9]{6})\./', $request->data()['Body'], $matches);
                $otp = $matches[1] ?? null;

                return Http::response(['sid' => 'SM123'], 201);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/parent-user-1')) {
                return Http::response(['id' => 'parent-user-1'], 200);
            }

            return Http::response([], 200);
        });

        $sendResponse = $this->postJson('/supabase/reset-password', [
            'account_type' => 'parent',
            'method' => 'sms',
            'phone' => '09171234567',
        ]);

        $sendResponse->assertOk()
            ->assertJsonPath('masked_phone', '+63******4567')
            ->assertJsonPath('message', 'A reset code was sent to +63******4567. The code expires in 5 minutes.');
        $flowId = $sendResponse->json('flow_id');
        $this->assertNotNull($otp);
        $wrongOtp = $otp === '000000' ? '000001' : '000000';

        $this->postJson('/supabase/reset-password/phone', [
            'flow_id' => $flowId,
            'code' => $wrongOtp,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable();

        $this->postJson('/supabase/reset-password/phone', [
            'flow_id' => $flowId,
            'code' => $otp,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk()
            ->assertJsonPath('message', 'Your password has been changed. You can now sign in.');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/auth/v1/admin/users/parent-user-1')
                && $request->method() === 'PUT'
                && $request->data()['password'] === 'NewPassword123!';
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.twilio.com')
                && $request->data()['To'] === '+639171234567';
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/v1/recover'));
    }

    public function test_email_password_reset_still_sends_the_supabase_recovery_link(): void
    {
        Http::fake([
            'https://example.supabase.co/auth/v1/recover' => Http::response([], 200),
        ]);

        $this->postJson('/supabase/reset-password', [
            'method' => 'email',
            'email' => 'parent@example.com',
            'redirect_to' => 'https://example.com/parent/login',
        ])->assertOk()
            ->assertJsonPath('message', 'Password reset email sent.');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/auth/v1/recover')
                && $request->data()['email'] === 'parent@example.com';
        });
    }

    public function test_staff_can_reset_password_with_an_sms_code_before_approval(): void
    {
        $otp = null;
        config([
            'services.sms.driver' => 'twilio',
            'services.sms.twilio.account_sid' => 'AC123',
            'services.sms.twilio.auth_token' => 'test-token',
            'services.sms.twilio.from' => '+15005550006',
        ]);

        Http::fake(function ($request) use (&$otp) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                if (($request->data()['phone_number'] ?? null) !== 'eq.+639171234568') {
                    return Http::response([], 200);
                }

                return Http::response([[
                    'id' => 'staff-user-1',
                    'is_approved' => false,
                    'phone_number' => '+639171234568',
                ]], 200);
            }

            if (str_contains($request->url(), 'api.twilio.com')) {
                preg_match('/code is ([0-9]{6})\./', $request->data()['Body'], $matches);
                $otp = $matches[1] ?? null;

                return Http::response(['sid' => 'SM456'], 201);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/staff-user-1')) {
                return Http::response(['id' => 'staff-user-1'], 200);
            }

            return Http::response([], 200);
        });

        $sendResponse = $this->postJson('/staff/password-reset/request', [
            'phone_number' => '09171234568',
        ]);

        $sendResponse->assertOk()
            ->assertJsonPath('masked_phone', '+63******4568')
            ->assertJsonPath('message', 'Verification code sent to your registered mobile number.')
            ->assertJsonMissingPath('otp');
        $flowId = $sendResponse->json('flow_id');
        $this->assertNotNull($otp);

        $verifyResponse = $this->postJson('/staff/password-reset/verify', [
            'flow_id' => $flowId,
            'otp' => $otp,
        ])->assertOk()
            ->assertJsonPath('message', 'Verification successful. Create a new password.')
            ->assertJsonMissingPath('otp');

        $resetToken = $verifyResponse->json('reset_token');
        $this->assertNotNull($resetToken);

        $this->postJson('/staff/password-reset/password', [
            'flow_id' => $flowId,
            'reset_token' => $resetToken,
            'password' => 'StaffPassword123!',
            'password_confirmation' => 'StaffPassword123!',
        ])->assertOk()
            ->assertJsonPath('redirect', '/staff/login');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/auth/v1/admin/users/staff-user-1')
                && $request->method() === 'PUT'
                && $request->data()['password'] === 'StaffPassword123!';
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.twilio.com')
                && $request->data()['To'] === '+639171234568';
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/v1/recover'));
    }

    public function test_staff_profile_saves_the_registered_number_in_normalized_format(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([['id' => 'staff-user-1']], 201);
            }

            return Http::response([], 200);
        });

        $this->postJson('/supabase/staff-profile', [
            'user_id' => 'staff-user-1',
            'full_name' => 'Sam Staff',
            'email' => 'staff@example.com',
            'phone_number' => '09171234568',
            'is_approved' => false,
        ])->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/rest/v1/staff')
                && $request->data()['phone_number'] === '+639171234568';
        });
    }

    public function test_staff_sms_reset_reports_an_unregistered_number_without_sending(): void
    {
        config([
            'services.sms.driver' => 'twilio',
            'services.sms.twilio.account_sid' => 'AC123',
            'services.sms.twilio.auth_token' => 'test-token',
            'services.sms.twilio.from' => '+15005550006',
        ]);
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([], 200),
            'https://api.twilio.com/*' => Http::response(['sid' => 'SHOULD_NOT_SEND'], 201),
        ]);

        $this->postJson('/staff/password-reset/request', [
            'phone_number' => '09179999999',
        ])->assertNotFound()
            ->assertJsonPath('message', 'No staff account is registered with this number.');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.twilio.com'));
    }

    public function test_staff_sms_reset_rejects_email_and_never_calls_email_recovery(): void
    {
        Http::fake();

        $this->postJson('/staff/password-reset/request', [
            'phone_number' => '+639171234568',
            'email' => 'staff@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Http::assertNothingSent();
    }

    public function test_staff_code_can_only_be_verified_once(): void
    {
        $otp = null;
        config([
            'services.sms.driver' => 'twilio',
            'services.sms.twilio.account_sid' => 'AC123',
            'services.sms.twilio.auth_token' => 'test-token',
            'services.sms.twilio.from' => '+15005550006',
        ]);

        Http::fake(function ($request) use (&$otp) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([[
                    'id' => 'staff-user-1',
                    'phone_number' => '+639171234568',
                ]], 200);
            }
            if (str_contains($request->url(), 'api.twilio.com')) {
                preg_match('/code is ([0-9]{6})\./', $request->data()['Body'], $matches);
                $otp = $matches[1] ?? null;

                return Http::response(['sid' => 'SM789'], 201);
            }

            return Http::response([], 200);
        });

        $flowId = $this->postJson('/staff/password-reset/request', [
            'phone_number' => '09171234568',
        ])->assertOk()->json('flow_id');

        $this->assertNotNull($otp);
        $verifyResponse = $this->postJson('/staff/password-reset/verify', [
            'flow_id' => $flowId,
            'otp' => $otp,
        ])->assertOk();

        $this->postJson('/staff/password-reset/verify', [
            'flow_id' => $flowId,
            'otp' => $otp,
        ])->assertUnprocessable();

        $this->postJson('/staff/password-reset/password', [
            'flow_id' => $flowId,
            'reset_token' => $verifyResponse->json('reset_token'),
            'password' => 'StaffPassword123!',
            'password_confirmation' => 'StaffPassword123!',
        ])->assertOk();

        $this->postJson('/staff/password-reset/password', [
            'flow_id' => $flowId,
            'reset_token' => $verifyResponse->json('reset_token'),
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ])->assertUnprocessable();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/v1/recover'));
    }

    public function test_staff_otp_expires_after_five_minutes(): void
    {
        $otp = null;
        config([
            'services.sms.driver' => 'twilio',
            'services.sms.twilio.account_sid' => 'AC123',
            'services.sms.twilio.auth_token' => 'test-token',
            'services.sms.twilio.from' => '+15005550006',
        ]);

        Http::fake(function ($request) use (&$otp) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([[
                    'id' => 'staff-user-1',
                    'phone_number' => '+639171234568',
                ]], 200);
            }
            if (str_contains($request->url(), 'api.twilio.com')) {
                preg_match('/code is ([0-9]{6})\./', $request->data()['Body'], $matches);
                $otp = $matches[1] ?? null;

                return Http::response(['sid' => 'SM890'], 201);
            }

            return Http::response([], 200);
        });

        $flowId = $this->postJson('/staff/password-reset/request', [
            'phone_number' => '09171234568',
        ])->assertOk()->json('flow_id');
        $this->assertNotNull($otp);

        $this->travel(6)->minutes();
        $expiredResponse = $this->postJson('/staff/password-reset/verify', [
            'flow_id' => $flowId,
            'otp' => $otp,
        ]);
        $this->travelBack();

        $expiredResponse->assertUnprocessable()
            ->assertJsonPath('message', 'The verification code is invalid, expired, or already used.');
    }

    public function test_staff_login_page_uses_only_sms_recovery(): void
    {
        $this->get('/staff/login')
            ->assertOk()
            ->assertSee('Account Number')
            ->assertSee('/staff/password-reset/request')
            ->assertDontSee('/supabase/reset-password')
            ->assertDontSee('Email reset link');

        $this->get('/staff/forgot-password')
            ->assertRedirect('/staff/login#forgot-password');
    }
}