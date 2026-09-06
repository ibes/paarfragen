<?php

declare(strict_types=1);

namespace Paarfragen\Application;

use Paarfragen\Domain\AppFeedback;

final readonly class RecordAppFeedback
{
    public function __construct(
        private AppFeedbackRepository $feedback,
    ) {}

    public function handle(AppFeedback $feedback): void
    {
        $this->feedback->record($feedback);
    }
}
