<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_students', function (Blueprint $table) {
            $table->id();
            $table->string('matric_number')->unique();
            $table->string('full_name');
            $table->string('department');
            $table->string('faculty');
            $table->string('level', 20);
            $table->string('programme')->nullable();
            $table->string('academic_session')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['status', 'department', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_students');
    }
};
