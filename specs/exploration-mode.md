# Exploration mode — product vision

**Status:** design input, not a locked slice spec. Restated here in full
(not by reference — the file this used to point at is external and no
longer something a session here can read) so it survives on its own.
Write a real slice spec from this using the `spec` skill before building
any Domain/Application/Infrastructure implementation against it — this
doc is the "what and why", not a scope-and-Done commitment.

## What this app is

Paarfragen is a couples question app — a "deck of cards on steroids."
One question is shown at a time on a single shared device. A couple
reads it, talks, moves to the next. The app never gives advice, never
analyzes answers, never intercepts the conversation, and collects
near-zero personal information.

## What exploration mode is

The first, fluid stage of the app, meant to be used in real
conversations by real couples. It is the research instrument for
discovering what makes a question good — not a stripped-down demo of a
finished product.

Two principles drive every decision below:

1. **Do not break the conversation flow.** Waiting a few seconds for a
   generated question is fine (like shuffling paper cards). Needing a
   laptop, a login, or any out-of-band step is not.
2. **Minimal, fluid, revisable.** Ship the fewest structures needed to
   start collecting real signal. Golden question tiers, smart
   selection/personalization, embeddings/vector search, multi-question
   choices, and onboarding are all **out of scope** — later,
   better-informed decisions.

## Core loop

1. A question is shown.
2. The couple talks about it (or doesn't — that's still useful data).
3. They rate it: a required numeric rating, plus an optional free-text
   note.
4. They move to the next question — pulled from the pool, or freshly
   generated around a topic they name.
5. Repeat, across many real evenings.

Good and bad ratings are equally valuable — this is a mining operation,
not a keep/discard filter.

## Rating scale

Four options, no neutral middle ("meh" isn't actionable):

| Value | Label |
|---|---|
| -5 | Pure negative example ("trash") |
| -1 | Rather negative |
| 1 | Rather positive, not impressive |
| 5 | Really good — this is what we want more of |

The gap between -5/-1 and 1/5 is wider than between -1/1 so outliers are
trivially separable when scanning data later.

Free-text field: present on every rating, always visible, always
optional — never gated behind an extra tap.

## Data model (design input, not schema — see `specs/STATUS.md` § Open decisions)

Three tables. No question-selection metadata (tiers, topic/depth/
language, embeddings) — out of scope for this stage.

### `questions`

| column | type | notes |
|---|---|---|
| id | uuid | generated at creation time, not by the database |
| text | string | |
| source | json | e.g. `{"type": "seed"}` or `{"type": "llm_generated", "topic_request": "...", "model": "..."}`. JSON so new creation methods don't need a migration. Never sent to clients — creator's own review only. |
| created_at | timestamp | |

Clients only ever receive `id` and `text` (matches `specs/api.md`'s
`GET /questions` response shape).

### `question_feedback`

| column | type | notes |
|---|---|---|
| id | uuid | generated client-side |
| question_id | uuid, fk → questions.id | |
| deck_id | uuid | see "Identity" below |
| rating | int | -5, -1, 1, or 5 |
| free_text | string, nullable | |
| created_at | timestamp | |

**Append-only** — re-rating creates a new row, never overwrites. Makes
retry after network failure trivially safe.

### `app_feedback`

| column | type | notes |
|---|---|---|
| id | uuid | generated client-side |
| deck_id | uuid | |
| free_text | string | |
| created_at | timestamp | |

Feedback about the app itself, not tied to a question.

## Identity: `deck_id`

No login, no account, no PII. A single opaque UUID, `deck_id`,
identifies a shared session (not a person — a device could hold
several, for different relationships). Anyone holding it has full
read/write access to that deck's history — a bearer credential,
acceptable since no personal data is stored.

**Correction from Slice 3's grill** (`specs/2026-09-06-slice-3-frontend-api-wiring.md`):
a single value hardcoded into the app's own config, shared by every
install, would mean every couple using the app writes into the same
deck — not what "a shared session" between two people means. Instead:
`deck_id` is generated client-side on first run and persisted to
`localStorage`; not entered by a user, not shared across installs. A
human-readable recovery/sharing code is a good later addition, not
needed now.

## API

Restated in full, with implementation-level detail, in `specs/api.md`.
Summary: `GET /questions`, `GET /question-feedback?deck_id=X`,
`POST /generate-question`, `POST /question-feedback`,
`POST /app-feedback` — no authentication beyond passing `deck_id`.

## Sync and offline behavior

Offline-first. No realtime sync.

- On open (if online): `GET /questions` and `GET /question-feedback`
  merge into local cache/state. Nothing is ever deleted locally.
- If offline: use whatever is cached. Rating already-synced questions
  must work with no network at all.
- Rating/app-feedback submissions write to local state immediately (UI
  never waits on network), then queue for background sending with
  retry.
- `POST /generate-question` cannot be queued — it needs a live round
  trip. A multi-second wait is accepted.

## Frontend: local storage

- `deck_id` — generated on first run, then persisted (see "Identity"
  above; corrected from "hardcoded" during Slice 3's grill).
- `questions` — local cache of `{id, text}`.
- `rated_question_ids` — set of already-rated question ids, updated
  instantly on submission (before server confirmation).

No settings, preferences, or history view yet.

## Frontend: screen layout

One screen, everything visible at once, no staged reveals:

- Question text — large, centered.
- Four rating buttons (-5 / -1 / 1 / 5), always visible, single tap.
- Free-text field below, always visible, optional.
- "Next" button — random question from cache not in
  `rated_question_ids`.
- Small, visually secondary "new topic" input →
  `POST /generate-question` (exception path, kept unobtrusive).
- Small, always-reachable entry point for app-level feedback, separate
  from the question flow.

Left open, to be decided from real use: whether a rating submits
instantly or needs a confirm step, and what "Next" does once every
cached question is rated.

## Explicitly out of scope for exploration mode

- Golden / candidate / retired question tiers.
- Personalization or smart selection logic (similarity, topic, depth,
  mood).
- Embeddings / vector search.
- Offering multiple generated questions to choose from.
- Accounts, login, or PII collection.
- Human-readable recovery/sharing code for `deck_id`.
- Onboarding flows.
