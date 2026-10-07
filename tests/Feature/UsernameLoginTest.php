<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
                return Http::response([['auth_user_id' => 'parent-auth-id']], 200);
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
            && ($request->data()['is_approved'] ?? null) === 'eq.true'
            && ($request->data()['is_active'] ?? null) === 'eq.true');
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

    public function test_existing_approved_parent_can_set_a_username_after_phone_verification(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('role', 20);
            $table->string('phone_number', 20)->unique();
            $table->longText('encrypted_password');
            $table->json('details');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        $flowId = str_repeat('v', 48);
        Cache::put('signup-phone-verification:'.$flowId, [
            'phone' => '+639171234567',
            'account_type' => 'parent',
            'verified_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], now()->addMinutes(5));

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'auth_user_id' => 'legacy-parent-id',
                    'phone_number' => '+639171234567',
                    'is_approved' => true,
                    'is_active' => true,
                ]], 200);
            }
            if (str_contains($request->url(), '/auth/v1/admin/users/legacy-parent-id') && $request->method() === 'GET') {
                return Http::response([
                    'id' => 'legacy-parent-id',
                    'phone' => '+639171234567',
                    'email' => '',
                    'user_metadata' => ['role' => 'parent'],
                ], 200);
            }
            if (str_contains($request->url(), '/auth/v1/admin/users/legacy-parent-id') && $request->method() === 'PUT') {
                return Http::response([
                    'id' => 'legacy-parent-id',
                    'email' => 'newparent@accounts.orion.invalid',
                ], 200);
            }

            return Http::response([], 404);
        });

        $this->postJson('/supabase/username-activate', [
            'username' => 'NewParent',
            'account_type' => 'parent',
            'phone_verification_flow_id' => $flowId,
        ])->assertOk()
            ->assertJsonPath('message', 'Username set. You can now sign in with your username and existing password.');

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/auth/v1/admin/users/legacy-parent-id')
            && $request->data()['email'] === 'newparent@accounts.orion.invalid'
            && $request->data()['email_confirm'] === true
            && $request->data()['user_metadata']['username'] === 'newparent'
            && $request->data()['user_metadata']['role'] === 'parent');
    }
}
