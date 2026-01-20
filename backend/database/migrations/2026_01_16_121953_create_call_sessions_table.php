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
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('session_id')->unique(); // Cloudonix session ID
            $table->string('call_id')->nullable(); // Cloudonix call ID
            $table->string('direction'); // inbound/outbound
            $table->string('from_number');
            $table->string('to_number');
            $table->enum('status', ['ringing', 'answered', 'completed', 'failed', 'busy'])->default('ringing');
            $table->json('state')->nullable(); // runtime state data
            $table->json('metadata')->nullable(); // additional call metadata
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['session_id']);
            $table->index(['started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
