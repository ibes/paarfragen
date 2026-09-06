<?php

declare(strict_types=1);

namespace Paarfragen\Application;

use Paarfragen\Domain\Exception\UnknownQuestion;
use Paarfragen\Domain\QuestionFeedback;

final readonly class RecordQuestionFeedback
{
    public function __construct(
        private QuestionRepository $questions,
        private QuestionFeedbackRepository $feedback,
    ) {}

    /** @throws UnknownQuestion */
    public function handle(QuestionFeedback $feedback): void
    {
        if (!$this->questions->exists($feedback->questionId)) {
            throw new UnknownQuestion($feedback->questionId);
        }

        $this->feedback->record($feedback);
    }
}
