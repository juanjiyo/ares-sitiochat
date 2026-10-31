#!/usr/bin/env bash
# Manual deployment of Ares to the production VPS (no Docker, no CI runner).
#
# Usage, from a clone of the repository:
#   scripts/vps-deploy.sh <commit>
#
# Requires an SSH alias "vps-irc" (see PENDIENTE.md) for the irc user on the VPS.
# Locally it packs <commit> with git archive (LF line endings), uploads it to
# ~/deploy-staging and runs this same script on the VPS in "remote" mode, which:
#   1. builds a release directory next to the live one, reusing .env.local and vendor/
#      (composer install only when composer.lock changed), and lints the container;
#   2. stops irc:connect gracefully (SIGTERM to the php process, not its bash wrapper);
#   3. backs up var/data and .env.local into ~/backups;
#   4. swaps the directories (the old one is kept as ares-sitiochat.old-<timestamp>);
#   5. clears the cache, applies the pending migrations and starts irc:connect again;
#   6. reports the introduced service bots and the errors logged since the restart;
#   7. prunes everything but the latest previous release, backup and staged archive.
set -euo pipefail

HOST_ALIAS="${ARES_VPS_HOST:-vps-irc}"

if [[ "${1:-}" != "remote" ]]; then
    COMMIT="${1:?usage: scripts/vps-deploy.sh <commit>}"
    SHORT=$(git rev-parse --short "$COMMIT")
    ARCHIVE=$(mktemp -d)/"$SHORT".tar.gz
    git -c core.autocrlf=false -c core.eol=lf archive --format=tar.gz -o "$ARCHIVE" "$SHORT"
    ssh -o BatchMode=yes "$HOST_ALIAS" 'mkdir -p ~/deploy-staging ~/backups'
    scp -q -o BatchMode=yes "$ARCHIVE" "$HOST_ALIAS":deploy-staging/
    rm -f "$ARCHIVE"
    ssh -o BatchMode=yes "$HOST_ALIAS" "bash -s -- remote $SHORT" < "$0"
    exit 0
fi

C="${2:?missing commit}"
LIVE=/home/irc/ares-sitiochat
TS=$(date +%Y%m%d-%H%M%S)
OLD=$LIVE.old-$TS
RELEASE=/home/irc/ares-release-$C

rm -rf "$RELEASE"
mkdir "$RELEASE"
tar -xzf ~/deploy-staging/"$C".tar.gz -C "$RELEASE"
cp -a "$LIVE"/.env.local "$LIVE"/vendor "$RELEASE"/
mkdir -p "$RELEASE"/var
cd "$RELEASE"
chmod +x bin/console bin/phpunit
if ! cmp -s composer.lock "$LIVE"/composer.lock; then
    composer install --no-interaction --no-scripts -q
fi
composer dump-autoload -o -q
php bin/console lint:container -q && echo "container ok"

cd /home/irc
PID=$(pgrep -f "^php bin/console irc:connect" | head -1 || true)
if [[ -n "$PID" ]]; then
    kill -TERM "$PID"
fi
for i in $(seq 1 30); do
    if ! pgrep -f "bin/console (irc:connect|messenger:consume)" >/dev/null; then
        echo "stopped after ${i}s"
        break
    fi
    sleep 1
done
if pgrep -f "bin/console (irc:connect|messenger:consume)" >/dev/null; then
    echo "still running, forcing"
    pkill -KILL -f "bin/console (irc:connect|messenger:consume)"
    sleep 1
fi

tar -czf ~/backups/ares-data-"$TS".tar.gz -C "$LIVE" var/data .env.local
mv "$LIVE" "$OLD"
mv "$RELEASE" "$LIVE"
cp -a "$OLD"/var/data "$OLD"/var/log "$OLD"/var/ares.log "$LIVE"/var/

cd "$LIVE"
php bin/console cache:clear --env=prod -q
php bin/console doctrine:migrations:status --no-interaction 2>&1 | grep -E "\| New" | head -1
# The backup above already holds var/data, so pending migrations are applied before the start.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 | grep -E "Migrating|migrated|No migrations|ERROR" || true
START=$(date -u -d "-1 min" +%FT%H:%M)
nohup setsid php bin/console irc:connect -vv >> var/ares.log 2>&1 < /dev/null &
sleep 25

echo "previous release: $OLD"
echo "backup: ~/backups/ares-data-$TS.tar.gz"
pgrep -fa "bin/console (irc:connect|messenger:consume)" | cut -c1-60
tail -15 var/ares.log | grep -E "\[OK\]" | cut -c1-80 || true
echo "bots introduced: $(awk -v s="[$START" '$0 >= s' var/log/irc-"$(date -u +%F)".log | grep -c 'introduced to network' || true)"
echo "errors since restart: $(awk -v s="[$START" '$0 >= s' var/log/*-"$(date -u +%F)".log | grep -ciE '\.(ERROR|CRITICAL|ALERT|EMERGENCY)' || true)"

# Only the latest rollback point is kept; each previous release alone takes about 300 MB.
ls -1d "$LIVE".old-* | head -n -1 | xargs -r rm -rf
ls -1 ~/backups/ares-data-*.tar.gz | head -n -1 | xargs -r rm -f
find ~/deploy-staging -name '*.tar.gz' ! -name "$C.tar.gz" -delete
