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
        // Check existing indexes
        $callRecordsIndexes = collect(\DB::select("SHOW INDEXES FROM call_records WHERE Key_name LIKE 'idx_%'"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        $webhookAuditIndexes = collect(\DB::select("SHOW INDEXES FROM webhook_audit WHERE Key_name LIKE 'idx_%'"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        // Additional indexes for call_records performance
        Schema::table('call_records', function (Blueprint $table) use ($callRecordsIndexes) {
            // Only create indexes that don't already exist
            if (!in_array('idx_call_records_tenant_time_status', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'start_time', 'status'], 'idx_call_records_tenant_time_status');
            }
            if (!in_array('idx_call_records_tenant_agent_time', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'agent_id', 'start_time'], 'idx_call_records_tenant_agent_time');
            }
            if (!in_array('idx_call_records_tenant_group_time', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'group_id', 'start_time'], 'idx_call_records_tenant_group_time');
            }
            if (!in_array('idx_call_records_tenant_direction_time', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'direction', 'start_time'], 'idx_call_records_tenant_direction_time');
            }
            if (!in_array('idx_call_records_tenant_time_duration', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'start_time', 'duration'], 'idx_call_records_tenant_time_duration');
            }
            if (!in_array('idx_call_records_active', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'status'], 'idx_call_records_active')->where('status', '!=', 'completed');
            }
            if (!in_array('idx_call_records_from_number', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'from_number'], 'idx_call_records_from_number');
            }
            if (!in_array('idx_call_records_to_number', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'to_number'], 'idx_call_records_to_number');
            }
            if (!in_array('idx_call_records_session_token', $callRecordsIndexes)) {
                $table->index(['tenant_id', 'session_token'], 'idx_call_records_session_token');
            }
        });

        // Indexes for webhook_audit table
        Schema::table('webhook_audit', function (Blueprint $table) use ($webhookAuditIndexes) {
            // Only create indexes that don't already exist
            if (!in_array('idx_webhook_audit_tenant_event_time', $webhookAuditIndexes)) {
                $table->index(['tenant_id', 'event_type', 'processed_at'], 'idx_webhook_audit_tenant_event_time');
            }
            if (!in_array('idx_webhook_audit_tenant_session', $webhookAuditIndexes)) {
                $table->index(['tenant_id', 'session_token'], 'idx_webhook_audit_tenant_session');
            }
            if (!in_array('idx_webhook_audit_processed_at', $webhookAuditIndexes)) {
                $table->index(['processed_at'], 'idx_webhook_audit_processed_at');
            }
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

        // Remove webhook_audit indexes
        Schema::table('webhook_audit', function (Blueprint $table) {
            $table->dropIndex('idx_webhook_audit_tenant_event_time');
            $table->dropIndex('idx_webhook_audit_tenant_session');
            $table->dropIndex('idx_webhook_audit_processed_at');
        });
    }
};