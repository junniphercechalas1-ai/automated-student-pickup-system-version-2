<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffHeaderUserDisplayTest extends TestCase
{
    public function test_staff_layout_uses_authenticated_user_name_and_role(): void
    {
        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([
                [
                    'id' => 'staff-user-id',
                    'email' => 'junnipher@example.com',
                    'is_approved' => true,
                    'is_active' => true,
                    'created_at' => '2023-09-14T10:30:00+00:00',
                ],
            ], 200),
        ]);

        $response = $this->withSession([
            'supabase_user' => [
                'id' => 'staff-user-id',
                'email' => 'junnipher@example.com',
                'user_metadata' => [
                    'role' => 'staff',
                    'full_name' => 'Junnipher Echalas',
                ],
            ],
        ])->get('/staff/profile');

        $response->assertOk();
        $response->assertSee('Junnipher Echalas');
        $response->assertSee('Gate Staff');
        $response->assertSee('Staff since 2023');
        $response->assertDontSee('Mark Dela Cruz');
    }
}
