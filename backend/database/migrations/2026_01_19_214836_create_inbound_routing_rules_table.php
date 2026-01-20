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
        Schema::create('inbound_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('pattern', 255); // Phone number or prefix pattern
            $table->enum('target_type', ['agent', 'group']);
            $table->unsignedBigInteger('target_id'); // References voice_agents or agent_groups
            $table->integer('priority')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'enabled', 'priority'], 'idx_tenant_enabled_priority');
            $table->index(['tenant_id', 'pattern'], 'idx_tenant_pattern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_routing_rules');
    }
};
