# API contract — exploration mode

**Status:** draft, not a locked slice spec. Expected to change as `api/`
and `frontend/` get actually built against it — update this file in
place rather than freezing it early. Once a real slice spec exists for
this feature (see `STATUS.md`), that spec is the source of truth for
scope/Done; this file stays the shared interface both sides code to.

Restates, with implementation-level detail, the API sketched in
[`specs/exploration-mode.md`](exploration-mode.md)'s "API" section.
Where this doc adds something that doc didn't specify (the error
shape, below), it's called out explicitly as this repo's own decision.

## Conventions

- Base URL: `api/.env`'s `BASE_URI` (local: `http://127.0.0.1:8000`).
  No path prefix / version segment yet — single evolving contract while
  in exploration mode.
- `Content-Type: application/json` for every request body and response.
- **Auth:** every request that reads or writes *deck-scoped* data
  carries `deck_id`, a client-held opaque UUID. No login, no session,
  no other identity. Anyone holding a `deck_id` has full read/write on
  that deck's data. `GET` endpoints take it as a query param; `POST`
  endpoints take it in the JSON body. `deck_id` is validated for UUID
  format only — 400 if malformed — never looked up against a table
  (there is no `decks` table; any well-formed UUID is accepted).
  **Exception:** `GET /questions` returns global data, not deck-scoped,
  and takes no `deck_id` at all — decided in
  `specs/2026-09-06-slice-2-questions-feedback-persistence.md` after
  this convention text and the endpoint's own "Query: none" turned out
  to contradict each other.
- **Error shape** (this repo's own decision — not in the source vision
  doc, needs confirming once real errors happen):
  ```json
  { "error": { "message": "human-readable, English, safe to show" } }
  ```
  Paired with a 4xx/5xx status. No error code enum yet — add one if/when
  the frontend needs to branch on error type, not preemptively.
- IDs are UUIDs. Server-generated IDs (`questions.id`) are **UUIDv7** —
  decided in `specs/2026-09-06-slice-2-questions-feedback-persistence.md`
  for its DB-index locality/sortability, no longer open. IDs generated
  client-side (`question_feedback.id`, `app_feedback.id`, noted below)
  are the frontend's own choice — this contract only requires
  uniqueness, not a specific UUID version.

## Endpoints

### `GET /questions`

Returns every known question. No pagination yet (dataset is small in
exploration mode).

**Query:** none — no `deck_id`. `questions` is global data, not
deck-scoped; see the Auth convention above.

**200:**
```json
[
  { "id": "uuid", "text": "..." }
]
```
Only `id` and `text` — never the `source` metadata (creator-only, never
sent to clients).

### `GET /question-feedback`

Reconstructs "already rated" state (first load, reinstall, second
device joining the deck) — **not** a rating-history endpoint.
`question_feedback` itself stays fully append-only in storage (Slice
2's decision, unchanged); this endpoint only answers "which questions
has this deck rated at least once."

**Query:** `deck_id` (required).

**200:**
```json
["uuid1", "uuid2"]
```
A bare array of distinct `question_id`s — no `rating`, no
`created_at`, no row `id`. A question rated more than once (re-rating
appends a new row, never overwrites) still appears exactly once here.
Empty array if the deck has no ratings yet, not `404`. Decided in
`specs/2026-09-06-slice-6-question-feedback-reconstruction.md`,
replacing an earlier, self-contradictory sketch (it referenced
`created_at` for "take the latest" without actually including
`created_at` in the response).

### `POST /generate-question`

Server calls an LLM (fixed system prompt + `topic_request`) and returns
exactly one freshly generated question — never several to pick from.
Can take several seconds; not safe to retry blindly (see below).

**Body:**
```json
{ "deck_id": "uuid", "topic_request": "free text, e.g. 'our future'" }
```

**200:**
```json
{ "id": "uuid", "text": "..." }
```

**Idempotency:** none — every call generates and stores a new question.
Cannot be queued for offline/background retry (needs a live round
trip); the frontend should show a wait state and let the user cancel/
retry manually rather than auto-retrying.

### `POST /question-feedback`

Records one rating (+ optional free text) for a question. Append-only —
never overwrites a prior rating for the same question.

**Body:**
```json
{
  "id": "uuid",
  "question_id": "uuid",
  "deck_id": "uuid",
  "rating": -5,
  "free_text": "optional, nullable"
}
```
`id` is generated **client-side** specifically so retries after a
dropped connection are safe: same `id` submitted twice writes the same
row once — the second call is silently accepted (same success
response), not compared against the first payload field-by-field, and
not rejected. `id` is the row's only uniqueness constraint.

**201:** empty body (or `{}` — undecided, pick when building). Decided
in `specs/2026-09-06-slice-2-questions-feedback-persistence.md`.

**404:** unknown `question_id` (no matching row in `questions`) —
error shape as above. `deck_id` is never looked up (see Auth
convention), only format-validated (400 if malformed).

### `POST /app-feedback`

Feedback about the app itself, not tied to any question. Same
idempotency shape as above.

**Body:**
```json
{ "id": "uuid", "deck_id": "uuid", "free_text": "..." }
```

`free_text` is **required, non-empty** — 400 if missing or blank.
Unlike `question_feedback.free_text`, there's no numeric rating to
fall back on as signal, so empty app-feedback carries no data. Decided
in `specs/2026-09-06-slice-4-app-feedback.md`.

**201:** empty body. Decided (not `200`) in
`specs/2026-09-06-slice-4-app-feedback.md`, consistent with
`POST /question-feedback`'s `201` above.

**Read/triage side:** not exposed as a `GET` endpoint. A Tempest
`tempest/mcp` server (`AppFeedbackServer`, `Infrastructure/Mcp/`)
exposes `listAppFeedback`/`markFeedbackHandled` MCP tools instead, so
feedback can be triaged directly from a Claude session — see
`specs/2026-09-06-slice-4-app-feedback.md` for the full design and its
`/mcp`-route auth scheme.

## Keeping frontend types in sync: Tempest's TypeScript generation

Tempest can generate TypeScript definitions from PHP classes — no extra
dependency needed, `tempest/generation` is already pulled in by
`tempest/framework` itself. Mechanism (confirmed against Tempest's own
docs/source, not guessed):

- Mark a PHP class `#[Tempest\Generation\TypeScript\AsType]`; enums are
  picked up automatically, no attribute needed.
- Run `generate:typescript-types` (a Tempest console command — wire it
  through `script/tempest` once that script exists, per the
  scripts-first rule in `CLAUDE.md`).
- Output location is configurable via a `typescript.config.php`: either
  one `types.d.ts` file (default) or a directory tree with one `.ts`
  file per PHP namespace.
- Tempest's own docs mark this feature **experimental** — the API may
  still change in a minor version.

**Where this fits our hexagonal layout:** `#[AsType]`-marked classes are
Tempest code, so per `CLAUDE.md` they can only live in
`src/Infrastructure` — meaning dedicated request/response DTOs mirroring
the shapes above, not the Domain entities themselves passed straight
through.

**Not wired up yet, on purpose:** there are no DTOs to generate from —
no Infrastructure code exists (see `STATUS.md`). Two things stay open
until real DTOs exist and get built against a spec:
- **Output location** — a directory tree written straight into
  `frontend/src/...`, vs. a single `types.d.ts` kept in `api/` that
  `frontend/`'s `tsconfig.json` references across the sibling-folder
  boundary. Explicitly deferred rather than picked now.
- Can't be run/tested in this dev sandbox either way — same PHP ^8.5
  gap as the rest of `api/` (see `STATUS.md` § Known quirks).

## Open items for whoever builds against this next

- `POST /generate-question` — not built yet. Exact 4xx codes,
  `200`/`201`/`204` choice, and idempotency shape still need deciding
  when a slice actually implements it — the pattern set for
  `POST /question-feedback` (above) is a reasonable default to start
  from, not a guarantee it fits unchanged.
- `GET /question-feedback` is now built, API-only
  (`specs/2026-09-06-slice-6-question-feedback-reconstruction.md`);
  the frontend still doesn't call it — `useQuestionDeck.ts` reconciling
  `rated_question_ids` with this endpoint on load is a later slice.
- `POST /app-feedback` is now built (`specs/2026-09-06-slice-4-app-
  feedback.md`); rate limiting / abuse protection on it, and IP/
  domain-level restriction on the `/mcp` triage route once a real
  deployment target exists, are still open — see that spec's
  "Explicitly deferred".
- Rate limiting / abuse handling on `POST /generate-question` (an LLM
  call an anonymous `deck_id` can trigger freely) — out of scope for
  exploration mode per `specs/exploration-mode.md`, but worth a line
  in a real spec before this goes anywhere near production.
