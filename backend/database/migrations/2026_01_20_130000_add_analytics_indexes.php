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
        // Optimize call_sessions table for analytics queries
        Schema::table('call_sessions', function (Blueprint $table) {
            // Composite indexes for common analytics queries
            $table->index(['tenant_id', 'started_at'], 'idx_call_sessions_tenant_date');
            $table->index(['tenant_id', 'status'], 'idx_call_sessions_tenant_status');
            $table->index(['tenant_id', 'voice_agent_id', 'started_at'], 'idx_call_sessions_agent_date');
            $table->index(['tenant_id', 'direction', 'started_at'], 'idx_call_sessions_direction_date');
            $table->index(['started_at', 'ended_at'], 'idx_call_sessions_duration');
            $table->index(['tenant_id', 'started_at', 'status'], 'idx_call_sessions_tenant_date_status');

            // JSON indexes for metadata queries
            $table->index(['metadata->group_id'], 'idx_call_sessions_group_metadata');
            $table->index(['metadata->routing_type'], 'idx_call_sessions_routing_type');
        });

        // Optimize voice_agents table
        Schema::table('voice_agents', function (Blueprint $table) {
            $table->index(['tenant_id', 'enabled'], 'idx_voice_agents_tenant_enabled');
            $table->index(['tenant_id', 'created_at'], 'idx_voice_agents_tenant_created');
        });

        // Optimize agent_groups table
        Schema::table('agent_groups', function (Blueprint $table) {
            $table->index(['tenant_id', 'enabled'], 'idx_agent_groups_tenant_enabled');
        });

        // Optimize trunks table (from WP5)
        Schema::table('trunks', function (Blueprint $table) {
            $table->index(['tenant_id', 'enabled'], 'idx_trunks_tenant_enabled');
            $table->index(['tenant_id', 'priority'], 'idx_trunks_tenant_priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from call_sessions
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_call_sessions_tenant_date');
            $table->dropIndex('idx_call_sessions_tenant_status');
            $table->dropIndex('idx_call_sessions_agent_date');
            $table->dropIndex('idx_call_sessions_direction_date');
            $table->dropIndex('idx_call_sessions_duration');
            $table->dropIndex('idx_call_sessions_tenant_date_status');
            $table->dropIndex('idx_call_sessions_group_metadata');
            $table->dropIndex('idx_call_sessions_routing_type');
        });

        // Remove indexes from voice_agents
        Schema::table('voice_agents', function (Blueprint $table) {
            $table->dropIndex('idx_voice_agents_tenant_enabled');
            $table->dropIndex('idx_voice_agents_tenant_created');
        });

        // Remove indexes from agent_groups
        Schema::table('agent_groups', function (Blueprint $table) {
            $table->dropIndex('idx_agent_groups_tenant_enabled');
        });

        // Remove indexes from trunks
        Schema::table('trunks', function (Blueprint $table) {
            $table->dropIndex('idx_trunks_tenant_enabled');
            $table->dropIndex('idx_trunks_tenant_priority');
        });
    }
};