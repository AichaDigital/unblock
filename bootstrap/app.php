<?php

use App\Actions\WhmcsSynchro;
use App\Http\Middleware\{CheckSessionTimeout, CheckSimpleModeEnabled, SetUserLocale, SimpleModeAccess, ThrottleSimpleUnblock, VerifyIsAdminMiddleware};
use App\Services\AuditService;
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
        // Register middleware globally for all web routes
        $middleware->web(append: [
            SetUserLocale::class,
        ]);

        $middleware->alias([
            'admin' => VerifyIsAdminMiddleware::class,
            'session.timeout' => CheckSessionTimeout::class,
            'simple.mode' => SimpleModeAccess::class,
            'simple.mode.enabled' => CheckSimpleModeEnabled::class,
            'throttle.simple.unblock' => ThrottleSimpleUnblock::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ThrottleRequestsException $e) {
            $ip = request()->ip() ?? '0.0.0.0';

            $msg = $e->getMessage();

            Log::channel('login_errors')->error("$ip $msg");
            $auditService = new AuditService;
            $auditService->audit(
                ip: $ip,
                action: 'too_many_requests',
                message: $msg,
                is_fail: true,
            );
        });
    })->create();
