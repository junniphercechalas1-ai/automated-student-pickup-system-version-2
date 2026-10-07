<?php

namespace App\Console\Commands;

use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConfirmLegacyPhoneAccounts extends Command
{
    protected $signature = 'supabase:confirm-legacy-phones {--apply : Mark existing parent and staff phone numbers as confirmed}';

    protected $description = 'Confirm phone numbers on existing parent and staff accounts that predate signup OTP verification';

    public function handle(): int
    {
        $supabaseUrl = rtrim((string) env('VITE_SUPABASE_URL'), '/');
        $serviceKey = (string) (env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY'));
        if ($supabaseUrl === '' || $serviceKey === '') {
            $this->error('Supabase URL and service-role key must be configured.');

            return self::FAILURE;
        }

        try {
            $accounts = $this->legacyAccounts($supabaseUrl, $serviceKey);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(count($accounts).' existing account phone number(s) qualify for confirmation.');
        if (! $this->option('apply')) {
            $this->comment('Dry run only. Re-run with --apply to mark these Supabase Auth phone numbers confirmed.');

            return self::SUCCESS;
        }

        foreach ($accounts as $userId => $phone) {
            $response = Http::withHeaders($this->headers($serviceKey))
                ->put("{$supabaseUrl}/auth/v1/admin/users/{$userId}", [
                    'phone' => $phone,
                    'phone_confirm' => true,
                ]);

            if ($response->failed()) {
                $this->error("Failed to confirm a legacy account ({$userId}): ".($response->json('message') ?? $response->body()));

                return self::FAILURE;
            }
        }

        $this->info('Confirmed '.count($accounts).' legacy phone number(s).');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function legacyAccounts(string $supabaseUrl, string $serviceKey): array
    {
        $accounts = [];

        foreach ([
            ['parents', 'auth_user_id', ['phone_number', 'mobile_number']],
            ['staff', 'id', ['phone_number']],
        ] as [$table, $userIdField, $phoneFields]) {
            $rows = null;
            $phoneField = null;
            foreach ($phoneFields as $candidateField) {
                try {
                    $rows = $this->profileRows($supabaseUrl, $serviceKey, $table, $userIdField, $candidateField);
                    $phoneField = $candidateField;
                    break;
                } catch (RuntimeException $exception) {
                    if ($candidateField !== end($phoneFields) && str_contains(strtolower($exception->getMessage()), 'column')) {
                        continue;
                    }

                    throw $exception;
                }
            }

            if (! is_array($rows) || $phoneField === null) {
                throw new RuntimeException("Unable to load legacy {$table} phone numbers.");
            }

            foreach ($rows as $row) {
                if (! is_array($row) || empty($row[$userIdField]) || empty($row[$phoneField])) {
                    continue;
                }

                $phone = PhoneNumber::normalize((string) $row[$phoneField]);
                if (preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
                    $accounts[(string) $row[$userIdField]] = $phone;
                }
            }
        }

        return $accounts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function profileRows(string $supabaseUrl, string $serviceKey, string $table, string $userIdField, string $phoneField): array
    {
        $rows = [];
        $offset = 0;
        do {
            $response = Http::withHeaders([
                ...$this->headers($serviceKey),
                'Range-Unit' => 'items',
                'Range' => "{$offset}-".($offset + 999),
            ])->get("{$supabaseUrl}/rest/v1/{$table}", [
                'select' => "{$userIdField},{$phoneField}",
            ]);

            if ($response->failed()) {
                throw new RuntimeException("Unable to load legacy {$table} accounts: ".($response->json('message') ?? $response->body()));
            }

            $page = $response->json();
            if (! is_array($page)) {
                throw new RuntimeException("Unexpected response while loading legacy {$table} accounts.");
            }

            $rows = [...$rows, ...$page];
            $count = count($page);
            $offset += $count;
        } while ($count === 1000);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $serviceKey): array
    {
        return [
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ];
    }
}
