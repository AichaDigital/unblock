<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock Redis facade
    Redis::shouldReceive('connection')->andReturnSelf();
});

test('queue monitor command shows sync queue status', function () {
    config()->set('queue.default', 'sync');

    $this->artisan('develop:queue-monitor')
        ->expectsOutput('📊 Queue Status - Connection: sync')
        ->expectsOutput('🔄 SYNC Queue - Jobs execute immediately')
        ->assertSuccessful();
});

test('queue monitor command shows redis queue status with empty queues', function () {
    config()->set('queue.default', 'redis');

    Redis::shouldReceive('llen')
        ->with('queues:default')
        ->andReturn(0);

    Redis::shouldReceive('llen')
        ->with('queues:emails')
        ->andReturn(0);

    Redis::shouldReceive('llen')
        ->with('queues:reports')
        ->andReturn(0);

    Redis::shouldReceive('llen')
        ->with('queues:failed')
        ->andReturn(0);

    Redis::shouldReceive('zcard')
        ->with('queues:delayed')
        ->andReturn(0);

    $this->artisan('develop:queue-monitor')
        ->expectsOutput('📊 Queue Status - Connection: redis')
        ->expectsOutput('✅ All queues are empty')
        ->assertSuccessful();
});

test('queue monitor command shows redis queue status with pending jobs', function () {
    config()->set('queue.default', 'redis');

    Redis::shouldReceive('llen')
        ->with('queues:default')
        ->andReturn(5);

    Redis::shouldReceive('llen')
        ->with('queues:emails')
        ->andReturn(3);

    Redis::shouldReceive('llen')
        ->with('queues:reports')
        ->andReturn(2);

    Redis::shouldReceive('llen')
        ->with('queues:failed')
        ->andReturn(1);

    Redis::shouldReceive('zcard')
        ->with('queues:delayed')
        ->andReturn(0);

    $this->artisan('develop:queue-monitor')
        ->expectsOutput('⚠️  Total pending jobs: 10')
        ->assertSuccessful();
});

test('queue monitor command handles redis connection failure gracefully', function () {
    config()->set('queue.default', 'redis');

    // Mock Redis to throw exception when llen is called
    Redis::shouldReceive('connection')->andReturnSelf();
    Redis::shouldReceive('llen')
        ->andThrow(new \Exception('Connection refused'));

    $this->artisan('develop:queue-monitor')
        ->expectsOutput('❌ Cannot connect to Redis: Connection refused')
        ->assertSuccessful();
});

test('queue monitor command accepts custom connection option', function () {
    config()->set('queue.default', 'sync');

    $this->artisan('develop:queue-monitor', ['--connection' => 'redis'])
        ->expectsOutput('📊 Queue Status - Connection: redis')
        ->assertSuccessful();
});
