<?php

namespace Tests\Unit;

use App\Services\SmsService;
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
}