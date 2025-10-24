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
        Schema::table('ip_reputation', function (Blueprint $table) {
            // Geographic data from MaxMind GeoIP2
            $table->string('country_code', 2)->nullable()->index()->after('subnet');
            $table->string('country_name', 100)->nullable()->after('country_code');
            $table->string('city', 100)->nullable()->after('country_name');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('timezone', 50)->nullable()->after('longitude');
            $table->string('continent', 2)->nullable()->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ip_reputation', function (Blueprint $table) {
            $table->dropColumn([
                'country_code',
                'country_name',
                'city',
                'postal_code',
                'latitude',
                'longitude',
                'timezone',
                'continent',
            ]);
        });
    }
};
