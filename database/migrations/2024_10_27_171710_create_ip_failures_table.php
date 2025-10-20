<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_failures', function (Blueprint $table) {
            $table->id();
            $table->string('ip')->unique();
            $table->integer('attempts')->default(0);
            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_failures');
    }
};
