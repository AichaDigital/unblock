<?php

use App\Actions\WhmcsSynchro;
use App\Http\Middleware\{CheckSessionTimeout, ThrottleSimpleUnblock, VerifyIsAdminMiddleware};
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        WhmcsSynchro::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => VerifyIsAdminMiddleware::class,
            'session.timeout' => CheckSessionTimeout::class,
            'throttle.simple.unblock' => ThrottleSimpleUnblock::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ThrottleRequestsException $e) {
            if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ipArray = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ipArray[0]);
            } else {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            }

            $msg = $e->getMessage();

            Log::channel('login_errors')->error("$ip $msg");
            $auditService = new App\Services\AuditService;
            $auditService->audit(
                ip: $ip,
                action: 'too_many_requests',
                message: $msg,
                is_fail: true,
            );
        });
    })->create();
