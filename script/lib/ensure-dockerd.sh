# Description: (lib, not run directly) ensure_dockerd() starts the Docker daemon if it isn't running and this is a Claude Code Remote session; no-op everywhere else. Shared by session-start.sh and script/lib/api-php so both self-heal the same way instead of drifting.
# Side-effects: starts `dockerd` (via `sudo nohup dockerd`) when missing, only when CLAUDE_CODE_REMOTE=true.

ensure_dockerd() {
    docker info >/dev/null 2>&1 && return 0
    [ "${CLAUDE_CODE_REMOTE:-}" = "true" ] || return 1

    # Same start this sandbox's `service docker start` can't do directly —
    # its init script hits a `ulimit` call the sandbox disallows (see
    # SETUP-LOG.md). Can die mid-session, not just before the first call,
    # so this runs from any Docker call, not just session start.
    sudo nohup dockerd >/var/log/dockerd-session-start.log 2>&1 &
    disown
    for _ in $(seq 1 15); do
        docker info >/dev/null 2>&1 && return 0
        sleep 1
    done
    return 1
}
