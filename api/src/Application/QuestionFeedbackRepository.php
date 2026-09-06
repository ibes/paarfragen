<?php

declare(strict_types=1);

namespace Paarfragen\Application;

use Paarfragen\Domain\QuestionFeedback;

interface QuestionFeedbackRepository
{
    /**
     * Persists the feedback row. Idempotent on `$feedback->id`: if a row
     * with that id already exists, this is a silent no-op — see
     * specs/2026-09-06-slice-2-questions-feedback-persistence.md.
     */
    public function record(QuestionFeedback $feedback): void;
}
