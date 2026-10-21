<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudentAccountDeletionTest extends TestCase
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

    public function test_deleting_student_confirms_supabase_removed_the_record(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/students*' => Http::response([['id' => 42]], 200),
        ]);

        $this->withSession(['admin_id' => 'admin-1'])
            ->deleteJson('/admin/students/42')
            ->assertOk()
            ->assertJsonPath('message', 'Student deleted successfully');

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://example.supabase.co/rest/v1/students?id=eq.42'
            && $request->hasHeader('Prefer', 'return=representation'));
    }

    public function test_deleting_missing_student_does_not_report_success(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/students*' => Http::response([], 200),
        ]);

        $this->withSession(['admin_id' => 'admin-1'])
            ->deleteJson('/admin/students/42')
            ->assertNotFound()
            ->assertJsonPath('error', 'Student was not found in Supabase; no record was deleted.');
    }

    public function test_supabase_constraint_error_is_reported_when_student_cannot_be_deleted(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/students*' => Http::response([
                'message' => 'Student is referenced by existing records.',
            ], 409),
        ]);

        $this->withSession(['admin_id' => 'admin-1'])
            ->deleteJson('/admin/students/42')
            ->assertStatus(502)
            ->assertJsonPath('error', 'Student is referenced by existing records.');
    }
}
