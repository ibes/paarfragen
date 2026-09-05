# CLAUDE.md

paarfragen — a PWA of questions for couples. PHP hexagonal API (`api/`) +
Vue/Vite PWA frontend (`frontend/`), meant to later wrap into a native
cross-platform shell without touching the API.

## Non-negotiables

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
  depend on a web framework, a database, or the outside world. See
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

## Load path

1. Read [`specs/STATUS.md`](specs/STATUS.md) — phase and next step.
2. No active phase? Ask the human what to build, or pick one ad-hoc task
   (bug, tooling, a spec) and say what you're about to do before doing it.
3. Domain/API work → `api/README.md`. Frontend work → `frontend/README.md`.

## Commits

Stage explicit paths only — never `git add -A`. Never push `--force`,
never merge to `main`, never bypass `script/qa` to get green.
