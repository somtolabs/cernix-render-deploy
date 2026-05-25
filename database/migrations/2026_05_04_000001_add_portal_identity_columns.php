<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'department_code')) {
                $table->string('department_code', 2)->nullable()->after('faculty');
            }
            if (! Schema::hasColumn('departments', 'faculty_code')) {
                $table->string('faculty_code', 2)->nullable()->after('department_code');
            }
        });

        Schema::table('mock_sis', function (Blueprint $table) {
            if (! Schema::hasColumn('mock_sis', 'department_code')) {
                $table->string('department_code', 2)->nullable()->after('department');
            }
            if (! Schema::hasColumn('mock_sis', 'faculty_code')) {
                $table->string('faculty_code', 2)->nullable()->after('department_code');
            }
            if (! Schema::hasColumn('mock_sis', 'level')) {
                $table->string('level', 3)->nullable()->after('faculty_code');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'level')) {
                $table->string('level', 3)->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('students', 'department_code')) {
                $table->string('department_code', 2)->nullable()->after('level');
            }
            if (! Schema::hasColumn('students', 'faculty_code')) {
                $table->string('faculty_code', 2)->nullable()->after('department_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            foreach (['faculty_code', 'department_code', 'level'] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('mock_sis', function (Blueprint $table) {
            foreach (['level', 'faculty_code', 'department_code'] as $column) {
                if (Schema::hasColumn('mock_sis', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            foreach (['faculty_code', 'department_code'] as $column) {
                if (Schema::hasColumn('departments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
