#!/usr/bin/env bash
# CERNIX — One-command reliable launch (Git Bash / WSL / bash on Windows)
# Usage:  bash start.sh           — start server + ngrok
#         bash start.sh --reset   — also reset DB before starting

PHP="/c/xampp/php/php"
ARTISAN="/c/Users/hp/cernix-exam-verify/cernix/artisan"
LOG_DIR="/c/Users/hp/cernix-exam-verify/cernix/storage/logs"
PORT=8000
NGROK_DOMAIN="refusal-deem-launch.ngrok-free.dev"
NGROK_API="http://localhost:4040/api/tunnels"

# ANSI colours
GRN=$'\033[0;32m'; YLW=$'\033[1;33m'; CYN=$'\033[0;36m'
RED=$'\033[0;31m'; DIM=$'\033[2m'; RST=$'\033[0m'

echo ""
echo "  ${CYN}============================================================${RST}"
echo "  ${CYN}  CERNIX  |  Adekunle Ajasin University  |  QR Verify${RST}"
echo "  ${CYN}============================================================${RST}"
echo ""

# ── Optional DB reset ──────────────────────────────────────────────────────────
if [[ "${1:-}" == "--reset" ]]; then
    echo "  ${YLW}[0/5]${RST} Resetting database..."
    "$PHP" "$ARTISAN" cernix:reset --force && \
        echo "  ${GRN}[0/5]${RST} Database reset complete." || \
        echo "  ${RED}[0/5]${RST} DB reset failed — continuing anyway."
    echo ""
fi

# ── Step 1: Release port ────────────────────────────────────────────────────────
echo "  ${YLW}[1/5]${RST} Releasing port $PORT..."
powershell.exe -NoProfile -NonInteractive -Command "
    \$rows = netstat -ano | Select-String ':${PORT}\s'
    foreach (\$row in \$rows) {
        \$pid = (\$row.ToString().Trim().Split()[-1])
        if (\$pid -ne '0') {
            try { Stop-Process -Id \$pid -Force -ErrorAction SilentlyContinue } catch {}
        }
    }
" 2>/dev/null || true
sleep 0.5

# ── Step 2: Start Laravel server ───────────────────────────────────────────────
echo "  ${YLW}[2/5]${RST} Starting Laravel server on port $PORT..."
mkdir -p "$LOG_DIR"
"$PHP" "$ARTISAN" serve --host=0.0.0.0 --port="$PORT" \
    > "$LOG_DIR/server.log" 2>&1 &
SERVER_PID=$!

# ── Step 3: Wait for server ready ─────────────────────────────────────────────
echo "  ${YLW}[3/5]${RST} Waiting for server..."
READY=false
for i in $(seq 1 60); do
    sleep 0.5
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
        --connect-timeout 1 --max-time 2 \
        "http://localhost:$PORT/up" 2>/dev/null || echo "000")
    if [[ "$HTTP_STATUS" == "200" ]]; then
        READY=true
        break
    fi
done

if [[ "$READY" != "true" ]]; then
    echo ""
    echo "  ${RED}ERROR: Server did not start within 30 s. Check: $LOG_DIR/server.log${RST}"
    echo ""
    kill "$SERVER_PID" 2>/dev/null || true
    exit 1
fi
echo "  ${GRN}[3/5]${RST} Server ready at http://localhost:$PORT"

# ── Step 4: Kill any stale ngrok on 4040 ──────────────────────────────────────
echo "  ${YLW}[4/5]${RST} Starting ngrok tunnel ($NGROK_DOMAIN)..."
powershell.exe -NoProfile -NonInteractive -Command "
    \$rows = netstat -ano | Select-String ':4040\s'
    foreach (\$row in \$rows) {
        \$pid = (\$row.ToString().Trim().Split()[-1])
        if (\$pid -ne '0') {
            try { Stop-Process -Id \$pid -Force -ErrorAction SilentlyContinue } catch {}
        }
    }
" 2>/dev/null || true
sleep 0.3

ngrok http --domain="$NGROK_DOMAIN" "$PORT" \
    > "$LOG_DIR/ngrok.log" 2>&1 &
NGROK_PID=$!

# ── Step 5: Verify ngrok tunnel is live ───────────────────────────────────────
echo "  ${YLW}[5/5]${RST} Verifying ngrok tunnel..."
NGROK_OK=false
for i in $(seq 1 20); do
    sleep 0.5
    TUNNEL_URL=$(curl -s "$NGROK_API" 2>/dev/null \
        | grep -o '"public_url":"[^"]*"' \
        | head -1 \
        | sed 's/"public_url":"//;s/"//')
    if [[ -n "$TUNNEL_URL" ]]; then
        NGROK_OK=true
        break
    fi
done

echo ""
echo "  ${GRN}============================================================${RST}"
echo "   LOCAL :  http://localhost:$PORT"
if [[ "$NGROK_OK" == "true" ]]; then
    echo "   PUBLIC:  https://$NGROK_DOMAIN"
    echo "   TUNNEL:  $TUNNEL_URL"
else
    echo "  ${RED}   PUBLIC:  ngrok tunnel not confirmed — check $LOG_DIR/ngrok.log${RST}"
    echo "  ${YLW}   Attempting fallback URL: https://$NGROK_DOMAIN${RST}"
fi
echo "  ${GRN}============================================================${RST}"
echo ""
echo "  Press ${YLW}Ctrl+C${RST} to stop all services."
echo "  ${DIM}Server log : $LOG_DIR/server.log${RST}"
echo "  ${DIM}ngrok  log : $LOG_DIR/ngrok.log${RST}"
echo "  ${DIM}DB reset   : bash start.sh --reset${RST}"
echo ""

# ── Cleanup on exit ────────────────────────────────────────────────────────────
_cleanup() {
    echo ""
    echo "  Stopping services..."
    kill "$SERVER_PID" 2>/dev/null || true
    kill "$NGROK_PID"  2>/dev/null || true
    powershell.exe -NoProfile -NonInteractive -Command "
        \$rows = netstat -ano | Select-String ':$PORT\s'
        foreach (\$row in \$rows) {
            \$pid = (\$row.ToString().Trim().Split()[-1])
            if (\$pid -ne '0') {
                try { Stop-Process -Id \$pid -Force -ErrorAction SilentlyContinue } catch {}
            }
        }
    " 2>/dev/null || true
    echo "  Stopped. Goodbye."
}
trap _cleanup EXIT INT TERM

wait "$SERVER_PID" 2>/dev/null || true
