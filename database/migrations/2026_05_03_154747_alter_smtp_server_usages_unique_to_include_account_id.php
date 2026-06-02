<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the new index FIRST so MySQL has an alternative index covering
        // smtp_server_id before we drop the old one (MySQL requires a leading
        // index on the FK column and will refuse to drop the only one).
        Schema::table('smtp_server_usages', function (Blueprint $table) {
            $table->unique(
                ['smtp_server_id', 'account_id', 'usage_date'],
                'smtp_server_usages_unique_server_account_date'
            );
        });

        Schema::table('smtp_server_usages', function (Blueprint $table) {
            $table->dropUnique('smtp_server_usages_unique_server_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_server_usages', function (Blueprint $table) {
            $table->unique(['smtp_server_id', 'usage_date'], 'smtp_server_usages_unique_server_date');
        });

        Schema::table('smtp_server_usages', function (Blueprint $table) {
            $table->dropUnique('smtp_server_usages_unique_server_account_date');
        });
    }
};
