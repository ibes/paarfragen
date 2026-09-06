# paarfragen — API

Hexagonal PHP core on [Tempest](https://tempestphp.com). Used here as a
**pure JSON API**: no server-rendered views, no `vite-plugin-tempest`
asset bundling — the frontend is a separate Vue/Vite project
(`../frontend`) that talks to this over HTTP only.

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

## Requires PHP ^8.5 — runs in a container, not on the host

Tempest ^3.0 requires PHP ^8.5. Rather than depend on whatever PHP the
host/VM happens to ship (a Claude Code cloud session's VM, a contributor's
laptop, CI), every `script/*` that touches `api/` runs it inside the
`api` service defined in `../docker-compose.yml` /
`../.devcontainer/Dockerfile` (`php:8.5-cli-trixie` + the extensions
Tempest needs) via `script/lib/api-php`. You never need PHP 8.5 installed
locally — you need Docker.

The same image is also the local VS Code Dev Container for the whole
repo (`../.devcontainer/devcontainer.json` points at this same `api`
service) — open the repo in a Dev Container and you get PHP 8.5, Node,
and the CLI tools in `.devcontainer/Dockerfile` in one shell.

Not yet build-tested end-to-end (no working Docker daemon in the sandbox
this was set up in) — see `../specs/STATUS.md` § Known quirks before
relying on it.

## Toolchain

Via `../script/*`, not composer directly — see `../script/help`.
