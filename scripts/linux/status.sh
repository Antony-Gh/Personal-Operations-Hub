#!/usr/bin/env bash
# ==============================================================================
# Personal Operations Hub - Linux Service Status
# ==============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$ROOT_DIR"

RUN_DIR="$ROOT_DIR/.run"

echo "=========================================================="
echo "  Personal Operations Hub - Linux Service Status"
echo "=========================================================="

check_service() {
    local label="$1"
    local pidfile="$2"

    if [ -f "$RUN_DIR/$pidfile" ]; then
        PID=$(cat "$RUN_DIR/$pidfile" 2>/dev/null || echo "")
        if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
            echo "  [RUNNING] $label - PID $PID"
            return
        fi
    fi
    echo "  [STOPPED] $label"
}

check_service "Laravel HTTP Server (port 8000)" "laravel.pid"
check_service "Laravel Scheduler (schedule:work)" "scheduler.pid"
check_service "Database Queue Worker (queue:work)" "queue.pid"
check_service "Vite Dev Compiler (port 5173)" "vite.pid"

echo ""
if curl -s -f -m 2 "http://127.0.0.1:8000/up" >/dev/null 2>&1; then
    echo "Health Endpoint (/up): HTTP 200 OK"
else
    echo "Health Endpoint (/up): Offline / Unreachable"
fi
