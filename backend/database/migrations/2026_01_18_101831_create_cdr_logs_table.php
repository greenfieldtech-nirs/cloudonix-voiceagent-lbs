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
        Schema::create('cdr_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Core CDR Fields
            $table->string('call_id', 255)->index();
            $table->string('session_token', 255)->nullable()->index();
            $table->string('from_number', 255)->nullable();
            $table->string('to_number', 255)->nullable();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('disposition', ['ANSWER', 'BUSY', 'CANCEL', 'FAILED', 'CONGESTION', 'NOANSWER']);

            // Timing Fields
            $table->timestamp('start_time')->nullable();
            $table->timestamp('answer_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('billsec')->nullable();

            // Extended CDR Fields
            $table->string('domain', 255)->nullable()->index();
            $table->string('subscriber', 255)->nullable();
            $table->string('cx_trunk_id', 255)->nullable();
            $table->string('application', 255)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('vapp_server', 255)->nullable();

            // Complete Raw CDR (includes QoS data and all original webhook data)
            $table->json('raw_cdr');

            // Metadata
            $table->timestamps();

            // Performance Indexes
            $table->index(['tenant_id', 'call_id'], 'idx_tenant_call_id');
            $table->index(['tenant_id', 'disposition'], 'idx_tenant_disposition');
            $table->index(['tenant_id', 'start_time'], 'idx_tenant_start_time');
            $table->index(['tenant_id', 'from_number', 'to_number'], 'idx_tenant_from_to');
            $table->index(['created_at'], 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cdr_logs');
    }
};
