# STATUS

Session router — read this first, every session. History and full
reasoning behind any decision below live in `SETUP-LOG.md`; this file
is the current state, kept short on purpose.

## Right now

**First fully green `script/qa` for this repo.** `deb.debian.org` and
`deb.nodesource.com` are now on the environment's Custom network
allowlist (alongside the already-fixed
`production.cloudfront.docker.com`). This session confirmed that fix,
then found and fixed two more layers of blocker that were hiding
behind it — neither needed a human/policy change:

1. **Extension bug the network blocker had been masking:**
   `docker-php-ext-install` was building `pdo_sqlite`, `sqlite3`,
   `curl`, `dom`, `simplexml`, `readline` as shared extensions, but
   `php:8.5-cli-trixie` already bundles all of them (`php -m` confirms
   it) — only `intl` was actually missing. PHP 8.5 ships the others'
   `config.m4` as `config0.m4` (a stub only the top-level `./buildconf`
   expands, for extensions meant to be built into core), so `phpize`
   can't build them standalone and `docker-php-ext-install` failed with
   "Cannot find config.m4." Fixed: only install `intl` now (see
   `SETUP-LOG.md`).
2. **A container-trust gap, not a policy block:** past the apt stage,
   the Dockerfile's `curl -fsSL https://deb.nodesource.com/...` failed
   with "self-signed certificate in certificate chain". Verified with
   `--cacert` against `/root/.ccr/ca-bundle.crt` that nodesource was
   never blocked (real `200`) — the actual cause, confirmed against
   every other HTTPS host too (npmjs, github, packagist, even
   `deb.debian.org` itself over HTTPS), is that plain containers in
   this sandbox don't trust its TLS-inspecting egress CA for *any*
   host. `/root/.ccr/README.md`'s "docker build / docker run" section
   documents this as standard for Claude Code Remote sessions, with
   the fix it names. Applied it so it's a no-op outside this sandbox:
   `session-start.sh` now stages `/root/.ccr/ca-bundle.crt` into a
   gitignored `.devcontainer/session-ca.crt` before the build, and the
   Dockerfile trusts it only if that file is present. Full details and
   the exact commands used to verify both: `SETUP-LOG.md`.
3. One more small, unrelated fix needed to reach green: `npm install -g
   @ast-grep/cli` collided with Debian's `/usr/bin/sg` — fixed with
   `--force`.

**Verified state, this session:** `docker compose build api`,
`script/test-api`, and `script/qa` all green end-to-end, via
`session-start.sh`'s own automatic flow (confirmed by re-running it
from a cold daemon) as well as by hand. No open host or extension
blocker remains as far as this session found.

**If a future session hits a build failure again:** don't assume it's
the network allowlist by default — check first whether it's actually a
container CA-trust gap (same fix as above, harmless to reapply) or an
extension/package issue (check `php -m` and the base image's real
`ext/` contents before assuming a name changed, same approach as
entry 1). Only fall back to the certificate-issuer / `--cacert` probe
from the previous version of this entry (kept in `SETUP-LOG.md`) if a
*new* host is genuinely unreachable even with the gateway's CA
trusted.

## Phase

**Slice 2 built, Slice 3 spec locked.** `api/src/` has its first real
Domain/Application/Infrastructure code: `GET /questions` and
`POST /question-feedback`, backed by SQLite, per
[`specs/2026-09-06-slice-2-questions-feedback-persistence.md`](2026-09-06-slice-2-questions-feedback-persistence.md).
`script/qa` is green end-to-end, including 9 PHPUnit tests (2
Application unit + 7 Infrastructure integration) and a manual live
smoke test against a real `php -S` server. `frontend/` is still the
scaffold only — nothing there calls the real API yet — but Slice 3's
spec (wiring the core loop against it) is now locked; building it is
next.

A pre-spec design input exists — [`specs/exploration-mode.md`](exploration-mode.md):
a single shared-device question deck, rating loop, `deck_id` bearer
identity, no accounts. It's a design input, not a spec: restate what's
needed from it into a real spec here before building against it. It
says nothing about tech stack; every stack decision below came from
the human directly.

## Next step

**Slice 3 spec locked:**
[`specs/2026-09-06-slice-3-frontend-api-wiring.md`](2026-09-06-slice-3-frontend-api-wiring.md)
— grilled with the human first (`grill-me`), self-contained, one
reasoning line per decision. Scope: the core question/rating loop in
`frontend/`, wired against Slice 2's real `GET /questions` +
`POST /question-feedback`, with a client-generated `deck_id` and an
offline-tolerant local queue for rating submissions. Branch:
`claude/ibes-paarfragen-frontend-api-wiring`.

Next: build it. `frontend/src/` is still the Vite scaffold only
(`App.vue`/`main.ts`/`style.css`) — this is the first real frontend
code. Follow the slice spec. Done when `script/qa` is green
end-to-end, per `CLAUDE.md`.

`api/`'s remaining scope (`GET /question-feedback`,
`POST /generate-question`, `POST /app-feedback`) stays deferred — see
§ Open decisions.

## Decided

- **API framework: Tempest** (`^3.0`), used as a pure JSON API — no
  server-rendered views, no `vite-plugin-tempest`. `api/README.md`.
- **`api/` and `frontend/` stay decoupled** — sibling top-level dirs,
  independent toolchains — so the frontend can later be wrapped in a
  native shell (e.g. Capacitor) without touching the API's deploy
  lifecycle.
- **`api/` (PHP 8.5) runs in Docker, not on the host** —
  `docker-compose.yml` + `.devcontainer/Dockerfile`, invoked via
  `script/lib/api-php`. Same image doubles as the local VS Code Dev
  Container. See "Docker for `api/`" below for current status.
- **API contract drafted ahead of implementation** — `specs/api.md`.
- **Repo content is English** (docs, code, comments, commits) —
  `CLAUDE.md`. The product's own end-user UI language is a separate,
  still-open decision — see below.
- **Slice 2 scope: `GET /questions` + `POST /question-feedback` only** —
  real `api/` implementation, everything else in `specs/api.md`
  (`GET /question-feedback`, `POST /generate-question`,
  `POST /app-feedback`) deferred to a later slice. Grilled and locked
  in `specs/2026-09-06-slice-2-questions-feedback-persistence.md`.
- **Auth model confirmed:** `deck_id` bearer, format-validated as a
  UUID (400 if malformed), never looked up against a table — there is
  no `decks` table. `GET /questions` is global data and needs no
  `deck_id` at all. `specs/api.md`.
- **Data storage: SQLite file**, no separate DB service in
  `docker-compose.yml`. Seed questions ship embedded in a DB migration
  (`source: {"type":"seed"}`), not a separate seed script.
- **Server-generated IDs (`questions.id`) are UUIDv7.** Client-generated
  IDs (`question_feedback.id`, `app_feedback.id`) stay the frontend's
  own choice. `specs/api.md`.
- **Deploy target's PHP version: `^8.5`, confirmed** — matches
  `api/composer.json`'s existing requirement; no longer open.
- **Slice 3 scope: the core question/rating loop only** — no
  "new topic" input, no app-feedback entry point (their endpoints
  don't exist yet). `deck_id` is generated client-side on first run
  and persisted to `localStorage`, not hardcoded — corrects
  `specs/exploration-mode.md`'s original design (a single hardcoded
  value would mean every install shares one deck). Rating submissions
  queue locally and flush on a threshold/online-event/app-start, never
  POST instantly. Plain Vue composable for state, native `fetch()`, no
  Pinia/HTTP library. Real PWA app-shell offline (icons,
  service-worker precaching) deferred. Grilled and locked in
  `specs/2026-09-06-slice-3-frontend-api-wiring.md`.

## Open decisions (not yet made — ask before assuming)

- **Where `generate:typescript-types` (Tempest → TS type generation,
  `specs/api.md`) writes its output** — deferred until real
  Infrastructure DTOs exist to generate from.
- **`GET /question-feedback`, `POST /generate-question`,
  `POST /app-feedback`** — not in Slice 2's scope; exact 4xx codes,
  success status, and idempotency shape for these three still need
  deciding when a later slice actually builds them.
- **End-user UI language** — repo content is English by house rule,
  but the product's name and its original design input are German; a
  German-language UI is plausible but not decided. Current placeholder
  frontend text is English only because it's a placeholder. Decide
  before writing real UI copy.

## Docker for `api/` — current status

**Green end-to-end as of this session** — `docker compose build api`,
`script/test-api`, and `script/qa` all pass. What's confirmed:

- The Docker **daemon itself works** in this dev sandbox (`sudo dockerd`
  directly, not `sudo service docker start` — that init script hits a
  `ulimit` call this sandbox disallows). `.claude/hooks/session-start.sh`
  starts it automatically and, from a cold daemon, takes a fresh session
  all the way through image build + `composer install` + `npm install`
  with no manual step — confirmed end-to-end this session.
- **All three network hosts the build needs are allowed:**
  `production.cloudfront.docker.com` (Docker Hub pulls),
  `deb.debian.org` and `deb.nodesource.com` (apt). Everything past the
  apt stages — `repo.packagist.org`, `api.github.com` /
  `codeload.github.com`, `github.com`, `registry.npmjs.org` — is
  reachable too.
- **Containers don't trust this sandbox's TLS-inspecting egress CA by
  default** — a standing property of Claude Code Remote sessions
  (`/root/.ccr/README.md`, "docker build / docker run"), not a policy
  gap, and it affects any HTTPS call from inside a container regardless
  of host. `session-start.sh` now stages `/root/.ccr/ca-bundle.crt` into
  the build context before building, and the Dockerfile trusts it
  conditionally — a no-op outside this sandbox. See "Right now" above
  and `SETUP-LOG.md` for the full finding.
- `docker-php-ext-install` only builds `intl` now — the rest of what
  the dependency tree needs is already bundled into `php:8.5-cli-trixie`
  (see "Right now" above). `script/check` runs `composer
  check-platform-reqs` to verify this against the real vendor tree on
  every run, instead of trusting a comment that could go stale.
- `docker-compose.yml` parses correctly (`docker compose config`); the
  Dockerfile's package/extension list is now proven by an actual
  successful build, not just grounded in Tempest's `composer.json`.
- Not yet done and not fixable from inside a session: add
  `docker compose build api` to this environment's **Setup script**
  field (claude.ai/code environment dialog — a different mechanism from
  this repo's `.claude/hooks/session-start.sh`) so the image is cached
  across sessions instead of rebuilding every time.

Full narrative (the PHP-PPA dead end tried first, the corrected
"no working daemon" claim, the extension and CA-trust findings,
everything ruled out along the way) is in `SETUP-LOG.md` — not
repeated here on purpose.

## Other known quirks

- GitHub's API rate-limited anonymous dist downloads through an
  earlier dev sandbox's proxy during a `composer update` run (before
  the Docker approach existed) — composer fell back to cloning from
  git source successfully; may not recur elsewhere.
- `frontend/vite.config.ts` ships `vite-plugin-pwa` with an empty
  `icons: []` — no real app icons exist yet (see the TODO next to it).
  Add 192×192 and 512×512 PNGs before treating the PWA as installable.
- `api/tests/*` and `api/src/*` subdirectories are empty (`.gitkeep`
  only) — first real code should come from a spec, not ad-hoc.
