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
        Schema::table('hosts', function (Blueprint $table) {
            // Ensure whmcs_server_id column exists
            if (!Schema::hasColumn('hosts', 'whmcs_server_id')) {
                if (Schema::hasColumn('hosts', 'server_id')) {
                    // Rename server_id to whmcs_server_id if it exists
                    $table->renameColumn('server_id', 'whmcs_server_id');
                } else {
                    // Add whmcs_server_id column if neither exists
                    $table->unsignedBigInteger('whmcs_server_id')->nullable();
                }
            }
            
            // Ensure hosting_manual column exists
            if (!Schema::hasColumn('hosts', 'hosting_manual')) {
                $table->boolean('hosting_manual')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            if (Schema::hasColumn('hosts', 'whmcs_server_id')) {
                $table->renameColumn('whmcs_server_id', 'server_id');
            }
            if (Schema::hasColumn('hosts', 'hosting_manual')) {
                $table->dropColumn('hosting_manual');
            }
        });
    }
};
