<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParentSupportMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_message_replies');
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_10_06_000000_create_support_messages_table.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_10_06_000001_create_support_message_replies_table.php',
            '--force' => true,
        ]);

        putenv('VITE_SUPABASE_URL=https://example.supabase.co');
        putenv('SUPABASE_SERVICE_KEY=test-service-key');
        $_ENV['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_ENV['SUPABASE_SERVICE_KEY'] = 'test-service-key';
        $_SERVER['VITE_SUPABASE_URL'] = 'https://example.supabase.co';
        $_SERVER['SUPABASE_SERVICE_KEY'] = 'test-service-key';
    }

    public function test_parent_can_submit_an_account_message_and_view_only_their_own_messages(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([], 200),
            'https://example.supabase.co/rest/v1/parents*' => Http::response([
                ['id' => 7, 'auth_user_id' => 'parent-auth-1', 'email' => 'parent@example.com', 'is_approved' => true, 'is_active' => true],
            ], 200),
        ]);

        SupportMessage::create([
            'parent_user_id' => 'different-parent',
            'subject' => 'Private message',
            'message' => 'This belongs to someone else.',
        ]);

        $this->withSession([
            'supabase_user' => [
                'id' => 'parent-auth-1',
                'email' => 'parent@example.com',
                'user_metadata' => ['role' => 'parent', 'full_name' => 'Pat Parent'],
            ],
        ])->from('/parent/contact-admin')->post('/parent/contact-admin', [
            'subject' => 'Unable to access my profile',
            'message' => 'The phone number update keeps failing.',
            'parent_user_id' => 'different-parent',
        ])->assertRedirect(route('parent.contact-admin'))
            ->assertSessionHas('status', 'Your message has been sent to the admin.');

        $this->assertDatabaseHas('support_messages', [
            'parent_user_id' => 'parent-auth-1',
            'parent_name' => 'Pat Parent',
            'parent_email' => 'parent@example.com',
            'subject' => 'Unable to access my profile',
        ]);

        $this->withSession([
            'supabase_user' => [
                'id' => 'parent-auth-1',
                'email' => 'parent@example.com',
                'user_metadata' => ['role' => 'parent'],
            ],
        ])->get('/parent/contact-admin')
            ->assertOk()
            ->assertSee('Unable to access my profile')
            ->assertDontSee('Private message');
    }

    public function test_admin_can_review_and_update_a_parent_message(): void
    {
        $message = SupportMessage::create([
            'parent_user_id' => 'parent-auth-1',
            'parent_name' => 'Pat Parent',
            'parent_email' => 'parent@example.com',
            'subject' => 'Unable to sign in',
            'message' => 'Password reset does not arrive.',
        ]);

        $this->withSession(['admin_id' => 'admin-1'])
            ->get('/admin/support-messages')
            ->assertOk()
            ->assertSee('Unable to sign in')
            ->assertSee('parent@example.com');

        $this->withSession(['admin_id' => 'admin-1'])
            ->patch(route('admin.support-messages.status', $message), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_messages', [
            'id' => $message->id,
            'status' => 'resolved',
        ]);
    }

    public function test_admin_can_delete_a_parent_message_and_its_replies(): void
    {
        $message = SupportMessage::create([
            'parent_user_id' => 'parent-auth-1',
            'parent_name' => 'Pat Parent',
            'subject' => 'Unable to sign in',
            'message' => 'Password reset does not arrive.',
        ]);
        $reply = $message->replies()->create([
            'sender_type' => 'admin',
            'sender_id' => 'admin-1',
            'body' => 'We are checking this issue.',
        ]);

        $this->withSession(['admin_id' => 'admin-1'])
            ->delete(route('admin.support-messages.delete', $message))
            ->assertRedirect()
            ->assertSessionHas('status', 'Support message deleted.');

        $this->assertDatabaseMissing('support_messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('support_message_replies', ['id' => $reply->id]);
    }

    public function test_admin_can_reply_and_parent_can_read_and_reply_back(): void
    {
        Http::fake([
            'https://example.supabase.co/rest/v1/staff*' => Http::response([], 200),
            'https://example.supabase.co/rest/v1/parents*' => Http::response([
                ['id' => 7, 'auth_user_id' => 'parent-auth-1', 'email' => 'parent@example.com', 'is_approved' => true, 'is_active' => true],
            ], 200),
        ]);

        $message = SupportMessage::create([
            'parent_user_id' => 'parent-auth-1',
            'parent_name' => 'Pat Parent',
            'parent_email' => 'parent@example.com',
            'subject' => 'Unable to sign in',
            'message' => 'Password reset does not arrive.',
        ]);

        $this->withSession(['admin_id' => 'admin-1'])
            ->post(route('admin.support-messages.reply', $message), ['reply' => 'We have sent a new reset link.'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_message_replies', [
            'support_message_id' => $message->id,
            'sender_type' => 'admin',
            'body' => 'We have sent a new reset link.',
        ]);

        $parentSession = [
            'supabase_user' => [
                'id' => 'parent-auth-1',
                'email' => 'parent@example.com',
                'user_metadata' => ['role' => 'parent'],
            ],
        ];

        $this->withSession($parentSession)
            ->get('/parent/contact-admin')
            ->assertOk()
            ->assertSee('We have sent a new reset link.');

        $this->withSession($parentSession)
            ->post(route('parent.contact-admin.reply', $message), ['reply' => 'Thank you, it worked.'])
            ->assertRedirect(route('parent.contact-admin'));

        $this->assertDatabaseHas('support_message_replies', [
            'support_message_id' => $message->id,
            'sender_type' => 'parent',
            'body' => 'Thank you, it worked.',
        ]);
    }
}