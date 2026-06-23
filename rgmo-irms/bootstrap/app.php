<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

if (! class_exists('ZipArchive')) {
    class ZipArchive
    {
        public const CM_STORE = 0;
        public const CM_DEFAULT = -1;
        public const CM_DEFLATE = 8;
        public const CM_BZIP2 = 12;
        public const CM_XZ = 95;
        public const EM_AES_256 = 257;
    }
}

if (! function_exists('mb_split')) {
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        return preg_split('/'.$pattern.'/', $string, $limit);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'admin' => \App\Http\Middleware\EnsureAdminAccess::class,
            'log.activity' => \App\Http\Middleware\LogUserActivity::class,
        ]);

        // Global middleware
        $middleware->append(\App\Http\Middleware\LogUserActivity::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
