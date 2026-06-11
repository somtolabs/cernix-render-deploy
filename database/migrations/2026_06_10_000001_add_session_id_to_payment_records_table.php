<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_records', 'session_id')) {
            return;
        }

        Schema::table('payment_records', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id')->nullable()->after('student_id');
            $table->foreign('session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->nullOnDelete();
            $table->index(['student_id', 'session_id'], 'payment_records_session_lookup');
        });

        DB::table('payment_records')
            ->select('payment_id', 'student_id')
            ->orderBy('payment_id')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $payment) {
                    $sessionId = DB::table('students')
                        ->where('matric_no', $payment->student_id)
                        ->value('session_id');

                    if ($sessionId) {
                        DB::table('payment_records')
                            ->where('payment_id', $payment->payment_id)
                            ->update(['session_id' => $sessionId]);
                    }
                }
            }, 'payment_id', 'payment_id');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payment_records', 'session_id')) {
            return;
        }

        Schema::table('payment_records', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropIndex('payment_records_session_lookup');
            $table->dropColumn('session_id');
        });
    }
};
