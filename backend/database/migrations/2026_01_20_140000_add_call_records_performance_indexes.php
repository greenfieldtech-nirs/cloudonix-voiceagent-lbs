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
        // Additional indexes for call_records performance
        Schema::table('call_records', function (Blueprint $table) {
            // Composite indexes for common query patterns
            $table->index(['tenant_id', 'start_time', 'status'], 'idx_call_records_tenant_time_status');
            $table->index(['tenant_id', 'agent_id', 'start_time'], 'idx_call_records_tenant_agent_time');
            $table->index(['tenant_id', 'group_id', 'start_time'], 'idx_call_records_tenant_group_time');
            $table->index(['tenant_id', 'direction', 'start_time'], 'idx_call_records_tenant_direction_time');
            $table->index(['tenant_id', 'start_time', 'duration'], 'idx_call_records_tenant_time_duration');

            // Partial indexes for active calls
            $table->index(['tenant_id', 'status'], 'idx_call_records_active')->where('status', '!=', 'completed');

            // Text search indexes for phone numbers
            $table->index(['tenant_id', 'from_number'], 'idx_call_records_from_number');
            $table->index(['tenant_id', 'to_number'], 'idx_call_records_to_number');

            // Session token lookup
            $table->index(['tenant_id', 'session_token'], 'idx_call_records_session_token');
        });

        // Indexes for webhook_audit table
        Schema::table('webhook_audits', function (Blueprint $table) {
            // Performance indexes for audit queries
            $table->index(['tenant_id', 'event_type', 'processed_at'], 'idx_webhook_audits_tenant_event_time');
            $table->index(['tenant_id', 'session_token'], 'idx_webhook_audits_tenant_session');
            $table->index(['processed_at'], 'idx_webhook_audits_processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove call_records indexes
        Schema::table('call_records', function (Blueprint $table) {
            $table->dropIndex('idx_call_records_tenant_time_status');
            $table->dropIndex('idx_call_records_tenant_agent_time');
            $table->dropIndex('idx_call_records_tenant_group_time');
            $table->dropIndex('idx_call_records_tenant_direction_time');
            $table->dropIndex('idx_call_records_tenant_time_duration');
            $table->dropIndex('idx_call_records_active');
            $table->dropIndex('idx_call_records_from_number');
            $table->dropIndex('idx_call_records_to_number');
            $table->dropIndex('idx_call_records_session_token');
        });

        // Remove webhook_audits indexes
        Schema::table('webhook_audits', function (Blueprint $table) {
            $table->dropIndex('idx_webhook_audits_tenant_event_time');
            $table->dropIndex('idx_webhook_audits_tenant_session');
            $table->dropIndex('idx_webhook_audits_processed_at');
        });
    }
};