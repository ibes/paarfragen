<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence;

use Paarfragen\Application\QuestionFeedbackRepository;
use Paarfragen\Domain\QuestionFeedback;
use Tempest\Container\Autowire;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

#[Autowire]
final readonly class DatabaseQuestionFeedbackRepository implements QuestionFeedbackRepository
{
    public function record(QuestionFeedback $feedback): void
    {
        $alreadyRecorded = query(QuestionFeedbackModel::class)->select()->get(new PrimaryKey($feedback->id)) !== null;

        if ($alreadyRecorded) {
            return;
        }

        $row = new QuestionFeedbackModel(
            question_id: $feedback->questionId,
            deck_id: $feedback->deckId,
            rating: $feedback->rating->value,
            free_text: $feedback->freeText,
        );
        $row->id = new PrimaryKey($feedback->id);

        query(QuestionFeedbackModel::class)->insert($row)->execute();
    }

    public function listRatedQuestionIds(string $deckId): array
    {
        // Deduplicated in PHP, not SQL DISTINCT: Tempest's model-based
        // SelectQueryBuilder has no distinct() (only CountQueryBuilder
        // does) — see specs/2026-09-06-slice-6-question-feedback-reconstruction.md.
        $ids = [];

        // @mago-expect analysis:mixed-assignment
        // @mago-expect analysis:mixed-property-access
        foreach (query(QuestionFeedbackModel::class)->select()->where('deck_id = ?', $deckId)->all() as $row) {
            $ids[$row->question_id] = true;
        }

        // Same query()-stub-typing gap as elsewhere in this class: mago
        // can't see $row->question_id is really a string, so array_keys()
        // here infers list<array-key>, wider than the declared string[].
        // @mago-expect analysis:less-specific-return-statement
        return array_keys($ids);
    }
}
