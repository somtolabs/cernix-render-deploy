<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

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

            if (! Schema::hasColumn('students', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('photo_path');
            }

            if (! Schema::hasColumn('students', 'photo_flag_reason')) {
                $table->string('photo_flag_reason', 500)->nullable();
            }

            if (! Schema::hasColumn('students', 'photo_approved_at')) {
                $table->timestamp('photo_approved_at')->nullable();
            }

            if (! Schema::hasColumn('students', 'photo_approved_by')) {
                $table->string('photo_approved_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty — this migration only adds columns
    }
};
