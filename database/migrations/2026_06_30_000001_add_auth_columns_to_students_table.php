<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'password')) {
                $table->string('password')->nullable()->after('photo_path');
            }

            if (! Schema::hasColumn('students', 'id_card_path')) {
                $table->string('id_card_path')->nullable()->after('password');
            }

            if (! Schema::hasColumn('students', 'account_status')) {
                $table->string('account_status')->default('pending')->after('id_card_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            foreach (['account_status', 'id_card_path', 'password'] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
