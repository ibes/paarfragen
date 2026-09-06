<?php

declare(strict_types=1);

namespace Paarfragen\Tests\Infrastructure;

use Paarfragen\Infrastructure\Mcp\AppFeedbackServer;
use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Framework\Testing\IntegrationTest;
use Tempest\Http\Status;

use function Tempest\Support\Random\uuid;

final class AppFeedbackMcpTest extends IntegrationTest
{
    #[PreCondition]
    protected function configure(): void
    {
        $this->database->setup();
    }

    #[Test]
    public function lists_only_unhandled_feedback(): void
    {
        $freeText = 'The end-state message is confusing — ' . uuid();
        $this->submitAppFeedback($freeText);

        $this->mcp
            ->onServer(AppFeedbackServer::class)
            ->callTool('list_app_feedback')
            ->assertOk()
            ->assertTextContains($freeText);
    }

    #[Test]
    public function marking_handled_removes_it_from_the_list(): void
    {
        $id = uuid();
        $freeText = 'A row that gets marked handled — ' . uuid();
        $this->submitAppFeedback($freeText, $id);

        $connection = $this->mcp->onServer(AppFeedbackServer::class);
        $connection->callTool('list_app_feedback')->assertOk()->assertTextContains($freeText);

        $connection->callTool('mark_feedback_handled', ['id' => $id])->assertOk();

        $afterMarking = $connection->callTool('list_app_feedback')->assertOk();
        self::assertStringNotContainsString($freeText, $afterMarking->result()['content'][0]['text'] ?? '');
    }

    #[Test]
    public function marking_an_unknown_id_handled_is_a_no_op_not_an_error(): void
    {
        $this->mcp->onServer(AppFeedbackServer::class)->callTool('mark_feedback_handled', ['id' => uuid()])->assertOk();
    }

    private function submitAppFeedback(string $freeText, ?string $id = null): void
    {
        $this->http
            ->post('/app-feedback', [
                'id' => $id ?? uuid(),
                'deck_id' => uuid(),
                'free_text' => $freeText,
            ])
            ->assertStatus(Status::CREATED);
    }
}
