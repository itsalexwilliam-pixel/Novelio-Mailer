<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'test' as a valid value for the email_queue.type ENUM column.
     */
    public function up(): void
    {
        // MySQL/MariaDB: alter the enum to include 'test'
        DB::statement("ALTER TABLE email_queue MODIFY COLUMN type ENUM('campaign', 'single', 'test') NOT NULL DEFAULT 'campaign'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original two-value enum (rows with type='test' will cause an error if any exist)
        DB::statement("ALTER TABLE email_queue MODIFY COLUMN type ENUM('campaign', 'single') NOT NULL DEFAULT 'campaign'");
    }
};
