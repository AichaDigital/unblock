<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates accounts table to cache hosting accounts from remote servers (cPanel/DirectAdmin).
     * This table acts as a local mirror of server data for fast validation without SSH connections.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('host_id')
                ->constrained('hosts')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Account data (synced from remote server)
            $table->string('username')->comment('Username in cPanel/DirectAdmin');
            $table->string('domain')->comment('Primary domain of the account');
            $table->string('owner')->nullable()->comment('Account owner name');

            // Status tracking
            $table->timestamp('suspended_at')->nullable()->comment('When account was suspended in the panel');
            $table->timestamp('deleted_at')->nullable()->comment('When account was deleted from the server');
            $table->timestamp('last_synced_at')->nullable()->comment('Last successful sync timestamp');

            $table->timestamps();

            // Indexes for performance
            $table->unique(['host_id', 'username'], 'accounts_host_username_unique');
            $table->index('domain', 'accounts_domain_index');
            $table->index('suspended_at', 'accounts_suspended_at_index');
            $table->index('deleted_at', 'accounts_deleted_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
