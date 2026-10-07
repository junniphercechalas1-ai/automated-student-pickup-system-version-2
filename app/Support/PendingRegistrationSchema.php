<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class PendingRegistrationSchema
{
    public static function ensure(): void
    {
        if (Schema::hasTable('pending_registrations')) {
            return;
        }

        Schema::create('pending_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('role', 20);
            $table->string('phone_number', 20)->unique();
            $table->longText('encrypted_password');
            $table->json('details');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['role', 'status']);
        });
    }
}
