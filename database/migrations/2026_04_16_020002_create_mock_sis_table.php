<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_sis', function (Blueprint $table) {
            $table->string('matric_no')->primary();
            $table->string('full_name');
            $table->string('department');
            $table->string('photo_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_sis');
    }
};
