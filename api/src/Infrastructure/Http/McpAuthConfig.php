<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Http;

use SensitiveParameter;

final readonly class McpAuthConfig
{
    public function __construct(
        #[SensitiveParameter]
        public string $token,
    ) {}
}
