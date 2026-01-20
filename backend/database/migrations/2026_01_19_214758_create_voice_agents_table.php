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
        Schema::create('voice_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('provider', [
                'synthflow', 'dasha', 'superdash.ai', 'elevenlabs', 'deepvox',
                'relayhawk', 'voicehub', 'retell-udp', 'retell-tcp', 'retell-tls',
                'retell', 'vapi', 'fonio', 'sigmamind', 'modon', 'puretalk',
                'millis-us', 'millis-eu'
            ]);
            $table->string('service_value', 500); // Encrypted in model
            $table->string('username', 255)->nullable(); // Encrypted in model
            $table->string('password', 255)->nullable(); // Encrypted in model
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'enabled'], 'idx_tenant_enabled');
            $table->index(['tenant_id', 'provider'], 'idx_tenant_provider');
            $table->unique(['tenant_id', 'name'], 'unique_tenant_agent_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_agents');
    }
};
