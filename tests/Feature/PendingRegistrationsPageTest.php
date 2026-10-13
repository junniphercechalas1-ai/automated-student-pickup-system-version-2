<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PendingRegistrationsPageTest extends TestCase
{
    public function test_admin_can_open_pending_registrations_page_from_sidebar(): void
    {
        $this->withSession(['admin_id' => 'admin-1'])
            ->get('/admin/pending-registrations')
            ->assertOk()
            ->assertSee('Pending Registrations')
            ->assertSee(route('admin.pending-registrations'))
            ->assertSee('Pending Registrations');
    }

    public function test_pending_registrations_page_requires_admin_login(): void
    {
        $this->get('/admin/pending-registrations')
            ->assertRedirect(route('admin.login'));
    }

    public function test_pending_registrations_route_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.pending-registrations'));
    }

    public function test_records_tabs_exclude_pending_registrations(): void
    {
        $session = ['admin_id' => 'admin-1'];

        $this->withSession($session)
            ->get('/admin/students')
            ->assertOk()
            ->assertSee('aria-label="User and Student Records"', false)
            ->assertSee(route('admin.students'))
            ->assertSee(route('admin.parents'))
            ->assertSee(route('admin.staff'))
            ->assertDontSee('>Pending Registrations</a>', false);
    }

    public function test_pending_registrations_is_a_separate_sidebar_feature_without_record_tabs(): void
    {
        $this->withSession(['admin_id' => 'admin-1'])
            ->get('/admin/pending-registrations')
            ->assertOk()
            ->assertSee('Pending Registrations')
            ->assertDontSee('aria-label="User and Student Records"', false)
            ->assertSee(route('admin.pending-registrations'));
    }

    public function test_parent_and_staff_records_keep_edit_and_delete_without_approval_actions(): void
    {
        $this->withSession(['admin_id' => 'admin-1'])
            ->get('/admin/parents')
            ->assertOk()
            ->assertSee('editParent')
            ->assertSee('deleteParent')
            ->assertDontSee('updateParentApproval')
            ->assertDontSee('>Approve</button>', false)
            ->assertDontSee('>Decline</button>', false);

        $this->withSession(['admin_id' => 'admin-1'])
            ->get('/admin/staff')
            ->assertOk()
            ->assertSee('editStaff')
            ->assertSee('deleteStaff')
            ->assertDontSee('updateStaffApproval')
            ->assertDontSee('>Approve</button>', false)
            ->assertDontSee('>Decline</button>', false);
    }

    public function test_approval_actions_remain_on_pending_registrations_page(): void
    {
        $this->withSession(['admin_id' => 'admin-1'])
            ->get('/admin/pending-registrations')
            ->assertOk()
            ->assertSee("createDecisionButton('Approve'", false)
            ->assertSee("createDecisionButton('Decline'", false);
    }
}
