#!/bin/bash
# SessionStart hook for Claude Code on the web.
# Installs CLI tools + project dependencies so script/qa, script/check and
# search tools work without a permission prompt per command. Idempotent —
# safe to re-run on resume/clear/compact, not just on a fresh container.
#
# api/ needs PHP 8.5 (Tempest) — routed entirely through the api/ Docker
# image (.devcontainer/Dockerfile, docker-compose.yml) instead of trying
# to apt-get install a PHP version onto the session VM itself. That PPA
# install was tried and confirmed blocked from a live session (see
# SETUP-LOG.md). Docker Hub pulls need this environment's Network access
# set to Custom with `production.cloudfront.docker.com` allowed — not on
# by default, confirmed the hard way; see specs/STATUS.md for that host
# plus the others (`deb.debian.org`, `deb.nodesource.com`) the Dockerfile
# itself needs on the same allowlist before its `apt-get` stages pass.
#
# This hook re-runs (and re-builds the image, hitting Docker's layer
# cache) on every session start — it does NOT get the environment's own
# ~7-day filesystem-snapshot caching. For that, add the same
# `docker compose build api` (or a registry `pull`, once one exists) to
# this environment's **Setup script** field at claude.ai/code — that's a
# separate, UI-configured mechanism from this repo file. See
# specs/STATUS.md and https://code.claude.com/docs/en/cloud-environments#setup-scripts.
set -uo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(dirname "$0")/../..}" || exit 1

echo "== session-start: CLI tools =="
# ripgrep ships with this base image already; the rest are plain Ubuntu
# archive packages (not a PPA) — reliable regardless of this session's
# network policy for third-party apt sources.
sudo apt-get update -qq
sudo apt-get install -y --no-install-recommends \
  fd-find jq tree sqlite3 shellcheck httpie \
  || echo "WARNING: apt-get install of CLI tools failed — continuing anyway."
[ -x /usr/bin/fdfind ] && sudo ln -sf /usr/bin/fdfind /usr/local/bin/fd

npm install -g @ast-grep/cli \
  || echo "WARNING: ast-grep install failed — continuing anyway."

echo "== session-start: docker daemon =="
if docker info >/dev/null 2>&1; then
  echo "Docker daemon already running."
else
  # `sudo service docker start` fails here — its init script hits a
  # `ulimit` call this sandbox disallows. `sudo dockerd` directly works
  # fine; it just isn't started by anything automatically. See
  # SETUP-LOG.md ("Correction: the daemon works; the real blocker is one
  # CDN host").
  sudo nohup dockerd >/var/log/dockerd-session-start.log 2>&1 &
  disown
  for _ in $(seq 1 15); do
    docker info >/dev/null 2>&1 && break
    sleep 1
  done
  if docker info >/dev/null 2>&1; then
    echo "Docker daemon started."
  else
    echo "WARNING: dockerd didn't come up within 15s — see /var/log/dockerd-session-start.log."
  fi
fi

echo "== session-start: api/ container (PHP 8.5 for Tempest) =="
if docker info >/dev/null 2>&1; then
  # Trust this sandbox's egress CA for the build (/root/.ccr/README.md,
  # "docker build / docker run") — HAS_SESSION_CA is a no-op when unset.
  build_args=()
  if [ -r /root/.ccr/ca-bundle.crt ]; then
    cp /root/.ccr/ca-bundle.crt .devcontainer/session-ca.crt
    build_args+=(--build-arg "HAS_SESSION_CA=1")
  fi
  docker compose build "${build_args[@]}" api \
    || echo "WARNING: building the api/ Docker image failed — api/ tests won't run this session. See specs/STATUS.md."
else
  echo "WARNING: Docker isn't available/running in this session — skipping the api/ image. See specs/STATUS.md."
fi

echo "== session-start: project dependencies =="
(cd frontend && npm install) \
  || echo "WARNING: frontend npm install failed."

if docker info >/dev/null 2>&1; then
  script/lib/api-php composer install --no-interaction \
    || echo "WARNING: api composer install failed even with the container built."
else
  echo "Skipping api composer install — no Docker available this session."
fi

echo "== session-start: done =="
exit 0
