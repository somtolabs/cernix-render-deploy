<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetables')) {
            return;
        }

        Schema::create('timetables', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('exam_session_id');
            $table->unsignedBigInteger('department_id');
            $table->string('level', 3);
            $table->string('course_code', 20);
            $table->string('course_title')->nullable();
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('venue');
            $table->enum('status', ['scheduled', 'active', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->foreign('exam_session_id')->references('session_id')->on('exam_sessions')->cascadeOnDelete();
            $table->foreign('department_id')->references('dept_id')->on('departments')->cascadeOnDelete();
            $table->unique(['exam_session_id', 'department_id', 'level', 'course_code', 'exam_date', 'start_time'], 'timetable_unique_exam');
            $table->index(['exam_session_id', 'department_id', 'level', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
