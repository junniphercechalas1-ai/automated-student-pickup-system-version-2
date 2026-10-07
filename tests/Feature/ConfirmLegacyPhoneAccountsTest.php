<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfirmLegacyPhoneAccountsTest extends TestCase
{
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['VITE_SUPABASE_URL', 'SUPABASE_SERVICE_KEY'] as $name) {
            $this->originalEnvironment[$name] = [
                'process' => getenv($name),
                'env' => $_ENV[$name] ?? null,
                'server' => $_SERVER[$name] ?? null,
                'env_exists' => array_key_exists($name, $_ENV),
                'server_exists' => array_key_exists($name, $_SERVER),
            ];
        }

        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $name => $values) {
            putenv($values['process'] === false ? $name : $name.'='.$values['process']);

            if ($values['env_exists']) {
                $_ENV[$name] = $values['env'];
            } else {
                unset($_ENV[$name]);
            }

            if ($values['server_exists']) {
                $_SERVER[$name] = $values['server'];
            } else {
                unset($_SERVER[$name]);
            }
        }

        parent::tearDown();
    }

    public function test_legacy_phone_confirmation_command_is_dry_run_until_apply_is_requested(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                if (($query['select'] ?? null) === 'auth_user_id,phone_number') {
                    return Http::response([
                        'message' => "Could not find the 'phone_number' column",
                    ], 400);
                }

                return Http::response([[
                    'auth_user_id' => 'parent-user-1',
                    'mobile_number' => '09170001111',
                ]], 200);
            }

            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([[
                    'id' => 'staff-user-1',
                    'phone_number' => '+639170002222',
                ]], 200);
            }

            return Http::response(['id' => 'confirmed-user'], 200);
        });

        $this->artisan('supabase:confirm-legacy-phones')
            ->expectsOutput('2 existing account phone number(s) qualify for confirmation.')
            ->expectsOutput('Dry run only. Re-run with --apply to mark these Supabase Auth phone numbers confirmed.')
            ->assertExitCode(0);

        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');

        $this->artisan('supabase:confirm-legacy-phones', ['--apply' => true])
            ->expectsOutput('Confirmed 2 legacy phone number(s).')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT' || ! str_contains($request->url(), '/auth/v1/admin/users/')) {
                return false;
            }

            return $request->data()['phone_confirm'] === true
                && in_array($request->data()['phone'], ['+639170001111', '+639170002222'], true);
        });
    }
}
