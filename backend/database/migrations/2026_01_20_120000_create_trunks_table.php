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
        Schema::create('trunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->string('cloudonix_trunk_id', 255); // Cloudonix trunk identifier
            $table->string('description')->nullable();
            $table->json('configuration'); // Cloudonix trunk configuration
            $table->integer('priority')->default(0); // Higher priority = preferred
            $table->integer('capacity')->default(0); // Concurrent call capacity (0 = unlimited)
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false); // Default fallback trunk
            $table->timestamps();

            $table->index(['tenant_id', 'enabled'], 'idx_trunks_tenant_enabled');
            $table->index(['tenant_id', 'priority'], 'idx_trunks_tenant_priority');
            $table->unique(['tenant_id', 'cloudonix_trunk_id'], 'unique_tenant_trunk_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trunks');
    }
};