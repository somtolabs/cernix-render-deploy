<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('actor_id');
            $table->string('actor_type');
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('metadata');
            $table->string('ip_address')->nullable();
            $table->string('device_fp')->nullable();
            $table->string('trace_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->timestamp('timestamp');

            $table->index(['actor_type', 'actor_id']);
            $table->index(['action', 'timestamp']);
            $table->index(['target_type', 'target_id']);
            $table->index(['session_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
