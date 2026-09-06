# Description: Shared helper for script/check — runs one script/check-* and folds its result into entries[]/worst. Sourced, not run directly.
# Side-effects: none.
#
# THE CONTRACT: a check "conforms" if it exits 0 (clean) or 1 (red) and its
# stdout is exactly one JSON object with a "status" key — the shape
# {status, violations, total, summary?} that a hand-written check-* should
# emit. Detected purely via `jq -e '.status'` on captured stdout, never by
# name — any check becomes "rich" the moment it speaks this, no wiring
# needed here. A non-conforming check (composer, vue-tsc, or anything that
# exited 2) falls back to {name, status, exit, raw_output}, raw_output
# omitted when status is clean, so a clean run stays a one-line entry.
#
# WRITING A NEW check-*: any `var=$(cmd)` where cmd is *expected* to
# sometimes exit non-zero (a grep with no match, a linter reporting
# findings) needs `|| true` or `|| rc=$?` at the end, even though this
# file's own `set -Eeuo pipefail` + `trap 'exit 2' ERR` shouldn't fire on
# a plain assignment per bash's own documented errexit exemption for
# that form — the ERR trap fires anyway on some bash versions regardless
# of that exemption. Confirmed the hard way: an early check-frontend-lint
# reported every real finding as "broken" (exit 2) instead of "red"
# (exit 1) until this was added.

worst=0
entries=()

run_check() {
    local name=$1
    shift
    local errfile out rc=0
    errfile=$(mktemp)
    out=$("$@" 2>"$errfile") || rc=$?
    local err
    err=$(<"$errfile")
    rm -f "$errfile"

    local verdict
    case $rc in
        0) verdict=0 ;;
        1) verdict=1 ;;
        *) verdict=2 ;;
    esac
    (( verdict > worst )) && worst=$verdict
    local status
    case $verdict in
        0) status=clean ;;
        1) status=red ;;
        *) status=broken ;;
    esac

    local conforms=0
    if [[ "$status" != broken && -n "$out" ]] \
        && jq -e '.status' <<<"$out" >/dev/null 2>&1; then
        conforms=1
    fi

    local entry
    if (( conforms )); then
        entry=$(jq --arg name "$name" --argjson exit "$rc" '. + {name: $name, exit: $exit}' <<<"$out")
    else
        local raw="${out}"$'\n'"${err}"
        if [[ "$status" == clean ]]; then
            entry=$(jq -n --arg name "$name" --arg status "$status" --argjson exit "$rc" \
                '{name: $name, status: $status, exit: $exit}')
        else
            entry=$(jq -n --arg name "$name" --arg status "$status" --argjson exit "$rc" --arg raw "$raw" \
                '{name: $name, status: $status, exit: $exit, raw_output: $raw}')
        fi
    fi
    entries+=("$entry")
}
