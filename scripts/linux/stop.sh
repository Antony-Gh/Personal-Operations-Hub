#!/usr/bin/env bash
# ==============================================================================
# Personal Operations Hub - Linux Service Stopper
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$ROOT_DIR"

RUN_DIR="$ROOT_DIR/.run"
STOPPED=0

stop_pid() {
    local pid="$1"
    local label="$2"

    if [ -z "$pid" ] || [ "$pid" -le 0 ] 2>/dev/null; then
        return
    fi

    if kill -0 "$pid" 2>/dev/null; then
        echo "Stopping $label (PID: $pid)..."
        kill -15 "$pid" 2>/dev/null || true

        # Wait up to 5 seconds for graceful termination
        for i in {1..10}; do
            if ! kill -0 "$pid" 2>/dev/null; then
                break
            fi
            sleep 0.5
        done

        if kill -0 "$pid" 2>/dev/null; then
            echo "Process $pid did not exit cleanly; sending SIGKILL..."
            kill -9 "$pid" 2>/dev/null || true
        fi
        STOPPED=$((STOPPED + 1))
    fi
}

if [ -f "$RUN_DIR/background-pids.json" ]; then
    SERVER_PID=$(grep -o '"server": [0-9]*' "$RUN_DIR/background-pids.json" | awk '{print $2}')
    SCHED_PID=$(grep -o '"scheduler": [0-9]*' "$RUN_DIR/background-pids.json" | awk '{print $2}')
    QUEUE_PID=$(grep -o '"queue": [0-9]*' "$RUN_DIR/background-pids.json" | awk '{print $2}')

    stop_pid "$SERVER_PID" "Laravel HTTP Server"
    stop_pid "$SCHED_PID" "Laravel Scheduler"
    stop_pid "$QUEUE_PID" "Database Queue Worker"

    rm -f "$RUN_DIR/background-pids.json"
fi

for pidfile in laravel.pid scheduler.pid queue.pid vite.pid; do
    if [ -f "$RUN_DIR/$pidfile" ]; then
        PID=$(cat "$RUN_DIR/$pidfile" 2>/dev/null || echo "")
        stop_pid "$PID" "$pidfile"
        rm -f "$RUN_DIR/$pidfile"
    fi
done

# Remove Vite hot-reload flag
rm -f "$ROOT_DIR/public/hot"

echo ""
if [ "$STOPPED" -gt 0 ]; then
    echo "[OK] Stopped $STOPPED process(es). Working directory clean."
else
    echo "[OK] No active tracked processes were running."
fi
