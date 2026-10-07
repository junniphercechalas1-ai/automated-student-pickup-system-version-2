<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SupportMessageSchema;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        SupportMessageSchema::ensure();
    }

    public function down(): void
    {
    }
};
