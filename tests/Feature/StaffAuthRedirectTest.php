<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffAuthRedirectTest extends TestCase
{
    public function test_staff_routes_redirect_to_staff_login_when_not_authenticated(): void
    {
        $response = $this->get('/staff/pickup-verification');

        $response->assertRedirect('/staff/login');
    }

    public function test_parent_routes_redirect_to_parent_login_when_not_authenticated(): void
    {
        $response = $this->get('/parent/student');

        $response->assertRedirect('/parent/login');
    }

    public function test_staff_registration_back_to_login_link_opens_dedicated_staff_login_page(): void
    {
        $this->get('/staff/register')
            ->assertOk()
            ->assertSee('href="/staff/login"', false)
            ->assertSee('Back to login');
    }

    public function test_authenticated_staff_session_remains_on_staff_page(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([
                ['auth_users_id' => 'staff-user-id', 'is_approved' => true, 'is_active' => true],
            ], 200),
        ]);

        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'staff-user-id',
                'email' => 'staff@example.com',
                'user_metadata' => ['role' => 'staff'],
            ],
        ])->get('/staff/pickup-verification');

        $response->assertStatus(200);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/staff')
            && ($request->data()['select'] ?? null) === 'auth_users_id,is_approved,is_active'
            && ($request->data()['auth_users_id'] ?? null) === 'eq.staff-user-id');
    }

    public function test_staff_without_an_approved_profile_is_not_redirected_to_parent_login(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([], 200),
        ]);

        $this->withSession([
            'supabase_user' => [
                'id' => 'staff-user-id',
                'email' => 'staff@example.com',
                'user_metadata' => ['role' => 'staff'],
            ],
        ])->get('/staff/pickup-verification')
            ->assertRedirect('/staff/login');
    }

    public function test_parent_account_requires_admin_approval_before_access(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([], 200),
            'https://example.supabase.co/rest/v1/parents*' => Http::response([
                ['id' => 42, 'auth_users_id' => 'parent-user-id', 'is_approved' => false, 'is_active' => true],
            ], 200),
        ]);

        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => ['role' => 'parent'],
            ],
        ])->get('/parent/dashboard');

        $response->assertRedirect('/parent/login');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/parents')
            && ($request->data()['select'] ?? null) === 'id,auth_users_id,is_approved,is_active'
            && ($request->data()['auth_users_id'] ?? null) === 'eq.parent-user-id');
    }
}
