<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\OneTimePasswords\Events\{FailedToConsumeOneTimePassword, OneTimePasswordSuccessfullyConsumed};

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Listen to Spatie OTP events for activity logging
        Event::listen(OneTimePasswordSuccessfullyConsumed::class, function ($event) {
            // Use reflection to access protected properties
            $reflection = new \ReflectionClass($event);
            $userProperty = $reflection->getProperty('user');
            $userProperty->setAccessible(true);
            $user = $userProperty->getValue($event);

            activity('otp_login')
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'success' => true,
                ])
                ->log('Successful OTP authentication');
        });

        Event::listen(FailedToConsumeOneTimePassword::class, function ($event) {
            // Properties are public in this event
            $user = $event->user;

            if ($user) {
                activity('otp_failed')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties([
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'success' => false,
                        'reason' => $event->validationResult->name ?? 'unknown',
                        'attempted_code' => 'hidden_for_security',
                    ])
                    ->log('Failed OTP authentication attempt');
            }
        });
    }
}
