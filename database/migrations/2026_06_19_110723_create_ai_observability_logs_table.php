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
        Schema::create('ai_observability_logs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->nullable()->index();
            $table->timestamp('timestamp');
            $table->longText('user_prompt');
            $table->longText('system_response')->nullable();
            $table->float('ttft_ms')->nullable();
            $table->float('total_latency_ms')->nullable();
            $table->float('tokens_per_second')->nullable();
            $table->boolean('was_blocked')->default(false);
            $table->json('tools_executed')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_observability_logs');
    }
};
