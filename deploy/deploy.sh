#!/usr/bin/env bash
# Deploys a commit (default origin/master) to the timeline VPS, keeping the server's database.
# Usage: deploy/deploy.sh [ref]    Env: DEPLOY_HOST (libretto), DEPLOY_DIR (/opt/php-timeline), DEPLOY_PORT (7103)
set -euo pipefail

host=${DEPLOY_HOST:-libretto}
dir=${DEPLOY_DIR:-/opt/php-timeline}
port=${DEPLOY_PORT:-7103}
ref=${1:-origin/master}

ssh "$host" sudo -n bash -s -- "$dir" "$ref" "$port" <<'REMOTE'
set -euo pipefail
dir=$1 ref=$2 port=$3
db=data/timeline.db
php_env=(env PHP_INI_SCAN_DIR=/etc/php/8.5/cli-swoole/conf.d)
as_app() { sudo -n -u timeline env HOME=/tmp COMPOSER_HOME=/tmp/timeline-composer "$@"; }

cd "$dir"

dirty=$(as_app git status --porcelain | grep -v -e " $db\$" -e " data/backups/\$" || true)
if [ -n "$dirty" ]; then
    echo "Stopped: $dir has changes that are not in git:" >&2
    echo "$dirty" >&2
    exit 1
fi

as_app git fetch --quiet origin
target=$(as_app git rev-parse --verify --quiet "$ref^{commit}") || { echo "Unknown ref: $ref" >&2; exit 1; }
previous=$(as_app git rev-parse HEAD)
echo "Deploying $(as_app git log -1 --format='%h %s' "$target") (was ${previous:0:7})"

systemctl stop timeline
backup=""
if [ -f "$db" ]; then
    as_app mkdir -p data/backups
    backup="data/backups/timeline-$(date +%Y%m%d-%H%M%S)-${previous:0:7}.db"
    as_app cp -p "$db" "$backup"
    echo "Database saved to $dir/$backup"
fi

# Older commits track the database: restore the tracked copy so the checkout can switch cleanly
if as_app git ls-files --error-unmatch "$db" >/dev/null 2>&1; then
    as_app git checkout --quiet -- "$db"
fi
as_app git checkout --quiet --detach "$target"
if [ -n "$backup" ]; then
    as_app cp -p "$backup" "$db"
fi

as_app "${php_env[@]}" php8.5 /usr/bin/composer install --no-dev --no-interaction --no-progress \
    --optimize-autoloader --ignore-platform-req=ext-inotify
[ -f "$db" ] || as_app php8.5 bin/seed.php

systemctl start timeline
for _ in $(seq 1 20); do
    page=$(curl -fs --max-time 2 "http://127.0.0.1:$port/" || true)
    if [[ $page == *'Life Timeline'* ]]; then
        echo "Live: $(as_app git log -1 --format='%h %s')"
        exit 0
    fi
    sleep 1
done
echo "The app did not answer on port $port. Roll back with: deploy/deploy.sh ${previous:0:7}" >&2
exit 1
REMOTE
