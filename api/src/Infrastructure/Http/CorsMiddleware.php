<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Http;

use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Ok;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Support\Priority;

/**
 * frontend/ and api/ are decoupled, different-origin services
 * (specs/STATUS.md) — every browser request here is cross-origin, so
 * without this every fetch() from frontend/ is blocked by the
 * browser's CORS policy before the response ever reaches app code.
 * Global by default (no #[SkipDiscovery]) since every route here is a
 * JSON endpoint the frontend needs to reach (api/README.md — pure
 * JSON API, no server-rendered views).
 *
 * Wildcard origin, not an allow-list: `deck_id` (specs/api.md) travels
 * only in request bodies/query params, never a cookie, so there is no
 * ambient credential for another origin to piggyback on the way a
 * cookie-based session would allow — the usual reason to restrict
 * CORS to a known origin doesn't apply here.
 *
 * Priority -50, before Tempest's own MatchRouteMiddleware (priority
 * -30, `Tempest\Router\MatchRouteMiddleware`): a CORS preflight is an
 * OPTIONS request to a path that only has a GET/POST route registered
 * — no route ever matches it. MatchRouteMiddleware returns its own
 * 404 (without CORS headers) before calling the rest of the chain, so
 * this has to intercept OPTIONS earlier than that, not rely on the
 * normal $next() pass-through. Confirmed by hand: without this
 * priority, a real preflight request 404s and the browser blocks the
 * real POST — see FRICTION.md.
 */
#[Priority(-50)]
final class CorsMiddleware implements HttpMiddleware
{
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        if ($request->method === Method::OPTIONS) {
            return $this->withCorsHeaders(new Ok());
        }

        return $this->withCorsHeaders($next($request));
    }

    private function withCorsHeaders(Response $response): Response
    {
        return $response
            ->addHeader('Access-Control-Allow-Origin', '*')
            ->addHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->addHeader('Access-Control-Allow-Headers', 'Content-Type');
    }
}
