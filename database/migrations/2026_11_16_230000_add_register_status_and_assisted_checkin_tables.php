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
        // 1. Timetables register confirmation tracking
        if (Schema::hasTable('timetables')) {
            Schema::table('timetables', function (Blueprint $table) {
                if (! Schema::hasColumn('timetables', 'register_status')) {
                    $table->string('register_status', 30)->default('incomplete')->after('status');
                }
                if (! Schema::hasColumn('timetables', 'roster_confirmed_at')) {
                    $table->timestamp('roster_confirmed_at')->nullable()->after('register_status');
                }
                if (! Schema::hasColumn('timetables', 'roster_confirmed_by')) {
                    $table->string('roster_confirmed_by', 100)->nullable()->after('roster_confirmed_at');
                }
            });
        }

        // 2. Attendance records check-in method and assisted check-in details
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                if (! Schema::hasColumn('attendance_records', 'checkin_method')) {
                    $table->string('checkin_method', 20)->default('qr_scan')->after('token_id');
                }
                if (! Schema::hasColumn('attendance_records', 'assisted_reason')) {
                    $table->string('assisted_reason', 100)->nullable()->after('checkin_method');
                }
                if (! Schema::hasColumn('attendance_records', 'assisted_notes')) {
                    $table->text('assisted_notes')->nullable()->after('assisted_reason');
                }
            });
        }

        // 3. Dedicated assisted check-in audit log table
        if (! Schema::hasTable('assisted_checkin_logs')) {
            Schema::create('assisted_checkin_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('timetable_id');
                $table->unsignedBigInteger('session_id');
                $table->string('matric_no', 50);
                $table->unsignedBigInteger('examiner_id');
                $table->string('reason', 100);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['timetable_id', 'matric_no'], 'assisted_tt_matric_idx');
                $table->index(['examiner_id', 'created_at'], 'assisted_exam_time_idx');
            });
        }

        // 4. Timetable students candidate metadata
        if (Schema::hasTable('timetable_students')) {
            Schema::table('timetable_students', function (Blueprint $table) {
                if (! Schema::hasColumn('timetable_students', 'candidate_type')) {
                    $table->string('candidate_type', 20)->default('regular')->after('matric_no');
                }
                if (! Schema::hasColumn('timetable_students', 'enrolled_by')) {
                    $table->string('enrolled_by', 100)->nullable()->after('candidate_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('assisted_checkin_logs')) {
            Schema::dropIfExists('assisted_checkin_logs');
        }

        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('attendance_records', 'assisted_notes')) {
                    $cols[] = 'assisted_notes';
                }
                if (Schema::hasColumn('attendance_records', 'assisted_reason')) {
                    $cols[] = 'assisted_reason';
                }
                if (Schema::hasColumn('attendance_records', 'checkin_method')) {
                    $cols[] = 'checkin_method';
                }
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('timetables')) {
            Schema::table('timetables', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('timetables', 'roster_confirmed_by')) {
                    $cols[] = 'roster_confirmed_by';
                }
                if (Schema::hasColumn('timetables', 'roster_confirmed_at')) {
                    $cols[] = 'roster_confirmed_at';
                }
                if (Schema::hasColumn('timetables', 'register_status')) {
                    $cols[] = 'register_status';
                }
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('timetable_students')) {
            Schema::table('timetable_students', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('timetable_students', 'enrolled_by')) {
                    $cols[] = 'enrolled_by';
                }
                if (Schema::hasColumn('timetable_students', 'candidate_type')) {
                    $cols[] = 'candidate_type';
                }
                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
