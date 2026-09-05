#!/usr/bin/env bash
# PreToolUse(Bash) hook: block `git add -A`/`--all`/`.` and `git commit -a`/
# `-am`/`--all` — CLAUDE.md: "Stage explicit paths only — never git add -A".
# Matches the flag/bare-dot forms specifically, not lookalikes
# (--allow-empty, --author, ./specific-file.txt). Emits a PreToolUse deny
# with the reason; silence = allow.
set -euo pipefail

cmd=$(jq -r '.tool_input.command // ""' 2>/dev/null || printf '')

deny() {
    jq -n --arg reason "$1" \
        '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:"deny",permissionDecisionReason:$reason}}'
    exit 0
}

REASON="Never \`git add -A\`/\`--all\`/\`.\` or \`git commit -a\`/\`-am\`/\`--all\` — CLAUDE.md: stage explicit paths only. Use \`git add <path> <path>\`."

# "git" must sit at a command-start position (line start, or right after
# && / ; / |), same anchor style as no-cd.sh — otherwise the literal text
# "git add -A" inside a commit-message body (describing the very thing
# this hook blocks) would false-positive on a command that never runs it.
CMD_START='(^|&&|;|\|)[[:space:]]*'

if printf '%s' "$cmd" | grep -Eq "${CMD_START}git[[:space:]]+add\\b.*[[:space:]](-A|--all)([[:space:]]|\$)"; then
    deny "$REASON"
fi

if printf '%s' "$cmd" | grep -Eq "${CMD_START}git[[:space:]]+add\\b.*[[:space:]]\\.([[:space:]]|\$)"; then
    deny "$REASON"
fi

if printf '%s' "$cmd" | grep -Eq "${CMD_START}git[[:space:]]+commit\\b.*[[:space:]](-a|-am|--all)([[:space:]]|\$|=)"; then
    deny "$REASON"
fi

exit 0
