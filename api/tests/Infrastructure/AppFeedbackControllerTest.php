<?php

declare(strict_types=1);

namespace Paarfragen\Tests\Infrastructure;

use PHPUnit\Framework\Attributes\PreCondition;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Framework\Testing\IntegrationTest;
use Tempest\Http\Status;

use function Tempest\Support\Random\uuid;

final class AppFeedbackControllerTest extends IntegrationTest
{
    #[PreCondition]
    protected function configure(): void
    {
        $this->database->setup();
    }

    #[Test]
    public function records_app_feedback(): void
    {
        $this->http
            ->post('/app-feedback', [
                'id' => uuid(),
                'deck_id' => uuid(),
                'free_text' => 'The rating scale feels a bit narrow.',
            ])
            ->assertStatus(Status::CREATED);
    }

    #[Test]
    public function retrying_the_same_id_is_idempotent(): void
    {
        $body = [
            'id' => uuid(),
            'deck_id' => uuid(),
            'free_text' => 'Same feedback, retried after a dropped connection.',
        ];

        $this->http->post('/app-feedback', $body)->assertStatus(Status::CREATED);
        $this->http->post('/app-feedback', $body)->assertStatus(Status::CREATED);
    }

    #[Test]
    public function malformed_id_is_a_400(): void
    {
        $this->http
            ->post('/app-feedback', [
                'id' => 'not-a-uuid',
                'deck_id' => uuid(),
                'free_text' => 'Feedback text.',
            ])
            ->assertStatus(Status::BAD_REQUEST);
    }

    #[Test]
    public function malformed_deck_id_is_a_400(): void
    {
        $this->http
            ->post('/app-feedback', [
                'id' => uuid(),
                'deck_id' => 'not-a-uuid',
                'free_text' => 'Feedback text.',
            ])
            ->assertStatus(Status::BAD_REQUEST);
    }

    #[Test]
    public function missing_free_text_is_a_400(): void
    {
        $this->http
            ->post('/app-feedback', [
                'id' => uuid(),
                'deck_id' => uuid(),
            ])
            ->assertStatus(Status::BAD_REQUEST);
    }

    #[Test]
    public function empty_free_text_is_a_400(): void
    {
        $this->http
            ->post('/app-feedback', [
                'id' => uuid(),
                'deck_id' => uuid(),
                'free_text' => '   ',
            ])
            ->assertStatus(Status::BAD_REQUEST);
    }
}
