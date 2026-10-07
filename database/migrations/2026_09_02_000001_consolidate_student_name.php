<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'full_name')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('full_name')->nullable();
            });
        }

        if (Schema::hasColumn('students', 'first_name')) {
            DB::statement("UPDATE students SET full_name = trim(first_name || ' ' || coalesce(middle_name || ' ', '') || last_name) WHERE full_name IS NULL");
        }

        foreach (['first_name', 'middle_name', 'last_name'] as $column) {
            if (Schema::hasColumn('students', $column)) {
                Schema::table('students', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
        });

        DB::statement("UPDATE students SET first_name = full_name WHERE first_name IS NULL");

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};