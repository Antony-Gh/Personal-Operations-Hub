#!/usr/bin/env bash
# ==============================================================================
# Personal Operations Hub - Linux & POSIX Central Controller (controller.sh)
# ==============================================================================
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

show_header() {
    local subtitle="$1"
    echo "=============================================================================="
    echo "                PERSONAL OPERATIONS HUB - UNIX CONTROLLER                    "
    echo "                 Cross-Platform Core Operations Engine                       "
    echo "=============================================================================="
    if [ -n "$subtitle" ]; then
        echo "  >> $subtitle"
        echo "------------------------------------------------------------------------------"
    fi
    echo ""
}

show_menu() {
    show_header "Operations Menu"
    echo "  === APPLICATION LIFECYCLE ==="
    echo "  [ 1] Start Background Services (HTTP, Scheduler, Queue)"
    echo "  [ 2] Check Service Health & Process Status"
    echo "  [ 3] Stop All Running Services Safely"
    echo "  [ 4] Restart All Services (Clean Cycle)"
    echo ""
    echo "  === DEVELOPMENT & ASSETS ==="
    echo "  [ 5] Start Interactive Dev Server (artisan serve)"
    echo "  [ 6] Start Vite Dev Compiler (npm run dev)"
    echo "  [ 7] Build Production Frontend Assets (npm run build)"
    echo ""
    echo "  === DATABASE & DISASTER RECOVERY ==="
    echo "  [ 8] Run Database Migrations (migrate --force)"
    echo "  [ 9] Check Migration Status (migrate:status)"
    echo "  [10] Create Verified Database Backup (ops:backup-database)"
    echo "  [11] Restore Database from Backup (ops:restore-database)"
    echo ""
    echo "  === DIAGNOSTICS & MAINTENANCE ==="
    echo "  [12] Run Automated Test Suite (Pest)"
    echo "  [13] View Recent Application & Background Logs"
    echo "  [14] Clear Application Caches (cache:clear, config:clear)"
    echo "  [15] Show Operational Summary (ops:status)"
    echo ""
    echo "------------------------------------------------------------------------------"
    echo "  [H] Help  |  [Q] Quit"
    echo ""
}

execute_op() {
    local op="$1"
    case "$op" in
        1|start)
            show_header "Starting Background Services"
            bash "$ROOT_DIR/scripts/linux/start.sh"
            ;;
        2|status)
            show_header "Checking Service Status"
            bash "$ROOT_DIR/scripts/linux/status.sh"
            ;;
        3|stop)
            show_header "Stopping All Services"
            bash "$ROOT_DIR/scripts/linux/stop.sh"
            ;;
        4|restart)
            show_header "Restarting Services"
            bash "$ROOT_DIR/scripts/linux/stop.sh"
            sleep 1
            bash "$ROOT_DIR/scripts/linux/start.sh"
            ;;
        5|serve)
            show_header "Starting Interactive Laravel Server"
            php artisan serve --host=127.0.0.1 --port=8000
            ;;
        6|dev)
            show_header "Starting Vite Dev Server"
            npm run dev
            ;;
        7|build)
            show_header "Building Production Frontend Assets"
            npm run build
            ;;
        8|migrate)
            show_header "Running Database Migrations"
            php artisan migrate --force --no-interaction
            ;;
        9|migrate:status)
            show_header "Migration Status"
            php artisan migrate:status
            ;;
        10|backup)
            show_header "Creating Verified Database Backup"
            php artisan ops:backup-database --trigger=manual --no-interaction
            ;;
        11|restore)
            show_header "Restoring Database from Backup"
            php artisan ops:restore-database
            ;;
        12|test)
            show_header "Running Automated Test Suite"
            php artisan test --compact
            ;;
        13|logs)
            show_header "Recent Logs"
            if [ -f "$ROOT_DIR/storage/logs/laravel.log" ]; then
                echo "--- storage/logs/laravel.log (last 25 lines) ---"
                tail -n 25 "$ROOT_DIR/storage/logs/laravel.log"
            fi
            if [ -d "$ROOT_DIR/storage/logs/background" ]; then
                for f in "$ROOT_DIR/storage/logs/background"/*.log; do
                    if [ -f "$f" ]; then
                        echo ""
                        echo "--- $(basename "$f") (last 15 lines) ---"
                        tail -n 15 "$f"
                    fi
                done
            fi
            ;;
        14|clean)
            show_header "Clearing Application Caches"
            php artisan cache:clear
            php artisan config:clear
            php artisan route:clear
            php artisan view:clear
            echo "Caches cleared successfully."
            ;;
        15|ops:status)
            show_header "Operational Summary"
            php artisan ops:status
            ;;
        setup)
            show_header "Project Initial Setup"
            composer install --optimize-autoloader
            if [ ! -f .env ]; then
                cp .env.example .env
                php artisan key:generate
            fi
            touch database/database.sqlite
            php artisan migrate --force
            npm ci
            npm run build
            echo "Initial setup completed successfully."
            ;;
        *)
            echo "[ERROR] Unknown operation: '$op'" >&2
            echo "Run './controller.sh --help' for available commands." >&2
            exit 1
            ;;
    esac
}

# Direct CLI flags and argument handling
if [ $# -gt 0 ]; then
    case "$1" in
        -h|--help|help)
            show_header "Help & Documentation"
            echo "Usage: ./controller.sh [command|option_number]"
            echo ""
            echo "Common commands:"
            echo "  start       Start background HTTP server, scheduler, and queue"
            echo "  stop        Stop all tracked services"
            echo "  restart     Restart all tracked services"
            echo "  status      Check active services and health"
            echo "  logs        Tail recent application and worker logs"
            echo "  test        Run Pest automated tests"
            echo "  build       Compile production frontend assets"
            echo "  backup      Create verified database snapshot"
            echo "  restore     Interactive or automated database restore"
            echo "  clean       Clear all Laravel application caches"
            echo "  setup       One-command project bootstrapping"
            exit 0
            ;;
        --option)
            shift
            execute_op "$1"
            exit 0
            ;;
        *)
            execute_op "$1"
            exit 0
            ;;
    esac
fi

# Interactive Loop
while true; do
    show_menu
    read -r -p "Enter selection [1-15, H, Q]: " choice
    case "$choice" in
        q|Q|exit|quit)
            echo "Exiting controller. Goodbye!"
            exit 0
            ;;
        h|H|\?)
            ./controller.sh --help
            read -r -p "Press Enter to return to menu..." _
            ;;
        [1-9]|1[0-5])
            execute_op "$choice"
            echo ""
            read -r -p "Press Enter to return to menu..." _
            ;;
        "")
            continue
            ;;
        *)
            echo "Unrecognized selection: '$choice'"
            sleep 1
            ;;
    esac
done
