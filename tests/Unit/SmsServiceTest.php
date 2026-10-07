<?php

namespace Tests\Unit;

use App\Services\SmsService;
use App\Services\SupabaseSmsQueue;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_log_driver_completes_without_calling_a_provider(): void
    {
        config(['services.sms.driver' => 'log']);

        $result = app(SmsService::class)->send('+639171234567', 'Portal test');

        $this->assertSame('log', $result['driver']);
        Http::assertNothingSent();
    }

    public function test_twilio_driver_sends_the_message(): void
    {
        config([
            'services.sms.driver' => 'twilio',
            'services.sms.twilio.account_sid' => 'AC123',
            'services.sms.twilio.auth_token' => 'secret',
            'services.sms.twilio.from' => '+15005550006',
        ]);
        Http::fake([
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $result = app(SmsService::class)->send('+639171234567', 'Portal test');

        $this->assertSame('twilio', $result['driver']);
        $this->assertSame('SM123', $result['provider_id']);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
                && $request->data()['To'] === '+639171234567'
                && $request->data()['From'] === '+15005550006'
                && $request->data()['Body'] === 'Portal test';
        });
    }

    public function test_supabase_queue_driver_enqueues_the_message(): void
    {
        config([
            'services.sms.driver' => 'supabase_queue',
            'services.sms.queue.url' => 'https://example.supabase.co',
            'services.sms.queue.service_key' => 'test-service-key',
        ]);
        Http::fake([
            'https://example.supabase.co/rest/v1/sms_queue' => Http::response([['id' => 'queue-123']], 201),
        ]);

        $result = app(SmsService::class)->send('09171234567', 'Pickup ready');

        $this->assertSame('supabase_queue', $result['driver']);
        $this->assertSame('queue-123', $result['provider_id']);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://example.supabase.co/rest/v1/sms_queue'
                && $request->method() === 'POST'
                && $request->hasHeader('apikey', 'test-service-key')
                && $request['recipient_phone'] === '+639171234567'
                && $request['message'] === 'Pickup ready'
                && $request['status'] === 'pending';
        });
    }

    public function test_supabase_queue_atomically_claims_and_completes_a_message(): void
    {
        config([
            'services.sms.queue.url' => 'https://example.supabase.co',
            'services.sms.queue.service_key' => 'test-service-key',
        ]);
        Http::fake([
            'https://example.supabase.co/rest/v1/sms_queue*' => Http::sequence()
                ->push([[
                    'id' => 'queue-123',
                    'recipient_phone' => '+639171234567',
                    'message' => 'Pickup ready',
                    'expires_at' => null,
                ]], 200)
                ->push([[
                    'id' => 'queue-123',
                    'recipient_phone' => '+639171234567',
                    'message' => 'Pickup ready',
                    'expires_at' => null,
                ]], 200)
                ->push([['id' => 'queue-123']], 200),
        ]);

        $queue = app(SupabaseSmsQueue::class);
        $claimed = $queue->claimNext();
        $queue->complete('queue-123', 'sent');

        $this->assertSame('queue-123', $claimed['id']);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), 'status=eq.pending')
                && $request['status'] === 'processing'
                && isset($request['claimed_at']);
        });
        Http::assertSent(function ($request): bool {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), 'status=eq.processing')
                && $request['status'] === 'sent'
                && isset($request['completed_at']);
        });
    }
}