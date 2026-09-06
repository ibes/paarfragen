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
(`IDEAS.md`) is a checkpoint, not the destination. Lean/kaizen:
improving how the work gets done outranks the work itself — don't let a
well-kept log become a substitute for actually closing the gap. Give
friction fixes high priority, not the highest: don't drop the actual
task at hand to chase every papercut, but don't let "later" become the
default either. A recurring entry is the signal to stop deferring and
build the fix, not to write a better description of it.

## Product over system

System work — skills, scripts, reference docs, audits — is cheap to
justify: there's always another gap to close, and each one looks
reasonable on its own. It only earns priority when it actually
unblocks or de-risks real product work (a spec, `Domain`/`Application`/
`Infrastructure` code, a screen); otherwise it's a way to stay busy
without shipping anything a user would ever see. When the next task
could reasonably be either more system work or a step toward the
product, say so explicitly instead of quietly defaulting to system
work — and push back if system work keeps winning session after
session with nothing to show against `specs/STATUS.md`'s own next
step.
