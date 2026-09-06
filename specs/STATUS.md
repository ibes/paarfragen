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

**Slices 2 and 3 both built; Slice 4 spec locked, not yet built.** `api/src/` has its first real
Domain/Application/Infrastructure code: `GET /questions` and
`POST /question-feedback`, backed by SQLite, per
[`specs/2026-09-06-slice-2-questions-feedback-persistence.md`](2026-09-06-slice-2-questions-feedback-persistence.md).
`frontend/src/` now has its first real code too: the core
question/rating loop wired against that API, per
[`specs/2026-09-06-slice-3-frontend-api-wiring.md`](2026-09-06-slice-3-frontend-api-wiring.md)
— a client-generated `deck_id`, a `localStorage`-persisted offline
queue for ratings, a plain Vue composable, no Pinia. `script/qa` is
green end-to-end (9 PHPUnit + 16 Vitest tests, `vue-tsc`, the frontend
build), and the whole loop was verified live in a real browser against
both real dev servers (`script/dev-api` + `npm run dev`) — including a
rating actually landing in the SQLite database after a queue flush.
That live pass caught a real gap unit tests couldn't: `api/` had no
CORS headers, so every cross-origin `fetch()` from `frontend/` was
silently blocked by the browser. Fixed with
`api/src/Infrastructure/Http/CorsMiddleware.php` — see `FRICTION.md`
and `api/reference/tempest.md` for the middleware-priority gotcha that
came with it (a CORS preflight has to be intercepted *before*
Tempest's own route-matching middleware, not after).

A pre-spec design input exists — [`specs/exploration-mode.md`](exploration-mode.md):
a single shared-device question deck, rating loop, `deck_id` bearer
identity, no accounts. It's a design input, not a spec: restate what's
needed from it into a real spec here before building against it. It
says nothing about tech stack; every stack decision below came from
the human directly.

## Next step

**Slice 4 (App-Feedback: submit, queue, MCP-driven triage) is locked**
(`specs/2026-09-06-slice-4-app-feedback.md`) — not yet implemented.
Grilled 2026-09-06: `POST /app-feedback` + a small always-reachable
frontend entry point (as Slice 2/3 deferred), plus a `tempest/mcp`
server (`AppFeedbackServer`) exposing `listAppFeedback`/
`markFeedbackHandled` tools so feedback can be triaged directly from a
Claude session, protected by a dedicated bearer-token +
signed-timestamp middleware on the `/mcp` route only.

After Slice 4, still not decided between:

- **Real PWA app-shell offline** — icons, service-worker precaching,
  installability. Slice 3 deliberately deferred this (its own spec's
  "explicitly out of scope").
- **Extend `api/`'s scope further** — `GET /question-feedback`,
  `POST /generate-question` (needs an LLM-provider decision first).
- **What replaces the end-state message** once every cached question
  is rated — reshuffle or AI-generated questions, the human's own
  framing during Slice 3's grill, explicitly not solved yet.
- **Real use** — the app is usable now; actually using it with a real
  couple is itself a way to learn what's missing, per
  `specs/exploration-mode.md`'s own framing of exploration mode as a
  research instrument.

Ask the human before picking one and grilling a new slice spec — same
reasoning as before Slice 3/4, `VALUES.md` § Product over system.

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
- **`api/` sends `Access-Control-Allow-Origin: *`** (not an
  allow-list) — `deck_id` travels only in request bodies/query params,
  never a cookie, so there's no ambient credential for another origin
  to piggyback on. Needed once `frontend/` (a different origin in dev
  and, per the decoupled-directories decision above, likely in
  production too) started making real cross-origin requests — found
  missing by Slice 3's live browser smoke test, not by any automated
  test. `api/src/Infrastructure/Http/CorsMiddleware.php`.
- **Slice 4 scope: `POST /app-feedback` (write) + its frontend entry
  point + an MCP-based triage side, one slice** — `free_text` required
  (unlike `question_feedback`'s nullable one), `201` on success,
  app-feedback's own immediate-then-fallback-queue offline behavior
  (not `question_feedback`'s threshold queue), `handled_at` marks a
  row triaged rather than deleting it. The triage/read side uses
  Tempest's built-in `tempest/mcp` component (experimental, but no
  separate infrastructure — one more route on the same `api/`
  deployment) instead of a `GET` endpoint, protected by a
  bearer-token + signed-timestamp middleware. That middleware is
  **global** (like `CorsMiddleware`), not a `#[WithMiddleware]` route
  decorator as originally planned — Tempest's own MCP route
  registration ignores decorators on the `#[McpServer]` class, found
  during implementation, not in the docs; the middleware scopes itself
  to `/mcp` internally instead. No IP/domain restriction — not
  reliably implementable (no stable published IP range for MCP client
  traffic). Grilled and locked in
  `specs/2026-09-06-slice-4-app-feedback.md`.

## Open decisions (not yet made — ask before assuming)

- **Where `generate:typescript-types` (Tempest → TS type generation,
  `specs/api.md`) writes its output** — deferred until real
  Infrastructure DTOs exist to generate from.
- **`GET /question-feedback`, `POST /generate-question`** — not in
  Slice 2's or Slice 4's scope; exact 4xx codes, success status, and
  idempotency shape still need deciding when a later slice actually
  builds them. (`POST /app-feedback` is now decided — Slice 4.)
- **Rate limiting / abuse protection on `POST /app-feedback`** — an
  anonymous `deck_id` can submit freely; noted but not solved in
  `specs/2026-09-06-slice-4-app-feedback.md`.
- **IP/domain-level restriction on the `/mcp` triage route** — deferred
  until a real deployment target exists (no stable IP range to
  restrict to before then). `specs/2026-09-06-slice-4-app-feedback.md`.
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
