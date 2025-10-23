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
        Schema::create('abuse_incidents', function (Blueprint $table) {
            $table->id();
            $table->enum('incident_type', [
                'rate_limit_exceeded',
                'ip_spoofing_attempt',
                'otp_bruteforce',
                'honeypot_triggered',
                'invalid_otp_attempts',
                'ip_mismatch',
                'suspicious_pattern',
                'other',
            ])->index();
            $table->string('ip_address', 45)->index();
            $table->string('email_hash', 64)->nullable()->index();
            $table->string('domain')->nullable()->index();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['incident_type', 'severity', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abuse_incidents');
    }
};
