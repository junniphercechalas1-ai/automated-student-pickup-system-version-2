<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ParentSupportMessageRemovalTest extends TestCase
{
    public function test_parent_and_admin_support_message_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('admin.support-messages'));
        $this->assertFalse(Route::has('admin.support-messages.reply'));
        $this->assertFalse(Route::has('admin.support-messages.status'));
        $this->assertFalse(Route::has('admin.support-messages.delete'));
        $this->assertFalse(Route::has('parent.contact-admin'));
        $this->assertFalse(Route::has('parent.contact-admin.store'));
        $this->assertFalse(Route::has('parent.contact-admin.reply'));

        $this->get('/admin/support-messages')->assertNotFound();
        $this->get('/parent/contact-admin')->assertNotFound();
    }
}
