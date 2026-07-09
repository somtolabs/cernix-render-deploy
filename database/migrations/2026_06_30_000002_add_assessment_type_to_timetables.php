<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('timetables', 'assessment_type')) {
            return;
        }

        Schema::table('timetables', function (Blueprint $table) {
            $table->string('assessment_type')->default('exam')->after('venue');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('timetables', 'assessment_type')) {
            Schema::table('timetables', function (Blueprint $table) {
                $table->dropColumn('assessment_type');
            });
        }
    }
};
