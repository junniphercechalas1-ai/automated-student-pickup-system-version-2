<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['first_name', 'middle_name', 'last_name', 'grade_level', 'section'] as $column) {
            if (! Schema::hasColumn('students', $column)) {
                Schema::table('students', function (Blueprint $table) use ($column) {
                    $table->string($column)->nullable();
                });
            }
        }

        $students = DB::table('students')->get(['id', 'name', 'class', 'first_name', 'middle_name', 'last_name', 'grade_level', 'section']);

        foreach ($students as $student) {
            $nameParts = preg_split('/\s+/', trim((string) ($student->name ?? '')));
            $parts = array_values(array_filter($nameParts, fn ($part) => $part !== ''));

            $updates = [];

            if (empty($student->first_name) && ! empty($parts)) {
                $updates['first_name'] = $parts[0];
            }

            if (empty($student->middle_name) && count($parts) > 2) {
                $updates['middle_name'] = implode(' ', array_slice($parts, 1, -1));
            }

            if (empty($student->last_name) && count($parts) > 1) {
                $updates['last_name'] = $parts[count($parts) - 1];
            }

            $classValue = trim((string) ($student->class ?? ''));

            if (empty($student->grade_level) && $classValue !== '') {
                $classParts = explode('-', $classValue, 2);
                $gradeLevel = trim($classParts[0]);
                $updates['grade_level'] = $gradeLevel !== '' ? $gradeLevel : null;
            }

            if (empty($student->section) && $classValue !== '') {
                $classParts = explode('-', $classValue, 2);
                $section = isset($classParts[1]) ? trim($classParts[1]) : null;
                $updates['section'] = $section !== '' ? $section : null;
            }

            if (! empty($updates)) {
                DB::table('students')->where('id', $student->id)->update($updates);
            }
        }

        if (Schema::hasColumn('students', 'name')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn(['name', 'class']);
            });
        }

        if (Schema::hasColumn('students', 'qr_code')) {
            DB::statement('DROP INDEX IF EXISTS students_qr_code_unique');

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('qr_code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('students', 'name')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('name')->nullable();
            });
        }

        if (! Schema::hasColumn('students', 'class')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('class')->nullable();
            });
        }

        if (! Schema::hasColumn('students', 'qr_code')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('qr_code')->nullable();
            });
        }

        $students = DB::table('students')->whereNotNull('first_name')->orWhereNotNull('middle_name')->orWhereNotNull('last_name')->get(['id', 'first_name', 'middle_name', 'last_name']);

        foreach ($students as $student) {
            $fullName = trim(implode(' ', array_filter([
                $student->first_name,
                $student->middle_name,
                $student->last_name,
            ], fn ($value) => $value !== null && $value !== '')));

            if ($fullName !== '') {
                DB::table('students')->where('id', $student->id)->update([
                    'name' => $fullName,
                ]);
            }
        }

        foreach (['first_name', 'middle_name', 'last_name', 'grade_level', 'section'] as $column) {
            if (Schema::hasColumn('students', $column)) {
                Schema::table('students', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};