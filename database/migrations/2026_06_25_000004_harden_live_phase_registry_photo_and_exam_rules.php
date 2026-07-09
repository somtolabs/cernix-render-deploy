<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if (! Schema::hasColumn('students', 'photo_status')) {
                    $table->string('photo_status', 40)->default('pending_photo_upload');
                }

                if (! Schema::hasColumn('students', 'photo_rejection_reason')) {
                    $table->string('photo_rejection_reason', 500)->nullable();
                }

                if (! Schema::hasColumn('students', 'photo_reviewed_by')) {
                    $table->string('photo_reviewed_by')->nullable();
                }

                if (! Schema::hasColumn('students', 'photo_reviewed_at')) {
                    $table->timestamp('photo_reviewed_at')->nullable();
                }

                if (! Schema::hasColumn('students', 'photo_approved_at')) {
                    $table->timestamp('photo_approved_at')->nullable();
                }

                if (! Schema::hasColumn('students', 'photo_approved_by')) {
                    $table->string('photo_approved_by')->nullable();
                }

                if (! Schema::hasColumn('students', 'photo_flag_reason')) {
                    $table->string('photo_flag_reason', 500)->nullable();
                }

                if (! Schema::hasColumn('students', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            DB::table('students')
                ->where(fn ($query) => $query->whereNull('photo_status')->orWhere('photo_status', ''))
                ->update([
                    'photo_status' => DB::raw("CASE WHEN photo_path IS NOT NULL AND photo_path != '' THEN 'pending_admin_approval' ELSE 'pending_photo_upload' END"),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('timetables') && ! Schema::hasColumn('timetables', 'payment_required')) {
            Schema::table('timetables', function (Blueprint $table) {
                $table->boolean('payment_required')->nullable()->after('status');
            });
        }

        if (Schema::hasTable('cernix_settings')) {
            $settings = [
                'system_mode' => 'live',
                'require_photo_approval_before_qr' => 'true',
                'allow_payment_not_required_exams' => 'true',
                'default_exam_payment_required' => 'true',
                'enable_submission_scan' => 'false',
                'allow_csv_student_import' => 'true',
                'scanner_server_verification_required' => 'true',
                'qr_single_use_enforced' => 'true',
            ];

            foreach ($settings as $key => $value) {
                DB::table('cernix_settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timetables') && Schema::hasColumn('timetables', 'payment_required')) {
            Schema::table('timetables', function (Blueprint $table) {
                $table->dropColumn('payment_required');
            });
        }

        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                foreach (['photo_flag_reason', 'photo_approved_by', 'photo_approved_at'] as $column) {
                    if (Schema::hasColumn('students', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
