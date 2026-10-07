<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffProfileSaveTest extends TestCase
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

        Storage::fake('public');
    }

    public function test_staff_profile_accepts_photo_upload_and_saves_storage_path(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([
                [
                    'id' => 99,
                    'user_id' => 'auth-user-123',
                    'full_name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                    'phone_number' => '09170001111',
                    'is_approved' => true,
                    'photo_url' => '/storage/staff-profiles/avatar.jpg',
                ],
            ], 200),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'staff-photo');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAF' . 'c0A0AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJ0UkG' . 'AAAAAABJRU5ErkJggg==');
        file_put_contents($tempFile, $png);

        $response = $this->call('POST', '/supabase/staff-profile', [
            'user_id' => 'auth-user-123',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone_number' => '09170001111',
            'is_approved' => '1',
        ], [], [
            'photo' => new UploadedFile($tempFile, 'avatar.png', 'image/png', null, true),
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty(Storage::disk('public')->allFiles('staff-profiles'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/staff')
                && ! empty($request->data()['photo_url'])
                && str_contains($request->data()['photo_url'], 'staff-profiles');
        });
    }

    public function test_staff_profile_retries_without_photo_url_when_supabase_schema_is_missing_the_column(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::sequence()
                ->push(['message' => 'Could not find the \'photo_url\' column of \'staff\' in the schema cache'], 400)
                ->push([
                    'id' => 99,
                    'user_id' => 'auth-user-456',
                    'full_name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone_number' => '09999999999',
                    'is_approved' => true,
                ], 200),
        ]);

        $response = $this->post('/supabase/staff-profile', [
            'user_id' => 'auth-user-456',
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone_number' => '09999999999',
            'is_approved' => '1',
            'photo_url' => '/storage/staff-profiles/avatar-fallback.jpg',
        ]);

        $response->assertStatus(200);
        $this->assertSame(true, $response->json('success'));
    }
}
