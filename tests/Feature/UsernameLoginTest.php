<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'VITE_SUPABASE_URL' => 'https://example.supabase.co',
            'VITE_SUPABASE_ANON_KEY' => 'test-anon-key',
            'SUPABASE_SERVICE_KEY' => 'test-service-key',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function test_approved_parent_can_sign_in_with_username_and_password(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/auth/v1/token')) {
                return Http::response([
                    'access_token' => 'parent-access-token',
                    'user' => [
                        'id' => 'parent-auth-id',
                        'user_metadata' => ['role' => 'parent'],
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([['auth_users_id' => 'parent-auth-id']], 200);
            }

            return Http::response([], 404);
        });

        $this->postJson('/supabase/username-login', [
            'username' => 'Pat.Parent',
            'password' => 'secret123',
            'account_type' => 'parent',
        ])->assertOk()
            ->assertExactJson(['access_token' => 'parent-access-token']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/token')
            && $request->data()['email'] === 'pat.parent@accounts.orion.invalid'
            && $request->data()['password'] === 'secret123');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/parents')
            && ($request->data()['select'] ?? null) === 'auth_users_id'
            && ($request->data()['is_approved'] ?? null) === 'eq.true'
            && ($request->data()['is_active'] ?? null) === 'eq.true');
    }

    public function test_parent_login_page_uses_username_auth_and_verifies_the_parent_session(): void
    {
        $this->get('/parent/login')
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee("account_type: 'parent'", false)
            ->assertSee("user?.user_metadata?.role !== 'parent'", false)
            ->assertSee("window.location.href = '/parent/dashboard'", false)
            ->assertDontSee('name="email"', false);
    }

    public function test_staff_login_shows_password_placeholder(): void
    {
        $this->get('/staff/login')
            ->assertOk()
            ->assertSee('id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password"', false);
    }

    public function test_staff_login_requires_an_approved_active_staff_profile(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/auth/v1/token')) {
                return Http::response([
                    'access_token' => 'staff-access-token',
                    'user' => [
                        'id' => 'staff-auth-id',
                        'user_metadata' => ['role' => 'staff'],
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([], 200);
            }

            return Http::response([], 404);
        });

        $this->postJson('/supabase/username-login', [
            'username' => 'teacher01',
            'password' => 'secret123',
            'account_type' => 'staff',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid username or password.');
    }

    public function test_approved_staff_sign_in_returns_the_verified_auth_users_id(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/auth/v1/token')) {
                return Http::response([
                    'access_token' => 'staff-access-token',
                    'user' => [
                        'id' => 'staff-auth-id',
                        'user_metadata' => ['role' => 'staff'],
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([['auth_users_id' => 'staff-auth-id']], 200);
            }

            return Http::response([], 404);
        });

        $this->postJson('/supabase/username-login', [
            'username' => 'teacher01',
            'password' => 'secret123',
            'account_type' => 'staff',
        ])->assertOk()
            ->assertExactJson([
                'access_token' => 'staff-access-token',
                'user_id' => 'staff-auth-id',
            ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/staff')
            && ($request->data()['auth_users_id'] ?? null) === 'eq.staff-auth-id'
            && ($request->data()['is_approved'] ?? null) === 'eq.true'
            && ($request->data()['is_active'] ?? null) === 'eq.true');
    }

    public function test_legacy_username_setup_endpoint_is_removed(): void
    {
        $this->postJson('/supabase/username-activate', [])->assertNotFound();
    }
}
