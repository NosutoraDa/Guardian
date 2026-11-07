<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway terminates HTTPS at its proxy and forwards the original scheme.
        // Trust that proxy metadata so generated asset URLs stay HTTPS.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions()
    ->create();
