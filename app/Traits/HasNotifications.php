<?php

declare(strict_types=1);

namespace App\Traits;

use Livewire\Component;

trait HasNotifications
{
    /**
     * Send a notification to the user
     *
     * @param array{icon: string, title: string, description: string} $notification
     */
    public function sendNotification(array $notification): void
    {
        if (! $this instanceof Component) {
            throw new \RuntimeException('HasNotifications trait can only be used in Livewire components');
        }

        // Map WireUI icon names to daisyUI alert types
        $iconMap = [
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info',
        ];

        $type = $iconMap[$notification['icon']] ?? 'info';

        // Dispatch browser event for Alpine.js to catch
        $payload = json_encode([
            'type' => $type,
            'title' => $notification['title'],
            'description' => $notification['description'],
        ]);

        $this->js("window.dispatchEvent(new CustomEvent('notify', { detail: {$payload} }))");
    }

    /**
     * Compatibility method for WireUI-style notifications
     */
    public function notification(): self
    {
        return $this;
    }

    /**
     * Send notification (compatibility method)
     */
    public function send(array $notification): void
    {
        $this->sendNotification($notification);
    }

    /**
     * Send error notification (compatibility method for WireUI)
     */
    public function error(string $title, string $description): void
    {
        $this->sendNotification([
            'icon' => 'error',
            'title' => $title,
            'description' => $description,
        ]);
    }

    /**
     * Send success notification (compatibility method for WireUI)
     */
    public function success(string $title, string $description): void
    {
        $this->sendNotification([
            'icon' => 'success',
            'title' => $title,
            'description' => $description,
        ]);
    }

    /**
     * Send info notification (compatibility method for WireUI)
     */
    public function info(string $title, string $description): void
    {
        $this->sendNotification([
            'icon' => 'info',
            'title' => $title,
            'description' => $description,
        ]);
    }

    /**
     * Send warning notification (compatibility method for WireUI)
     */
    public function warning(string $title, string $description): void
    {
        $this->sendNotification([
            'icon' => 'warning',
            'title' => $title,
            'description' => $description,
        ]);
    }
}
