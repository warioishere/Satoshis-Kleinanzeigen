#!/usr/bin/env bash
# Runs the Nostr trust tests against this install. Nothing reaches a public
# relay: PHP tests talk to the mock relay on 127.0.0.1, the browser test
# replaces WebSocket entirely.
#
#   tools/nostr-test/run.sh            # all three
#   tools/nostr-test/run.sh php        # mock relay + PHP tests only
#   tools/nostr-test/run.sh graph      # browser test only
#
# First time: (cd tools/nostr-test && npm install)   — @noble for signatures,
# puppeteer optional (SK_PUPPETEER may point at an existing install).
set -u
cd "$(dirname "$0")"
HERE=$(pwd)
WHAT=${1:-all}
PORT=${MOCK_PORT:-18080}
export MOCK_RELAY="ws://127.0.0.1:${PORT}"
export MOCK_EVENTS="$HERE/events.json"
export MOCK_LOG="$HERE/relay.jsonl"
STATUS=0

start_relay() {
  if ss -ltn 2>/dev/null | grep -q ":${PORT} "; then
    echo "port ${PORT} already in use; set MOCK_PORT" >&2; exit 1
  fi
  echo '[]' > "$MOCK_EVENTS"; : > "$MOCK_LOG"
  php mock-relay.php --port="$PORT" --events="$MOCK_EVENTS" --log="$MOCK_LOG" > "$HERE/relay.out" 2>&1 &
  RELAY_PID=$!
  sleep 1.5
}

stop_relay() {
  [ -n "${RELAY_PID:-}" ] && kill "$RELAY_PID" 2>/dev/null
  rm -f "$MOCK_EVENTS"
}

if [ "$WHAT" = all ] || [ "$WHAT" = php ]; then
  start_relay
  trap stop_relay EXIT
  for t in php/events.test.php php/followmirror.test.php php/reports.test.php; do
    echo "== $t"
    php "$t" > "$HERE/last.out" 2>&1
    RC=$?
    grep -v 'WP_CACHE_KEY_SALT' "$HERE/last.out"
    [ "$RC" -eq 0 ] || STATUS=1
  done
  rm -f "$HERE/last.out"
fi

if [ "$WHAT" = all ] || [ "$WHAT" = graph ]; then
  echo "== graph.test.mjs"
  node graph.test.mjs || STATUS=1
fi

exit $STATUS
