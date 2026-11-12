<?php

namespace App\Http\Middleware;

use App\Domain\Display\SectorContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveSectorContext
{
    /**
     * Resolve the sector for this request without sharing state with other requests.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sectorKey = $request->route('sector');

        abort_unless(is_string($sectorKey), Response::HTTP_NOT_FOUND);

        $configuration = config("sectors.sectors.{$sectorKey}");

        abort_unless(is_array($configuration), Response::HTTP_NOT_FOUND);

        $request->attributes->set(
            SectorContext::class,
            SectorContext::fromConfiguration($sectorKey, $configuration),
        );

        return $next($request);
    }
}
