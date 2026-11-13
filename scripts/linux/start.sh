#!/usr/bin/env bash
# ==============================================================================
# Personal Operations Hub - Linux Service Launcher
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$ROOT_DIR"

RUN_DIR="$ROOT_DIR/.run"
LOG_DIR="$ROOT_DIR/storage/logs/background"
APP_URL="http://127.0.0.1:8000"

mkdir -p "$RUN_DIR" "$LOG_DIR" "$ROOT_DIR/database"

# 1. Check prerequisites
for tool in php composer; do
    if ! command -v "$tool" >/dev/null 2>&1; then
        echo "[ERROR] $tool is required but not installed or not in PATH." >&2
        exit 1
    fi
done

# 2. Verify environment & database
if [ ! -f "$ROOT_DIR/.env" ]; then
    echo "Creating .env from .env.example..."
    cp "$ROOT_DIR/.env.example" "$ROOT_DIR/.env"
    php artisan key:generate --no-interaction
fi

if [ ! -f "$ROOT_DIR/database/database.sqlite" ]; then
    echo "Initializing database/database.sqlite..."
    touch "$ROOT_DIR/database/database.sqlite"
fi

# 3. Safe database migrations
echo "Running safe migrations..."
php artisan migrate --force --no-interaction

# 4. Clean up stale PID files
for pidfile in laravel.pid scheduler.pid queue.pid vite.pid; do
    if [ -f "$RUN_DIR/$pidfile" ]; then
        PID=$(cat "$RUN_DIR/$pidfile" 2>/dev/null || echo "")
        if [ -n "$PID" ] && ! kill -0 "$PID" 2>/dev/null; then
            rm -f "$RUN_DIR/$pidfile"
        fi
    fi
done

# 5. Start Laravel HTTP Server
if [ -f "$RUN_DIR/laravel.pid" ] && kill -0 "$(cat "$RUN_DIR/laravel.pid")" 2>/dev/null; then
    echo "Laravel HTTP server is already running (PID: $(cat "$RUN_DIR/laravel.pid"))."
else
    echo "Starting Laravel HTTP server on port 8000..."
    nohup php artisan serve --host=127.0.0.1 --port=8000 --no-interaction > "$LOG_DIR/server.log" 2>&1 &
    SERVER_PID=$!
    echo "$SERVER_PID" > "$RUN_DIR/laravel.pid"
    echo "Laravel HTTP server started (PID: $SERVER_PID)."
fi

# 6. Start Scheduler Worker
if [ -f "$RUN_DIR/scheduler.pid" ] && kill -0 "$(cat "$RUN_DIR/scheduler.pid")" 2>/dev/null; then
    echo "Laravel Scheduler is already running (PID: $(cat "$RUN_DIR/scheduler.pid"))."
else
    echo "Starting Laravel Scheduler daemon..."
    nohup php artisan schedule:work --no-interaction > "$LOG_DIR/scheduler.log" 2>&1 &
    SCHED_PID=$!
    echo "$SCHED_PID" > "$RUN_DIR/scheduler.pid"
    echo "Laravel Scheduler started (PID: $SCHED_PID)."
fi

# 7. Start Database Queue Worker
if [ -f "$RUN_DIR/queue.pid" ] && kill -0 "$(cat "$RUN_DIR/queue.pid")" 2>/dev/null; then
    echo "Database Queue Worker is already running (PID: $(cat "$RUN_DIR/queue.pid"))."
else
    echo "Starting Database Queue Worker..."
    nohup php artisan queue:work --sleep=3 --tries=3 --timeout=60 --no-interaction > "$LOG_DIR/queue.log" 2>&1 &
    QUEUE_PID=$!
    echo "$QUEUE_PID" > "$RUN_DIR/queue.pid"
    echo "Database Queue Worker started (PID: $QUEUE_PID)."
fi

# 8. Record composite JSON for controller compatibility
cat <<EOF > "$RUN_DIR/background-pids.json"
{
  "server": $(cat "$RUN_DIR/laravel.pid" 2>/dev/null || echo 0),
  "scheduler": $(cat "$RUN_DIR/scheduler.pid" 2>/dev/null || echo 0),
  "queue": $(cat "$RUN_DIR/queue.pid" 2>/dev/null || echo 0)
}
EOF

# 9. Verify Readiness
echo "Verifying application readiness..."
READY=0
for i in {1..15}; do
    if curl -s -f -m 2 "$APP_URL" >/dev/null 2>&1 || curl -s -f -m 2 "$APP_URL/login" >/dev/null 2>&1; then
        READY=1
        break
    fi
    sleep 1
done

if [ "$READY" -eq 1 ]; then
    echo ""
    echo "=========================================================="
    echo "  Personal Operations Hub is LIVE at: $APP_URL"
    echo "=========================================================="
    echo "Background services (HTTP, Scheduler, Queue) active."
    echo "Stop anytime with: ./controller.sh stop"
else
    echo "[WARNING] Server took longer than expected to answer HTTP probes. Check $LOG_DIR/server.log"
fi
