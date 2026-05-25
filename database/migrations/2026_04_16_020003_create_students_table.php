<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->string('matric_no')->primary();
            $table->string('full_name');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('session_id');
            $table->string('photo_path');
            $table->timestamp('created_at');

            $table->foreign('department_id')
                  ->references('dept_id')
                  ->on('departments');

            $table->foreign('session_id')
                  ->references('session_id')
                  ->on('exam_sessions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
