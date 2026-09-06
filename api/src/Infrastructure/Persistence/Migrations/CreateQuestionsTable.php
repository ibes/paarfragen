<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence\Migrations;

use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CreateTableStatement;

final class CreateQuestionsTable implements MigratesUp
{
    public string $name = '2026-09-06_1_create_questions_table';

    public function up(): QueryStatement
    {
        return new CreateTableStatement('questions')
            ->uuid('id')
            ->text('text')
            ->json('source')
            ->datetime('created_at', current: true);
    }
}
