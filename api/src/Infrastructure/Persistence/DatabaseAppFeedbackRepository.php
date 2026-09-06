<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence;

use DateTimeImmutable;
use Paarfragen\Application\AppFeedbackRepository;
use Paarfragen\Domain\AppFeedback;
use Tempest\Container\Autowire;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

#[Autowire]
final readonly class DatabaseAppFeedbackRepository implements AppFeedbackRepository
{
    public function record(AppFeedback $feedback): void
    {
        $alreadyRecorded = query(AppFeedbackModel::class)->select()->get(new PrimaryKey($feedback->id)) !== null;

        if ($alreadyRecorded) {
            return;
        }

        $row = new AppFeedbackModel(deck_id: $feedback->deckId, free_text: $feedback->freeText);
        $row->id = new PrimaryKey($feedback->id);

        query(AppFeedbackModel::class)->insert($row)->execute();
    }

    public function listUnhandled(): array
    {
        $feedback = [];

        // query()'s own @template binds `TModel` to the literal
        // class-string passed in, not to instances of it — same known
        // stub gap as DatabaseQuestionRepository::all() (FRICTION.md),
        // just with more property accesses/constructor args to
        // suppress since AppFeedback carries more fields than Question.
        // @mago-expect analysis:mixed-assignment
        // @mago-expect analysis:mixed-property-access
        // @mago-expect analysis:mixed-property-access
        // @mago-expect analysis:mixed-property-access
        // @mago-expect analysis:mixed-property-access
        // @mago-expect analysis:mixed-argument
        // @mago-expect analysis:mixed-argument
        // @mago-expect analysis:mixed-argument
        foreach (query(AppFeedbackModel::class)->select()->where('handled_at IS NULL')->all() as $row) {
            $feedback[] = new AppFeedback(
                id: (string) $row->id,
                deckId: $row->deck_id,
                freeText: $row->free_text,
                createdAt: $row->created_at === null ? null : new DateTimeImmutable($row->created_at),
            );
        }

        return $feedback;
    }

    public function markHandled(string $id): void
    {
        query(AppFeedbackModel::class)
            ->update(handled_at: new DateTimeImmutable()->format('Y-m-d H:i:s'))
            ->whereField('id', $id)
            ->execute();
    }
}
