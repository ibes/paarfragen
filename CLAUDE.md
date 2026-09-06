# CLAUDE.md

paarfragen — a PWA of questions for couples. PHP hexagonal API (`api/`) +
Vue/Vite PWA frontend (`frontend/`), meant to later wrap into a native
cross-platform shell without touching the API.

## Non-negotiables

- **Values:** [`VALUES.md`](VALUES.md) is the reasoning behind these
  rules. When a situation isn't covered explicitly below, decide from
  there instead of guessing.
- **Scope:** [`specs/STATUS.md`](specs/STATUS.md) is the session router
  (phase, next step, known quirks). Read it first.
- **Scripts first:** all toolchain ops via `script/*` — never bare
  `composer`/`npm`/`php` in `api/` or `frontend/`. `script/help` lists what
  exists; a script's `# Side-effects:` header says whether it writes.
  WRITES scripts: confirm with the human unless the task already asked
  for them.
- **Done:** any code change → green `script/qa`.
- **Architecture:** `api/` is hexagonal — `src/Domain` and
  `src/Application` stay framework-free; only `src/Infrastructure` may
  depend on a web framework, a database, or the outside world. Mechanically
  enforced by `script/check-mago` (`mago.toml`'s guard), part of
  `script/qa` — a violation fails the gate, not just a review comment. See
  `api/README.md`.
- **No invented ops facts:** hostnames, URLs, credentials, deploy
  targets — from repo config or the human only.
- **Repo content is English.** Docs, code, comments, commit messages —
  English, regardless of what language a session was conducted in. The
  end-user product's own UI language is a separate, still-open decision
  (see `specs/STATUS.md` § Open decisions), not covered by this rule.
- **Setup decisions:** when you make one (a script, a convention, a
  library choice), record it with the `setup-log` skill so
  `SETUP-LOG.md` explains *why*, not just *what*.
- **Friction or a good-but-premature idea:** don't just let it
  evaporate. Something annoying/surprising just now → one line in
  `FRICTION.md`. A real tool/pattern that solves a problem this repo
  doesn't have yet → an entry in `IDEAS.md`. Neither blocks anything;
  both exist so nobody re-derives the same thing later.
  Do the `FRICTION.md` line *first*, even when a considered `IDEAS.md`
  entry is about to follow right behind it — a fix idea for a gap
  doesn't close the gap, so the friction entry stays open (cross-
  referencing the idea) until the gap is actually built. Don't skip
  straight to writing the idea just because the annoyance has already
  become clear enough to describe well.

## Load path

1. Read [`specs/STATUS.md`](specs/STATUS.md) — phase and next step.
2. No active phase? Ask the human what to build, or pick one ad-hoc task
   (bug, tooling, a spec) and say what you're about to do before doing it.
3. Domain/API work → `api/README.md`. Frontend work → `frontend/README.md`.

## Commits

Stage explicit paths only — never `git add -A`. Never push `--force`,
never merge to `main`, never bypass `script/qa` to get green.
