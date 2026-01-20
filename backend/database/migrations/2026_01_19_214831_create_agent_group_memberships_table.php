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
        Schema::create('agent_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('voice_agent_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('priority')->default(50); // 1-100, higher = higher priority
            $table->unsignedSmallInteger('capacity')->nullable(); // Max concurrent calls, null = unlimited
            $table->timestamps();

            // Composite unique constraint
            $table->unique(['agent_group_id', 'voice_agent_id'], 'unique_group_agent_membership');

            // Indexes
            $table->index(['agent_group_id', 'priority'], 'idx_memberships_group_priority');
            $table->index(['voice_agent_id'], 'idx_memberships_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_group_memberships');
    }
};
