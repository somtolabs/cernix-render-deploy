<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('qr_tokens', 'timetable_id')) {
            return;
        }

        Schema::table('qr_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('timetable_id')->nullable()->after('session_id');
            $table->foreign('timetable_id')
                ->references('id')
                ->on('timetables')
                ->nullOnDelete();
            $table->index(['student_id', 'session_id', 'timetable_id'], 'qr_tokens_exam_lookup');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('qr_tokens', 'timetable_id')) {
            return;
        }

        Schema::table('qr_tokens', function (Blueprint $table) {
            $table->dropForeign(['timetable_id']);
            $table->dropIndex('qr_tokens_exam_lookup');
            $table->dropColumn('timetable_id');
        });
    }
};
