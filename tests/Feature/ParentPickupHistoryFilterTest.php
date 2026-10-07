<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParentPickupHistoryFilterTest extends TestCase
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
    }

    public function test_parent_pickup_history_renders_local_date_key_and_clickable_filter(): void
    {
        config(['app.timezone' => 'Asia/Manila']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'id' => 23,
                    'auth_user_id' => 'parent-user-id',
                    'student_id' => 7,
                    'first_name' => 'Jordan',
                    'middle_name' => null,
                    'last_name' => 'Parent',
                    'phone_number' => '+639171234567',
                    'relationship' => 'Guardian',
                    'is_approved' => true,
                    'is_active' => true,
                ]], 200);
            }

            if (str_contains($request->url(), '/rest/v1/students')) {
                return Http::response([[
                    'id' => 7,
                    'first_name' => 'Alex',
                    'middle_name' => null,
                    'last_name' => 'Student',
                    'grade_level' => 'Grade 1',
                    'section' => 'A',
                ]], 200);
            }

            if (str_contains($request->url(), '/rest/v1/pickups')) {
                return Http::response([[
                    'id' => 15,
                    'student_id' => 7,
                    'picked_at' => '2026-10-06T02:09:00+00:00',
                ]], 200);
            }

            return Http::response([], 200);
        });

        $this->withSession([
            'supabase_user' => [
                'id' => 'parent-user-id',
                'email' => 'parent@example.invalid',
                'user_metadata' => [
                    'role' => 'parent',
                    'full_name' => 'Jordan Parent',
                    'mobile_number' => '+639171234567',
                ],
            ],
        ])->get('/parent/pickup-history')
            ->assertOk()
            ->assertSee('id="parentPickupFilterDate"', false)
            ->assertSee('id="parentPickupFilterButton" type="button"', false)
            ->assertSee('data-pickup-date="2026-10-06"', false)
            ->assertSee('row.dataset.pickupDate === selectedDate', false);
    }
}
