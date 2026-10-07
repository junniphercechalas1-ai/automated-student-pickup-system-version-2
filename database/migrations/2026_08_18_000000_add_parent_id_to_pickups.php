<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add parent_id column to pickups table to link scan records to parent/guardians.
     */
    public function up()
    {
        // This migration documents the schema change needed in Supabase.
        // Since Supabase uses direct SQL API, run this manually in Supabase SQL editor:
        //
        // ALTER TABLE public.pickups ADD COLUMN parent_id bigint REFERENCES public.parents(id) ON DELETE SET NULL;
        //
        // Or via Supabase REST API with service role key:
        // POST to /rest/v1/rpc/raw_sql with the ALTER TABLE statement
        //
        // For local testing, uncomment below if using a local PostgreSQL database:
        // Schema::table('pickups', function (Blueprint $table) {
        //     $table->bigInteger('parent_id')->nullable()->constrained('parents')->onDelete('setNull');
        // });
    }

    /**
     * Reverse the migration.
     */
    public function down()
    {
        // Schema::table('pickups', function (Blueprint $table) {
        //     $table->dropConstrainedForeignId('parent_id');
        // });
    }
};
