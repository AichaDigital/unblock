<?php

declare(strict_types=1);

use App\Livewire\SimpleUnblockForm;
use App\Models\{Account, Domain, Host, User};
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    // Enable simple mode
    config()->set('unblock.simple_mode.enabled', true);
    config()->set('unblock.simple_mode.cooldown_seconds', 30);

    // Create test data
    $this->host = Host::factory()->create(['panel' => 'cpanel']);
    $this->account = Account::factory()->create([
        'host_id' => $this->host->id,
        'username' => 'testuser',
        'domain' => 'example.com',
    ]);
    $this->domain = Domain::factory()->create([
        'account_id' => $this->account->id,
        'domain_name' => 'example.com',
        'type' => 'primary',
    ]);

    // Create simple mode user
    $this->user = User::create([
        'first_name' => 'Simple',
        'last_name' => 'Unblock',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'is_admin' => false,
    ]);

    // Authenticate as simple mode user
    session()->put('otp_request_email', 'test@example.com');
    $this->actingAs($this->user);

    Queue::fake();
});

test('simple unblock form shows cooldown after submission', function () {
    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->call('processUnblock')
        ->assertSet('cooldownActive', true)
        ->assertSet('cooldownSeconds', 30)
        ->assertSee(__('simple_unblock.request_submitted'));
});

test('simple unblock form prevents submission during cooldown', function () {
    // First submission
    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->call('processUnblock')
        ->assertSet('cooldownActive', true);

    // Second submission should be blocked
    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.2')
        ->set('domain', 'example.com')
        ->call('processUnblock')
        ->assertSet('message', __('simple_unblock.cooldown_active', ['seconds' => 30]));
});

test('simple unblock form clears cooldown after expiration', function () {
    // Set cooldown in session manually to simulate expired cooldown
    session()->put('simple_unblock_cooldown', now()->subSeconds(5)->timestamp);

    Livewire::test(SimpleUnblockForm::class)
        ->call('checkCooldown')
        ->assertSet('cooldownActive', false)
        ->assertSet('cooldownSeconds', 0);

    expect(session()->has('simple_unblock_cooldown'))->toBeFalse();
});

test('simple unblock form resets domain field but keeps ip after submission', function () {
    $component = Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->call('processUnblock');

    expect($component->get('ip'))->toBe('192.168.1.1')
        ->and($component->get('domain'))->toBe('');
});

test('cooldown respects custom configuration', function () {
    config()->set('unblock.simple_mode.cooldown_seconds', 60);

    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->call('processUnblock')
        ->assertSet('cooldownSeconds', 60);
});

test('cooldown initializes correctly on mount', function () {
    // Set an active cooldown in session
    session()->put('simple_unblock_cooldown', now()->addSeconds(45)->timestamp);

    $component = Livewire::test(SimpleUnblockForm::class);

    expect($component->get('cooldownActive'))->toBeTrue()
        ->and($component->get('cooldownSeconds'))->toBeGreaterThan(40)
        ->and($component->get('cooldownSeconds'))->toBeLessThanOrEqual(45);
});
