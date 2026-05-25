<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->bigIncrements('log_id');
            $table->char('token_id', 36);
            $table->unsignedBigInteger('examiner_id');
            $table->enum('decision', ['APPROVED', 'REJECTED', 'DUPLICATE']);
            $table->timestamp('timestamp');
            $table->string('device_fp');
            $table->string('ip_address');

            $table->index(['examiner_id', 'timestamp']);
            $table->index(['decision', 'timestamp']);
            $table->index(['token_id', 'timestamp']);

            $table->foreign('token_id')
                  ->references('token_id')
                  ->on('qr_tokens');

            $table->foreign('examiner_id')
                  ->references('examiner_id')
                  ->on('examiners');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
    }
};
