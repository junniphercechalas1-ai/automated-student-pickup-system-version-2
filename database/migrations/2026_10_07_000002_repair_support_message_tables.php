<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table) {
                $table->id();
                $table->string('parent_user_id')->index();
                $table->string('parent_name')->nullable();
                $table->string('parent_email')->nullable();
                $table->string('subject', 120);
                $table->text('message');
                $table->string('status')->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_message_replies')) {
            Schema::create('support_message_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_message_id')->constrained()->cascadeOnDelete();
                $table->string('sender_type');
                $table->string('sender_id')->nullable();
                $table->text('body');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
    }
};
