<?php

declare(strict_types=1);

namespace Paarfragen\Application;

use Paarfragen\Domain\Question;

final readonly class ListQuestions
{
    public function __construct(
        private QuestionRepository $questions,
    ) {}

    /** @return Question[] */
    public function handle(): array
    {
        return $this->questions->all();
    }
}
