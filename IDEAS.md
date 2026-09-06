# Ideas — good, not needed yet

Considered ideas worth remembering, not worth building now — tools or
patterns that solve a real problem, but the problem doesn't exist here
yet. The point of this file: preserve the reasoning so nobody has to
re-derive or re-research it once the moment actually comes, without
paying the cost of building it early.

Not this file:
- A decision already made and acted on → `SETUP-LOG.md`.
- A blocking, must-decide-soon question → `specs/STATUS.md` § Open
  decisions.
- Raw, unconsidered friction from just now → `FRICTION.md`.

## Entry format

```
## <short title>

**What:** the tool/pattern/convention.
**Why it might matter:** the real problem it solves.
**Rough shape:** how it'd roughly work here, 1-3 sentences.
**Not now because:** what's missing today that makes it premature.
```

When an idea gets built: remove the entry and log the actual decision
in `SETUP-LOG.md` instead. Don't let a built idea linger here.

---

## CI workflow (GitHub Actions)

**What:** A single `qa` job on a clean clone — checkout, build the
`api/` Docker image, run `script/qa`, nothing else.
**Why it might matter:** `script/qa`'s pre-commit hook is bypassable
with `--no-verify`; CI on a clean clone is the gate that can't be.
**Rough shape:** One job, Docker-based (not a native `setup-php`
action, since `api/` only runs inside the pinned container) — build
the image, then run the same `script/qa` a local session already runs.
**Not now because:** no CI provider/target decided yet, and there's
no team/PR flow this would actually gate.

## Test conventions doc (TDD order + assertion style)

**What:** A short house rule: Domain/Application are test-first,
Infrastructure is test-last (route + DB + HTML needed before real
assertions); never assert on cosmetic details (a CSS class, a DOM
structure) — assert on behavior, copy, redirects, or DB rows instead.
**Why it might matter:** cheap to state, expensive to unwind once a
test suite full of brittle cosmetic assertions exists.
**Rough shape:** A paragraph in `api/README.md` or a new
`api/tests/README.md`, once `api/tests/` actually needs guidance.
**Not now because:** `api/tests/*` are empty `.gitkeep` placeholders —
nothing to protect yet, and writing the rule before any test exists
risks getting the specifics wrong.

## `dependency-cruiser` for frontend architectural boundaries

**What:** A framework-agnostic dependency-rule checker for JS/TS —
the frontend analogue of `mago.toml`'s `[guard]`.
**Why it might matter:** once `frontend/src/` grows real internal
structure (e.g. `composables/`, an API client, stores), it'll need the
same kind of "layer X can't import layer Y" enforcement `api/` already
has.
**Rough shape:** JSON output, straightforward to fold into the
existing `{status, violations, total, summary}` contract as
`script/check-frontend-boundary`.
**Not now because:** `frontend/src/` is currently just
`App.vue`/`main.ts`/`style.css` — no layers exist to draw a boundary
between yet. Defining rules now would be guessing at a structure that
doesn't exist.

## A named spec template shape

**What:** A concrete field set for the first real slice spec: Goal /
Done when / Interfaces (pinned) / Context / Non-goals / Decisions /
Surface (a bounded file list) / Looks like.
**Why it might matter:** the `spec` skill needs a template to fill in;
having one ready avoids inventing the shape under time pressure when
the first slice actually gets written.
**Rough shape:** A `specs/TEMPLATE.md` the `spec` skill fills in,
covering scope/interfaces/non-goals explicitly so a slice spec commits
to what's *in* and *out* rather than drifting.
**Not now because:** no slice has been specced yet — the template
should be shaped by writing it against a real first attempt, not
speculatively.

## Mago: structural rules for Infrastructure DTO conventions

**What:** Rules like "`*Request` classes must implement Tempest's
`Request` interface" or "route-param bindables must be final readonly"
— shape rules for Infrastructure code that follows a chosen naming/
implementation convention.
**Why it might matter:** `mago.toml`'s guard already enforces the
Domain/Application/Infrastructure layering and a few generic shape
rules (Infrastructure classes final, Application readonly); DTO-shape
rules are the next layer of that same idea.
**Rough shape:** `[[guard.structural.rules]]` entries in `mago.toml`,
same pattern already in use.
**Not now because:** paarfragen hasn't chosen its own request/response
DTO naming or implementation convention yet — writing the rule first
would mean guessing the convention instead of the convention coming
from real Infrastructure code.

## Mago/ESLint: revisit rule thresholds once real code exists

**What:** `mago.toml`'s size-related rules (`cyclomatic-complexity`,
`excessive-parameter-list`, `excessive-nesting`, `too-many-methods`)
currently run at Mago's own default thresholds, not tuned to this
codebase.
**Why it might matter:** a threshold tuned to nothing is a guess;
once real Domain/Application classes exist, their actual natural size
is better evidence than any number picked in the abstract.
**Rough shape:** revisit the numbers (not whether the rules exist) once
a first real module's shape is known.
**Not now because:** no code exists to tune against yet.

## typescript-eslint: revisit type-aware linting

**What:** `tseslint.configs.recommendedTypeChecked` (floating-promise
and unsafe-`any` catches on top of plain syntactic linting).
**Why it might matter:** real, valuable catches beyond what `vue-tsc`
already gives — tried once already (see `SETUP-LOG.md`, the ESLint/
Prettier entry) and dropped over a `.vue`-SFC type-resolution gap
between plain `typescript-eslint` and `vue-tsc`'s own language-service
plugin.
**Rough shape:** worth trying again once the Vue/TS tooling ecosystem
closes that interop gap, or once enough real async/Promise-heavy logic
exists that the marginal catch is clearly worth fighting the interop
for.
**Not now because:** hit real friction on the very first file tested,
for zero code that exists yet to actually benefit from it.

## Extended harvest: a structured, ledger-backed friction log

**What:** `FRICTION.md` as a flat markdown file, upgraded to an
append-only structured ledger (e.g. one JSON object per line) with an
id per entry, a state per entry (`seen` → `promoted` → `built`, plus an
outcome), near-duplicate detection so the same friction isn't logged
twice, and a review cadence (time-based, or triggered after N entries
accumulate) that mines the backlog and decides what's actually worth
building — rather than relying on someone eventually reading the whole
file top to bottom.
**Why it might matter:** a flat file works while it's short; once
`FRICTION.md` has dozens of entries, "skim it occasionally" stops being
a reliable way to notice a pattern (the same friction hit five times
is a much stronger signal than any one instance, and a flat file makes
that easy to miss).
**Rough shape:** a small CLI or script (`script/friction seen "<note>"`
or similar) that appends one structured entry and assigns it an id,
plus a separate review step — run periodically, not on every entry —
that lists unresolved entries grouped/deduped for a human or agent to
triage into "build it" (→ `IDEAS.md` or straight to a decision) or
"drop it."
**Known pitfalls, worth remembering if this gets built:** a
JSONL-ledger-plus-CLI version of this idea was built and refined
elsewhere over many real entries, and hit real, non-obvious bugs along
the way — BSD vs. GNU `date` producing different timestamp formats
depending on the host OS, git index-lock races when two near-parallel
processes append to the log around the same commit, and subtlety in
resolving "which commit does this entry's anchor SHA actually mean" as
history moves forward. None of these are hard to fix once you know to
look for them, but they're exactly the kind of thing worth not
re-discovering from scratch.
**Not now because:** `FRICTION.md` doesn't exist long enough yet to
know whether flat-file skimming actually breaks down here, and this is
real, non-trivial tooling to build and maintain (a CLI, a state
machine, dedup logic) for a repo that has zero entries in the file
this would replace.

## Extended kaizen: a periodic agent-setup review pass

**What:** A dedicated review skill (distinct from the narrower
`housekeeping` skill, which only checks session/git hygiene) that
periodically audits the whole agent setup — skills, `script/*`,
conventions, `CLAUDE.md` itself — for staleness, drift, or duplication,
and decides what in `IDEAS.md`/`FRICTION.md` is actually worth building
now. Coupled to whatever phase/milestone structure a project uses (e.g.
"run it when a slice ships," once paarfragen has a slice/phase system)
rather than firing at a fixed time interval.
**Why it might matter:** `SETUP-LOG.md`, `IDEAS.md`, and `FRICTION.md`
only capture information — nothing currently *decides* when to act on
what's accumulated there. A periodic pass is what turns "we noticed
this" into "we built it," on a cadence rather than only when a human
happens to ask.
**Rough shape:** a skill that reads `IDEAS.md` + `FRICTION.md` +
recent `SETUP-LOG.md` entries, checks `script/*` for staleness (dead
scripts, missing headers — already partly covered by
`check-script-integrity`), and reports a short "here's what's worth
doing next" list rather than making changes itself. Could be backed by
small audit scripts of its own (e.g. one that flags a `script/*` file
nothing references anymore) the same way `script/check-repo-hygiene`
(see the housekeeping-audit idea, not yet written up separately here)
would back `/housekeeping`.
**Not now because:** there's no accumulated backlog yet to review —
`IDEAS.md` and `FRICTION.md` were just created — and no slice/phase
structure for a review cadence to hook into. Revisit once either file
is long enough that "just read it" stops scaling, or once a
slice/phase system exists to anchor the cadence to.
