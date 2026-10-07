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
# The booking system's email retry (`appointments:notify-pending`) runs
# from the deploy user's crontab, not from this script - see AGENTS.md
# "Production deploys" > "Cron".

# -E (errtrace) makes the ERR trap below fire for a failure inside a
# function (e.g. fix_permissions) too, not just at the top level. Without
# it, set -e still stops the script, but on_error never runs, so a
# mid-maintenance failure inside a function leaves the site down with no
# instructions printed - caught by the obranur demo's review.
set -Eeuo pipefail

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

# Fails (returns 1) when the .env given as $1 lacks the booking mail
# settings that `php artisan deploy:check` requires. Run in the preflight,
# while the site is still live: deploy:check itself only runs inside the
# maintenance window, with migrations already applied, so catching a
# missing setting there means an avoidable outage. Mirrors the shape checks
# of DeployCheckCommand::checkMail (tests/Feature/DeployCheckCommandTest.php
# runs this function against sample .env files, with plain `sh`, so it is
# kept POSIX: no [[ ]]).
check_env_mail() {
    env_file="$1"
    failed=0

    mailer="$(sed -n 's/^MAIL_MAILER=//p' "$env_file" | tail -n 1 | tr -d '"'"'")"
    host="$(sed -n 's/^MAIL_HOST=//p' "$env_file" | tail -n 1 | tr -d '"'"'")"
    from="$(sed -n 's/^MAIL_FROM_ADDRESS=//p' "$env_file" | tail -n 1 | tr -d '"'"'")"
    salon="$(sed -n 's/^BOOKING_NOTIFICATION_EMAIL=//p' "$env_file" | tail -n 1 | tr -d '"'"'")"

    case "$mailer" in
        ''|log|array)
            echo "MAIL_MAILER in $env_file does not send real email (got \"$mailer\")." >&2
            failed=1 ;;
    esac
    if [ "$mailer" = "smtp" ]; then
        case "$host" in
            ''|127.0.0.1|localhost)
                echo "MAIL_HOST in $env_file is not a real SMTP server (got \"$host\")." >&2
                failed=1 ;;
        esac
    fi
    case "$from" in
        hello@example.com)
            echo "MAIL_FROM_ADDRESS in $env_file is still the skeleton default." >&2
            failed=1 ;;
        *@*) ;;
        *)
            echo "MAIL_FROM_ADDRESS in $env_file is missing." >&2
            failed=1 ;;
    esac
    case "$salon" in
        *@*.*) ;;
        *)
            echo "BOOKING_NOTIFICATION_EMAIL in $env_file is missing or not an email address." >&2
            failed=1 ;;
    esac

    return "$failed"
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

if ! check_env_mail .env; then
    echo "Set the booking mail settings in $APP_DIR/.env (see AGENTS.md, \"Before deploying the booking system\") and re-run ./deploy.sh. The site has not been touched." >&2
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
            echo "failed. The new code is already live: check $SITE_URL by hand and storage/logs." >&2
            echo "'git checkout <previous-commit> && ./deploy.sh' will not fix it: the preflight" >&2
            echo "check below only ever fast-forwards, so it refuses to deploy a commit that is" >&2
            echo "not an ancestor of @{u}. If it is actually broken, revert the bad commit(s)" >&2
            echo "('git revert <commit>'), push that to the branch, then run ./deploy.sh again." >&2
            ;;
        *)
            echo "Deploy failed before touching the site; production is untouched." >&2
            ;;
    esac
}
trap on_error ERR

step "Checking sudo access"
sudo -v

step "Checking the working tree is clean"
if [[ -n "$(git status --porcelain)" ]]; then
    echo "The working tree has uncommitted changes. Commit, stash or discard them before deploying." >&2
    exit 1
fi

step "Fixing storage and bootstrap/cache permissions"
fix_permissions

# Catches three problems before the site goes into maintenance mode, where
# they would otherwise be discovered too late: this preflight reuses the
# one cobaprojects added after its own first real deploy went down and
# only then failed to pull because "deploy" had no SSH access to GitHub
# (see cobaprojects' AGENTS.md "Production deploys" and
# developer-brain/knowledge/vps-ovh.md - that incident happened there, not
# on this project's own first deploy, which has not run yet). The
# "Pulling the latest code" step below merges @{u} instead of pulling
# again, so it reuses this exact fetch rather than risking a second one
# racing ahead of the ancestry check just done.
step "Checking GitHub access and that the branch can fast-forward"
if ! git fetch; then
    echo "git fetch failed. Check deploy's SSH access to GitHub: the deploy key and the" >&2
    echo "'github-peluqueriajenver' alias in ~/.ssh/config (see AGENTS.md, 'Production deploys')." >&2
    exit 1
fi

if ! git rev-parse --abbrev-ref --symbolic-full-name "@{u}" >/dev/null 2>&1; then
    echo "The current branch has no upstream configured (@{u} does not resolve). Set one" >&2
    echo "with 'git branch --set-upstream-to=origin/<branch>' before deploying." >&2
    exit 1
fi

if ! git merge-base --is-ancestor HEAD "@{u}"; then
    echo "The local branch has diverged from its upstream (@{u}); a fast-forward merge would" >&2
    echo "fail. Resolve this by hand (rebase, reset or fix the branch) before deploying." >&2
    exit 1
fi

step "Enabling maintenance mode"
# Refreshed here, the last point where it is safe to prompt for a
# password: every step below this line runs while the site is down.
sudo -v
php artisan down --retry=15
deploy_stage="in_maintenance"

step "Pulling the latest code"
git merge --ff-only "@{u}"

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
for path in / /contacto /reservas /avisos-legales /sitemap.xml /up; do
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
