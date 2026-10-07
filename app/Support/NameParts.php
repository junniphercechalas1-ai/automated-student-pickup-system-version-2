<?php

namespace App\Support;

final class NameParts
{
    public static function split(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = array_shift($parts) ?? '';
        $lastName = count($parts) > 0 ? array_pop($parts) : $firstName;

        return [
            'first_name' => $firstName,
            'middle_name' => $parts ? implode(' ', $parts) : null,
            'last_name' => $lastName,
        ];
    }

    public static function display(array $record): string
    {
        return trim(implode(' ', array_filter([
            $record['first_name'] ?? null,
            $record['middle_name'] ?? null,
            $record['last_name'] ?? null,
        ], static fn ($part) => is_string($part) && trim($part) !== '')));
    }
}
