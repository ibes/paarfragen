<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence\Migrations;

use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CreateTableStatement;

final class CreateQuestionFeedbackTable implements MigratesUp
{
    public string $name = '2026-09-06_2_create_question_feedback_table';

    public function up(): QueryStatement
    {
        // question_id/deck_id are plain text columns, not `belongsTo`
        // foreign keys: SQLite drops FK constraints from this builder
        // entirely, and `belongsTo` assumes an integer column anyway,
        // wrong for our UUID strings. `question_id` existence is
        // enforced at the Application layer instead (RecordQuestionFeedback),
        // and `deck_id` is never looked up at all — see
        // specs/2026-09-06-slice-2-questions-feedback-persistence.md.
        return new CreateTableStatement('question_feedback')
            ->uuid('id')
            ->text('question_id')
            ->text('deck_id')
            ->integer('rating')
            ->text('free_text', nullable: true)
            ->datetime('created_at', current: true);
    }
}
