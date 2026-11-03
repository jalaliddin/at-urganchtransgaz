#!/usr/bin/env bash
# Create .env from .env.example (if absent) and fill every empty secret with a random value.
# Existing values are never overwritten.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

fill() { # fill KEY VALUE — only when KEY exists and is empty
    local key=$1 value=$2
    if grep -qE "^${key}=$" .env; then
        sed -i "s|^${key}=$|${key}=${value}|" .env
        echo "  generated ${key}"
    fi
}

fill APP_KEY "base64:$(openssl rand -base64 32)"
fill DB_PASSWORD "$(openssl rand -hex 16)"
fill DB_ROOT_PASSWORD "$(openssl rand -hex 16)"
fill REDIS_PASSWORD "$(openssl rand -hex 16)"
fill DEMO_PASSWORD "Demo$(openssl rand -hex 8)9"
fill UID "$(id -u)"
fill GID "$(id -g)"
echo ".env is ready"
