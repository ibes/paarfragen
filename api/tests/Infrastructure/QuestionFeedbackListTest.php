<?php

declare(strict_types=1);

namespace Paarfragen\Tests\Infrastructure;

use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Framework\Testing\IntegrationTest;
use Tempest\Http\Status;

use function Tempest\Support\Random\uuid;

/**
 * `GET /question-feedback` — reconstructs "already rated" state, not
 * rating history. specs/2026-09-06-slice-6-question-feedback-reconstruction.md.
 * Split from QuestionFeedbackControllerTest (the POST/write side) to
 * stay under mago's too-many-methods lint, same pattern as
 * AppFeedbackControllerTest/AppFeedbackMcpTest/McpAuthMiddlewareTest.
 */
final class QuestionFeedbackListTest extends IntegrationTest
{
    #[PreCondition]
    protected function configure(): void
    {
        $this->database->setup();
    }

    #[Test]
    public function a_deck_with_no_ratings_gets_an_empty_list(): void
    {
        $this->http->get('/question-feedback', ['deck_id' => uuid()])->assertOk()->assertJson([]);
    }

    #[Test]
    public function lists_a_rated_question_id_for_the_deck_that_rated_it(): void
    {
        $deckId = uuid();
        $questionId = $this->firstSeededQuestionId();

        $this->http
            ->post('/question-feedback', [
                'id' => uuid(),
                'question_id' => $questionId,
                'deck_id' => $deckId,
                'rating' => 5,
                'free_text' => null,
            ])
            ->assertStatus(Status::CREATED);

        $this->http->get('/question-feedback', ['deck_id' => $deckId])->assertOk()->assertJson([$questionId]);
    }

    #[Test]
    public function a_re_rated_question_appears_only_once(): void
    {
        $deckId = uuid();
        $questionId = $this->firstSeededQuestionId();

        $this->http
            ->post('/question-feedback', [
                'id' => uuid(),
                'question_id' => $questionId,
                'deck_id' => $deckId,
                'rating' => 1,
                'free_text' => null,
            ])
            ->assertStatus(Status::CREATED);
        $this->http
            ->post('/question-feedback', [
                'id' => uuid(),
                'question_id' => $questionId,
                'deck_id' => $deckId,
                'rating' => 5,
                'free_text' => 'changed my mind',
            ])
            ->assertStatus(Status::CREATED);

        $this->http->get('/question-feedback', ['deck_id' => $deckId])->assertOk()->assertJson([$questionId]);
    }

    #[Test]
    public function ratings_are_scoped_to_their_own_deck(): void
    {
        $ratedDeckId = uuid();
        $otherDeckId = uuid();
        $questionId = $this->firstSeededQuestionId();

        $this->http
            ->post('/question-feedback', [
                'id' => uuid(),
                'question_id' => $questionId,
                'deck_id' => $ratedDeckId,
                'rating' => 5,
                'free_text' => null,
            ])
            ->assertStatus(Status::CREATED);

        $this->http->get('/question-feedback', ['deck_id' => $otherDeckId])->assertOk()->assertJson([]);
    }

    #[Test]
    public function malformed_deck_id_is_a_400(): void
    {
        $this->http->get('/question-feedback', ['deck_id' => 'not-a-uuid'])->assertStatus(Status::BAD_REQUEST);
    }

    private function firstSeededQuestionId(): string
    {
        return $this->http->get('/questions')->assertOk()->body[0]['id'];
    }
}
