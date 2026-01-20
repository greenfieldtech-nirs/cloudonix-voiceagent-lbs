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
            $table->foreignId('group_id')->constrained('agent_groups')->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('voice_agents')->onDelete('cascade');
            $table->integer('priority')->nullable(); // For priority strategy ordering
            $table->integer('capacity')->default(1); // Relative capacity weight
            $table->timestamps();

            $table->unique(['group_id', 'agent_id'], 'unique_group_agent');
            $table->index(['group_id', 'priority'], 'idx_group_priority');
            $table->index(['group_id', 'capacity'], 'idx_group_capacity');
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
