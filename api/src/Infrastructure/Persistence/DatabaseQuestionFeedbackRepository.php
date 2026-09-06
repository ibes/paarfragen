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
}
