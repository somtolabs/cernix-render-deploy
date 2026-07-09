<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('timetables', 'examiner_id')) {
            return;
        }

        Schema::table('timetables', function (Blueprint $table) {
            $table->unsignedBigInteger('examiner_id')->nullable()->after('venue');
            $table->foreign('examiner_id')->references('examiner_id')->on('examiners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('timetables', 'examiner_id')) {
            Schema::table('timetables', function (Blueprint $table) {
                $table->dropForeign(['examiner_id']);
                $table->dropColumn('examiner_id');
            });
        }
    }
};
