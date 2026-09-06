<?php

declare(strict_types=1);

namespace Paarfragen\Tests\Infrastructure;

use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Framework\Testing\IntegrationTest;

final class QuestionControllerTest extends IntegrationTest
{
    #[PreCondition]
    protected function configure(): void
    {
        $this->database->setup();
    }

    #[Test]
    public function lists_seeded_questions_as_id_and_text_only(): void
    {
        $questions = $this->http->get('/questions')->assertOk()->body;

        self::assertCount(8, $questions);
        self::assertSame(['id', 'text'], array_keys($questions[0]));
    }

    #[Test]
    public function needs_no_deck_id(): void
    {
        // questions is global data, not deck-scoped — specs/api.md.
        $this->http->get('/questions')->assertOk();
    }
}
