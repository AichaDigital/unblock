<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates domains table to store all domains (primary, addon, subdomains, aliases)
     * associated with hosting accounts. Used for fast domain validation in Simple Mode.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();

            // Foreign key
            $table->foreignId('account_id')
                ->constrained('accounts')
                ->onDelete('cascade');

            // Domain data
            $table->string('domain_name')->comment('Full domain name (lowercase, normalized)');
            $table->string('type')->default('primary')->comment('Domain type: primary, addon, subdomain, alias');

            $table->timestamps();

            // Indexes for fast lookups
            $table->unique('domain_name', 'domains_domain_name_unique');
            $table->index('account_id', 'domains_account_id_index');
            $table->index(['account_id', 'domain_name'], 'domains_account_domain_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
