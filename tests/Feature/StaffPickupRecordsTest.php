<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffPickupRecordsTest extends TestCase
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

    public function test_pickup_history_handles_a_pickup_with_no_matching_parent_profile(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/pickups')) {
                return Http::response([[
                    'id' => 15,
                    'students_id' => 7,
                    'parents_id' => 23,
                    'picked_at' => '2026-10-06T09:20:00+00:00',
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

            return Http::response([], 200);
        });

        $this->withSession([
            'supabase_user' => [
                'id' => 'admin-user-id',
                'user_metadata' => ['role' => 'admin'],
            ],
        ])->get('/staff/pickup-records')
            ->assertOk()
            ->assertSee('Alex Student')
            ->assertSee('No Guardian')
            ->assertSee('pickupPrintReport')
            ->assertSee('Print Report')
            ->assertSee('window.print()')
            ->assertDontSee('Export Report');
    }

    public function test_pickup_endpoint_writes_the_students_id_foreign_key(): void
    {
        Http::fake(fn () => Http::response([[
            'id' => 15,
            'students_id' => 7,
            'parents_id' => 23,
            'picked_at' => '2026-10-06T09:20:00+00:00',
        ]], 201));

        $this->postJson('/supabase/pickups', ['student_id' => 7, 'parent_id' => 23])
            ->assertSuccessful()
            ->assertJsonPath('0.student_id', 7)
            ->assertJsonPath('0.parent_id', 23)
            ->assertJsonMissingPath('0.students_id')
            ->assertJsonMissingPath('0.parents_id');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/pickups')
            && $request->data()['students_id'] === 7
            && $request->data()['parents_id'] === 23
            && ! array_key_exists('student_id', $request->data())
            && ! array_key_exists('parent_id', $request->data()));
    }
}
