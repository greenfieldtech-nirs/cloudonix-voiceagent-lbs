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
        // Check if indexes already exist before creating them
        $existingIndexes = collect(\DB::select("SHOW INDEXES FROM call_sessions WHERE Key_name LIKE 'idx_%'"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        // Optimize call_sessions table for analytics queries
        Schema::table('call_sessions', function (Blueprint $table) use ($existingIndexes) {
            // Only create indexes that don't already exist
            if (!in_array('idx_call_sessions_direction_date', $existingIndexes)) {
                $table->index(['tenant_id', 'direction', 'started_at'], 'idx_call_sessions_direction_date');
            }

            if (!in_array('idx_call_sessions_duration', $existingIndexes)) {
                $table->index(['started_at', 'ended_at'], 'idx_call_sessions_duration');
            }

            if (!in_array('idx_call_sessions_tenant_date_status', $existingIndexes)) {
                $table->index(['tenant_id', 'started_at', 'status'], 'idx_call_sessions_tenant_date_status');
            }

            // JSON indexes for metadata queries - Note: These may not work on all MySQL/MariaDB versions
            // For now, we'll skip JSON indexes as they require specific database versions and syntax
            // if (!in_array('idx_call_sessions_group_metadata', $existingIndexes)) {
            //     $table->index([DB::raw('JSON_EXTRACT(metadata, "$.group_id")')], 'idx_call_sessions_group_metadata');
            // }
            // if (!in_array('idx_call_sessions_routing_type', $existingIndexes)) {
            //     $table->index([DB::raw('JSON_EXTRACT(metadata, "$.routing_type")')], 'idx_call_sessions_routing_type');
            // }
        });

        // Get existing indexes for other tables too
        $voiceAgentsIndexes = collect(\DB::select("SHOW INDEXES FROM voice_agents WHERE Key_name LIKE 'idx_%'"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        $agentGroupsIndexes = collect(\DB::select("SHOW INDEXES FROM agent_groups WHERE Key_name LIKE 'idx_%'"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        $trunksIndexes = collect(\DB::select("SHOW INDEXES FROM trunks WHERE Key_name LIKE 'idx_%'"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        // Optimize voice_agents table
        Schema::table('voice_agents', function (Blueprint $table) use ($voiceAgentsIndexes) {
            if (!in_array('idx_voice_agents_tenant_enabled', $voiceAgentsIndexes)) {
                $table->index(['tenant_id', 'enabled'], 'idx_voice_agents_tenant_enabled');
            }
            if (!in_array('idx_voice_agents_tenant_created', $voiceAgentsIndexes)) {
                $table->index(['tenant_id', 'created_at'], 'idx_voice_agents_tenant_created');
            }
        });

        // Optimize agent_groups table
        Schema::table('agent_groups', function (Blueprint $table) use ($agentGroupsIndexes) {
            if (!in_array('idx_agent_groups_tenant_enabled', $agentGroupsIndexes)) {
                $table->index(['tenant_id', 'enabled'], 'idx_agent_groups_tenant_enabled');
            }
        });

        // Optimize trunks table (from WP5)
        Schema::table('trunks', function (Blueprint $table) use ($trunksIndexes) {
            if (!in_array('idx_trunks_tenant_enabled', $trunksIndexes)) {
                $table->index(['tenant_id', 'enabled'], 'idx_trunks_tenant_enabled');
            }
            if (!in_array('idx_trunks_tenant_priority', $trunksIndexes)) {
                $table->index(['tenant_id', 'priority'], 'idx_trunks_tenant_priority');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from call_sessions
        Schema::table('call_sessions', function (Blueprint $table) {
            // Only drop indexes that we know were created by this migration
            try {
                $table->dropIndex('idx_call_sessions_direction_date');
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            try {
                $table->dropIndex('idx_call_sessions_duration');
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            try {
                $table->dropIndex('idx_call_sessions_tenant_date_status');
            } catch (\Exception $e) {
                // Index might not exist, continue
            }

            // Skip JSON indexes as they were not created
            // $table->dropIndex('idx_call_sessions_group_metadata');
            // $table->dropIndex('idx_call_sessions_routing_type');
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