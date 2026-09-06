<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Http;

use Paarfragen\Application\RecordQuestionFeedback;
use Paarfragen\Domain\Exception\UnknownQuestion;
use Paarfragen\Domain\QuestionFeedback;
use Paarfragen\Domain\Rating;
use Tempest\Http\GenericResponse;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Created;
use Tempest\Http\Responses\NotFound;
use Tempest\Http\Status;
use Tempest\Router\Post;
use Tempest\Router\Stateless;
use Tempest\Validation\Rules\IsIn;
use Tempest\Validation\Rules\IsUuid;

/**
 * Manual field-by-field validation instead of a Tempest Request
 * subclass on purpose: the automatic validation pipeline responds with
 * its own 422 + X-Validation envelope, which doesn't match the 400 +
 * `{"error":{"message":...}}` contract specs/api.md already locks in.
 * Reuses Tempest's own Rule classes for the checks themselves, just not
 * its response format. specs/2026-09-06-slice-2-questions-feedback-persistence.md.
 *
 * `#[Stateless]`: see QuestionController — no session cookie, no CSRF
 * middleware blocking a cross-origin bearer-token API call.
 */
#[Stateless]
final readonly class QuestionFeedbackController
{
    public function __construct(
        private RecordQuestionFeedback $recordQuestionFeedback,
    ) {}

    #[Post('/question-feedback')]
    public function store(Request $request): Response
    {
        // @mago-expect analysis:mixed-assignment
        $id = $request->get('id');
        if (!is_string($id) || !new IsUuid()->isValid($id)) {
            return $this->badRequest('id must be a UUID.');
        }

        // @mago-expect analysis:mixed-assignment
        $questionId = $request->get('question_id');
        if (!is_string($questionId) || !new IsUuid()->isValid($questionId)) {
            return $this->badRequest('question_id must be a UUID.');
        }

        // @mago-expect analysis:mixed-assignment
        $deckId = $request->get('deck_id');
        if (!is_string($deckId) || !new IsUuid()->isValid($deckId)) {
            return $this->badRequest('deck_id must be a UUID.');
        }

        // @mago-expect analysis:mixed-assignment
        $rating = $request->get('rating');
        $validRatings = array_map(static fn(Rating $case): int => $case->value, Rating::cases());
        if (!is_int($rating) || !new IsIn($validRatings)->isValid($rating)) {
            return $this->badRequest('rating must be one of -5, -1, 1, 5.');
        }

        // @mago-expect analysis:mixed-assignment
        $freeText = $request->get('free_text');
        if ($freeText !== null && !is_string($freeText)) {
            return $this->badRequest('free_text must be a string or null.');
        }

        $feedback = new QuestionFeedback(
            id: $id,
            questionId: $questionId,
            deckId: $deckId,
            rating: Rating::from($rating),
            freeText: $freeText,
        );

        try {
            $this->recordQuestionFeedback->handle($feedback);
        } catch (UnknownQuestion $exception) {
            return new NotFound(['error' => ['message' => $exception->getMessage()]]);
        }

        return new Created();
    }

    private function badRequest(string $message): GenericResponse
    {
        return new GenericResponse(status: Status::BAD_REQUEST, body: ['error' => ['message' => $message]]);
    }
}
