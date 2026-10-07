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
                    'email' => 'admin@example.com',
                    'phone_number' => '+639171234567',
                    'auth_user_id' => '11111111-1111-1111-1111-111111111111',
                    'password_hash' => '',
                    'first_name' => 'Admin',
                    'last_name' => 'Administrator',
                    'is_active' => true,
                ],
            ], 200),
            'https://example.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'supabase-access-token',
                'user' => [
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'phone' => '+639171234567',
                ],
            ], 200),
        ]);

        $response = $this->post('/admin/login', [
            'phone' => '09171234567',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertSame('2d9df428-9249-421f-abdd-a186f877728', session('admin_id'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/token')
            && ($request->data()['phone'] ?? null) === '+639171234567'
            && ! array_key_exists('email', $request->data()));
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
        $this->app->instance(SmsService::class, $smsService);

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

    public function test_admin_password_reset_updates_profile_hash_when_no_auth_user_is_linked(): void
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
        $this->app->instance(SmsService::class, $smsService);

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
            if (str_contains($request->url(), '/rest/v1/admin?id=eq.admin-profile-id') && $request->method() === 'PATCH') {
                return Http::response([], 204);
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
        ])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/admin?id=eq.admin-profile-id')
            && $request->method() === 'PATCH'
            && Hash::check('NewAdminPassword123!', $request->data()['password_hash'] ?? ''));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users/'));
    }

    public function test_existing_admin_can_sign_in_by_phone_while_auth_still_uses_legacy_email(): void
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
                    'email' => 'admin@example.com',
                    'phone_number' => '+639171234567',
                    'auth_user_id' => 'admin-auth-id',
                    'password_hash' => '',
                    'is_active' => true,
                ]], 200);
            }

            if (str_contains($request->url(), '/auth/v1/token')) {
                if (($request->data()['phone'] ?? null) === '+639171234567') {
                    return Http::response(['message' => 'Phone auth is not enabled for this legacy user.'], 400);
                }

                return Http::response([
                    'user' => ['id' => 'admin-auth-id', 'email' => 'admin@example.com'],
                ], 200);
            }

            return Http::response([], 200);
        });

        $this->post('/admin/login', [
            'phone' => '+639171234567',
            'password' => 'Secret123!',
        ])->assertRedirect('/admin/dashboard');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/token')
            && ($request->data()['email'] ?? null) === 'admin@example.com');
    }
}
