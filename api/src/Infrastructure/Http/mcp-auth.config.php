<?php

declare(strict_types=1);

// Config file, not a class — needs its own explicit namespace or
// mago's [guard.perimeter] flags the `use Tempest\...` below as an
// illegal dependency from the (otherwise unnamespaced) global
// namespace. See api/reference/tempest.md.
namespace Paarfragen\Infrastructure\Http;

use function Tempest\env;

// @mago-expect analysis:mixed-assignment
$token = env('MCP_AUTH_TOKEN', default: '');

return new McpAuthConfig(token: is_string($token) ? $token : '');
