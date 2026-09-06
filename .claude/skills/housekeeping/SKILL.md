# Housekeeping — end of session

Leave the repo ready for the next session. Report first; fix only with
user OK. Never commit unless the human asks.

**Determinism split:** session facts *and* their severity come from
`script/repo-hygiene` in one pass — its `FINDINGS:` section is
pre-graded. Adopt each finding as-is; add judgment only for what the
script can't decide (below).

---

## Run the audit

```
script/repo-hygiene
```

Hold the output. `FINDINGS:` is pre-graded (🟡 or `none`) — adopt as-is.
Everything above it is the raw data those findings were derived from:
`BRANCH`/`UPSTREAM`/`AHEAD`/`BEHIND`, `LAST_COMMIT`, `STAGED`/`MODIFIED`/
`UNTRACKED` (+ `UNTRACKED_FILES`), `WORKTREES`.

## Judgment

What the script can't decide for you:

- For each `UNTRACKED_FILES` entry → decide with the user whether it
  should be added, gitignored, or deleted. Don't guess silently.
- `UPSTREAM: none` → fine, not a problem — a branch may not be pushed
  yet. No finding needed either way.
- A `stale-merged-branch` finding → confirm with the user before
  `git branch -d <branch>` (and `git push origin --delete <branch>` if
  it has a remote counterpart worth cleaning up too).
- More than one entry under `WORKTREES` → informational; flag one only
  if it looks abandoned, and ask before `git worktree remove`.
- If `FINDINGS` includes `dirty-tree` → draft the actual one-line
  commit message from what changed (the script only tells you *that*
  it's dirty).

## Report

```
## Housekeeping — YYYY-MM-DD

### 🔴 Before you close
- [N] [action] — [rationale]

### 🟡 Tidy up
- [N] [action] — [rationale]

### 🟢 All clear
- …

### Status snapshot
- Branch: …
- Last commit: …
- Ahead/behind: …
- Worktrees: …
```

Number items across 🔴 and 🟡. Empty bucket → `- Nothing.`
All buckets empty → `✅ Repo ready for next session.`

Ask which numbered items to act on now; apply only what's accepted.
