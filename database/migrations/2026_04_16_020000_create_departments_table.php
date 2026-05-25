<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->bigIncrements('dept_id');
            $table->string('dept_name');
            $table->string('faculty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
