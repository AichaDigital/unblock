<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\SimpleUnblockAction;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

/**
 * Simple Unblock Form Component
 *
 * Anonymous IP unblock form (no authentication required).
 * Part of the decoupled "simple mode" architecture.
 */
#[Layout('layouts.guest')]
#[Title('Simple IP Unblock')]
class SimpleUnblockForm extends Component
{
    public string $ip = '';

    public string $domain = '';

    public string $email = '';

    public bool $processing = false;

    public ?string $message = null;

    public ?string $messageType = null;

    /**
     * Component initialization
     */
    public function mount(): void
    {
        // Auto-detect user's IP address
        $this->ip = $this->detectUserIp();
    }

    /**
     * Handle form submission
     */
    public function submit(): void
    {
        $this->validate([
            'ip' => 'required|ip',
            'domain' => ['required', 'string', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'email' => 'required|email',
        ]);

        $this->processing = true;
        $this->message = null;

        try {
            SimpleUnblockAction::run(
                ip: $this->ip,
                domain: $this->domain,
                email: $this->email
            );

            $this->message = __('simple_unblock.processing_message');
            $this->messageType = 'success';

            // Clear form
            $this->reset(['domain', 'email']);
            $this->ip = $this->detectUserIp();

        } catch (\Exception $e) {
            $this->message = __('simple_unblock.error_message');
            $this->messageType = 'error';

            \Log::error('Simple unblock form error', [
                'ip' => $this->ip,
                'domain' => $this->domain,
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->processing = false;
        }
    }

    /**
     * Detect user's IP address (v1.2.0 - Fixed IP Spoofing)
     *
     * Uses request()->ip() which respects TrustProxies configuration.
     * This prevents IP header spoofing attacks.
     */
    private function detectUserIp(): string
    {
        return (string) request()->ip();
    }

    /**
     * Render the component
     */
    public function render(): \Illuminate\View\View
    {
        return view('livewire.simple-unblock-form');
    }
}
