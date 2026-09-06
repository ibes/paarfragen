<?php

declare(strict_types=1);

namespace Paarfragen\Application;

use Paarfragen\Domain\Question;

interface QuestionRepository
{
    /** @return Question[] */
    public function all(): array;

    public function exists(string $id): bool;
}
