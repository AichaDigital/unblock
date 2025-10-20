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
        Schema::create('user_hosting_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('hosting_id')->constrained()->onDelete('cascade');
            $table->string('permission_type')->default('read');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['user_id', 'hosting_id']);
            $table->index(['hosting_id', 'is_active']);

            // Evitar duplicados
            $table->unique(['user_id', 'hosting_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_hosting_permissions');
    }
};
