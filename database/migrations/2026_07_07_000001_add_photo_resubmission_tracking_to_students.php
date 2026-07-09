<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'photo_resubmitted_at')) {
                $table->timestamp('photo_resubmitted_at')->nullable()->after('photo_rejection_reason');
            }
            if (! Schema::hasColumn('students', 'photo_submission_count')) {
                $table->unsignedInteger('photo_submission_count')->default(1)->after('photo_resubmitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'photo_submission_count')) {
                $table->dropColumn('photo_submission_count');
            }
            if (Schema::hasColumn('students', 'photo_resubmitted_at')) {
                $table->dropColumn('photo_resubmitted_at');
            }
        });
    }
};
