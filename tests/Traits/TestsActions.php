<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Support\Facades\{DB, Queue};

trait TestsActions
{
    /**
     * Mock an action class with given return values
     */
    protected function mockAction(string $actionClass, array $returnValue): void
    {
        $this->mock($actionClass)
            ->shouldReceive('run')
            ->once()
            ->andReturn($returnValue);
    }

    /**
     * Assert that an action was queued
     */
    protected function assertActionQueued(string $actionClass): void
    {
        Queue::assertPushed($actionClass);
    }

    /**
     * Assert that an action was not dispatched
     */
    protected function assertActionNotDispatched(string $actionClass): void
    {
        Queue::assertNotPushed($actionClass);
    }

    /**
     * Set up WHMCS test database (only for remote WHMCS simulation)
     * Main application tables should use real migrations and factories
     */
    protected function setUpWhmcsTestDatabase(): void
    {
        // Configure WHMCS test database connection
        config(['database.connections.whmcs_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        // Create WHMCS tables for testing imports
        DB::connection('whmcs_test')->statement('CREATE TABLE IF NOT EXISTS tblclients (
            id INTEGER PRIMARY KEY,
            firstname VARCHAR(255),
            lastname VARCHAR(255),
            companyname VARCHAR(255),
            email VARCHAR(255),
            password VARCHAR(255),
            status VARCHAR(255)
        )');

        DB::connection('whmcs_test')->statement('CREATE TABLE IF NOT EXISTS tblhosting (
            id INTEGER PRIMARY KEY,
            userid INTEGER,
            domain VARCHAR(255),
            username VARCHAR(255),
            server VARCHAR(255),
            domainstatus VARCHAR(255)
        )');
    }

    protected function tearDownWhmcsTestDatabase(): void
    {
        DB::connection('whmcs_test')->statement('DROP TABLE IF EXISTS tblhosting');
        DB::connection('whmcs_test')->statement('DROP TABLE IF EXISTS tblclients');
    }
}
