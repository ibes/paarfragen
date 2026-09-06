<?php

declare(strict_types=1);

namespace Paarfragen\Infrastructure\Persistence;

use Paarfragen\Application\QuestionRepository;
use Paarfragen\Domain\Question;
use Tempest\Container\Autowire;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

#[Autowire]
final readonly class DatabaseQuestionRepository implements QuestionRepository
{
    public function all(): array
    {
        $questions = [];

        // query()'s own @template binds `TModel` to the literal
        // class-string passed in, not to instances of it (Tempest's
        // `@param TModel $model` on `query()` should read `class-string<TModel>|TModel`
        // — see FRICTION.md), so mago mistypes every row here as a
        // class-string rather than a QuestionModel. Not a real bug in
        // this loop; documented as a known stub gap instead of worked
        // around with fake types.
        // @mago-expect analysis:invalid-property-access
        // @mago-expect analysis:invalid-property-access
        // @mago-expect analysis:null-argument
        foreach (query(QuestionModel::class)->select()->all() as $row) {
            $questions[] = new Question(id: (string) $row->id, text: $row->text);
        }

        return $questions;
    }

    public function exists(string $id): bool
    {
        return query(QuestionModel::class)->select()->get(new PrimaryKey($id)) !== null;
    }
}
