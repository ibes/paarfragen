<?php

declare(strict_types=1);

namespace Paarfragen\Application;

final readonly class MarkAppFeedbackHandled
{
    public function __construct(
        private AppFeedbackRepository $feedback,
    ) {}

    public function handle(string $id): void
    {
        $this->feedback->markHandled($id);
    }
}
