#!/bin/bash
# SessionStart hook for Claude Code on the web.
# Installs CLI tools + project dependencies so script/qa, script/check and
# search tools work without a permission prompt per command. Idempotent —
# safe to re-run on resume/clear/compact, not just on a fresh container.
set -uo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

echo "== session-start: CLI tools =="
# ripgrep ships with this base image already; the rest are plain Ubuntu
# archive packages (not a PPA) — reliable regardless of this session's
# network policy for third-party apt sources (see the PHP note below).
sudo apt-get update -qq
sudo apt-get install -y --no-install-recommends \
  fd-find jq tree sqlite3 shellcheck httpie \
  || echo "WARNING: apt-get install of CLI tools failed — continuing anyway."
[ -x /usr/bin/fdfind ] && sudo ln -sf /usr/bin/fdfind /usr/local/bin/fd

npm install -g @ast-grep/cli \
  || echo "WARNING: ast-grep install failed — continuing anyway."

echo "== session-start: PHP 8.5 (api/ needs it for Tempest) =="
# php8.5-cli comes from the same ondrej/php PPA this image's own php8.4
# was built from — but reaching that PPA from *inside* a live session
# depends on this environment's configured network policy. If this
# fails, api/'s composer install/tests won't run this session; that's a
# known, documented limitation (specs/STATUS.md), not this script being
# broken. See docs: https://code.claude.com/docs/en/claude-code-on-the-web
if ! php8.5 -v >/dev/null 2>&1; then
  sudo apt-get install -y --no-install-recommends \
    php8.5-cli php8.5-common php8.5-curl php8.5-mbstring php8.5-xml php8.5-sqlite3 php8.5-intl \
    || echo "WARNING: PHP 8.5 not installable in this session's network policy — api/ tests won't run this session. See specs/STATUS.md."
fi

echo "== session-start: project dependencies =="
(cd frontend && npm install) \
  || echo "WARNING: frontend npm install failed."

if php8.5 -v >/dev/null 2>&1; then
  (cd api && php8.5 "$(command -v composer)" install --no-interaction) \
    || echo "WARNING: api composer install failed even with PHP 8.5 present."
else
  echo "Skipping api composer install — no PHP 8.5 available this session."
fi

echo "== session-start: done =="
exit 0
