# paarfragen — API

Hexagonal PHP core on [Tempest](https://tempestphp.com) — same framework as
the `emsig` sibling repo. Used here as a **pure JSON API**: no
server-rendered views, no `vite-plugin-tempest` asset bundling — the
frontend is a separate Vue/Vite project (`../frontend`) that talks to this
over HTTP only.

```
public/
  index.php        Tempest boot entrypoint
src/
  Domain/          framework-free entities, value objects, domain services
  Application/     use cases orchestrating the domain (ports in, ports out)
  Infrastructure/  adapters: HTTP controllers, persistence, everything
                   framework-specific — this is where Tempest lives
```

Domain and Application stay framework-free; only Infrastructure may depend
on Tempest, a database driver, or the outside world. A route is a Tempest
controller under `src/Infrastructure/<Feature>/`, e.g.:

```php
final readonly class QuestionController
{
    public function __construct(private ListQuestions $listQuestions) {}

    #[Get('/questions')]
    public function index(): array
    {
        return $this->listQuestions->handle();
    }
}
```

No such controller exists yet — first slice needs a spec first, see
`../specs/STATUS.md`.

## Requires PHP ^8.5

Tempest ^3.0 requires PHP ^8.5. `composer.lock` was generated against that
requirement; it could not be installed/verified in the sandbox this repo
was scaffolded in (PHP 8.4.19 only, and GitHub's API rate-limited dist
downloads through that sandbox's proxy) — run `../script/setup` and
`../script/qa` on a machine or CI with PHP 8.5 to actually validate.

## Toolchain

Via `../script/*`, not composer directly — see `../script/help`.
