<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\ActionAudit;
use App\Traits\AuditLoginTrait;
use Illuminate\Support\Facades\Log;

test('audit creates ActionAudit record with correct data', function () {
    $auditor = new class
    {
        use AuditLoginTrait;
    };

    $auditor->audit('10.0.0.1', 'access', 'Test audit message');

    $this->assertDatabaseHas('action_audits', [
        'ip' => '10.0.0.1',
        'action' => AuditAction::Access->value,
        'message' => 'Test audit message',
        'is_fail' => false,
    ]);
});

test('audit with is_fail true records failure', function () {
    $auditor = new class
    {
        use AuditLoginTrait;
    };

    $auditor->audit('192.168.1.100', 'email', 'Failed login attempt', true);

    $this->assertDatabaseHas('action_audits', [
        'ip' => '192.168.1.100',
        'action' => AuditAction::Email->value,
        'message' => 'Failed login attempt',
        'is_fail' => true,
    ]);
});

test('audit creates record for each audit action type', function (string $actionKey, AuditAction $expectedEnum) {
    $auditor = new class
    {
        use AuditLoginTrait;
    };

    $auditor->audit('10.0.0.1', $actionKey, "Test {$actionKey}");

    $this->assertDatabaseHas('action_audits', [
        'ip' => '10.0.0.1',
        'action' => $expectedEnum->value,
        'message' => "Test {$actionKey}",
    ]);
})->with([
    'email' => ['email', AuditAction::Email],
    'access' => ['access', AuditAction::Access],
    'clean' => ['clean', AuditAction::Clean],
    'success' => ['success', AuditAction::Success],
    'too_many_requests' => ['too_many_requests', AuditAction::TooManyRequests],
    'otp_login' => ['otp_login', AuditAction::OtpLogin],
]);

test('audit with null message stores null', function () {
    $auditor = new class
    {
        use AuditLoginTrait;
    };

    $auditor->audit('10.0.0.1', 'success', null);

    $this->assertDatabaseHas('action_audits', [
        'ip' => '10.0.0.1',
        'action' => AuditAction::Success->value,
        'message' => null,
        'is_fail' => false,
    ]);
});

test('audit catches exception and logs error gracefully', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(function (string $message) {
            return strlen($message) > 0;
        });

    $auditor = new class
    {
        use AuditLoginTrait;
    };

    // Pass an invalid action key that will cause a match error in AuditAction::getValueByKey
    $auditor->audit('10.0.0.1', 'invalid_action_key', 'This should fail');

    // Verify no record was created
    expect(ActionAudit::count())->toBe(0);
});
