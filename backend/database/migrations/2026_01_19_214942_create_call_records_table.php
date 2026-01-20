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
        Schema::create('call_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('session_token', 255)->index();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('from_number', 50)->nullable();
            $table->string('to_number', 50)->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('voice_agents')->onDelete('set null');
            $table->foreignId('group_id')->nullable()->constrained('agent_groups')->onDelete('set null');
            $table->enum('status', ['queued', 'ringing', 'in_progress', 'completed', 'busy', 'failed', 'no_answer']);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('duration')->nullable(); // seconds
            $table->timestamps();

            $table->index(['tenant_id', 'session_token'], 'idx_tenant_session');
            $table->index(['tenant_id', 'direction', 'status'], 'idx_tenant_direction_status');
            $table->index(['tenant_id', 'start_time'], 'idx_tenant_start_time');
            $table->index(['agent_id', 'start_time'], 'idx_agent_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_records');
    }
};
