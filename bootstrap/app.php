<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if (
                $exception->getStatusCode() !== 419
                || ! ($exception->getPrevious() instanceof TokenMismatchException)
                || ! $request->isMethod('post')
                || ! $request->is('logout', 'admin/logout')
            ) {
                return null;
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $redirectTo = $request->is('admin/logout')
                ? route('admin.login')
                : '/';

            return redirect($redirectTo)->withoutCookie('supabase_token');
        });
    })->create();
