<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Mcp;

use Paarfragen\Application\ListAppFeedback;
use Paarfragen\Application\MarkAppFeedbackHandled;
use Tempest\Mcp\McpServer;
use Tempest\Mcp\McpTool;

/**
 * Triage side of app-feedback, for a Claude session — not a REST
 * endpoint (specs/2026-09-06-slice-4-app-feedback.md). `path: '/mcp'`
 * registers this as a normal route on the same `api/` deployment, no
 * separate infrastructure.
 *
 * Protection is NOT a `#[WithMiddleware]` decorator on this class —
 * that has no effect here. Tempest's `McpDiscovery::registerRoutes()`
 * always points every discovered server's route at the same generic
 * `Tempest\Mcp\McpHttpController`, with a hardcoded decorator list
 * that never reads decorators off the `#[McpServer]` class. See
 * `Infrastructure/Http/McpAuthMiddleware.php` for the actual
 * mechanism (a normally-discovered, global middleware that no-ops
 * unless the request path is this server's own `/mcp`).
 */
#[McpServer(path: '/mcp')]
final readonly class AppFeedbackServer
{
    public function __construct(
        private ListAppFeedback $listFeedback,
        private MarkAppFeedbackHandled $markHandled,
    ) {}

    /** @return list<array{id: string, deck_id: string, free_text: string, created_at: ?string}> */
    #[McpTool(description: 'Lists app-feedback rows not yet triaged (handled_at IS NULL).')]
    public function listAppFeedback(): array
    {
        $rows = [];

        foreach ($this->listFeedback->handle() as $feedback) {
            $rows[] = [
                'id' => $feedback->id,
                'deck_id' => $feedback->deckId,
                'free_text' => $feedback->freeText,
                'created_at' => $feedback->createdAt?->format('Y-m-d H:i:s'),
            ];
        }

        return $rows;
    }

    /**
     * No-op, not an error, if `$id` is already handled or unknown — a
     * triage run re-executed against the same list shouldn't fail on
     * a row an earlier call already processed.
     */
    #[McpTool(description: 'Marks one app-feedback row as handled/triaged, by id.')]
    public function markFeedbackHandled(string $id): string
    {
        $this->markHandled->handle($id);

        return "Marked {$id} as handled.";
    }
}
