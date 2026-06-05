<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'test' as a valid value for the email_queue.type ENUM column (MySQL/MariaDB only).
     * SQLite stores the column as TEXT so no structural change is needed.
     */
    public function up(): void
    {
        // MySQL/MariaDB only — SQLite uses TEXT and accepts any value natively
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE email_queue MODIFY COLUMN type ENUM('campaign', 'single', 'test') NOT NULL DEFAULT 'campaign'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE email_queue MODIFY COLUMN type ENUM('campaign', 'single') NOT NULL DEFAULT 'campaign'");
        }
    }
};
