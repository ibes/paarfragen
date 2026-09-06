<?php

declare(strict_types=1);

namespace Paarfragen\Tests\Application;

use Paarfragen\Application\QuestionFeedbackRepository;
use Paarfragen\Application\QuestionRepository;
use Paarfragen\Application\RecordQuestionFeedback;
use Paarfragen\Domain\Exception\UnknownQuestion;
use Paarfragen\Domain\QuestionFeedback;
use Paarfragen\Domain\Rating;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecordQuestionFeedbackTest extends TestCase
{
    #[Test]
    public function throws_for_an_unknown_question(): void
    {
        $questions = new class implements QuestionRepository {
            public function all(): array
            {
                return [];
            }

            public function exists(string $id): bool
            {
                return false;
            }
        };
        $feedback = new class implements QuestionFeedbackRepository {
            public function record(QuestionFeedback $feedback): void
            {
                self::fail('should not be reached for an unknown question');
            }

            public function listRatedQuestionIds(string $deckId): array
            {
                self::fail('should not be reached for an unknown question');
            }
        };

        $useCase = new RecordQuestionFeedback($questions, $feedback);

        $this->expectException(UnknownQuestion::class);

        $useCase->handle(new QuestionFeedback(
            id: 'feedback-1',
            questionId: 'unknown-question',
            deckId: 'deck-1',
            rating: Rating::VeryPositive,
            freeText: null,
        ));
    }

    #[Test]
    public function records_feedback_for_a_known_question(): void
    {
        $questions = new class implements QuestionRepository {
            public function all(): array
            {
                return [];
            }

            public function exists(string $id): bool
            {
                return $id === 'question-1';
            }
        };
        $feedbackRepository = new class implements QuestionFeedbackRepository {
            /** @var QuestionFeedback[] */
            public array $recorded = [];

            public function record(QuestionFeedback $feedback): void
            {
                $this->recorded[] = $feedback;
            }

            public function listRatedQuestionIds(string $deckId): array
            {
                self::fail('not exercised by this test');
            }
        };

        $useCase = new RecordQuestionFeedback($questions, $feedbackRepository);
        $feedback = new QuestionFeedback(
            id: 'feedback-1',
            questionId: 'question-1',
            deckId: 'deck-1',
            rating: Rating::Negative,
            freeText: 'not great',
        );

        $useCase->handle($feedback);

        self::assertSame([$feedback], $feedbackRepository->recorded);
    }
}
