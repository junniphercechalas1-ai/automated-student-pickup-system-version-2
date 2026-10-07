<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Support\SupportMessageSchema;

return new class extends Migration
{
    public function up(): void
    {
        SupportMessageSchema::ensure();
    }

    public function down(): void
    {
        Schema::dropIfExists('support_message_replies');
    }
};