<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use DateTimeInterface;

class SupabaseSmsQueue
{
    public function enqueue(
        string $recipientPhone,
        string $message,
        ?string $notificationId = null,
        ?DateTimeInterface $expiresAt = null,
    ): array
    {
        $payload = [
            'recipient_phone' => $recipientPhone,
            'message' => $message,
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
        ];
        if ($notificationId !== null) {
            $payload['notifications_id'] = $notificationId;
        }
        if ($expiresAt !== null) {
            $payload['expires_at'] = $expiresAt->format(DATE_ATOM);
        }

        $response = $this->request()
            ->withHeaders(['Prefer' => 'return=representation'])
            ->post($this->tableUrl(), $payload);

        $this->throwOnFailure($response, 'Unable to enqueue the SMS.');
        $rows = $response->json();
        if (! is_array($rows) || ! isset($rows[0]['id'])) {
            throw new RuntimeException('Supabase did not return the queued SMS ID.');
        }

        return [
            'driver' => 'supabase_queue',
            'provider_id' => (string) $rows[0]['id'],
        ];
    }

    public function enqueueNotification(string $recipientPhone, string $message, string $notificationType): array
    {
        $response = $this->request()
            ->withHeaders(['Prefer' => 'return=representation'])
            ->post($this->notificationsUrl(), [
                'notification_type' => $notificationType,
                'recipient_phone' => $recipientPhone,
                'message' => $message,
            ]);

        $this->throwOnFailure($response, 'Unable to record the SMS notification.');
        $rows = $response->json();
        if (! is_array($rows) || ! isset($rows[0]['id'])) {
            throw new RuntimeException('Supabase did not return the notification ID.');
        }

        $notificationId = (string) $rows[0]['id'];
        $queued = $this->enqueue($recipientPhone, $message, $notificationId);

        return [
            ...$queued,
            'notification_id' => $notificationId,
        ];
    }

    /**
     * Atomically claims the oldest pending row.
     *
     * @return array<string, mixed>|null
     */
    public function claimNext(): ?array
    {
        $pending = $this->request()->get($this->tableUrl(), [
            'select' => 'id,recipient_phone,message,expires_at,notifications_id',
            'status' => 'eq.pending',
            'order' => 'created_at.asc',
            'limit' => 20,
        ]);

        $this->throwOnFailure($pending, 'Unable to read the SMS queue.');
        $rows = $pending->json();
        if (! is_array($rows)) {
            throw new RuntimeException('Supabase returned an invalid SMS queue response.');
        }

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['recipient_phone'], $row['message'])) {
                throw new RuntimeException('Supabase returned an incomplete SMS queue row.');
            }

            $claim = $this->request()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->patch($this->tableUrl().'?'.http_build_query([
                    'id' => 'eq.'.$row['id'],
                    'status' => 'eq.pending',
                ], '', '&', PHP_QUERY_RFC3986), [
                    'status' => 'processing',
                    'claimed_at' => now()->toIso8601String(),
                ]);

            $this->throwOnFailure($claim, 'Unable to claim an SMS queue row.');
            $claimedRows = $claim->json();
            if (is_array($claimedRows) && isset($claimedRows[0]) && is_array($claimedRows[0])) {
                return $claimedRows[0];
            }
        }

        return null;
    }

    public function complete(string|int $id, string $status, ?string $providerId = null, ?string $error = null): void
    {
        if (! in_array($status, ['sent', 'failed'], true)) {
            throw new RuntimeException('Invalid SMS queue completion status.');
        }

        $response = $this->request()
            ->withHeaders(['Prefer' => 'return=representation'])
            ->patch($this->tableUrl().'?'.http_build_query([
                'id' => 'eq.'.$id,
                'status' => 'eq.processing',
            ], '', '&', PHP_QUERY_RFC3986), [
                'status' => $status,
                'provider_id' => $providerId,
                'error_message' => $error,
                'completed_at' => now()->toIso8601String(),
            ]);

        $this->throwOnFailure($response, 'Unable to update the SMS queue result.');
        $rows = $response->json();
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('The SMS queue row was no longer processing when its result was saved.');
        }
    }

    public function markNotificationSent(?string $notificationId): void
    {
        if ($notificationId === null) {
            return;
        }

        $response = $this->request()
            ->withHeaders(['Prefer' => 'return=representation'])
            ->patch($this->notificationsUrl().'?'.http_build_query([
                'id' => 'eq.'.$notificationId,
            ], '', '&', PHP_QUERY_RFC3986), [
                'sent_at' => now()->toIso8601String(),
            ]);

        $this->throwOnFailure($response, 'Unable to mark the notification as sent.');
        $rows = $response->json();
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('The notification record could not be updated.');
        }
    }

    private function request(): PendingRequest
    {
        $url = (string) config('services.sms.queue.url');
        $key = (string) config('services.sms.queue.service_key');

        if ($url === '' || $key === '') {
            throw new RuntimeException('Supabase SMS queue settings are incomplete. Configure SUPABASE_URL and SUPABASE_SERVICE_KEY.');
        }

        return Http::acceptJson()
            ->withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer '.$key,
            ])
            ->timeout(15);
    }

    private function tableUrl(): string
    {
        return rtrim((string) config('services.sms.queue.url'), '/').'/rest/v1/sms_queue';
    }

    private function notificationsUrl(): string
    {
        return rtrim((string) config('services.sms.queue.url'), '/').'/rest/v1/notifications';
    }

    private function throwOnFailure(Response $response, string $context): void
    {
        if ($response->failed()) {
            $message = $response->json('message') ?: $response->body();
            throw new RuntimeException($context.' '.$message);
        }
    }
}
