<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(?string $phoneNumber): string
    {
        $value = trim((string) $phoneNumber);
        if ($value === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return '+63'.substr($digits, 1);
        }

        if (str_starts_with($digits, '639') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '+63'.$digits;
        }

        return str_starts_with($value, '+') ? '+'.$digits : $value;
    }
}