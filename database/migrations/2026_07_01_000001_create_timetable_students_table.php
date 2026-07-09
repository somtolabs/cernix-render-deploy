<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timetable_id');
            $table->string('matric_no', 30);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['timetable_id', 'matric_no']);
            $table->index('timetable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_students');
    }
};
