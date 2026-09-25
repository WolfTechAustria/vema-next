<?php

use App\Http\Middleware\EnsureStaffAccountIsActive;
use App\Http\Middleware\EnsureUserCanManageInvoices;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\UseDemoDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'active.staff' => EnsureStaffAccountIsActive::class,
            'invoices' => EnsureUserCanManageInvoices::class,
        ]);

        // Testmodus: direkt nach StartSession, also vor Auth und Route-Model-Binding.
        $middleware->appendToGroup('web', UseDemoDatabase::class);
        $middleware->appendToPriorityList(
            StartSession::class,
            UseDemoDatabase::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
