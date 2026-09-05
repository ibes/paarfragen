# PHP 8.5 for api/ (Tempest) — pinned here so nobody depends on whatever
# PHP the host/VM happens to ship. Also carries Node + CLI tools so the
# same image doubles as the local dev container for the whole repo
# (api/ and frontend/ together), not just a PHP-only runtime.
#
# Used two ways:
#   - docker-compose.yml's `api` service (script/* route PHP/composer
#     calls through `docker compose run --rm api ...`, in cloud sessions
#     and locally alike — see script/lib/api-php)
#   - this same service as the local VS Code Dev Container (devcontainer.json
#     points at it via dockerComposeFile + service, not a separate build)
FROM php:8.5-cli-trixie

# Optional trust for a sandbox's TLS-inspecting egress proxy. The bracket
# in the source path makes this COPY a glob — it's a no-op when the file
# isn't there, which is the case everywhere except a Claude Code Remote
# session build (session-start.sh writes it, and passes HAS_SESSION_CA,
# before `docker compose build` — see .gitignore). That sandbox routes all
# container HTTPS through a policy-enforcing gateway that re-terminates
# TLS with its own CA; a stock base image doesn't trust it, so every plain
# HTTPS call below (deb.nodesource.com, later composer/npm registries)
# fails cert validation otherwise. Documented fix for exactly this
# situation: /root/.ccr/README.md, "docker build / docker run" section.
# (The cert content itself goes through this file, not a build-arg — its
# multi-KB PEM text overflows the exec argv limit as an ARG value.)
ARG HAS_SESSION_CA=""
COPY .devcontainer/session-ca.cr[t] /usr/local/share/ca-certificates/
RUN if [ -n "$HAS_SESSION_CA" ]; then \
        apt-get update -qq \
        && apt-get install -y --no-install-recommends ca-certificates -qq \
        && update-ca-certificates \
        && apt-get clean && rm -rf /var/lib/apt/lists/*; \
    fi
ENV NODE_EXTRA_CA_CERTS=${HAS_SESSION_CA:+/usr/local/share/ca-certificates/session-ca.crt}

# Extensions tempest/framework's own composer.json requires (ext-dom,
# ext-fileinfo, ext-intl, ext-libxml, ext-mbstring, ext-pdo, ext-readline,
# ext-simplexml), plus pdo_sqlite/sqlite3 for local persistence and curl
# for Guzzle (an indirect Tempest dependency). Of those, only `intl` is
# actually missing from `php:8.5-cli-trixie` — `php -m` confirms
# pdo_sqlite/sqlite3/curl/dom/simplexml/readline are already built in.
# Installing them again via docker-php-ext-install fails on this base
# image anyway: PHP 8.5 ships their ext/*/config.m4 as config0.m4
# (a stub only the top-level ./buildconf processes), so phpize can't
# build them standalone — confirmed by extracting docker-php-source and
# testing docker-php-ext-install per extension.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        unzip \
        git \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer — copied from its own official image rather than piping
# getcomposer.org's installer through bash.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node, for frontend/ work from the same shell (matches redlich's
# devcontainer carrying its whole toolchain in one image).
ARG NODE_MAJOR=22
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Search/nav tools that beat plain grep/find (same idea as redlich's
# .devcontainer/Dockerfile) — all from Debian's default archive, no
# third-party PPA, so this stays reliable regardless of network policy.
RUN apt-get update && apt-get install -y --no-install-recommends \
        ripgrep \
        fd-find \
        jq \
        tree \
        httpie \
        shellcheck \
        sqlite3 \
    && ln -s /usr/bin/fdfind /usr/local/bin/fd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ast-grep: structural code search/rewrite (survives reformatting, unlike
# grep). gh and git-delta deliberately left out — both need a network path
# (cli.github.com's own apt source, a GitHub release download) that isn't
# reliably available from every build context this image runs in; add them
# by hand locally if useful, see SETUP-LOG.md.
# --force: its `sg` binary collides with Debian's own /usr/bin/sg
# (the "execute as a different group" utility from `login`); ast-grep's
# search tool is what this image's shell actually wants at that name.
RUN npm install -g @ast-grep/cli --force

WORKDIR /workspace
