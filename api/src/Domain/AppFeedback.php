<?php

declare(strict_types=1);

namespace Paarfragen\Domain;

use DateTimeImmutable;

final readonly class AppFeedback
{
    /**
     * `$createdAt` is only populated when reading (e.g. `ListAppFeedback`)
     * — recording a new row never needs it, the database sets it.
     */
    public function __construct(
        public string $id,
        public string $deckId,
        public string $freeText,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
