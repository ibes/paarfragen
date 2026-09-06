# Values

The reasoning behind the rules in `CLAUDE.md`, not a rulebook itself. The
other files are mechanical: `CLAUDE.md` says what to do, `FRICTION.md`/
`IDEAS.md`/`SETUP-LOG.md` capture what happened and why. This file is for
situations none of them cover explicitly — decide from these instead of
guessing.

## Simple over impressively complex

Prefer the plain tool over the clever one. A shell script beats a
framework; a flat file beats a database; one obvious rule beats a
configurable system for expressing many rules. Complexity has to earn its
place by solving a problem that actually exists here now — "this could
handle more later" is not that problem. When two options solve today's
need equally well, take the simpler one even if the other looks more
capable.

## Friction gets solved, not just logged

Naming a problem (`FRICTION.md`) or writing up a considered fix
(`IDEAS.md`) is a checkpoint, not the destination. Lean/kaizen: surface
friction fast and cheaply, but don't let a well-kept log become a
substitute for actually closing the gap. A recurring entry is a signal to
build the fix, not to write a better description of it.
