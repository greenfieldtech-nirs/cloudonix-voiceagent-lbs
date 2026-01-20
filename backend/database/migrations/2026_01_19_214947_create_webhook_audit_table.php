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
        Schema::create('webhook_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 100)->index();
            $table->string('session_token', 255)->index();
            $table->json('payload'); // Complete webhook request
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'event_type'], 'idx_tenant_event_type');
            $table->index(['tenant_id', 'processed_at'], 'idx_tenant_processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_audit');
    }
};
