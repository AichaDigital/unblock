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
        Schema::create('ip_reputation', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index(); // IPv4 or IPv6
            $table->string('subnet', 50)->index(); // Subnet /24 or /48
            $table->integer('reputation_score')->default(100); // 0-100
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('failed_requests')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique constraint on IP
            $table->unique('ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_reputation');
    }
};
