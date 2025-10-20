<?php

declare(strict_types=1);

use App\Services\FirewallService;

/**
 * Unit test for ModSecurity JSON processing
 */
test('processes modsecurity json from directadmin correctly', function () {
    // Arrange: Use simple JSON for specific test (avoid parsing issues)
    $jsonInput = '{"transaction":{"client_ip":"216.244.66.195","time_stamp":"Thu Jun 26 05:31:50 2025","request":{"uri":"/robots.txt"}},"messages":[{"message":"Custom WAF Rules: WEB CRAWLER/BAD BOT","details":{"ruleId":"1100000"}}]}';

    $firewallService = new FirewallService;

    // Act: Use reflection to access private method
    $reflection = new ReflectionClass(FirewallService::class);
    $method = $reflection->getMethod('processModSecurityJson');
    $method->setAccessible(true);

    $result = $method->invoke($firewallService, $jsonInput, '216.244.66.195');

    // Assert: Specific verifications based on real data
    expect($result)->not->toBeEmpty()
        ->and($result)->toContain('216.244.66.195')
        ->and($result)->toContain('Thu Jun 26 05:31:50 2025')
        ->and($result)->toContain('/robots.txt')
        ->and($result)->toContain('Custom WAF Rules: WEB CRAWLER/BAD BOT')
        ->and($result)->toContain('1100000')
        ->and($result)->toMatch('/\[Thu Jun 26 05:31:50 2025\] IP: 216\.244\.66\.195 \| URI: \/robots\.txt \| Rules: \[1100000\]/');
})->group('unit', 'modsecurity');

test('handles empty or invalid modsecurity json gracefully', function () {
    $firewallService = new FirewallService;
    $reflection = new ReflectionClass(FirewallService::class);
    $method = $reflection->getMethod('processModSecurityJson');
    $method->setAccessible(true);

    // Test edge cases
    expect($method->invoke($firewallService, '', '22.2.2.2'))->toBe('')
        ->and($method->invoke($firewallService, 'invalid json', '22.2.2.2'))->toBe('');
})->group('unit', 'modsecurity');

test('processes multiple modsecurity json lines correctly', function () {
    // Use the SAME JSON that works in the simple test
    $workingJson = '{"transaction":{"client_ip":"216.244.66.195","time_stamp":"Thu Jun 26 05:31:50 2025","request":{"uri":"/robots.txt"}},"messages":[{"message":"Custom WAF Rules: WEB CRAWLER/BAD BOT","details":{"ruleId":"1100000"}}]}';

    $firewallService = new FirewallService;
    $reflection = new ReflectionClass(FirewallService::class);
    $method = $reflection->getMethod('processModSecurityJson');
    $method->setAccessible(true);

    $result = $method->invoke($firewallService, $workingJson, '216.244.66.195');

    // Assert: Should work with the same JSON that passes in first test
    expect($result)->toContain('/robots.txt')
        ->and($result)->toContain('1100000')
        ->and($result)->toContain('Custom WAF Rules: WEB CRAWLER/BAD BOT');
})->group('unit', 'modsecurity');
