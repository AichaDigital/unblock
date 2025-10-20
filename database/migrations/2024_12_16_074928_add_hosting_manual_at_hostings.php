<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hostings', function (Blueprint $table) {
            $table->boolean('hosting_manual')->default(false);
        });

        Schema::table('hosts', function (Blueprint $table) {
            $table->boolean('hosting_manual')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('hostings', function (Blueprint $table) {
            $table->dropColumn('hosting_manual');
        });
        Schema::table('hosts', function (Blueprint $table) {
            $table->dropColumn('hosting_manual');
        });
    }
};
