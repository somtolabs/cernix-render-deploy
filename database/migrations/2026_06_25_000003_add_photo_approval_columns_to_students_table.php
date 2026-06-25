<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'photo_status')) {
                $table->enum('photo_status', [
                    'pending_photo_upload',
                    'pending_admin_approval',
                    'approved',
                    'rejected',
                    'flagged',
                ])->default('pending_photo_upload')->after('photo_path');
            }

            if (! Schema::hasColumn('students', 'photo_rejection_reason')) {
                $table->string('photo_rejection_reason', 500)->nullable()->after('photo_status');
            }

            if (! Schema::hasColumn('students', 'photo_reviewed_by')) {
                $table->string('photo_reviewed_by')->nullable()->after('photo_rejection_reason');
            }

            if (! Schema::hasColumn('students', 'photo_reviewed_at')) {
                $table->timestamp('photo_reviewed_at')->nullable()->after('photo_reviewed_by');
            }

            if (! Schema::hasColumn('students', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            foreach (['updated_at', 'photo_reviewed_at', 'photo_reviewed_by', 'photo_rejection_reason', 'photo_status'] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
