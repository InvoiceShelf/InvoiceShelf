#!/usr/bin/env bash
# =============================================================================
# init-dev.sh — Reset and initialise the InvoiceShelf dev environment.
#
# Run this script INSIDE the PHP container:
#
#   docker exec -it invoiceshelf-dev-php bash /var/www/html/docker/development/init-dev.sh [options]
#
# Safe to re-run at any time, including after switching git tags/branches.
# Always does a full clean slate: fresh DB, cleared caches, reinstalled deps.
#
# Usage:
#   init-dev.sh [--db=sqlite|mysql|pgsql] [--seed] [--no-seed]
#
#   --db=sqlite   Use SQLite (default — no external DB container needed)
#   --db=mysql    Use MariaDB  (requires docker-compose.mysql.yml)
#   --db=pgsql    Use PostgreSQL (requires docker-compose.pgsql.yml)
#   --seed        Seed the database with fake dev data (DevDataSeeder)
#   --no-seed     Skip seeding (default)
#
# Examples:
#   init-dev.sh                          # sqlite, no seed
#   init-dev.sh --seed                   # sqlite + fake data
#   init-dev.sh --db=mysql --seed        # MariaDB + fake data
#   init-dev.sh --db=pgsql --no-seed     # PostgreSQL, no seed
# =============================================================================

set -euo pipefail

ROOT="/var/www/html"
cd "$ROOT"

# ── Argument parsing ──────────────────────────────────────────────────────────

DB=sqlite
SEED=prompt

for arg in "$@"; do
    case "$arg" in
        --db=sqlite) DB=sqlite ;;
        --db=mysql)  DB=mysql  ;;
        --db=pgsql)  DB=pgsql  ;;
        --seed)      SEED=true  ;;
        --no-seed)   SEED=false ;;
        *)
            echo "Unknown argument: $arg"
            echo "Usage: $0 [--db=sqlite|mysql|pgsql] [--seed] [--no-seed]"
            exit 1
            ;;
    esac
done

step() { echo; echo "▶ $*"; }

# ── 1. PHP dependencies ───────────────────────────────────────────────────────
# Note: JS dependencies (yarn) are NOT available in this PHP-FPM container.
# Run "yarn install && yarn build" on your host, or use the static_builder
# Docker stage which handles the frontend build automatically.

step "Installing PHP dependencies"
composer install --no-interaction --prefer-dist

# ── 2. Environment file ───────────────────────────────────────────────────────

ENV_FILE="$ROOT/.env"

# Helper: replace or append a key=value in .env
set_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        echo "${key}=${value}" >> "$ENV_FILE"
    fi
}

step "Setting up .env"
cp "$ROOT/.env.example" "$ENV_FILE"
php artisan key:generate --force

set_env "APP_ENV"   "local"
set_env "APP_DEBUG" "true"
set_env "APP_URL"   "http://invoiceshelf.test"

# Database — values differ per backend
case "$DB" in
    sqlite)
        SQLITE_PATH="$ROOT/storage/app/database.sqlite"
        set_env "DB_CONNECTION" "sqlite"
        set_env "DB_DATABASE"   "$SQLITE_PATH"
        ;;
    mysql)
        set_env "DB_CONNECTION" "mysql"
        set_env "DB_HOST"       "db"
        set_env "DB_PORT"       "3306"
        set_env "DB_DATABASE"   "invoiceshelf"
        set_env "DB_USERNAME"   "invoiceshelf"
        set_env "DB_PASSWORD"   "invoiceshelf"
        ;;
    pgsql)
        set_env "DB_CONNECTION" "pgsql"
        set_env "DB_HOST"       "db"
        set_env "DB_PORT"       "5432"
        set_env "DB_DATABASE"   "invoiceshelf"
        set_env "DB_USERNAME"   "invoiceshelf"
        set_env "DB_PASSWORD"   "invoiceshelf"
        ;;
esac

set_env "CACHE_STORE"              "file"
set_env "SESSION_DRIVER"           "file"
set_env "SESSION_LIFETIME"         "120"
set_env "SESSION_DOMAIN"           "invoiceshelf.test"
set_env "SANCTUM_STATEFUL_DOMAINS" "invoiceshelf.test"

# Mailpit — fake SMTP available inside the dev Docker network
set_env "MAIL_MAILER"     "smtp"
set_env "MAIL_HOST"       "mail"
set_env "MAIL_PORT"       "1025"
set_env "MAIL_ENCRYPTION" "none"
set_env "MAIL_USERNAME"   ""
set_env "MAIL_PASSWORD"   ""

# ── 3. Clear ALL Laravel caches ───────────────────────────────────────────────

step "Clearing all Laravel caches"
php artisan optimize:clear
php artisan cache:clear
php artisan clear-compiled

step "Clearing runtime session and view files"
find "$ROOT/storage/framework/sessions"   -type f ! -name '.gitignore' -delete 2>/dev/null || true
find "$ROOT/storage/framework/views"      -name '*.php'                 -delete 2>/dev/null || true
find "$ROOT/storage/framework/cache/data" -type f ! -name '.gitignore' -delete 2>/dev/null || true

# ── 4. Database — full reset ──────────────────────────────────────────────────

if [ "$DB" = "sqlite" ]; then
    step "Resetting SQLite database"
    mkdir -p "$(dirname "$SQLITE_PATH")"
    cp "$ROOT/database/stubs/sqlite.empty.db" "$SQLITE_PATH"
    chown www-data:www-data "$SQLITE_PATH" 2>/dev/null || true
fi

step "Running all migrations from scratch (migrate:fresh)"
php artisan migrate:fresh --force

step "Seeding reference data (currencies, countries)"
php artisan db:seed --class=CurrenciesTableSeeder --force
php artisan db:seed --class=CountriesTableSeeder --force

# ── 5. Optional dev data seeding ─────────────────────────────────────────────

if [ "$SEED" = prompt ]; then
    echo
    read -r -p "Seed the database with fake dev data? [Y/n] " reply
    case "${reply:-Y}" in
        [Yy]*|"") SEED=true  ;;
        *)         SEED=false ;;
    esac
fi

if [ "$SEED" = true ]; then
    step "Seeding fake dev data"
    php artisan db:seed --class=DevDataSeeder --force
    echo
    echo "  Credentials seeded:"
    echo "    Admin : admin@invoiceshelf.com / password"
    echo "    Staff : jane@invoiceshelf.com  / password"
    echo "    Staff : bob@invoiceshelf.com   / password"
else
    echo
    echo "  Seeding skipped. Run again with --seed, or:"
    echo "    php artisan db:seed --class=DevDataSeeder"
fi

# ── 6. Storage ────────────────────────────────────────────────────────────────

step "Recreating public storage symlink"
rm -f "$ROOT/public/storage"
php artisan storage:link

# ── 7. Permissions ────────────────────────────────────────────────────────────

step "Fixing permissions"
chmod -R 775 storage/framework storage/logs bootstrap/cache

# ── Done ──────────────────────────────────────────────────────────────────────

echo
echo "✓ Dev environment ready — http://invoiceshelf.test"
echo
