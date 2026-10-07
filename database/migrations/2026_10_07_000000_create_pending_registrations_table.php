<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
