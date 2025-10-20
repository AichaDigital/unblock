<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('action_audits', function (Blueprint $table) {
            $table->id();
            $table->string('ip')->index();
            $table->integer('action')->index();
            $table->boolean('is_fail')->default(false)->index();
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_audits');
    }
};
