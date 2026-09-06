<?php

declare(strict_types=1);

namespace Paarfragen\Application;

final readonly class ListRatedQuestionIds
{
    public function __construct(
        private QuestionFeedbackRepository $feedback,
    ) {}

    /** @return string[] */
    public function handle(string $deckId): array
    {
        return $this->feedback->listRatedQuestionIds($deckId);
    }
}
