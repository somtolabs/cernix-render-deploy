<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Students are keyed by matric_no (string), examiners by int id,
            // so owner_id is a string rather than morphs()' unsigned big int.
            $table->string('owner_type');
            $table->string('owner_id');

            $table->string('purpose');

            $table->string('disk');
            $table->string('storage_key')->unique();

            // Admin display only. Never used to construct a storage key.
            $table->string('original_filename')->nullable();

            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
