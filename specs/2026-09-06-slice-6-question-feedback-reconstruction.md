# Slice 6 — `GET /question-feedback`: rated-question reconstruction (API only)

**Status:** locked. Grilled with the human (`grill-me` skill,
2026-09-06) against `specs/api.md`'s existing sketch and
`specs/STATUS.md` § Open decisions. Self-contained — restates what's
needed from `specs/api.md` rather than pointing at it for the
load-bearing facts.

## Why this slice

`specs/exploration-mode.md`'s "Sync and offline behavior" names the
real need: on first load, reinstall, or a second device joining a
deck, the frontend has no local `rated_question_ids` yet and needs to
reconstruct it from the server. `specs/api.md` already sketched this
endpoint, but Slice 2 deferred it (`GET /questions` +
`POST /question-feedback` only) and it's stayed an open item since.

## Scope

**In scope:** `GET /question-feedback` (API only).

**Explicitly out of scope:**
- **Frontend wiring** (`useQuestionDeck.ts` calling this on load and
  merging into `ratedQuestionIds`, instead of trusting `localStorage`
  alone). Same split as Slice 2 → Slice 3: prove the API in isolation
  first. A later slice wires it in.
- **`POST /generate-question`** — unrelated, still open, needs an
  LLM-provider decision first.

## What changed from `specs/api.md`'s original sketch

The original sketch returned every rating event
(`[{question_id, rating}]`, `rating` repeating a `question_id` across
re-rates) with a comment that "the frontend takes the latest by
`created_at` if it needs a single current value." Grilling this
surfaced two problems with that shape, in order:

1. **It was self-contradictory.** The reasoning depended on
   `created_at`, but the sketched response didn't include it at all —
   there was no way to actually determine "the latest" from the
   documented shape.
2. **Once asked whether `rating` was even needed:** it isn't. The
   *only* thing the frontend actually needs today is "did this deck
   ever rate this question" — a presence check, not the rating value
   or when it happened. A richer "rating history" UI is a real,
   possible future need, but not one that exists yet, and one that
   doesn't need this endpoint's help either — `question_feedback`
   itself stays fully append-only in the database (Slice 2's decision,
   unchanged), so the raw history is never lost; a future feature
   reads it from there or a purpose-built endpoint then, not by
   speculatively widening this one now.

**Locked response, replacing `specs/api.md`'s original sketch:**

```json
["uuid1", "uuid2"]
```

A bare array of `question_id`s this deck has rated at least once — no
`rating`, no `created_at`, no row `id`. `SELECT ... WHERE deck_id = ?`,
deduplicated (a `question_id` rated three times appears once).

**Reasoning — bare array, not `{question_ids: [...]}`:** consistent
with `GET /questions`, which also returns a bare array — no wrapping
object needed for a single list, and no anticipated future field this
endpoint would need to add without a breaking change anyway (if one
ever does show up, that's a new endpoint or a version bump, not
something worth guessing a wrapper shape for today).

**Reasoning — dedup in PHP, not SQL `DISTINCT`:** Tempest's model-based
`SelectQueryBuilder` has no `distinct()` (only `CountQueryBuilder`
does — checked the source, not assumed). Given the deck-scoped row
count is small in exploration mode, selecting all matching rows and
deduplicating in PHP (array keyed by `question_id`) is simpler than a
raw-SQL escape hatch for one query — matches the original grill's own
reasoning for this endpoint: "no window-function/group-by logic to
write or test."

## Endpoint

### `GET /question-feedback`

**Query:** `deck_id` (required). Format-validated as a UUID — `400` if
malformed, same pattern as every other endpoint. Never looked up
against a table (no `decks` table — `specs/api.md`'s Auth convention).

**200:**
```json
["uuid1", "uuid2"]
```
Empty array if the deck has no ratings yet (not a `404` — an empty
result is a valid, ordinary state, not an error).

## Architecture (hexagonal)

Mirrors the existing `question_feedback` write-side shape exactly, one
more method added to the same port rather than a parallel one:

- **`Application`** — `QuestionFeedbackRepository` (existing port)
  gains `listRatedQuestionIds(string $deckId): string[]`; a new
  `ListRatedQuestionIds` use case wraps it (mirrors `ListQuestions`/
  `ListAppFeedback`'s thin-delegation shape).
- **`Infrastructure`** — `DatabaseQuestionFeedbackRepository` (existing
  adapter) implements the new method; `QuestionFeedbackController`
  (existing controller) gains a `#[Get('/question-feedback')]` method
  alongside its existing `POST` one, with the same manual
  `deck_id`-format validation as the write side.

## Done

- `script/qa` green end-to-end.
- `GET /question-feedback?deck_id=X` returns every distinct
  `question_id` deck `X` has rated, deduplicated; a deck with no
  ratings gets `[]`, not `404`.
- Malformed `deck_id` returns `400`.
- Re-rating the same question (already covered by `POST
  /question-feedback`'s existing idempotent/append-only tests) doesn't
  duplicate the `question_id` in this endpoint's response.
- Tests cover: empty deck, one rating, a re-rated question appearing
  once, malformed `deck_id`.

## Explicitly deferred (not decided here — see `specs/STATUS.md` § Open decisions)

- Frontend wiring (`useQuestionDeck.ts` reconciling `rated_question_ids`
  with this endpoint on load).
- `POST /generate-question`.
