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
        Schema::create('email_reputation', function (Blueprint $table) {
            $table->id();
            $table->string('email_hash', 64)->index(); // SHA-256 hash (GDPR compliant)
            $table->string('email_domain')->index();
            $table->integer('reputation_score')->default(100); // 0-100
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('failed_requests')->default(0);
            $table->unsignedInteger('verified_requests')->default(0); // OTP verified
            $table->timestamp('last_seen_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique constraint on email hash
            $table->unique('email_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_reputation');
    }
};
