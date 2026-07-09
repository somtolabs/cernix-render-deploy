<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timetables')) {
            return;
        }

        $cols = ['exam_session_id', 'department_id', 'level', 'course_code', 'exam_date', 'start_time'];
        if (Schema::hasColumn('timetables', 'assessment_type')) {
            $cols[] = 'assessment_type';
        }
        if (Schema::hasColumn('timetables', 'venue')) {
            $cols[] = 'venue';
        }

        // Drop the old constraint — ignore failure (doesn't exist or already updated)
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS timetable_unique_exam');
            } else {
                Schema::table('timetables', fn (Blueprint $t) => $t->dropUnique('timetable_unique_exam'));
            }
        } catch (\Exception) {}

        // Add the expanded constraint — ignore if it already exists with correct columns
        try {
            Schema::table('timetables', fn (Blueprint $t) => $t->unique($cols, 'timetable_unique_exam'));
        } catch (\Exception) {}
    }

    public function down(): void
    {
        if (! Schema::hasTable('timetables')) {
            return;
        }

        try {
            Schema::table('timetables', function (Blueprint $table) {
                $table->dropUnique('timetable_unique_exam');
                $table->unique(
                    ['exam_session_id', 'department_id', 'level', 'course_code', 'exam_date', 'start_time'],
                    'timetable_unique_exam'
                );
            });
        } catch (\Exception) {}
    }
};
