<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffAccountDeletionTest extends TestCase
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

    public function test_deleting_staff_also_deletes_its_linked_auth_user(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([[
                    'id' => 'staff-profile-id',
                    'auth_user_id' => 'staff-auth-id',
                    'is_approved' => true,
                ]], 200);
            }

            return Http::response([], 204);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->deleteJson('/admin/staff/staff-profile-id')
            ->assertOk()
            ->assertJsonPath('message', 'Staff account deleted successfully.');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/auth/v1/admin/users/staff-auth-id'));
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/rest/v1/staff?id=eq.staff-profile-id'));
    }

    public function test_staff_profile_is_kept_when_auth_user_deletion_fails(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([[
                    'id' => 'staff-profile-id',
                    'auth_user_id' => 'staff-auth-id',
                    'is_approved' => true,
                ]], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/staff-auth-id')) {
                return Http::response(['message' => 'Auth deletion failed'], 503);
            }

            return Http::response([], 204);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->deleteJson('/admin/staff/staff-profile-id')
            ->assertStatus(502)
            ->assertJsonPath('error', 'The staff sign-in account could not be deleted. The staff profile was kept; please retry or contact an administrator.');

        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/rest/v1/staff'));
    }
}
