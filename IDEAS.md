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
