<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSupabaseLoginTest extends TestCase
{
    public function test_admin_login_uses_supabase_auth_when_auth_user_id_is_present(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('VITE_SUPABASE_ANON_KEY=test-anon-key');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');

        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        Http::fake([
            'https://example.supabase.co/rest/v1/admin*' => Http::response([
                [
                    'id' => '2d9df428-9249-421f-abdd-a186f877728',
                    'username' => 'adminuser',
                    'email' => null,
                    'phone_number' => '+639171234567',
                    'auth_user_id' => '11111111-1111-1111-1111-111111111111',
                    'password_hash' => '',
                    'first_name' => 'Admin',
                    'last_name' => 'Administrator',
                    'is_active' => true,
                ],
            ], 200),
            'https://example.supabase.co/auth/v1/admin/users/11111111-1111-1111-1111-111111111111' => Http::response([
                'id' => '11111111-1111-1111-1111-111111111111',
                'email' => 'admin@example.com',
            ], 200),
            'https://example.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'supabase-access-token',
                'user' => [
                    'id' => '11111111-1111-1111-1111-111111111111',
                        'email' => 'admin@example.com',
                ],
            ], 200),
        ]);

        $response = $this->post('/admin/login', [
            'username' => 'AdminUser',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertSame('2d9df428-9249-421f-abdd-a186f877728', session('admin_id'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/token')
            && ($request->data()['email'] ?? null) === 'admin@example.com'
            && ! array_key_exists('phone', $request->data()));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/admin?')
            && ($request->data()['username'] ?? null) === 'eq.adminuser');
    }

    public function test_admin_profile_password_change_reports_failed_auth_password_save(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('VITE_SUPABASE_ANON_KEY=test-anon-key');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/admin?') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 'admin-profile-id',
                    'auth_user_id' => 'admin-auth-id',
                    'username' => 'adminuser',
                    'email' => 'admin@example.com',
                    'phone_number' => '+639171234567',
                    'is_active' => true,
                ]], 200);
            }

            if (str_contains($request->url(), '/auth/v1/token')) {
                return Http::response(['user' => ['id' => 'admin-auth-id']], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/admin-auth-id')
                && $request->method() === 'GET') {
                return Http::response(['id' => 'admin-auth-id', 'email' => 'admin@example.com'], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/admin-auth-id')
                && $request->method() === 'PUT') {
                return Http::response(['message' => 'Auth service unavailable'], 500);
            }

            return Http::response([], 404);
        });

        $this->withSession(['admin_id' => 'admin-profile-id'])
            ->from('/admin/profile')
            ->patch('/admin/profile/password', [
                'current_password' => 'OldPassword123!',
                'new_password' => 'NewAdminPassword123!',
                'new_password_confirmation' => 'NewAdminPassword123!',
            ])
            ->assertRedirect('/admin/profile')
            ->assertSessionHasErrors('password')
            ->assertSessionMissing('status');
    }

    public function test_admin_profile_password_change_updates_supabase_auth_user(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('VITE_SUPABASE_ANON_KEY=test-anon-key');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/admin?') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 'admin-profile-id',
                    'auth_user_id' => 'admin-auth-id',
                    'username' => 'adminuser',
                    'email' => 'admin@example.com',
                    'phone_number' => '+639171234567',
                    'is_active' => true,
                ]], 200);
            }

            if (str_contains($request->url(), '/auth/v1/token')) {
                return Http::response(['user' => ['id' => 'admin-auth-id']], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/admin-auth-id')
                && $request->method() === 'GET') {
                return Http::response(['id' => 'admin-auth-id', 'email' => 'admin@example.com'], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/admin-auth-id')
                && $request->method() === 'PUT') {
                return Http::response(['id' => 'admin-auth-id'], 200);
            }

            return Http::response([], 404);
        });

        $this->withSession(['admin_id' => 'admin-profile-id'])
            ->from('/admin/profile')
            ->patch('/admin/profile/password', [
                'current_password' => 'OldPassword123!',
                'new_password' => 'NewAdminPassword123!',
                'new_password_confirmation' => 'NewAdminPassword123!',
            ])
            ->assertRedirect('/admin/profile')
            ->assertSessionHas('status', 'Password updated successfully.');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/auth/v1/admin/users/admin-auth-id')
                && $request->method() === 'PUT'
                && ($request->data()['password'] ?? null) === 'NewAdminPassword123!';
        });
    }

    public function test_admin_password_reset_uses_sms_code_and_updates_supabase_auth_password(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('VITE_SUPABASE_ANON_KEY=test-anon-key');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        $otp = null;
        config(['services.sms.driver' => 'gsm']);
        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('send')
            ->once()
            ->with('+639171234568', \Mockery::type('string'))
            ->andReturnUsing(function (string $phone, string $message) use (&$otp): array {
                preg_match('/reset code is ([0-9]{6})\./', $message, $matches);
                $otp = $matches[1] ?? null;

                return ['driver' => 'gsm', 'provider_id' => null];
            });
        $this->instance(SmsService::class, $smsService);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/admin?') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 'admin-profile-id',
                    'email' => 'admin@example.com',
                    'phone_number' => '+639171234568',
                    'auth_user_id' => 'admin-auth-id',
                    'password_hash' => Hash::make('OldPassword123!'),
                    'is_active' => true,
                ]], 200);
            }
            if (str_contains($request->url(), '/auth/v1/admin/users/admin-auth-id') && $request->method() === 'PUT') {
                return Http::response(['id' => 'admin-auth-id'], 200);
            }
            return Http::response([], 200);
        });

        $flowId = $this->postJson('/admin/password-reset/request', [
            'phone' => '09171234568',
        ])->assertOk()->json('flow_id');
        $this->assertNotNull($otp);

        $this->postJson('/admin/password-reset/complete', [
            'flow_id' => $flowId,
            'code' => $otp,
            'password' => 'NewAdminPassword123!',
            'password_confirmation' => 'NewAdminPassword123!',
        ])->assertOk()
            ->assertJsonPath('redirect', '/admin/login');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users/admin-auth-id')
            && $request->method() === 'PUT'
            && ($request->data()['password'] ?? null) === 'NewAdminPassword123!');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/admin?id=eq.admin-profile-id')
            && $request->method() === 'PATCH');
    }

    public function test_admin_password_reset_does_not_claim_success_when_auth_user_is_not_linked(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        $otp = null;
        config(['services.sms.driver' => 'gsm']);
        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (string $phone, string $message) use (&$otp): array {
                preg_match('/reset code is ([0-9]{6})\./', $message, $matches);
                $otp = $matches[1] ?? null;

                return ['driver' => 'gsm', 'provider_id' => null];
            });
        $this->instance(SmsService::class, $smsService);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/admin?')) {
                return Http::response([[
                    'id' => 'admin-profile-id',
                    'phone_number' => '+639171234569',
                    'auth_user_id' => null,
                    'password_hash' => Hash::make('OldPassword123!'),
                    'is_active' => true,
                ]], 200);
            }
            return Http::response([], 200);
        });

        $flowId = $this->postJson('/admin/password-reset/request', [
            'phone' => '+639171234569',
        ])->assertOk()->json('flow_id');
        $this->assertNotNull($otp);
        $this->postJson('/admin/password-reset/complete', [
            'flow_id' => $flowId,
            'code' => $otp,
            'password' => 'NewAdminPassword123!',
            'password_confirmation' => 'NewAdminPassword123!',
        ])->assertServiceUnavailable()
            ->assertJsonPath('message', 'This administrator account is not linked to Supabase Auth. Contact the system administrator.');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/admin?id=eq.admin-profile-id')
            && $request->method() === 'PATCH');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users/'));
    }

    public function test_admin_login_uses_linked_auth_email_for_username_and_password(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('VITE_SUPABASE_ANON_KEY=test-anon-key');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/admin?')) {
                return Http::response([[
                    'id' => 'admin-profile-id',
                    'username' => 'adminuser',
                    'email' => null,
                    'phone_number' => '+639171234567',
                    'auth_user_id' => 'admin-auth-id',
                    'password_hash' => '',
                    'is_active' => true,
                ]], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/admin-auth-id')
                && $request->method() === 'GET') {
                return Http::response(['id' => 'admin-auth-id', 'email' => 'admin@example.com'], 200);
            }

            if (str_contains($request->url(), '/auth/v1/token')) {
                return Http::response(['message' => 'Invalid login credentials.'], 400);
            }

            return Http::response([], 200);
        });

        $this->from('/admin/login')->post('/admin/login', [
            'username' => ' adminuser ',
            'password' => 'Secret123!',
        ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/token')
            && ($request->data()['email'] ?? null) === 'admin@example.com'
            && ! array_key_exists('phone', $request->data()));
    }

    public function test_admin_login_rejects_invalid_username_format(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('VITE_SUPABASE_ANON_KEY=test-anon-key');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['VITE_SUPABASE_ANON_KEY'] = 'test-anon-key';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        Http::fake();

        $this->from('/admin/login')->post('/admin/login', [
            'username' => 'x!',
            'password' => 'Secret123!',
        ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        Http::assertNothingSent();
    }
}
