<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laxa\Laxa;
use Laxa\Plugin\Plugin;
use Laxa\SpatieLaravelPermissionPlugin\SpatieLaravelPermissionPlugin;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        $tool = Laxa::plugins()->all()->first([$this, 'matchesPlugin']);

        if (is_null($tool)) {
            abort(404);
        }

        if (! $tool->authorize($request)) {
            abort(403);
        }

        return $next($request);
    }

    public function matchesPlugin(Plugin $plugin): bool
    {
        return $plugin instanceof SpatieLaravelPermissionPlugin;
    }
}
