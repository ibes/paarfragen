<?php

declare(strict_types=1);

namespace Paarfragen\Application;

use Paarfragen\Domain\AppFeedback;

final readonly class ListAppFeedback
{
    public function __construct(
        private AppFeedbackRepository $feedback,
    ) {}

    /** @return AppFeedback[] */
    public function handle(): array
    {
        return $this->feedback->listUnhandled();
    }
}
