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
                ['id' => 'staff-user-id', 'email' => 'staff@example.com', 'is_approved' => true, 'is_active' => true],
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
                ['id' => 42, 'auth_user_id' => 'parent-user-id', 'email' => 'parent@example.com', 'is_approved' => false, 'is_active' => true],
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
    }
}
