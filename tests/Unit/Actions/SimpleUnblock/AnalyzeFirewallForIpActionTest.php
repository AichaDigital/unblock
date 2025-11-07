<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\AnalyzeFirewallForIpAction;
use App\Enums\PanelType;
use App\Models\Host;
use App\Services\Firewall\{FirewallAnalysisResult, FirewallAnalyzerFactory, FirewallAnalyzerInterface};
use App\Services\{SshConnectionManager, SshSession};

beforeEach(function () {
    $this->host = Host::factory()->create(['panel' => PanelType::DIRECTADMIN]);
});

test('action analyzes firewall status for IP on host', function () {
    $ip = '192.168.1.100';

    // Mock SSH session
    $sshSession = Mockery::mock(SshSession::class);
    $sshSession->shouldReceive('cleanup')->once();

    // Mock SSH connection manager
    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->shouldReceive('createSession')
        ->with($this->host)
        ->once()
        ->andReturn($sshSession);

    // Mock analyzer
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['IP blocked'],
        analysis: ['source' => 'CSF']
    );

    $analyzer = Mockery::mock(FirewallAnalyzerInterface::class);
    $analyzer->shouldReceive('analyze')
        ->with($ip, $sshSession)
        ->once()
        ->andReturn($analysisResult);

    // Mock factory
    $factory = Mockery::mock(FirewallAnalyzerFactory::class);
    $factory->shouldReceive('createForHost')
        ->with($this->host)
        ->once()
        ->andReturn($analyzer);

    app()->instance(FirewallAnalyzerFactory::class, $factory);

    // Execute action
    $action = new AnalyzeFirewallForIpAction($sshManager);
    $result = $action->handle($ip, $this->host);

    expect($result)->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->isBlocked())->toBeTrue();
});

test('action creates SSH session for host', function () {
    $ip = '10.0.0.50';

    $sshSession = Mockery::mock(SshSession::class);
    $sshSession->shouldReceive('cleanup')->once();

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->shouldReceive('createSession')
        ->with($this->host)
        ->once()
        ->andReturn($sshSession);

    $analysisResult = new FirewallAnalysisResult(
        blocked: false,
        logs: [],
        analysis: []
    );

    $analyzer = Mockery::mock(FirewallAnalyzerInterface::class);
    $analyzer->shouldReceive('analyze')->once()->andReturn($analysisResult);

    $factory = Mockery::mock(FirewallAnalyzerFactory::class);
    $factory->shouldReceive('createForHost')->once()->andReturn($analyzer);

    app()->instance(FirewallAnalyzerFactory::class, $factory);

    $action = new AnalyzeFirewallForIpAction($sshManager);
    $action->handle($ip, $this->host);

    // SSH Manager was called to create session
    expect(true)->toBeTrue();
});

test('action gets correct analyzer for host panel type', function () {
    $ip = '203.0.113.42';

    $sshSession = Mockery::mock(SshSession::class);
    $sshSession->shouldReceive('cleanup')->once();

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->shouldReceive('createSession')->once()->andReturn($sshSession);

    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['Block info'],
        analysis: ['panel' => 'DirectAdmin']
    );

    $analyzer = Mockery::mock(FirewallAnalyzerInterface::class);
    $analyzer->shouldReceive('analyze')->once()->andReturn($analysisResult);

    $factory = Mockery::mock(FirewallAnalyzerFactory::class);
    $factory->shouldReceive('createForHost')
        ->with(Mockery::on(fn ($h) => $h->panel === PanelType::DIRECTADMIN))
        ->once()
        ->andReturn($analyzer);

    app()->instance(FirewallAnalyzerFactory::class, $factory);

    $action = new AnalyzeFirewallForIpAction($sshManager);
    $result = $action->handle($ip, $this->host);

    expect($result->isBlocked())->toBeTrue();
});

test('action cleans up SSH session after analysis', function () {
    $ip = '172.16.0.5';

    $sshSession = Mockery::mock(SshSession::class);
    $sshSession->shouldReceive('cleanup')
        ->once()
        ->andReturnNull();

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->shouldReceive('createSession')->once()->andReturn($sshSession);

    $analysisResult = new FirewallAnalysisResult(
        blocked: false,
        logs: [],
        analysis: []
    );

    $analyzer = Mockery::mock(FirewallAnalyzerInterface::class);
    $analyzer->shouldReceive('analyze')->once()->andReturn($analysisResult);

    $factory = Mockery::mock(FirewallAnalyzerFactory::class);
    $factory->shouldReceive('createForHost')->once()->andReturn($analyzer);

    app()->instance(FirewallAnalyzerFactory::class, $factory);

    $action = new AnalyzeFirewallForIpAction($sshManager);
    $action->handle($ip, $this->host);

    // Cleanup was called (verified by mock)
    expect(true)->toBeTrue();
});

test('action cleans up SSH session even when analysis throws exception', function () {
    $ip = '192.0.2.1';

    $sshSession = Mockery::mock(SshSession::class);
    $sshSession->shouldReceive('cleanup')
        ->once()
        ->andReturnNull();

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->shouldReceive('createSession')->once()->andReturn($sshSession);

    $analyzer = Mockery::mock(FirewallAnalyzerInterface::class);
    $analyzer->shouldReceive('analyze')
        ->once()
        ->andThrow(new RuntimeException('Analysis failed'));

    $factory = Mockery::mock(FirewallAnalyzerFactory::class);
    $factory->shouldReceive('createForHost')->once()->andReturn($analyzer);

    app()->instance(FirewallAnalyzerFactory::class, $factory);

    $action = new AnalyzeFirewallForIpAction($sshManager);

    expect(fn () => $action->handle($ip, $this->host))
        ->toThrow(RuntimeException::class, 'Analysis failed');

    // Cleanup was called even though exception was thrown (verified by mock)
});

test('action returns correct analysis result structure', function () {
    $ip = '198.51.100.10';

    $sshSession = Mockery::mock(SshSession::class);
    $sshSession->shouldReceive('cleanup')->once();

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->shouldReceive('createSession')->once()->andReturn($sshSession);

    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['CSF log 1', 'CSF log 2', 'Exim log 1', 'Dovecot log 1'],
        analysis: [
            'csf' => true,
            'exim' => true,
            'dovecot' => true,
            'sources' => ['CSF', 'Exim', 'Dovecot'],
        ]
    );

    $analyzer = Mockery::mock(FirewallAnalyzerInterface::class);
    $analyzer->shouldReceive('analyze')->once()->andReturn($analysisResult);

    $factory = Mockery::mock(FirewallAnalyzerFactory::class);
    $factory->shouldReceive('createForHost')->once()->andReturn($analyzer);

    app()->instance(FirewallAnalyzerFactory::class, $factory);

    $action = new AnalyzeFirewallForIpAction($sshManager);
    $result = $action->handle($ip, $this->host);

    expect($result)->toBeInstanceOf(FirewallAnalysisResult::class)
        ->and($result->isBlocked())->toBeTrue()
        ->and($result->getLogs())->toHaveCount(4)
        ->and($result->getAnalysis())->toHaveKey('sources');
});

test('action logs analysis start and completion', function () {
    $ip = '10.20.30.40';

    Log::spy();

    $sshSession = Mockery::mock(SshSession::class);
    $sshSession->shouldReceive('cleanup')->once();

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->shouldReceive('createSession')->once()->andReturn($sshSession);

    $analysisResult = new FirewallAnalysisResult(
        blocked: false,
        logs: [],
        analysis: []
    );

    $analyzer = Mockery::mock(FirewallAnalyzerInterface::class);
    $analyzer->shouldReceive('analyze')->once()->andReturn($analysisResult);

    $factory = Mockery::mock(FirewallAnalyzerFactory::class);
    $factory->shouldReceive('createForHost')->once()->andReturn($analyzer);

    app()->instance(FirewallAnalyzerFactory::class, $factory);

    $action = new AnalyzeFirewallForIpAction($sshManager);
    $action->handle($ip, $this->host);

    Log::shouldHaveReceived('info')
        ->with('Starting firewall analysis', Mockery::type('array'))
        ->once();

    Log::shouldHaveReceived('info')
        ->with('Firewall analysis completed', Mockery::type('array'))
        ->once();
});

test('action works with cPanel hosts', function () {
    $cpanelHost = Host::factory()->create(['panel' => PanelType::CPANEL]);
    $ip = '203.0.113.100';

    $sshSession = Mockery::mock(SshSession::class);
    $sshSession->shouldReceive('cleanup')->once();

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->shouldReceive('createSession')
        ->with($cpanelHost)
        ->once()
        ->andReturn($sshSession);

    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['cPanel CSF block'],
        analysis: ['panel' => 'cPanel']
    );

    $analyzer = Mockery::mock(FirewallAnalyzerInterface::class);
    $analyzer->shouldReceive('analyze')->once()->andReturn($analysisResult);

    $factory = Mockery::mock(FirewallAnalyzerFactory::class);
    $factory->shouldReceive('createForHost')
        ->with(Mockery::on(fn ($h) => $h->panel === PanelType::CPANEL))
        ->once()
        ->andReturn($analyzer);

    app()->instance(FirewallAnalyzerFactory::class, $factory);

    $action = new AnalyzeFirewallForIpAction($sshManager);
    $result = $action->handle($ip, $cpanelHost);

    expect($result->isBlocked())->toBeTrue();
});
