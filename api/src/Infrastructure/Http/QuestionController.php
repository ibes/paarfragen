<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Http;

use Paarfragen\Application\ListQuestions;
use Paarfragen\Domain\Question;
use Tempest\Router\Get;
use Tempest\Router\Stateless;

/**
 * `#[Stateless]`: no login, no session — `deck_id` is a bearer value
 * carried explicitly per request, never an ambient session cookie
 * (specs/exploration-mode.md § Identity). Without this, Tempest sets a
 * session cookie on every response by default and blocks cross-origin
 * calls via its CSRF middleware — both wrong for a decoupled `api/`
 * this frontend may call from a different origin.
 */
#[Stateless]
final readonly class QuestionController
{
    public function __construct(
        private ListQuestions $listQuestions,
    ) {}

    /**
     * No `deck_id`: `questions` is global data, not deck-scoped —
     * specs/api.md.
     *
     * @return array<array-key, array{id: string, text: string}>
     */
    #[Get('/questions')]
    public function index(): array
    {
        return array_map(
            /** @return array{id: string, text: string} */
            static fn(Question $question): array => [
                'id' => $question->id,
                'text' => $question->text,
            ],
            $this->listQuestions->handle(),
        );
    }
}
