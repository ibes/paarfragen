# API contract — exploration mode

**Status:** draft, not a locked slice spec. Expected to change as `api/`
and `frontend/` get actually built against it — update this file in
place rather than freezing it early. Once a real slice spec exists for
this feature (see `STATUS.md`), that spec is the source of truth for
scope/Done; this file stays the shared interface both sides code to.

Restates (in this repo's own words, not by reference — the source can
shrink or change independently) the API sketched in
[`ibes/redlich`'s `paarfrage-exploration-mode.md`](https://github.com/ibes/redlich/blob/main/VISION/paarfrage-exploration-mode.md).
Where this doc adds something that vision doc didn't specify (the error
shape, below), it's called out explicitly as this repo's own decision,
not carried over from there.

## Conventions

- Base URL: `api/.env`'s `BASE_URI` (local: `http://127.0.0.1:8000`).
  No path prefix / version segment yet — single evolving contract while
  in exploration mode.
- `Content-Type: application/json` for every request body and response.
- **Auth:** every request (except none — even reads) carries `deck_id`,
  a client-held opaque UUID. No login, no session, no other identity.
  Anyone holding a `deck_id` has full read/write on that deck's data.
  `GET` endpoints take it as a query param; `POST` endpoints take it in
  the JSON body.
- **Error shape** (this repo's own decision — not in the source vision
  doc, needs confirming once real errors happen):
  ```json
  { "error": { "message": "human-readable, English, safe to show" } }
  ```
  Paired with a 4xx/5xx status. No error code enum yet — add one if/when
  the frontend needs to branch on error type, not preemptively.
- IDs are UUIDs (v4 or v7, undecided — pick one when the persistence
  layer is built, not before). Client-generated where noted below.

## Endpoints

### `GET /questions`

Returns every known question. No pagination yet (dataset is small in
exploration mode).

**Query:** none.

**200:**
```json
[
  { "id": "uuid", "text": "..." }
]
```
Only `id` and `text` — never the `source` metadata (creator-only, never
sent to clients).

### `GET /question-feedback`

Returns this deck's own rating history, used to reconstruct "already
rated" state (first load, reinstall, second device joining the deck).

**Query:** `deck_id` (required).

**200:**
```json
[
  { "question_id": "uuid", "rating": -5 }
]
```
`rating` is one of `-5, -1, 1, 5`. One row can exist per past *rating
event* — re-rating appends, it doesn't overwrite (see `POST
/question-feedback` below) — so a `question_id` can repeat; the
frontend takes the latest by `created_at` if it needs a single current
value, but for the "already rated" check, presence at all is enough.

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
row once.

**200:** empty body (or `{}` — undecided, pick when building).

### `POST /app-feedback`

Feedback about the app itself, not tied to any question. Same
idempotency shape as above.

**Body:**
```json
{ "id": "uuid", "deck_id": "uuid", "free_text": "..." }
```

**200:** empty body (or `{}` — same open item as above).

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

- Exact 4xx status codes per failure case (missing `deck_id`, unknown
  `question_id`, empty `free_text` when required, etc.) — not decided,
  decide when writing the first Infrastructure controller/test.
- `200` vs `201`/`204` on the two write-only endpoints above.
- Rate limiting / abuse handling on `POST /generate-question` (an LLM
  call an anonymous `deck_id` can trigger freely) — out of scope for
  exploration mode per the source vision doc, but worth a line in a
  real spec before this goes anywhere near production.
