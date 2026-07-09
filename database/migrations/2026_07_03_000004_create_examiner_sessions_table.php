<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('examiner_sessions')) {
            return;
        }

        Schema::create('examiner_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('examiner_id');
            $table->unsignedBigInteger('timetable_id');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->json('audit_summary')->nullable();
            $table->timestamps();

            $table->index(['examiner_id', 'ended_at']);
            $table->index(['timetable_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examiner_sessions');
    }
};
