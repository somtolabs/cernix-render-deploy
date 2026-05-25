<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examiners', function (Blueprint $table) {
            $table->bigIncrements('examiner_id');
            $table->string('full_name');
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->enum('role', ['examiner', 'admin', 'super_admin']);
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_active_at')->nullable()->index();
            $table->timestamp('created_at');

            $table->foreign('admin_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examiners');
    }
};
