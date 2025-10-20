<?php

use App\Actions\SendFirewallReportAction;
use App\Mail\LogNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\{Config, Log, Mail};
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\FirewallTestConstants as TC;

uses(MockeryPHPUnitIntegration::class);

beforeEach(function () {
    $this->action = new SendFirewallReportAction;

    // Fake mail and logging
    Mail::fake();
    Log::shouldReceive('error')->byDefault();
    Log::shouldReceive('debug')->byDefault();
    Log::shouldReceive('info')->byDefault();

    // Set admin email config
    Config::set('unblock.admin_email', 'admin@example.com');
});

test('sends report successfully', function () {
    // Arrange
    $user = User::factory()->admin()->create();
    $error = new \Exception('Test error');

    // Act
    $result = $this->action->handle(
        error: $error,
        userId: $user->id,
        hostId: 1,
        ip: TC::TEST_IP
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->and($result['success'])->toBeTrue();

    // Verify mail was queued
    Mail::assertQueued(LogNotificationMail::class, function ($mail) use ($user) {
        return $mail->hasTo('admin@example.com') &&
               $mail->user->is($user) &&
               isset($mail->report['error']) &&
               $mail->ip === TC::TEST_IP &&
               ! $mail->is_unblock;
    });
});

test('handles missing admin user', function () {
    // Arrange
    $error = new \Exception('Test error');

    // Act
    $result = $this->action->handle(
        error: $error,
        userId: 1,
        hostId: 1,
        ip: TC::TEST_IP
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->and($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('No admin user found');

    // Verify error was logged
    Log::shouldHaveReceived('error')
        ->with('No admin user found for error notification');

    // Verify no mail was queued
    Mail::assertNothingQueued();
});

test('handles mail sending failure', function () {
    // Arrange
    $user = User::factory()->admin()->create();
    $error = new \Exception('Test error');

    // Mock mail to throw exception
    Mail::shouldReceive('to')
        ->andThrow(new \Exception('Mail error'));

    // Act
    $result = $this->action->handle(
        error: $error,
        userId: $user->id,
        hostId: 1,
        ip: TC::TEST_IP
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->and($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('Mail error');

    // Verify error was logged
    Log::shouldHaveReceived('error')
        ->with('Failed to send firewall report', \Mockery::any());
});

test('handles null host id', function () {
    // Arrange
    $user = User::factory()->admin()->create();
    $error = new \Exception('Test error');

    // Act
    $result = $this->action->handle(
        error: $error,
        userId: $user->id,
        hostId: null,
        ip: TC::TEST_IP
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->and($result['success'])->toBeTrue();

    // Verify mail was queued
    Mail::assertQueued(LogNotificationMail::class);
});

test('email report includes modsecurity message and rule when present in logs (directadmin format - deprecated)', function () {// Arrange
    $user = User::factory()->admin()->create();
    $stub = require base_path('tests/stubs/directadmin_mod_security_da.php');
    $modSecLogTemplate = $stub['mod_security_da'];

    // Replace template constants with actual values
    $modSecLog = str_replace(
        ['{TC::TEST_DATE}', '{TC::BLOCKED_IP}', '{TC::HOSTNAME}', '{TC::ADMIN_USER}', '{TC::TEST_PASSWORD}'],
        ['2024-01-15', TC::BLOCKED_IP, TC::HOSTNAME, TC::ADMIN_USER, 'testpass123'],
        $modSecLogTemplate
    );

    // Simulate log as plain string (as the system currently receives it)
    $logs = [
        'MOD_SECURITY' => $modSecLog,
    ];
    $report = \App\Models\Report::factory()->create([
        'user_id' => $user->id,
        'logs' => $logs,
        'analysis' => ['was_blocked' => true],
        'ip' => TC::BLOCKED_IP,
    ]);

    // Act: Generate email
    $mailable = new LogNotificationMail(
        user: $user,
        report: [
            'logs' => $logs,
            'host' => 'test.example.com',
            'analysis' => ['was_blocked' => true],
        ],
        ip: $report->ip,
        is_unblock: true,
        report_uuid: (string) $report->id
    );
    $rendered = $mailable->render();

    // Save HTML for inspection
    file_put_contents(storage_path('logs/modsec_test_output.html'), $rendered);

    // Assert: Email should contain ModSecurity processed data
    expect($rendered)
        ->toContain('216.244.66.195')  // IP del cliente
        ->toContain('Custom WAF Rules: WEB CRAWLER/BAD BOT')  // Mensaje procesado
        ->toContain('1100000');  // Rule ID del JSON real
});

test('email report includes modsecurity details from nginx json format', function () {
    // Arrange
    $user = User::factory()->admin()->create();
    $stub = require base_path('tests/stubs/nginx_modsec_audit.php');
    $modSecLogTemplate = $stub['nginx_modsec_audit'];

    // Replace template constants with actual values
    $modSecLog = str_replace('{TARGET_IP}', TC::BLOCKED_IP, $modSecLogTemplate);

    // Simulate log as JSON string (as received from nginx modsec_audit.log)
    $logs = [
        'MODSEC_AUDIT' => $modSecLog,
    ];

    $report = \App\Models\Report::factory()->create([
        'user_id' => $user->id,
        'logs' => $logs,
        'analysis' => ['was_blocked' => true],
        'ip' => TC::BLOCKED_IP,
    ]);

    // Act: Generate email
    $mailable = new LogNotificationMail(
        user: $user,
        report: [
            'logs' => $logs,
            'host' => 'fontaneriafontcal.com',
            'analysis' => ['was_blocked' => true],
        ],
        ip: $report->ip,
        is_unblock: true,
        report_uuid: (string) $report->id
    );
    $rendered = $mailable->render();

    // Save HTML for inspection
    file_put_contents(storage_path('logs/modsec_test_output.html'), $rendered);

    // Assert: Email should contain ModSecurity details from JSON format
    expect($rendered)
        ->toContain('Host header is a numeric IP address')  // message field
        ->toContain('960017')  // ruleId field
        ->toContain('/wp-login.php')  // uri field
        ->toContain('POST')  // method field
        ->toContain('403');  // http_code field
});

test('reproduces real scenario where modsecurity logs are missing from email', function () {
    // Arrange: Simulate the real case from email
    $user = User::factory()->admin()->create(['first_name' => 'Abdelkarim', 'last_name' => 'Mateos Sanchez']);

    // Real CSF output from the email
    $realCsfOutput = <<<'CSF_OUTPUT'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter DENYIN           183      4   208 DROP       tcp  --  !lo    *       45.61.161.233        0.0.0.0/0            multiport dports 80,443
filter DENYIN           185      0     0 DROP       tcp  --  !lo    *       45.61.161.233        0.0.0.0/0            multiport dports 80,443

IPSET: No matches found for 45.61.161.233
CSF_OUTPUT;

    // Simulate logs structure as it comes from the system
    $logs = [
        'csf' => $realCsfOutput,
        'csf_specials' => '',
        'exim' => '',
        'dovecot' => '',
        'mod_security' => '', // Empty - this is the problem!
    ];

    $analysis = [
        'was_blocked' => true,
        'csf' => [
            'Table  Chain            num   pkts bytes target     prot opt in     out     source               destination',
            'filter DENYIN           183      4   208 DROP       tcp  --  !lo    *       45.61.161.233        0.0.0.0/0            multiport dports 80,443',
            'filter DENYIN           185      0     0 DROP       tcp  --  !lo    *       45.61.161.233        0.0.0.0/0            multiport dports 80,443',
            'IPSET: No matches found for 45.61.161.233',
        ],
    ];

    $report = \App\Models\Report::factory()->create([
        'user_id' => $user->id,
        'logs' => $logs,
        'analysis' => $analysis,
        'ip' => '45.61.161.233',
    ]);

    // Act: Generate email as the system does
    $mailable = new LogNotificationMail(
        user: $user,
        report: [
            'logs' => $logs,
            'host' => 'kvm456.elayudante.es',
            'analysis' => $analysis,
        ],
        ip: $report->ip,
        is_unblock: true,
        report_uuid: (string) $report->id
    );

    $rendered = $mailable->render();

    // Save HTML for inspection
    file_put_contents(storage_path('logs/modsec_test_output.html'), $rendered);

    // Assert: Current behavior (what we want to fix)
    expect($rendered)
        ->toContain('CSF')  // CSF logs appear
        ->toContain('DENYIN')  // CSF blocking patterns appear
        ->toContain('IP desbloqueada correctamente')  // IP was unblocked (partial text that should work)
        ->not->toContain('MOD_SECURITY')  // ModSecurity logs don't appear
        ->not->toContain('960017');  // No ModSecurity rule IDs

    // Debug info for investigation
    $logKeys = array_keys($logs);
    $nonEmptyLogs = array_filter($logs, fn ($log) => ! empty($log));

    expect($logKeys)->toContain('mod_security');  // Key exists
    expect($logs['mod_security'])->toBeEmpty();   // But content is empty
    expect($nonEmptyLogs)->toHaveKey('csf');      // CSF has content
    expect($nonEmptyLogs)->not->toHaveKey('mod_security');  // ModSecurity doesn't
});

test('simplified modsecurity command returns raw json for local processing', function () {
    // Arrange: Simulate what the simplified grep command should return
    $user = User::factory()->admin()->create();
    $stub = require base_path('tests/stubs/nginx_modsec_audit.php');
    $modSecLogTemplate = $stub['nginx_modsec_audit'];

    // This is what grep should return (raw JSON lines)
    $modSecRawOutput = str_replace('{TARGET_IP}', TC::BLOCKED_IP, $modSecLogTemplate);

    // Simulate logs structure with raw JSON string (as grep returns it)
    $logs = [
        'csf' => 'filter DENYIN 183 4 208 DROP tcp -- !lo * 192.0.2.123 0.0.0.0/0 multiport dports 80,443',
        'mod_security' => $modSecRawOutput, // Raw JSON lines from grep
    ];

    $report = \App\Models\Report::factory()->create([
        'user_id' => $user->id,
        'logs' => $logs,
        'analysis' => ['was_blocked' => true],
        'ip' => TC::BLOCKED_IP,
    ]);

    // Act: Generate email
    $mailable = new LogNotificationMail(
        user: $user,
        report: [
            'logs' => $logs,
            'host' => 'test.example.com',
            'analysis' => ['was_blocked' => true],
        ],
        ip: $report->ip,
        is_unblock: true,
        report_uuid: (string) $report->id
    );
    $rendered = $mailable->render();

    // Save HTML for inspection
    file_put_contents(storage_path('logs/modsec_test_output.html'), $rendered);

    // Assert: Now ModSecurity content should appear in email
    expect($rendered)
        ->toContain('ModSecurity')  // Section appears
        ->toContain('Host header is a numeric IP address')  // Message content appears
        ->toContain('960017')  // Rule ID appears
        ->toContain('/wp-login.php')  // URI appears
        ->toContain('POST');  // Method appears

    // Verify the simplified approach works
    expect($logs['mod_security'])
        ->not->toBeEmpty()
        ->toContain('client_ip')
        ->toContain('960017')
        ->toContain('Host header is a numeric IP address');
});

test('processes real modsecurity json from production server', function () {
    // Arrange: Real JSON from production server
    $user = User::factory()->admin()->create();
    $realModSecJson = '{"transaction":{"client_ip":"172.86.122.32","time_stamp":"Fri Jun 13 00:29:29 2025","server_id":"a8616bf70d357408b89b11703d8608db52fc1e56","client_port":57555,"host_ip":"5.135.93.75","host_port":443,"unique_id":"174976736949.880613","request":{"method":"GET","http_version":1.1,"uri":"/wp-admin/css/","body":"","headers":{"Host":"pavimentosencantabria.es","Connection":"keep-alive","Accept-Encoding":"gzip, deflate","Accept":"*/*","User-Agent":"Mozlila/5.0 (Linux; Android 7.0; SM-G892A Bulid/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/60.0.3112.107 Moblie Safari/537.36","Cookie":"wpr_guest_token=240f6a80b1ea52aaa432b3d063697444d2384ec8f9f97cd7aefe4d0caeeef65e"}},"response":{"http_code":403,"headers":{"Server":"nginx","Date":"Thu, 12 Jun 2025 22:29:29 GMT","Content-Length":"199","Content-Type":"text/html; charset=iso-8859-1","Connection":"keep-alive"}},"producer":{"modsecurity":"ModSecurity v3.0.14 (Linux)","connector":"ModSecurity-nginx v1.0.4","secrules_engine":"Enabled","components":["OWASP_CRS/4.15.0\""]},"messages":[{"message":"Found User-Agent associated with security scanner","details":{"match":"Matched \\"Operator `PmFromFile\' with parameter `scanners-user-agents.data\' against variable `REQUEST_HEADERS:User-Agent\' (Value: `Mozlila/5.0 (Linux; Android 7.0; SM-G892A Bulid/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) V (52 characters omitted)\' )","reference":"o0,7v137,152","ruleId":"913100","file":"/etc/modsecurity.d/REQUEST-913-SCANNER-DETECTION.conf","lineNumber":"38","data":"Matched Data: Mozlila found within REQUEST_HEADERS:User-Agent: Mozlila/5.0 (Linux; Android 7.0; SM-G892A Bulid/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/60.0.3112.107 Moblie Safari/537.36","severity":"2","ver":"OWASP_CRS/4.15.0","rev":"","tags":["application-multi","language-multi","platform-multi","attack-reputation-scanner","paranoia-level/1","OWASP_CRS","OWASP_CRS/SCANNER-DETECTION","capec/1000/118/224/541/310","PCI/6.5.10"],"maturity":"0","accuracy":"0"}}]}}';

    $logs = [
        'csf' => 'filter DENYIN 183 4 208 DROP tcp -- !lo * 172.86.122.32',
        'mod_security' => $realModSecJson,
    ];

    $report = \App\Models\Report::factory()->create([
        'user_id' => $user->id,
        'logs' => $logs,
        'analysis' => ['was_blocked' => true],
        'ip' => '172.86.122.32',
    ]);

    // Act: Generate email
    $mailable = new LogNotificationMail(
        user: $user,
        report: [
            'logs' => $logs,
            'host' => 'pavimentosencantabria.es',
            'analysis' => ['was_blocked' => true],
        ],
        ip: $report->ip,
        is_unblock: true,
        report_uuid: (string) $report->id
    );
    $rendered = $mailable->render();

    // Guardar el HTML para inspección
    file_put_contents(storage_path('logs/modsec_test_output.html'), $rendered);

    // Assert: All important fields from the real JSON should appear
    expect($rendered)
        ->toContain('ModSecurity')  // Section header
        ->toContain('172.86.122.32')  // client_ip
        ->toContain('/wp-admin/css/')  // uri
        ->toContain('GET')  // method
        ->toContain('403')  // http_code
        ->toContain('913100')  // ruleId (highlighted)
        ->toContain('Found User-Agent associated with security scanner');  // message
});
