# Slice 3 — frontend: wire the core loop against the real API

**Status:** locked. Grilled with the human (`grill-me` skill,
2026-09-06) against `specs/STATUS.md` § Next step and
`specs/exploration-mode.md`. Self-contained — restates what's needed
rather than pointing at the design input for load-bearing facts, per
that file's own instructions. Branch:
`claude/ibes-paarfragen-frontend-api-wiring`.

## Why this slice

Slice 2 made `GET /questions` and `POST /question-feedback` real, but
`frontend/src/` is still the Vite scaffold (`App.vue`/`main.ts`/
`style.css`) — nothing calls the API yet. This slice is the **first
real frontend code**: the core question/rating loop, wired against the
real endpoints, so the product becomes usable end to end for the first
time (`VALUES.md` § Product over system — picked over extending
`api/`'s own scope further).

## Scope

**In scope:**
- Question display (large, centered).
- Four rating buttons (-5 / -1 / 1 / 5), always visible, single tap.
- Free-text field, always visible, always optional.
- "Next" button — random question from cache not yet rated.
- An end-state message once every cached question is rated.
- `deck_id` generation and persistence.
- Offline-tolerant rating submission (queue + flush, see below).

**Explicitly out of scope for this slice:**
- The "new topic" input (`POST /generate-question`) — that endpoint
  isn't built (Slice 2 deferred it).
- The app-feedback entry point (`POST /app-feedback`) — same reason.
- Reconstructing "already rated" state from the server
  (`GET /question-feedback`) — that endpoint isn't built either; this
  slice's "already rated" state lives in `localStorage` only.
- Real PWA offline (service-worker precaching of the app shell, real
  icons, installability) — `vite-plugin-pwa` is already in the
  project but ships with empty `icons: []`
  (`specs/STATUS.md` § Other known quirks); wiring that up is a
  separate, clearly-bounded chunk of work. This slice makes *API
  calls* tolerate no network — it does not make the app *load*
  without network on first visit.
- What replaces the end-state message once the question pool grows
  (AI-generated questions, reshuffle) — explicitly a later iteration,
  the human's own words during the grill: the goal is "a few hundred
  questions" eventually, not solved here.

**Reasoning:** mirrors Slice 2's own scope-cut — build against the
API surface that actually exists, not the full `specs/api.md` contract
at once.

## `deck_id`

Generated client-side on first run (`crypto.randomUUID()`), persisted
to `localStorage`, read from there on every subsequent load. No
hardcoded shared value, in dev or production; no manual override
(no `?deck_id=` query param). If `localStorage` is lost, a new
`deck_id` is generated on the next run — the app has no way to know
which questions were already rated under the old one, and there is no
recovery mechanism in this slice. Tests set `deck_id` by seeding
`localStorage` directly before mounting — no test-only code path in
the app itself.

**Reasoning:** corrects `specs/exploration-mode.md`'s original
"hardcoded into the app's local config" design — a single value shared
by every install would mean every couple using the app writes into the
same deck, not what "a shared session between two people" is meant to
be. Caught during the grill, documented as a correction in
`specs/exploration-mode.md` itself, not just here. No override
mechanism: a bearer credential in a URL lands in browser history and
any shared link — a real exposure this slice doesn't need to accept
for a "switch decks while testing" convenience `localStorage`
already covers (clear it, or use a different browser profile).

## Rating submission: queued, not instant

A rating tap:
1. Writes the feedback row to a **pending queue in `localStorage`**
   immediately.
2. Adds the question's id to a `rated_question_ids` set, updated at
   the same moment — independent of the queue, and never removed once
   added (see "Why a separate `rated_question_ids` set" below).
3. Advances to the next question immediately. The UI never waits on
   the network for this.

The queue is **not** flushed on every rating. It flushes when:
- its size crosses a threshold (default: 10 — a named constant, not a
  scattered magic number, see "Keep it changeable" below), or
- the browser's `online` event fires, or
- the app starts up (an attempt to flush whatever's still pending from
  a previous session).

Flushing sends each queued row as its own `POST /question-feedback`
call (no batch endpoint — the existing single-row, idempotent,
client-generated-`id` contract from `specs/api.md` was already
designed for exactly this retry shape). Per row, on flush:
- **Success (201) or a duplicate-id replay (also 201):** remove the
  row from the queue.
- **Permanent rejection (400 malformed `deck_id`, 404 unknown
  `question_id`):** remove the row from the queue, log to the console.
  Retrying an input the server has already rejected would only fail
  again.
- **Network failure or 5xx:** leave the row in the queue for the next
  flush attempt.

**Why a separate `rated_question_ids` set, not derived from the
queue:** a successfully-flushed row leaves the queue (it's done), but
the question must stay "rated" — if "rated" were computed from current
queue contents, a flushed question would fall out of that set and
reappear as a candidate for "Next". `rated_question_ids` is instead its
own persisted, append-only set, updated the moment a rating is queued
(step 2 above), regardless of whether or when it's actually flushed.

**Why queued instead of instant `POST` on tap:** the human's explicit
call during the grill — assume no internet connectivity from the
start, rather than trying to POST immediately and falling back to a
queue only on failure. Feedback isn't needed on the server instantly;
it's only needed, in aggregate, to eventually improve the question
pool. This also means `specs/exploration-mode.md`'s existing "Rating
submissions write to local state immediately, then queue for
background sending with retry" line was already the right shape — this
slice just pins the concrete flush triggers and per-row failure
handling that document left unspecified.

## Keep it changeable

The four rating values, their labels, and the flush threshold are
explicitly **experimental** — the human's own words: "the buttons
themselves are experimental and should be treated as such in the
code." Concretely: one small, central place for the rating scale
(value + label pairs) and the flush threshold — not scattered across
components — so tuning them later (chasing the sweet spot between "not
annoying" and "good signal", an explicit open question the human wants
to answer from real use, not from this spec) is a one-line change, not
a refactor. This is *not* a request to build a generic experimentation
framework — the opposite: the least structure that keeps the numbers
in one place.

## State management: a plain composable, no Pinia

A single composable (e.g. `useQuestionDeck()`) holding: the questions
cache, `rated_question_ids`, the pending feedback queue, and the
current question — built on `ref`/`reactive`, not a Pinia store.

**Reasoning:** no router is installed, this is a single screen, no
cross-route state to share. Pinia would be an abstraction for a
problem (keeping state in sync across multiple components/routes) that
doesn't exist yet with one screen (`VALUES.md` § Simple over
impressively complex) — same reasoning Slice 2 applied to skip a
custom `IsDatabaseModel`-equivalent trait for two persistence models.

## HTTP: native `fetch()`, no library

Two endpoints, JSON in and out, error handling already needing custom
code either way (`specs/api.md`'s `{"error":{"message":...}}` shape,
not something a library's error handling would shortcut). No
dependency added.

## `GET /questions` and the local cache

On load, if online: `GET /questions`, merge into the local `questions`
cache (union — nothing already cached is ever dropped, per
`specs/exploration-mode.md` § Sync and offline behavior). If offline:
use whatever is already cached. "Next" picks a random question from
the cache whose id isn't in `rated_question_ids`; once none remain,
show the end-state message instead (see Scope, "explicitly out of
scope" for what replaces this later).

## Testing

Unit-test the composable's logic — queueing, the three flush triggers,
per-row flush outcomes (success/duplicate/permanent-rejection/network-
failure), `rated_question_ids` staying independent of the queue,
`deck_id` generation-and-persistence — with `fetch` mocked. Skip
brittle component/DOM-structure assertions; per "Keep it changeable"
above, tests that pin exact markup would fight the explicit goal of
staying easy to tweak. Matches the reasoning already in `IDEAS.md`'s
"Test conventions doc" entry (assert on behavior, not cosmetic
detail), applied here since `api/tests/*` are still empty and this is
the first real code to actually need the rule.

## Done

- `script/qa` green end-to-end (frontend build, lint, types, tests).
- The core loop works against the real API via `script/dev-api` +
  `npm run dev`: a question shows, a rating tap advances to the next
  question without waiting on the network, the free-text field is
  always visible and optional, and the end-state message appears once
  every seeded question is rated.
- Turning the network off (dev tools) mid-session doesn't break rating
  — taps keep queueing and advancing; turning it back on eventually
  flushes the queue (observable via the API's own data, e.g.
  `sqlite3 api/.tempest/database.sqlite`).
- `deck_id` persists across a reload and is absent from any committed
  file.

## Explicitly deferred (not decided here — see `specs/STATUS.md` § Open decisions)

- Real PWA app-shell offline support (icons, service-worker
  precaching, installability).
- What replaces the end-state message once the question pool is
  larger (AI-generated questions, reshuffle).
- `GET /question-feedback`, `POST /generate-question`,
  `POST /app-feedback` on the `api/` side — unchanged from Slice 2's
  own deferral.
- End-user UI language (still English placeholder copy, per
  `specs/STATUS.md`).
