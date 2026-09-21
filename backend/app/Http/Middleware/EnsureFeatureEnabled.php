<?php

namespace App\Http\Middleware;

use App\Support\FeatureFlagRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        if (! FeatureFlagRegistry::enabled($featureKey)) {
            abort(403, 'Cette fonctionnalité est désactivée.');
        }

        return $next($request);
    }
}
