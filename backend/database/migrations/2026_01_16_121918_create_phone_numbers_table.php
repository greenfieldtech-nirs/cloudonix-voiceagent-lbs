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
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('number')->unique();
            $table->string('provider_id')->nullable(); // Cloudonix provider ID
            $table->string('label')->nullable();
            $table->json('capabilities')->nullable(); // ['voice', 'sms', 'fax']
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->foreignId('voice_application_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
