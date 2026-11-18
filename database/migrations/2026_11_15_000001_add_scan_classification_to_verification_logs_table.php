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
        if (Schema::hasTable('verification_logs') && ! Schema::hasColumn('verification_logs', 'scan_classification')) {
            Schema::table('verification_logs', function (Blueprint $table) {
                $table->string('scan_classification', 30)->nullable()->after('rejection_reason');
                if (Schema::hasColumn('verification_logs', 'timetable_id')) {
                    $table->index(['timetable_id', 'scan_classification'], 'verif_logs_tt_class_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('verification_logs') && Schema::hasColumn('verification_logs', 'scan_classification')) {
            Schema::table('verification_logs', function (Blueprint $table) {
                if (Schema::hasColumn('verification_logs', 'timetable_id')) {
                    $table->dropIndex('verif_logs_tt_class_idx');
                }
                $table->dropColumn('scan_classification');
            });
        }
    }
};
