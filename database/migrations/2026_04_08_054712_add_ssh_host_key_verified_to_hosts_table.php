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
            $table->boolean('ssh_host_key_verified')->default(false)->after('hosting_manual');
            $table->timestamp('ssh_host_key_verified_at')->nullable()->after('ssh_host_key_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->dropColumn(['ssh_host_key_verified', 'ssh_host_key_verified_at']);
        });
    }
};
