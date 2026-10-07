<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PhoneNumberRegistrationGuard
{
    public function isRegistered(string $phoneNumber, string $supabaseUrl, string $serviceKey): bool
    {
        PendingRegistrationSchema::ensure();

        $normalizedPhone = PhoneNumber::normalize($phoneNumber);
        foreach (DB::table('pending_registrations')->pluck('phone_number') as $pendingPhone) {
            if (PhoneNumber::normalize((string) $pendingPhone) === $normalizedPhone) {
                return true;
            }
        }

        $baseUrl = rtrim($supabaseUrl, '/');
        $headers = [
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
        ];

        $page = 1;
        do {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get($baseUrl.'/auth/v1/admin/users', [
                    'page' => $page,
                    'per_page' => 100,
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Supabase could not check existing accounts.');
            }

            $users = $response->json('users');
            if ($users === null && is_array($response->json())) {
                $users = $response->json();
            }
            if (! is_array($users)) {
                throw new RuntimeException('Supabase returned an invalid account list.');
            }

            foreach ($users as $user) {
                if (is_array($user)
                    && PhoneNumber::normalize((string) ($user['phone'] ?? '')) === $normalizedPhone) {
                    return true;
                }
            }

            $page++;
        } while (count($users) === 100);

        $phoneCandidates = array_unique([
            $normalizedPhone,
            substr($normalizedPhone, 1),
            str_starts_with($normalizedPhone, '+63') ? '0'.substr($normalizedPhone, 3) : $normalizedPhone,
        ]);

        foreach ([
            'parents' => ['phone_number', 'mobile_number'],
            'staff' => ['phone_number'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                foreach ($phoneCandidates as $candidate) {
                    $response = Http::withHeaders($headers)
                        ->timeout(10)
                        ->get($baseUrl.'/rest/v1/'.$table, [
                            'select' => $column,
                            $column => 'eq.'.$candidate,
                            'limit' => 1,
                        ]);

                    if ($response->failed()) {
                        $message = strtolower((string) ($response->json('message') ?? ''));
                        if ($column === 'mobile_number'
                            && $response->status() === 400
                            && str_contains($message, 'mobile_number')
                            && (str_contains($message, 'column') || str_contains($message, 'schema cache'))) {
                            continue;
                        }

                        throw new RuntimeException('Supabase could not check existing account profiles.');
                    }

                    $profiles = $response->json();
                    if (! is_array($profiles)) {
                        throw new RuntimeException('Supabase returned an invalid account profile list.');
                    }

                    foreach ($profiles as $profile) {
                        if (is_array($profile)
                            && PhoneNumber::normalize((string) ($profile[$column] ?? '')) === $normalizedPhone) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
