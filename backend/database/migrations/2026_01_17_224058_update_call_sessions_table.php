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
        Schema::table('call_sessions', function (Blueprint $table) {
            // Update status enum to include 'connected'
            $table->enum('status', ['ringing', 'connected', 'answered', 'completed', 'failed', 'busy'])
                ->default('ringing')
                ->change();

            // Add new columns for Cloudonix webhook data (only those not already existing)
            $table->string('domain')->nullable()->after('session_id');
            $table->string('caller_id')->nullable()->after('domain');
            $table->string('destination')->nullable()->after('caller_id');
            $table->string('token')->nullable()->after('direction');
            $table->string('vapp_server')->nullable()->after('token');
            $table->timestamp('call_start_time')->nullable()->after('vapp_server');
            $table->timestamp('call_answer_time')->nullable()->after('call_start_time');
            $table->timestamp('answer_time')->nullable()->after('call_answer_time');
            $table->timestamp('webhook_created_at')->nullable()->after('answer_time');
            $table->timestamp('webhook_modified_at')->nullable()->after('webhook_created_at');

            // Add indexes for performance
            $table->index(['domain']);
            $table->index(['status', 'tenant_id']);
            $table->index(['call_start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['domain']);
            $table->dropIndex(['status', 'tenant_id']);
            $table->dropIndex(['call_start_time']);

            // Drop new columns (only those added by this migration)
            $table->dropColumn([
                'domain',
                'caller_id',
                'destination',
                'token',
                'vapp_server',
                'call_start_time',
                'call_answer_time',
                'answer_time',
                'webhook_created_at',
                'webhook_modified_at',
            ]);

            // Revert status enum to original
            $table->enum('status', ['ringing', 'answered', 'completed', 'failed', 'busy'])
                ->default('ringing')
                ->change();
        });
    }
};
