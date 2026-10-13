<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPickupRecordsRouteTest extends TestCase
{
    public function test_pickup_records_use_the_renamed_url_and_old_url_redirects(): void
    {
        $this->assertSame(url('/admin/pickup-records'), route('admin.entry-records'));

        $this->withSession(['admin_id' => 'admin-1'])
            ->get('/admin/entry-records')
            ->assertRedirect(route('admin.entry-records'));
    }
}
