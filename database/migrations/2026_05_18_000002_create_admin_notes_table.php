<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notes', function (Blueprint $table) {
            $table->bigIncrements('note_id');
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('entity_type');
            $table->string('entity_id');
            $table->string('note_type')->nullable();
            $table->text('note');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['note_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notes');
    }
};
