<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsService
{
    public function send(string $to, string $message): array
    {
        $to = PhoneNumber::normalize($to);
        $driver = (string) config('services.sms.driver', 'log');

        if ($driver === 'log') {
            Log::info('SMS test completed in log mode.', [
                'to' => $to,
                'message' => $message,
            ]);

            return [
                'driver' => 'log',
                'provider_id' => null,
            ];
        }

        if ($driver === 'gsm') {
            return $this->sendWithGsmModule($to, $message);
        }

        if ($driver !== 'twilio') {
            throw new RuntimeException('Unsupported SMS driver: '.$driver);
        }

        $accountSid = (string) config('services.sms.twilio.account_sid');
        $authToken = (string) config('services.sms.twilio.auth_token');
        $from = (string) config('services.sms.twilio.from');

        if ($accountSid === '' || $authToken === '' || $from === '') {
            throw new RuntimeException('Twilio SMS settings are incomplete.');
        }

        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?: 'The SMS provider rejected the message.');
        }

        return [
            'driver' => 'twilio',
            'provider_id' => $response->json('sid'),
        ];
    }

    private function sendWithGsmModule(string $to, string $message): array
    {
        $powershell = (string) getenv('WINDIR').'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        if (! is_file($powershell)) {
            $powershell = 'powershell.exe';
        }

        $messageFile = tempnam(storage_path('app'), 'sms-');
        if ($messageFile === false || file_put_contents($messageFile, $message) === false) {
            throw new RuntimeException('Unable to prepare the SMS message for the GSM module.');
        }

        try {
            $escapePowerShell = static fn (string $value): string => str_replace("'", "''", $value);
            $powerShellScript = sprintf(
                "& '%s' '%s' '%s' '%s' -MessageFile '%s'",
                $escapePowerShell(base_path('scripts/send_sms.ps1')),
                $escapePowerShell((string) config('services.sms.gsm.port', 'COM10')),
                $escapePowerShell((string) config('services.sms.gsm.baud_rate', 19200)),
                $escapePowerShell($to),
                $escapePowerShell($messageFile),
            );
            $encodedCommand = base64_encode(mb_convert_encoding($powerShellScript, 'UTF-16LE', 'UTF-8'));
            $command = $powershell.' -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand '.$encodedCommand;
            $output = [];
            $exitCode = 0;
            exec('cmd.exe /d /s /c '.$command.' 2>&1', $output, $exitCode);
        } finally {
            @unlink($messageFile);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException('The GSM module failed to confirm SMS submission.');
        }

        return [
            'driver' => 'gsm',
            'provider_id' => null,
        ];
    }
}