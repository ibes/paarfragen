<?php

declare(strict_types=1);

namespace Paarfragen\Tests\Infrastructure;

use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Framework\Testing\IntegrationTest;
use Tempest\Http\Status;

use function Tempest\Support\Random\uuid;

final class QuestionFeedbackControllerTest extends IntegrationTest
{
    #[PreCondition]
    protected function configure(): void
    {
        $this->database->setup();
    }

    #[Test]
    public function records_feedback_for_a_seeded_question(): void
    {
        $this->http
            ->post('/question-feedback', [
                'id' => uuid(),
                'question_id' => $this->firstSeededQuestionId(),
                'deck_id' => uuid(),
                'rating' => 5,
                'free_text' => 'Loved this one.',
            ])
            ->assertStatus(Status::CREATED);
    }

    #[Test]
    public function retrying_the_same_id_is_idempotent(): void
    {
        $body = [
            'id' => uuid(),
            'question_id' => $this->firstSeededQuestionId(),
            'deck_id' => uuid(),
            'rating' => 1,
            'free_text' => null,
        ];

        $this->http->post('/question-feedback', $body)->assertStatus(Status::CREATED);
        $this->http->post('/question-feedback', $body)->assertStatus(Status::CREATED);
    }

    #[Test]
    public function unknown_question_id_is_a_404(): void
    {
        $this->http
            ->post('/question-feedback', [
                'id' => uuid(),
                'question_id' => uuid(),
                'deck_id' => uuid(),
                'rating' => -5,
                'free_text' => null,
            ])
            ->assertStatus(Status::NOT_FOUND);
    }

    #[Test]
    public function malformed_deck_id_is_a_400(): void
    {
        $this->http
            ->post('/question-feedback', [
                'id' => uuid(),
                'question_id' => $this->firstSeededQuestionId(),
                'deck_id' => 'not-a-uuid',
                'rating' => 5,
                'free_text' => null,
            ])
            ->assertStatus(Status::BAD_REQUEST);
    }

    #[Test]
    public function invalid_rating_is_a_400(): void
    {
        $this->http
            ->post('/question-feedback', [
                'id' => uuid(),
                'question_id' => $this->firstSeededQuestionId(),
                'deck_id' => uuid(),
                'rating' => 3,
                'free_text' => null,
            ])
            ->assertStatus(Status::BAD_REQUEST);
    }

    private function firstSeededQuestionId(): string
    {
        return $this->http->get('/questions')->assertOk()->body[0]['id'];
    }
}
