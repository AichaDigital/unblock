<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('whmcs_id')->unique();
            $table->string('fqdn')->unique();
            $table->string('alias')->unique();
            $table->string('ip');
            $table->integer('port_ssh')->default(22);
            $table->text('hash')->nullable();
            $table->string('panel')->default('da');
            $table->string('admin')->default('admin');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('server_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosts');
    }
};
