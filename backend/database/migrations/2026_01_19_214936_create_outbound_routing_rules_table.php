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
        Schema::create('outbound_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('caller_id', 255); // Outbound caller ID pattern
            $table->string('destination_pattern', 255); // Prefix/country pattern
            $table->json('trunk_config'); // Cloudonix trunk selection rules
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'enabled'], 'idx_tenant_enabled');
            $table->index(['tenant_id', 'caller_id'], 'idx_tenant_caller_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_routing_rules');
    }
};
