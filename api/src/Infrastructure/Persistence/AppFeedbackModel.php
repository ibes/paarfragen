<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence;

use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

/**
 * Persistence shape for `app_feedback` — not the Domain entity.
 * Property names are snake_case to match the DB columns exactly, same
 * "exact-name" mapping rule as QuestionFeedbackModel.
 *
 * `id` has no `#[Uuid]` attribute: it is always client-generated
 * (specs/api.md) and set explicitly before insert, never
 * auto-generated — see DatabaseAppFeedbackRepository.
 *
 * `handled_at`/`created_at` have **no `= null` default** on purpose:
 * Tempest's `ModelInspector::getPropertyValues()` (used to build an
 * INSERT) only skips a property that is *uninitialized* — a typed
 * property with a `= null` default is initialized immediately, so it
 * would be sent as an explicit `NULL` on every insert. For
 * `created_at` that trips the `NOT NULL` + `current: true` column
 * default (`CreateAppFeedbackTable`) into a constraint violation;
 * leaving both properties truly uninitialized excludes them from
 * `record()`'s insert (letting SQLite's own column default/implicit
 * NULL apply) while still readable after `listUnhandled()`'s select
 * populates them via reflection.
 */
#[Table('app_feedback')]
final class AppFeedbackModel
{
    public PrimaryKey $id;

    public ?string $handled_at;

    public ?string $created_at;

    public function __construct(
        public string $deck_id,
        public string $free_text,
    ) {}
}
