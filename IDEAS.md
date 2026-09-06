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
`api/tests/README.md`.
**Trigger has fired, not yet written:** `api/tests/*` now has real
tests (Slice 2's 9 PHPUnit tests), and Slice 3's spec
(`specs/2026-09-06-slice-3-frontend-api-wiring.md`) already leans on
this same unwritten rule for its own frontend testing section. Worth
writing from both slices' actual experience rather than staying
speculative.

**External input, evaluated 2026-09-06, not adopted wholesale:**
Matt Pocock's `tdd` skill
(https://github.com/mattpocock/skills/blob/main/skills/engineering/tdd/SKILL.md)
covers close to the same ground — good tests exercise behavior through
public interfaces ("seams"), never mock internals or assert through a
side channel. Two things from it are worth folding into this repo's
own doc when it's written:
- **The "tautological test" anti-pattern** (an assertion that
  recomputes the expected value the same way the code does, so it
  can't ever disagree with the code) — sharper and more specific than
  anything currently in this entry.
- **"Seam"** as the name for the public boundary a test exercises —
  precise, worth borrowing as vocabulary.

Not adopted as an installed skill: its "red before green, always" rule
would contradict the Domain/Application-test-first-but-Infrastructure-
test-last split above, which exists specifically because that split
doesn't hold for a hexagonal app with a framework boundary (a
Tempest-routed test can't meaningfully fail-then-pass before the
routing/DB plumbing exists). It also references sibling skills this
repo doesn't have (`codebase-design` for its seam/module vocabulary,
a `code-review` skill it expects to own the refactor stage) — grabbing
just the `tdd` piece leaves those references dangling. And nothing in
this repo's own test-writing so far (Slice 2's tests hit real
HTTP/DB seams, no mocked internals) shows the specific failure mode
the skill defends against — adopting a new workflow to prevent a
problem not yet observed here would be exactly the kind of
complexity `VALUES.md` says has to earn its place.

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
**Not now because:** `frontend/src/` now has real structure since
Slice 3 (`api/`, `composables/`, `deck/`), but it's still three small,
already-obviously-separated modules with no accidental cross-imports
observed yet — enforcing a boundary now would still be guessing ahead
of an actual violation. Revisit once `frontend/src/` is big enough
that "just don't do that" stops being a reliable convention on its
own.

## `frontend/reference/` — a Vue/Vite/tooling gotchas doc, mirroring `api/reference/`

**What:** A `frontend/reference/` doc (or a `frontend/reference/
vue.md`) for framework/tooling behavior that's surprising and worth
not rediscovering — the same role `api/reference/tempest.md`'s
"Framework gotchas" section plays for `api/`.
**Why it might matter:** four gotchas have landed so far, each parked
in a different spot rather than one lookup place: ESLint's `no-undef`
not knowing about tsconfig's DOM lib (fixed, reasoning lives in an
inline `eslint.config.js` comment); Playwright's absolute-import-path
and versioned-Chromium-binary quirks, and `waitUntil: "networkidle"`
hanging against Vite's dev server (all three folded into
`script/lib/playwright-launch.mjs`'s header comment once that helper
got built, Slice 4's retro); and, from Slice 5, a service worker's
`active`/`controller` state not meaning Workbox's precache has
actually finished populating Cache Storage (currently only in
`FRICTION.md` + that slice's own spec — no natural code-comment home
this time, since it's a testing-methodology fact, not tied to one
helper file). None of these are hard to find individually, but there's
still nowhere a future session would look *before* writing frontend
code the way `api/reference/tempest.md` is read up front for `api/`
work — each gotcha's home was decided ad hoc, after the fact.
**Rough shape:** Same shape as `api/reference/tempest.md`'s "Framework
gotchas" section — short, dated-free bullets, referenced from
`frontend/README.md`'s "Toolchain" section the way `api/README.md`
points at `api/reference/`.
**Not now because:** the originally-stated "third or fourth gotcha"
threshold has technically been reached (this is the fourth) — worth
raising with the human next time frontend work happens, rather than
building it unasked mid-retro (per the `retro` skill's own rule: no
inventing a reference doc on the spot). Still not been a real cost
yet — none of the four needed rediscovering from scratch, each had
*some* documented home — so it's a "should probably happen soon," not
a "was actively painful this build."

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
nothing references anymore), the same relationship the entry below
describes between `/housekeeping` and a backing script.
**Not now because:** there's no accumulated backlog yet to review —
`IDEAS.md` and `FRICTION.md` were just created — and no slice/phase
structure for a review cadence to hook into. Revisit once either file
is long enough that "just read it" stops scaling, or once a
slice/phase system exists to anchor the cadence to.

## Reka UI for interactive widgets that are hard to get right by hand

**What:** [Reka UI](https://reka-ui.com) (formerly Radix Vue) —
headless, unstyled accessible component primitives (Dialog, Popover,
Select, Tabs, Accordion, …). Unlike shadcn-vue (discussed and rejected
for now, same reasoning below), it's a plain npm dependency, not a
Tailwind-coupled copy-into-your-repo workflow — no CSS framework
commitment, adopt one primitive at a time, bring your own styling.
**Why it might matter:** focus management, ARIA semantics, and
keyboard navigation for things like modals/dialogs are exactly the
kind of code that's both easy to get subtly wrong by hand and hard to
verify correct just by reading a diff — a widely-used, tested
primitive shifts that risk from "trust the diff" to "trust a library."
**Rough shape:** add as a normal `frontend/package.json` dependency
the first time a specific hard-to-get-right widget is actually needed;
import just that primitive, style it with this project's own CSS via
Reka UI's data-attribute styling hooks.
**Not now because:** `specs/exploration-mode.md`'s screen layout is
four rating buttons, a text field, a "Next" button, and a small
feedback entry point — all native `<button>`/`<input>` elements,
already accessible for free, no Dialog/Select/Popover/Tabs/Accordion
anywhere in the current design. **Concrete trigger to watch for:** if
the "small, always-reachable feedback entry point" ever becomes an
actual modal/dialog (rather than an inline panel or separate route),
that's the first real use case — Reka UI's Dialog primitive handles
focus-trap/Escape/`aria-modal` correctly where a hand-rolled one is
easy to get wrong.

## Semantic review skills for hexagon/Tempest drift, once real code exists

**What:** `mago.toml`'s guard already catches *structural* violations
(an `Infrastructure` class importing a `Domain` namespace, wrong
visibility). It can't catch *semantic* drift: business logic sitting in
a controller instead of the domain, a domain entity thrown as a
framework exception, a Tempest `Request`/view-object convention
followed inconsistently. A pair of thin skills — `hexagon-check` and
`tempest-check` — each apply a short fail-conditions/pass-looks-like
checklist to the current diff, report file+line+why, and explicitly
don't refactor unasked.
**Why it might matter:** exactly the two conventions this repo has
already committed to (`CLAUDE.md`'s hexagonal-architecture rule, the
Tempest framework choice) — the two places drift would matter most and
Mago structurally can't see.
**Rough shape:** a `agent/reviews/hexagon.md` / `agent/reviews/
tempest.md` checklist doc each, plus a skill that's little more than
"apply this checklist to the diff." Seen working elsewhere (emsig) as
exactly this shape, before losing access to that repo — but its actual
checklist entries are that app's own conventions (a `Guide/{Screen}/`
module layout, `PublicIdEncoder`, specific view-object naming), not
Tempest conventions in general, so the checklist itself has to be
written fresh from this repo's own Infrastructure code, not copied.
**Not now because:** `api/src/Domain`, `Application`, and
`Infrastructure` are still empty `.gitkeep` placeholders — a
drift-checklist needs real Infrastructure code to observe conventions
from, the same reasoning as the Mago DTO-rules entry above.

