<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
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
    public function test_parent_list_uses_auth_username_when_profile_has_no_email_column(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'id' => 41,
                    'auth_users_id' => 'parent-auth-id',
                    'first_name' => 'Echo',
                    'middle_name' => null,
                    'last_name' => 'Holland',
                    'student_id' => 17,
                    'phone_number' => '+639170000041',
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
                    'user_metadata' => ['username' => 'echo-holland'],
                ]]], 200);
            }

            return Http::response([], 200);
        });

        $this->withSession([
            'supabase_user' => [
                'id' => 'admin-auth-id',
                'user_metadata' => ['role' => 'admin'],
            ],
        ])->withSession(['admin_id' => 'admin-1'])
            ->getJson('/admin/parents?format=json')
            ->assertOk()
            ->assertJsonPath('parents.0.username', 'echo-holland');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/v1/admin/users')
            && $request->method() === 'GET');
    }

    public function test_staff_list_uses_auth_username_without_a_profile_email_column(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([[
                    'id' => 'staff-auth-id',
                    'auth_users_id' => 'staff-auth-id',
                    'first_name' => 'Alex',
                    'last_name' => 'Staff',
                    'is_approved' => true,
                    'is_active' => true,
                ]], 200);
            }

            if (str_contains($request->url(), '/auth/v1/admin/users')) {
                return Http::response(['users' => [[
                    'id' => 'staff-auth-id',
                    'email' => 'alex@accounts.orion.invalid',
                    'user_metadata' => ['username' => 'alex-staff'],
                ]]], 200);
            }

            return Http::response([], 200);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->getJson('/admin/staff?format=json')
            ->assertOk()
            ->assertJsonPath('0.username', 'alex-staff')
            ->assertJsonMissingPath('0.email');
    }

    public function test_unapproved_staff_profile_is_listed_for_admin_review(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff')) {
                return Http::response([[
                    'id' => 'pending-staff-auth-id',
                    'auth_users_id' => 'pending-staff-auth-id',
                    'first_name' => 'Taylor',
                    'last_name' => 'Staff',
                    'phone_number' => '+639171234569',
                    'is_approved' => false,
                    'is_active' => true,
                ]], 200);
            }
            if (str_contains($request->url(), '/auth/v1/admin/users')) {
                return Http::response(['users' => [[
                    'id' => 'pending-staff-auth-id',
                    'user_metadata' => ['username' => 'taylorstaff'],
                ]]], 200);
            }

            return Http::response([], 200);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->getJson('/admin/staff?format=json')
            ->assertOk()
            ->assertJsonPath('0.id', 'pending-staff-auth-id')
            ->assertJsonPath('0.full_name', 'Taylor Staff')
            ->assertJsonPath('0.is_approved', false)
            ->assertJsonPath('0.is_pending_registration', true);
    }

    public function test_unapproved_parent_profile_is_included_in_admin_parent_list(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents')) {
                return Http::response([[
                    'id' => 41,
                    'auth_users_id' => 'pending-parent-auth-id',
                    'first_name' => 'Jordan',
                    'last_name' => 'Parent',
                    'phone_number' => '+639170000041',
                    'relationship' => 'Guardian',
                    'is_approved' => false,
                    'is_active' => true,
                ]], 200);
            }
            if (str_contains($request->url(), '/rest/v1/students')
                || str_contains($request->url(), '/rest/v1/parents_students')) {
                return Http::response([], 200);
            }
            if (str_contains($request->url(), '/auth/v1/admin/users')) {
                return Http::response(['users' => [[
                    'id' => 'pending-parent-auth-id',
                    'user_metadata' => ['username' => 'jordanparent'],
                ]]], 200);
            }

            return Http::response([], 200);
        });

        $this->withSession(['admin_id' => 'admin-1'])
            ->getJson('/admin/parents?format=json')
            ->assertOk()
            ->assertJsonPath('parents.0.id', 41)
            ->assertJsonPath('parents.0.full_name', 'Jordan Parent')
            ->assertJsonPath('parents.0.is_approved', false)
            ->assertJsonPath('parents.0.is_pending_registration', true)
            ->assertJsonPath('counts.pending_parents', 1);
    }

    public function test_declining_an_existing_parent_deletes_auth_user_and_profile(): void
    {
        $this->mockSmsDelivery('+639171234572');
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 42,
                    'auth_users_id' => 'parent-auth-id',
                    'phone_number' => '+639171234572',
                ]], 200);
            }

            if ($request->method() === 'DELETE'
                && (str_contains($request->url(), '/rest/v1/parents?id=eq.42')
                    || str_contains($request->url(), '/rest/v1/staff?id=eq.staff-auth-id'))) {
                return Http::response([['id' => str_contains($request->url(), '/rest/v1/parents') ? 42 : 'staff-auth-id']], 200);
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
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 'staff-auth-id',
                    'auth_users_id' => 'staff-auth-id',
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
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/parents') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 43,
                    'auth_users_id' => 'parent-auth-id',
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

        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/parents?id=eq.43')
            && $request->method() === 'PATCH'
            && $request->data()['is_approved'] === true);
    }

    public function test_approving_an_existing_staff_profile_sends_sms_to_its_registered_phone(): void
    {
        $this->mockSmsDelivery('+639171234575');
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/v1/staff') && $request->method() === 'GET') {
                return Http::response([[
                    'id' => 'staff-auth-id',
                    'auth_users_id' => 'staff-auth-id',
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

        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/staff?id=eq.staff-auth-id')
            && $request->method() === 'PATCH'
            && $request->data()['is_approved'] === true);
    }



    private function mockSmsDelivery(string $phone): void
    {
        config(['services.sms.driver' => 'gsm']);
        $smsService = \Mockery::mock(SmsService::class);
        $smsService->shouldReceive('sendNotification')
            ->once()
            ->with($phone, \Mockery::on(fn ($message) => str_contains($message, 'account')
                || str_contains($message, 'registration')), 'sms_account_decision')
            ->andReturn(['driver' => 'gsm', 'provider_id' => null]);
        $this->instance(SmsService::class, $smsService);
    }


}
