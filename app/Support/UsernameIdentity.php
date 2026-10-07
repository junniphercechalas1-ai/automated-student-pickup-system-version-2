<?php

namespace App\Support;

final class UsernameIdentity
{
    private const EMAIL_DOMAIN = 'accounts.orion.invalid';

    public static function normalize(string $username): string
    {
        return strtolower(trim($username));
    }

    public static function isValid(string $username): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9._-]{2,29}$/', $username) === 1;
    }

    public static function authEmail(string $username): string
    {
        return self::normalize($username).'@'.self::EMAIL_DOMAIN;
    }
}
