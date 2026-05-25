<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notes', function (Blueprint $table) {
            $table->string('visibility')->default('internal')->after('note_type')->index();
            $table->string('target_type')->nullable()->after('visibility')->index();
            $table->string('target_id')->nullable()->after('target_type')->index();
            $table->boolean('requires_acknowledgement')->default(false)->after('target_id');
            $table->timestamp('student_read_at')->nullable()->after('requires_acknowledgement');
            $table->timestamp('examiner_read_at')->nullable()->after('student_read_at');
            $table->timestamp('student_acknowledged_at')->nullable()->after('examiner_read_at');
            $table->timestamp('examiner_acknowledged_at')->nullable()->after('student_acknowledged_at');
            $table->timestamp('resolved_at')->nullable()->after('examiner_acknowledged_at')->index();

            $table->index(['visibility', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_notes', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'created_at']);
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropColumn([
                'visibility',
                'target_type',
                'target_id',
                'requires_acknowledgement',
                'student_read_at',
                'examiner_read_at',
                'student_acknowledged_at',
                'examiner_acknowledged_at',
                'resolved_at',
            ]);
        });
    }
};
