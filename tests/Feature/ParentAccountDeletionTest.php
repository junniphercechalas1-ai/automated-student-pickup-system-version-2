<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParentAccountDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'VITE_SUPABASE_URL' => 'https://example.supabase.co',
            'SUPABASE_SERVICE_KEY' => 'test-service-key',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

    }

    public function test_deleting_parent_also_deletes_its_linked_auth_user(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'id' => 42,
                    'auth_users_id' => 'parent-auth-id',
                ]], 200);
            }

            if ($request->method() === 'DELETE' && str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([['id' => 42]], 200);
            }

            return Http::response([], 204);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->deleteJson('/admin/parents/42')
            ->assertOk()
            ->assertJsonPath('auth_user_deleted', true);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/auth/v1/admin/users/parent-auth-id'));
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/rest/v1/parents?id=eq.42'));
    }

    public function test_parent_profile_is_kept_when_auth_user_deletion_fails(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'id' => 42,
                    'auth_users_id' => 'parent-auth-id',
                ]], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/parent-auth-id')) {
                return Http::response(['message' => 'Auth deletion failed'], 503);
            }

            return Http::response([], 204);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->deleteJson('/admin/parents/42')
            ->assertStatus(502)
            ->assertJsonPath('error', 'The parent sign-in account could not be deleted. The parent profile was kept; please retry or contact an administrator.');

        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/rest/v1/parents'));
    }
}
