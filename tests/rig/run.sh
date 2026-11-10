#!/bin/bash
# The bot on a test rig: a copy of this checkout, the real code, a real
# database (MariaDB/MySQL), and a stand-in for Telegram (fake_tg.php) that
# logs every call. Installs the schema with table.php, then taps through the
# customer's and the admin's screens and runs the crons; fails on any PHP
# fatal error, an update that is not answered, or a web panel page that does
# not load.
#
#   DB_HOST DB_USER DB_PASS DB_NAME   an EMPTY database the script may fill
#   tests/rig/run.sh
set -u
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HERE="$ROOT/tests/rig"
export RIG_DIR=$(mktemp -d)
export RIG_LOG="$RIG_DIR/tg_calls.jsonl"
export DB_HOST=${DB_HOST:-127.0.0.1} DB_USER=${DB_USER:-rig} DB_PASS=${DB_PASS:-rigpass} DB_NAME=${DB_NAME:-ciro_rig}
export BOT_PORT=${BOT_PORT:-18080} TG_PORT=${TG_PORT:-18081}
BOT="$RIG_DIR/bot"
fail=0
step() { printf '\n== %s\n' "$*"; }
bad() { echo "  ✗ $*"; fail=1; }

step "copy of the code in $BOT"
mkdir -p "$BOT"
tar -C "$ROOT" --exclude=.git --exclude=./config.php --exclude=./storage --exclude=error_log -cf - . | tar -C "$BOT" -xf -
sed -i "s#\"https://api.telegram.org/bot\"#\"http://127.0.0.1:${TG_PORT}/bot\"#" "$BOT/botapi.php"
sed -i 's#if (!checktelegramip())#if (false)#' "$BOT/index.php"
grep -q "127.0.0.1:${TG_PORT}" "$BOT/botapi.php" || { echo "botapi.php: the Telegram address was not found"; exit 1; }
[ -d "$BOT/vendor" ] || { echo "vendor/ is missing: run composer install first"; exit 1; }
cat > "$BOT/config.php" <<CFG
<?php
\$request_exec_timeout = null;
\$dbhost = '$DB_HOST'; \$dbname = '$DB_NAME'; \$usernamedb = '$DB_USER'; \$passworddb = '$DB_PASS';
\$connect = mysqli_connect(\$dbhost, \$usernamedb, \$passworddb, \$dbname);
mysqli_set_charset(\$connect, "utf8mb4");
\$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
\$pdo = new PDO("mysql:host=\$dbhost;dbname=\$dbname;charset=utf8mb4", \$usernamedb, \$passworddb, \$options);
\$APIKEY = '123:RIGTOKEN';
\$adminnumber = '1000';
\$domainhosts = '127.0.0.1:${BOT_PORT}';
\$usernamebot = 'rigbot';
CFG

step "servers"
(cd "$HERE" && php -S 127.0.0.1:$TG_PORT fake_tg.php >"$RIG_DIR/fake_tg.log" 2>&1 &)
(cd "$BOT" && env -u HTTPS_PROXY -u HTTP_PROXY -u https_proxy -u http_proxy php -S 127.0.0.1:$BOT_PORT >"$RIG_DIR/bot_server.log" 2>&1 &)
for i in $(seq 1 20); do curl -s --noproxy '*' -o /dev/null "http://127.0.0.1:$TG_PORT/bot1/getMe" && break; sleep 0.5; done
trap 'pkill -f "php -S 127.0.0.1:$TG_PORT" ; pkill -f "php -S 127.0.0.1:$BOT_PORT"' EXIT

step "schema (table.php, twice: an update must work on what the install made)"
(cd "$BOT" && php table.php >"$RIG_DIR/table1.log" 2>&1) || bad "table.php failed: $(tail -3 "$RIG_DIR/table1.log")"
(cd "$BOT" && php table.php >"$RIG_DIR/table2.log" 2>&1) || bad "table.php (again) failed: $(tail -3 "$RIG_DIR/table2.log")"

tap() { echo "• tap $*"; php "$HERE/tap.php" "$@" || fail=1; }
step "customer and admin screens"
tap 1000 text /start
tap 2000 text /start
tap 2000 cb account 90
tap 2000 text /support
tap 2000 cb support 90
tap 2000 cb departman_1 90
tap 2000 photo ""
tap 1000 cb "fls_lang:fa" 50
tap 1000 cb "xf|tog|fa|tk" 50
tap 1000 cb "xf|tog|fa|wt" 50
tap 2000 cb "tk|l|0" 91
tap 2000 cb account 92
tap 2000 cb "wt|s" 92
tap 2000 cb "wt|x" 92
tap 1000 cb "arh|hub|fa" 60
tap 1000 cb "wbh|hub|fa" 61
tap 1000 cb "wth|hub|fa" 62
tap 1000 cb "bt_group|fa|misc" 63
tap 1000 cb "bt_group|en|msmore" 63
tap 1000 cb "bt_group|fa|account" 63
tap 1000 cb "bt_edit|fa|users.support.tkMyBtn" 63
tap 1000 cb "bt_edit|en|users.autorenew.done" 63
tap 1000 cb "webpanel_2fa" 64
tap 1000 cb "webpanel_2fa" 64

step "crons"
for c in winback NoticationsService payment_expire; do
  echo "• cronbot/$c.php"
  (cd "$BOT/cronbot" && php "$c.php" >"$RIG_DIR/cron_$c.log" 2>&1) || bad "cronbot/$c.php exited with an error: $(tail -3 "$RIG_DIR/cron_$c.log")"
done

step "web panel"
for page in login.php; do
  code=$(curl -s --noproxy '*' -o /dev/null -w '%{http_code}' "http://127.0.0.1:$BOT_PORT/panel/$page")
  echo "• panel/$page → $code"; [ "$code" = 200 ] || bad "panel/$page answered $code"
done

step "PHP errors"
errs=$(cat "$RIG_DIR/bot_server.log" "$BOT/error_log" "$BOT/cronbot/error_log" "$BOT/panel/error_log" 2>/dev/null | grep -E "PHP (Fatal|Parse) error|Uncaught " | sort -u)
if [ -n "$errs" ]; then bad "fatal PHP errors:"; echo "$errs" | head -20; else echo "  none"; fi

[ $fail = 0 ] && echo -e "\n✅ rig: all passed" || echo -e "\n❌ rig: something failed (logs in $RIG_DIR)"
exit $fail
