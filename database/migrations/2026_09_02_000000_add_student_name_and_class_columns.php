<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('grade_level')->nullable();
            $table->string('section')->nullable();
        });

        DB::statement(<<<'SQL'
            WITH parsed_students AS (
                SELECT
                    id,
                    regexp_split_to_array(trim(name), '\s+') AS name_parts
                FROM students
            )
            UPDATE students
            SET
                first_name = name_parts[1],
                middle_name = CASE
                    WHEN array_length(name_parts, 1) > 2
                    THEN array_to_string(name_parts[2:array_length(name_parts, 1) - 1], ' ')
                    ELSE NULL
                END,
                last_name = CASE
                    WHEN array_length(name_parts, 1) > 1
                    THEN name_parts[array_length(name_parts, 1)]
                    ELSE NULL
                END
            FROM parsed_students
            WHERE students.id = parsed_students.id
              AND students.first_name IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE students
            SET
                grade_level = NULLIF(trim(split_part(class, '-', 1)), ''),
                section = CASE
                    WHEN position('-' IN class) > 0
                    THEN NULLIF(trim(substring(class FROM position('-' IN class) + 1)), '')
                    ELSE NULL
                END
            WHERE grade_level IS NULL
        SQL);

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['name', 'class']);
            $table->dropColumn('qr_code');
        });

    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->string('qr_code')->nullable();
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'grade_level',
                'section',
            ]);
        });

    }
};