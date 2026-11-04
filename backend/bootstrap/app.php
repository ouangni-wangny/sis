<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Derrière nginx / Cloudflare / load balancer.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // Bearer tokens only (Next.js + mobile). Do NOT enable statefulApi():
        // it applies CSRF to origins in SANCTUM_STATEFUL_DOMAINS (e.g. localhost:3000)
        // and breaks token-based login with "CSRF token mismatch".
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Messages par défaut du framework : en anglais, parfois techniques
        // (nom de classe sur un 404). Un message écrit par l’application est gardé.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->is('api/*') || ! $response instanceof JsonResponse || config('app.debug') && $response->getStatusCode() >= 500) {
                return $response;
            }

            $data = $response->getData(true);
            $actuel = (string) ($data['message'] ?? '');
            $message = match (true) {
                $response->getStatusCode() === 401 && $actuel === 'Unauthenticated.' => 'Votre session a expiré. Reconnectez-vous.',
                $response->getStatusCode() === 403 && in_array($actuel, ['', 'This action is unauthorized.', 'Forbidden'], true) => 'Vous n’avez pas le droit d’effectuer cette action.',
                $response->getStatusCode() === 404 && ($actuel === '' || str_starts_with($actuel, 'No query results') || str_starts_with($actuel, 'The route')) => 'Élément introuvable : il a peut-être été supprimé. Actualisez la page.',
                $response->getStatusCode() === 405 => 'Cette action n’est pas disponible.',
                $response->getStatusCode() === 413 => 'Le fichier ou les données envoyés sont trop volumineux.',
                $response->getStatusCode() === 429 => 'Trop de tentatives. Patientez un instant puis réessayez.',
                $response->getStatusCode() === 503 => 'Le service est momentanément indisponible. Réessayez dans un instant.',
                $response->getStatusCode() >= 500 => 'Une erreur est survenue de notre côté. Réessayez dans un instant.',
                default => null,
            };

            return $message === null ? $response : $response->setData([...$data, 'message' => $message]);
        });
    })->create();
