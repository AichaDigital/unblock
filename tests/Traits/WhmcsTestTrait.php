<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Support\Facades\{Config, DB, Schema};

trait WhmcsTestTrait
{
    protected function setUpWhmcsDatabase(): void
    {
        // Configure WHMCS connection to use in-memory SQLite
        Config::set('database.connections.whmcs', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Eliminar tablas si existen
        $this->tearDownWhmcsDatabase();

        // Crear tablas necesarias para WHMCS
        Schema::connection('whmcs')->create('tblclients', function ($table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('companyname')->nullable();
            $table->string('email');
            $table->string('password');
            $table->string('status');
            $table->timestamps();
        });

        Schema::connection('whmcs')->create('tblhosting', function ($table) {
            $table->id();
            $table->unsignedBigInteger('userid');
            $table->string('domain');
            $table->string('username');
            $table->string('server');
            $table->string('domainstatus');
            $table->timestamps();
        });
    }

    protected function tearDownWhmcsDatabase(): void
    {
        Schema::connection('whmcs')->dropIfExists('tblhosting');
        Schema::connection('whmcs')->dropIfExists('tblclients');
    }

    protected function createWhmcsClient(array $data): void
    {
        DB::connection('whmcs')->table('tblclients')->insert($data);
    }

    protected function createWhmcsHosting(array $data): void
    {
        DB::connection('whmcs')->table('tblhosting')->insert($data);
    }
}
