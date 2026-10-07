<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PendingRegistrationApprovalTest extends TestCase
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

    public function test_staff_auth_user_and_profile_are_created_only_when_admin_approves(): void
    {
        $this->mockSmsDelivery('+639171234567');
        $this->createPendingRegistrationTable();
        $registrationId = $this->createPendingRegistration('staff', '+639171234567', [
            'full_name' => 'Sam Staff',
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/auth/v1/admin/users') && $request->method() === 'GET') {
                return Http::response(['users' => []], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users') && $request->method() === 'POST') {
                return Http::response(['id' => 'approved-staff-id', 'phone' => '+639171234567'], 200);
            }

            if (str_contains($request->url(), '/rest/v1/staff') && $request->method() === 'POST') {
                return Http::response([['id' => 'approved-staff-id', 'is_approved' => true]], 201);
            }

            return Http::response([], 200);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson("/admin/staff/{$registrationId}", [
                'is_approved' => true,
                'is_active' => true,
            ])->assertOk()
            ->assertJsonPath('user_id', 'approved-staff-id')
            ->assertJsonPath('notification_sent', true);

        Http::assertSent(function ($request) use ($registrationId) {
            return str_contains($request->url(), '/auth/v1/admin/users')
                && $request->method() === 'POST'
                && str_ends_with($request->data()['email'], '@accounts.orion.invalid')
                && $request->data()['email_confirm'] === true
                && $request->data()['phone'] === '+639171234567'
                && $request->data()['password'] === 'secret123'
                && $request->data()['phone_confirm'] === true
                && $request->data()['user_metadata']['username'] === 'user'.substr(str_replace('-', '', $registrationId), 0, 10);
        });
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/staff')
            && $request->method() === 'POST'
            && $request->data()['id'] === 'approved-staff-id'
            && $request->data()['is_approved'] === true);
        $this->assertDatabaseMissing('pending_registrations', ['id' => $registrationId]);
    }

    public function test_parent_auth_user_and_profile_are_created_only_when_admin_approves(): void
    {
        $this->mockSmsDelivery('+639171234568');
        $this->createPendingRegistrationTable();
        $registrationId = $this->createPendingRegistration('parent', '+639171234568', [
            'full_name' => 'Pat Parent',
            'relationship' => 'Mother',
            'student_id' => 17,
            'student_name' => 'Student One',
            'student_class' => 'Grade 1',
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/auth/v1/admin/users') && $request->method() === 'GET') {
                return Http::response(['users' => []], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users') && $request->method() === 'POST') {
                return Http::response(['id' => 'approved-parent-id'], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents') && $request->method() === 'POST') {
                return Http::response([['id' => 82, 'auth_user_id' => 'approved-parent-id']], 201);
            }

            return Http::response([], 200);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson("/admin/parents/{$registrationId}", [
                'is_approved' => true,
                'is_active' => true,
            ])->assertOk()
            ->assertJsonPath('user_id', 'approved-parent-id')
            ->assertJsonPath('notification_sent', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users')
            && $request->method() === 'POST'
            && str_ends_with($request->data()['email'], '@accounts.orion.invalid')
            && $request->data()['email_confirm'] === true
            && $request->data()['phone'] === '+639171234568'
            && $request->data()['user_metadata']['role'] === 'parent'
            && $request->data()['user_metadata']['username'] === 'user'.substr(str_replace('-', '', $registrationId), 0, 10));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/parents')
            && $request->method() === 'POST'
            && $request->data()['auth_user_id'] === 'approved-parent-id'
            && $request->data()['is_approved'] === true);
        $this->assertDatabaseMissing('pending_registrations', ['id' => $registrationId]);
    }

    public function test_parent_approval_reuses_an_orphaned_auth_user_with_the_same_verified_phone(): void
    {
        $phone = '+639171234577';
        $this->mockSmsDelivery($phone);
        $this->createPendingRegistrationTable();
        $registrationId = $this->createPendingRegistration('parent', $phone, [
            'full_name' => 'Pat Parent',
            'relationship' => 'Mother',
            'student_id' => 17,
        ]);
        Http::fake(function ($request) use ($phone) {
            if (str_contains($request->url(), '/auth/v1/admin/users') && $request->method() === 'GET') {
                return Http::response(['users' => [[
                    'id' => 'orphan-parent-id',
                    'email' => 'legacy-parent@example.invalid',
                    'phone' => $phone,
                    'user_metadata' => ['role' => 'parent'],
                ]], 'last_page' => 1], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users/orphan-parent-id') && $request->method() === 'PUT') {
                return Http::response(['id' => 'orphan-parent-id'], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents') && $request->method() === 'GET') {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents') && $request->method() === 'POST') {
                return Http::response([['id' => 83, 'auth_user_id' => 'orphan-parent-id']], 201);
            }

            return Http::response([], 200);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson("/admin/parents/{$registrationId}", [
                'is_approved' => true,
                'is_active' => true,
            ])->assertOk()
            ->assertJsonPath('user_id', 'orphan-parent-id')
            ->assertJsonPath('notification_sent', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users/orphan-parent-id')
            && $request->method() === 'PUT'
            && $request->data()['email'] === 'user'.substr(str_replace('-', '', $registrationId), 0, 10).'@accounts.orion.invalid'
            && $request->data()['password'] === 'secret123'
            && $request->data()['user_metadata']['role'] === 'parent');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users')
            && $request->method() === 'POST');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/parents')
            && $request->method() === 'POST'
            && $request->data()['auth_user_id'] === 'orphan-parent-id'
            && $request->data()['is_approved'] === true);
        $this->assertDatabaseMissing('pending_registrations', ['id' => $registrationId]);
    }

    public function test_approval_does_not_reuse_a_phone_match_for_a_different_role(): void
    {
        $phone = '+639171234578';
        $this->createPendingRegistrationTable();
        $registrationId = $this->createPendingRegistration('parent', $phone, [
            'full_name' => 'Pat Parent',
            'relationship' => 'Mother',
            'student_id' => 17,
        ]);
        Http::fake(function ($request) use ($phone) {
            if (str_contains($request->url(), '/auth/v1/admin/users') && $request->method() === 'GET') {
                return Http::response(['users' => [[
                    'id' => 'staff-account-id',
                    'email' => 'legacy-staff@example.invalid',
                    'phone' => $phone,
                    'user_metadata' => ['role' => 'staff'],
                ]]], 200);
            }

            return Http::response([], 200);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson("/admin/parents/{$registrationId}", [
                'is_approved' => true,
                'is_active' => true,
            ])->assertStatus(409)
            ->assertJsonPath('error', 'This mobile number is already attached to a different account. Contact the administrator to resolve the duplicate account.');

        Http::assertNotSent(fn ($request) => in_array($request->method(), ['POST', 'PUT'], true)
            && str_contains($request->url(), '/auth/v1/admin/users'));
        $this->assertDatabaseHas('pending_registrations', ['id' => $registrationId, 'status' => 'pending']);
    }

    public function test_pending_staff_request_is_listed_without_exposing_its_encrypted_password(): void
    {
        $this->createPendingRegistrationTable();
        $registrationId = $this->createPendingRegistration('staff', '+639171234569', [
            'full_name' => 'Taylor Staff',
        ]);
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([], 200),
        ]);

        $this->withSession(['admin_id' => 'admin-1'])
            ->getJson('/admin/staff')
            ->assertOk()
            ->assertJsonPath('0.id', $registrationId)
            ->assertJsonPath('0.full_name', 'Taylor Staff')
            ->assertJsonPath('0.is_pending_registration', true)
            ->assertJsonMissingPath('0.encrypted_password');
    }

    public function test_pending_parent_request_is_included_in_admin_parent_list(): void
    {
        $this->createPendingRegistrationTable();
        $registrationId = $this->createPendingRegistration('parent', '+639171234571', [
            'full_name' => 'Jordan Parent',
            'relationship' => 'Guardian',
            'student_id' => 17,
            'student_name' => 'Student One',
        ]);
        Http::fake([
            'https://example.supabase.co/rest/v1/parents*' => Http::response([], 200),
            'https://example.supabase.co/rest/v1/students*' => Http::response([
                ['id' => 17, 'first_name' => 'Student', 'middle_name' => null, 'last_name' => 'One'],
            ], 200),
        ]);

        $this->withSession(['admin_id' => 'admin-1'])
            ->getJson('/admin/parents?format=json')
            ->assertOk()
            ->assertJsonPath('parents.0.id', $registrationId)
            ->assertJsonPath('parents.0.full_name', 'Jordan Parent')
            ->assertJsonPath('parents.0.is_pending_registration', true)
            ->assertJsonPath('counts.pending_parents', 1);
    }

    public function test_parent_list_uses_auth_email_when_profile_email_is_empty(): void
    {
        $this->createPendingRegistrationTable();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'id' => 41,
                    'auth_user_id' => 'parent-auth-id',
                    'first_name' => 'Echo',
                    'middle_name' => null,
                    'last_name' => 'Holland',
                    'student_id' => 17,
                    'phone_number' => '+639170000041',
                    'email' => null,
                    'is_approved' => true,
                    'is_active' => true,
                ]], 200);
            }

            if (str_contains($request->url(), '/rest/v1/students')) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users')) {
                return Http::response(['users' => [[
                    'id' => 'parent-auth-id',
                    'email' => 'echo@accounts.orion.invalid',
                ]]], 200);
            }

            return Http::response([], 200);
        });

        $this->withSession([
            'supabase_user' => [
                'id' => 'admin-auth-id',
                'user_metadata' => ['role' => 'admin'],
            ],
        ])->getJson('/admin/parents?format=json')
            ->assertOk()
            ->assertJsonPath('parents.0.email', 'echo@accounts.orion.invalid');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users')
            && $request->method() === 'GET');
    }

    public function test_declining_a_pending_registration_does_not_create_an_auth_user(): void
    {
        $this->mockSmsDelivery('+639171234570');
        $this->createPendingRegistrationTable();
        $registrationId = $this->createPendingRegistration('parent', '+639171234570', [
            'full_name' => 'Riley Parent',
            'relationship' => 'Father',
            'student_id' => 17,
        ]);
        Http::fake();

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson("/admin/parents/{$registrationId}", [
                'is_approved' => false,
                'is_active' => false,
            ])->assertOk()
            ->assertJsonPath('notification_sent', true);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('pending_registrations', ['id' => $registrationId]);
    }

    public function test_declining_an_existing_parent_deletes_auth_user_and_profile(): void
    {
        $this->mockSmsDelivery('+639171234572');
        $this->createPendingRegistrationTable();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 42,
                    'auth_user_id' => 'parent-auth-id',
                    'phone_number' => '+639171234572',
                ]], 200);
            }

            return Http::response([], 204);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson('/admin/parents/42', [
                'is_approved' => false,
                'is_active' => false,
            ])->assertOk()
            ->assertJsonPath('notification_sent', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users/parent-auth-id')
            && $request->method() === 'DELETE');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/parents?id=eq.42')
            && $request->method() === 'DELETE');
    }

    public function test_declining_an_existing_staff_account_deletes_auth_user_and_profile(): void
    {
        $this->mockSmsDelivery('+639171234573');
        $this->createPendingRegistrationTable();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 'staff-auth-id',
                    'email' => null,
                    'phone_number' => '+639171234573',
                ]], 200);
            }

            return Http::response([], 204);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson('/admin/staff/staff-auth-id', [
                'is_approved' => false,
                'is_active' => false,
            ])->assertOk()
            ->assertJsonPath('notification_sent', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users/staff-auth-id')
            && $request->method() === 'DELETE');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/staff?id=eq.staff-auth-id')
            && $request->method() === 'DELETE');
    }

    public function test_approving_an_existing_parent_profile_sends_sms_to_its_registered_phone(): void
    {
        $this->mockSmsDelivery('+639171234574');
        $this->createPendingRegistrationTable();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 43,
                    'auth_user_id' => 'parent-auth-id',
                    'phone_number' => '+639171234574',
                    'is_approved' => false,
                ]], 200);
            }

            return Http::response([], 204);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson('/admin/parents/43', ['is_approved' => true, 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('notification_sent', true);
    }

    public function test_approving_an_existing_staff_profile_sends_sms_to_its_registered_phone(): void
    {
        $this->mockSmsDelivery('+639171234575');
        $this->createPendingRegistrationTable();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 'staff-auth-id',
                    'email' => null,
                    'phone_number' => '+639171234575',
                    'is_approved' => false,
                ]], 200);
            }

            return Http::response([], 204);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson('/admin/staff/staff-auth-id', ['is_approved' => true, 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('notification_sent', true);
    }

    public function test_approval_succeeds_but_reports_sms_delivery_failure(): void
    {
        config(['services.sms.driver' => 'gsm']);
        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('GSM modem unavailable.'));
        $this->app->instance(SmsService::class, $smsService);
        $this->createPendingRegistrationTable();
        $registrationId = $this->createPendingRegistration('staff', '+639171234576', [
            'full_name' => 'Sam Staff',
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/auth/v1/admin/users') && $request->method() === 'GET') {
                return Http::response(['users' => []], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users') && $request->method() === 'POST') {
                return Http::response(['id' => 'approved-staff-id'], 200);
            }
            if (str_contains($request->url(), '/rest/v1/staff') && $request->method() === 'POST') {
                return Http::response([['id' => 'approved-staff-id']], 201);
            }

            return Http::response([], 200);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->patchJson("/admin/staff/{$registrationId}", ['is_approved' => true, 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('notification_sent', false)
            ->assertJsonPath('message', 'Staff account approved and created. SMS notification could not be sent; check SMS configuration and delivery.');
        $this->assertDatabaseMissing('pending_registrations', ['id' => $registrationId]);
    }

    private function createPendingRegistration(string $role, string $phone, array $details): string
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        $details['username'] ??= 'user'.substr(str_replace('-', '', $id), 0, 10);

        DB::table('pending_registrations')->insert([
            'id' => $id,
            'role' => $role,
            'phone_number' => $phone,
            'encrypted_password' => Crypt::encryptString('secret123'),
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function mockSmsDelivery(string $phone): void
    {
        config(['services.sms.driver' => 'gsm']);
        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('send')
            ->once()
            ->with($phone, \Mockery::on(fn ($message) => str_contains($message, 'account')
                || str_contains($message, 'registration')))
            ->andReturn(['driver' => 'gsm', 'provider_id' => null]);
        $this->app->instance(SmsService::class, $smsService);
    }

    private function createPendingRegistrationTable(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('role', 20);
            $table->string('phone_number', 20)->unique();
            $table->longText('encrypted_password');
            $table->json('details');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['role', 'status']);
        });
    }
}
