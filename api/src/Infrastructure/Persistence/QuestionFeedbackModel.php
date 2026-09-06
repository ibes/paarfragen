<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence;

use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

/**
 * Persistence shape for `question_feedback` — not the Domain entity.
 * Property names are snake_case to match the DB columns exactly:
 * Tempest maps a model property to a column by exact name, no case
 * conversion (same "exact-name" rule as HTTP request mapping, see
 * api/reference/tempest.md). Domain/Application stay camelCase; this
 * repository is the only place the two shapes meet.
 *
 * `id` has no `#[Uuid]` attribute: it is always client-generated
 * (specs/api.md) and set explicitly before insert, never
 * auto-generated — see DatabaseQuestionFeedbackRepository.
 */
#[Table('question_feedback')]
final class QuestionFeedbackModel
{
    public PrimaryKey $id;

    public function __construct(
        public string $question_id,
        public string $deck_id,
        public int $rating,
        public ?string $free_text,
    ) {}
}
