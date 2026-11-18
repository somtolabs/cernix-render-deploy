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
        if (Schema::hasTable('verification_logs') && ! Schema::hasColumn('verification_logs', 'timetable_id')) {
            Schema::table('verification_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('timetable_id')->nullable()->after('examiner_id');
                $table->index(['timetable_id', 'timestamp'], 'verif_logs_timetable_time_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('verification_logs') && Schema::hasColumn('verification_logs', 'timetable_id')) {
            Schema::table('verification_logs', function (Blueprint $table) {
                $table->dropIndex('verif_logs_timetable_time_idx');
                $table->dropColumn('timetable_id');
            });
        }
    }
};
