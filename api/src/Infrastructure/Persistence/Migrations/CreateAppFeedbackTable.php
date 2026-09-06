<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence\Migrations;

use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CreateTableStatement;

final class CreateAppFeedbackTable implements MigratesUp
{
    public string $name = '2026-09-06_3_create_app_feedback_table';

    public function up(): QueryStatement
    {
        // deck_id is a plain text column, not a fk: same reasoning as
        // question_feedback (CreateQuestionFeedbackTable) — deck_id is
        // never looked up against a table, there is no `decks` table.
        return new CreateTableStatement('app_feedback')
            ->uuid('id')
            ->text('deck_id')
            ->text('free_text')
            ->datetime('handled_at', nullable: true)
            ->datetime('created_at', current: true);
    }
}
