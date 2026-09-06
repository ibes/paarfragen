<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Http;

use Paarfragen\Application\RecordAppFeedback;
use Paarfragen\Domain\AppFeedback;
use Tempest\Http\GenericResponse;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Created;
use Tempest\Http\Status;
use Tempest\Router\Post;
use Tempest\Router\Stateless;
use Tempest\Validation\Rules\IsUuid;

/**
 * Manual field-by-field validation, same reasoning as
 * QuestionFeedbackController: matches specs/api.md's `400 +
 * {"error":{"message":...}}` contract instead of Tempest's own
 * validation-pipeline response shape.
 *
 * `#[Stateless]`: see QuestionController — no session cookie, no CSRF
 * middleware blocking a cross-origin bearer-token API call.
 */
#[Stateless]
final readonly class AppFeedbackController
{
    public function __construct(
        private RecordAppFeedback $recordAppFeedback,
    ) {}

    #[Post('/app-feedback')]
    public function store(Request $request): Response
    {
        // @mago-expect analysis:mixed-assignment
        $id = $request->get('id');
        if (!is_string($id) || !new IsUuid()->isValid($id)) {
            return $this->badRequest('id must be a UUID.');
        }

        // @mago-expect analysis:mixed-assignment
        $deckId = $request->get('deck_id');
        if (!is_string($deckId) || !new IsUuid()->isValid($deckId)) {
            return $this->badRequest('deck_id must be a UUID.');
        }

        // @mago-expect analysis:mixed-assignment
        $freeText = $request->get('free_text');
        if (!is_string($freeText) || trim($freeText) === '') {
            return $this->badRequest('free_text is required and must not be empty.');
        }

        $feedback = new AppFeedback(id: $id, deckId: $deckId, freeText: $freeText);

        $this->recordAppFeedback->handle($feedback);

        return new Created();
    }

    private function badRequest(string $message): GenericResponse
    {
        return new GenericResponse(status: Status::BAD_REQUEST, body: ['error' => ['message' => $message]]);
    }
}
