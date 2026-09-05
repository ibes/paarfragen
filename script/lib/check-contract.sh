# Description: Lets a script/check-* emit the {status, violations, total, summary} envelope. Sourced, not run directly.
# Side-effects: none.

# contract_emit clean|red <violations-json-array> [summary]
# Sorts violations by (file, line), caps at 50, sets total to the pre-cap
# count. summary is only used (and only allowed) when status is clean.
# Exits 0 on clean, 1 on red — the caller's script ends here.
contract_emit() {
    local status=$1 violations=$2 summary=${3:-}
    local total capped
    total=$(jq 'length' <<<"$violations")
    capped=$(jq 'sort_by(.file, .line) | .[:50]' <<<"$violations")
    if [[ "$status" == clean ]]; then
        jq -n --argjson v "$capped" --argjson t "$total" --arg s "$summary" \
            '{status: "clean", violations: $v, total: $t, summary: $s}'
        exit 0
    fi
    jq -n --argjson v "$capped" --argjson t "$total" \
        '{status: "red", violations: $v, total: $t}'
    exit 1
}
