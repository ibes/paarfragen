# Setup — Evolution Log

History of how this repo's setup was built and why. Not an inventory
(that's `script/help` for commands, the filesystem for everything else)
— just the decisions and the reasoning behind them, so they don't have
to be re-derived or re-litigated later.

---

## 2026-09-05 — Monorepo split: `api/` (PHP) + `frontend/` (Vue/Vite)

Two independent projects, each with its own dependency manifest
(`api/composer.json`, `frontend/package.json`), rather than one Tempest
app serving both API and server-rendered views (as `emsig` does).

**Why:** the frontend is meant to become a PWA now and get wrapped in a
native cross-platform shell (e.g. Capacitor) later, without the API
changing. Coupling them into one framework's request/view cycle (like
emsig's Tempest+Vite setup) would make that split harder later, not
easier.

## 2026-09-05 — `api/` hexagonal skeleton, no web framework picked yet

`src/Domain`, `src/Application`, `src/Infrastructure` exist as empty
directories (`.gitkeep`); `composer.json` requires only `php` +
`phpunit`.

**Why:** `emsig` (sibling repo, same author) uses Tempest, but Tempest
`^3.0` requires PHP `^8.5` and this environment only has PHP 8.4.19
installed — couldn't verify an install here. Rather than pick a
framework untested in this environment, left it open in
`specs/STATUS.md` for a real decision once the target runtime (and the
first spec) is known. Domain/Application layers don't need a framework
to start being written anyway.

## 2026-09-05 — `script/` header convention: Description + Side-effects

Every `script/*` file carries `# Description:` and `# Side-effects:`
header comments; `script/help` greps them to generate the command list.

**Why:** copied from `emsig`/`redlich` (same author, same convention) —
the header *is* the documentation, so it can't drift out of sync with a
separately maintained doc.

## 2026-09-05 — Agent-first meta files added before any product code

`CLAUDE.md`, `specs/STATUS.md`, `agent/skills/INDEX.md`, this file, and
`script/{help,setup,qa,check}` were set up before writing any
Domain/Application/Infrastructure code or frontend UI.

**Why:** requested explicitly — the app itself stays a minimal skeleton
for now, but the working conventions (specs first, `script/qa` as the
gate, decisions logged here) should be in place from the first commit,
matching how `emsig`/`redlich` operate.
