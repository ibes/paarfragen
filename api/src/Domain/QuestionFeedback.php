<?php

declare(strict_types=1);

namespace Paarfragen\Domain;

final readonly class QuestionFeedback
{
    public function __construct(
        public string $id,
        public string $questionId,
        public string $deckId,
        public Rating $rating,
        public ?string $freeText,
    ) {}
}
