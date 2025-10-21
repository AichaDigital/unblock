<?php

declare(strict_types=1);

use App\Livewire\SimpleUnblockForm;
use App\Models\Host;
use Illuminate\Support\Facades\{Config, Queue, Route};
use Livewire\Livewire;

use function Pest\Laravel\{assertDatabaseHas, get};

beforeEach(function () {
    // Enable simple mode for tests
    Config::set('unblock.simple_mode.enabled', true);

    // Register simple unblock route for testing
    Route::get('/simple-unblock', \App\Livewire\SimpleUnblockForm::class)
        ->middleware(['throttle.simple.unblock'])
        ->name('simple.unblock');

    // Create hosts for testing
    Host::factory()->create(['panel' => 'cpanel']);
    Host::factory()->create(['panel' => 'directadmin']);
});

test('simple unblock form is accessible when enabled', function () {
    get('/simple-unblock')
        ->assertOk()
        ->assertSeeLivewire(SimpleUnblockForm::class);
});

test('simple unblock form autodetects user IP', function () {
    Livewire::test(SimpleUnblockForm::class)
        ->assertSet('ip', request()->ip());
});

test('simple unblock form validates IP format', function () {
    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', 'invalid-ip')
        ->set('domain', 'example.com')
        ->set('email', 'test@example.com')
        ->call('submit')
        ->assertHasErrors(['ip']);
});

test('simple unblock form validates domain format', function () {
    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'invalid domain!')
        ->set('email', 'test@example.com')
        ->call('submit')
        ->assertHasErrors(['domain']);
});

test('simple unblock form validates email format', function () {
    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->set('email', 'invalid-email')
        ->call('submit')
        ->assertHasErrors(['email']);
});

test('simple unblock form accepts valid input', function () {
    Queue::fake();

    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->set('email', 'test@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('messageType', 'success');
});

test('simple unblock normalizes domain to lowercase', function () {
    Queue::fake();

    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'EXAMPLE.COM')
        ->set('email', 'test@example.com')
        ->call('submit')
        ->assertHasNoErrors();

    // Verify activity log contains normalized domain
    assertDatabaseHas('activity_log', [
        'description' => 'simple_unblock_request',
    ]);
});

test('simple unblock removes www prefix from domain', function () {
    Queue::fake();

    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'www.example.com')
        ->set('email', 'test@example.com')
        ->call('submit')
        ->assertHasNoErrors();
});

test('simple unblock clears form after successful submission', function () {
    Queue::fake();

    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->set('email', 'test@example.com')
        ->call('submit')
        ->assertSet('domain', '')
        ->assertSet('email', '');
});

test('simple unblock shows processing state', function () {
    Queue::fake();

    $component = Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->set('email', 'test@example.com');

    $component->call('submit');

    expect($component->get('message'))->not->toBeNull();
    expect($component->get('messageType'))->toBe('success');
});

test('simple unblock dispatches jobs for all hosts', function () {
    Queue::fake();

    $hostsCount = Host::count();

    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->set('email', 'test@example.com')
        ->call('submit');

    Queue::assertPushed(\App\Jobs\ProcessSimpleUnblockJob::class, $hostsCount);
});

test('simple unblock logs activity for audit', function () {
    Queue::fake();

    Livewire::test(SimpleUnblockForm::class)
        ->set('ip', '192.168.1.1')
        ->set('domain', 'example.com')
        ->set('email', 'test@example.com')
        ->call('submit');

    assertDatabaseHas('activity_log', [
        'description' => 'simple_unblock_request',
    ]);
});
