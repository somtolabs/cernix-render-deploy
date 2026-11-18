<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_students', function (Blueprint $table) {
            $table->string('department')->nullable()->change();
            $table->string('faculty')->nullable()->change();
            $table->string('level', 20)->nullable()->change();

            if (! Schema::hasColumn('official_students', 'admission_year')) {
                $table->unsignedSmallInteger('admission_year')->nullable()->after('matric_number');
            }
            if (! Schema::hasColumn('official_students', 'faculty_code')) {
                $table->string('faculty_code', 2)->nullable()->after('admission_year');
            }
            if (! Schema::hasColumn('official_students', 'department_code')) {
                $table->string('department_code', 2)->nullable()->after('faculty_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('official_students', function (Blueprint $table) {
            if (Schema::hasColumn('official_students', 'department_code')) {
                $table->dropColumn('department_code');
            }
            if (Schema::hasColumn('official_students', 'faculty_code')) {
                $table->dropColumn('faculty_code');
            }
            if (Schema::hasColumn('official_students', 'admission_year')) {
                $table->dropColumn('admission_year');
            }
            $table->string('level', 20)->nullable(false)->change();
            $table->string('faculty')->nullable(false)->change();
            $table->string('department')->nullable(false)->change();
        });
    }
};
