<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhanceMagicLinksSecurity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Solo ejecutar si la tabla magic_links existe
        if (Schema::hasTable('magic_links')) {
            Schema::table('magic_links', function (Blueprint $table) {
                // IP del dispositivo que solicitó el magic link
                $table->string('source_ip', 45)->nullable()->after('access_code');

                // IP del dispositivo que usó el magic link (para validar que sea el mismo)
                $table->string('used_ip', 45)->nullable()->after('source_ip');

                // Timestamp de expiración absoluta (independiente de visitas)
                $table->timestamp('expires_at')->nullable()->after('used_ip');

                // Marca si el magic link fue usado exitosamente y debe invalidarse
                $table->boolean('is_consumed')->default(false)->after('expires_at');

                // Timestamp cuando se usó exitosamente por primera vez
                $table->timestamp('consumed_at')->nullable()->after('is_consumed');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Solo ejecutar si la tabla magic_links existe
        if (Schema::hasTable('magic_links')) {
            Schema::table('magic_links', function (Blueprint $table) {
                $table->dropColumn([
                    'source_ip',
                    'used_ip',
                    'expires_at',
                    'is_consumed',
                    'consumed_at'
                ]);
            });
        }
    }
}