# Slice 4 — App-Feedback: submit, queue, and MCP-driven triage

**Status:** locked. Grilled with the human (`grill-me` skill,
2026-09-06) against `specs/STATUS.md` § Next step / § Open decisions,
`specs/api.md`, and `specs/exploration-mode.md`. Self-contained —
restates what's needed from those files rather than pointing at them
for the load-bearing facts, per their own instructions.

## Why this slice

`specs/api.md` already sketches `POST /app-feedback`; `specs/
exploration-mode.md` already names "a small, always-reachable entry
point for app-level feedback, separate from the question flow" in its
screen layout. Both were explicitly deferred in Slice 2 and Slice 3.

The grill surfaced a second, more important "why": this feedback isn't
just collected and stored — the human wants to actually **act on it**
from inside a Claude Code session on this repo, to iterate on the app.
That reframes the slice from "one more CRUD endpoint" into two halves:
a **submission channel** (couple → API) and a **triage channel** (API
→ a Claude session), the second built on Tempest's built-in `tempest/
mcp` component (`api/vendor/tempest/framework/packages/mcp/`,
currently marked experimental by Tempest itself).

## Scope

**In scope:**
- `POST /app-feedback` — records one feedback row.
- Frontend entry point — a small, always-visible button opening a
  modal with a free-text field.
- `app_feedback.handled_at` — a nullable column marking a row as
  triaged, without deleting it.
- An MCP server (`AppFeedbackServer`) exposing two tools:
  `listAppFeedback` (unhandled rows) and `markFeedbackHandled` (marks
  one row done).
- A dedicated auth middleware protecting the MCP route only (bearer
  token + signed, time-boxed timestamp).

**Explicitly out of scope:**
- **Any live deployment.** No deployment target exists yet
  (`specs/STATUS.md` names none, and `CLAUDE.md` forbids inventing
  ops facts). This slice makes the read/triage side *usable the moment
  a deployment exists*, without assuming one exists now — see "MCP
  server" below for what that means in practice.
- **Rate limiting / abuse protection on `POST /app-feedback`.** An
  anonymous `deck_id` can submit freely, same open item already noted
  for `POST /generate-question` in `specs/api.md`. Left as an open
  point in `specs/STATUS.md`, not solved here.
- **IP/domain restriction on the MCP route.** Considered during the
  grill and rejected as not reliably implementable: Anthropic
  publishes no stable, guaranteed IP range for MCP client traffic
  (Claude Desktop runs from an arbitrary end-user machine; a Claude
  Code session runs from rotating cloud infrastructure) — inventing
  one here would violate `CLAUDE.md`'s "no invented ops facts" and
  would likely just break. Network-level restriction becomes possible
  once a real deployment target exists (e.g. a hoster's private
  networking) — a later, deployment-specific decision.
- **Deleting feedback.** Rejected in favor of marking (see "Data
  model" below) — deletion loses the history of what's already been
  acted on.
- **`GET /question-feedback`, `POST /generate-question`** — unrelated
  to this slice, still open per `specs/STATUS.md`.

## Submission: `POST /app-feedback`

Body (per `specs/api.md`, one clarification below):
`{id, deck_id, free_text}`.

- **`id`, `deck_id`:** format-validated as UUIDs — 400 if malformed.
  Same pattern as `POST /question-feedback` (Slice 2): `deck_id` is
  never looked up against a table, `id` is the row's only uniqueness
  constraint (idempotent retry — a duplicate `id` is silently accepted
  with the same success response, not compared field-by-field).
- **`free_text`:** **required, non-empty** — 400 if missing or blank.
  **Reasoning (clarifies `specs/api.md`, which didn't say either
  way):** app-feedback with no text carries no signal — unlike a
  question rating, where the numeric rating alone is already useful
  data even without a comment. `question_feedback.free_text` stays
  nullable; this is a deliberate difference between the two feedback
  shapes, not an oversight.
- **Success:** `201 Created`, empty body. **Reasoning:** consistent
  with `POST /question-feedback`'s already-decided `201` — both create
  a new feedback row, no reason for the two "feedback write" endpoints
  to diverge on status code.

## Submission: offline behavior

**Different from `question_feedback`'s threshold/online/app-start
queue (Slice 3) — deliberately.** That queue's count-based trigger
(`FLUSH_THRESHOLD = 10`) assumes many small writes per session (one
per rated question). App-feedback is rare — realistically 0–1 per
session — so a count threshold would almost never fire, leaving a
submitted row stuck locally indefinitely.

Instead:

1. On submit, attempt the `POST` immediately.
2. On success, done — show the confirmation (below).
3. On network failure only, write the row into its own small pending
   list (separate `localStorage` key from `question_feedback`'s
   queue) and flush it on the next `online` event or app start — no
   count threshold.
4. On a `400`/other rejection (not a network failure), drop the row
   and log it — same "don't retry a request the server will never
   accept" reasoning as Slice 3's rejected-row handling.

**UI never waits on this.** Per `specs/exploration-mode.md`'s
already-decided "UI never waits on network": the modal shows its
confirmation and closes regardless of whether the `POST` above
resolved immediately or fell through to the pending list — the human
confirmed this explicitly during the grill.

## Frontend entry point

- A small, fixed-position button, visible on every screen state
  (loading, question shown, end-state message) — not just while a
  question is on screen. Matches `specs/exploration-mode.md`'s "always-
  reachable... separate from the question flow."
- Click opens a modal: a free-text `<textarea>` and a submit button.
  No rating, no other fields — this is prose feedback about the app,
  not a structured rating like `question_feedback`.
- On submit: `free_text` is trimmed and checked non-empty client-side
  too (mirrors the server's own validation, avoids a round trip for an
  empty submission); the field clears, a brief ("thanks") confirmation
  shows, and the modal closes automatically — no explicit "close"
  step, no waiting on the network (see above).

## Data model

### `app_feedback`

| column | type | notes |
|---|---|---|
| `id` | uuid (text), unique | client-generated |
| `deck_id` | uuid (text) | format-validated only, no fk (no `decks` table) |
| `free_text` | text | required, non-empty |
| `handled_at` | timestamp, nullable | `NULL` = not yet triaged |
| `created_at` | timestamp | |

**Reasoning — `handled_at`, not a delete:** the grill's triage
requirement ("Feedback soll abgearbeitet werden — markiert oder
gelöscht") was resolved in favor of marking. A nullable timestamp is
reversible (a wrongly-marked row can be reopened by clearing it) and
keeps a record of what's already been acted on, which a hard delete
throws away for no real storage-cost benefit at this scale.

## MCP server: triage from a Claude session

**What `tempest/mcp` actually offers (corrects an initial
under-estimate made during the grill):** an MCP server in Tempest is a
plain PHP class annotated `#[Tempest\Mcp\McpServer(path: '/mcp')]`,
with its capabilities as `#[McpTool]`-annotated methods, resolved
through the same container as any controller. It is **not** separate
infrastructure — it's one more route on the same `api/` deployment,
same process, same deploy lifecycle. The framework marks the component
itself experimental (not covered by Tempest's BC promise), which is a
real caveat for an internal tool, not a blocker.

### `AppFeedbackServer` (`Infrastructure/Mcp/`)

Two tools, both container-resolving an `AppFeedbackRepository` (the
same Application-layer port the HTTP controller and `POST
/app-feedback` use case depend on). PHP method names stay camelCase
(`listAppFeedback`/`markFeedbackHandled`), but the *MCP tool names* a
client actually calls are Tempest's default snake-cased form —
`list_app_feedback`/`mark_feedback_handled` (`tempest/mcp`'s own
documented default: "the tool name defaults to the snake-cased method
name"; not overridden with an explicit `name:` since that's the
idiomatic default for this ecosystem, not a real design decision):

- **`list_app_feedback`** — returns every row where `handled_at IS
  NULL`, as `{id, deck_id, free_text, created_at}`.
- **`mark_feedback_handled(id)`** — sets `handled_at = now()` for the
  row matching `id`. No-op (not an error) if already handled or if
  `id` doesn't exist — a triage tool re-run against the same list
  shouldn't fail on a row already processed by an earlier call.

**Reasoning — build the tools now, even without a live deployment:**
cheap to build (one more Infrastructure class, no new dependency),
independently testable via Tempest's own `IntegrationTest.mcp` test
helper (`callTool()`, `assertOk()` etc. — no live server needed to
exercise it), and it means a future session doesn't have to
re-derive this design once a deployment actually exists. Weighed
against `CLAUDE.md`'s "don't design for hypothetical future
requirements" and decided in the tool's favor specifically *because*
it's cheap and testable now, not despite that concern — an untestable,
speculative feature would have been deferred instead (this is exactly
what happened with `POST /generate-question`'s LLM-provider decision
in Slice 2).

### MCP auth: bearer token + signed timestamp

The MCP route needs its own protection — `app_feedback` rows aren't
deck-scoped or otherwise access-controlled, so an unprotected `/mcp`
route would let anyone read every submitted feedback row.

- **`McpAuthMiddleware`** (`Infrastructure/Http/`) — **global, like
  `CorsMiddleware`, not route-scoped.** The spec originally planned a
  `#[WithMiddleware]` route decorator on `AppFeedbackServer` (Tempest's
  documented "protect the route... through a route decorator"
  pattern) — implementation found this has no effect: Tempest's own
  `Tempest\Mcp\McpDiscovery::registerRoutes()` always points every
  discovered `#[McpServer]`'s route at the same generic
  `Tempest\Mcp\McpHttpController`, with a hardcoded decorator list
  that never reads anything off the server class itself. Corrected to
  a normally-discovered (no `#[SkipDiscovery]`) middleware that no-ops
  on every request except `/mcp`, scoping itself internally instead of
  relying on route registration to do it. See `FRICTION.md`.
- Checks two things on every request to `/mcp`:
  1. `Authorization: Bearer <token>` — the token is a single shared
     secret read from an env var (`MCP_AUTH_TOKEN`) via a
     `mcp-auth.config.php` config object (`api/vendor/.../
     06-configuration.md`'s documented pattern), not hardcoded.
  2. A signed, time-boxed timestamp — an `X-Timestamp` header plus an
     `X-Signature` header (`hash_hmac('sha256', $timestamp,
     $secret)`), rejected if the signature doesn't match or the
     timestamp is more than 10 minutes old.
- Both checks fail closed: a missing or invalid header on either
  results in `401`, same error-body shape as the rest of the API.

**Reasoning — why both a bearer token and a timestamp, not just one:**
the token alone is already the real access control (nobody without it
gets in). The signed timestamp adds replay protection specifically
against the token or a request leaking somewhere it shouldn't (a proxy
log, for instance) and being reused later — a real but secondary
concern for a header-based credential (unlike `deck_id`, which
`specs/api.md` deliberately keeps out of query strings/URLs for
exactly this reason). Considered and rejected during the grill: IP/
domain restriction to "Claude" specifically (no reliable IP range to
restrict to, see "Explicitly out of scope" above).

## Architecture (hexagonal)

Mirrors `question_feedback`'s existing shape (Slice 2) exactly:

- **`Domain`** — `AppFeedback` (id, deckId, freeText, handledAt) as a
  framework-free value object.
- **`Application`** — `AppFeedbackRepository` (port), with `record()`,
  `listUnhandled()`, `markHandled(id)`; three use cases:
  `RecordAppFeedback`, `ListAppFeedback`, `MarkAppFeedbackHandled`.
- **`Infrastructure`** — `AppFeedbackController` (the `POST` HTTP
  endpoint), `AppFeedbackServer` (the MCP tools), `McpAuthMiddleware`,
  `DatabaseAppFeedbackRepository` + `AppFeedbackModel` + a migration
  creating the table (with `handled_at` from the start, not a later
  `ALTER TABLE`).

**Reasoning:** same "why" as Slice 2's identical layering decision —
restated for completeness, not re-litigated. The MCP tools depend on
the *same* `AppFeedbackRepository` port the HTTP controller uses, not
a separate read path — one adapter, two Infrastructure entry points
(HTTP `POST`, MCP tools) calling into the same Application layer.

## Done

- `script/qa` green end-to-end.
- `POST /app-feedback` persists a row, returns `201`; a duplicate `id`
  retry returns `201` again without a second row; missing/empty
  `free_text` returns `400`; malformed `id`/`deck_id` returns `400`.
- Frontend: the feedback button is visible in every app state; the
  modal submits, clears, confirms, and closes without waiting on the
  network; a submission made while offline lands in the DB once back
  online (verified live, same Playwright-smoke-test approach as Slice
  3 — CORS-style bugs only show up against a real browser).
- MCP: `list_app_feedback` returns only unhandled rows;
  `mark_feedback_handled` sets `handled_at` and the row drops out of a
  subsequent `list_app_feedback` call; both verified via
  `IntegrationTest.mcp`, per `tempest/mcp`'s own testing helper.
- `/mcp` rejects a request with a missing/wrong token, and a request
  with a valid token but a stale (>10 min) or wrong-signature
  timestamp, both with `401`.

## Explicitly deferred (not decided here — see `specs/STATUS.md` § Open decisions)

- Rate limiting / abuse protection on `POST /app-feedback`.
- IP/domain-level restriction on `/mcp`, once a real deployment target
  exists.
- `GET /question-feedback`, `POST /generate-question`.
