<?php

declare(strict_types=1);

namespace Paarfragen\Application;

use Paarfragen\Domain\AppFeedback;

interface AppFeedbackRepository
{
    /**
     * Persists the feedback row. Idempotent on `$feedback->id`: if a row
     * with that id already exists, this is a silent no-op — see
     * specs/2026-09-06-slice-4-app-feedback.md.
     */
    public function record(AppFeedback $feedback): void;

    /** @return AppFeedback[] rows where `handled_at IS NULL`. */
    public function listUnhandled(): array;

    /**
     * Marks a row handled. No-op if `$id` doesn't exist or is already
     * handled — a triage tool re-run against the same list shouldn't
     * fail on a row an earlier call already processed.
     */
    public function markHandled(string $id): void;
}
