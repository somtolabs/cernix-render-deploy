<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'qr_tokens_student_session_timetable_unique';

    public function up(): void
    {
        if (! Schema::hasTable('qr_tokens') || ! Schema::hasColumn('qr_tokens', 'timetable_id')) {
            return;
        }

        $hasIndex = collect(Schema::getIndexes('qr_tokens'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === self::INDEX);

        if ($hasIndex) {
            return;
        }

        $hasDuplicates = DB::table('qr_tokens')
            ->whereNotNull('timetable_id')
            ->select('student_id', 'session_id', 'timetable_id')
            ->groupBy('student_id', 'session_id', 'timetable_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            return;
        }

        Schema::table('qr_tokens', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'session_id', 'timetable_id'],
                self::INDEX
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qr_tokens')) {
            return;
        }

        $hasIndex = collect(Schema::getIndexes('qr_tokens'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === self::INDEX);

        if ($hasIndex) {
            Schema::table('qr_tokens', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
