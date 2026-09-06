<?php

declare(strict_types=1);

namespace Paarfragen\Tests\Infrastructure;

use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Framework\Testing\IntegrationTest;
use Tempest\Http\Status;

/**
 * Exercises McpAuthMiddleware over a real HTTP request — the
 * in-process `$this->mcp` test helper bypasses HTTP/middleware
 * entirely (FRICTION.md, "Tempest's MCP docs say route decorators
 * protect a server's route..."), so it can't be used to test this.
 */
final class McpAuthMiddlewareTest extends IntegrationTest
{
    // Test fixture, not a real secret — matches phpunit.xml's MCP_AUTH_TOKEN.
    // @mago-expect lint:no-literal-password
    private const string TOKEN = 'testing-mcp-auth-token';

    #[PreCondition]
    protected function configure(): void
    {
        $this->database->setup();
    }

    #[Test]
    public function a_correctly_signed_request_is_accepted(): void
    {
        $this->http->post('/mcp', $this->requestBody(), headers: $this->validHeaders())->assertStatus(Status::OK);
    }

    #[Test]
    public function missing_authorization_header_is_rejected(): void
    {
        $headers = $this->validHeaders();
        unset($headers['Authorization']);

        $this->http->post('/mcp', $this->requestBody(), headers: $headers)->assertStatus(Status::UNAUTHORIZED);
    }

    #[Test]
    public function wrong_token_is_rejected(): void
    {
        $headers = $this->validHeaders();
        $headers['Authorization'] = 'Bearer wrong-token';

        $this->http->post('/mcp', $this->requestBody(), headers: $headers)->assertStatus(Status::UNAUTHORIZED);
    }

    #[Test]
    public function missing_timestamp_or_signature_is_rejected(): void
    {
        $headers = $this->validHeaders();
        unset($headers['X-Timestamp'], $headers['X-Signature']);

        $this->http->post('/mcp', $this->requestBody(), headers: $headers)->assertStatus(Status::UNAUTHORIZED);
    }

    #[Test]
    public function wrong_signature_is_rejected(): void
    {
        $timestamp = (string) time();
        $headers = [
            'Authorization' => 'Bearer ' . self::TOKEN,
            'X-Timestamp' => $timestamp,
            'X-Signature' => hash_hmac('sha256', $timestamp, key: 'not-the-real-token'),
            'Content-Type' => 'application/json',
        ];

        $this->http->post('/mcp', $this->requestBody(), headers: $headers)->assertStatus(Status::UNAUTHORIZED);
    }

    #[Test]
    public function stale_timestamp_is_rejected(): void
    {
        $timestamp = (string) (time() - 601); // just past the 10-minute window
        $headers = [
            'Authorization' => 'Bearer ' . self::TOKEN,
            'X-Timestamp' => $timestamp,
            'X-Signature' => hash_hmac('sha256', $timestamp, self::TOKEN),
            'Content-Type' => 'application/json',
        ];

        $this->http->post('/mcp', $this->requestBody(), headers: $headers)->assertStatus(Status::UNAUTHORIZED);
    }

    #[Test]
    public function other_routes_are_unaffected(): void
    {
        $this->http->get('/questions')->assertOk();
    }

    private function validHeaders(): array
    {
        $timestamp = (string) time();

        return [
            'Authorization' => 'Bearer ' . self::TOKEN,
            'X-Timestamp' => $timestamp,
            'X-Signature' => hash_hmac('sha256', $timestamp, self::TOKEN),
            'Content-Type' => 'application/json',
        ];
    }

    private function requestBody(): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ], JSON_THROW_ON_ERROR);
    }
}
