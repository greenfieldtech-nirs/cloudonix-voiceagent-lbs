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
        Schema::create('agent_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('strategy', ['load_balanced', 'priority', 'round_robin']);
            $table->json('settings')->nullable(); // Strategy-specific configuration
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'enabled'], 'idx_agent_groups_tenant_enabled');
            $table->index(['tenant_id', 'strategy'], 'idx_agent_groups_tenant_strategy');
            $table->unique(['tenant_id', 'name'], 'unique_tenant_group_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_groups');
    }
};
