#!/usr/bin/env bash
# PreToolUse(Bash) hook: block `cd` used as a command. The shell's cwd
# persists across tool calls and every script/* self-anchors
# (cd "$(dirname "$0")/.."), so a leading `cd` is unnecessary and triggers
# an extra permission prompt for no reason. Matches cd at command start or
# after && / ; / | — not `cd` as a substring (mkdir cdx) or a trailing
# target (cdx). Emits a PreToolUse deny with the reason; silence = allow.
set -euo pipefail

cmd=$(jq -r '.tool_input.command // ""' 2>/dev/null || printf '')

if printf '%s' "$cmd" | grep -Eq '(^|&&|;|\|)[[:space:]]*cd([[:space:]]|$)'; then
    cat <<'JSON'
{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"No `cd` — the shell persists at the repo root and every script/* self-anchors (cd \"$(dirname \"$0\")/..\"). Run e.g. `script/qa` directly. For another directory use `git -C <path>` or absolute paths."}}
JSON
fi
exit 0
