<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Track DirectAdmin BFM whitelist entries with expiration times
     * for automatic cleanup via scheduled job
     */
    public function up(): void
    {
        Schema::create('bfm_whitelist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45); // IPv4 o IPv6
            $table->timestamp('added_at');
            $table->timestamp('expires_at');
            $table->boolean('removed')->default(false);
            $table->timestamp('removed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices para optimizar búsquedas
            $table->index(['host_id', 'ip_address', 'removed']);
            $table->index(['expires_at', 'removed']);
            $table->unique(['host_id', 'ip_address', 'removed']); // Una IP activa por host
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bfm_whitelist_entries');
    }
};
