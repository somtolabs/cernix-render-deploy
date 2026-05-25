<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->char('token_id', 36)->primary();
            $table->string('student_id');
            $table->unsignedBigInteger('session_id');
            $table->text('encrypted_payload');
            $table->text('hmac_signature');
            $table->enum('status', ['UNUSED', 'USED', 'REVOKED'])->default('UNUSED');
            $table->timestamp('issued_at');
            $table->timestamp('used_at')->nullable();

            $table->foreign('student_id')
                  ->references('matric_no')
                  ->on('students');

            $table->foreign('session_id')
                  ->references('session_id')
                  ->on('exam_sessions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_tokens');
    }
};
