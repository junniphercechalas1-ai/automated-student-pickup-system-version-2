<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * @property string $id
 * @property string|null $email
 * @property string|null $phone_number
 * @property string|null $auth_user_id
 * @property string $password_hash
 * @property string|null $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property bool $is_active
 * @property-read string $full_name
 */
class Admin extends Model
{
    protected $table = 'admins';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'phone_number',
        'auth_user_id',
        'password_hash',
        'first_name',
        'middle_name',
        'last_name',
        'is_active',
    ];

    protected $hidden = ['password_hash'];

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Hash password before saving (only if not already hashed)
     */
    public function setPasswordHashAttribute(string $value): void
    {
        // Check if value is already a bcrypt hash (starts with $2 and has proper length)
        if (preg_match('/^\$2[aby]\$/', $value)) {
            $this->attributes['password_hash'] = $value;
        } else {
            $this->attributes['password_hash'] = Hash::make($value);
        }
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        $hash = (string) $this->password_hash;

        if (! preg_match('/^\$2[aby]\$/', $hash)) {
            return false;
        }

        // Supabase pgcrypto may return $2a$; Laravel's bcrypt driver expects $2y$.
        if (str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
            $hash = '$2y$'.substr($hash, 4);
        }

        return Hash::check($password, $hash);
    }

    public function hasRole(string $role): bool
    {
        return $role === 'admin';
    }

}
