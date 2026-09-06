<?php

declare(strict_types=1);

namespace Paarfragen\Domain;

final readonly class Question
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}
}
