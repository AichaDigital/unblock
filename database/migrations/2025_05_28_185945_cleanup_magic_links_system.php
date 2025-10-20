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
        // Eliminar tabla magic_links ya que hemos migrado a Spatie OTP
        if (Schema::hasTable('magic_links')) {
            Schema::dropIfExists('magic_links');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recrear tabla magic_links en caso de rollback
        Schema::create('magic_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 255)->unique();
            $table->morphs('authenticatable');
            $table->string('email')->nullable();
            $table->string('url', 500);
            $table->integer('max_visits')->default(1);
            $table->integer('num_visits')->default(0);
            $table->boolean('is_consumed')->default(false);
            $table->string('source_ip')->nullable();
            $table->string('used_ip')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->index(['token', 'email']);
            $table->index('expires_at');
            $table->index('available_at');
        });
    }
};
