# Slice 2 — real `api/`: questions + question-feedback persistence

**Status:** locked. Grilled with the human (`grill-me` skill,
2026-09-06) against `specs/STATUS.md` § Open decisions and
`specs/api.md`. Self-contained — restates what's needed from
`specs/exploration-mode.md` and `specs/api.md` rather than pointing at
them for the load-bearing facts, per those files' own instructions.

## Why this slice

`specs/api.md` already drafts a five-endpoint contract, but `api/src/`
is still empty (`.gitkeep` only) — no Domain, no Application, no
Infrastructure code exists. This slice is the **first real
implementation**: a working, persisted, hexagonal PHP backend for a
deliberately small cut of that contract, proving out the architecture
(`api/README.md`'s layering) and the toolchain (`script/qa` green)
before adding the harder pieces (LLM generation, more endpoints).

## Scope

**In scope — implemented for real, with persistence:**
- `GET /questions`
- `POST /question-feedback`

**Explicitly out of scope for this slice** (stay mocked/absent, no
code written against them here):
- `GET /question-feedback` — used by the frontend to reconstruct
  "already rated" state; not needed to prove out the backend shape.
- `POST /generate-question` — pulls in an LLM provider decision (which
  model, API key handling, cost, rate limiting — `specs/api.md`'s own
  "Open items") that doesn't belong in a persistence-proving slice.
- `POST /app-feedback` — same shape as `question-feedback` schema-wise,
  lower priority, deferred rather than duplicating effort now.

**Reasoning:** the two in-scope endpoints together exercise a read
path, an append-only write path, deck-scoped vs. global data, and
idempotent retries — enough surface to prove the hexagonal layers and
persistence work, without dragging in an LLM-provider decision or
building three more endpoints whose shape isn't exercised any
differently.

## Endpoints

Full request/response contract: `specs/api.md`. This slice implements
exactly what's written there for these two endpoints, plus the
clarifications this spec adds (below) where `api.md` was silent or, in
one case, self-contradictory.

### `GET /questions`

Returns every row in `questions` as `{id, text}`. No `deck_id`, no
query params, no pagination.

**Reasoning (no `deck_id`):** `api.md`'s own Auth convention
("every request... carries `deck_id`") contradicted this endpoint's
"Query: none" — caught during the grill. Resolved in favor of no
`deck_id`: `questions` is global data (every deck sees the same set),
not deck-scoped, so there is nothing to authorize or scope by. Fixed
in `specs/api.md` itself, not just here, since it's a contract-level
correction.

### `POST /question-feedback`

Records one append-only rating row. Body per `api.md`:
`{id, question_id, deck_id, rating, free_text}`.

- **`deck_id`:** format-validated as a UUID (any RFC 4122 version) —
  400 if malformed. Never looked up against a table — there is no
  `decks` table, so any well-formed UUID is accepted as a valid bearer
  identity. **Reasoning:** cheap guard against client bugs sending
  garbage instead of a UUID, without inventing a `decks` table this
  design explicitly doesn't have (`specs/exploration-mode.md` §
  Identity — `deck_id` is a bearer credential, not a registered
  entity).
- **`question_id`:** must reference an existing row in `questions` —
  404 with the standard error body if not. **Reasoning:** prevents
  silently accumulating orphaned feedback rows from client bugs; a 404
  surfaces the bug instead of hiding it in bad data.
- **`rating`:** must be one of `-5, -1, 1, 5` — 400 otherwise. Directly
  from `specs/exploration-mode.md`'s rating scale; not separately
  grilled, no real tradeoff here.
- **Success:** `201 Created`, empty body (or `{}`). **Reasoning:** a
  new resource (a feedback row) is created — `201` is the precise verb
  for that, and cheap to assert in tests.
- **Idempotency:** `id` is the row's only uniqueness constraint (a
  `UNIQUE` column, not a compound key). A second call with an `id`
  that already exists is silently accepted with the same `201`
  success response — **not** compared field-by-field against the
  first call's payload, and not rejected. **Reasoning:** the spec's
  own purpose for client-generated `id` is safe retries after a
  dropped connection (`api.md`); comparing payloads on retry would
  need a lookup-then-compare instead of a plain unique-constraint
  insert, for a threat (a forged retry with a different payload) that
  isn't a real security concern here — `deck_id` already grants full
  read/write on that deck's own data, so there's nothing an attacker
  gains by racing their own `id`.

## Data model

Two tables only — `app_feedback` (out of scope) is not created this
slice.

### `questions`

| column | type | notes |
|---|---|---|
| `id` | uuid (text) | **UUIDv7**, server-generated at insert time |
| `text` | text | |
| `source` | json (text) | e.g. `{"type": "seed"}`. Never sent to clients. |
| `created_at` | timestamp | |

### `question_feedback`

| column | type | notes |
|---|---|---|
| `id` | uuid (text), unique | client-generated |
| `question_id` | uuid (text), fk → `questions.id` | |
| `deck_id` | uuid (text) | format-validated only, no fk (no `decks` table) |
| `rating` | integer | one of `-5, -1, 1, 5` |
| `free_text` | text, nullable | |
| `created_at` | timestamp | |

**Reasoning — UUIDv7 for `questions.id`:** time-ordered, so DB index
locality and natural sort-by-creation come for free without a separate
`created_at` sort. PHP 8.5/Tempest have no trouble generating v7.
Client-generated IDs (`question_feedback.id`, and later
`app_feedback.id`) stay whatever the frontend already produces — this
slice doesn't change or constrain that; only uniqueness matters
server-side.

## Storage: SQLite

A single SQLite file inside the `api` container's volume — no separate
database service added to `docker-compose.yml`.

**Reasoning:** `VALUES.md` § "Simple over impressively complex" — no
DB server to run, connect to, or hold credentials for; `sqlite3` is
already installed in the dev sandbox for inspection. Exploration mode
has no concurrent-write-at-scale requirement (`specs/exploration-mode.md`
explicitly scopes out everything that would need it). Swapping to
Postgres/MySQL later is a single Infrastructure adapter change, not a
rewrite, because `Domain`/`Application` never see the storage
mechanism (`api/README.md`'s hexagonal layering) — so this isn't a
one-way door.

## Seed data

A handful of example questions (5–10) ship embedded directly in a DB
migration, `source: {"type": "seed"}` each. No separate seed script or
data file.

**Reasoning:** without `POST /generate-question` in scope, `GET
/questions` needs *something* to return. A migration runs
automatically on every setup/test — no manual step, no new script, no
new data format to design for a handful of rows. A dedicated seed
script (`script/seed-questions` + JSON/CSV) is real IDEAS.md-shaped
system work for when the question set is large or externally sourced
— not needed at this size (`VALUES.md` § "Product over system").

## Architecture (hexagonal)

Following `api/README.md`'s layout:

- **`Domain`** — `Question` (id, text, source, createdAt) and
  `QuestionFeedback` (id, questionId, deckId, rating, freeText,
  createdAt) as framework-free entities/value objects; a `Rating` value
  object enforcing the `-5/-1/1/5` set.
- **`Application`** — two use cases: `ListQuestions` (query) and
  `RecordQuestionFeedback` (command), each depending on a
  framework-free repository *port* (interface) it doesn't implement.
- **`Infrastructure`** — Tempest HTTP controllers
  (`QuestionController`, `QuestionFeedbackController`) and the SQLite
  repository *adapters* implementing the Application ports, using
  Tempest's own database layer (`tempest/database`, already pulled in
  by `tempest/framework` — no extra dependency).

**Reasoning:** this is `api/README.md`'s existing, already-decided
shape — restated here for completeness, not re-litigated. The only new
call this slice makes is *which* Tempest building block backs the
adapter (`tempest/database`), chosen because it's already in the
dependency tree — adding a second ORM/query layer would be exactly the
kind of unjustified complexity `VALUES.md` warns against.

## Deploy PHP version

`^8.5`, confirmed — matches `api/composer.json`'s existing requirement
and the `php:8.5-cli-trixie` container this repo already builds
against (`api/README.md`). No longer an open decision.

**Reasoning:** this is an ops fact (`CLAUDE.md`: "no invented ops
facts... from repo config or the human only"); asked directly rather
than assumed. The human confirmed `^8.5` as binding for the real
deploy target, matching what was already in `composer.json`.

## Done

- `script/qa` green end-to-end.
- `GET /questions` returns the seeded rows as `{id, text}`, no
  `source`, no `deck_id` required.
- `POST /question-feedback` persists a row, returns `201`; a
  duplicate `id` retry returns `201` again without a second row;
  unknown `question_id` returns `404`; malformed `deck_id` returns
  `400`.
- Tests cover both endpoints' success and error paths (`api/tests/`,
  currently empty).

## Explicitly deferred (not decided here — see `specs/STATUS.md` § Open decisions)

- `GET /question-feedback`, `POST /generate-question`,
  `POST /app-feedback` — including their exact 4xx codes, success
  status, and idempotency shape.
- `generate:typescript-types` output location.
- End-user UI language.
