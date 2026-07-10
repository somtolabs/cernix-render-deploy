<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profile_photo_change_requests')) {
            return;
        }

        Schema::create('profile_photo_change_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('matric_no', 50)->index();
            $table->text('reasons');
            $table->text('additional_notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by', 100)->nullable();
            $table->text('admin_response')->nullable();
            $table->timestamps();

            $table->index(['matric_no', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_photo_change_requests');
    }
};
