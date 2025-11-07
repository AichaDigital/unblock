<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\DomainLogsSearchResult;

test('DTO can be constructed with found status', function () {
    $result = new DomainLogsSearchResult(
        found: true,
        matchingLogs: ['log line 1', 'log line 2'],
        searchedPaths: ['/var/log/apache', '/var/log/nginx']
    );

    expect($result->found)->toBeTrue()
        ->and($result->matchingLogs)->toBe(['log line 1', 'log line 2'])
        ->and($result->searchedPaths)->toBe(['/var/log/apache', '/var/log/nginx']);
});

test('DTO can be constructed with not found status', function () {
    $result = new DomainLogsSearchResult(
        found: false,
        matchingLogs: [],
        searchedPaths: ['/var/log/apache']
    );

    expect($result->found)->toBeFalse()
        ->and($result->matchingLogs)->toBeEmpty()
        ->and($result->searchedPaths)->toBe(['/var/log/apache']);
});

test('DTO has found factory method', function () {
    $logs = ['Apache log 1', 'Nginx log 1'];
    $paths = ['/var/log/apache', '/var/log/nginx'];

    $result = DomainLogsSearchResult::found($logs, $paths);

    expect($result->found)->toBeTrue()
        ->and($result->matchingLogs)->toBe($logs)
        ->and($result->searchedPaths)->toBe($paths);
});

test('DTO has notFound factory method', function () {
    $paths = ['/var/log/apache', '/var/log/exim'];

    $result = DomainLogsSearchResult::notFound($paths);

    expect($result->found)->toBeFalse()
        ->and($result->matchingLogs)->toBeEmpty()
        ->and($result->searchedPaths)->toBe($paths);
});

test('DTO is immutable', function () {
    $result = DomainLogsSearchResult::found(['log1'], ['/path']);

    $reflection = new ReflectionClass($result);

    expect($reflection->isReadOnly())->toBeTrue();
});

test('DTO with empty matching logs and found true is valid', function () {
    $result = new DomainLogsSearchResult(
        found: true,
        matchingLogs: [],
        searchedPaths: ['/var/log/apache']
    );

    expect($result->found)->toBeTrue()
        ->and($result->matchingLogs)->toBeEmpty();
});

test('DTO handles multiple searched paths correctly', function () {
    $paths = [
        '/var/log/apache2/access.log',
        '/var/log/apache2/error.log',
        '/var/log/nginx/access.log',
        '/var/log/nginx/error.log',
        '/var/log/exim/mainlog',
    ];

    $result = DomainLogsSearchResult::notFound($paths);

    expect($result->searchedPaths)->toHaveCount(5)
        ->and($result->searchedPaths)->toBe($paths);
});

test('DTO handles empty searched paths', function () {
    $result = DomainLogsSearchResult::notFound([]);

    expect($result->searchedPaths)->toBeEmpty()
        ->and($result->found)->toBeFalse();
});

test('DTO preserves matching logs order', function () {
    $logs = [
        '2023-01-01 First log',
        '2023-01-02 Second log',
        '2023-01-03 Third log',
    ];

    $result = DomainLogsSearchResult::found($logs, ['/path']);

    expect($result->matchingLogs)->toBe($logs)
        ->and($result->matchingLogs[0])->toBe('2023-01-01 First log')
        ->and($result->matchingLogs[2])->toBe('2023-01-03 Third log');
});
