<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->bigIncrements('payment_id');
            $table->string('student_id');
            $table->string('rrr_number')->unique();
            $table->decimal('amount_declared', 10, 2);
            $table->decimal('amount_confirmed', 10, 2);
            $table->json('remita_response');
            $table->timestamp('verified_at');

            $table->foreign('student_id')
                  ->references('matric_no')
                  ->on('students');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};
