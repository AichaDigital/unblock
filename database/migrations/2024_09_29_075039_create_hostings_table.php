<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hostings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('host_id');
            $table->string('domain')->unique();
            $table->string('username');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostings');
    }
};
