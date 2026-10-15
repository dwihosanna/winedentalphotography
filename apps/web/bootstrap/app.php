<?php

use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\LoginFromTrustedDevice;
use App\Http\Middleware\SetCurrentWorkspace;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['workspace' => SetCurrentWorkspace::class]);
        // Perangkat tepercaya: masuk otomatis setelah sesi dimulai, sebelum middleware auth.
        $middleware->web(append: [LoginFromTrustedDevice::class]);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: LoginFromTrustedDevice::class);
        // Urutan wajib: login → email terverifikasi → onboarding → workspace aktif → route model binding.
        // Workspace harus aktif sebelum binding agar binding ikut dibatasi workspace.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentWorkspace::class);
        $middleware->prependToPriorityList(before: SetCurrentWorkspace::class, prepend: EnsureOnboarded::class);
        $middleware->prependToPriorityList(before: EnsureOnboarded::class, prepend: EnsureEmailIsVerified::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
