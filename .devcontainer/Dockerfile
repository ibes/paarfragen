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

# Optional trust for a Claude Code Remote sandbox's TLS-inspecting egress
# proxy (session-start.sh writes the cert + build-arg; see .gitignore and
# /root/.ccr/README.md's "docker build / docker run" section). The bracket
# in the COPY source makes it a glob, so this is a no-op everywhere else.
ARG HAS_SESSION_CA=""
COPY .devcontainer/session-ca.cr[t] /usr/local/share/ca-certificates/
RUN if [ -n "$HAS_SESSION_CA" ]; then \
        apt-get update -qq \
        && apt-get install -y --no-install-recommends ca-certificates -qq \
        && update-ca-certificates \
        && apt-get clean && rm -rf /var/lib/apt/lists/*; \
    fi
ENV NODE_EXTRA_CA_CERTS=${HAS_SESSION_CA:+/usr/local/share/ca-certificates/session-ca.crt}

# `intl` is the one extension the dependency tree needs that
# php:8.5-cli-trixie doesn't already bundle (`script/check` runs
# `composer check-platform-reqs` to verify that against the real vendor
# tree — check that instead of trusting this comment). The others don't
# install via docker-php-ext-install on this PHP version anyway: see
# SETUP-LOG.md.
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

# Node, for frontend/ work from the same shell — one image carries the
# whole repo's toolchain rather than juggling two dev containers.
ARG NODE_MAJOR=22
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Search/nav tools that beat plain grep/find — all from Debian's default
# archive, no third-party PPA, so this stays reliable regardless of
# network policy.
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
