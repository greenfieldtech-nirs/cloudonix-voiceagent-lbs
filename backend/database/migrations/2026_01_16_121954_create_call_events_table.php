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
        Schema::create('call_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('call_session_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // Cloudonix webhook event type
            $table->string('event_id')->unique(); // Cloudonix event ID for idempotency
            $table->json('payload'); // webhook payload
            $table->json('headers')->nullable(); // request headers
            $table->timestamp('occurred_at'); // when event occurred
            $table->timestamp('processed_at')->nullable();
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'processing_status']);
            $table->index(['event_id']);
            $table->index(['occurred_at']);
            $table->index(['call_session_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_events');
    }
};
