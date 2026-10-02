#!/usr/bin/env bash
#
# Deploys Peluquería Jenver's site on a server with nginx and PHP-FPM
# installed directly (modeled on cobaprojects' deploy.sh, itself modeled
# on obranur's). Run it as the non-root "deploy" user that owns this
# checkout, already on the branch to deploy (it only ever fast-forwards,
# it never switches branches):
#   ./deploy.sh
#
# Override the default web user with an environment variable if the server
# differs:
#   WEB_USER=www-data ./deploy.sh
#
# Requirements: git, composer, Node.js with npm, PHP 8.3+, sudo rights
# (password-protected) for chown/chmod on storage/bootstrap/cache and for
# running `php artisan deploy:check` as $WEB_USER, and a working SSH
# connection to GitHub for the "deploy" user (see AGENTS.md "Production
# deploys" for the deploy-key prerequisite). sudo is asked for twice, both
# times while the site is still up (once up front, once right before
# maintenance mode - the last point where prompting for a password is
# safe); every sudo call from there on runs with -n, so an expired
# credential fails fast into "stays down on purpose" instead of hanging
# the site in maintenance mode waiting for input that cannot arrive from
# an unattended run.
#
# This project has no contact form and no background work of its own, so
# unlike cobaprojects there is no equivalent of `contact:notify-pending`
# or a cron entry here - see AGENTS.md "Production deploys".

set -euo pipefail

WEB_USER="${WEB_USER:-www-data}"
DEPLOY_USER="$(id -un)"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SITE_URL="https://www.peluqueriajenver.com"

step() {
    printf '\n==> %s\n' "$1"
}

# storage/ and bootstrap/cache/ are shared between the deploy user
# (artisan, composer) and PHP-FPM (views cache, logs, uploads), so both need
# write access through the web server group. Called once up front, before
# maintenance mode, in case an earlier `artisan` call run as root left
# something it owns that would otherwise make this very deploy fail before
# reaching the same fix further down; and again after migrate/optimize,
# since they create new files under those directories too. Extra arguments
# (e.g. -n) are passed through to sudo.
fix_permissions() {
    sudo "$@" chown -R "$DEPLOY_USER":"$WEB_USER" storage bootstrap/cache
    sudo "$@" find storage bootstrap/cache -type d -exec chmod 2775 {} +
    sudo "$@" find storage bootstrap/cache -type f -exec chmod 664 {} +
}

if [[ "$EUID" -eq 0 ]]; then
    echo "Do not run the deploy as root; use the 'deploy' user that owns $APP_DIR." >&2
    exit 1
fi

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    echo "Missing $APP_DIR/.env. Copy .env.example, set the production values and run 'php artisan key:generate' first." >&2
    exit 1
fi

# Tracks how far the deploy got, so a failure (via the ERR trap below, or an
# explicit call right before a non-trapped exit) can give instructions that
# match what state the site is actually in.
deploy_stage="not_started"

on_error() {
    echo >&2
    case "$deploy_stage" in
        in_maintenance)
            # Staying down is the safer default: the maintenance page is a
            # known, controlled state, while bringing an only-partially
            # deployed site back up could show visitors a 500 or stale code.
            echo "Deploy failed while the site was in maintenance mode. It stays down on purpose." >&2
            echo "Fix the problem, then either re-run ./deploy.sh or, once you are sure the code" >&2
            echo "is good, run 'php artisan up' yourself." >&2
            ;;
        live)
            # The code is already live; this only means the post-deploy
            # health check failed. Automatically rolling back here would be
            # riskier than it looks (which commit is "good"?), so this asks
            # a human instead of guessing.
            echo "Deploy finished and the site left maintenance mode, but the health check below" >&2
            echo "failed. The new code is already live: check $SITE_URL by hand and storage/logs," >&2
            echo "and roll back by hand (e.g. 'git checkout <previous-commit> && ./deploy.sh') if" >&2
            echo "it is actually broken." >&2
            ;;
        *)
            echo "Deploy failed before touching the site; production is untouched." >&2
            ;;
    esac
}
trap on_error ERR

# Checked before anything that needs sudo or touches the site: a failed
# fetch or a diverged branch means `git pull --ff-only` further down would
# fail too, but finding that out here costs nothing, while finding it out
# after the site is already in maintenance mode would leave it down for no
# reason - this is exactly the 2026-10-02 cobaprojects incident this check
# exists to avoid (see AGENTS.md "Production deploys").
step "Checking the remote is reachable and this branch has not diverged"
if ! git fetch origin; then
    echo "Could not fetch from the remote. This usually means 'deploy' cannot reach GitHub" >&2
    echo "over SSH (check its deploy key and ~/.ssh/config alias), not a problem with the code." >&2
    on_error
    exit 1
fi

if ! git merge-base --is-ancestor HEAD '@{u}'; then
    echo "This branch has diverged from its upstream (@{u}); a fast-forward pull would fail." >&2
    echo "Inspect it by hand (e.g. 'git log --oneline HEAD..@{u}' and 'git log --oneline @{u}..HEAD')" >&2
    echo "before deploying - this is not an access problem, the histories themselves disagree." >&2
    on_error
    exit 1
fi

step "Checking sudo access"
sudo -v

step "Checking the working tree is clean"
if [[ -n "$(git status --porcelain)" ]]; then
    echo "The working tree has uncommitted changes. Commit, stash or discard them before deploying." >&2
    exit 1
fi

step "Fixing storage and bootstrap/cache permissions"
fix_permissions

step "Enabling maintenance mode"
# Refreshed here, the last point where it is safe to prompt for a
# password: every step below this line runs while the site is down.
sudo -v
php artisan down --retry=15
deploy_stage="in_maintenance"

step "Pulling the latest code"
git pull --ff-only

step "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

step "Installing and building frontend assets"
npm ci
npm run build

step "Running database migrations"
php artisan migrate --force

step "Caching configuration, routes and views"
php artisan optimize

step "Fixing storage and bootstrap/cache permissions again"
fix_permissions -n

step "Checking the deploy as $WEB_USER"
sudo -n -u "$WEB_USER" php artisan deploy:check

step "Leaving maintenance mode"
php artisan up
deploy_stage="live"

step "Checking the live site"
for path in / /contacto /avisos-legales /sitemap.xml; do
    if ! status="$(curl -sS --max-time 20 -o /dev/null -w '%{http_code}' "$SITE_URL$path")"; then
        echo "Could not reach $SITE_URL$path (curl failed)." >&2
        on_error
        exit 1
    fi

    if [[ "$status" != "200" ]]; then
        echo "$SITE_URL$path returned $status instead of 200." >&2
        on_error
        exit 1
    fi

    echo "$SITE_URL$path -> 200"
done

printf '\nDeployed %s.\n' "$(git rev-parse --short HEAD)"
