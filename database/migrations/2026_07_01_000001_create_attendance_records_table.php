<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->string('matric_no');
            $table->unsignedBigInteger('timetable_id');
            $table->unsignedBigInteger('session_id');
            $table->string('token_id')->nullable();
            $table->enum('status', ['checked_in', 'submitted', 'flagged'])->default('checked_in');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('entry_examiner_id')->nullable();
            $table->unsignedBigInteger('exit_examiner_id')->nullable();
            $table->timestamps();

            $table->unique(['matric_no', 'timetable_id', 'session_id'], 'attendance_student_exam_unique');
            $table->index(['session_id', 'timetable_id']);
            $table->index('matric_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
