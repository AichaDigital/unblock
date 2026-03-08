<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_simple_mode_user')->default(false)->after('is_admin');
        });

        // Backfill existing simple mode users
        DB::table('users')
            ->where('first_name', 'Simple')
            ->where('last_name', 'Unblock')
            ->where('is_admin', false)
            ->update(['is_simple_mode_user' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_simple_mode_user');
        });
    }
};
