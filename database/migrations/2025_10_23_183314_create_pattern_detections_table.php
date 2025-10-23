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
        Schema::create('pattern_detections', function (Blueprint $table) {
            $table->id();

            // Pattern classification
            $table->string('pattern_type')->index(); // distributed_attack, subnet_scan, coordinated_attack, anomaly_spike
            $table->string('severity'); // low, medium, high, critical
            $table->integer('confidence_score')->default(50); // 0-100

            // Attack details
            $table->string('email_hash', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('subnet', 50)->nullable()->index();
            $table->string('domain')->nullable()->index();

            // Pattern metrics
            $table->unsignedInteger('affected_ips_count')->default(0);
            $table->unsignedInteger('affected_emails_count')->default(0);
            $table->unsignedInteger('time_window_minutes')->default(60);

            // Detection metadata
            $table->string('detection_algorithm', 100);
            $table->timestamp('detected_at')->index();
            $table->timestamp('first_incident_at')->nullable();
            $table->timestamp('last_incident_at')->nullable();

            // JSON metadata
            $table->json('pattern_data')->nullable(); // Full pattern details
            $table->json('related_incidents')->nullable(); // Array of incident IDs

            // Resolution
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['pattern_type', 'severity', 'detected_at']);
            $table->index(['resolved_at', 'detected_at']); // For unresolved patterns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pattern_detections');
    }
};
