<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->string('phone_column')->nullable()->after('website_column');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->dropColumn('phone_column');
        });
    }
};
