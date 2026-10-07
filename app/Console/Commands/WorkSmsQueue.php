<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use App\Services\SupabaseSmsQueue;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Throwable;

class WorkSmsQueue extends Command
{
    protected $signature = 'sms:work {--once : Process at most one queued SMS} {--sleep=5 : Seconds to wait when the queue is empty}';

    protected $description = 'Send Supabase-queued SMS messages through the local GSM modem';

    public function handle(SupabaseSmsQueue $queue, SmsService $sms): int
    {
        $sleep = filter_var($this->option('sleep'), FILTER_VALIDATE_INT);
        if ($sleep === false || $sleep < 1) {
            $this->error('The --sleep value must be a positive whole number of seconds.');

            return self::FAILURE;
        }

        do {
            $row = $queue->claimNext();
            if ($row === null) {
                if ($this->option('once')) {
                    $this->info('No pending SMS messages.');

                    return self::SUCCESS;
                }

                sleep($sleep);

                continue;
            }

            $id = $row['id'];
            $expiresAt = $row['expires_at'] ?? null;
            if ($expiresAt !== null && $expiresAt !== '') {
                try {
                    $expired = Carbon::parse((string) $expiresAt)->isPast();
                } catch (InvalidFormatException) {
                    $queue->complete($id, 'failed', error: 'The SMS queue row has an invalid expires_at timestamp.');
                    $this->warn("SMS queue row {$id} has an invalid expiration timestamp.");

                    if ($this->option('once')) {
                        return self::FAILURE;
                    }

                    continue;
                }

                if ($expired) {
                    $queue->complete($id, 'failed', error: 'SMS expired before it could be sent.');
                    $this->warn("SMS queue row {$id} expired before it could be sent.");

                    if ($this->option('once')) {
                        return self::FAILURE;
                    }

                    continue;
                }
            }

            try {
                $result = $sms->sendUsingGsm((string) $row['recipient_phone'], (string) $row['message']);
            } catch (Throwable $exception) {
                $queue->complete(
                    $id,
                    'failed',
                    error: mb_substr($exception->getMessage(), 0, 2000),
                );
                $this->warn("SMS queue row {$id} failed: ".$exception->getMessage());

                if ($this->option('once')) {
                    return self::FAILURE;
                }

                continue;
            }

            $queue->complete($id, 'sent', $result['provider_id']);
            $this->info("SMS queue row {$id} sent.");

            if ($this->option('once')) {
                return self::SUCCESS;
            }
        } while (true);
    }
}
