<?php

declare(strict_types=1);

use App\Actions\SimpleUnblockAction;
use App\Jobs\ProcessSimpleUnblockJob;
use App\Models\Host;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Queue::fake();

    Host::factory()->count(3)->create();
});

test('action normalizes domain to lowercase', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'EXAMPLE.COM',
        email: 'test@example.com'
    );

    Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) {
        return $job->domain === 'example.com';
    });
});

test('action removes www prefix from domain', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'www.example.com',
        email: 'test@example.com'
    );

    Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) {
        return $job->domain === 'example.com';
    });
});

test('action validates domain format', function () {
    expect(fn () => SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'invalid domain!',
        email: 'test@example.com'
    ))->toThrow(\InvalidArgumentException::class);
});

test('action dispatches job for each host', function () {
    $hostsCount = Host::count();

    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    Queue::assertPushed(ProcessSimpleUnblockJob::class, $hostsCount);
});

test('action dispatches jobs with correct parameters', function () {
    $host = Host::first();

    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) use ($host) {
        return $job->ip === '192.168.1.1'
            && $job->domain === 'example.com'
            && $job->email === 'test@example.com'
            && $job->hostId === $host->id;
    });
});

test('action logs activity', function () {
    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    assertDatabaseHas('activity_log', [
        'description' => 'simple_unblock_request',
    ]);
});

test('action handles no hosts gracefully', function () {
    Host::query()->delete();

    SimpleUnblockAction::run(
        ip: '192.168.1.1',
        domain: 'example.com',
        email: 'test@example.com'
    );

    Queue::assertNothingPushed();
});

test('action handles multiple domain formats', function () {
    $testCases = [
        ['input' => 'example.com', 'expected' => 'example.com'],
        ['input' => 'EXAMPLE.COM', 'expected' => 'example.com'],
        ['input' => 'www.example.com', 'expected' => 'example.com'],
        ['input' => 'WWW.EXAMPLE.COM', 'expected' => 'example.com'],
        ['input' => 'sub.example.com', 'expected' => 'sub.example.com'],
    ];

    foreach ($testCases as $testCase) {
        Queue::fake();

        SimpleUnblockAction::run(
            ip: '192.168.1.1',
            domain: $testCase['input'],
            email: 'test@example.com'
        );

        Queue::assertPushed(ProcessSimpleUnblockJob::class, function ($job) use ($testCase) {
            return $job->domain === $testCase['expected'];
        });
    }
});

test('action rejects invalid domain formats', function () {
    $invalidDomains = [
        'invalid',
        'invalid domain',
        '192.168.1.1',
        'http://example.com',
        'example',
    ];

    foreach ($invalidDomains as $invalidDomain) {
        expect(fn () => SimpleUnblockAction::run(
            ip: '192.168.1.1',
            domain: $invalidDomain,
            email: 'test@example.com'
        ))->toThrow(\InvalidArgumentException::class);
    }
});
