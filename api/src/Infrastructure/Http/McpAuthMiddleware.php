<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Http;

use Tempest\Http\GenericResponse;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Status;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;

/**
 * Protects the `/mcp` route — `app_feedback` rows aren't deck-scoped,
 * so an unprotected route would let anyone read every submitted
 * feedback row.
 *
 * **Global, not route-scoped** — deliberately, unlike a normal Tempest
 * route middleware. Tempest's own MCP routing
 * (`Tempest\Mcp\McpDiscovery::registerRoutes()`) always points every
 * discovered `#[McpServer]`'s route at the same generic
 * `Tempest\Mcp\McpHttpController`, using a hardcoded decorator list
 * that never reads a `#[WithMiddleware]` attribute off the server
 * class — confirmed by reading that method's source, not assumed from
 * the docs. So this middleware is discovered normally (no
 * `#[SkipDiscovery]`, unlike a route-scoped middleware would be) and
 * scopes *itself* to `AppFeedbackServer`'s path instead, no-op'ing on
 * every other route the same way `CorsMiddleware` applies to every
 * route rather than one.
 *
 * Two checks, both must pass: a static bearer token (the actual
 * access control), plus a signed, max-10-minutes-old timestamp
 * (replay protection if the token ever leaks somewhere, e.g. a proxy
 * log — a smaller risk here than for `deck_id`, which specs/api.md
 * keeps out of URLs for the same reason, since this token only ever
 * travels in a header). No IP/domain restriction: rejected during
 * this slice's grill as not reliably implementable — Anthropic
 * publishes no stable IP range for MCP client traffic. See
 * specs/2026-09-06-slice-4-app-feedback.md.
 */
final readonly class McpAuthMiddleware implements HttpMiddleware
{
    private const string MCP_PATH = '/mcp';

    private const int MAX_TIMESTAMP_AGE_SECONDS = 600;

    public function __construct(
        private McpAuthConfig $config,
    ) {}

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        if ($request->path !== self::MCP_PATH) {
            return $next($request);
        }

        // An unset MCP_AUTH_TOKEN must never mean "any token is
        // valid" — fail closed instead.
        if ($this->config->token === '') {
            return $this->unauthorized('MCP auth token is not configured.');
        }

        $authorization = $request->headers->get('Authorization');
        if ($authorization !== 'Bearer ' . $this->config->token) {
            return $this->unauthorized('Missing or invalid bearer token.');
        }

        $timestamp = $request->headers->get('X-Timestamp');
        $signature = $request->headers->get('X-Signature');
        if ($timestamp === null || $signature === null) {
            return $this->unauthorized('Missing X-Timestamp/X-Signature headers.');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp, $this->config->token);
        if (!hash_equals($expectedSignature, $signature)) {
            return $this->unauthorized('Invalid signature.');
        }

        $age = time() - (int) $timestamp;
        if ($age < 0 || $age > self::MAX_TIMESTAMP_AGE_SECONDS) {
            return $this->unauthorized('Timestamp expired or invalid.');
        }

        return $next($request);
    }

    private function unauthorized(string $message): GenericResponse
    {
        return new GenericResponse(status: Status::UNAUTHORIZED, body: ['error' => ['message' => $message]]);
    }
}
