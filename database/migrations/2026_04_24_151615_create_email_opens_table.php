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
        Schema::create('email_opens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_queue_id');
            $table->foreign('email_queue_id', 'email_opens_queue_fk')
                ->references('id')->on('email_queue')->cascadeOnDelete();
            $table->index('email_queue_id', 'email_opens_queue_id_idx');
            $table->timestamp('opened_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_opens');
    }
};
