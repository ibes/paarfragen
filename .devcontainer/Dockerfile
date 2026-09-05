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

# Extensions tempest/framework's own composer.json requires (ext-dom,
# ext-fileinfo, ext-intl, ext-libxml, ext-mbstring, ext-pdo, ext-readline,
# ext-simplexml — confirmed from its composer.json, not guessed), plus
# pdo_sqlite/sqlite3 for local persistence and curl for Guzzle (an
# indirect Tempest dependency).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libsqlite3-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libreadline-dev \
        unzip \
        git \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        pdo_sqlite \
        sqlite3 \
        curl \
        dom \
        simplexml \
        readline \
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
RUN npm install -g @ast-grep/cli

WORKDIR /workspace
