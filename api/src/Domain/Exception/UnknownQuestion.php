<?php

declare(strict_types=1);

namespace Paarfragen\Domain\Exception;

use RuntimeException;

final class UnknownQuestion extends RuntimeException
{
    public function __construct(
        public readonly string $questionId,
    ) {
        parent::__construct("Unknown question: {$questionId}");
    }
}
