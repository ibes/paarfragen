# PHP — house rules

Framework/language-level guidance, not a repo convention doc — none of
this depends on `src/` having real code yet, unlike a naming/DTO
convention (see `IDEAS.md`).

## Modern PHP (prefer over training defaults)

LLM training data skews toward pre-8.3 PHP. This repo targets PHP 8.5
(`mago.toml`'s `php-version`, the `.devcontainer/Dockerfile` base
image) — default to current syntax when it fits, especially:

| Feature | PHP | Use for |
|---|---|---|
| `final readonly class` | 8.2 | Use-cases, controllers, bindables, value objects |
| Typed constants | 8.3 | `private const int MAX_RATING = 4;` — not bare `const` |
| Concrete union returns | 8.0+ | `QuestionShown\|NotFound` — not a bare interface/base type |
| `?static` on bindables | 8.0+ | `resolve(): ?static` — `null` → HTTP 404 |
| Asymmetric visibility | 8.4 | `private(set)` — construct internally, read-only outside |
| Property hooks | 8.4 | Computed/controlled properties — see below |

Mago enforces some of these already (`script/check-mago`) — this table
is for getting the syntax right the first time, not a second rulebook.
When unsure how Tempest itself does something, check
`api/vendor/tempest/framework/` for the idiom rather than an older
tutorial — the framework's own code uses current PHP.

### Property hooks (PHP 8.4)

Hooks replace hand-written getters/setters with `{ get; }`, `{ set; }`,
or `{ get => $expr; }` on a property.

**Helpful when:**
- A property is **derived** from another, read-through, no stored
  field (e.g. a `$statusLabel { get => $this->status->label(); }`).
- An **interface** declares storage + access shape.
- You want a controlled write (`{ get; set => …; }`) without a
  separate mutator method.

**Prefer not to, when:**
- The type is a stateless value object or use-case — keep
  `final readonly class` + constructor promotion.
- A named method reads clearer for domain behavior
  (`Deck::recordAnswer()`) — hooks are for property semantics, not
  use-case steps.

Asymmetric visibility (`private(set)`) pairs well: only the class (or
its factory) assigns; callers only read.

## Readability

Code reads top-to-bottom: guards first, then the work.

- **No magic** — explicit names, types, and steps; no clever shortcuts
  or hidden behavior.
- **Guard early** — preconditions at the top; `return` or `throw` on
  failure. Prefer guards over nested `if`/`else`.
- **One guard per `if`** — each condition in its own block, so one
  check can be edited in isolation.
- **Extract to clarify** — a `private` method when it pulls real detail
  out of the main flow. Not for one-liners.

## Architecture

`Domain/` and `Application/` stay framework-free — no Tempest, HTTP, or
database imports. Mechanically enforced by `mago.toml`'s guard (see
`api/README.md`), not just this doc.

## Tests

- PHPUnit — version in `api/composer.lock`.
- `Domain`/`Application`: plain PHPUnit, no framework boot.
- `Infrastructure`: needs a route + DB + real HTML/JSON before
  assertions make sense — see `api/tests/` once it has real content
  (currently empty `.gitkeep` placeholders).
