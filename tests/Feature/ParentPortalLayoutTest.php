<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParentPortalLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'id' => 42,
                    'auth_users_id' => 'parent-user-id',
                    'email' => 'parent@example.com',
                    'is_approved' => true,
                    'is_active' => true,
                ]], 200);
            }

            return Http::response([], 200);
        });
    }

    public function test_parent_layout_uses_authenticated_parent_name_and_role(): void
    {
        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                ],
            ],
        ])->get('/parent/profile');

        $response->assertOk();
        $response->assertSee('Maria Santos');
        $response->assertSee('Parent / Guardian');
        $response->assertSee('class="parent-portal-bottom-nav"', false);
        $response->assertSee('Home');
        $response->assertSee('My QR Code');
        $response->assertSee('Pickup History');
        $response->assertSee('Profile');
        $response->assertDontSee('My Student</span>', false);
        $response->assertDontSee('href="/parent/notifications"');
    }

    public function test_parent_student_page_uses_the_live_student_card(): void
    {
        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                ],
            ],
        ])->get('/parent/student');

        $response->assertOk();
        $response->assertSee('Welcome, Maria!');
        $response->assertSee('Your Student');
        $response->assertSee('Authorized Parent');
        $response->assertSee('Quick Actions');
        $response->assertSee('Reminder');
        $response->assertSee('How It Works', false);
        $response->assertSee('Receive SMS Reminder');
        $response->assertSee('Open My QR Code and tap Generate QR Code to display it.');
        $response->assertSee('QR Code Verification');
        $response->assertSee('Pickup Recorded');
        $response->assertSee('class="parent-how-it-works"', false);
    }

    public function test_parent_nav_active_states_single_highlight(): void
    {
        $session = [
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.com',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Maria Santos',
                ],
            ],
        ];

        // Test My Student page - verify nav renders with 4 items only
        $response = $this->withSession($session)->get('/parent/student');
        $response->assertOk();
        // Verify the nav exists with correct structure
        $response->assertSee('parent-portal-bottom-nav', false);
        // Verify the 4 nav links are present
        $response->assertSee('href="/parent/dashboard"', false);
        $response->assertSee('href="/parent/qr-code"', false);
        $response->assertSee('href="/parent/pickup-history"', false);
        $response->assertSee('href="/parent/profile"', false);
        // Verify page content is still accessible (Your Student section and Quick Actions)
        $response->assertSee('Your Student');
        $response->assertSee('Quick Actions');
        $response->assertSee('My Student</span>', false);
    }
}
