<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence;

use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\Database\Uuid;

/**
 * Persistence shape for `questions` — not the Domain entity. Only `id`
 * and `text` are declared: Tempest's query builder selects exactly the
 * model's public properties, so `source` (creator-only, never sent to
 * clients per specs/api.md) and `created_at` (unused this slice) are
 * structurally excluded from every query, not just filtered after the
 * fact. `#[Uuid]` makes Tempest auto-generate a UUIDv7 `id` on insert
 * when unset — not used in this slice (questions are seeded via a raw
 * SQL migration), but correct for whenever a later slice inserts one.
 */
#[Table('questions')]
final class QuestionModel
{
    #[Uuid]
    public PrimaryKey $id;

    public function __construct(
        public string $text,
    ) {}
}
