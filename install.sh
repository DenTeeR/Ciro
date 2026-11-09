#!/bin/bash
# Checking Root Access
if [[ $EUID -ne 0 ]]; then
    echo -e "\033[31m[ERROR]\033[0m Please run this script as \033[1mroot\033[0m."
    exit 1
fi

INSTALL_LOG="/tmp/ciro_install.log"

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1
export APT_LISTCHANGES_FRONTEND=none

# ── Progress / ETA state ─────────────────────────────────────
ETA_REMAINING=0   # estimated seconds left for the whole install
STEP_NO=0         # how many steps have started
STEP_TOTAL=0      # total steps planned for this run (0 = unknown)

# Seconds -> "9s" or "2m05s"
_fmt_secs() {
    local s=$1
    [ "$s" -lt 0 ] && s=0
    if [ "$s" -lt 60 ]; then printf '%ds' "$s"; else printf '%dm%02ds' $((s / 60)) $((s % 60)); fi
}

# Filled/empty bar of WIDTH chars at PCT percent
_bar() {
    local pct=$1 width=${2:-14} filled i out=""
    [ "$pct" -gt 100 ] && pct=100; [ "$pct" -lt 0 ] && pct=0
    filled=$(( pct * width / 100 ))
    for ((i = 0; i < width; i++)); do
        if [ "$i" -lt "$filled" ]; then out+="█"; else out+="░"; fi
    done
    printf '%s' "$out"
}

# Expected duration (seconds) for a step, matched by its label.
# Keeps the per-step bar and the overall ETA in sync.
_step_eta() {
    case "$1" in
        "Preparing package manager"*)        echo 5  ;;
        "Adding PHP repository"*|"Retrying PHP repository"*) echo 15 ;;
        "Updating & upgrading"*|"Re-running system update"*) echo 120 ;;
        "Installing base tools"*)            echo 25 ;;
        "Installing PHP "*)                  echo 30 ;;
        "Installing web stack"*)             echo 90 ;;
        "Repairing broken MySQL"*)           echo 90 ;;
        "Re-installing web stack"*)          echo 90 ;;
        "Installing phpMyAdmin"*)            echo 40 ;;
        "Installing extra modules"*)         echo 25 ;;
        "Enabling & starting services"*)     echo 8  ;;
        "Configuring firewall"*)             echo 15 ;;
        "Restarting Apache"*)                echo 5  ;;
        "Setting PHP as the active"*|"Setting PHP "*) echo 6  ;;
        "Downloading Ciro"*)                 echo 20 ;;
        "Extracting source files"*)          echo 5  ;;
        "Configuring MySQL root access"*)    echo 10 ;;
        "Opening firewall ports"*)           echo 4  ;;
        "Stopping Apache"*)                  echo 4  ;;
        "Installing Let's Encrypt"*|"Installing certbot"*) echo 25 ;;
        "Requesting SSL certificate"*)       echo 25 ;;
        "Installing Apache certbot plugin"*) echo 25 ;;
        "Configuring SSL on Apache"*)        echo 20 ;;
        "Enabling & starting Apache"*|"Starting Apache"*) echo 5 ;;
        "Configuring Apache virtual hosts"*) echo 6  ;;
        "Creating database & user"*)         echo 5  ;;
        "Setting Telegram webhook"*)         echo 5  ;;
        "Initializing database tables"*)     echo 15 ;;
        *)                                   echo 8  ;;
    esac
}

# Plan the run: count pending steps + total expected time (skips done phases).
plan_eta() {
    STEP_TOTAL=0; ETA_REMAINING=0; STEP_NO=0
    phase_done DEPS    || { STEP_TOTAL=$((STEP_TOTAL + 12)); ETA_REMAINING=$((ETA_REMAINING + 388)); }
    phase_done FILES   || { STEP_TOTAL=$((STEP_TOTAL + 3));  ETA_REMAINING=$((ETA_REMAINING + 40)); }
    phase_done DBROOT  || { STEP_TOTAL=$((STEP_TOTAL + 1));  ETA_REMAINING=$((ETA_REMAINING + 10)); }
    if ! phase_done SSL; then
        if [ -f "/etc/letsencrypt/live/$(state_get DOMAIN)/fullchain.pem" ]; then
            STEP_TOTAL=$((STEP_TOTAL + 1)); ETA_REMAINING=$((ETA_REMAINING + 5))
        else
            STEP_TOTAL=$((STEP_TOTAL + 7)); ETA_REMAINING=$((ETA_REMAINING + 108))
        fi
    fi
    phase_done VHOST   || { STEP_TOTAL=$((STEP_TOTAL + 1)); ETA_REMAINING=$((ETA_REMAINING + 6)); }
    phase_done DB      || { STEP_TOTAL=$((STEP_TOTAL + 1)); ETA_REMAINING=$((ETA_REMAINING + 5)); }
    phase_done WEBHOOK || { STEP_TOTAL=$((STEP_TOTAL + 3)); ETA_REMAINING=$((ETA_REMAINING + 25)); }
}

print_header() {
    echo ""
    echo -e "\033[1;34m╭────────────────────────────────────────────────╮\033[0m"
    printf  "\033[1;34m│\033[0m \033[1;36m%-46s\033[0m \033[1;34m│\033[0m\n" "$1"
    echo -e "\033[1;34m╰────────────────────────────────────────────────╯\033[0m"
}

run_step() {
    local msg="$1"
    local cmd="$2"
    local eta="${3:-$(_step_eta "$msg")}"
    [ "$eta" -lt 1 ] && eta=1
    STEP_NO=$((STEP_NO + 1))
    local counter="$STEP_NO"
    [ "$STEP_TOTAL" -gt 0 ] && counter="$STEP_NO/$STEP_TOTAL"
    : > "$INSTALL_LOG"
    local start; start=$(date +%s)
    bash -c "$cmd" >> "$INSTALL_LOG" 2>&1 &
    local pid=$!
    local frames=("⠋" "⠙" "⠹" "⠸" "⠼" "⠴" "⠦" "⠧" "⠇" "⠏")
    local n=${#frames[@]}
    local i=0
    tput civis 2>/dev/null
    while kill -0 "$pid" 2>/dev/null; do
        local el=$(( $(date +%s) - start ))
        local pct=$(( el * 100 / eta ))
        [ "$pct" -gt 95 ] && pct=95          # don't show full until it really finishes
        local left=$(( eta - el )) lefttxt
        if [ "$left" -gt 0 ]; then lefttxt="~$(_fmt_secs $left) left"; else lefttxt="finishing…"; fi
        local otxt=""
        if [ "$ETA_REMAINING" -gt 0 ]; then
            local orem=$(( ETA_REMAINING - el )); [ "$orem" -lt 0 ] && orem=0
            otxt=" \033[0;37m· total ~$(_fmt_secs $orem)\033[0m"
        fi
        printf "\r\033[K \033[1;33m%s\033[0m \033[0;37m[%s]\033[0m %s  \033[1;36m▕%s▏\033[0m \033[0;37m%s · %s\033[0m%b" \
            "${frames[$i]}" "$counter" "$msg" "$(_bar "$pct" 14)" "$(_fmt_secs $el)" "$lefttxt" "$otxt"
        i=$(( (i + 1) % n ))
        sleep 0.2
    done
    wait "$pid"
    local rc=$?
    local el=$(( $(date +%s) - start ))
    tput cnorm 2>/dev/null
    if [ "$ETA_REMAINING" -gt 0 ]; then
        ETA_REMAINING=$(( ETA_REMAINING - eta )); [ "$ETA_REMAINING" -lt 0 ] && ETA_REMAINING=0
    fi
    # The one place every long step passes through, so the bot's progress bar is
    # drawn from here instead of from seven separate call sites - and a step
    # added to the update later moves the bar without anyone remembering to.
    if { [ -n "$MZ_TPL_PROGRESS" ] || [ -n "$CIRO_RESTORE_PCT" ]; } && [ "$rc" -eq 0 ] && [ "$STEP_TOTAL" -gt 0 ]; then
        _selfupdate_progress $(( STEP_NO * 100 / STEP_TOTAL ))
    fi
    if [ "$rc" -eq 0 ]; then
        printf "\r\033[K \033[1;32m✔\033[0m \033[0;37m[%s]\033[0m %s \033[0;37m(%s)\033[0m\n" "$counter" "$msg" "$(_fmt_secs $el)"
    else
        printf "\r\033[K \033[1;31m✘\033[0m \033[0;37m[%s]\033[0m %s \033[0;37m(%s)\033[0m\n" "$counter" "$msg" "$(_fmt_secs $el)"
    fi
    return "$rc"
}

show_step_error() {
    echo -e "\033[1;31m──────────────── Error details ─────────────────\033[0m"
    tail -n 20 "$INSTALL_LOG" 2>/dev/null
    echo -e "\033[1;31m─────────────────────────────────────────────────\033[0m"
}

# ── Menu UI helpers ──────────────────────────────────────────
C_BORDER=$'\033[38;5;24m'; C_TITLE=$'\033[1;97m'; C_DIM=$'\033[38;5;245m'
C_KEY=$'\033[1;36m';       C_TXT=$'\033[0;37m';   C_OK=$'\033[1;32m'
C_BAD=$'\033[1;31m';       C_WARN=$'\033[1;33m';  C_PROMPT=$'\033[1;36m'
C_ACCENT=$'\033[38;5;39m'; C_LABEL=$'\033[38;5;250m'
CR=$'\033[0m'
UI_W=60   # width of horizontal rules (no right border = never misaligns)

_repeat() { local ch="$1" n="$2" out="" i; for ((i=0;i<n;i++)); do out+="$ch"; done; printf '%s' "$out"; }
# Horizontal rules (left-aligned, no right edge to drift)
_rule()   { printf "  ${C_BORDER}%s${CR}\n" "$(_repeat "─" "$UI_W")"; }
_drule()  { printf "  ${C_ACCENT}%s${CR}\n" "$(_repeat "━" "$UI_W")"; }
# Banner
banner()  {
    echo
    _drule
    printf "  ${C_ACCENT}◆${CR}  ${C_TITLE}CIRO${CR}\n"
    printf "     ${C_DIM}one bot, a different shop for every language${CR}\n"
    _drule
}
# Menu row. Two forms:
#   _mi "1" "text"                 - plain
#   _mi "1" "Install" "what it does" - name padded, description dimmed
_mi() {
    if [ -n "${3-}" ]; then
        printf "   ${C_KEY}%2s${CR} ${C_BORDER}│${CR} ${C_TXT}%-13s${CR} ${C_DIM}%b${CR}\n" "$1" "$2" "$3"
    else
        printf "   ${C_KEY}%2s${CR} ${C_BORDER}│${CR} ${C_TXT}%b${CR}\n" "$1" "$2"
    fi
}

# ── DNS auto-fix (used early, before any download) ───────────
RESOLV="/etc/resolv.conf"
DNS_SERVERS=("1.1.1.1" "8.8.8.8" "9.9.9.9")

dns_works() {
    getent hosts github.com       >/dev/null 2>&1 && return 0
    getent hosts api.telegram.org >/dev/null 2>&1 && return 0
    return 1
}

ensure_dns() {
    dns_works && return 0
    echo -e "  ${C_WARN}!${CR} ${C_WARN}DNS resolution failed - configuring public DNS...${CR}"
    if [ -L "$RESOLV" ]; then
        rm -f "$RESOLV" 2>/dev/null
    elif [ -f "$RESOLV" ] && [ ! -f "${RESOLV}.ciro.bak" ]; then
        cp -a "$RESOLV" "${RESOLV}.ciro.bak" 2>/dev/null
    fi
    { local d; for d in "${DNS_SERVERS[@]}"; do echo "nameserver $d"; done; } > "$RESOLV" 2>/dev/null
    if command -v resolvectl >/dev/null 2>&1; then
        local ifc; ifc=$(ip route show default 2>/dev/null | awk '/default/{print $5; exit}')
        [ -n "$ifc" ] && resolvectl dns "$ifc" "${DNS_SERVERS[@]}" 2>/dev/null || true
    fi
    sleep 1
    dns_works && { echo -e "  ${C_OK}●${CR} ${C_OK}DNS is now working.${CR}"; return 0; }
    echo -e "  ${C_BAD}●${CR} ${C_BAD}DNS still failing after applying public resolvers.${CR}"
    return 1
}

# Ensure /usr/local/bin/Ciro points at the master script
_link_ciro() {
    local master="$1" link="$2"
    chmod +x "$master" 2>/dev/null
    if [ ! -e "$link" ] || [ "$(readlink -f "$link" 2>/dev/null)" != "$(readlink -f "$master" 2>/dev/null)" ]; then
        ln -sf "$master" "$link"
    fi
    chmod +x "$link" 2>/dev/null
}

# ── GitHub source ────────────────────────────────────────────
# The one place the project's GitHub repository is named: the script's own
# self-update, the version check and every download of the bot (install,
# `Ciro update` and the bot's 🔄 /update) all read it from here.
GIT_REPO="DenTeeR/Ciro"

# Self-update: every run, fetch the latest script from GitHub, validate it,
# install it to /root/install.sh, link it into /usr/local/bin, and re-exec.
function self_update_script() {
    # asking for help must never depend on the network - and neither must the
    # once-a-minute watcher: fetching this script from GitHub every 60 seconds
    # would hammer it for nothing, and rewriting /root/install.sh while bash is
    # still reading that very file is how a half-executed script happens. The
    # watcher gets its new copy the normal way, from the package update_bot
    # unpacks (step 9), not from here.
    for _a in "$@"; do
        case "$_a" in -h|--help|-v|--version|version|selfupdate-watch|transfer-probe|transfer-receive) return 0 ;; esac
    done
    local MASTER_PATH="/root/install.sh"
    local BIN_LINK="/usr/local/bin/Ciro"
    local URL="https://raw.githubusercontent.com/${GIT_REPO}/master/install.sh"
    local TEMP_FILE="/tmp/ciro_update.sh"

    # Make sure DNS works before reaching GitHub
    ensure_dns >/dev/null 2>&1

    echo -e "\e[33mChecking for the latest script version...\033[0m"
    rm -f "$TEMP_FILE"
    curl -fsSL --max-time 15 -o "$TEMP_FILE" "$URL" 2>/dev/null \
        || wget -q -O "$TEMP_FILE" "$URL" 2>/dev/null

    # Normalize line endings so a CRLF download can never break bash
    [ -f "$TEMP_FILE" ] && sed -i 's/\r$//' "$TEMP_FILE"

    # Validate the download is a complete, valid bash script (not a 404/HTML/partial)
    local valid=0
    if [ -s "$TEMP_FILE" ] \
       && head -n1 "$TEMP_FILE" | grep -q '^#!/bin/bash' \
       && grep -q 'process_arguments' "$TEMP_FILE" \
       && bash -n "$TEMP_FILE" 2>/dev/null; then
        valid=1
    fi

    if [ "$valid" -ne 1 ]; then
        echo -e "\e[91mWarning: could not fetch a valid update (offline / bad download). Using current version.\033[0m"
        rm -f "$TEMP_FILE"
        if [ ! -f "$MASTER_PATH" ]; then
            # No copy installed yet - but if the script we are running is
            # itself valid, use it. Only a genuinely unusable state exits.
            local SELF; SELF=$(readlink -f "${BASH_SOURCE[0]}" 2>/dev/null)
            if [ -n "$SELF" ] && [ -r "$SELF" ] && bash -n "$SELF" 2>/dev/null; then
                install -m 0755 "$SELF" "$MASTER_PATH" 2>/dev/null || { cp "$SELF" "$MASTER_PATH"; chmod +x "$MASTER_PATH"; }
                _link_ciro "$MASTER_PATH" "$BIN_LINK"
                echo -e "\e[93mGitHub is unreachable - continuing with this copy of the script.\033[0m"
                return 0
            fi
            echo -e "\e[91mCritical: no usable copy of the script and GitHub is unreachable.\033[0m"
            exit 1
        fi
        _link_ciro "$MASTER_PATH" "$BIN_LINK"
        return 0
    fi

    local LOCAL_HASH REMOTE_HASH
    if [ -f "$MASTER_PATH" ]; then
        LOCAL_HASH=$(md5sum "$MASTER_PATH" | awk '{print $1}')
    else
        LOCAL_HASH="not_installed"
    fi
    REMOTE_HASH=$(md5sum "$TEMP_FILE" | awk '{print $1}')

    if [ "$LOCAL_HASH" != "$REMOTE_HASH" ]; then
        if [ "$LOCAL_HASH" = "not_installed" ]; then
            echo -e "\e[32mInstalling script to the system...\033[0m"
        else
            echo -e "\e[32mNew version found - updating...\033[0m"
        fi
        install -m 0755 "$TEMP_FILE" "$MASTER_PATH" 2>/dev/null || { mv "$TEMP_FILE" "$MASTER_PATH"; chmod +x "$MASTER_PATH"; }
        rm -f "$TEMP_FILE"
        _link_ciro "$MASTER_PATH" "$BIN_LINK"
        echo -e "\e[32mUpdated. Restarting with the latest version...\033[0m"
        sleep 1
        exec bash "$MASTER_PATH" "$@"
    fi

    # Already up to date - just make sure it is linked under /usr/local/bin
    rm -f "$TEMP_FILE"
    _link_ciro "$MASTER_PATH" "$BIN_LINK"
    echo -e "\e[32mScript is up to date.\033[0m"
}
self_update_script "$@"

# Tools the bot needs from the server, not only the installer: its 🗄 backups
# are zipped with zip. Checked whenever an admin runs this script - install,
# the menu, a transfer (both ends) - and installed only when missing; never
# from the once-a-minute watcher.
CIRO_PREREQS="zip"
function ensure_prerequisites() {
    for _a in "$@"; do
        case "$_a" in -h|--help|-v|--version|version|selfupdate-watch|transfer-probe|transfer-receive) return 0 ;; esac
    done
    local missing="" t
    for t in $CIRO_PREREQS; do
        command -v "$t" >/dev/null 2>&1 || missing="$missing $t"
    done
    [ -z "$missing" ] && return 0
    echo -e "\e[33mInstalling prerequisites:${missing}...\033[0m"
    DEBIAN_FRONTEND=noninteractive apt-get install -y $missing >/dev/null 2>&1 \
        || echo -e "\e[91mCould not install${missing} - the bot's backups need it: apt install -y${missing}\033[0m"
}
ensure_prerequisites "$@"

# ── Repo / paths ─────────────────────────────────────────────
BOT_DIR_DEFAULT="/var/www/html/Ciro"
CONFIG_FILE_DEFAULT="$BOT_DIR_DEFAULT/config.php"
# GIT_REPO is set at the top of the script (GitHub source)
IP_CACHE="/tmp/.ciro_server_ip"

# ── Resumable-install state engine ───────────────────────────
# Survives reboots / network drops. Lets a failed install resume
# from the last completed phase instead of starting from scratch.
STATE_DIR="/root/Ciro"
STATE_FILE="$STATE_DIR/.install_state"

state_init() {
    mkdir -p "$STATE_DIR" 2>/dev/null
    if [ ! -f "$STATE_FILE" ]; then
        : > "$STATE_FILE"
        chmod 600 "$STATE_FILE" 2>/dev/null
    fi
}

# state_set KEY VALUE  -> store a persistent answer (domain/token/etc.)
state_set() {
    state_init
    sed -i "/^$1=/d" "$STATE_FILE" 2>/dev/null
    printf '%s=%s\n' "$1" "$2" >> "$STATE_FILE"
}

# state_get KEY -> echo the stored value (empty if missing)
state_get() {
    [ -f "$STATE_FILE" ] || return 0
    grep -E "^$1=" "$STATE_FILE" 2>/dev/null | tail -1 | cut -d= -f2-
}

# phase_done NAME -> 0 if the phase already completed successfully
phase_done() {
    [ -f "$STATE_FILE" ] && grep -qxF "PHASE:$1" "$STATE_FILE" 2>/dev/null
}

# mark_phase NAME -> record a phase as completed
mark_phase() {
    state_init
    grep -qxF "PHASE:$1" "$STATE_FILE" 2>/dev/null || echo "PHASE:$1" >> "$STATE_FILE"
}

# has_resumable_state -> 0 if an unfinished install is on disk
has_resumable_state() {
    [ -f "$STATE_FILE" ] || return 1
    { grep -q '^PHASE:' "$STATE_FILE" 2>/dev/null || grep -q '^STARTED=' "$STATE_FILE" 2>/dev/null; } \
        && ! phase_done COMPLETE
}

state_clear() { rm -f "$STATE_FILE" 2>/dev/null; }

# ── apt/dpkg recovery ────────────────────────────────────────
# A previous interrupted apt run (or Ubuntu's background
# unattended-upgrades) can hold the dpkg lock, making the next
# apt command hang forever. This waits for any LIVE apt to finish,
# clears locks left by a DEAD process, then repairs dpkg state.
apt_recover() {
    local i=0
    # 1) If a real apt/dpkg is running (e.g. unattended-upgrades), wait for it
    if pgrep -x 'apt|apt-get|dpkg|unattended-upgr' >/dev/null 2>&1; then
        echo "Another apt/dpkg process is running; waiting up to 3 minutes for it to finish..."
        while pgrep -x 'apt|apt-get|dpkg|unattended-upgr' >/dev/null 2>&1; do
            sleep 3; i=$((i + 1)); [ "$i" -ge 60 ] && break
        done
    fi
    # 2) Disable Ubuntu auto-update timers during install so they cannot re-grab the lock
    systemctl stop apt-daily.service apt-daily-upgrade.service \
        unattended-upgrades.service >/dev/null 2>&1
    systemctl stop apt-daily.timer apt-daily-upgrade.timer >/dev/null 2>&1
    # 3) No live holder now -> remove stale locks left by the crashed run
    if ! pgrep -x 'apt|apt-get|dpkg|unattended-upgr' >/dev/null 2>&1; then
        rm -f /var/lib/apt/lists/lock /var/cache/apt/archives/lock \
              /var/lib/dpkg/lock /var/lib/dpkg/lock-frontend 2>/dev/null
    fi
    # 4) Repair any half-configured packages from the interruption
    DEBIAN_FRONTEND=noninteractive dpkg --configure -a >/dev/null 2>&1
    return 0
}
export -f apt_recover

OS_ID=""; OS_VERSION_ID=""; OS_CODENAME=""; OS_PRETTY=""
detect_os() {
    [ -n "$OS_ID" ] && return 0
    [ -f /etc/os-release ] || return 1
    local fields
    fields=$(. /etc/os-release 2>/dev/null; printf '%s\t%s\t%s\t%s' \
        "${ID:-}" "${VERSION_ID:-}" "${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}" "${PRETTY_NAME:-unknown}")
    IFS=$'\t' read -r OS_ID OS_VERSION_ID OS_CODENAME OS_PRETTY <<< "$fields"
    return 0
}
export -f detect_os

os_major() {
    detect_os
    local m="${OS_VERSION_ID%%.*}"
    case "$m" in ''|*[!0-9]*) echo 0 ;; *) echo "$m" ;; esac
}
export -f os_major

php_ppa_has_series() {
    [ -n "$1" ] || return 1
    local try
    for try in 1 2 3; do
        curl -fsSL --max-time 10 -o /dev/null \
            "https://ppa.launchpadcontent.net/ondrej/php/ubuntu/dists/$1/Release" 2>/dev/null && return 0
        sleep 2
    done
    return 1
}
export -f php_ppa_has_series

php_repo_disable() {
    local f n=0
    for f in /etc/apt/sources.list.d/*ondrej*php*.sources /etc/apt/sources.list.d/*ondrej*php*.list; do
        [ -f "$f" ] || continue
        mv -f "$f" "$f.disabled-by-ciro" && n=$((n + 1))
    done
    [ "$n" -gt 0 ]
}
export -f php_repo_disable

setup_php_repo() {
    detect_os
    export DEBIAN_FRONTEND=noninteractive
    # add-apt-repository lives in software-properties-common - minimal cloud
    # images (and the 26.04 minimal image in particular) do not ship it.
    if ! command -v add-apt-repository >/dev/null 2>&1; then
        apt-get update -o DPkg::Lock::Timeout=180 >/dev/null 2>&1
        apt-get install -y software-properties-common ca-certificates curl gnupg \
            -o DPkg::Lock::Timeout=180 || return 1
    fi
    add-apt-repository -y ppa:ondrej/php || \
        LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php || return 1

    # Does the PPA actually build for this release? Pinning to an older series
    if [ -n "$OS_CODENAME" ] && ! php_ppa_has_series "$OS_CODENAME"; then
        echo "ondrej/php publishes no packages for '$OS_CODENAME' - disabling the PPA and using the PHP shipped with $OS_PRETTY."
        php_repo_disable || echo "Warning: no ondrej/php source file found to disable."
    fi
    return 0
}
export -f setup_php_repo

export PHP_VER_CANDIDATES="8.2 8.3 8.4 8.5"

# 0 when apt has an installable candidate for this package.
_apt_has_candidate() {
    local cand
    cand=$(apt-cache policy "$1" 2>/dev/null | awk '/Candidate:/{print $2; exit}')
    [ -n "$cand" ] && [ "$cand" != "(none)" ]
}
export -f _apt_has_candidate

resolve_php_ver() {
    local v
    for v in $PHP_VER_CANDIDATES; do
        if _apt_has_candidate "php$v" && _apt_has_candidate "libapache2-mod-php$v"; then
            echo "$v"; return 0
        fi
    done
    echo "8.2"
    return 1
}
export -f resolve_php_ver

# Configure MySQL root login (all output captured by run_step's log).
setup_mysql_root() {
    sudo mkdir -p /root/Ciro || return 1
    touch /root/Ciro/dbroot.txt || return 1
    sudo chmod -R 777 /root/Ciro/dbroot.txt || return 1
    local randomdbpasstxt passs userrr RANDOM_NUMBER
    randomdbpasstxt=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    RANDOM_NUMBER=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | cut -c1-12)
    echo "\$user = 'root';"               >> /root/Ciro/dbroot.txt
    echo "\$pass = '${randomdbpasstxt}';" >> /root/Ciro/dbroot.txt
    echo "\$path = '${RANDOM_NUMBER}';"   >> /root/Ciro/dbroot.txt
    passs=$(grep '$pass' /root/Ciro/dbroot.txt | cut -d"'" -f2)
    userrr=$(grep '$user' /root/Ciro/dbroot.txt | cut -d"'" -f2)
    local alter_ok=0
    if sudo mysql -u "$userrr" -p"$passs" -e "alter user '$userrr'@'localhost' identified with mysql_native_password by '$passs';FLUSH PRIVILEGES;"; then
        alter_ok=1
    elif sudo mysql -e "alter user '$userrr'@'localhost' identified with mysql_native_password by '$passs';FLUSH PRIVILEGES;"; then
        alter_ok=1
    elif sudo mysql -e "alter user '$userrr'@'localhost' identified with caching_sha2_password by '$passs';FLUSH PRIVILEGES;"; then
        alter_ok=1
    elif sudo mysql -e "alter user '$userrr'@'localhost' identified by '$passs';FLUSH PRIVILEGES;"; then
        alter_ok=1
    fi
    if [ "$alter_ok" -eq 1 ]; then
        echo "SELECT 1" | mysql -u"$userrr" -p"$passs" >/dev/null 2>&1 && return 0
    fi

    local dropin_dir=""
    local d
    for d in /etc/mysql/mysql.conf.d /etc/mysql/mariadb.conf.d /etc/mysql/conf.d; do
        [ -d "$d" ] && { dropin_dir="$d"; break; }
    done
    [ -n "$dropin_dir" ] || return 1
    local dropin="$dropin_dir/zz-ciro-recovery.cnf"
    printf '[mysqld]\nskip-grant-tables\n' | sudo tee "$dropin" >/dev/null || return 1
    sudo systemctl restart mysql
    sudo mysql <<EOF
FLUSH PRIVILEGES;
DROP USER IF EXISTS 'root'@'localhost';
CREATE USER 'root'@'localhost' IDENTIFIED BY '${passs}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
    sudo rm -f "$dropin"
    # Older installer versions appended the option to mysqld.cnf directly.
    sudo sed -i '/^skip-grant-tables/d' /etc/mysql/mysql.conf.d/mysqld.cnf 2>/dev/null
    sudo systemctl restart mysql
    echo "SELECT 1" | mysql -u"$userrr" -p"$passs" >/dev/null 2>&1 || return 1
    return 0
}
export -f setup_mysql_root

# Install Composer to /usr/local/bin/composer when it is not already available.
# The installer is verified against the official signature before it is run.
ensure_composer() {
    if command -v composer >/dev/null 2>&1; then
        return 0
    fi

    local php_bin setup expected actual
    php_bin="$(command -v php)" || return 1
    setup="$(mktemp /tmp/composer-setup.XXXXXX.php)"

    expected="$("$php_bin" -r "echo @file_get_contents('https://composer.github.io/installer.sig');" 2>/dev/null | tr -d '[:space:]')"
    if ! "$php_bin" -r "exit(@copy('https://getcomposer.org/installer', '$setup') ? 0 : 1);"; then
        rm -f "$setup"
        echo "Failed to download the Composer installer." >&2
        return 1
    fi

    actual="$("$php_bin" -r "echo hash_file('sha384', '$setup');" 2>/dev/null | tr -d '[:space:]')"
    if [ -z "$expected" ] || [ "$expected" != "$actual" ]; then
        rm -f "$setup"
        echo "Composer installer signature mismatch - refusing to run it." >&2
        return 1
    fi

    "$php_bin" "$setup" --quiet --install-dir=/usr/local/bin --filename=composer
    local rc=$?
    rm -f "$setup"
    [ "$rc" -eq 0 ] && command -v composer >/dev/null 2>&1
}
export -f ensure_composer

# Detect the active CLI PHP major.minor (e.g. 8.2). Empty on failure.
active_php_ver() {
    php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null
}
export -f active_php_ver

ensure_php_exts_for_composer() {
    local ver pkgs
    ver="$(active_php_ver)"
    if [ -z "$ver" ]; then
        echo "PHP CLI not found - cannot install required extensions." >&2
        return 1
    fi

    if php -m 2>/dev/null | grep -qi '^mbstring$' \
        && php -m 2>/dev/null | grep -qi '^dom$'; then
        return 0
    fi

    pkgs="php${ver}-mbstring php${ver}-xml php${ver}-zip php${ver}-gd php${ver}-curl php${ver}-intl php${ver}-bcmath"
    echo "Ensuring PHP ${ver} extensions for Composer: ${pkgs}"
    DEBIAN_FRONTEND=noninteractive apt-get install -y $pkgs || {
        echo "Failed to install PHP ${ver} extensions required by Composer." >&2
        return 1
    }

    if ! php -m 2>/dev/null | grep -qi '^mbstring$' \
        || ! php -m 2>/dev/null | grep -qi '^dom$'; then
        echo "PHP ${ver} is missing mbstring and/or dom after package install." >&2
        return 1
    fi
    return 0
}
export -f ensure_php_exts_for_composer

# Build vendor/ from composer.json + composer.lock. vendor/ is not shipped in the
# release archive, so this must run on every fresh install and update.
install_php_deps() {
    local dir="$1"

    if [ ! -f "$dir/composer.json" ]; then
        echo "No composer.json in $dir - skipping dependency installation."
        return 0
    fi

    ensure_php_exts_for_composer || return 1
    ensure_composer || return 1

    COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 \
        composer install --working-dir="$dir" \
        --no-dev --optimize-autoloader --prefer-dist --no-progress || return 1

    if [ ! -f "$dir/vendor/autoload.php" ]; then
        echo "composer install finished but $dir/vendor/autoload.php is missing." >&2
        return 1
    fi

    chown -R www-data:www-data "$dir/vendor" 2>/dev/null
    return 0
}
export -f install_php_deps

# True if a package is installed and configured.
_pkg_installed() { dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q 'install ok installed'; }

_pkg_installed_glob() {
    dpkg-query -W -f='${Package} ${Status}\n' "$1" 2>/dev/null | grep -q 'install ok installed'
}

# Refuse to install on a server that already has conflicting software.
# Only runs on a brand-new install (never on resume / a partial state of its own).
precheck_fresh_server() {
    # A package being installed is not a reason to refuse - an empty LAMP
    # stack is fine and gets reconfigured. What blocks the install is
    # something that is actually IN USE and would be taken over: another
    # site, another database, a running web server, a panel.
    # There is no way past this on purpose: the installer resets the MySQL
    # root password, rewrites the Apache config and reinstalls phpMyAdmin.
    # Each blocker is "label|name|detail" so the name can be shown in red.
    local blockers=() notes=()

    # --- panels that bind the same ports and rewrite the same configs ---
    { [ -d /opt/marzban ] || [ -d /var/lib/marzban ]; } && blockers+=("Panel|Marzban|owns ports 80/443")
    { [ -d /opt/hiddify-manager ] || [ -d /opt/hiddify-config ]; } && blockers+=("Panel|Hiddify|owns ports 80/443")
    { [ -d /etc/x-ui ] || [ -d /usr/local/x-ui ]; } && notes+=("x-ui is installed - make sure it is not using port 80 (on 443 it is fine: the bot can take 8443 or 88)")

    # --- nginx: only a problem if it is actually serving ---
    if _pkg_installed nginx || _pkg_installed nginx-core || _pkg_installed nginx-full; then
        if systemctl is-active nginx >/dev/null 2>&1; then
            blockers+=("Web server|nginx|running, would fight Apache for port 80/443")
        else
            notes+=("nginx is installed but stopped - leaving it alone")
        fi
    fi

    # --- apache: only a problem if it already serves someone else's site ---
    if _pkg_installed apache2; then
        local site
        for site in $(ls -1 /etc/apache2/sites-enabled/ 2>/dev/null | grep -vE '^(000-default|default-ssl)'); do
            blockers+=("Apache site|${site}|already served from this server")
        done
        if [ ${#blockers[@]} -eq 0 ]; then
            notes+=("Apache is installed with no sites of its own - it will be configured for the bot")
        fi
    fi

    # --- mysql/mariadb: only a problem if it holds real databases ---
    if _pkg_installed mysql-server || _pkg_installed_glob 'mysql-server-[0-9]*' \
       || _pkg_installed mariadb-server || _pkg_installed_glob 'mariadb-server-[0-9]*'; then
        local dbs db owner
        dbs=$(mysql -N -B -e "SHOW DATABASES;" 2>/dev/null \
            | grep -vxE '(mysql|information_schema|performance_schema|sys|phpmyadmin)')
        if [ -n "$dbs" ]; then
            for db in $dbs; do
                # name the service that owns it, when one is obvious
                owner=$(systemctl list-units --type=service --state=running --no-legend 2>/dev/null \
                        | awk '{print $1}' | grep -iE "^${db}(\.service)?$" | head -1)
                if [ -n "$owner" ]; then
                    blockers+=("MySQL database|${db}|in use by ${owner}")
                else
                    blockers+=("MySQL database|${db}|holds data that is not the bot's")
                fi
            done
        else
            if mysql -e "SELECT 1;" >/dev/null 2>&1; then
                notes+=("MySQL is installed and empty - the bot's database will be created in it")
            else
                notes+=("MySQL is installed but its root login is password-protected - the installer will ask for it")
            fi
        fi
    fi

    _pkg_installed phpmyadmin && notes+=("phpMyAdmin is installed - it will be reconfigured for Apache")

    # --- nothing in the way ---
    if [ ${#blockers[@]} -eq 0 ]; then
        if [ ${#notes[@]} -gt 0 ]; then
            _sec "Existing software"
            local n
            for n in "${notes[@]}"; do printf "    ${C_DIM}·${CR} ${C_DIM}%s${CR}\n" "$n"; done
            echo ""
        fi
        return 0
    fi

    # --- blocked ---
    clear 2>/dev/null || true
    banner
    _sec "Cannot install on this server"
    printf "    ${C_BAD}●${CR} ${C_TXT}Something here is already in use and would be taken over:${CR}\n"
    echo ""
    local b label name detail
    for b in "${blockers[@]}"; do
        label="${b%%|*}"
        name="${b#*|}"; name="${name%%|*}"
        detail="${b##*|}"
        printf "      ${C_DIM}%-16s${CR} ${C_BAD}%s${CR}  ${C_DIM}%s${CR}\n" "$label" "$name" "$detail"
    done
    echo ""
    printf "    ${C_TXT}Installing Ciro resets the MySQL root password, rewrites the Apache${CR}\n"
    printf "    ${C_TXT}configuration and reinstalls phpMyAdmin. On the above that breaks${CR}\n"
    printf "    ${C_TXT}what is running now, so the installer stops here.${CR}\n"
    echo ""
    printf "    ${C_DIM}Install on a clean server, or remove the software listed above first.${CR}\n"
    printf "    ${C_DIM}If this server already runs a bot, choose ${CR}${C_KEY}2 (Update)${CR}${C_DIM} instead.${CR}\n"
    return 1
}


repair_mysql() {
    export DEBIAN_FRONTEND=noninteractive
    systemctl stop mysql 2>/dev/null
    # 1) Gentle fix first
    dpkg --configure -a >/dev/null 2>&1
    apt-get install -f -y >/dev/null 2>&1
    if dpkg-query -W -f='${Package} ${Status}\n' 'mysql-server-[0-9]*' 2>/dev/null | grep -q 'install ok installed'; then
        return 0
    fi
    # 2) Hard reset: purge MySQL and wipe its (empty) data dir, then reinstall fresh
    apt-get purge -y 'mysql-server*' 'mysql-client*' 'mysql-community*' mysql-common >/dev/null 2>&1
    apt-get autoremove -y >/dev/null 2>&1
    rm -rf /var/lib/mysql /var/log/mysql /etc/mysql
    dpkg --configure -a >/dev/null 2>&1
    apt-get update --allow-releaseinfo-change >/dev/null 2>&1
    return 0
}
export -f repair_mysql


install_pause() {
    local where="$1"
    echo ""
    echo -e "  ${C_WARN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${CR}"
    echo -e "  ${C_WARN}● Installation paused${CR} ${C_DIM}(${where})${CR}"
    echo -e "  ${C_DIM}This is usually caused by the server losing internet or a network error.${CR}"
    echo ""
    echo -e "  ${C_TXT}Completed steps are saved. Just run it again:${CR}"
    echo -e "      ${C_KEY}Ciro install${CR}"
    echo -e "  ${C_DIM}It resumes from this step; values you already entered (domain/token/...) will not be asked again.${CR}"
    echo -e "  ${C_WARN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${CR}"
    echo ""
    exit 1
}

# Colored status dot
_dot() {
    case "$1" in
        ok)   printf "${C_OK}●${CR}"  ;;
        bad)  printf "${C_BAD}●${CR}" ;;
        warn) printf "${C_WARN}●${CR}";;
        *)    printf "${C_DIM}○${CR}" ;;
    esac
}
_sec() { printf "\n  ${C_ACCENT}▎${CR}${C_TITLE}%s${CR}\n" "$1"; _rule; }
_kv()  { printf "    ${C_LABEL}%-12s${CR} %b${CR}\n" "$1" "$2"; }

# Read the installed version from the source 'version' file
# $1 = bot directory (default: the first bot's)
get_installed_version() {
    local dir="${1:-$BOT_DIR_DEFAULT}"
    if [ -f "$dir/version" ]; then
        tr -d ' \t\r\n' < "$dir/version"
    else
        echo ""
    fi
}

# The newest version on GitHub: the very `version` file on master that the
# bot's own update screen compares with, so the command and the bot always
# name the same number. Cached ten minutes in /root/Ciro ("fresh" skips the
# cache); a failed ask keeps the last answer and is tried again a minute
# later. Prints nothing when it is not known.
github_latest_version() {
    local cache="/root/Ciro/latest_version" now at="" v="" body
    now=$(date +%s)
    if [ -f "$cache" ]; then
        read -r at v < "$cache" 2>/dev/null
        case "$at" in ''|*[!0-9]*) at=0; v="" ;; esac
        if [ "${1:-}" != "fresh" ] && [ $((now - at)) -lt 600 ]; then
            echo "$v"
            return 0
        fi
    fi
    body=$(curl -fsSL --connect-timeout 3 --max-time 4 "https://raw.githubusercontent.com/${GIT_REPO}/master/version" 2>/dev/null | tr -d ' \t\r\n')
    mkdir -p /root/Ciro 2>/dev/null
    if [[ "$body" =~ ^[0-9][0-9A-Za-z._-]{0,20}$ ]]; then
        echo "$now $body" > "$cache" 2>/dev/null
        echo "$body"
    else
        echo "$((now - 540)) $v" > "$cache" 2>/dev/null
        echo "$v"
    fi
}

# Choose which source to download.
# Sets globals: SRC_ZIP_URL, SRC_LABEL
# There is one source: the current code on master. Release tags used to be an
# option, and that is exactly what went wrong - the newest tag could sit well
# behind master, so "latest stable" quietly installed old code. No prompt now.
# Returns: 0 = chosen (it cannot fail)
choose_source() {
    local latest
    latest=$(github_latest_version fresh)
    SRC_ZIP_URL="https://github.com/${GIT_REPO}/archive/refs/heads/master.zip"
    # named by its number, the way the bot names it
    SRC_LABEL="Latest"
    [ -n "$latest" ] && SRC_LABEL="version ${latest}"
    return 0
}

# Get public server IP, cached for 1 hour (falls back to local IP)
get_server_ip() {
    if [ -f "$IP_CACHE" ] && [ $(( $(date +%s) - $(stat -c %Y "$IP_CACHE" 2>/dev/null || echo 0) )) -lt 3600 ]; then
        cat "$IP_CACHE"
        return
    fi
    local ip
    ip=$(curl -fsSL --max-time 4 ifconfig.me 2>/dev/null)
    [ -z "$ip" ] && ip=$(curl -fsSL --max-time 4 https://api.ipify.org 2>/dev/null)
    [ -z "$ip" ] && ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    [ -z "$ip" ] && ip="n/a"
    echo "$ip" > "$IP_CACHE"
    echo "$ip"
}

# ── Dashboard sections ───────────────────────────────────────
# The three bot sections take the bot's directory; version_section also
# takes the block's title.
version_section() {
    local inst latest line
    inst=$(get_installed_version "$1")
    _sec "${2:-Bot}"
    if [ -n "$inst" ]; then
        line="$(_dot ok) ${C_OK}${inst}${CR}"
        # next to what GitHub has, as the bot's update screen puts it
        latest=$(github_latest_version)
        if [ -n "$latest" ]; then
            if _ver_older "$inst" "$latest"; then
                line="${line}  ${C_WARN}→ ${latest} is out · choose Update${CR}"
            else
                line="${line}  ${C_DIM}· the newest on GitHub${CR}"
            fi
        fi
        _kv "Version" "$line"
    elif [ -f "$1/config.php" ]; then
        _kv "Version" "$(_dot warn) ${C_WARN}original project (no version file)${CR}"
    else
        _kv "Version" "$(_dot none) ${C_DIM}not installed${CR}"
    fi
}

# `Ciro version`: each bot's version and the newest on GitHub, in the same
# words the dashboard and the bot's update screen use.
print_version() {
    local rows="" dir name ver latest any=0 older=0
    latest=$(github_latest_version)
    if [ -f "$BOTS_REGISTRY" ] && command -v jq >/dev/null 2>&1; then
        rows=$(jq -r '.[] | "\(.dir)\t\(.name)"' "$BOTS_REGISTRY" 2>/dev/null)
    fi
    [ -z "$rows" ] && rows="${BOT_DIR_DEFAULT}"$'\t'"Bot"
    echo
    while IFS=$'\t' read -r dir name; do
        [ -d "$dir" ] || continue
        ver=$(get_installed_version "$dir")
        if [ -n "$ver" ]; then
            _kv "${name:-Bot}" "${C_OK}${ver}${CR} ${C_DIM}${dir}${CR}"
            [ -n "$latest" ] && _ver_older "$ver" "$latest" && older=1
        elif [ -f "$dir/config.php" ]; then
            _kv "${name:-Bot}" "${C_WARN}original project (no version file)${CR} ${C_DIM}${dir}${CR}"
        else
            continue
        fi
        any=1
    done <<< "$rows"
    [ "$any" = 0 ] && _kv "Bot" "${C_DIM}not installed${CR}"
    if [ -z "$latest" ]; then
        _kv "GitHub" "${C_DIM}could not be reached${CR}"
    elif [ "$older" = 1 ]; then
        _kv "GitHub" "${C_WARN}${latest}${CR} ${C_DIM}· newer than the bot · run${CR} ${C_KEY}Ciro update${CR}"
    else
        _kv "GitHub" "${C_OK}${latest}${CR} ${C_DIM}· the newest${CR}"
    fi
    echo
}

bot_section() {
    local dir="${1:-$BOT_DIR_DEFAULT}"
    SSL_DOMAIN=""
    if [ ! -f "$dir/config.php" ]; then
        return
    fi
    SSL_DOMAIN=$(hosts_domain "$(grep '^\$domainhosts' "$dir/config.php" | cut -d"'" -f2)")
    if [ -n "$SSL_DOMAIN" ] && [ -f "/etc/letsencrypt/live/$SSL_DOMAIN/cert.pem" ]; then
        local expiry days
        expiry=$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/$SSL_DOMAIN/cert.pem" 2>/dev/null | cut -d= -f2)
        days=$(( ( $(date -d "$expiry" +%s 2>/dev/null || echo 0) - $(date +%s) ) / 86400 ))
        if [ "$days" -gt 14 ]; then
            _kv "Domain" "$(_dot ok) ${C_OK}${SSL_DOMAIN}${CR} ${C_DIM}· SSL ${days}d left${CR}"
        elif [ "$days" -gt 0 ]; then
            _kv "Domain" "$(_dot warn) ${C_WARN}${SSL_DOMAIN}${CR} ${C_DIM}· SSL ${days}d left, renew soon${CR}"
        else
            _kv "Domain" "$(_dot bad) ${C_BAD}${SSL_DOMAIN}${CR} ${C_BAD}· SSL expired${CR}"
        fi
    elif [ -n "$SSL_DOMAIN" ]; then
        _kv "Domain" "$(_dot warn) ${C_WARN}${SSL_DOMAIN}${CR} ${C_DIM}· no certificate${CR}"
    fi
    [ -n "$SSL_DOMAIN" ] && _kv "Database" "${C_DIM}$(pma_url "$SSL_DOMAIN" "$(bots_registry_pma_port "$dir")")${CR}"
}

# Read the Telegram webhook using the bot token from config.php.
# Prints webhook URL / pending count, and surfaces any error message.
webhook_section() {
    # No header of its own: this belongs under "Bot". One line when healthy,
    # detail only when something is actually wrong.
    local dir="${1:-$BOT_DIR_DEFAULT}"
    if [ ! -f "$dir/config.php" ]; then
        return
    fi
    local token info ok url pending err errdate apierr when host
    token=$(grep '^\$APIKEY' "$dir/config.php" | cut -d"'" -f2)
    if [ -z "$token" ]; then
        _kv "Webhook" "$(_dot bad) ${C_BAD}no token in config.php${CR}"
        return
    fi
    info=$(curl -fsSL --max-time 8 "https://api.telegram.org/bot${token}/getWebhookInfo" 2>/dev/null)
    if [ -z "$info" ]; then
        _kv "Webhook" "$(_dot warn) ${C_WARN}cannot reach Telegram${CR} ${C_DIM}· network or filtering${CR}"
        return
    fi
    if command -v jq >/dev/null 2>&1; then
        ok=$(echo "$info"     | jq -r '.ok')
        url=$(echo "$info"    | jq -r '.result.url // empty')
        pending=$(echo "$info"| jq -r '.result.pending_update_count // 0')
        err=$(echo "$info"    | jq -r '.result.last_error_message // empty')
        errdate=$(echo "$info"| jq -r '.result.last_error_date // empty')
        apierr=$(echo "$info" | jq -r '.description // empty')
    else
        ok=$(echo "$info"     | grep -oE '"ok":[[:space:]]*(true|false)' | grep -oE '(true|false)')
        url=$(echo "$info"    | grep -oE '"url":[[:space:]]*"[^"]*"' | sed -E 's/.*"url":[[:space:]]*"([^"]*)".*/\1/')
        pending=$(echo "$info"| grep -oE '"pending_update_count":[[:space:]]*[0-9]+' | grep -oE '[0-9]+$')
        err=$(echo "$info"    | grep -oE '"last_error_message":[[:space:]]*"[^"]*"' | sed -E 's/.*"last_error_message":[[:space:]]*"([^"]*)".*/\1/')
        errdate=$(echo "$info"| grep -oE '"last_error_date":[[:space:]]*[0-9]+' | grep -oE '[0-9]+$')
        apierr=$(echo "$info" | grep -oE '"description":[[:space:]]*"[^"]*"' | sed -E 's/.*"description":[[:space:]]*"([^"]*)".*/\1/')
        [ -z "$pending" ] && pending=0
    fi
    if [ "$ok" != "true" ]; then
        _kv "Webhook" "$(_dot bad) ${C_BAD}${apierr:-API error}${CR}"
        return
    fi
    if [ -z "$url" ]; then
        _kv "Webhook" "$(_dot bad) ${C_BAD}not set${CR} ${C_DIM}· Telegram has nowhere to deliver${CR}"
        return
    fi
    # Telegram keeps last_error_* after it recovers; with nothing pending the
    # error is history, not a current delivery problem.
    if [ -n "$err" ] && [ "${pending:-0}" -gt 0 ] 2>/dev/null; then
        when=""
        [ -n "$errdate" ] && when=$(date -d "@$errdate" '+%d %b %H:%M' 2>/dev/null)
        _kv "Webhook" "$(_dot warn) ${C_WARN}delivering with errors${CR} ${C_DIM}· ${pending} pending${CR}"
        _kv "" "${C_BAD}${err}${CR}${when:+ ${C_DIM}(${when})${CR}}"
    elif [ "${pending:-0}" -gt 50 ] 2>/dev/null; then
        _kv "Webhook" "$(_dot warn) ${C_WARN}${pending} updates queued${CR} ${C_DIM}· bot may be stalled${CR}"
    else
        _kv "Webhook" "$(_dot ok) ${C_OK}connected${CR} ${C_DIM}· ${pending} pending${CR}"
    fi
}

system_section() {
    local php_v apache_s mysql_s ip os apache_d mysql_d
    php_v=$(php -r 'echo PHP_VERSION;' 2>/dev/null); [ -z "$php_v" ] && php_v="n/a"
    apache_s=$(systemctl is-active apache2 2>/dev/null || echo "inactive")
    mysql_s=$(systemctl is-active mysql 2>/dev/null || echo "inactive")
    ip=$(get_server_ip)
    if [ -f /etc/os-release ]; then os=$(. /etc/os-release; echo "$PRETTY_NAME"); else os="Unknown"; fi
    if [ "$apache_s" = "active" ]; then apache_d="$(_dot ok) ${C_DIM}Apache${CR}"; else apache_d="$(_dot bad) ${C_BAD}Apache ${apache_s}${CR}"; fi
    if [ "$mysql_s" = "active" ];  then mysql_d="$(_dot ok) ${C_DIM}MySQL${CR}";   else mysql_d="$(_dot bad) ${C_BAD}MySQL ${mysql_s}${CR}"; fi
    _sec "Server"
    _kv "System" "${C_DIM}${os}${CR} ${C_BORDER}·${CR} ${C_DIM}PHP ${php_v}${CR}"
    _kv "Services" "${apache_d}  ${mysql_d}"
    _kv "Address" "${C_DIM}${ip}${CR}"
}

resources_section() {
    local mem_t mem_u mem_p disk_u disk_t disk_p load cores up bar
    mem_t=$(free -m 2>/dev/null | awk '/^Mem:/{print $2}')
    mem_u=$(free -m 2>/dev/null | awk '/^Mem:/{print $3}')
    if [ -n "$mem_t" ] && [ "$mem_t" -gt 0 ] 2>/dev/null; then mem_p=$(( mem_u * 100 / mem_t )); else mem_p=0; fi
    disk_u=$(df -h / 2>/dev/null | awk 'NR==2{print $3}')
    disk_t=$(df -h / 2>/dev/null | awk 'NR==2{print $2}')
    disk_p=$(df -h / 2>/dev/null | awk 'NR==2{gsub(/%/,"",$5); print $5}')
    load=$(awk '{print $1" "$2" "$3}' /proc/loadavg 2>/dev/null)
    cores=$(nproc 2>/dev/null)
    up=$(uptime -p 2>/dev/null | sed 's/^up //; s/ hours\?/h/; s/ minutes\?/m/; s/ days\?/d/; s/,//g')
    [ -z "$up" ] && up="n/a"
    # a 10-cell meter, coloured by pressure
    _meter() {
        local pct="$1" filled i out="" col
        [ -z "$pct" ] && pct=0
        filled=$(( pct / 10 ))
        if   [ "$pct" -ge 90 ]; then col="$C_BAD"
        elif [ "$pct" -ge 70 ]; then col="$C_WARN"
        else col="$C_OK"; fi
        for ((i=0;i<10;i++)); do
            if [ "$i" -lt "$filled" ]; then out+="█"; else out+="░"; fi
        done
        printf "${col}%s${CR}" "$out"
    }
    _kv "Memory" "$(_meter "$mem_p") ${C_DIM}${mem_p}%  ${mem_u}/${mem_t} MB${CR}"
    _kv "Disk" "$(_meter "$disk_p") ${C_DIM}${disk_p}%  ${disk_u}/${disk_t}${CR}"
    local corelbl="cores"; [ "$cores" = "1" ] && corelbl="core"
    _kv "Load" "${C_DIM}${load}${CR} ${C_BORDER}·${CR} ${C_DIM}${cores} ${corelbl}${CR} ${C_BORDER}·${CR} ${C_DIM}up ${up}${CR}"
}

# One block per bot, numbered and named when there are two or more. A
# single bot (or a server whose registry does not exist yet) gets the one
# "Bot" block it always had. Read-only: never installs jq from here.
bots_dashboard() {
    local rows="" count dir name stopped i=1
    if [ -f "$BOTS_REGISTRY" ] && command -v jq >/dev/null 2>&1; then
        rows=$(jq -r '.[] | "\(.dir)\t\(.name)\t\(.stopped // false)"' "$BOTS_REGISTRY" 2>/dev/null)
    fi
    count=$(printf '%s\n' "$rows" | grep -c .)
    if [ "$count" -lt 2 ]; then
        dir="$BOT_DIR_DEFAULT"; stopped="false"
        if [ "$count" = "1" ]; then
            dir=$(printf '%s' "$rows" | cut -f1)
            stopped=$(printf '%s' "$rows" | cut -f3)
        fi
        _bot_dashboard_block "$dir" "Bot" "$stopped"
        return
    fi
    while IFS=$'\t' read -r dir name stopped; do
        _bot_dashboard_block "$dir" "Bot $i · $name" "$stopped"
        i=$((i + 1))
    done <<< "$rows"
}
# A stopped bot's webhook belongs to wherever it runs now, so asking Telegram
# about it here would only describe the other server.
_bot_dashboard_block() {
    local dir="$1" title="$2" stopped="$3"
    if [ "$stopped" = "true" ]; then
        version_section "$dir" "$title · ⏸ stopped"
        bot_section "$dir"
        _kv "Webhook" "$(_dot none) ${C_DIM}stopped on this server · option 11 starts it again${CR}"
        return
    fi
    version_section "$dir" "$title"
    bot_section "$dir"
    webhook_section "$dir"
}

function show_logo() {
    clear 2>/dev/null || true
    banner
    bots_dashboard
    system_section
    resources_section
}

# certbot's Apache plugin answers the Let's Encrypt challenge through the
# running Apache. The first bot's install never pulled it in (it issues in
# standalone mode, before Apache is set up), so it is added on first need.
ssl_apache_plugin_ensure() {
    certbot plugins 2>/dev/null | grep -qi apache && return 0
    apt-get install -y python3-certbot-apache >/dev/null 2>&1
    certbot plugins 2>/dev/null | grep -qi apache
}

# Make DOMAIN's certificate renew by itself. certbot renews a certificate the
# way it was issued, and the first bot's is issued in standalone mode -
# certbot binding port 80 itself, which Apache holds from then on - so the
# twice-daily certbot.timer run fails on it and the certificate simply runs
# out after 90 days, taking the webhook with it. Switched to the Apache
# plugin (the way every additional bot is issued) it renews through the
# running Apache instead. Silent when there is nothing to switch; returns 1
# only when it had to and could not.
ssl_renewal_ensure() {
    local conf="/etc/letsencrypt/renewal/${1}.conf"
    [ -n "$1" ] && [ -f "$conf" ] || return 0
    grep -qE '^authenticator *= *standalone *$' "$conf" || return 0
    if ssl_apache_plugin_ensure; then
        sed -i -E 's/^authenticator *= *standalone *$/authenticator = apache/' "$conf"
        printf "    ${C_OK}✔${CR} ${C_DIM}SSL for %s now renews by itself (through Apache)${CR}\n" "$1"
        return 0
    fi
    printf "    ${C_WARN}!${CR} ${C_WARN}Could not install certbot's Apache plugin - SSL for %s will not renew by itself.${CR}\n" "$1"
    return 1
}

# Renew (or issue) the SSL certificate for the bot's domain.
function renew_ssl() {
    clear 2>/dev/null || true
    banner
    _sec "Renew SSL certificate"

    # 1) Detect the bot domain: the registry (name + domain, prompts only
    #    when more than one bot exists), then config.php, then saved state
    local domain="" name=""
    if pick_bot_instance "renew the certificate for"; then
        domain="$PICKED_DOMAIN"
        name="$PICKED_NAME"
        [ "$domain" = "null" ] && domain=""
    elif [ "$(bots_registry_count)" != "0" ]; then
        # [0] in the bot list is "back" - not "renew the first bot instead"
        show_menu; return 0
    fi
    if [ -z "$domain" ]; then
        local cfg="/var/www/html/Ciro/config.php"
        [ -f "$cfg" ] && domain=$(hosts_domain "$(grep -E "\\\$domainhosts" "$cfg" 2>/dev/null | head -1 | cut -d"'" -f2)")
    fi
    [ -z "$domain" ] && domain="$(state_get DOMAIN)"
    if [ -z "$domain" ]; then
        printf "  ${C_PROMPT}❯${CR} Enter the bot domain: "
        read -r domain
    fi
    if [ -z "$domain" ]; then
        echo -e "  ${C_BAD}●${CR} ${C_BAD}No domain found. Aborting.${CR}"
        sleep 1; show_menu; return 1
    fi
    [ -n "$name" ] && _kv "Bot" "${C_KEY}${name}${CR}"
    _kv "Domain" "${C_KEY}${domain}${CR}"

    if ! command -v certbot >/dev/null 2>&1; then
        echo -e "  ${C_BAD}●${CR} ${C_BAD}certbot is not installed. Install Ciro first.${CR}"
        sleep 1; show_menu; return 1
    fi

    # this bot's certificate, or a new domain for it
    if [ -n "${PICKED_DIR:-}" ] && [ -f "$PICKED_DIR/config.php" ]; then
        echo ""
        printf "    ${C_KEY}[1]${CR} ${C_TXT}Renew the certificate of %s${CR}\n" "$domain"
        printf "    ${C_KEY}[2]${CR} ${C_TXT}Change this bot's domain${CR} ${C_DIM}(a new domain and certificate)${CR}\n"
        printf "    ${C_KEY}[0]${CR} ${C_TXT}Back to menu${CR}\n"
        printf "  ${C_PROMPT}❯${CR} Choose ${C_DIM}[1]${CR}: "
        local _ssl_what; read -r _ssl_what
        case "$_ssl_what" in
            0) show_menu; return 0 ;;
            2) change_bot_domain "$PICKED_DIR" "${name:-Bot}" "$domain"; return $? ;;
        esac
        echo ""
    fi

    # Show current expiry, if a certificate already exists
    local certfile="/etc/letsencrypt/live/${domain}/cert.pem"
    if [ -f "$certfile" ]; then
        local exp
        exp=$(openssl x509 -enddate -noout -in "$certfile" 2>/dev/null | cut -d= -f2)
        [ -n "$exp" ] && _kv "Expires" "${C_DIM}${exp}${CR}"
    else
        echo -e "  ${C_WARN}!${CR} ${C_WARN}No existing certificate found - a new one will be issued.${CR}"
    fi
    echo ""

    # 2) Optional force (Let's Encrypt normally renews only within ~30 days of expiry)
    printf "  ${C_PROMPT}❯${CR} Force renewal now even if not near expiry? ${C_DIM}[y/N]${CR}: "
    read -r _force
    local force_flag=""
    [[ "$_force" =~ ^[Yy]$ ]] && force_flag="--force-renewal"
    echo ""

    # Use the apache authenticator so it works while Apache is running (no downtime).
    # certonly updates the existing cert lineage in place; Apache already points at it.
    if ! certbot plugins 2>/dev/null | grep -qi apache; then
        run_step "Installing Apache certbot plugin" "apt-get install -y python3-certbot-apache" \
            || { show_step_error; echo -e "\n  ${C_BAD}●${CR} ${C_BAD}certbot's Apache plugin could not be installed. Nothing was changed.${CR}"; echo ""; printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "; read -r _; show_menu; return 1; }
    fi
    # and from now on renew the same way by itself
    ssl_renewal_ensure "$domain"
    run_step "Renewing certificate for ${domain}" \
        "certbot certonly --apache --non-interactive --agree-tos --register-unsafely-without-email --keep-until-expiring --cert-name '${domain}' -d '${domain}' ${force_flag}" \
        || { show_step_error; echo -e "\n  ${C_BAD}●${CR} ${C_BAD}Renewal failed. See the details above.${CR}"; echo ""; printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "; read -r _; show_menu; return 1; }

    run_step "Reloading Apache" "systemctl reload apache2 2>/dev/null || systemctl restart apache2"

    _sec "Done"
    [ -n "$name" ] && _kv "Bot" "${C_KEY}${name}${CR}"
    _kv "Domain" "${C_KEY}${domain}${CR}"
    if [ -f "$certfile" ]; then
        local newexp
        newexp=$(openssl x509 -enddate -noout -in "$certfile" 2>/dev/null | cut -d= -f2)
        [ -n "$newexp" ] && _kv "Valid until" "${C_OK}${newexp}${CR}"
    fi
    # renewing by itself needs the timer running AND a method that works
    # while Apache is up (a snap certbot brings its own timer)
    systemctl is-active --quiet certbot.timer 2>/dev/null \
        || systemctl is-active --quiet snap.certbot.renew.timer 2>/dev/null \
        || systemctl enable --now certbot.timer >/dev/null 2>&1
    if { systemctl is-active --quiet certbot.timer 2>/dev/null || systemctl is-active --quiet snap.certbot.renew.timer 2>/dev/null; } \
        && ! grep -qE '^authenticator *= *standalone *$' "/etc/letsencrypt/renewal/${domain}.conf" 2>/dev/null; then
        _kv "Auto-renew" "${C_OK}on${CR} ${C_DIM}· checked twice a day, renewed 30 days before expiry${CR}"
    else
        _kv "Auto-renew" "${C_BAD}off${CR} ${C_DIM}· run this option again before the date above${CR}"
    fi
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

# ── A bot's domain, changed ───────────────────────────────────
# The bot in DIR answers on a new domain: Apache's sites for it (made from
# the bot's own, so anything set in them stays), a certificate for it, its
# config.php and registry entry, phpMyAdmin's own-port site, its scheduled
# jobs, its webhook and its agents' bots' webhooks. The old domain keeps
# answering until the new one has its certificate; Apache is put back as it
# was if it refuses the new sites. Every other bot is left as it is.
change_bot_domain() {
    local dir="$1" name="$2" old="$3"
    local cfg="$dir/config.php" sa="/etc/apache2/sites-available"
    local hosts token port newd newhosts k rc other
    hosts=$(grep '^\$domainhosts' "$cfg" | head -1 | cut -d"'" -f2)
    token=$(grep '^\$APIKEY' "$cfg" | head -1 | cut -d"'" -f2)
    [ -z "$old" ] && old=$(hosts_domain "$hosts")
    if [ -z "$old" ] || [ -z "$dir" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}Could not read this bot's domain from %s.${CR}\n" "$cfg"
        sleep 2; show_menu; return 1
    fi
    port=$(bots_registry_bot_port "$dir"); [ -z "$port" ] && port=$(hosts_port "$hosts")
    _cd_back() {
        echo ""
        printf "    ${C_BAD}●${CR} ${C_BAD}%s${CR}\n" "$1"
        [ -n "${2:-}" ] && printf "    ${C_DIM}%s${CR}\n" "$2"
        echo ""
        printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
        read -r _
        show_menu
    }

    echo ""
    _sec "New domain for ${name}"
    _kv "Now" "${C_KEY}${old}${CR} ${C_DIM}· ${dir}${CR}"
    echo ""
    # 1) the new domain: a real one, and nobody else's here
    while :; do
        printf "  ${C_PROMPT}❯${CR} New domain ${C_DIM}[0 = back]${CR}: "
        read -r newd
        newd=$(printf '%s' "$newd" | tr '[:upper:]' '[:lower:]' | sed -E 's#^https?://##; s#[/:].*$##; s/^[[:space:]]+//; s/[[:space:]]+$//')
        if [ -z "$newd" ] || [ "$newd" = "0" ]; then show_menu; return 0; fi
        if ! validate_domain "$newd"; then
            printf "    ${C_FAINT_BAD}That is not a domain (like bot.example.com).${CR}\n"; continue
        fi
        if [ "$newd" = "$old" ]; then
            printf "    ${C_FAINT_BAD}That is this bot's domain now.${CR}\n"; continue
        fi
        if jq -e --arg d "$newd" --arg o "$dir" 'any(.[]; .domain == $d and .dir != $o)' "$BOTS_REGISTRY" >/dev/null 2>&1; then
            printf "    ${C_FAINT_BAD}Another bot on this server uses %s.${CR}\n" "$newd"; continue
        fi
        other=$(grep -lsE "^[[:space:]]*Server(Name|Alias)([[:space:]]+[^[:space:]]+)*[[:space:]]+${newd//./\\.}(:[0-9]+)?([[:space:]]|\$)" /etc/apache2/sites-enabled/* 2>/dev/null \
            | grep -v -e "/${old}\.conf\$" -e "/${old}-ssl\.conf\$" -e "/${old}-pma\.conf\$" | head -1)
        if [ -n "$other" ]; then
            printf "    ${C_FAINT_BAD}Apache here already serves %s (%s).${CR}\n" "$newd" "$(basename "$other")"; continue
        fi
        break
    done
    newhosts=$(hosts_of "$newd" "$port")

    # 2) it has to point here, or Let's Encrypt says no
    echo ""
    printf "    Point the A record of ${C_KEY}%s${CR} at ${C_KEY}%s${CR} ${C_DIM}(on Cloudflare: \"DNS only\")${CR}\n" "$newd" "$(get_server_ip)"
    while :; do
        domain_points_here "$newd"; rc=$?
        if [ "$rc" -eq 0 ]; then
            printf "    ${C_OK}●${CR} ${C_OK}%s points at this server${CR}\n" "$newd"; break
        fi
        if [ "$rc" -eq 2 ]; then
            printf "    ${C_FAINT_BAD}%s does not resolve yet - DNS can take a few minutes.${CR}\n" "$newd"
        else
            printf "    ${C_FAINT_BAD}%s points at %s, not here.${CR}\n" "$newd" "$(getent ahostsv4 "$newd" 2>/dev/null | awk '{print $1; exit}')"
        fi
        printf "  ${C_PROMPT}❯${CR} ${C_DIM}Enter = check again, c = continue anyway, 0 = back${CR}: "
        read -r k
        [ "$k" = "0" ] && { show_menu; return 0; }
        [[ "$k" =~ ^[Cc]$ ]] && break
    done

    echo ""
    _kv "Bot" "${C_KEY}${name}${CR}"
    _kv "Domain" "${C_DIM}${old}${CR} ${C_DIM}→${CR} ${C_KEY}${newd}${CR}"
    _kv "Address" "${C_DIM}https://${newhosts}${CR}"
    printf "    ${C_DIM}The old domain keeps working until the new one has its certificate.${CR}\n"
    printf "    ${C_DIM}Then the bot, its webhook, its scheduled jobs and its agents' bots${CR}\n"
    printf "    ${C_DIM}move to the new domain. Links sent with the old domain stop working.${CR}\n"
    printf "  ${C_PROMPT}❯${CR} Change it now? ${C_DIM}[y/N]${CR}: "
    read -r k
    [[ "$k" =~ ^[Yy]$ ]] || { printf "\n    ${C_DIM}Nothing was changed.${CR}\n"; sleep 1; show_menu; return 0; }
    echo ""

    # what is there now, to put back if Apache says no
    local abak; abak=$(mktemp -d)
    cp -a /etc/apache2/sites-available /etc/apache2/sites-enabled /etc/apache2/conf-available /etc/apache2/conf-enabled "$abak"/
    cp -a /etc/apache2/ports.conf "$abak/" 2>/dev/null
    cp -a "$cfg" "$abak/config.php"
    _cd_undo() {
        _pma_restore "${abak:?}"
        cp -a "${abak:?}/config.php" "$cfg"
        systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1
        rm -rf "${abak:?}"
    }
    local oe; oe=$(printf '%s' "$old" | sed 's/[.[\*^$/]/\\&/g')

    # 3) the new domain's plain-HTTP site, a copy of the bot's own - the old
    #    one stays on, so the bot keeps answering meanwhile
    if [ -f "$sa/${old}.conf" ]; then
        sed -E "s/\b${oe}\b/${newd}/g" "$sa/${old}.conf" > "$sa/${newd}.conf"
    else
        tee "$sa/${newd}.conf" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName $newd
    DocumentRoot $dir
    <Directory $dir>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/${newd}-error.log
    CustomLog \${APACHE_LOG_DIR}/${newd}-access.log combined
</VirtualHost>
EOF
    fi
    a2ensite "${newd}.conf" >/dev/null 2>&1
    if ! apache2ctl configtest >/dev/null 2>&1 || ! { systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1; }; then
        _cd_undo
        _cd_back "Apache would not take a site for ${newd} - nothing was changed."
        return 1
    fi

    # 4) its certificate (one that is already there is used)
    if [ ! -f "/etc/letsencrypt/live/${newd}/fullchain.pem" ]; then
        if ! certbot plugins 2>/dev/null | grep -qi apache; then
            run_step "Installing Apache certbot plugin" "apt-get install -y python3-certbot-apache" || show_step_error
        fi
        if ! run_step "Getting a certificate for ${newd}" \
            "certbot certonly --apache --non-interactive --agree-tos --register-unsafely-without-email --preferred-challenges http --cert-name '${newd}' -d '${newd}'"; then
            show_step_error
            _cd_undo
            _cd_back "Let's Encrypt did not issue a certificate for ${newd} - nothing was changed." \
                "Does ${newd} point at this server, and is port 80 open? The bot still answers on ${old}."
            return 1
        fi
    fi

    # 5) the HTTPS site on the new domain; the old domain's sites go off
    if [ -f "$sa/${old}-ssl.conf" ]; then
        sed -E "s/\b${oe}\b/${newd}/g" "$sa/${old}-ssl.conf" > "$sa/${newd}-ssl.conf"
    else
        tee "$sa/${newd}-ssl.conf" >/dev/null <<EOF
<VirtualHost *:${port}>
    ServerName $newd
    DocumentRoot $dir
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$newd/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$newd/privkey.pem
    <Directory $dir>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    Include /etc/apache2/conf-available/phpmyadmin.conf
    ErrorLog \${APACHE_LOG_DIR}/${newd}-error.log
    CustomLog \${APACHE_LOG_DIR}/${newd}-access.log combined
</VirtualHost>
EOF
    fi
    a2ensite "${newd}-ssl.conf" >/dev/null 2>&1
    for k in "${old}.conf" "${old}-ssl.conf" "${old}-pma.conf"; do
        a2dissite "$k" >/dev/null 2>&1
    done
    local aout
    if ! aout=$(apache2ctl configtest 2>&1); then
        _cd_undo
        printf '%s\n' "$aout" | tail -n 3 | sed 's/^/      /'
        _cd_back "Apache refused the new site - everything was put back as it was."
        return 1
    fi
    systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1
    sleep 1
    if ! systemctl is-active --quiet apache2; then
        _cd_undo
        systemctl restart apache2 >/dev/null 2>&1
        _cd_back "Apache would not run with the new site - everything was put back as it was."
        return 1
    fi
    printf "    ${C_OK}✔${CR} ${C_DIM}Apache serves the bot on %s${CR}\n" "$newd"

    # 6) the bot itself: its address, its registry entry, phpMyAdmin's own-port site
    sed -i "s#^\(\$domainhosts = '\)[^']*'#\1${newhosts}'#" "$cfg"
    chown www-data:www-data "$cfg" 2>/dev/null
    bots_registry_add "$name" "$dir" "$newd"
    rm -f "${sa:?}/${old:?}.conf" "${sa:?}/${old:?}-ssl.conf" "${sa:?}/${old:?}-pma.conf"
    local pma; pma=$(bots_registry_pma_port "$dir")
    if [ -n "$pma" ] && [ "$pma" != "$port" ]; then
        pma_sync_all >/dev/null 2>&1 || printf "    ${C_WARN}!${CR} ${C_WARN}phpMyAdmin's port could not be set up again - set it from menu 9.${CR}\n"
    fi
    rm -rf "${abak:?}"

    # 7) scheduled jobs: the old address out, the new one in
    _bot_crons_remove "$hosts"
    local crons_ok=0
    cat > /tmp/ciro_cron_setup.php <<PHPEOF
<?php
chdir('$dir');
require 'config.php';
require 'botapi.php';
require 'panels.php';
require 'function.php';
require_once 'jdf.php';
require 'vendor/autoload.php';
if (!function_exists('activecron')) { exit(3); }
activecron();
PHPEOF
    chmod 644 /tmp/ciro_cron_setup.php
    ( cd "$dir" && runuser -u www-data -- php /tmp/ciro_cron_setup.php ) >/dev/null 2>&1 && crons_ok=1
    rm -f /tmp/ciro_cron_setup.php

    # 8) Telegram: the bot, then every agent's bot
    local secret hook hook_ok=0 agents=0 agents_ok=0 dbhost dbname dbuser dbpass _tok _uid _uname
    secret=$(_bot_webhook_secret "$dir")
    hook=$(curl -s --max-time 20 -F "url=https://${newhosts}/index.php" -F "secret_token=${secret}" \
        "https://api.telegram.org/bot${token}/setWebhook" 2>/dev/null)
    printf '%s' "$hook" | grep -q '"ok":true' && hook_ok=1
    dbhost=$(grep '^\$dbhost' "$cfg" | head -1 | cut -d"'" -f2); [ -z "$dbhost" ] && dbhost=localhost
    dbname=$(grep '^\$dbname' "$cfg" | head -1 | cut -d"'" -f2)
    dbuser=$(grep '^\$usernamedb' "$cfg" | head -1 | cut -d"'" -f2)
    dbpass=$(grep '^\$passworddb' "$cfg" | head -1 | cut -d"'" -f2)
    while IFS=$'\t' read -r _tok _uid _uname; do
        [ -n "$_tok" ] && [ -d "$dir/vpnbot/${_uid}${_uname}" ] || continue
        agents=$((agents + 1))
        curl -s --max-time 20 "https://api.telegram.org/bot${_tok}/setWebhook?url=https://${newhosts}/vpnbot/${_uid}${_uname}/index.php" \
            | grep -q '"ok":true' && agents_ok=$((agents_ok + 1))
    done < <(mysql -N -B -h "$dbhost" -u "$dbuser" -p"$dbpass" "$dbname" -e "SELECT bot_token, id_user, username FROM botsaz;" 2>/dev/null)

    # 9) renewing by itself; the old certificate goes once nothing uses it
    ssl_renewal_ensure "$newd" >/dev/null
    local old_cert="kept"
    if [ -d "/etc/letsencrypt/live/${old}" ] && ! grep -rqsF "/live/${old}/" /etc/apache2/sites-enabled/ /etc/apache2/sites-available/; then
        certbot delete --non-interactive --cert-name "$old" >/dev/null 2>&1 && old_cert="removed"
    fi

    _sec "Domain changed"
    _kv "Bot" "${C_KEY}${name}${CR}"
    _kv "Address" "${C_OK}https://${newhosts}${CR}"
    if [ "$hook_ok" -eq 1 ]; then
        _kv "Webhook" "$(_dot ok) ${C_OK}set${CR}"
    else
        _kv "Webhook" "$(_dot bad) ${C_BAD}not set${CR} ${C_DIM}· run option 2 (Update) once to retry${CR}"
    fi
    [ "$agents" -gt 0 ] && _kv "Agents' bots" "${C_DIM}${agents_ok} of ${agents} moved${CR}"
    if [ "$crons_ok" -eq 1 ]; then
        _kv "Jobs" "$(_dot ok) ${C_OK}on the new domain${CR}"
    else
        _kv "Jobs" "$(_dot warn) ${C_WARN}open the bot's admin panel once to set them up${CR}"
    fi
    if [ -f "/etc/letsencrypt/live/${newd}/cert.pem" ]; then
        _kv "Valid until" "${C_DIM}$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/${newd}/cert.pem" 2>/dev/null | cut -d= -f2)${CR}"
    fi
    [ "$old_cert" = "removed" ] && _kv "Old certificate" "${C_DIM}${old} · removed${CR}"
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

function backup_bot() {
    clear 2>/dev/null || true
    banner
    _sec "Backup Database"

    if ! pick_bot_instance "back up"; then
        echo ""
        printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
        read -r _
        show_menu
        return 1
    fi
    CONFIG_PATH="$PICKED_DIR/config.php"
    if [ ! -f "$CONFIG_PATH" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}Ciro is not installed. config.php not found.${CR}\n"
        echo ""
        printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
        read -r _
        show_menu
        return 1
    fi

    local dbhost dbname dbuser dbpass bot_token admin_id
    dbhost=$(grep '^\$dbhost' "$CONFIG_PATH" | cut -d"'" -f2)
    dbname=$(grep '^\$dbname' "$CONFIG_PATH" | cut -d"'" -f2)
    dbuser=$(grep '^\$usernamedb' "$CONFIG_PATH" | cut -d"'" -f2)
    dbpass=$(grep '^\$passworddb' "$CONFIG_PATH" | cut -d"'" -f2)
    bot_token=$(grep '^\$APIKEY' "$CONFIG_PATH" | cut -d"'" -f2)
    admin_id=$(grep '^\$adminnumber' "$CONFIG_PATH" | cut -d"'" -f2)
    [ -z "$dbhost" ] && dbhost="localhost"

    if [ -z "$dbname" ] || [ -z "$dbuser" ] || [ -z "$dbpass" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}Could not read database credentials from config.php${CR}\n"
        echo ""
        printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
        read -r _
        show_menu
        return 1
    fi

    _kv "Database" "${C_DIM}${dbname}${CR}"
    _kv "DB User" "${C_DIM}${dbuser}${CR}"
    _kv "DB Host" "${C_DIM}${dbhost}${CR}"
    echo ""

    local backup_date
    backup_date=$(date +"%Y-%m-%d_%H-%M-%S")
    local backup_file="/root/ciro_backup_${backup_date}.sql"

    run_step "Exporting database (${dbname})" \
        "mysqldump -h '$dbhost' -u '$dbuser' -p'$dbpass' --no-tablespaces --ssl-mode=DISABLED '$dbname' > '$backup_file'" \
        || { show_step_error; echo -e "\n  ${C_BAD}●${CR} ${C_BAD}Backup failed. See details above.${CR}"; echo ""; printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "; read -r _; show_menu; return 1; }

    local file_size
    file_size=$(du -h "$backup_file" 2>/dev/null | awk '{print $1}')
    _kv "File" "${C_OK}${backup_file}${CR}"
    _kv "Size" "${C_DIM}${file_size}${CR}"

    if [ -n "$bot_token" ] && [ -n "$admin_id" ]; then
        echo ""
        local send_result
        send_result=$(curl -s -o /dev/null -w "%{http_code}" \
            -F "chat_id=${admin_id}" \
            -F "document=@${backup_file}" \
            -F "caption=📦 Ciro DB Backup (${backup_date})" \
            "https://api.telegram.org/bot${bot_token}/sendDocument" 2>/dev/null)
        if [ "$send_result" = "200" ]; then
            _kv "Telegram" "$(_dot ok) ${C_OK}Backup sent to admin chat (${admin_id})${CR}"
        else
            _kv "Telegram" "$(_dot bad) ${C_BAD}Failed to send (HTTP ${send_result})${CR}"
            printf "    ${C_DIM}Make sure the bot token and admin chat ID are correct.${CR}\n"
        fi
    else
        echo ""
        printf "    ${C_WARN}!${CR} ${C_WARN}Bot token or admin ID not found in config - skipping Telegram send.${CR}\n"
    fi

    echo ""
    printf "    ${C_OK}✔${CR} ${C_OK}Backup saved to:${CR} ${C_KEY}${backup_file}${CR}\n"
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

# ── Restore ───────────────────────────────────────────────────
_is_zip() { [ "$(head -c 4 "$1" 2>/dev/null | od -An -tx1 | tr -d ' \n')" = "504b0304" ]; }

# Does FILE look like an SQL dump? (a dump header or statements in its first MB)
_restore_is_sql() {
    head -c 1048576 "$1" 2>/dev/null \
        | grep -qaiE '^(-- MySQL dump|-- MariaDB dump|-- phpMyAdmin SQL Dump|CREATE TABLE|INSERT INTO|DROP TABLE)'
}

# The database a dump (on stdin) was taken from, from its mysqldump or
# phpMyAdmin header; empty when the header does not say.
_sql_source_db() {
    head -n 40 | grep -m1 -aoE 'Database: `?[A-Za-z0-9_]+' | sed -E 's/Database: `?//'
}

# The name of the bot on this server that uses DBNAME, or DBNAME itself
_db_owner_label() {
    local d n
    while IFS=$'\t' read -r d n; do
        if [ "$(grep '^\$dbname' "$d/config.php" 2>/dev/null | cut -d"'" -f2)" = "$1" ]; then
            echo "$n"; return
        fi
    done < <(jq -r '.[] | "\(.dir)\t\(.name)"' "$BOTS_REGISTRY" 2>/dev/null)
    echo "$1"
}

# The folder inside a zip that holds a bot - index.php and function.php side
# by side; "" when they sit at its top, as in the bot's own botfolder_*.zip.
# Fails when the zip holds no bot.
_restore_bot_prefix() {
    local list fn p
    list=$(unzip -Z1 "$1" 2>/dev/null)
    fn=$(printf '%s\n' "$list" | grep -E '(^|/)function\.php$' | awk -F/ '{ print NF "\t" $0 }' | sort -n | head -1 | cut -f2-)
    [ -n "$fn" ] || return 1
    p="${fn%function.php}"
    printf '%s\n' "$list" | grep -qxF "${p}index.php" || return 1
    printf '%s' "$p"
}

# A zip of the bot's folder rather than of its database
_restore_is_botfolder() {
    _is_zip "$1" && ! unzip -Z1 "$1" 2>/dev/null | grep -qiE '\.sql$' && _restore_bot_prefix "$1" >/dev/null
}

# One short note per listed file: whose data it is, or why it cannot be used
_restore_file_note() {
    local f="$1" entry src prefix
    if _is_zip "$f"; then
        entry=$(unzip -Z1 "$f" 2>/dev/null | grep -iE '\.sql$' | head -1)
        if [ -z "$entry" ]; then
            prefix=$(_restore_bot_prefix "$f") || { echo "zip · not a backup"; return; }
            if unzip -Z -v "$f" "${prefix}index.php" 2>/dev/null | grep -q 'file security status:[[:space:]]*encrypted'; then
                echo "bot folder · password"; return
            fi
            src=$(unzip -p "$f" "${prefix}config.php" 2>/dev/null | grep '^\$dbname' | cut -d"'" -f2)
            if [ -n "$src" ]; then echo "bot folder · from $(_db_owner_label "$src")"; else echo "bot folder"; fi
            return
        fi
        if unzip -Z -v "$f" "$entry" 2>/dev/null | grep -q 'file security status:[[:space:]]*encrypted'; then
            echo "zip · password"; return
        fi
        src=$(unzip -p "$f" "$entry" 2>/dev/null | _sql_source_db)
    else
        src=$(_sql_source_db < "$f")
    fi
    [ -n "$src" ] && echo "from $(_db_owner_label "$src")"
}

# ── Opening a backup zip ──────────────────────────────────────
# Info-ZIP's unzip reads only the classic (ZipCrypto) password. A zip locked
# with AES - 7-Zip, WinZip, PHP's ZipArchive - gets "unsupported compression
# method 99" from it whatever the password is, and the prompt used to call
# that "wrong password, or the zip is damaged". PHP's libzip reads both kinds,
# so it takes over whenever unzip cannot, and the reason is told as it is.
ZIP_PW=""      # the password of the zip being restored, asked once
ZIP_WHY=""     # why the last attempt failed, in the tool's own words
_ZIP_ONE_PHP='
if (!class_exists("ZipArchive")) { fwrite(STDERR, "PHP has no zip extension"); exit(6); }
$z = new ZipArchive();
if ($z->open($argv[1], ZipArchive::RDONLY) !== true) { fwrite(STDERR, "cannot open the zip"); exit(4); }
$pw = getenv("ZPW");
if ($pw !== false && $pw !== "") { $z->setPassword($pw); }
$s = $z->getStream($argv[2]);
if (!$s) {
    $m = $z->getStatusString();
    fwrite(STDERR, $m);
    exit(stripos($m, "password") !== false ? 3 : (stripos($m, "not supported") !== false ? 5 : 4));
}
$o = fopen($argv[3], "wb");
$n = stream_copy_to_stream($s, $o);
fclose($o); fclose($s);
exit($n > 0 ? 0 : 4);
'
_ZIP_ALL_PHP='
if (!class_exists("ZipArchive")) { fwrite(STDERR, "PHP has no zip extension"); exit(6); }
$z = new ZipArchive();
if ($z->open($argv[1], ZipArchive::RDONLY) !== true) { fwrite(STDERR, "cannot open the zip"); exit(4); }
$pw = getenv("ZPW");
if ($pw !== false && $pw !== "") { $z->setPassword($pw); }
$want = [];
if (count($argv) > 3) {
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = $z->getNameIndex($i);
        foreach (array_slice($argv, 3) as $g) { if (fnmatch($g, $n)) { $want[] = $n; break; } }
    }
    if (!$want) { exit(0); }
}
$ok = $want ? $z->extractTo($argv[2], $want) : $z->extractTo($argv[2]);
if (!$ok) {
    $m = $z->getStatusString();
    fwrite(STDERR, $m);
    exit(stripos($m, "password") !== false ? 3 : (stripos($m, "not supported") !== false ? 5 : 4));
}
exit(0);
'

# _zip_extract ZIP ENTRY PASSWORD OUTFILE      (PASSWORD "" = none)
# Returns 0 done, 3 wrong password, 4 damaged or unreadable, 5 a protection
# this server cannot open. ZIP_WHY says why.
_zip_extract() {
    local zip="$1" entry="$2" pw="$3" out="$4" pat err rc uw
    ZIP_WHY=""
    err=$(mktemp)
    # unzip reads the entry name as a pattern: [ ] * ? in a name must be escaped
    pat=$(printf '%s' "$entry" | sed 's/[][*?\\]/\\&/g')
    # a dummy password is ignored by an unprotected zip, and unzip never stops
    # to ask on the terminal
    unzip -p -P "${pw:-ciro-no-password}" "$zip" "$pat" > "$out" 2> "$err"; rc=$?
    if [ "$rc" -le 1 ] && [ -s "$out" ]; then rm -f "$err"; return 0; fi
    uw=$(head -n 3 "$err" | tr '\n' ' ' | sed -E 's/  +/ /g; s/^ //; s/ $//')
    ZIP_WHY="$uw"
    case "$uw" in *"incorrect password"*) rm -f "$err"; return 3 ;; esac
    # unzip could not (AES, an unusual zip): libzip tries
    ZPW="$pw" php -d display_errors=0 -r "$_ZIP_ONE_PHP" -- "$zip" "$entry" "$out" 2> "$err"; rc=$?
    case "$rc" in
        0) rm -f "$err"; return 0 ;;
        3) rm -f "$err"; return 3 ;;
    esac
    ZIP_WHY="${uw:+$uw · }$(head -c 200 "$err" | tr '\n' ' ')"
    rm -f "$err"
    case "$ZIP_WHY" in
        *"method 99"*|*"not supported"*|*"nsupported"*|*"no zip extension"*) return 5 ;;
    esac
    return 4
}

# _zip_extract_all ZIP PASSWORD DESTDIR [PATTERN ...]
# The whole zip into DESTDIR, or only the entries matching the patterns.
# Returns 0 done (nothing matching is fine), 3 wrong password, 4 failed.
_zip_extract_all() {
    local zip="$1" pw="$2" dest="$3" err rc
    shift 3
    mkdir -p "$dest"
    err=$(mktemp)
    unzip -q -o -P "${pw:-ciro-no-password}" -d "$dest" "$zip" "$@" >/dev/null 2> "$err"; rc=$?
    # 11: a pattern matched nothing - an optional piece the zip does not have
    if [ "$rc" -le 1 ] || [ "$rc" -eq 11 ]; then rm -f "$err"; return 0; fi
    ZIP_WHY=$(head -n 3 "$err" | tr '\n' ' ' | sed -E 's/  +/ /g')
    case "$ZIP_WHY" in *"incorrect password"*) rm -f "$err"; return 3 ;; esac
    ZPW="$pw" php -d display_errors=0 -r "$_ZIP_ALL_PHP" -- "$zip" "$dest" "$@" 2> "$err"; rc=$?
    ZIP_WHY="${ZIP_WHY:+$ZIP_WHY · }$(head -c 200 "$err" | tr '\n' ' ')"
    rm -f "$err"
    [ "$rc" -eq 0 ] && return 0
    [ "$rc" -eq 3 ] && return 3
    return 4
}

_zip_say_damaged() {
    printf "    ${C_BAD}●${CR} ${C_BAD}The zip cannot be read${CR}${C_DIM}%s${CR}\n" "${ZIP_WHY:+ ($ZIP_WHY)}"
    printf "    ${C_DIM}It is damaged or was cut short while it was copied - copy it to the server again${CR}\n"
    printf "    ${C_DIM}(scp / SFTP in binary mode) and try again. The password is not the problem.${CR}\n"
}

_zip_say_unsupported() {
    printf "    ${C_BAD}●${CR} ${C_BAD}This zip uses a protection this server cannot open${CR}${C_DIM}%s${CR}\n" "${ZIP_WHY:+ ($ZIP_WHY)}"
    printf "    ${C_DIM}Save it again with a normal zip password, or without one, and try again.${CR}\n"
}

# Extract ENTRY of ZIP to OUT. A protected zip asks for its password, kept in
# ZIP_PW so the same zip is never asked twice.
# Returns 0 done, 1 unusable (reason printed), 2 backed out.
_zip_open_entry() {
    local zip="$1" entry="$2" out="$3" rc pw v
    _zip_extract "$zip" "$entry" "$ZIP_PW" "$out"; rc=$?
    case "$rc" in
        0) return 0 ;;
        4) RESTORE_FAIL="damaged"; _zip_say_damaged; return 1 ;;
        5) RESTORE_FAIL="unsupported"; _zip_say_unsupported; return 1 ;;
    esac
    # run for the bot's 📥 import (no terminal): the password it was given is the only one
    if [ -n "$CIRO_NONINTERACTIVE" ]; then
        RESTORE_FAIL="password"
        printf "    ${C_BAD}●${CR} ${C_BAD}That password does not open the zip.${CR}\n"
        return 1
    fi
    [ -z "$ZIP_PW" ] && printf "    ${C_DIM}This zip is password-protected (the password from the bot's backup settings).${CR}\n"
    while true; do
        printf "  ${C_PROMPT}❯${CR} Zip password ${C_DIM}[0 = back]${CR}: "
        read -rs pw; echo ""
        if [ -z "$pw" ] || [ "$pw" = "0" ]; then return 2; fi
        # as typed, then without a stray line end, then without outer spaces
        for v in "$pw" "$(printf '%s' "$pw" | tr -d '\r\n')" "$(printf '%s' "$pw" | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"; do
            [ -n "$v" ] || continue
            _zip_extract "$zip" "$entry" "$v" "$out"; rc=$?
            if [ "$rc" -eq 0 ]; then ZIP_PW="$v"; return 0; fi
            [ "$rc" -eq 3 ] || break
        done
        case "$rc" in
            4) _zip_say_damaged; return 1 ;;
            5) _zip_say_unsupported; return 1 ;;
        esac
        printf "    ${C_BAD}●${CR} ${C_BAD}That password does not open the zip. Try again.${CR}\n"
    done
}

# What a zip holds, from its listing (which needs no password): the database
# dumps (.sql) and the bot folders (index.php beside function.php).
# Sets ZS_SQL and ZS_BOTS (a folder's prefix is "" when it sits at the top).
# Fails when the zip cannot be listed.
ZS_SQL=(); ZS_BOTS=(); ZS_BOT_DB=(); ZS_BOT_VER=()
_zs_scan() {
    local list p q fn keep
    ZS_SQL=(); ZS_BOTS=(); ZS_BOT_DB=(); ZS_BOT_VER=()
    list=$(unzip -Z1 "$1" 2>/dev/null) || return 1
    [ -n "$list" ] || return 1
    while IFS= read -r p; do
        [ -n "$p" ] && ZS_SQL+=("$p")
    done < <(printf '%s\n' "$list" | grep -iE '\.sql$' | grep -viE '(^|/)(vendor|node_modules)/')
    while IFS= read -r fn; do
        p="${fn%function.php}"
        printf '%s\n' "$list" | grep -qxF "${p}index.php" || continue
        case "/$p" in */vendor/*|*/vpnbot/*) continue ;; esac
        keep=1
        for q in "${ZS_BOTS[@]}"; do
            # inside a folder already taken (or the zip's top, which holds everything)
            if [ -z "$q" ] || [ "${p#"$q"}" != "$p" ]; then keep=0; break; fi
        done
        [ "$keep" -eq 1 ] && ZS_BOTS+=("$p")
    done < <(printf '%s\n' "$list" | grep -E '(^|/)function\.php$' | awk -F/ '{ print NF "\t" $0 }' | sort -n | cut -f2-)
    return 0
}

# The database and version each bot folder of the zip belongs to (needs the
# password already known): ZS_BOT_DB, ZS_BOT_VER - empty when it says nothing.
_zs_bot_info() {
    local zip="$1" work="$2" i p
    ZS_BOT_DB=(); ZS_BOT_VER=()
    for i in "${!ZS_BOTS[@]}"; do
        p="${ZS_BOTS[$i]}"
        ZS_BOT_DB[$i]=""; ZS_BOT_VER[$i]=""
        if _zip_extract "$zip" "${p}config.php" "$ZIP_PW" "$work/cfg.tmp"; then
            ZS_BOT_DB[$i]=$(grep '^\$dbname' "$work/cfg.tmp" 2>/dev/null | head -1 | cut -d"'" -f2)
        fi
        if _zip_extract "$zip" "${p}version" "$ZIP_PW" "$work/ver.tmp"; then
            ZS_BOT_VER[$i]=$(tr -cd '[:alnum:]._-' < "$work/ver.tmp" | head -c 40)
        fi
        rm -f "$work/cfg.tmp" "$work/ver.tmp"
    done
}

# Is version A older than B? A bot folder without a version file is the
# original project's, which is older than every version of this one.
_ver_older() {
    [ -z "$1" ] && return 0
    [ "$1" = "$2" ] && return 1
    [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | head -1)" = "$1" ]
}

# What the old bot kept in its own FILES that is data, not code: the agents'
# bots (vpnbot/<id><name>/ - their token and their data) and the API token
# (api/hash.txt). Copied from the unpacked folder $1 into the bot $2 only
# where the bot has nothing of that name - never over something there.
# Sets CARRIED_AGENTS and CARRIED_TOKEN.
_restore_carry_data() {
    local from="$1" dir="$2" a base
    CARRIED_AGENTS=0; CARRIED_TOKEN=0
    for a in "${from}"vpnbot/*/; do
        [ -d "$a" ] || continue
        base=$(basename "$a")
        case "$base" in Default|update) continue ;; esac
        [ -e "$dir/vpnbot/$base" ] && continue
        mkdir -p "$dir/vpnbot"
        cp -a "$a" "$dir/vpnbot/$base" 2>/dev/null && CARRIED_AGENTS=$((CARRIED_AGENTS + 1))
    done
    if [ -f "${from}api/hash.txt" ] && [ ! -e "$dir/api/hash.txt" ]; then
        mkdir -p "$dir/api"
        cp -a "${from}api/hash.txt" "$dir/api/hash.txt" 2>/dev/null && CARRIED_TOKEN=1
    fi
    chown -R www-data:www-data "$dir/vpnbot" "$dir/api" 2>/dev/null
    return 0
}

# Say what is being done with the old folder, then keep its data. $1 the
# unpacked folder, $2 the bot, $3 its version in the zip, $4 the folder's name.
_restore_old_folder() {
    local from="$1" dir="$2" ver="$3" label="$4" cur kind
    cur=$(get_installed_version "$dir")
    if [ -z "$ver" ]; then kind="the original project (no version file)"
    elif _ver_older "$ver" "$cur"; then kind="an older version (${ver})"
    else kind="version ${ver}"; fi
    echo ""
    printf "    ${C_DIM}Bot folder in the zip: %s - %s${CR}\n" "${label:-/}" "$kind"
    printf "    ${C_OK}✔${CR} ${C_OK}Its code is not restored - this bot already runs the newest files${CR}${C_DIM}%s${CR}\n" "${cur:+ ($cur)}"
    _restore_carry_data "$from" "$dir"
    RESTORE_OLD="${ver:-original}"
    if [ "$CARRIED_AGENTS" -gt 0 ] || [ "$CARRIED_TOKEN" -gt 0 ]; then
        printf "    ${C_OK}✔${CR} ${C_OK}Its own data is kept:${CR}${C_DIM}%s%s${CR}\n" \
            "$([ "$CARRIED_AGENTS" -gt 0 ] && echo " ${CARRIED_AGENTS} agent bot(s)")" \
            "$([ "$CARRIED_TOKEN" -gt 0 ] && echo " · the API token")"
    fi
    printf "    ${C_DIM}Its settings come from the database; config.php stays this bot's own.${CR}\n"
}

# Turn the chosen file into an .sql to import: a plain dump is used as it
# is, a zip has its dump extracted into WORK (asking for the password the
# bot's backup settings put on it, if any). A zip with several dumps says which
# database and which bot folder each belongs to. Sets RESTORE_SQL and
# RESTORE_ENTRY. Returns 0 ready, 1 unusable (reason printed), 2 back.
_restore_prepare() {
    local f="$1" work="$2" n pick i m rc db fl
    RESTORE_SQL=""; RESTORE_ENTRY=""; ZIP_PW="${RESTORE_PW:-}"
    ZS_SQL=(); ZS_BOTS=(); ZS_BOT_DB=(); ZS_BOT_VER=()
    if ! _is_zip "$f"; then
        if ! _restore_is_sql "$f"; then
            printf "    ${C_BAD}●${CR} ${C_BAD}This file is not a database backup (.sql or .zip).${CR}\n"
            return 1
        fi
        RESTORE_SQL="$f"; return 0
    fi
    if ! _zs_scan "$f"; then
        ZIP_WHY="its file list cannot be read"; _zip_say_damaged
        return 1
    fi
    n=${#ZS_SQL[@]}
    if [ "$n" -eq 0 ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}This zip holds neither a database dump (.sql) nor a bot folder - it is not a backup.${CR}\n"
        return 1
    fi
    pick=1
    if [ "$n" -eq 1 ]; then
        RESTORE_ENTRY="${ZS_SQL[0]}"
        _zip_open_entry "$f" "$RESTORE_ENTRY" "$work/restore.sql"; rc=$?
        [ "$rc" -ne 0 ] && return "$rc"
    else
        # the password first (on the first dump), then every dump's header so the
        # list can say which database it is and which bot folder it goes with
        _zip_open_entry "$f" "${ZS_SQL[0]}" "$work/hdr_1.sql"; rc=$?
        [ "$rc" -ne 0 ] && return "$rc"
        m=$n; [ "$m" -gt 6 ] && m=6
        for ((i = 2; i <= m; i++)); do
            _zip_extract "$f" "${ZS_SQL[$((i - 1))]}" "$ZIP_PW" "$work/hdr_$i.sql" || rm -f "$work/hdr_$i.sql"
        done
        _zs_bot_info "$f" "$work"
        printf "    ${C_DIM}This zip holds %d database dumps:${CR}\n" "$n"
        for ((i = 1; i <= n; i++)); do
            db=""; fl=""
            [ -f "$work/hdr_$i.sql" ] && db=$(_sql_source_db < "$work/hdr_$i.sql")
            if [ -n "$db" ]; then
                for m in "${!ZS_BOTS[@]}"; do
                    [ "${ZS_BOT_DB[$m]}" = "$db" ] && { fl="${ZS_BOTS[$m]:-/}"; break; }
                done
            fi
            printf "    ${C_KEY}[%d]${CR} ${C_TXT}%s${CR}${C_DIM}%s%s${CR}\n" "$i" "${ZS_SQL[$((i - 1))]}" \
                "${db:+  · database $db}" "${fl:+  · bot folder $fl}"
        done
        if [ -n "$RESTORE_PICK" ]; then
            pick=0
            for ((i = 1; i <= n; i++)); do
                [ "${ZS_SQL[$((i - 1))]}" = "$RESTORE_PICK" ] && pick=$i
            done
            [ "$pick" -gt 0 ] || { RESTORE_FAIL="pick"; return 1; }
        else
            printf "  ${C_PROMPT}❯${CR} Which one? ${C_DIM}[1-%d, 0 = back]${CR}: " "$n"
            read -r pick
        fi
        [[ "$pick" =~ ^[0-9]+$ ]] && [ "$pick" -ge 1 ] && [ "$pick" -le "$n" ] || return 2
        RESTORE_ENTRY="${ZS_SQL[$((pick - 1))]}"
        if [ -f "$work/hdr_$pick.sql" ]; then
            mv -f "$work/hdr_$pick.sql" "$work/restore.sql"
        else
            _zip_open_entry "$f" "$RESTORE_ENTRY" "$work/restore.sql"; rc=$?
            [ "$rc" -ne 0 ] && return "$rc"
        fi
        rm -f "$work"/hdr_*.sql
    fi
    [ "${#ZS_BOTS[@]}" -gt 0 ] && [ "${#ZS_BOT_DB[@]}" -eq 0 ] && _zs_bot_info "$f" "$work"
    if ! _restore_is_sql "$work/restore.sql"; then
        printf "    ${C_BAD}●${CR} ${C_BAD}%s inside the zip is not a database dump.${CR}\n" "$RESTORE_ENTRY"
        return 1
    fi
    RESTORE_SQL="$work/restore.sql"
    return 0
}

# DROP statements for everything now in the database, so a restore replaces
# it instead of piling onto it (phpMyAdmin exports carry no DROP TABLE)
_restore_drop_sql() {
    mysql -N -B -h "$1" -u "$2" -p"$3" "$4" -e 'SHOW FULL TABLES' 2>/dev/null \
        | while IFS=$'\t' read -r t type; do
            if [ "$type" = "VIEW" ]; then echo "DROP VIEW IF EXISTS \`$t\`;"
            else echo "DROP TABLE IF EXISTS \`$t\`;"; fi
        done
}

# after a restore, a refusal or a wrong pick: back to the file list, not
# all the way out to the main menu
_restore_pause() {
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to go back to the list... "
    read -r _
}

# What a restore replaced is kept in CIRO_BACKUP_DIR (next to the update
# snapshots) instead of /root itself - the newest CIRO_SNAPSHOTS_KEEP of
# each kind for each bot. $1: the name up to its date.
_restore_prune() {
    ls -1t "$CIRO_BACKUP_DIR/$1"[0-9]* 2>/dev/null | tail -n +$((CIRO_SNAPSHOTS_KEEP + 1)) \
        | while IFS= read -r f; do rm -f "$f"; done
}

# Menu 7 - put a backup into one bot: a database dump (.sql, or the .zip the
# bot sends) into its database, or the bot's folder zip (botfolder_*.zip)
# over its files. What is there now is saved first either way. The list
# stays open until 0 - a refusal or a finished restore comes back to it,
# and [R] reads /root again for a file uploaded meanwhile.
function import_bot() {
    clear 2>/dev/null || true
    banner
    _sec "Restore"
    if ! pick_bot_instance "restore a backup into"; then
        [ "$(bots_registry_count)" = "0" ] && sleep 2
        show_menu; return 0
    fi
    local dir="$PICKED_DIR" name="$PICKED_NAME" cfg="$PICKED_DIR/config.php"
    if [ ! -f "$cfg" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}Ciro is not installed. config.php not found.${CR}\n"
        echo ""; printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "; read -r _
        show_menu; return 1
    fi

    local dbhost dbname dbuser dbpass
    dbhost=$(grep '^\$dbhost' "$cfg" | cut -d"'" -f2)
    dbname=$(grep '^\$dbname' "$cfg" | cut -d"'" -f2)
    dbuser=$(grep '^\$usernamedb' "$cfg" | cut -d"'" -f2)
    dbpass=$(grep '^\$passworddb' "$cfg" | cut -d"'" -f2)
    [ -z "$dbhost" ] && dbhost="localhost"
    if [ -z "$dbname" ] || [ -z "$dbuser" ] || [ -z "$dbpass" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}Could not read database credentials from config.php${CR}\n"
        echo ""; printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "; read -r _
        show_menu; return 1
    fi

    local files f i choice file rc
    while true; do
        clear 2>/dev/null || true
        banner
        _sec "Restore"
        _kv "Bot" "${C_KEY}${name}${CR}"
        _kv "Database" "${C_DIM}${dbname}${CR}"
        echo ""
        printf "    ${C_DIM}Put the backup in /root - the .sql or .zip of the database, or${CR}\n"
        printf "    ${C_DIM}the bot's botfolder zip - and it shows up here (newest first).${CR}\n"
        echo ""
        files=(); i=1
        while IFS= read -r f; do
            files+=("$f")
            printf "    ${C_KEY}[%d]${CR} ${C_TXT}%-38s${CR} ${C_DIM}%5s  %s  %s${CR}\n" "$i" "$(basename "$f")" \
                "$(du -h "$f" 2>/dev/null | cut -f1)" "$(date -r "$f" '+%d %b %H:%M' 2>/dev/null)" "$(_restore_file_note "$f")"
            i=$((i + 1))
        done < <(find /root -maxdepth 1 -type f \( -iname '*.sql' -o -iname '*.zip' \) -printf '%T@\t%p\n' 2>/dev/null \
                    | sort -rn | head -n 15 | cut -f2-)
        [ "${#files[@]}" -eq 0 ] && printf "    ${C_WARN}!${CR} ${C_WARN}No .sql or .zip file in /root yet.${CR}\n"
        printf "    ${C_KEY}[R]${CR} ${C_TXT}Refresh this list${CR}\n"
        printf "    ${C_KEY}[0]${CR} ${C_TXT}Back to menu${CR}\n"
        echo ""
        printf "  ${C_PROMPT}❯${CR} Choose a file, or type its full path: "
        # nobody at the keyboard (stdin closed): leave instead of looping
        read -r choice || { show_menu; return 0; }
        case "$choice" in
            0) show_menu; return 0 ;;
            ""|r|R) continue ;;
        esac
        if [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -le "${#files[@]}" ]; then
            file="${files[$((choice - 1))]}"
        else
            file="$choice"
        fi
        rc=0
        if [ ! -f "$file" ]; then
            printf "\n    ${C_BAD}●${CR} ${C_BAD}File not found: %s${CR}\n" "$file"
        elif _restore_is_botfolder "$file"; then
            _restore_files "$file" "$dir" "$name" "$dbname"; rc=$?
        else
            _restore_db "$file" "$dir" "$name" "$dbhost" "$dbuser" "$dbpass" "$dbname"; rc=$?
        fi
        # 2: backed out of a question - straight back to the list
        [ "$rc" -eq 2 ] || _restore_pause
    done
}

# A database dump (.sql, or the .zip the bot sends) into DBNAME. The
# database is saved first; if the import fails half way it is put back from
# that copy, so a bad file never leaves the bot empty. Unpacked outside
# /root, and nothing of it is left behind.
# Returns 0 done or cancelled, 1 failed, 2 backed out.
_restore_db() {
    local file="$1" dir="$2" name="$3" dbhost="$4" dbuser="$5" dbpass="$6" dbname="$7" rc
    local work; work=$(mktemp -d /var/tmp/ciro_restore.XXXXXX)
    echo ""
    _restore_prepare "$file" "$work"; rc=$?
    if [ "$rc" -ne 0 ]; then
        rm -rf "$work"; return "$rc"
    fi

    local src owner shown
    src=$(_sql_source_db < "$RESTORE_SQL")
    shown="$(basename "$file")"
    [ -n "$RESTORE_ENTRY" ] && shown="$shown → $RESTORE_ENTRY"
    _sec "Restore into ${name}"
    _kv "File" "${C_DIM}${shown}${CR} ${C_DIM}($(du -h "$RESTORE_SQL" 2>/dev/null | cut -f1))${CR}"
    if [ -n "$src" ]; then
        owner=$(_db_owner_label "$src")
        if [ "$owner" = "$src" ]; then _kv "Made from" "${C_DIM}${src}${CR}"
        else _kv "Made from" "${C_DIM}${owner} (${src})${CR}"; fi
    fi
    _kv "Into" "${C_KEY}${dbname}${CR} ${C_DIM}(${name})${CR}"
    local zi
    for zi in "${!ZS_BOTS[@]}"; do
        _kv "Folder" "${C_DIM}${ZS_BOTS[$zi]:-/}${ZS_BOT_DB[$zi]:+  · database ${ZS_BOT_DB[$zi]}}  · $([ -n "${ZS_BOT_VER[$zi]}" ] && echo "version ${ZS_BOT_VER[$zi]}" || echo "original project")${CR}"
    done
    echo ""
    if [ -n "$src" ] && [ "$src" != "$dbname" ]; then
        printf "    ${C_WARN}!${CR} ${C_WARN}This backup was made from another database (%s).${CR}\n" "$src"
    fi
    printf "    ${C_WARN}!${CR} ${C_WARN}Everything in %s is replaced. A copy of it is saved first.${CR}\n" "$dbname"
    [ "${#ZS_BOTS[@]}" -gt 0 ] && printf "    ${C_DIM}Only the database is restored; the bot's files are not overwritten.${CR}\n"
    local confirm
    # RESTORE_YES: the admin already said yes in the bot
    if [ -n "$RESTORE_YES" ]; then
        confirm=y
    else
        printf "  ${C_PROMPT}❯${CR} Continue? ${C_DIM}[y/N]${CR}: "
        read -r confirm
    fi
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        printf "\n    ${C_DIM}Restore cancelled - nothing was changed.${CR}\n"
        rm -rf "$work"; return 0
    fi
    echo ""

    mkdir -p "$CIRO_BACKUP_DIR"
    local safe="$CIRO_BACKUP_DIR/pre-restore_db_${dbname}_$(date +%Y%m%d_%H%M%S).sql"
    local my="mysql -h '$dbhost' -u '$dbuser' -p'$dbpass' '$dbname'"
    # --ssl-mode is MySQL's; MariaDB's mysqldump stops at it, so it goes without
    if ! run_step "Saving the current database" \
        "mysqldump -h '$dbhost' -u '$dbuser' -p'$dbpass' --no-tablespaces --ssl-mode=DISABLED '$dbname' > '$safe' 2>/dev/null || mysqldump -h '$dbhost' -u '$dbuser' -p'$dbpass' --no-tablespaces '$dbname' > '$safe'"; then
        show_step_error; rm -f "$safe"
        printf "\n    ${C_BAD}●${CR} ${C_BAD}Could not save the current database - nothing was changed.${CR}\n"
        RESTORE_FAIL="safe"
        rm -rf "$work"; return 1
    fi
    _restore_prune "pre-restore_db_${dbname}_"
    _restore_drop_sql "$dbhost" "$dbuser" "$dbpass" "$dbname" > "$work/drop.sql"
    # A dump may name its own database (CREATE DATABASE / USE); drop those
    # lines so it always lands in this bot's database, never another one.
    if ! run_step "Importing $(basename "${RESTORE_ENTRY:-$file}")" \
        "{ echo 'SET FOREIGN_KEY_CHECKS=0;'; cat '$work/drop.sql'; sed -E '/^(CREATE DATABASE |USE \`)/d' '$RESTORE_SQL'; } | $my"; then
        show_step_error
        _restore_drop_sql "$dbhost" "$dbuser" "$dbpass" "$dbname" > "$work/drop.sql"
        if run_step "Putting the previous database back" \
            "{ echo 'SET FOREIGN_KEY_CHECKS=0;'; cat '$work/drop.sql'; cat '$safe'; } | $my"; then
            printf "\n    ${C_BAD}●${CR} ${C_BAD}The import failed, so the database was put back as it was.${CR}\n"
            RESTORE_FAIL="import"
        else
            show_step_error
            printf "\n    ${C_BAD}●${CR} ${C_BAD}The import failed and the old data could not be put back.${CR}\n"
            printf "    ${C_DIM}It is saved in %s${CR}\n" "$safe"
            RESTORE_FAIL="import_lost"; RESTORE_SAFE="$safe"
        fi
        rm -rf "$work"; return 1
    fi
    # An older backup gets the columns and tables this version expects
    run_step "Updating tables for this version" "cd '$dir' && php table.php" || show_step_error
    chown -R www-data:www-data "$dir" 2>/dev/null

    echo ""
    RESTORE_NOTE="db"; RESTORE_SAFE="$safe"
    printf "    ${C_OK}✔${CR} ${C_OK}Restored into %s (%s).${CR}\n" "$dbname" "$name"
    printf "    ${C_DIM}The data it had before is kept in %s${CR}\n" "$safe"
    _restore_zip_folders "$file" "$dir" "$src" "$work"
    rm -rf "$work"
    return 0
}

# The bot folder a backup zip carries along with its database. Only the
# database is restored from it: a folder of an older version (or of the
# original project, which has no version file) would put old code back, and
# this bot already runs the newest. What the folder keeps that is data and not
# code is kept - see _restore_carry_data.
_restore_zip_folders() {
    local file="$1" dir="$2" src="$3" work="$4" i k=-1 p
    _is_zip "$file" || return 0
    [ "${#ZS_BOTS[@]}" -gt 0 ] || return 0
    for i in "${!ZS_BOTS[@]}"; do
        if [ -n "$src" ] && [ "${ZS_BOT_DB[$i]}" = "$src" ]; then k=$i; break; fi
    done
    if [ "$k" -lt 0 ] && [ "${#ZS_BOTS[@]}" -eq 1 ]; then k=0; fi
    if [ "$k" -lt 0 ]; then
        printf "    ${C_DIM}The zip also holds bot folders, none made from this database - they were left alone.${CR}\n"
        return 0
    fi
    p="${ZS_BOTS[$k]}"
    if _zip_extract_all "$file" "$ZIP_PW" "$work/old" "${p}vpnbot/*" "${p}api/hash.txt"; then
        _restore_old_folder "$work/old/${p}" "$dir" "${ZS_BOT_VER[$k]}" "$p"
    else
        printf "    ${C_WARN}!${CR} ${C_WARN}The bot folder in the zip could not be read, so its own files were not kept.${CR}\n"
    fi
}

# The bot's folder zip (botfolder_*.zip) over DIR: unpacked outside /root
# first (asking for the password the bot's backup settings put on it, if
# any), the folder as it is now saved, then laid over it - files the zip
# has are replaced, the rest stays. config.php stays this bot's own, so it
# keeps its database, token and address; the update machinery's state
# files stay too. Core files failing the PHP syntax check put the saved
# folder back. Returns 0 done or cancelled, 1 failed, 2 backed out.
_restore_files() {
    local file="$1" dir="$2" name="$3" dbname="$4"
    local prefix from work rc src owner confirm tag snap bad c ver cur
    prefix=$(_restore_bot_prefix "$file") || return 1
    work=$(mktemp -d /var/tmp/ciro_restore.XXXXXX)
    from="$work/new/${prefix}"
    echo ""
    ZIP_PW="${RESTORE_PW:-}"
    # the password (if the zip has one) is asked here, once, on index.php
    _zip_open_entry "$file" "${prefix}index.php" "$work/probe.tmp"; rc=$?
    if [ "$rc" -ne 0 ]; then rm -rf "$work"; return "$rc"; fi
    rm -f "$work/probe.tmp"
    _zip_extract_all "$file" "$ZIP_PW" "$work/new"; rc=$?
    if [ "$rc" -ne 0 ] || [ ! -s "${from}index.php" ]; then
        _zip_say_damaged
        rm -rf "$work"; return 1
    fi
    # A folder of an older version - or of the original project, which has no
    # version file - is not laid over this bot's newer files; only what is its
    # own data is kept from it.
    ver=""
    [ -f "${from}version" ] && ver=$(tr -cd '[:alnum:]._-' < "${from}version" | head -c 40)
    cur=$(get_installed_version "$dir")
    if _ver_older "$ver" "$cur"; then
        _sec "Restore into ${name}"
        _kv "File" "${C_DIM}$(basename "$file")${CR} ${C_DIM}($(du -h "$file" 2>/dev/null | cut -f1), the bot's folder)${CR}"
        _kv "Into" "${C_KEY}${dir}${CR} ${C_DIM}(${name})${CR}"
        _restore_old_folder "$from" "$dir" "$ver" "$prefix"
        rm -rf "$work"; return 0
    fi

    src=$(grep '^\$dbname' "${from}config.php" 2>/dev/null | cut -d"'" -f2)
    _sec "Restore into ${name}"
    _kv "File" "${C_DIM}$(basename "$file")${CR} ${C_DIM}($(du -h "$file" 2>/dev/null | cut -f1), the bot's folder)${CR}"
    if [ -n "$src" ]; then
        owner=$(_db_owner_label "$src")
        if [ "$owner" = "$src" ]; then _kv "Made from" "${C_DIM}${src}${CR}"
        else _kv "Made from" "${C_DIM}${owner} (${src})${CR}"; fi
    fi
    _kv "Into" "${C_KEY}${dir}${CR} ${C_DIM}(${name})${CR}"
    echo ""
    if [ -n "$src" ] && [ "$src" != "$dbname" ]; then
        printf "    ${C_WARN}!${CR} ${C_WARN}This backup was made from another bot (%s).${CR}\n" "$owner"
    fi
    printf "    ${C_WARN}!${CR} ${C_WARN}The bot's files are replaced with the ones in this backup. A copy of them is saved first.${CR}\n"
    printf "    ${C_DIM}config.php stays as it is, so the bot keeps its own database, token and address.${CR}\n"
    if [ -n "$RESTORE_YES" ]; then
        confirm=y
    else
        printf "  ${C_PROMPT}❯${CR} Continue? ${C_DIM}[y/N]${CR}: "
        read -r confirm
    fi
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        printf "\n    ${C_DIM}Restore cancelled - nothing was changed.${CR}\n"
        rm -rf "$work"; return 0
    fi
    echo ""

    rm -f "${from}config.php" "${from}update_request" "${from}update_request.running" "${from}rollback_request" \
          "${from}update_progress.json" "${from}update_status.json" "${from}update_backups.json" \
          "${from}restore_request" "${from}restore_request.running" "${from}restore_progress.json" \
          "${from}restore_status.json" "${from}watch_caps.json"
    rm -rf "${from}storage/restore"
    # named like the update snapshots - after the bot's @username, or its directory
    tag=$(grep '^\$usernamebot' "$dir/config.php" 2>/dev/null | cut -d"'" -f2 | tr -cd 'A-Za-z0-9_' | cut -c1-32)
    [ -z "$tag" ] && tag=$(basename "$dir" | tr -cd 'A-Za-z0-9_' | cut -c1-32)
    mkdir -p "$CIRO_BACKUP_DIR"
    snap="$CIRO_BACKUP_DIR/pre-restore_files_${tag}_$(date +%Y%m%d_%H%M%S).tar.gz"
    if ! run_step "Saving the current files" \
        "tar --warning=no-file-changed -czf '$snap' -C '$(dirname "$dir")' --exclude='*.bak*' --exclude='.git' '$(basename "$dir")'"; then
        show_step_error; rm -f "$snap"
        printf "\n    ${C_BAD}●${CR} ${C_BAD}Could not save the current files - nothing was changed.${CR}\n"
        RESTORE_FAIL="safe"
        rm -rf "$work"; return 1
    fi
    _restore_prune "pre-restore_files_${tag}_"
    if ! run_step "Putting the backup's files in place" "cp -a '${from}.' '$dir/'"; then
        show_step_error
        _rollback_update "$snap" "$dir" restore
        rm -rf "$work"; return 1
    fi
    rm -rf "$work"
    run_step "Setting ownership and permissions" \
        "chown -R www-data:www-data '$dir' && find '$dir' -type d -exec chmod 755 {} + && find '$dir' -type f -exec chmod 644 {} + && chmod +x '$dir'/*.sh 2>/dev/null; true" \
        || true
    bad=""
    for c in index.php admin.php function.php keyboard.php table.php; do
        [ -f "$dir/$c" ] || continue
        php -l "$dir/$c" >/dev/null 2>&1 || bad="$bad $c"
    done
    if [ -n "$bad" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}PHP syntax errors in the restored files:${CR}${C_KEY}%s${CR}\n" "$bad"
        _rollback_update "$snap" "$dir" restore
        RESTORE_FAIL="syntax"
        return 1
    fi
    # the restored code gets the columns and tables it expects
    run_step "Updating tables for this version" "cd '$dir' && php table.php" || show_step_error
    chown -R www-data:www-data "$dir" 2>/dev/null

    echo ""
    RESTORE_NOTE="files"; RESTORE_SAFE="$snap"
    printf "    ${C_OK}✔${CR} ${C_OK}The bot's files were restored into %s (%s).${CR}\n" "$dir" "$name"
    printf "    ${C_DIM}The files it had before are kept in %s${CR}\n" "$snap"
    return 0
}

function show_menu() {
    # A run with nobody at the keyboard (the bot's own 🔄 آپدیت ربات button,
    # carried out by the root watcher below) has no terminal: the `read` at the
    # bottom of this menu would hit EOF, fall into the "Not an option" branch
    # and call show_menu again - forever. Every error path in this script
    # returns to this menu, so guarding it in this one place is what makes all
    # 57 of them safe to reach unattended.
    if [ -n "$CIRO_NONINTERACTIVE" ]; then
        return 0
    fi
    show_logo
    _sec "Menu"
    _mi "1" "Install"   "set up the bot on a clean server"
    _mi "2" "Update"    "newest code, keeps all your data"
    _mi "3" "Remove"    "delete the bot and its packages"
    _mi "5" "Renew SSL" "renew a certificate, or change a bot's domain"
    _mi "6" "Backup"    "database dump, sent to Telegram"
    _mi "7" "Restore"   "a database or bot folder backup ${C_WARN}(beta)${CR}"
    _mi "8" "Help"      "commands and flags for scripts"
    _mi "9" "Database"  "password, login info, phpMyAdmin port"
    _mi "10" "Transfer"  "move a bot to another server"
    _mi "11" "Start/Stop" "pause a bot here, or bring it back"
    _mi "0" "Exit"      ""
    _rule
    echo ""
    printf  "  ${C_PROMPT}❯${CR} Choose ${C_DIM}[0-11]${CR}: "
    read -r option
    case $option in
        1) install_bot ;;
        2) update_bot ;;
        3) remove_bot ;;
        5) renew_ssl ;;
        6) backup_bot ;;
        7) import_bot ;;
        8) show_help_screen ;;
        9) database_menu ;;
        10) transfer_bot ;;
        11) startstop_menu ;;
        0) echo -e "\n${C_OK}Bye.${CR}"; exit 0 ;;
        *) echo -e "\n${C_BAD}Not an option. Try again.${CR}"; sleep 1; show_menu ;;
    esac
}

# Clean, styled guide of all commands and parameters
function show_help_screen() {
    clear 2>/dev/null || true
    banner

    _sec "Commands"
    _kv "install" "${C_DIM}Install Ciro${CR}"
    _kv "update" "${C_DIM}Update Ciro to the latest code${CR}"
    _kv "remove" "${C_DIM}Remove Ciro and its services${CR}"
    _kv "migrate" "${C_DIM}Migrate a bot of the original project to Ciro (Install and Update do it by themselves)${CR}"
    _kv "addbot" "${C_DIM}Install a second, independent bot on this server${CR}"
    _kv "renew" "${C_DIM}Renew the bot domain SSL certificate${CR}"
    _kv "backup" "${C_DIM}Backup database & send to Telegram${CR}"
    _kv "import" "${C_DIM}Restore a database backup (.sql/.zip) or a bot folder zip (Beta)${CR}"
    _kv "menu" "${C_DIM}Open this interactive panel (default)${CR}"

    _sec "Install parameters"
    _kv "--name" "${C_DIM}Bot username${CR}"
    _kv "--token" "${C_DIM}Telegram bot token${CR}"
    _kv "--admin" "${C_DIM}Admin chat id${CR}"
    _kv "--domain" "${C_DIM}Domain name (e.g. bot.example.com)${CR}"
    _kv "--db-user" "${C_DIM}Database username${CR}"
    _kv "--db-pass" "${C_DIM}Database password${CR}"

    _kv "-h, --help" "${C_DIM}Show CLI help and exit${CR}"

    _sec "Examples"
    printf "    ${C_KEY}Ciro install${CR}\n"
    printf "    ${C_KEY}Ciro install --name myvpnbot --token 123:ABC \\\\${CR}\n"
    printf "    ${C_DIM}            --admin 111 --domain bot.example.com${CR}\n"
    printf "    ${C_KEY}Ciro update${CR}\n"
    printf "    ${C_KEY}Ciro remove${CR}\n"
    printf "    ${C_KEY}Ciro backup${CR}\n"
    printf "    ${C_KEY}Ciro import${CR}\n"

    echo ""
    _rule
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}
function find_free_port() {
    for port in {3300..3330}; do
        if ! ss -tuln | grep -q ":$port "; then
            echo "$port"
            return 0
        fi
    done
    echo -e "\033[31m[ERROR] No free port found between 3300 and 3330.\033[0m"
    exit 1
}
function fix_update_issues() {
    echo -e "\e[33mTrying to fix update issues by changing mirrors...\033[0m"
    # Broken apt mirrors are often a DNS problem - fix DNS first
    ensure_dns
    if ! detect_os || [ -z "$OS_CODENAME" ]; then
        echo -e "\e[91mCould not detect Ubuntu version.\033[0m"
        return 1
    fi

    # Ubuntu 24.04+ (and 26.04) ship the deb822 file and often have no
    # /etc/apt/sources.list at all - rewrite whichever one this release uses.
    local DEB822=/etc/apt/sources.list.d/ubuntu.sources
    local LEGACY=/etc/apt/sources.list
    local target="" fmt=""
    if [ -f "$DEB822" ]; then target="$DEB822"; fmt="deb822"
    else target="$LEGACY"; fmt="legacy"; fi
    [ -f "$target" ] && cp "$target" "$target.cirobackup"

    local parked=""
    if [ "$fmt" = "deb822" ] && [ -s "$LEGACY" ]; then
        cp "$LEGACY" "$LEGACY.cirobackup" && : > "$LEGACY" && parked="$LEGACY"
    fi

    # arm64/armhf live on ports.ubuntu.com, not the archive mirrors.
    local arch path MIRRORS
    arch=$(dpkg --print-architecture 2>/dev/null || uname -m)
    case "$arch" in
        arm64|armhf|ppc64el|s390x|riscv64)
            MIRRORS=("ports.ubuntu.com")
            path="ubuntu-ports"
            ;;
        *)
            MIRRORS=(
                "archive.ubuntu.com"
                "us.archive.ubuntu.com"
                "fr.archive.ubuntu.com"
                "de.archive.ubuntu.com"
                "mirrors.digitalocean.com"
                "mirrors.linode.com"
            )
            path="ubuntu"
            ;;
    esac

    local mirror
    for mirror in "${MIRRORS[@]}"; do
        echo -e "\e[33mTrying mirror: $mirror\033[0m"
        if [ "$fmt" = "deb822" ]; then
            cat > "$target" << EOF
Types: deb
URIs: http://$mirror/$path/
Suites: $OS_CODENAME $OS_CODENAME-updates $OS_CODENAME-backports $OS_CODENAME-security
Components: main restricted universe multiverse
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg
EOF
        else
            cat > "$target" << EOF
deb http://$mirror/$path/ $OS_CODENAME main restricted universe multiverse
deb http://$mirror/$path/ $OS_CODENAME-updates main restricted universe multiverse
deb http://$mirror/$path/ $OS_CODENAME-security main restricted universe multiverse
EOF
        fi
        if apt-get update --allow-releaseinfo-change 2>/dev/null; then
            echo -e "\e[32mSuccessfully updated using mirror: $mirror\033[0m"
            rm -f "$target.cirobackup"
            [ -n "$parked" ] && rm -f "$parked.cirobackup"
            return 0
        fi
    done
    if [ -f "$target.cirobackup" ]; then
        mv "$target.cirobackup" "$target"
    else
        rm -f "$target"
    fi
    [ -n "$parked" ] && [ -f "$parked.cirobackup" ] && mv "$parked.cirobackup" "$parked"
    echo -e "\e[91mAll mirrors failed. Restored original apt sources\033[0m"
    return 1
}

# ─────────────────────────────────────────────────────────────
#  Validation and pre-flight checks
#  (DNS helpers dns_works/ensure_dns are defined near the top)
# ─────────────────────────────────────────────────────────────

# Can we actually reach the internet?
net_works() {
    curl -fsSL --max-time 8 -o /dev/null "https://github.com" 2>/dev/null && return 0
    curl -fsSL --max-time 8 -o /dev/null "https://api.telegram.org" 2>/dev/null && return 0
    return 1
}

# Ensure DNS + connectivity, fixing DNS automatically if needed.
ensure_connectivity() {
    ensure_dns
    net_works && return 0
    echo -e "  ${C_WARN}!${CR} ${C_WARN}No connectivity - resetting DNS and retrying...${CR}"
    ensure_dns
    net_works && return 0
    return 1
}

# ── Input validators ─────────────────────────────────────────
validate_domain() { [[ "$1" =~ ^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$ ]]; }

# 0 = points here, 1 = points elsewhere, 2 = could not resolve
domain_points_here() {
    local dom="$1" myip resolved
    myip=$(get_server_ip)
    resolved=$(getent ahostsv4 "$dom" 2>/dev/null | awk '{print $1; exit}')
    [ -z "$resolved" ] && resolved=$(getent hosts "$dom" 2>/dev/null | awk '{print $1; exit}')
    [ -z "$resolved" ] && return 2
    [ "$resolved" = "$myip" ] && return 0
    return 1
}

# 0 = valid+live, 1 = bad format, 2 = format ok but token rejected/unreachable
validate_token() {
    [[ "$1" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]] || return 1
    local r; r=$(curl -fsSL --max-time 8 "https://api.telegram.org/bot$1/getMe" 2>/dev/null)
    echo "$r" | grep -q '"ok":true' && return 0
    return 2
}

# Safe identifiers/passwords (no quotes/specials that break SQL or config.php)
valid_db_ident() { [[ "$1" =~ ^[A-Za-z0-9_]{1,32}$ ]]; }
valid_db_pass()  { [[ "$1" =~ ^[A-Za-z0-9_]{6,64}$ ]]; }

# config.php's $domainhosts is the bot's address as its links use it:
# "bot.example.com", or "bot.example.com:8443" for a bot on another port.
# hosts_domain gives the bare domain (certificates, vhost files), hosts_port
# the port (443 when there is none), hosts_of builds it back.
hosts_domain() { local h="${1%%/*}"; echo "${h%%:*}"; }
hosts_port()   { local h="${1%%/*}"; case "$h" in *:*) echo "${h##*:}" ;; *) echo 443 ;; esac; }
hosts_of()     { if [ "${2:-443}" = "443" ]; then echo "$1"; else echo "$1:$2"; fi; }

# ── Multi-bot registry ────────────────────────────────────────
# Tracks every bot installed on this server (name, directory, domain) so
# Update/Remove/Renew SSL/Backup/Restore can ask which one to act on. A
# server that only ever had the one bot never sees any of this - the
# registry silently carries a single entry and every picker below returns
# it without a prompt, exactly like before this existed.
BOTS_REGISTRY="/root/Ciro/bots.json"

_ensure_jq() {
    command -v jq >/dev/null 2>&1 || apt-get install -y jq >/dev/null 2>&1
}

# Make sure the registry file exists and, for a server that predates it,
# seed it from whatever is already installed at the default path.
bots_registry_ensure() {
    _ensure_jq
    mkdir -p /root/Ciro
    [ -f "$BOTS_REGISTRY" ] || echo '[]' > "$BOTS_REGISTRY"
    local seed
    for seed in "$BOT_DIR_DEFAULT" "$LEGACY_BOT_DIR"; do
        [ -f "$seed/config.php" ] && break
        seed=""
    done
    if [ "$(jq 'length' "$BOTS_REGISTRY" 2>/dev/null)" = "0" ] && [ -n "$seed" ]; then
        local name hosts
        name=$(grep '^\$usernamebot' "$seed/config.php" 2>/dev/null | cut -d"'" -f2)
        [ -z "$name" ] && name="Bot 1"
        hosts=$(grep '^\$domainhosts' "$seed/config.php" 2>/dev/null | cut -d"'" -f2)
        bots_registry_add "$name" "$seed" "$(hosts_domain "$hosts")"
        [ "$(hosts_port "$hosts")" != "443" ] && bots_registry_set_bot_port "$seed" "$(hosts_port "$hosts")"
    fi
}

# bots_registry_add NAME DIR DOMAIN - insert, or update if DIR is already
# registered (so re-running install on the same directory does not duplicate).
# An update keeps the entry's other fields (pma_port).
bots_registry_add() {
    local name="$1" dir="$2" domain="$3"
    _ensure_jq
    mkdir -p /root/Ciro
    [ -f "$BOTS_REGISTRY" ] || echo '[]' > "$BOTS_REGISTRY"
    local tmp; tmp=$(mktemp)
    jq --arg name "$name" --arg dir "$dir" --arg domain "$domain" '
        (map(.dir) | index($dir)) as $i
        | if $i != null then .[$i] += {name:$name,dir:$dir,domain:$domain}
          else . + [{name:$name,dir:$dir,domain:$domain}]
          end
    ' "$BOTS_REGISTRY" > "$tmp" && mv "$tmp" "$BOTS_REGISTRY"
}

bots_registry_remove_by_dir() {
    local dir="$1"
    _ensure_jq
    [ -f "$BOTS_REGISTRY" ] || return 0
    local tmp; tmp=$(mktemp)
    jq --arg dir "$dir" '[.[] | select(.dir != $dir)]' "$BOTS_REGISTRY" > "$tmp" && mv "$tmp" "$BOTS_REGISTRY"
}

bots_registry_count() {
    bots_registry_ensure
    jq 'length' "$BOTS_REGISTRY" 2>/dev/null || echo 0
}

# Sets PICKED_NAME / PICKED_DIR / PICKED_DOMAIN. Prompts only when more than
# one bot is registered; a single bot (or a legacy server with none
# registered yet) is picked automatically, no prompt, same as before.
# $1 = a short label for the prompt, e.g. "update".
# Returns 1 if there is nothing to act on, or the user backs out.
pick_bot_instance() {
    local action="${1:-manage}"
    bots_registry_ensure
    # The watcher already knows which install asked for this update (the request
    # file was found in that very directory), so it says so outright. Without
    # this, a server with two bots would reach the "which bot?" prompt with no
    # one there to answer it.
    if [ -n "$CIRO_BOT_DIR" ] && [ -d "$CIRO_BOT_DIR" ]; then
        PICKED_DIR="$CIRO_BOT_DIR"
        PICKED_NAME=$(jq -r --arg d "$CIRO_BOT_DIR" '.[] | select(.dir==$d) | .name' "$BOTS_REGISTRY" 2>/dev/null | head -1)
        PICKED_DOMAIN=$(jq -r --arg d "$CIRO_BOT_DIR" '.[] | select(.dir==$d) | .domain' "$BOTS_REGISTRY" 2>/dev/null | head -1)
        [ -z "$PICKED_NAME" ] && PICKED_NAME="Bot"
        return 0
    fi
    local count; count=$(jq 'length' "$BOTS_REGISTRY" 2>/dev/null)
    if [ -z "$count" ] || [ "$count" = "0" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}No Ciro bot found on this server.${CR}\n"
        return 1
    fi
    if [ "$count" = "1" ]; then
        PICKED_NAME=$(jq -r '.[0].name' "$BOTS_REGISTRY")
        PICKED_DIR=$(jq -r '.[0].dir' "$BOTS_REGISTRY")
        PICKED_DOMAIN=$(jq -r '.[0].domain' "$BOTS_REGISTRY")
        return 0
    fi
    _sec "Which bot do you want to $action?"
    local i=1
    while IFS=$'\t' read -r name domain stopped; do
        printf "    ${C_KEY}[%d]${CR} ${C_TXT}%s${CR}  ${C_DIM}%s%s${CR}\n" "$i" "$name" "$domain" \
            "$([ "$stopped" = "true" ] && echo " · ⏸ stopped")"
        i=$((i + 1))
    done < <(jq -r '.[] | "\(.name)\t\(.domain)\t\(.stopped // false)"' "$BOTS_REGISTRY")
    printf "    ${C_KEY}[0]${CR} ${C_TXT}Back to menu${CR}\n"
    echo ""
    printf "  ${C_PROMPT}❯${CR} Choose ${C_DIM}[0-%d]${CR}: " "$((count))"
    local pick; read -r pick
    if ! [[ "$pick" =~ ^[0-9]+$ ]] || [ "$pick" -lt 1 ] || [ "$pick" -gt "$count" ]; then
        return 1
    fi
    local idx=$((pick - 1))
    PICKED_NAME=$(jq -r ".[$idx].name" "$BOTS_REGISTRY")
    PICKED_DIR=$(jq -r ".[$idx].dir" "$BOTS_REGISTRY")
    PICKED_DOMAIN=$(jq -r ".[$idx].domain" "$BOTS_REGISTRY")
    return 0
}

# ── phpMyAdmin port ───────────────────────────────────────────
# phpMyAdmin is included in each bot's own vhosts, so it answers at
# https://<bot domain>[:<bot port>]/phpmyadmin - next to the webhook. A bot
# can move it to a port of its own instead: https://<bot domain>:<port>/phpmyadmin.
# The webhook stays on the bot's port (443, or 8443/88 when something else
# holds 443). Several bots may share one port: Apache tells them apart by
# domain (SNI), exactly as on 443.
# The registry's pma_port is the source of truth; pma_sync_all turns it
# into Apache config.
PMA_CONF="/etc/apache2/conf-available/phpmyadmin.conf"
PMA_PORTS_CONF="/etc/apache2/conf-available/ciro-pma-ports.conf"

# bots_registry_pma_port DIR - that bot's phpMyAdmin port (the bot's own
# port when unset: phpMyAdmin inside the bot's own site)
bots_registry_pma_port() {
    local p
    p=$(jq -r --arg d "$1" '.[] | select(.dir==$d) | .pma_port // empty' "$BOTS_REGISTRY" 2>/dev/null | head -1)
    echo "${p:-$(bots_registry_bot_port "$1")}"
}

# bots_registry_bot_port DIR - the port Telegram reaches that bot on: the
# registry's, else the one in its config.php's $domainhosts, else 443
bots_registry_bot_port() {
    local p
    p=$(jq -r --arg d "$1" '.[] | select(.dir==$d) | .port // empty' "$BOTS_REGISTRY" 2>/dev/null | head -1)
    [ -z "$p" ] && p=$(hosts_port "$(grep '^\$domainhosts' "$1/config.php" 2>/dev/null | head -1 | cut -d"'" -f2)")
    echo "${p:-443}"
}

bots_registry_set_bot_port() {
    local tmp; tmp=$(mktemp)
    jq --arg d "$1" --arg p "$2" 'map(if .dir==$d then .port=($p|tonumber) else . end)' \
        "$BOTS_REGISTRY" > "$tmp" && mv "$tmp" "$BOTS_REGISTRY"
}

# A bot stopped on this server (option 11, or after a transfer): its sites
# are switched off and its jobs removed, but its files, database and
# certificate stay, so it can be started again.
bots_registry_set_stopped() {
    local tmp; tmp=$(mktemp)
    jq --arg d "$1" --arg s "$2" 'map(if .dir==$d then (if $s == "true" then .stopped=true else del(.stopped) end) else . end)' \
        "$BOTS_REGISTRY" > "$tmp" && mv "$tmp" "$BOTS_REGISTRY"
}
bots_registry_is_stopped() {
    [ "$(jq -r --arg d "$1" '.[] | select(.dir==$d) | .stopped // false' "$BOTS_REGISTRY" 2>/dev/null | head -1)" = "true" ]
}

bots_registry_set_pma_port() {
    local tmp; tmp=$(mktemp)
    jq --arg d "$1" --arg p "$2" 'map(if .dir==$d then .pma_port=($p|tonumber) else . end)' \
        "$BOTS_REGISTRY" > "$tmp" && mv "$tmp" "$BOTS_REGISTRY"
    config_set_pma_port "$1" "$2"
}

# config_set_pma_port DIR PORT - the bot's own copy of its phpMyAdmin port
# ($pmaport in its config.php): ⚙️ تنظیمات عمومی → 🗃 phpMyAdmin builds its
# login link with it, and the registry above lives in /root, out of the
# bot's reach. Written before config.php's closing ?> - after it, PHP would
# print the line instead of reading it.
config_set_pma_port() {
    local cfg="$1/config.php"
    [ -f "$cfg" ] || return 0
    if grep -q '^\$pmaport' "$cfg"; then
        sed -i -E "s/^(\\\$pmaport[[:space:]]*=[[:space:]]*')[^']*'/\\1$2'/" "$cfg"
    elif grep -q '^?>' "$cfg"; then
        awk -v line="\$pmaport = '$2';" '!done && /^\?>/ {print line; done=1} {print}' "$cfg" > "$cfg.pma" \
            && cat "$cfg.pma" > "$cfg"
        rm -f "$cfg.pma"
    else
        printf "\$pmaport = '%s';\n" "$2" >> "$cfg"
    fi
}

# pma_url DOMAIN PORT [PROTO]
pma_url() {
    if [ "$2" = "443" ]; then
        echo "${3:-https}://$1/phpmyadmin"
    else
        echo "https://$1:$2/phpmyadmin"
    fi
}

# Prints why PORT cannot carry phpMyAdmin and returns 1; silent when it can.
# BOT_PORT (2nd argument, 443 when not given) is the bot's own port: always
# fine, phpMyAdmin then answers inside the bot's own site.
pma_port_problem() {
    local p="$1" bp="${2:-443}" held who
    if ! [[ "$p" =~ ^[1-9][0-9]{0,4}$ ]]; then echo "Enter a port number."; return 1; fi
    [ "$p" -eq "$bp" ] && return 0
    if [ "$p" -ne 443 ] && { [ "$p" -lt 1024 ] || [ "$p" -gt 65535 ]; }; then
        echo "Use the bot port ($bp), 443, or a port from 1024 to 65535."; return 1
    fi
    held=$(ss -ltnp "( sport = :$p )" 2>/dev/null | tail -n +2)
    [ -z "$held" ] && return 0
    who=$(printf '%s' "$held" | grep -o 'users:(("[^"]*"' | head -1 | cut -d'"' -f2)
    # Apache already on it for another bot or its phpMyAdmin: sharing is fine
    if [ "$who" = "apache2" ] \
        && jq -e --arg p "$p" 'any(.[]; .pma_port == ($p|tonumber) or (.port // 443) == ($p|tonumber))' "$BOTS_REGISTRY" >/dev/null 2>&1; then
        return 0
    fi
    echo "Port $p is already used by ${who:-another program}. Pick another one."
    return 1
}

# Asked while a bot is being set up; Enter keeps the bot's own port (the
# argument, 443 when not given). Sets PMA_PORT.
ask_pma_port() {
    local p why bp="${1:-443}"
    # --pma-port (Transfer checked it free on this server already): no question
    if [ -n "$ARG_PMA_PORT" ]; then
        if why=$(pma_port_problem "$ARG_PMA_PORT" "$bp"); then
            PMA_PORT="$ARG_PMA_PORT"
            echo -e "\e[33m[+] \e[36mphpMyAdmin port (from --pma-port):\e[0m ${PMA_PORT}"
            return 0
        fi
        echo -e "\e[91m--pma-port ${ARG_PMA_PORT}: ${why}\033[0m"
    fi
    while true; do
        printf "\e[33m[+] \e[36mphpMyAdmin port ${CR}${C_DIM}(can be the same as the bot port) [default: ${bp}]${CR}: "
        read -r p
        [ -z "$p" ] && p="$bp"
        if why=$(pma_port_problem "$p" "$bp"); then break; fi
        echo -e "\e[91m${why}\033[0m"
    done
    PMA_PORT="$p"
}

# ── The bot's own port ────────────────────────────────────────
# Telegram delivers a webhook to 443, 80, 88 or 8443 only, and 80 stays
# Apache's plain-HTTP site (certificate renewals use it) - so a bot is on
# 443, 8443 or 88. 443 is the default; when something else already holds it
# (Xray, a VPN panel) the bot takes 8443 or 88 instead and neither has to
# move. config.php's $domainhosts then carries the port, so every link the
# bot makes (webhook, payment callbacks, scheduled jobs) uses it too.
BOT_PORTS_ALLOWED="443 8443 88"

# Prints why PORT cannot carry the bot and returns 1; silent when it can.
bot_port_problem() {
    local p="$1" held who
    case " $BOT_PORTS_ALLOWED " in
        *" $p "*) ;;
        *) echo "Telegram only reaches a bot on 443, 8443 or 88."; return 1 ;;
    esac
    held=$(ss -ltnp "( sport = :$p )" 2>/dev/null | tail -n +2)
    [ -z "$held" ] && return 0
    who=$(printf '%s' "$held" | grep -o 'users:(("[^"]*"' | head -1 | cut -d'"' -f2)
    # Apache already on it (another bot of this server): sharing is fine
    [ "$who" = "apache2" ] && return 0
    echo "Port $p is already used by ${who:-another program}. Pick another one."
    return 1
}

# The first allowed port nothing else holds - what Enter picks.
bot_port_default() {
    local p
    for p in $BOT_PORTS_ALLOWED; do
        bot_port_problem "$p" >/dev/null && { echo "$p"; return 0; }
    done
    echo 443
}

# Asked while a bot is being set up; --port answers it. Sets BOT_PORT.
ask_bot_port() {
    local p why def who
    if [ -n "$ARG_PORT" ]; then
        if why=$(bot_port_problem "$ARG_PORT"); then
            BOT_PORT="$ARG_PORT"
            echo -e "\e[33m[+] \e[36mBot port (from --port):\e[0m ${BOT_PORT}"
            return 0
        fi
        echo -e "\e[91m--port ${ARG_PORT}: ${why}\033[0m"
    fi
    def=$(bot_port_default)
    if [ "$def" != "443" ]; then
        who=$(ss -ltnp "( sport = :443 )" 2>/dev/null | tail -n +2 | grep -o 'users:(("[^"]*"' | head -1 | cut -d'"' -f2)
        echo -e "  ${C_WARN}!${CR} ${C_WARN}Port 443 is used by ${who:-another program} - the bot can use port ${def} instead.${CR}"
    fi
    while true; do
        printf "\e[33m[+] \e[36mBot port ${CR}${C_DIM}(Telegram accepts 443, 8443 or 88) [default: ${def}]${CR}: "
        read -r p
        [ -z "$p" ] && p="$def"
        if why=$(bot_port_problem "$p"); then break; fi
        echo -e "\e[91m${why}\033[0m"
    done
    BOT_PORT="$p"
}

_pma_restore() {
    local d
    for d in sites-available sites-enabled conf-available conf-enabled; do
        rm -rf "/etc/apache2/$d" && cp -a "$1/$d" /etc/apache2/
    done
    # 443's Listen may have been switched off for a bot on another port
    [ -f "$1/ports.conf" ] && cp -a "$1/ports.conf" /etc/apache2/ports.conf
    return 0
}

# Apache's HTTPS ports: every bot's own port and every phpMyAdmin port in
# the registry, plus any given here (a bot being set up, not registered
# yet). 443 comes from ports.conf's own Listen, which is switched off -
# marked, so it can come back - while nothing uses 443: with Xray on 443
# Apache would not even start otherwise. Every other port goes in
# $PMA_PORTS_CONF. Opens the ports in UFW when it is active.
ciro_listen_sync() {
    local need p others=""
    need="$* $(jq -r '.[] | select(.domain != null and .domain != "" and .domain != "null" and (.stopped // false) != true) | "\(.port // 443) \(.pma_port // .port // 443)"' "$BOTS_REGISTRY" 2>/dev/null)"
    # a site the registry does not know (a bot of the original project) may be on 443 too
    grep -qsE '^[[:space:]]*<VirtualHost[^>]*:443[[:space:]>]' /etc/apache2/sites-enabled/* && need="$need 443"
    need=$(printf '%s\n' $need | grep -E '^[0-9]+$' | sort -un | tr '\n' ' ')
    for p in $need; do
        [ "$p" != "443" ] && [ "$p" != "80" ] && others="$others $p"
    done
    if [ -n "${others// /}" ]; then
        {
            echo "# Written by the Ciro script: the HTTPS ports a bot or its phpMyAdmin uses besides 443."
            for p in $others; do echo "Listen $p https"; done
        } > "$PMA_PORTS_CONF"
        a2enconf ciro-pma-ports >/dev/null 2>&1
    else
        a2disconf ciro-pma-ports >/dev/null 2>&1
        rm -f "$PMA_PORTS_CONF"
    fi
    if [[ " $need " == *" 443 "* ]]; then
        sed -i -E 's/^([[:space:]]*)#ciro# (Listen 443)/\1\2/' /etc/apache2/ports.conf 2>/dev/null
    else
        sed -i -E 's/^([[:space:]]*)(Listen 443([[:space:]]|$))/\1#ciro# \2/' /etc/apache2/ports.conf 2>/dev/null
    fi
    if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
        for p in $others; do ufw allow "$p/tcp" >/dev/null 2>&1; done
    fi
}

# Write every bot's phpMyAdmin wiring from the registry, then reload Apache.
# A bot whose phpMyAdmin is on its own port (443 unless it moved the bot)
# keeps it inside its own two vhosts, as the installer writes them; a bot
# with phpMyAdmin on another port has it taken out of those and served from
# <domain>-pma.conf instead, on that port with the bot's certificate.
# Apache's config is backed up first and put back if Apache rejects the
# result, so a bad port can never take the webhooks down with it.
pma_sync_all() {
    bots_registry_ensure
    local sa="/etc/apache2/sites-available" rows domain port bport ports="" f p
    # a stopped bot's sites stay switched off - including its phpMyAdmin port
    rows=$(jq -r '.[] | select(.domain != null and .domain != "" and .domain != "null" and (.stopped // false) != true) | "\(.domain) \(.pma_port // .port // 443) \(.port // 443)"' "$BOTS_REGISTRY" 2>/dev/null)
    # Nothing ever moved: leave Apache exactly as the installer wrote it
    if [ -z "$(printf '%s\n' "$rows" | awk '($2 != "" && $2 != 443) || ($3 != "" && $3 != 443)')" ] \
        && [ ! -f "$PMA_PORTS_CONF" ] && ! ls "$sa"/*-pma.conf >/dev/null 2>&1 \
        && ! grep -q '^[[:space:]]*#ciro# Listen 443' /etc/apache2/ports.conf 2>/dev/null; then
        return 0
    fi
    local bak; bak=$(mktemp -d)
    cp -a /etc/apache2/sites-available /etc/apache2/sites-enabled \
          /etc/apache2/conf-available /etc/apache2/conf-enabled "$bak"/
    cp -a /etc/apache2/ports.conf "$bak"/

    while read -r domain port bport; do
        [ -z "$domain" ] && continue
        if [ "$port" != "$bport" ] && [ ! -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
            printf "    ${C_WARN}!${CR} ${C_WARN}%s has no SSL certificate - its phpMyAdmin stays on the bot's address.${CR}\n" "$domain"
            port="$bport"
        fi
        if [ "$port" = "$bport" ]; then
            for f in "$sa/${domain}.conf" "$sa/${domain}-ssl.conf"; do
                [ -f "$f" ] || continue
                grep -qF "Include $PMA_CONF" "$f" && continue
                awk -v inc="    Include $PMA_CONF" '/<\/VirtualHost>/{print inc} {print}' "$f" > "$f.tmp" && mv "$f.tmp" "$f"
            done
            if [ -f "$sa/${domain}-pma.conf" ]; then
                a2dissite "${domain}-pma.conf" >/dev/null 2>&1 </dev/null
                rm -f "$sa/${domain}-pma.conf"
            fi
        else
            for f in "$sa/${domain}.conf" "$sa/${domain}-ssl.conf"; do
                [ -f "$f" ] && sed -i '/^[[:space:]]*Include[[:space:]].*phpmyadmin\.conf/d' "$f"
            done
            tee "$sa/${domain}-pma.conf" > /dev/null <<EOF
<VirtualHost *:${port}>
    ServerName $domain
    DocumentRoot /usr/share/phpmyadmin
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$domain/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$domain/privkey.pem
    Include $PMA_CONF
    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
EOF
            a2ensite "${domain}-pma.conf" >/dev/null 2>&1 </dev/null
            ports="$ports $port"
        fi
    done <<< "$rows"

    if [ -n "${ports// /}" ]; then
        # The phpMyAdmin package enables /phpmyadmin for every site on its
        # own, which would keep it on the bot's port for a bot whose
        # phpMyAdmin just moved away. The others carry their own Include
        # (made sure of above).
        a2disconf phpmyadmin >/dev/null 2>&1
    fi
    # every port a bot or a phpMyAdmin is on - 443 too, or not at all
    ciro_listen_sync
    ports=$(sed -n 's/^Listen \([0-9]*\) https$/\1/p' "$PMA_PORTS_CONF" 2>/dev/null | tr '\n' ' ')

    local out
    if ! out=$(apache2ctl configtest 2>&1); then
        _pma_restore "$bak"; rm -rf "$bak"
        printf "    ${C_BAD}●${CR} ${C_BAD}Apache rejected the new config - it was put back as it was.${CR}\n"
        printf '%s\n' "$out" | tail -n 5 | sed 's/^/      /'
        return 1
    fi
    systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1
    sleep 1
    # A reload does not always open a brand-new Listen port, nor let go of
    # 443 for the program that needs it; a restart does
    for p in $ports; do
        if [ -z "$(ss -ltn "( sport = :$p )" 2>/dev/null | tail -n +2)" ]; then
            systemctl restart apache2 >/dev/null 2>&1; sleep 1; break
        fi
    done
    if ! systemctl is-active --quiet apache2; then
        _pma_restore "$bak"; rm -rf "$bak"
        systemctl restart apache2 >/dev/null 2>&1
        printf "    ${C_BAD}●${CR} ${C_BAD}Apache would not start with the new port - the old setup is back.${CR}\n"
        return 1
    fi
    rm -rf "$bak"
    return 0
}

# pma_set_port DIR PORT - store it and apply it; on failure the old port
# is stored again (pma_sync_all has already put Apache back).
pma_set_port() {
    local dir="$1" port="$2" old
    old=$(bots_registry_pma_port "$dir")
    bots_registry_set_pma_port "$dir" "$port"
    pma_sync_all && return 0
    bots_registry_set_pma_port "$dir" "$old"
    return 1
}

_back_prompt() {
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to go back... "
    read -r _
}

# ALTER USER statements giving USER the password PASS on every host MySQL
# has it on ($3 = "host<TAB>plugin" rows), each keeping its own auth plugin.
_db_alter_sql() {
    local user="$1" pass="$2" h plugin sql=""
    while IFS=$'\t' read -r h plugin; do
        [[ "$h" =~ ^[A-Za-z0-9._%:-]+$ ]] && [[ "$plugin" =~ ^[a-z0-9_]+$ ]] || continue
        sql+="ALTER USER '${user}'@'${h}' IDENTIFIED WITH ${plugin} BY '${pass}'; "
    done <<< "$3"
    echo "$sql"
}

# db_set_password CONFIG NEWPASS - a new password for the bot's MySQL user,
# in MySQL and in config.php together. If config.php cannot take it, or the
# bot cannot log in with it, MySQL gets the old one back and config.php is
# restored, so the bot is never left holding a password that fails.
# Prints the reason and returns 1 on failure.
db_set_password() {
    local cfg="$1" new="$2" dbuser dbname dbhost old rootpass rows sql bak err
    dbuser=$(grep '^\$usernamedb' "$cfg" | cut -d"'" -f2)
    dbname=$(grep '^\$dbname' "$cfg" | cut -d"'" -f2)
    dbhost=$(grep '^\$dbhost' "$cfg" | cut -d"'" -f2)
    old=$(grep '^\$passworddb' "$cfg" | cut -d"'" -f2)
    rootpass=$(grep '$pass' /root/Ciro/dbroot.txt 2>/dev/null | cut -d"'" -f2)
    [ -z "$dbhost" ] && dbhost="localhost"
    if ! valid_db_ident "$dbuser"; then echo "config.php has no usable database username."; return 1; fi
    if [ -z "$rootpass" ]; then echo "Could not read the MySQL root password."; return 1; fi
    rows=$(mysql -u root -p"$rootpass" -N -B -e "SELECT host, plugin FROM mysql.user WHERE user='${dbuser}';" 2>/dev/null)
    sql=$(_db_alter_sql "$dbuser" "$new" "$rows")
    if [ -z "$sql" ]; then echo "MySQL has no user named ${dbuser} (or the root login failed)."; return 1; fi
    if ! err=$(mysql -u root -p"$rootpass" -e "$sql" 2>&1); then
        echo "MySQL refused the change: $(printf '%s\n' "$err" | grep -v 'password on the command line' | tail -n 1)"
        return 1
    fi
    bak=$(mktemp); cp -p "$cfg" "$bak"
    sed -i -E "s/^(\\\$passworddb[[:space:]]*=[[:space:]]*')[^']*'/\\1${new}'/" "$cfg"
    if [ "$(grep '^\$passworddb' "$cfg" | cut -d"'" -f2)" != "$new" ] \
        || ! mysql -h "$dbhost" -u "$dbuser" -p"$new" -e "SELECT 1;" "$dbname" >/dev/null 2>&1; then
        cat "$bak" > "$cfg"; rm -f "$bak"
        [ -n "$old" ] && mysql -u root -p"$rootpass" -e "$(_db_alter_sql "$dbuser" "$old" "$rows")" >/dev/null 2>&1
        echo "The bot could not log in with the new password, so the old one was put back."
        return 1
    fi
    rm -f "$bak"
    # mod_php may still hold the old config.php in its opcode cache
    systemctl reload apache2 >/dev/null 2>&1
    return 0
}

db_show_password() {
    echo ""
    _kv "Password" "${C_KEY}$(grep '^\$passworddb' "$1" | cut -d"'" -f2)${CR}"
    printf "    ${C_DIM}Only root can open this script, and config.php already holds this${CR}\n"
    printf "    ${C_DIM}password - still, keep it out of screenshots and shared screens.${CR}\n"
    _back_prompt
}

db_change_password() {
    local cfg="$1" new out
    echo ""
    printf "    ${C_DIM}6 to 64 characters: letters, digits and _ only.${CR}\n"
    while true; do
        printf "  ${C_PROMPT}❯${CR} New password ${C_DIM}[Enter = random, 0 = back]${CR}: "
        read -r new
        [ "$new" = "0" ] && return 0
        [ -z "$new" ] && new=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | cut -c1-16)
        valid_db_pass "$new" && break
        printf "    ${C_BAD}●${CR} ${C_BAD}Use 6 to 64 characters: letters, digits and _ only.${CR}\n"
    done
    echo ""
    if out=$(db_set_password "$cfg" "$new"); then
        _kv "Password" "${C_OK}${new}${CR}"
        printf "    ${C_OK}●${CR} ${C_OK}Changed in MySQL and in config.php - the bot keeps working.${CR}\n"
        printf "    ${C_DIM}Use it to log in to phpMyAdmin from now on. Save it somewhere safe.${CR}\n"
    else
        printf "    ${C_BAD}●${CR} ${C_BAD}%s${CR}\n" "$out"
        printf "    ${C_DIM}The password was not changed.${CR}\n"
    fi
    _back_prompt
}

# Move this bot's phpMyAdmin to another port, or back to the bot's own.
pma_port_screen() {
    local dir="$1" domain="$2" cur p why bp
    echo ""
    if [ -z "$domain" ] || [ "$domain" = "null" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}No domain is on record for this bot.${CR}\n"
        _back_prompt; return 1
    fi
    cur=$(bots_registry_pma_port "$dir")
    bp=$(bots_registry_bot_port "$dir")
    printf "    ${C_DIM}%s is the default: the bot's own port (it can be the same). The webhook${CR}\n" "$bp"
    printf "    ${C_DIM}stays on %s whatever you pick, and several bots may share one port.${CR}\n" "$bp"
    echo ""
    while true; do
        printf "  ${C_PROMPT}❯${CR} New port ${C_DIM}[now %s, 0 = back]${CR}: " "$cur"
        read -r p
        if [ -z "$p" ] || [ "$p" = "0" ]; then return 0; fi
        if ! why=$(pma_port_problem "$p" "$bp"); then
            printf "    ${C_BAD}●${CR} ${C_BAD}%s${CR}\n" "$why"; continue
        fi
        if [ "$p" != "$bp" ] && [ ! -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
            printf "    ${C_BAD}●${CR} ${C_BAD}%s has no SSL certificate yet - renew it first (menu 5).${CR}\n" "$domain"; continue
        fi
        break
    done
    echo ""
    if [ "$p" = "$cur" ]; then
        printf "    ${C_DIM}Already on port %s - nothing to change.${CR}\n" "$p"
    elif pma_set_port "$dir" "$p"; then
        printf "    ${C_OK}●${CR} ${C_OK}phpMyAdmin is now at %s${CR}\n" "$(pma_url "$domain" "$p")"
        [ "$p" != "443" ] && [ "$p" != "$bp" ] && printf "    ${C_DIM}If your server provider has its own firewall, open port %s there too.${CR}\n" "$p"
    else
        printf "    ${C_BAD}●${CR} ${C_BAD}Port %s could not be used - phpMyAdmin is still at %s${CR}\n" "$p" "$(pma_url "$domain" "$cur")"
    fi
    _back_prompt
}

# Menu 9 - one bot's database: its login, its password, its phpMyAdmin port.
function database_menu() {
    clear 2>/dev/null || true
    banner
    _sec "Database"
    if ! pick_bot_instance "manage the database of"; then
        [ "$(bots_registry_count)" = "0" ] && sleep 2
        show_menu; return 0
    fi
    local dir="$PICKED_DIR" name="$PICKED_NAME" domain="$PICKED_DOMAIN" cfg="$PICKED_DIR/config.php" choice
    if [ ! -f "$cfg" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}config.php not found in %s.${CR}\n" "$dir"
        sleep 2; show_menu; return 1
    fi
    while true; do
        clear 2>/dev/null || true
        banner
        _sec "Database · ${name}"
        _kv "phpMyAdmin" "${C_DIM}$(pma_url "$domain" "$(bots_registry_pma_port "$dir")")${CR}"
        _kv "Database" "${C_KEY}$(grep '^\$dbname' "$cfg" | cut -d"'" -f2)${CR}"
        _kv "Username" "${C_KEY}$(grep '^\$usernamedb' "$cfg" | cut -d"'" -f2)${CR}"
        _kv "Password" "${C_DIM}hidden · option 1 shows it${CR}"
        echo ""
        _mi "1" "Show password"
        _mi "2" "Change phpMyAdmin password"
        _mi "3" "Change phpMyAdmin port"
        _mi "0" "Back"
        _rule
        echo ""
        printf "  ${C_PROMPT}❯${CR} Choose ${C_DIM}[0-3]${CR}: "
        read -r choice
        case "$choice" in
            1) db_show_password "$cfg" ;;
            2) db_change_password "$cfg" ;;
            3) pma_port_screen "$dir" "$domain" ;;
            0) show_menu; return 0 ;;
        esac
    done
}

# Whole-server pre-flight before installing

preflight() {
    local ok=1
    _sec "Pre-flight checks"

    if command -v apt-get >/dev/null 2>&1; then
        _kv "Package mgr" "$(_dot ok) ${C_OK}apt detected${CR}"
    else
        _kv "Package mgr" "$(_dot bad) ${C_BAD}apt not found (Ubuntu/Debian required)${CR}"; ok=0
    fi

    # Supported: Ubuntu 22.04 / 24.04 / 26.04 (newer releases pass with a note).
    detect_os
    local maj; maj=$(os_major)
    if [ "$OS_ID" = "ubuntu" ]; then
        case "$OS_VERSION_ID" in
            22.04|24.04|26.04) _kv "OS" "$(_dot ok) ${C_OK}${OS_PRETTY}${CR}" ;;
            *)
                if [ "$maj" -ge 26 ]; then
                    _kv "OS" "$(_dot ok) ${C_OK}${OS_PRETTY}${CR} ${C_DIM}(newer than tested)${CR}"
                elif [ "$maj" -ge 20 ]; then
                    _kv "OS" "$(_dot warn) ${C_WARN}${OS_PRETTY} (untested; 22.04/24.04/26.04 recommended)${CR}"
                elif [ "$maj" -eq 0 ]; then
                    # No usable VERSION_ID (dev snapshot, trimmed image): warn, don't block.
                    _kv "OS" "$(_dot warn) ${C_WARN}${OS_PRETTY} (version unknown; 22.04/24.04/26.04 recommended)${CR}"
                else
                    _kv "OS" "$(_dot bad) ${C_BAD}${OS_PRETTY} (too old; use 22.04, 24.04 or 26.04)${CR}"; ok=0
                fi
                ;;
        esac
    elif [ "$OS_ID" = "debian" ]; then
        _kv "OS" "$(_dot warn) ${C_WARN}${OS_PRETTY} (untested; Ubuntu 22.04/24.04/26.04 recommended)${CR}"
    else
        _kv "OS" "$(_dot warn) ${C_WARN}${OS_PRETTY:-unknown} (untested)${CR}"
    fi

    local arch; arch=$(uname -m)
    case "$arch" in
        x86_64|amd64|aarch64|arm64) _kv "Arch" "$(_dot ok) ${C_OK}${arch}${CR}" ;;
        *) _kv "Arch" "$(_dot warn) ${C_WARN}${arch} (untested)${CR}" ;;
    esac

    local free_mb; free_mb=$(df -Pm / 2>/dev/null | awk 'NR==2{print $4}')
    if [ "${free_mb:-0}" -ge 2048 ]; then
        _kv "Disk free" "$(_dot ok) ${C_OK}${free_mb} MB${CR}"
    else
        _kv "Disk free" "$(_dot bad) ${C_BAD}${free_mb:-0} MB (need >= 2048 MB)${CR}"; ok=0
    fi

    local mem; mem=$(free -m 2>/dev/null | awk '/^Mem:/{print $2}')
    if [ "${mem:-0}" -ge 900 ]; then
        _kv "RAM" "$(_dot ok) ${C_OK}${mem} MB${CR}"
    else
        _kv "RAM" "$(_dot warn) ${C_WARN}${mem:-0} MB (low; MySQL may struggle)${CR}"
    fi

    if ensure_connectivity; then
        _kv "Network" "$(_dot ok) ${C_OK}online${CR}"
    else
        _kv "Network" "$(_dot bad) ${C_BAD}offline (cannot reach GitHub/Telegram)${CR}"; ok=0
    fi

    local b80 w443
    b80=$(ss -ltnH 'sport = :80' 2>/dev/null | head -1)
    w443=$(ss -ltnpH 'sport = :443' 2>/dev/null | grep -o 'users:(("[^"]*"' | head -1 | cut -d'"' -f2)
    if [ -n "$b80" ]; then
        _kv "Port 80" "$(_dot warn) ${C_WARN}in use (will be freed for Apache/SSL)${CR}"
    else
        _kv "Port 80" "$(_dot ok) ${C_OK}free${CR}"
    fi
    if [ -z "$w443" ] || [ "$w443" = "apache2" ]; then
        _kv "Port 443" "$(_dot ok) ${C_OK}free for the bot${CR}"
    else
        _kv "Port 443" "$(_dot warn) ${C_WARN}used by ${w443}${CR} ${C_DIM}· the bot can take 8443 or 88 (asked later)${CR}"
    fi

    if [ "$ok" -ne 1 ]; then
        echo ""
        echo -e "  ${C_BAD}●${CR} ${C_BAD}Pre-flight checks failed. Aborting to avoid a broken install.${CR}"
        return 1
    fi
    return 0
}

function install_bot() {
    BOT_DIR="/var/www/html/Ciro"
    PHP_VER="$(state_get PHP_VER)"
    [ -z "$PHP_VER" ] && PHP_VER="8.2"

    # the original Mirza bot is here: it becomes this Ciro bot, data and all
    if ! has_resumable_state && auto_migrate_original; then
        return 0
    fi

    # ── Guard: only block when a PREVIOUS install fully COMPLETED ──
    local _installed=""
    [ -f "$LEGACY_BOT_DIR/config.php" ] && _installed="$LEGACY_BOT_DIR"
    [ -f "$CONFIG_FILE_DEFAULT" ] && _installed="$BOT_DIR_DEFAULT"
    if [ -n "$_installed" ] && ! has_resumable_state; then
        clear 2>/dev/null || true
        banner
        _sec "Install blocked"
        printf "    ${C_BAD}●${CR} ${C_BAD}Ciro is already installed on this server.${CR}\n"
        printf "    ${C_DIM}Path:${CR} %s\n" "$_installed"
        echo ""
        echo ""
        printf "    ${C_KEY}[1]${CR} ${C_TXT}Update it instead${CR}\n"
        printf "    ${C_KEY}[2]${CR} ${C_TXT}Remove it first, then reinstall${CR}\n"
        printf "    ${C_KEY}[3]${CR} ${C_TXT}Install another, independent bot on this server${CR}\n"
        printf "    ${C_KEY}[0]${CR} ${C_TXT}Back to menu${CR}\n"
        echo ""
        printf "  ${C_PROMPT}❯${CR} Choose ${C_DIM}[0-3]${CR}: "
        read -r _blocked_choice
        case "$_blocked_choice" in
            1) update_bot; return 0 ;;
            2) remove_bot; return 0 ;;
            3) install_second_bot; return 0 ;;
            *) show_menu; return 1 ;;
        esac
    fi
    # ── Fresh-server requirement (only on a brand-new install) ──
    if ! has_resumable_state && [ ! -f "$CONFIG_FILE_DEFAULT" ]; then
        if ! precheck_fresh_server; then
            echo ""
            printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
            read -r _
            show_menu
            return 1
        fi
    fi

    # ── Resume detector: an unfinished install is on disk ──
    if has_resumable_state; then
        clear 2>/dev/null || true
        banner
        _sec "Resume install"
        local _last
        _last="$(grep '^PHASE:' "$STATE_FILE" 2>/dev/null | tail -1 | cut -d: -f2)"
        [ -z "$_last" ] && _last="dependencies"
        printf "    ${C_WARN}●${CR} ${C_WARN}An unfinished installation was found.${CR}\n"
        printf "    ${C_DIM}Last completed step:${CR} ${C_KEY}%s${CR}\n" "$_last"
        echo ""
        printf "    ${C_KEY}[1]${CR} ${C_TXT}Resume from where it stopped${CR}\n"
        printf "    ${C_KEY}[2]${CR} ${C_TXT}Start fresh from the beginning${CR}\n"
        printf "    ${C_KEY}[0]${CR} ${C_TXT}Back to menu${CR}\n"
        echo ""
        printf "  ${C_PROMPT}❯${CR} Your choice: "
        read -r _resume_choice
        case "$_resume_choice" in
            2)
                state_clear
                [ -d "$BOT_DIR" ] && sudo rm -rf "$BOT_DIR"
                echo -e "  ${C_DIM}Starting from scratch...${CR}"; sleep 1 ;;
            0) show_menu; return 0 ;;
            *) echo -e "  ${C_OK}●${CR} ${C_OK}Resuming installation from the last step...${CR}"; sleep 1 ;;
        esac
    fi
    state_init
    state_set STARTED 1   # mark install as in-progress -> future re-runs resume (skip fresh-check)
    plan_eta   # count pending steps + estimate total time left

    # ── Pre-flight checks (network/DNS/disk/ram/ports) ──
    clear 2>/dev/null || true
    banner
    if ! preflight; then
        echo ""
        printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
        read -r _
        show_menu
        return 1
    fi

    # ╭──────────────────────── PHASE: DEPS ────────────────────────╮
    if ! phase_done DEPS; then
        # Choose which version to install (only needed before files are fetched)
        echo ""
        choose_source
        local _rc=$?
        if [ "$_rc" -eq 2 ]; then show_menu; return 0; fi
        if [ "$_rc" -ne 0 ]; then sleep 2; show_menu; return 1; fi
        state_set SRC_ZIP_URL "$SRC_ZIP_URL"
        state_set SRC_LABEL "$SRC_LABEL"
        echo ""
        echo -e "  ${C_DIM}Install target:${CR} ${C_KEY}${SRC_LABEL}${CR}"
        sleep 1

        print_header "Installing Dependencies"

        run_step "Preparing package manager (clearing stale apt locks)" "apt_recover" \
            || { show_step_error; install_pause "Preparing package manager"; }

        if ! run_step "Adding PHP repository (ondrej/php)" "setup_php_repo"; then
            if ! run_step "Retrying PHP repository with locale override" "LC_ALL=C.UTF-8 setup_php_repo"; then
                show_step_error
                install_pause "Adding PHP repository"
            fi
        fi

        if ! run_step "Updating & upgrading system packages" "apt-get update --allow-releaseinfo-change -o DPkg::Lock::Timeout=180 && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -o DPkg::Lock::Timeout=180"; then
            echo -e "\e[93mUpdate/upgrade failed. Attempting to fix using alternative mirrors...\033[0m"
            if fix_update_issues; then
                if ! run_step "Re-running system update after mirror fix" "apt-get update --allow-releaseinfo-change -o DPkg::Lock::Timeout=180 && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -o DPkg::Lock::Timeout=180"; then
                    show_step_error
                    install_pause "System update/upgrade"
                fi
            else
                install_pause "System update/upgrade (mirror fix failed)"
            fi
        fi

        run_step "Installing base tools (git, curl, wget, zip, unzip, jq)" \
            "apt-get install -y software-properties-common git zip unzip curl wget jq" \
            || { show_step_error; install_pause "Installing base tools"; }

        PHP_VER="$(resolve_php_ver)"; [ -z "$PHP_VER" ] && PHP_VER="8.2"
        state_set PHP_VER "$PHP_VER"
        echo -e "  ${C_DIM}Selected PHP version:${CR} ${C_KEY}${PHP_VER}${CR}"

        run_step "Installing PHP ${PHP_VER} (fpm + mysql)" \
            "DEBIAN_FRONTEND=noninteractive apt install -y php${PHP_VER} php${PHP_VER}-cli php${PHP_VER}-fpm php${PHP_VER}-mysql" \
            || { show_step_error; install_pause "Installing PHP ${PHP_VER}"; }

        # Versioned packages only: unversioned (php-*, lamp-server^) would pull the
        # newest PHP available as default and break the bot's mysqli/curl.
        WEBSTACK_CMD="DEBIAN_FRONTEND=noninteractive apt install -y mysql-server apache2 libapache2-mod-php${PHP_VER} php${PHP_VER}-mbstring php${PHP_VER}-zip php${PHP_VER}-gd php${PHP_VER}-curl php${PHP_VER}-intl php${PHP_VER}-xml php${PHP_VER}-bcmath"
        if ! run_step "Installing web stack (Apache, MySQL, PHP modules)" "$WEBSTACK_CMD"; then
            # Most common cause: a broken/half-configured MySQL from an interrupted run.
            # Safe to repair here because the fresh-server check ran and no DB exists yet.
            run_step "Repairing broken MySQL installation" "repair_mysql" \
                || { show_step_error; install_pause "Repairing MySQL"; }
            run_step "Re-installing web stack" "$WEBSTACK_CMD" \
                || { show_step_error; install_pause "Installing web stack"; }
        fi

        local _other_php="" _pv
        for _pv in 8.5 8.4 8.3 8.2 8.1 8.0 7.4; do
            [ "$_pv" = "$PHP_VER" ] || _other_php="$_other_php php$_pv"
        done
        run_step "Setting PHP ${PHP_VER} as the active version" \
            "a2dismod${_other_php} mpm_event mpm_worker 2>/dev/null; a2enmod php${PHP_VER} mpm_prefork 2>/dev/null; update-alternatives --set php /usr/bin/php${PHP_VER} 2>/dev/null; systemctl restart apache2" \
            || { show_step_error; install_pause "Setting PHP ${PHP_VER} as default"; }

        echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | sudo debconf-set-selections
        echo 'phpmyadmin phpmyadmin/app-password-confirm password cirohipass' | sudo debconf-set-selections
        echo 'phpmyadmin phpmyadmin/mysql/admin-pass password cirohipass' | sudo debconf-set-selections
        echo 'phpmyadmin phpmyadmin/mysql/app-pass password cirohipass' | sudo debconf-set-selections
        echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2' | sudo debconf-set-selections
        run_step "Installing phpMyAdmin" \
            "DEBIAN_FRONTEND=noninteractive apt-get install -y phpmyadmin" \
            || { show_step_error; install_pause "Installing phpMyAdmin"; }

        if [ -f /etc/apache2/conf-available/phpmyadmin.conf ]; then
            sudo rm -f /etc/apache2/conf-available/phpmyadmin.conf
        fi
        sudo ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf || {
            echo -e "\e[91mError: Failed to create symbolic link for phpMyAdmin configuration.\033[0m"
            install_pause "phpMyAdmin symlink"
        }

        # PHP defaults to a 2M upload limit, which rejects the bot's own DB backups
        # in phpMyAdmin ("No data was received to import") once the site has any real data.
        BUMP_PHP_LIMITS_CMD="for f in \"/etc/php/${PHP_VER}/apache2/php.ini\" \"/etc/php/${PHP_VER}/fpm/php.ini\"; do [ -f \"\$f\" ] || continue; sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 200M/' \"\$f\"; sed -i 's/^post_max_size = .*/post_max_size = 200M/' \"\$f\"; sed -i 's/^memory_limit = .*/memory_limit = 256M/' \"\$f\"; sed -i 's/^max_execution_time = .*/max_execution_time = 300/' \"\$f\"; sed -i 's/^max_input_time = .*/max_input_time = 300/' \"\$f\"; done; systemctl restart apache2; systemctl restart php${PHP_VER}-fpm 2>/dev/null || true"
        run_step "Raising PHP upload/post size limits (so phpMyAdmin backup restores don't fail)" \
            "$BUMP_PHP_LIMITS_CMD" \
            || { show_step_error; install_pause "Raising PHP upload limits"; }

        run_step "Installing extra modules (php-soap, php-ssh2, libssh2)" \
            "DEBIAN_FRONTEND=noninteractive apt-get install -y php${PHP_VER}-soap php${PHP_VER}-ssh2 libssh2-1-dev libssh2-1" \
            || { show_step_error; install_pause "Installing extra PHP modules"; }

        run_step "Enabling & starting services (MySQL, Apache)" \
            "systemctl enable mysql.service && systemctl start mysql.service && systemctl enable apache2 && systemctl start apache2" \
            || { show_step_error; install_pause "Enabling core services"; }

        run_step "Configuring firewall (UFW + Apache)" \
            "apt-get install -y ufw && ufw allow 'Apache'" \
            || { show_step_error; install_pause "Configuring UFW"; }
        run_step "Restarting Apache" "systemctl restart apache2" \
            || { show_step_error; install_pause "Restarting Apache"; }

        mark_phase DEPS
    else
        echo -e "  ${C_OK}●${CR} ${C_DIM}Dependencies already installed - skipping.${CR}"
    fi
    # ╰─────────────────────────────────────────────────────────────╯

    # ╭──────────────────────── PHASE: FILES ───────────────────────╮
    if ! phase_done FILES; then
        print_header "Downloading Bot Files"
        ZIP_URL="$(state_get SRC_ZIP_URL)"; [ -z "$ZIP_URL" ] && ZIP_URL="$SRC_ZIP_URL"
        SRC_LABEL_RESUME="$(state_get SRC_LABEL)"; [ -z "$SRC_LABEL_RESUME" ] && SRC_LABEL_RESUME="$SRC_LABEL"
        if [ -d "$BOT_DIR" ]; then
            sudo rm -rf "$BOT_DIR" || {
                echo -e "\e[91mError: Failed to remove existing directory $BOT_DIR.\033[0m"
                install_pause "Cleaning bot directory"
            }
        fi
        sudo mkdir -p "$BOT_DIR"
        if [ ! -d "$BOT_DIR" ]; then
            echo -e "\e[91mError: Failed to create directory $BOT_DIR.\033[0m"
            install_pause "Creating bot directory"
        fi

        TEMP_DIR="/tmp/cirobot"
        rm -rf "$TEMP_DIR"; mkdir -p "$TEMP_DIR"
        run_step "Downloading Ciro (${SRC_LABEL_RESUME})" "wget -O '$TEMP_DIR/bot.zip' '$ZIP_URL'" \
            || { show_step_error; install_pause "Downloading bot files"; }
        run_step "Extracting source files" "unzip -o '$TEMP_DIR/bot.zip' -d '$TEMP_DIR'" \
            || { show_step_error; install_pause "Extracting bot files"; }

        EXTRACTED_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -1)
        if [ -z "$EXTRACTED_DIR" ] || [ ! -d "$EXTRACTED_DIR" ]; then
            echo -e "\e[91mError: Extracted source folder not found (bad or empty download).\033[0m"
            install_pause "Locating extracted files"
        fi
        mv "$EXTRACTED_DIR"/* "$BOT_DIR" || {
            echo -e "\e[91mError: Failed to move extracted files.\033[0m"
            install_pause "Moving bot files"
        }
        rm -rf "$TEMP_DIR"
        sudo chown -R www-data:www-data "$BOT_DIR"
        sudo chmod -R 755 "$BOT_DIR"
        wait
        run_step "Installing PHP dependencies (composer)" "install_php_deps '$BOT_DIR'" \
            || { show_step_error; install_pause "Installing PHP dependencies"; }
        mark_phase FILES
    else
        echo -e "  ${C_OK}●${CR} ${C_DIM}Bot files already downloaded - skipping.${CR}"
    fi
    # ╰─────────────────────────────────────────────────────────────╯

    # ╭──────────────────────── PHASE: DBROOT ──────────────────────╮
    if ! phase_done DBROOT; then
        if [ ! -f "/root/Ciro/dbroot.txt" ] || ! grep -q '\$pass' /root/Ciro/dbroot.txt 2>/dev/null; then
            run_step "Configuring MySQL root access" "setup_mysql_root" \
                || { show_step_error; install_pause "MySQL root setup"; }
        fi
        mark_phase DBROOT
    fi
    # ╰─────────────────────────────────────────────────────────────╯

    # ── Domain capture (needed for SSL, VHost, config & webhook) ──
    clear 2>/dev/null || true
    print_header "SSL Certificate Setup"
    domainname="$(state_get DOMAIN)"
    if [ -n "$domainname" ]; then
        echo -e "  ${C_DIM}Domain (resumed):${CR} ${C_KEY}${domainname}${CR}"
    else
        if [ -n "$ARG_DOMAIN" ]; then
            domainname="$ARG_DOMAIN"
            echo -e "  ${C_DIM}Domain (from --domain):${CR} ${C_KEY}${domainname}${CR}"
        else
            read -p "Enter the domain: " domainname
        fi
        while ! validate_domain "$domainname"; do
            echo -e "\e[91mInvalid domain. Enter a full domain like bot.example.com (no http://, no slash).\033[0m"
            read -p "Enter the domain: " domainname
        done
        # Verify the domain actually points to this server (certbot needs this)
        domain_points_here "$domainname"
        case $? in
            0) echo -e "  ${C_OK}●${CR} ${C_OK}Domain resolves to this server.${CR}" ;;
            1) echo -e "  ${C_WARN}!${CR} ${C_WARN}Domain does NOT point to this server's IP ($(get_server_ip)).${CR}"
               echo -e "  ${C_DIM}Let's Encrypt will fail until the DNS A record points here.${CR}"
               printf "  ${C_PROMPT}❯${CR} Continue anyway? ${C_DIM}[y/N]${CR}: "
               read -r _gd
               if [[ ! "$_gd" =~ ^[Yy]$ ]]; then echo -e "  ${C_BAD}Aborted. Fix the DNS A record and retry.${CR}"; sleep 1; show_menu; return 1; fi ;;
            2) echo -e "  ${C_WARN}!${CR} ${C_WARN}Could not resolve the domain yet (DNS may still be propagating).${CR}"
               printf "  ${C_PROMPT}❯${CR} Continue anyway? ${C_DIM}[y/N]${CR}: "
               read -r _gd
               if [[ ! "$_gd" =~ ^[Yy]$ ]]; then echo -e "  ${C_BAD}Aborted.${CR}"; sleep 1; show_menu; return 1; fi ;;
        esac
        state_set DOMAIN "$domainname"
    fi
    DOMAIN_NAME="$domainname"
    PATHS=$(cat /root/Ciro/dbroot.txt | grep '$path' | cut -d"'" -f2)

    # ── The bot's port: 443, or 8443/88 when something (Xray) holds 443 ──
    BOT_PORT="$(state_get BOT_PORT)"
    # an install begun by an older version of this script set Apache up on 443
    [ -z "$BOT_PORT" ] && phase_done VHOST && { BOT_PORT=443; state_set BOT_PORT 443; }
    if [ -n "$BOT_PORT" ]; then
        echo -e "\e[33m[+] \e[36mBot port (resumed):\e[0m ${BOT_PORT}"
    else
        ask_bot_port
        state_set BOT_PORT "$BOT_PORT"
    fi

    # ╭──────────────────────── PHASE: SSL ─────────────────────────╮
    if ! phase_done SSL; then
        if [ -f "/etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem" ]; then
            echo -e "  ${C_OK}●${CR} ${C_DIM}SSL certificate for ${DOMAIN_NAME} already exists - skipping issuance.${CR}"
        else
            run_step "Opening firewall ports 80 & ${BOT_PORT}" "ufw allow 80 && ufw allow ${BOT_PORT}" \
                || { show_step_error; install_pause "Opening firewall ports"; }
            run_step "Stopping Apache for certificate issuance" "systemctl stop apache2 && systemctl disable apache2" \
                || { show_step_error; install_pause "Stopping Apache"; }
            run_step "Installing Let's Encrypt (certbot)" "apt install letsencrypt -y && systemctl enable certbot.timer" \
                || { show_step_error; install_pause "Installing certbot"; }

            run_step "Requesting SSL certificate (Let's Encrypt)" \
                "certbot certonly --standalone --non-interactive --agree-tos --register-unsafely-without-email --preferred-challenges http -d $DOMAIN_NAME" \
                || { show_step_error; install_pause "Requesting SSL certificate"; }
        fi
        run_step "Enabling & starting Apache" "systemctl enable apache2 && systemctl start apache2" \
            || { show_step_error; install_pause "Starting Apache"; }
        mark_phase SSL
    else
        echo -e "  ${C_OK}●${CR} ${C_DIM}SSL certificate already configured - skipping.${CR}"
    fi
    # ╰─────────────────────────────────────────────────────────────╯

    # ╭──────────────────────── PHASE: VHOST ───────────────────────╮
    if ! phase_done VHOST; then
        VHOST_FILE="/etc/apache2/sites-available/${DOMAIN_NAME}.conf"
        sudo tee "$VHOST_FILE" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN_NAME
    DocumentRoot $BOT_DIR
    <Directory $BOT_DIR>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    Include /etc/apache2/conf-available/phpmyadmin.conf
    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN_NAME}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN_NAME}-access.log combined
</VirtualHost>
EOF
        VHOST_SSL_FILE="/etc/apache2/sites-available/${DOMAIN_NAME}-ssl.conf"
        sudo tee "$VHOST_SSL_FILE" > /dev/null <<EOF
<VirtualHost *:${BOT_PORT}>
    ServerName $DOMAIN_NAME
    DocumentRoot $BOT_DIR
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN_NAME/privkey.pem
    <Directory $BOT_DIR>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    Include /etc/apache2/conf-available/phpmyadmin.conf
    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN_NAME}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN_NAME}-access.log combined
</VirtualHost>
EOF
        # Apache listens on the bot's port - and leaves 443 alone when the
        # bot is not on it, or Apache could not start beside Xray at all
        ciro_listen_sync "$BOT_PORT"
        run_step "Configuring Apache virtual hosts" \
            "a2ensite '${DOMAIN_NAME}.conf' && a2ensite '${DOMAIN_NAME}-ssl.conf' ; a2dissite 000-default.conf 2>/dev/null ; a2dissite 000-default-le-ssl.conf 2>/dev/null ; a2dissite default-ssl.conf 2>/dev/null ; rm -f /etc/apache2/sites-enabled/000-default.conf /etc/apache2/sites-enabled/000-default-le-ssl.conf /etc/apache2/sites-enabled/default-ssl.conf ; rm -f /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default-le-ssl.conf /etc/apache2/sites-available/default-ssl.conf ; a2enmod ssl ; a2enmod rewrite ; systemctl restart apache2" \
            || { show_step_error; install_pause "Configuring Apache virtual hosts"; }
        mark_phase VHOST
    else
        echo -e "  ${C_OK}●${CR} ${C_DIM}Apache virtual hosts already configured - skipping.${CR}"
    fi
    # ╰─────────────────────────────────────────────────────────────╯

    # ── Bot configuration inputs (token / chat id / botname) ──
    clear 2>/dev/null || true
    print_header "Bot Configuration"
    YOUR_BOT_TOKEN="$(state_get BOT_TOKEN)"
    if [ -n "$YOUR_BOT_TOKEN" ]; then
        echo -e "\e[33m[+] \e[36mBot Token (resumed):\e[0m ${YOUR_BOT_TOKEN:0:10}..."
    else
        if [ -n "$ARG_TOKEN" ]; then
            YOUR_BOT_TOKEN="$ARG_TOKEN"
            echo -e "\e[33m[+] \e[36mBot Token (from --token):\e[0m ${YOUR_BOT_TOKEN:0:10}..."
        else
            printf "\e[33m[+] \e[36mBot Token: \033[0m"
            read YOUR_BOT_TOKEN
        fi
        while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; do
            echo -e "\e[91mInvalid bot token format. Please try again.\033[0m"
            printf "\e[33m[+] \e[36mBot Token: \033[0m"
            read YOUR_BOT_TOKEN
        done
        # Live-verify the token with Telegram (getMe)
        while true; do
            validate_token "$YOUR_BOT_TOKEN"
            case $? in
                0) echo -e "  ${C_OK}●${CR} ${C_OK}Token verified with Telegram.${CR}"; break ;;
                2) echo -e "  ${C_BAD}●${CR} ${C_BAD}Telegram rejected this token (or API unreachable).${CR}"
                   printf "  ${C_PROMPT}❯${CR} Re-enter token, or press Enter to keep it anyway: "
                   read -r _t
                   if [ -z "$_t" ]; then break; fi
                   YOUR_BOT_TOKEN="$_t"
                   while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; do
                       echo -e "\e[91mInvalid format.\033[0m"; printf "  ${C_PROMPT}❯${CR} Bot Token: "; read -r YOUR_BOT_TOKEN
                   done ;;
                *) break ;;
            esac
        done
        state_set BOT_TOKEN "$YOUR_BOT_TOKEN"
    fi

    YOUR_CHAT_ID="$(state_get CHAT_ID)"
    if [ -n "$YOUR_CHAT_ID" ]; then
        echo -e "\e[33m[+] \e[36mChat id (resumed):\e[0m ${YOUR_CHAT_ID}"
    else
        if [ -n "$ARG_ADMIN" ]; then
            YOUR_CHAT_ID="$ARG_ADMIN"
            echo -e "\e[33m[+] \e[36mChat id (from --admin):\e[0m ${YOUR_CHAT_ID}"
        else
            printf "\e[33m[+] \e[36mChat id: \033[0m"
            read YOUR_CHAT_ID
        fi
        while [[ ! "$YOUR_CHAT_ID" =~ ^-?[0-9]+$ ]]; do
            echo -e "\e[91mInvalid chat ID format. Please try again.\033[0m"
            printf "\e[33m[+] \e[36mChat id: \033[0m"
            read YOUR_CHAT_ID
        done
        state_set CHAT_ID "$YOUR_CHAT_ID"
    fi

    YOUR_DOMAIN="$DOMAIN_NAME"
    YOUR_BOTNAME="$(state_get BOTNAME)"
    if [ -n "$YOUR_BOTNAME" ]; then
        echo -e "\e[33m[+] \e[36musernamebot (resumed):\e[0m ${YOUR_BOTNAME}"
    else
        if [ -n "$ARG_NAME" ]; then
            YOUR_BOTNAME="$ARG_NAME"
            echo -e "\e[33m[+] \e[36musernamebot (from --name):\e[0m ${YOUR_BOTNAME}"
        else
            while true; do
                printf "\e[33m[+] \e[36musernamebot: \033[0m"
                read YOUR_BOTNAME
                if [ "$YOUR_BOTNAME" != "" ]; then
                    break
                else
                    echo -e "\e[91mError: Bot username cannot be empty. Please enter a valid username.\033[0m"
                fi
            done
        fi
        YOUR_BOTNAME="${YOUR_BOTNAME#@}"
        YOUR_BOTNAME="${YOUR_BOTNAME//[[:space:]]/}"
        state_set BOTNAME "$YOUR_BOTNAME"
    fi

    YOUR_BOTLABEL="$(state_get BOTLABEL)"
    if [ -n "$YOUR_BOTLABEL" ]; then
        echo -e "\e[33m[+] \e[36mBot name (resumed):\e[0m ${YOUR_BOTLABEL}"
    else
        printf "\e[33m[+] \e[36mName for this bot ${CR}${C_DIM}[default: ${YOUR_BOTNAME}]${CR}: "
        read YOUR_BOTLABEL
        [ -z "$YOUR_BOTLABEL" ] && YOUR_BOTLABEL="$YOUR_BOTNAME"
        state_set BOTLABEL "$YOUR_BOTLABEL"
    fi

    PMA_PORT="$(state_get PMA_PORT)"
    if [ -n "$PMA_PORT" ] && pma_port_problem "$PMA_PORT" "$BOT_PORT" >/dev/null; then
        echo -e "\e[33m[+] \e[36mphpMyAdmin port (resumed):\e[0m ${PMA_PORT}"
    else
        ask_pma_port "$BOT_PORT"
        state_set PMA_PORT "$PMA_PORT"
    fi

    ROOT_PASSWORD=$(cat /root/Ciro/dbroot.txt | grep '$pass' | cut -d"'" -f2)
    ROOT_USER="root"
    echo "SELECT 1" | mysql -u$ROOT_USER -p$ROOT_PASSWORD 2>/dev/null || {
        echo -e "\e[91mError: MySQL connection failed.\033[0m"
        install_pause "MySQL connection"
    }

    randomdbpass=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    randomdbdb=$(openssl rand -base64 10 | tr -dc 'a-zA-Z' | cut -c1-8)
    dbname="Ciro"

    # ╭──────────────────────── PHASE: DB ──────────────────────────╮
    if ! phase_done DB; then
        dbuser="$(state_get DBUSER)"
        dbpass="$(state_get DBPASS)"
        if [ -z "$dbuser" ] || [ -z "$dbpass" ]; then
            clear 2>/dev/null || true
            if [ -n "$ARG_DBUSER" ]; then
                dbuser="$ARG_DBUSER"
                echo -e "\e[32mDatabase username (from --db-user):\e[0m ${dbuser}"
            else
                echo -e "\n\e[32mPlease enter the database username!\033[0m"
                printf "[+] Default user name is \e[91m${randomdbdb}\e[0m ( let it blank to use this user name ): "
                read dbuser
            fi
            if [ "$dbuser" = "" ]; then
                dbuser=$randomdbdb
            fi
            if ! valid_db_ident "$dbuser"; then
                echo -e "  ${C_WARN}!${CR} ${C_WARN}Invalid DB username (use only A-Z a-z 0-9 _). Using generated name.${CR}"
                dbuser=$randomdbdb
            fi
            if [ -n "$ARG_DBPASS" ]; then
                dbpass="$ARG_DBPASS"
                echo -e "\e[32mDatabase password (from --db-pass): [hidden]\033[0m"
            else
                echo -e "\n\e[32mPlease enter the database password!\033[0m"
                printf "[+] Default password is \e[91m${randomdbpass}\e[0m ( let it blank to use this password ): "
                read dbpass
            fi
            if [ "$dbpass" = "" ]; then
                dbpass=$randomdbpass
            fi
            if ! valid_db_pass "$dbpass"; then
                echo -e "  ${C_WARN}!${CR} ${C_WARN}Password has unsafe characters or is too short (need 6+, A-Z a-z 0-9 _). Using generated password.${CR}"
                dbpass=$randomdbpass
            fi
            state_set DBUSER "$dbuser"
            state_set DBPASS "$dbpass"
        else
            echo -e "  ${C_OK}●${CR} ${C_DIM}Database credentials resumed.${CR}"
        fi
        # Idempotent: safe to re-run (IF NOT EXISTS), so a resumed install never breaks here
        run_step "Creating database & user" \
            "mysql -u root -p$ROOT_PASSWORD -e \"CREATE DATABASE IF NOT EXISTS $dbname;\" && mysql -u root -p$ROOT_PASSWORD -e \"CREATE USER IF NOT EXISTS '$dbuser'@'%' IDENTIFIED WITH mysql_native_password BY '$dbpass'; GRANT ALL PRIVILEGES ON $dbname.* TO '$dbuser'@'%'; FLUSH PRIVILEGES;\" && mysql -u root -p$ROOT_PASSWORD -e \"CREATE USER IF NOT EXISTS '$dbuser'@'localhost' IDENTIFIED WITH mysql_native_password BY '$dbpass'; GRANT ALL PRIVILEGES ON $dbname.* TO '$dbuser'@'localhost'; FLUSH PRIVILEGES;\"" \
            || { show_step_error; install_pause "Creating database/user"; }
        mark_phase DB
    else
        dbuser="$(state_get DBUSER)"
        dbpass="$(state_get DBPASS)"
        echo -e "  ${C_OK}●${CR} ${C_DIM}Database already created - skipping.${CR}"
    fi
    # ╰─────────────────────────────────────────────────────────────╯

    # ╭──────────────────────── PHASE: CONFIG ──────────────────────╮
    if ! phase_done CONFIG; then
        wait
        sleep 1
        file_path="/var/www/html/Ciro/config.php"
        if [ -f "$file_path" ]; then
            rm "$file_path" || {
                echo -e "\e[91mError: Failed to delete old config.php.\033[0m"
                install_pause "Removing old config.php"
            }
        fi
        sleep 1
        secrettoken="$(state_get SECRET)"
        if [ -z "$secrettoken" ]; then
            secrettoken=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
            state_set SECRET "$secrettoken"
        fi
        cat <<EOF > /var/www/html/Ciro/config.php
<?php
// This variable added for high load panels which their response time is long and bot can't communicate with online panel!
// null for default settings
\$request_exec_timeout = null;
\$dbhost = 'localhost';
\$dbname = '$dbname';
\$usernamedb = '$dbuser';
\$passworddb = '$dbpass';
\$connect = mysqli_connect(\$dbhost, \$usernamedb, \$passworddb, \$dbname);
if (\$connect->connect_error) { die("error" . \$connect->connect_error); }
mysqli_set_charset(\$connect, "utf8mb4");
\$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
\$dsn = "mysql:host=\$dbhost;dbname=\$dbname;charset=utf8mb4";
try { \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options); } catch (\PDOException \$e) { error_log("Database connection failed: " . \$e->getMessage()); }
\$APIKEY = '${YOUR_BOT_TOKEN}';
\$adminnumber = '${YOUR_CHAT_ID}';
\$domainhosts = '$(hosts_of "$YOUR_DOMAIN" "$BOT_PORT")';
\$usernamebot = '${YOUR_BOTNAME}';
?>
EOF
        sudo chown www-data:www-data /var/www/html/Ciro/config.php 2>/dev/null
        mark_phase CONFIG
    else
        secrettoken="$(state_get SECRET)"
        echo -e "  ${C_OK}●${CR} ${C_DIM}config.php already written - skipping.${CR}"
    fi
    # ╰─────────────────────────────────────────────────────────────╯

    # ╭──────────────────────── PHASE: WEBHOOK ─────────────────────╮
    if ! phase_done WEBHOOK; then
        sleep 1
        run_step "Starting Apache" "systemctl start apache2" \
            || { show_step_error; install_pause "Starting Apache"; }
        sleep 5
        # Self-heal a state left by an install that started before the
        # composer/vendor fix existed: FILES may already be marked done
        # without vendor/ actually present. A directory downloaded from a
        # release that predates composer.json being shipped at all has
        # neither file on disk, so fetch them first if still missing.
        if [ ! -f "$BOT_DIR/vendor/autoload.php" ]; then
            if [ ! -f "$BOT_DIR/composer.json" ]; then
                curl -fsSL -o "$BOT_DIR/composer.json" "https://raw.githubusercontent.com/${GIT_REPO}/master/composer.json" 2>/dev/null
                curl -fsSL -o "$BOT_DIR/composer.lock" "https://raw.githubusercontent.com/${GIT_REPO}/master/composer.lock" 2>/dev/null
            fi
            run_step "Installing PHP dependencies (composer)" "install_php_deps '$BOT_DIR'" \
                || { show_step_error; install_pause "Installing PHP dependencies"; }
        fi
        run_step "Initializing database tables" "cd '$BOT_DIR' && php${PHP_VER} table.php" \
            || { show_step_error; install_pause "Initializing database tables"; }
        # table.php ran as root; files it created (log.txt) must stay writable by Apache
        chown -R www-data:www-data "$BOT_DIR" 2>/dev/null
        secrettoken="$(_bot_webhook_secret "$BOT_DIR")"
        # A transfer sets the webhook itself, once the moved data is in: a
        # webhook now would hand the users' messages to an empty bot.
        if [ -z "${CIRO_TRANSFER:-}" ]; then
        # Only now - Apache is up and the schema is migrated - tell Telegram
        # about the webhook and invite the admin to send /start. Doing this
        # earlier risked a real update arriving before table.php had run.
        run_step "Setting Telegram webhook" \
            "curl -s -F \"url=https://$(hosts_of "$YOUR_DOMAIN" "$BOT_PORT")/index.php\" -F \"secret_token=${secrettoken}\" \"https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook\"" \
            || { show_step_error; install_pause "Setting Telegram webhook"; }

        MESSAGE="✅ The Ciro bot is installed! Send /start to the bot to begin."
        curl -s -X POST "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/sendMessage" -d chat_id="${YOUR_CHAT_ID}" -d text="$MESSAGE" > /dev/null 2>&1
        fi
        mark_phase WEBHOOK
    fi
    # ╰─────────────────────────────────────────────────────────────╯

    # ── Done ──
    mark_phase COMPLETE
    bots_registry_add "$YOUR_BOTLABEL" "$BOT_DIR" "$YOUR_DOMAIN"
    bots_registry_set_bot_port "$BOT_DIR" "${BOT_PORT:-443}"
    local pma_failed=0
    pma_set_port "$BOT_DIR" "${PMA_PORT:-${BOT_PORT:-443}}" || pma_failed=1
    # issued in standalone mode above (Apache was not set up yet); switch it
    # to renew through Apache, or it cannot renew at all
    ssl_renewal_ensure "$YOUR_DOMAIN" >/dev/null
    clear 2>/dev/null || true
    banner
    _sec "Installation complete"
    printf "    ${C_OK}●${CR} ${C_OK}Ciro is installed and the webhook is set.${CR}\n"
    printf "    ${C_DIM}Open Telegram and send ${CR}${C_KEY}/start${CR}${C_DIM} to your bot.${CR}\n"

    _sec "Access"
    _kv "Name" "${C_DIM}${YOUR_BOTLABEL}${CR}"
    _kv "Bot URL" "${C_DIM}https://$(hosts_of "$YOUR_DOMAIN" "$BOT_PORT")${CR}"
    _kv "phpMyAdmin" "${C_DIM}$(pma_url "$YOUR_DOMAIN" "$(bots_registry_pma_port "$BOT_DIR")")${CR}"
    [ "$pma_failed" -eq 1 ] && printf "    ${C_WARN}!${CR} ${C_DIM}Port ${PMA_PORT} could not be used, so phpMyAdmin stayed on the bot's address. Try another from menu 9.${CR}\n"

    _sec "Database"
    _kv "Name" "${C_KEY}${dbname}${CR}"
    _kv "Username" "${C_KEY}${dbuser}${CR}"
    _kv "Password" "${C_KEY}${dbpass}${CR}"
    printf "    ${C_WARN}!${CR} ${C_DIM}Save these credentials somewhere safe.${CR}\n"

    _sec "Manage"
    _kv "Command" "${C_DIM}run ${CR}${C_KEY}Ciro${CR}${C_DIM} anytime to open this panel${CR}"
    echo ""
    _rule
    echo ""

    chmod +x /root/install.sh
    ln -sf /root/install.sh /usr/local/bin/Ciro
    _ensure_selfupdate_cron
    self_update_script
}
function install_second_bot() {
    clear 2>/dev/null || true
    banner
    _sec "Install another bot"
    printf "    ${C_DIM}Sets up a second, independent Ciro bot on this server:${CR}\n"
    printf "    ${C_DIM}its own directory, database and domain. Bot(s) already${CR}\n"
    printf "    ${C_DIM}installed here are not touched.${CR}\n"
    echo ""

    if [ ! -f "/root/Ciro/dbroot.txt" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}No Ciro install found on this server yet. Use option 1 (Install) first.${CR}\n"
        sleep 2; show_menu; return 1
    fi
    if ! ensure_connectivity; then
        printf "    ${C_BAD}●${CR} ${C_BAD}No internet connection. Aborting.${CR}\n"
        sleep 2; show_menu; return 1
    fi

    # ── Pick a free directory + database name ────────────────
    local n=2 NEW_BOT_DIR NEW_DB ROOT_PASSWORD
    ROOT_PASSWORD=$(cat /root/Ciro/dbroot.txt 2>/dev/null | grep '$pass' | cut -d"'" -f2)
    while [ -d "/var/www/html/Ciro${n}" ] \
        || mysql -u root -p"$ROOT_PASSWORD" -N -e "SHOW DATABASES LIKE 'Ciro${n}';" 2>/dev/null | grep -q "Ciro${n}"; do
        n=$((n+1))
    done
    NEW_BOT_DIR="/var/www/html/Ciro${n}"
    NEW_DB="Ciro${n}"
    _kv "Directory" "${C_KEY}${NEW_BOT_DIR}${CR}"
    _kv "Database"  "${C_KEY}${NEW_DB}${CR}"
    echo ""

    # ── Domain ─────────────────────────────────────────────────
    local domainname
    read -p "Enter the domain for this bot: " domainname
    while ! validate_domain "$domainname" || [ -f "/etc/apache2/sites-available/${domainname}.conf" ]; do
        if [ -f "/etc/apache2/sites-available/${domainname}.conf" ]; then
            echo -e "\e[91mThis domain already has a virtual host on this server. Use a different domain.\033[0m"
        else
            echo -e "\e[91mInvalid domain. Enter a full domain like bot2.example.com (no http://, no slash).\033[0m"
        fi
        read -p "Enter the domain: " domainname
    done
    domain_points_here "$domainname"
    case $? in
        0) echo -e "  ${C_OK}●${CR} ${C_OK}Domain resolves to this server.${CR}" ;;
        *) echo -e "  ${C_WARN}!${CR} ${C_WARN}Domain does not resolve to this server's IP ($(get_server_ip)) yet.${CR}"
           printf "  ${C_PROMPT}❯${CR} Continue anyway? SSL will fail until DNS is fixed. ${C_DIM}[y/N]${CR}: "
           read -r _gd
           [[ "$_gd" =~ ^[Yy]$ ]] || { echo -e "  ${C_BAD}Aborted.${CR}"; sleep 1; show_menu; return 1; } ;;
    esac
    local DOMAIN_NAME="$domainname"
    local BOT_PORT
    ask_bot_port

    # ── Bot token / chat id / username ────────────────────────
    print_header "Bot Configuration"
    local YOUR_BOT_TOKEN YOUR_CHAT_ID YOUR_BOTNAME
    printf "\e[33m[+] \e[36mBot Token: \033[0m"; read YOUR_BOT_TOKEN
    while [[ ! "$YOUR_BOT_TOKEN" =~ ^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$ ]]; do
        echo -e "\e[91mInvalid bot token format. Please try again.\033[0m"
        printf "\e[33m[+] \e[36mBot Token: \033[0m"; read YOUR_BOT_TOKEN
    done
    validate_token "$YOUR_BOT_TOKEN"
    if [ $? -eq 0 ]; then
        echo -e "  ${C_OK}●${CR} ${C_OK}Token verified with Telegram.${CR}"
    else
        echo -e "  ${C_WARN}!${CR} ${C_WARN}Could not verify the token with Telegram - continuing anyway.${CR}"
    fi
    printf "\e[33m[+] \e[36mChat id: \033[0m"; read YOUR_CHAT_ID
    printf "\e[33m[+] \e[36musernamebot: \033[0m"; read YOUR_BOTNAME
    local YOUR_BOTLABEL
    printf "\e[33m[+] \e[36mName for this bot ${CR}${C_DIM}[default: ${YOUR_BOTNAME}]${CR}: "
    read YOUR_BOTLABEL
    [ -z "$YOUR_BOTLABEL" ] && YOUR_BOTLABEL="$YOUR_BOTNAME"
    local PMA_PORT
    ask_pma_port "$BOT_PORT"

    # ── Download & extract ────────────────────────────────────
    print_header "Downloading Bot Files"
    choose_source
    local _rc=$?
    if [ "$_rc" -eq 2 ]; then show_menu; return 0; fi
    if [ "$_rc" -ne 0 ]; then sleep 2; show_menu; return 1; fi
    local ZIP_URL="$SRC_ZIP_URL" TARGET_LABEL="$SRC_LABEL"

    mkdir -p "$NEW_BOT_DIR"
    local TEMP_DIR="/tmp/cirobot_second"
    rm -rf "$TEMP_DIR"; mkdir -p "$TEMP_DIR"
    run_step "Downloading Ciro (${TARGET_LABEL})" "wget -O '$TEMP_DIR/bot.zip' '$ZIP_URL'" \
        || { show_step_error; echo -e "\033[31mDownload failed.\033[0m"; rm -rf "$TEMP_DIR"; sleep 2; show_menu; return 1; }
    run_step "Extracting source files" "unzip -o '$TEMP_DIR/bot.zip' -d '$TEMP_DIR'" \
        || { show_step_error; echo -e "\033[31mExtraction failed.\033[0m"; rm -rf "$TEMP_DIR"; sleep 2; show_menu; return 1; }
    local EXTRACTED_DIR; EXTRACTED_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -1)
    if [ -z "$EXTRACTED_DIR" ] || [ ! -d "$EXTRACTED_DIR" ]; then
        echo -e "\033[31mExtracted folder not found.\033[0m"; rm -rf "$TEMP_DIR"; sleep 2; show_menu; return 1
    fi
    mv "$EXTRACTED_DIR"/* "$NEW_BOT_DIR"
    rm -rf "$TEMP_DIR"
    chown -R www-data:www-data "$NEW_BOT_DIR"
    chmod -R 755 "$NEW_BOT_DIR"
    run_step "Installing PHP dependencies (composer)" "install_php_deps '$NEW_BOT_DIR'" \
        || { show_step_error; echo -e "\033[31mFailed to install PHP dependencies.\033[0m"; sleep 2; show_menu; return 1; }

    # ── Database ───────────────────────────────────────────────
    local dbuser dbpass
    dbuser=$(openssl rand -base64 10 | tr -dc 'a-zA-Z' | cut -c1-8)
    dbpass=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | cut -c1-10)
    run_step "Creating database & user" \
        "mysql -u root -p'$ROOT_PASSWORD' -e \"CREATE DATABASE IF NOT EXISTS $NEW_DB;\" && mysql -u root -p'$ROOT_PASSWORD' -e \"CREATE USER IF NOT EXISTS '$dbuser'@'%' IDENTIFIED WITH mysql_native_password BY '$dbpass'; GRANT ALL PRIVILEGES ON $NEW_DB.* TO '$dbuser'@'%'; FLUSH PRIVILEGES;\" && mysql -u root -p'$ROOT_PASSWORD' -e \"CREATE USER IF NOT EXISTS '$dbuser'@'localhost' IDENTIFIED WITH mysql_native_password BY '$dbpass'; GRANT ALL PRIVILEGES ON $NEW_DB.* TO '$dbuser'@'localhost'; FLUSH PRIVILEGES;\"" \
        || { show_step_error; echo -e "\033[31mDatabase creation failed.\033[0m"; sleep 2; show_menu; return 1; }

    # ── config.php ─────────────────────────────────────────────
    local secrettoken; secrettoken=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    cat <<EOF > "$NEW_BOT_DIR/config.php"
<?php
// This variable added for high load panels which their response time is long and bot can't communicate with online panel!
// null for default settings
\$request_exec_timeout = null;
\$dbhost = 'localhost';
\$dbname = '$NEW_DB';
\$usernamedb = '$dbuser';
\$passworddb = '$dbpass';
\$connect = mysqli_connect(\$dbhost, \$usernamedb, \$passworddb, \$dbname);
if (\$connect->connect_error) { die("error" . \$connect->connect_error); }
mysqli_set_charset(\$connect, "utf8mb4");
\$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
\$dsn = "mysql:host=\$dbhost;dbname=\$dbname;charset=utf8mb4";
try { \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options); } catch (\PDOException \$e) { error_log("Database connection failed: " . \$e->getMessage()); }
\$APIKEY = '${YOUR_BOT_TOKEN}';
\$adminnumber = '${YOUR_CHAT_ID}';
\$domainhosts = '$(hosts_of "$DOMAIN_NAME" "$BOT_PORT")';
\$usernamebot = '${YOUR_BOTNAME}';
?>
EOF
    chown www-data:www-data "$NEW_BOT_DIR/config.php"

    # ── HTTP-only vhost first (needed for the certbot --apache challenge,
    #    and lets the bot answer over plain HTTP even if SSL fails below) ──
    local VHOST_FILE="/etc/apache2/sites-available/${DOMAIN_NAME}.conf"
    tee "$VHOST_FILE" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN_NAME
    DocumentRoot $NEW_BOT_DIR
    <Directory $NEW_BOT_DIR>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    Include /etc/apache2/conf-available/phpmyadmin.conf
    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN_NAME}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN_NAME}-access.log combined
</VirtualHost>
EOF
    run_step "Enabling the new virtual host" "a2ensite '${DOMAIN_NAME}.conf' && systemctl reload apache2" \
        || { show_step_error; echo -e "\033[31mFailed to enable the virtual host.\033[0m"; sleep 2; show_menu; return 1; }

    # ── SSL via the Apache plugin - does not stop Apache, so the bot(s)
    #    already running keep answering the whole time ────────
    local ssl_ok=1
    if [ -f "/etc/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem" ]; then
        echo -e "  ${C_OK}●${CR} ${C_DIM}SSL certificate for ${DOMAIN_NAME} already exists.${CR}"
    else
        # certbot may already be installed (bot #1's SSL phase only ever uses
        # --standalone mode), but that never pulls in the apache plugin - so
        # check for the plugin itself, not just the certbot binary.
        certbot plugins 2>/dev/null | grep -qi apache \
            || apt install -y certbot python3-certbot-apache >/dev/null 2>&1
        if ! run_step "Requesting SSL certificate (Let's Encrypt)" \
            "certbot certonly --apache --non-interactive --agree-tos --register-unsafely-without-email --preferred-challenges http -d '$DOMAIN_NAME'"; then
            show_step_error
            echo -e "  ${C_WARN}!${CR} ${C_WARN}Certificate request failed - continuing with HTTP only.${CR}"
            echo -e "  ${C_DIM}Once the domain's DNS A record points here, run: certbot certonly --apache -d ${DOMAIN_NAME}${CR}"
            ssl_ok=0
        fi
    fi
    if [ "$ssl_ok" -eq 1 ]; then
        local VHOST_SSL_FILE="/etc/apache2/sites-available/${DOMAIN_NAME}-ssl.conf"
        tee "$VHOST_SSL_FILE" > /dev/null <<EOF
<VirtualHost *:${BOT_PORT}>
    ServerName $DOMAIN_NAME
    DocumentRoot $NEW_BOT_DIR
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN_NAME/privkey.pem
    <Directory $NEW_BOT_DIR>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    Include /etc/apache2/conf-available/phpmyadmin.conf
    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN_NAME}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN_NAME}-access.log combined
</VirtualHost>
EOF
        ciro_listen_sync "$BOT_PORT"
        run_step "Enabling the HTTPS virtual host" "a2ensite '${DOMAIN_NAME}-ssl.conf' && a2enmod ssl && systemctl reload apache2" \
            || show_step_error
        # a reload does not always open a brand-new port
        if [ -z "$(ss -ltn "( sport = :${BOT_PORT} )" 2>/dev/null | tail -n +2)" ]; then
            systemctl restart apache2 >/dev/null 2>&1
        fi
    fi

    # ── table.php, then webhook - never the other way around. Telling
    #    Telegram (and the admin) the bot is ready before the schema is
    #    migrated risks a real update arriving mid-migration.  ─────
    local proto="http" hosts="$DOMAIN_NAME"
    [ "$ssl_ok" -eq 1 ] && { proto="https"; hosts=$(hosts_of "$DOMAIN_NAME" "$BOT_PORT"); }
    run_step "Initializing database tables" "cd '$NEW_BOT_DIR' && php table.php" \
        || { show_step_error; echo -e "\033[31mtable.php failed - see the details above.\033[0m"; }
    # table.php ran as root; files it created (log.txt) must stay writable by Apache
    chown -R www-data:www-data "$NEW_BOT_DIR" 2>/dev/null
    secrettoken="$(_bot_webhook_secret "$NEW_BOT_DIR")"
    run_step "Setting Telegram webhook" \
        "curl -s -F \"url=${proto}://${hosts}/index.php\" -F \"secret_token=${secrettoken}\" \"https://api.telegram.org/bot${YOUR_BOT_TOKEN}/setWebhook\"" \
        || show_step_error
    curl -s -X POST "https://api.telegram.org/bot${YOUR_BOT_TOKEN}/sendMessage" -d chat_id="${YOUR_CHAT_ID}" -d text="✅ The Ciro bot is installed! Send /start to the bot to begin." > /dev/null 2>&1
    bots_registry_add "$YOUR_BOTLABEL" "$NEW_BOT_DIR" "$DOMAIN_NAME"
    bots_registry_set_bot_port "$NEW_BOT_DIR" "$BOT_PORT"
    local pma_note=""
    if [ "$PMA_PORT" != "$BOT_PORT" ]; then
        if [ "$ssl_ok" -ne 1 ]; then
            pma_note="No SSL certificate yet, so phpMyAdmin stayed on the bot's address. Change it later from menu 9."
        elif ! pma_set_port "$NEW_BOT_DIR" "$PMA_PORT"; then
            pma_note="Port ${PMA_PORT} could not be used, so phpMyAdmin stayed on the bot's address. Try another from menu 9."
        fi
    fi

    clear 2>/dev/null || true
    banner
    _sec "Another bot installed"
    printf "    ${C_OK}●${CR} ${C_OK}Set up alongside the bot(s) already on this server.${CR}\n"
    echo ""
    _kv "Name"       "${C_DIM}${YOUR_BOTLABEL}${CR}"
    _kv "Bot URL"    "${C_DIM}${proto}://${hosts}${CR}"
    _kv "phpMyAdmin" "${C_DIM}$(pma_url "$DOMAIN_NAME" "$(bots_registry_pma_port "$NEW_BOT_DIR")" "$proto")${CR}"
    [ -n "$pma_note" ] && printf "    ${C_WARN}!${CR} ${C_DIM}%s${CR}\n" "$pma_note"
    _kv "Directory"  "${C_DIM}${NEW_BOT_DIR}${CR}"
    _kv "Database"   "${C_KEY}${NEW_DB}${CR}"
    _kv "DB User"    "${C_KEY}${dbuser}${CR}"
    _kv "DB Pass"    "${C_KEY}${dbpass}${CR}"
    echo ""
    printf "    ${C_WARN}!${CR} ${C_DIM}Save these credentials. Update/Backup/Remove/Renew SSL now${CR}\n"
    printf "    ${C_DIM}ask which bot when more than one is installed.${CR}\n"
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}
function update_bot() {
    clear 2>/dev/null || true
    banner
    # the original Mirza bot is here and no Ciro bot yet: updating it means
    # moving it over, data and all
    if auto_migrate_original; then
        return 0
    fi
    if ! pick_bot_instance "update"; then
        # [0] in the bot list is "back", not "nothing installed"
        [ "$(bots_registry_count)" != "0" ] && { show_menu; return 1; }
        _sec "Update"
        printf "    ${C_BAD}●${CR} ${C_BAD}Ciro is not installed here. Use option 1 to install it first.${CR}\n"
        sleep 2
        show_menu
        return 1
    fi
    BOT_DIR="$PICKED_DIR"
    # table.php runs in this update and would announce what is new by itself;
    # the closing message (step 10) says it instead, in the same message
    export CIRO_WHATSNEW_MERGE=1
    local BOT_LABEL="$PICKED_NAME"
    [ -n "$PICKED_DOMAIN" ] && [ "$PICKED_DOMAIN" != "null" ] && BOT_LABEL="$PICKED_NAME · $PICKED_DOMAIN"
    if [ ! -d "$BOT_DIR" ]; then
        _sec "Update"
        printf "    ${C_BAD}●${CR} ${C_BAD}Ciro is not installed here. Use option 1 to install it first.${CR}\n"
        sleep 2
        show_menu
        return 1
    fi

    CONFIG_PATH="$BOT_DIR/config.php"
    if [ ! -f "$CONFIG_PATH" ]; then
        _sec "Update"
        printf "    ${C_BAD}●${CR} ${C_BAD}config.php is missing - this does not look like a working install.${CR}\n"
        printf "    ${C_DIM}Updating would leave you without database credentials. Aborting.${CR}\n"
        sleep 3
        show_menu
        return 1
    fi

    # ── What are we updating FROM? ───────────────────────────
    # A bot of the original project uses the same config.php variables, so it
    # can be upgraded in place. It simply has no "version" file of ours.
    local current flavour
    current=$(get_installed_version "$BOT_DIR")
    if [ -n "$current" ]; then
        flavour="Ciro ${current}"
    elif [ -f "$BOT_DIR/index.php" ] && [ -f "$BOT_DIR/table.php" ]; then
        flavour="original project (no version file)"
        current="original"
    else
        flavour="unknown"
        current="unknown"
    fi

    _sec "Update"
    _kv "Bot" "${C_KEY}${BOT_LABEL}${CR}"
    _kv "Installed" "${C_OK}${flavour}${CR}"
    _kv "Directory" "${C_DIM}${BOT_DIR}${CR}"
    echo ""
    printf "    ${C_DIM}Your config.php, database and any files not shipped by this${CR}\n"
    printf "    ${C_DIM}project are kept. New files are written over the old ones and${CR}\n"
    printf "    ${C_DIM}the database gains its new columns - nothing is deleted.${CR}\n"

    if ! ensure_connectivity; then
        printf "\n    ${C_BAD}●${CR} ${C_BAD}No internet connection (even after a DNS reset). Try again later.${CR}\n"
        sleep 2; show_menu; return 1
    fi

    choose_source
    local _rc=$?
    if [ "$_rc" -eq 2 ]; then show_menu; return 0; fi
    if [ "$_rc" -ne 0 ]; then sleep 2; show_menu; return 1; fi
    local ZIP_URL="$SRC_ZIP_URL" TARGET_LABEL="$SRC_LABEL"

    # ── Confirm before touching anything ─────────────────────
    _sec "Ready to update"
    _kv "Bot" "${C_KEY}${BOT_LABEL}${CR}"
    _kv "Installed" "${C_OK}${flavour}${CR}"
    if [ -n "$current" ] && [ "version $current" = "$TARGET_LABEL" ]; then
        _kv "Target" "${C_KEY}${TARGET_LABEL}${CR} ${C_DIM}(the one installed - its files are put in again)${CR}"
    else
        _kv "Target" "${C_KEY}${TARGET_LABEL}${CR}"
    fi
    echo ""
    local confirm
    if [ -n "$CIRO_NONINTERACTIVE" ]; then
        # the admin already confirmed, in the bot, before the request was written
        confirm="y"
    else
        printf "  ${C_PROMPT}❯${CR} Update now? ${C_DIM}[y/N]${CR}: "
        read -r confirm
    fi
    case "$confirm" in
        y|Y|yes|YES|Yes) ;;
        *)
            printf "\n    ${C_DIM}Nothing was changed.${CR}\n"
            sleep 1
            show_menu
            return 0
            ;;
    esac

    print_header "Updating Ciro"

    # ── 1. Safety net: a restorable snapshot BEFORE anything moves ──
    local BK_DIR="/root/Ciro-backups"
    local STAMP; STAMP=$(date +%Y%m%d_%H%M%S)
    # Named after the bot it belongs to - its @username, or its directory when
    # config.php has none - so two bots' snapshots are told apart at a glance
    # in this shared folder. Only a label: which bot a snapshot really belongs
    # to is still decided by the directory inside it, since that is where it
    # would unpack to.
    local BK_TAG
    BK_TAG=$(grep '^\$usernamebot' "$CONFIG_PATH" 2>/dev/null | cut -d"'" -f2 | tr -cd 'A-Za-z0-9_' | cut -c1-32)
    [ -z "$BK_TAG" ] && BK_TAG=$(basename "$BOT_DIR" | tr -cd 'A-Za-z0-9_' | cut -c1-32)
    local ROLLBACK="${BK_DIR}/pre-update_${BK_TAG}_${STAMP}.tar.gz"
    mkdir -p "$BK_DIR"
    run_step "Backing up the current install" \
        "tar --warning=no-file-changed -czf '$ROLLBACK' -C '$(dirname "$BOT_DIR")' --exclude='*.bak*' --exclude='.git' --exclude='log.txt' --exclude='error_log' --exclude='update_request' --exclude='update_request.running' --exclude='rollback_request' --exclude='update_progress.json' --exclude='update_status.json' --exclude='update_backups.json' --exclude='restore_request' --exclude='restore_request.running' --exclude='restore_progress.json' --exclude='restore_status.json' --exclude='watch_caps.json' --exclude='storage/restore' '$(basename "$BOT_DIR")'" \
        || { show_step_error; printf "  ${C_BAD}Could not create a rollback archive. Refusing to continue.${CR}\n"; sleep 3; show_menu; return 1; }
    # keep only this bot's newest CIRO_SNAPSHOTS_KEEP - the bot lists exactly
    # these for "بازگشت به نسخه قبلی"; another bot's are never touched
    _snapshots_prune "$BOT_DIR"

    # ── 2. Fetch and validate the new code ───────────────────
    TEMP_DIR="/tmp/cirobot_update"
    rm -rf "$TEMP_DIR"; mkdir -p "$TEMP_DIR"
    run_step "Downloading ${TARGET_LABEL}" \
        "curl -fsSL --max-time 180 -o '$TEMP_DIR/bot.zip' '$ZIP_URL' || wget -q -O '$TEMP_DIR/bot.zip' '$ZIP_URL'" \
        || { show_step_error; printf "  ${C_BAD}Download failed. Nothing was changed.${CR}\n"; rm -rf "$TEMP_DIR"; sleep 3; show_menu; return 1; }
    run_step "Extracting the package" "unzip -o -q '$TEMP_DIR/bot.zip' -d '$TEMP_DIR'" \
        || { show_step_error; printf "  ${C_BAD}The archive could not be extracted. Nothing was changed.${CR}\n"; rm -rf "$TEMP_DIR"; sleep 3; show_menu; return 1; }

    local NEW_DIR
    NEW_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -1)
    # Sanity-check the payload before letting it near a working bot
    if [ -z "$NEW_DIR" ] || [ ! -f "$NEW_DIR/index.php" ] || [ ! -f "$NEW_DIR/table.php" ] || [ ! -f "$NEW_DIR/admin.php" ]; then
        printf "  ${C_BAD}●${CR} ${C_BAD}The downloaded package does not look like Ciro. Aborting.${CR}\n"
        printf "  ${C_DIM}Your install was not touched.${CR}\n"
        rm -rf "$TEMP_DIR"; sleep 3; show_menu; return 1
    fi
    # never let a packaged config.php overwrite real credentials
    rm -f "$NEW_DIR/config.php"

    # ── 3. PHP dependencies - built in the extracted copy first, so a
    #      composer/network failure aborts before the live install is touched.
    run_step "Installing PHP dependencies (composer)" "install_php_deps '$NEW_DIR'" \
        || { show_step_error; printf "  ${C_BAD}Could not install PHP dependencies. Nothing was changed.${CR}\n"; rm -rf "$TEMP_DIR"; sleep 3; show_menu; return 1; }

    # ── 4. Overlay the new files (no wipe) ───────────────────
    # cp -a over the existing tree: shipped files are replaced, everything the
    # operator added (config.php, vendor/, uploads, custom panels) stays put.
    run_step "Installing new files" "cp -a '$NEW_DIR/.' '$BOT_DIR/'" \
        || { show_step_error; _rollback_update "$ROLLBACK" "$BOT_DIR"; rm -rf "$TEMP_DIR"; sleep 3; show_menu; return 1; }

    run_step "Setting ownership and permissions" \
        "chown -R www-data:www-data '$BOT_DIR' && find '$BOT_DIR' -type d -exec chmod 755 {} + && find '$BOT_DIR' -type f -exec chmod 644 {} + && chmod +x '$BOT_DIR'/*.sh 2>/dev/null; true" \
        || true

    # ── 5. Syntax-check the core before trusting it ──────────
    local BAD=""
    for f in index.php admin.php function.php keyboard.php table.php config.php; do
        [ -f "$BOT_DIR/$f" ] || continue
        php -l "$BOT_DIR/$f" >/dev/null 2>&1 || BAD="$BAD $f"
    done
    if [ -n "$BAD" ]; then
        printf "  ${C_BAD}●${CR} ${C_BAD}PHP syntax errors after update:${CR}${C_KEY}${BAD}${CR}\n"
        _rollback_update "$ROLLBACK" "$BOT_DIR"
        rm -rf "$TEMP_DIR"; sleep 4; show_menu; return 1
    fi
    printf "    ${C_OK}✔${CR} ${C_DIM}Core files pass the PHP syntax check${CR}\n"

    # ── 6. Database migration (additive, never destructive) ──
    # table.php only ever CREATEs missing tables and ADDs missing columns, so
    # it upgrades an original-project schema without touching existing rows.
    local DOMAIN_URL DOMAIN_NAME
    DOMAIN_URL=$(grep "^\$domainhosts" "$CONFIG_PATH" | cut -d"'" -f2)
    DOMAIN_NAME=$(hosts_domain "$DOMAIN_URL")
    local DB_STATE=""
    if ( cd "$BOT_DIR" && php table.php >/dev/null 2>&1 ); then
        printf "    ${C_OK}✔${CR} ${C_DIM}Database schema updated${CR}\n"
        DB_STATE="به‌روزرسانی شد"
    elif [ -n "$DOMAIN_URL" ] && curl -fsS --max-time 60 "https://${DOMAIN_URL}/table.php" >/dev/null 2>&1; then
        printf "    ${C_OK}✔${CR} ${C_DIM}Database schema updated (over HTTPS)${CR}\n"
        DB_STATE="به‌روزرسانی شد"
    else
        printf "    ${C_WARN}!${CR} ${C_WARN}Could not run table.php automatically.${CR}\n"
        printf "      ${C_DIM}Open https://${DOMAIN_URL}/table.php once in a browser.${CR}\n"
        DB_STATE="به‌روزرسانی نشد - table.php رو یک بار توی مرورگر باز کن"
    fi

    # ── 7. Apache: only fix what is actually missing ─────────
    if [ -n "$DOMAIN_NAME" ]; then
        _ensure_vhost "$DOMAIN_NAME" "$BOT_DIR"
        # the bot's own copy of its phpMyAdmin port (🗃 phpMyAdmin's link)
        config_set_pma_port "$BOT_DIR" "$(bots_registry_pma_port "$BOT_DIR")"
        # a certificate still set to renew in standalone mode never will
        ssl_renewal_ensure "$DOMAIN_NAME"
    fi

    # ── 8. Is the bot actually answering? ────────────────────
    if [ -n "$DOMAIN_NAME" ]; then
        local code
        code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "https://${DOMAIN_URL%%/*}/" 2>/dev/null)
        if [ "$code" = "200" ] || [ "$code" = "301" ] || [ "$code" = "302" ]; then
            printf "    ${C_OK}✔${CR} ${C_DIM}Site responds (HTTP ${code})${CR}\n"
        else
            printf "    ${C_WARN}!${CR} ${C_WARN}Site returned HTTP ${code:-timeout}. Check Apache and SSL.${CR}\n"
        fi
    fi

    # ── 9. Refresh the management script itself ──────────────
    if [ -f "$BOT_DIR/install.sh" ]; then
        sed -i 's/\r$//' "$BOT_DIR/install.sh"
        if bash -n "$BOT_DIR/install.sh" 2>/dev/null; then
            install -m 0755 "$BOT_DIR/install.sh" /root/install.sh 2>/dev/null || \
                { cp "$BOT_DIR/install.sh" /root/install.sh; chmod +x /root/install.sh; }
            ln -sf /root/install.sh /usr/local/bin/Ciro
        fi
    fi
    # an install that predates the button gets its watcher here, so the very
    # next update can be started from inside the bot
    _ensure_selfupdate_cron

    rm -rf "$TEMP_DIR"

    local newver; newver=$(get_installed_version "$BOT_DIR"); [ -z "$newver" ] && newver="$TARGET_LABEL"

    # ── 10. Tell the bot's admins it was updated ───────────────
    # Best effort on purpose: the update has already succeeded by this point,
    # so a message that cannot be delivered must never turn it into a failure.
    if [ -f "$BOT_DIR/config.php" ]; then
        # the closing message is built by the bot's own code (function.php,
        # bot_update_notify_admins): the notice, and under it what each update
        # the admin missed added - one message, in the admin's language
        cat > /tmp/ciro_notify.php <<'NOTIFYEOF'
<?php
// the same set, in the same order, every cronbot entry point takes
require 'config.php';
require 'botapi.php';
require 'panels.php';
require 'function.php';
require_once 'jdf.php';
require 'vendor/autoload.php';
echo bot_update_notify_admins(getenv('CIRO_DOMAIN') ?: '', getenv('CIRO_NEW') ?: '', getenv('CIRO_BACKUP') ?: '');
NOTIFYEOF
        local SENT
        SENT=$( cd "$BOT_DIR" && CIRO_OLD="$flavour" CIRO_NEW="$newver" \
            CIRO_DOMAIN="$DOMAIN_NAME" CIRO_DB="$DB_STATE" CIRO_BACKUP="$ROLLBACK" \
            php /tmp/ciro_notify.php 2>/dev/null )
        rm -f /tmp/ciro_notify.php
        if [ -n "$SENT" ] && [ "$SENT" -gt 0 ] 2>/dev/null; then
            printf "    ${C_OK}✔${CR} ${C_DIM}Update announced to ${SENT} admin(s) in Telegram${CR}\n"
        else
            printf "    ${C_DIM}· Could not announce the update in Telegram${CR}\n"
        fi
    fi
    echo ""
    _sec "Done"
    _kv "Bot"      "${C_KEY}${BOT_LABEL}${CR}"
    _kv "Was"      "${C_DIM}${flavour}${CR}"
    _kv "Now"      "${C_OK}${newver}${CR}"
    _kv "Rollback" "${C_DIM}${ROLLBACK}${CR}"
    echo ""
    printf "    ${C_DIM}Your config.php and database were kept as they were.${CR}\n"
    printf "    ${C_DIM}To undo:${CR} ${C_KEY}tar -xzf ${ROLLBACK} -C /var/www/html${CR}\n"
    echo ""
    # the success report above is the whole point of the run when nobody is
    # watching; the admin is told in Telegram by step 10 either way
    if [ -n "$CIRO_NONINTERACTIVE" ]; then
        return 0
    fi
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

# Put the pre-update snapshot back, exactly as it was.
# $3: what is being undone, for the wording - an update (default) or a restore
_rollback_update() {
    local archive="$1" botdir="$2" what="${3:-update}"
    printf "  ${C_WARN}↺${CR} ${C_WARN}Rolling back to the pre-${what} snapshot...${CR}\n"
    if [ ! -f "$archive" ]; then
        printf "  ${C_BAD}●${CR} ${C_BAD}Snapshot missing (${archive}). Manual recovery needed.${CR}\n"
        return 1
    fi
    rm -rf "${botdir}.failed" 2>/dev/null
    mv "$botdir" "${botdir}.failed" 2>/dev/null
    if tar -xzf "$archive" -C "$(dirname "$botdir")" 2>/dev/null; then
        chown -R www-data:www-data "$botdir" 2>/dev/null
        rm -rf "${botdir}.failed"
        printf "  ${C_OK}✔${CR} ${C_OK}Rolled back. Your bot is as it was before the ${what}.${CR}\n"
        return 0
    fi
    mv "${botdir}.failed" "$botdir" 2>/dev/null
    printf "  ${C_BAD}●${CR} ${C_BAD}Rollback failed. Snapshot kept at ${archive}${CR}\n"
    return 1
}

# ── Updates asked for from inside the bot ────────────────────
# The bot is served as www-data; everything update_bot() does needs root, so
# the bot can never run it directly. Instead its 🔄 آپدیت ربات button only
# WRITES a request file into its own directory, and this watcher - a line in
# root's crontab - is what actually updates. No sudo rights are handed to the
# web server, so the worst a compromised bot could ask for is the same update
# an admin could already have started from the terminal.
CIRO_UPDATE_REQUEST="update_request"
CIRO_UPDATE_STATUS="update_status.json"
CIRO_ROLLBACK_REQUEST="rollback_request"
CIRO_BACKUPS_LIST="update_backups.json"
# the same folder update_bot() drops its pre-update snapshot into
CIRO_BACKUP_DIR="/root/Ciro-backups"
# how many of those each bot keeps - on the server and so in its own
# "بازگشت به نسخه قبلی" list (bot_update_backups() shows no more either)
CIRO_SNAPSHOTS_KEEP=2
# where the bot leaves the chat, message and already-translated wording for the
# progress bar. Written next to the request rather than inside it: the request
# is claimed and deleted early, and this has to outlive that.
CIRO_PROGRESS_CTX="update_progress.json"

# ── The bot's own message, redrawn as the work goes ──────────
# Only root can do this. The update runs here, not in the bot, and a bot that
# is being overwritten cannot report on its own progress. Everything needed is
# read ONCE, up front, into these variables - a rollback replaces the whole bot
# directory half way through, taking the context file with it, so anything read
# lazily would be gone exactly when the finished message is due.
MZ_TOKEN=""; MZ_CHAT=""; MZ_MSG=""; MZ_LANG=""
MZ_TPL_PROGRESS=""; MZ_TPL_DONE=""; MZ_TPL_FAIL=""

_selfupdate_ctx_load() {
    local dir="$1" ctx="${dir}/${CIRO_PROGRESS_CTX}"
    MZ_TOKEN=""; MZ_CHAT=""; MZ_MSG=""; MZ_LANG=""
    MZ_TPL_PROGRESS=""; MZ_TPL_DONE=""; MZ_TPL_FAIL=""
    [ -f "$ctx" ] || return 1
    MZ_TOKEN=$(grep '^\$APIKEY' "$dir/config.php" 2>/dev/null | cut -d"'" -f2)
    MZ_CHAT=$(jq -r '.chat_id // empty' "$ctx" 2>/dev/null)
    MZ_MSG=$(jq -r '.message_id // empty' "$ctx" 2>/dev/null)
    MZ_LANG=$(jq -r '.lang // empty' "$ctx" 2>/dev/null)
    MZ_TPL_PROGRESS=$(jq -r '.progress // empty' "$ctx" 2>/dev/null)
    MZ_TPL_DONE=$(jq -r '.done // empty' "$ctx" 2>/dev/null)
    MZ_TPL_FAIL=$(jq -r '.fail // empty' "$ctx" 2>/dev/null)
    [ -n "$MZ_TOKEN" ] && [ -n "$MZ_CHAT" ] && [ -n "$MZ_MSG" ]
}

_selfupdate_bar() {
    local pct="$1" width=10 filled i out=""
    [ "$pct" -gt 100 ] && pct=100
    [ "$pct" -lt 0 ] && pct=0
    filled=$(( pct * width / 100 ))
    for ((i = 0; i < width; i++)); do
        if [ "$i" -lt "$filled" ]; then out="${out}█"; else out="${out}░"; fi
    done
    printf '%s' "$out"
}

_selfupdate_tg_edit() {
    [ -n "$MZ_TOKEN" ] && [ -n "$MZ_CHAT" ] && [ -n "$MZ_MSG" ] || return 0
    curl -s --max-time 10 -X POST "https://api.telegram.org/bot${MZ_TOKEN}/editMessageText" \
        --data-urlencode "chat_id=${MZ_CHAT}" \
        --data-urlencode "message_id=${MZ_MSG}" \
        --data-urlencode "text=$1" \
        --data-urlencode "parse_mode=HTML" >/dev/null 2>&1
}

# Redraw at a whole percentage. Called once per finished step, never inside the
# spinner loop: Telegram rate-limits edits, and five a minute is plenty for a
# bar that only really moves seven times.
_selfupdate_progress() {
    local pct="$1" text
    # a restore: the web panel's page reads how far it is from its status file
    [ -n "$CIRO_RESTORE_PCT" ] && printf '{"state":"running","pct":%s,"at":%s}\n' "$pct" "$(date +%s)" > "$CIRO_RESTORE_PCT" 2>/dev/null
    [ -n "$MZ_TPL_PROGRESS" ] || return 0
    text="${MZ_TPL_PROGRESS//\{bar\}/$(_selfupdate_bar "$pct")}"
    text="${text//\{percent\}/$pct}"
    _selfupdate_tg_edit "$text"
}

# The finished message is the update screen itself - the version now installed,
# the date in the reader's own calendar, and the same menu the admin started
# from. None of that can be built here, so PHP builds it; this only decides
# when. Returns non-zero whenever that is not possible - php missing, or a
# rollback that has just restored a version predating this function - and the
# caller falls back to the plain template below.
_selfupdate_finish_php() {
    local dir="$1" kind="$2" backup="$3" ok="$4" rc
    [ -n "$MZ_CHAT" ] && [ -n "$MZ_MSG" ] || return 1
    command -v php >/dev/null 2>&1 || return 1
    grep -q 'function bot_update_finish_edit' "$dir/function.php" 2>/dev/null || return 1
    cat > /tmp/ciro_update_finish.php <<'FINEOF'
<?php
// the same set, in the same order, every cronbot entry point takes
require 'config.php';
require 'botapi.php';
require 'panels.php';
require 'function.php';
require_once 'jdf.php';
require 'vendor/autoload.php';
bot_update_finish_edit(
    getenv('MZ_CHAT'),
    getenv('MZ_MSG'),
    getenv('MZ_LANG'),
    getenv('MZ_BACKUP'),
    getenv('MZ_KIND'),
    getenv('MZ_OK') === '1'
);
FINEOF
    ( cd "$dir" && MZ_CHAT="$MZ_CHAT" MZ_MSG="$MZ_MSG" MZ_LANG="$MZ_LANG" \
        MZ_BACKUP="$backup" MZ_KIND="$kind" MZ_OK="$ok" \
        php /tmp/ciro_update_finish.php ) >> /tmp/ciro_selfupdate.log 2>&1
    rc=$?
    rm -f /tmp/ciro_update_finish.php
    return $rc
}

# The last word: done (with the snapshot the admin can go back to) or failed.
_selfupdate_final() {
    local which="$1" backup="$2" tpl text
    tpl="$MZ_TPL_DONE"
    [ "$which" = "fail" ] && tpl="$MZ_TPL_FAIL"
    [ -n "$tpl" ] || return 0
    text="${tpl//\{backup\}/$backup}"
    text="${text//\{bar\}/$(_selfupdate_bar 100)}"
    text="${text//\{percent\}/100}"
    _selfupdate_tg_edit "$text"
}

# Leave the bot a readable note about what happened, so its button can report
# back without needing to see this script's output.
_selfupdate_status() {
    local dir="$1" state="$2" detail="$3"
    printf '{"state":"%s","detail":"%s","at":%s}\n' "$state" "$detail" "$(date +%s)" \
        > "${dir}/${CIRO_UPDATE_STATUS}" 2>/dev/null
    chown www-data:www-data "${dir}/${CIRO_UPDATE_STATUS}" 2>/dev/null
    chmod 664 "${dir}/${CIRO_UPDATE_STATUS}" 2>/dev/null
}

# _snapshots_prune BOTDIR - delete all but that bot's CIRO_SNAPSHOTS_KEEP
# newest snapshots. The folder is shared by every bot on the server; a
# snapshot belongs to the bot whose directory it holds (where it would unpack
# to), so another bot's are never counted or deleted.
_snapshots_prune() {
    local base; base=$(basename "$1")
    local n=0 f
    for f in $(ls -1t "$CIRO_BACKUP_DIR"/pre-update_*.tar.gz 2>/dev/null); do
        [ "$(tar -tzf "$f" 2>/dev/null | head -1 | cut -d/ -f1)" = "$base" ] || continue
        n=$((n + 1))
        [ "$n" -gt "$CIRO_SNAPSHOTS_KEEP" ] && rm -f "$f"
    done
}

# Which snapshots belong to THIS bot, newest first, each with the version it
# holds - so the admin picks "the 1.0.2 from this morning" rather than a
# filename. Ownership is not cosmetic: on a two-bot server every snapshot
# lands in the same folder, and unpacking one bot's tree over another's would
# be a disaster, so the archive's own top-level directory has to match.
# Pruned first, so the list never names more than the server keeps.
_selfupdate_publish_backups() {
    local dir="$1"
    _snapshots_prune "$dir"
    local base; base=$(basename "$dir")
    local out="[" first=1 f top ver at size
    for f in $(ls -1t "$CIRO_BACKUP_DIR"/pre-update_*.tar.gz 2>/dev/null); do
        top=$(tar -tzf "$f" 2>/dev/null | head -1 | cut -d/ -f1)
        [ "$top" = "$base" ] || continue
        ver=$(tar -xzOf "$f" "${base}/version" 2>/dev/null | tr -cd '[:alnum:]._-')
        [ -z "$ver" ] && ver="?"
        at=$(stat -c %Y "$f" 2>/dev/null || echo 0)
        size=$(stat -c %s "$f" 2>/dev/null || echo 0)
        [ "$first" -eq 1 ] || out="${out},"
        first=0
        out="${out}{\"name\":\"$(basename "$f")\",\"version\":\"${ver}\",\"at\":${at},\"size\":${size}}"
    done
    out="${out}]"
    printf '%s\n' "$out" > "${dir}/${CIRO_BACKUPS_LIST}" 2>/dev/null
    chown www-data:www-data "${dir}/${CIRO_BACKUPS_LIST}" 2>/dev/null
    chmod 664 "${dir}/${CIRO_BACKUPS_LIST}" 2>/dev/null
}

# ── Backups imported from inside the bot (📥 ایمپورت بکاپ) ───
# The bot takes the zip from its admin, works out what it holds and its
# password, and asks for a yes - then leaves this request. The restore itself
# is menu 7's, run here: only root can import the database and lay files over
# the bot. www-data wrote the request, so everything in it is checked: a bare
# file name in the bot's own inbox, a known kind, a dump the zip really has.
CIRO_RESTORE_REQUEST="restore_request"
CIRO_RESTORE_CTX="restore_progress.json"
CIRO_RESTORE_STATUS="restore_status.json"
CIRO_RESTORE_INBOX="storage/restore"
# what this watcher can be asked for: the bot offers 📥 only while a watcher
# that knows restores is running (an older copy of this script ignores them)
CIRO_WATCH_CAPS="watch_caps.json"

_selfwatch_caps() {
    local f="$1/${CIRO_WATCH_CAPS}"
    printf '{"caps":["update","rollback","restore"],"at":%s}\n' "$(date +%s)" > "$f" 2>/dev/null
    chown www-data:www-data "$f" 2>/dev/null
    chmod 664 "$f" 2>/dev/null
}

# _selfrestore_status DIR STATE [RESULTS] - what the web panel's page shows
_selfrestore_status() {
    printf '{"state":"%s","at":%s,"results":%s}\n' "$2" "$(date +%s)" "${3:-[]}" > "$1/${CIRO_RESTORE_STATUS}" 2>/dev/null
    chown www-data:www-data "$1/${CIRO_RESTORE_STATUS}" 2>/dev/null
    chmod 664 "$1/${CIRO_RESTORE_STATUS}" 2>/dev/null
}

# the bot's message and its wording, read once (same keys as the update's)
_selfrestore_ctx_load() {
    local dir="$1" ctx="$1/${CIRO_RESTORE_CTX}"
    MZ_TOKEN=""; MZ_CHAT=""; MZ_MSG=""; MZ_LANG=""
    MZ_TPL_PROGRESS=""; MZ_TPL_DONE=""; MZ_TPL_FAIL=""
    [ -f "$ctx" ] || return 1
    MZ_TOKEN=$(grep '^\$APIKEY' "$dir/config.php" 2>/dev/null | cut -d"'" -f2)
    MZ_CHAT=$(jq -r '.chat_id // empty' "$ctx" 2>/dev/null)
    MZ_MSG=$(jq -r '.message_id // empty' "$ctx" 2>/dev/null)
    MZ_LANG=$(jq -r '.lang // empty' "$ctx" 2>/dev/null)
    MZ_TPL_PROGRESS=$(jq -r '.progress // empty' "$ctx" 2>/dev/null)
    MZ_TPL_DONE=$(jq -r '.done // empty' "$ctx" 2>/dev/null)
    MZ_TPL_FAIL=$(jq -r '.fail // empty' "$ctx" 2>/dev/null)
    [ -n "$MZ_TOKEN" ] && [ -n "$MZ_CHAT" ] && [ -n "$MZ_MSG" ]
}

# The finished message is the bot's own (it says what each file did, in the
# admin's language); the plain template is the fallback.
_selfrestore_finish_php() {
    local dir="$1" results="$2" ok="$3" rc
    [ -n "$MZ_CHAT" ] && [ -n "$MZ_MSG" ] || return 1
    command -v php >/dev/null 2>&1 || return 1
    grep -q 'function bkimp_finish_edit' "$dir/function.php" "$dir/backup_import.php" 2>/dev/null || return 1
    cat > /tmp/ciro_restore_finish.php <<'FINEOF'
<?php
require 'config.php';
require 'botapi.php';
require 'panels.php';
require 'function.php';
require_once 'jdf.php';
require 'vendor/autoload.php';
bkimp_finish_edit(getenv('MZ_CHAT'), getenv('MZ_MSG'), getenv('MZ_LANG'), getenv('MZ_RESULTS'), getenv('MZ_OK') === '1');
FINEOF
    ( cd "$dir" && MZ_CHAT="$MZ_CHAT" MZ_MSG="$MZ_MSG" MZ_LANG="$MZ_LANG" MZ_RESULTS="$results" MZ_OK="$ok" \
        php /tmp/ciro_restore_finish.php ) >> /tmp/ciro_selfupdate.log 2>&1
    rc=$?
    rm -f /tmp/ciro_restore_finish.php
    return $rc
}

_selfrestore_run() {
    local dir="$1" req="$1/${CIRO_RESTORE_REQUEST}" claimed inbox age n i kind file entry pw path rc
    local cfg dbhost dbname dbuser dbpass name results="" allok=1 steps=0 item
    [ -f "$req" ] || return 0
    claimed="${req}.running"
    mv -f "$req" "$claimed" 2>/dev/null || return 0
    inbox="${dir}/${CIRO_RESTORE_INBOX}"
    age=$(( $(date +%s) - $(stat -c %Y "$claimed" 2>/dev/null || echo 0) ))
    if [ "$age" -gt 900 ] || ! command -v jq >/dev/null 2>&1; then
        # nobody is waiting on a request this old; never act on it later
        rm -f "$claimed" "${dir}/${CIRO_RESTORE_CTX}"
        find "$inbox" -maxdepth 1 -type f \( -name '*.zip' -o -name '*.sql' \) -delete 2>/dev/null
        _selfrestore_status "$dir" "stale"
        return 0
    fi
    n=$(jq -r '.items | length' "$claimed" 2>/dev/null)
    [[ "$n" =~ ^[12]$ ]] || n=0
    cfg="$dir/config.php"
    dbhost=$(grep '^\$dbhost' "$cfg" | cut -d"'" -f2); [ -z "$dbhost" ] && dbhost="localhost"
    dbname=$(grep '^\$dbname' "$cfg" | cut -d"'" -f2)
    dbuser=$(grep '^\$usernamedb' "$cfg" | cut -d"'" -f2)
    dbpass=$(grep '^\$passworddb' "$cfg" | cut -d"'" -f2)
    name=$(jq -r --arg d "$dir" '.[] | select(.dir == $d) | .name' "$BOTS_REGISTRY" 2>/dev/null | head -1)
    [ -z "$name" ] && name=$(basename "$dir")
    _selfrestore_ctx_load "$dir"
    _selfrestore_status "$dir" "running"
    CIRO_RESTORE_PCT="$dir/${CIRO_RESTORE_STATUS}"
    # the bar moves once per finished step: menu 7 takes 3 for a database, 4 for files
    for ((i = 0; i < n; i++)); do
        case "$(jq -r ".items[$i].kind" "$claimed")" in db) steps=$((steps + 3)) ;; files) steps=$((steps + 4)) ;; esac
    done
    STEP_NO=0; STEP_TOTAL=$steps
    _selfupdate_progress 5
    {
        echo "[$(date '+%F %T')] restore requested from the bot in $dir ($n file(s))"
    } >> /tmp/ciro_selfupdate.log
    for ((i = 0; i < n; i++)); do
        kind=$(jq -r ".items[$i].kind // empty" "$claimed")
        file=$(jq -r ".items[$i].file // empty" "$claimed")
        entry=$(jq -r ".items[$i].entry // empty" "$claimed")
        pw=$(jq -r ".items[$i].pw // empty" "$claimed")
        path="${inbox}/${file}"
        RESTORE_NOTE=""; RESTORE_FAIL=""; RESTORE_OLD=""; RESTORE_SAFE=""; CARRIED_AGENTS=0; CARRIED_TOKEN=0
        rc=1
        if ! [[ "$file" =~ ^[a-f0-9]{32}\.(zip|sql)$ ]] || [ ! -f "$path" ] || [ -L "$path" ]; then
            RESTORE_FAIL="missing"
        elif [ -z "$dbname" ] || [ -z "$dbuser" ] || [ -z "$dbpass" ]; then
            RESTORE_FAIL="config"
        elif [ "$kind" = "db" ]; then
            if _restore_is_botfolder "$path"; then
                RESTORE_FAIL="kind"
            else
                # run in this shell, not a subshell: it sets RESTORE_* for the message
                RESTORE_PW="$pw" RESTORE_PICK="$entry" RESTORE_YES=1 CIRO_NONINTERACTIVE=1 \
                    _restore_db "$path" "$dir" "$name" "$dbhost" "$dbuser" "$dbpass" "$dbname" >> /tmp/ciro_selfupdate.log 2>&1
                rc=$?
                [ "$rc" -ne 0 ] && [ -z "$RESTORE_FAIL" ] && RESTORE_FAIL="import"
            fi
        elif [ "$kind" = "files" ]; then
            if _restore_is_botfolder "$path"; then
                RESTORE_PW="$pw" RESTORE_YES=1 CIRO_NONINTERACTIVE=1 \
                    _restore_files "$path" "$dir" "$name" "$dbname" >> /tmp/ciro_selfupdate.log 2>&1
                rc=$?
                [ "$rc" -ne 0 ] && [ -z "$RESTORE_FAIL" ] && RESTORE_FAIL="files"
                [ "$rc" -eq 0 ] && [ -n "$RESTORE_OLD" ] && RESTORE_NOTE="old"
            else
                RESTORE_FAIL="kind"
            fi
        else
            RESTORE_FAIL="kind"
        fi
        [ "$rc" -eq 0 ] || allok=0
        item=$(jq -cn --arg k "$kind" --arg note "$RESTORE_NOTE" --arg fail "$RESTORE_FAIL" --arg old "$RESTORE_OLD" \
            --arg safe "$RESTORE_SAFE" --argjson ok "$([ "$rc" -eq 0 ] && echo true || echo false)" \
            --argjson agents "${CARRIED_AGENTS:-0}" --argjson token "${CARRIED_TOKEN:-0}" \
            '{kind:$k, ok:$ok, note:$note, fail:$fail, old:$old, safe:$safe, agents:$agents, token:$token}')
        results="${results:+$results,}$item"
        rm -f "$path"
    done
    results="[${results}]"
    CIRO_RESTORE_PCT=""
    rm -f "$claimed"
    # nothing the admin sent stays on the server
    find "$inbox" -maxdepth 1 -type f \( -name '*.zip' -o -name '*.sql' \) -delete 2>/dev/null
    _selfwatch_caps "$dir"
    if [ "$allok" -eq 1 ] && [ "$n" -gt 0 ]; then
        _selfrestore_status "$dir" "done" "$results"
        _selfrestore_finish_php "$dir" "$results" 1 || _selfupdate_final "done" ""
    else
        _selfrestore_status "$dir" "failed" "$results"
        _selfrestore_finish_php "$dir" "$results" 0 || _selfupdate_final "fail" ""
    fi
    rm -f "${dir}/${CIRO_RESTORE_CTX}"
}

selfupdate_watch() {
    _legacy_migrate locked >> /tmp/ciro_selfupdate.log 2>&1
    bots_registry_ensure
    local dirs; dirs=$(jq -r '.[].dir' "$BOTS_REGISTRY" 2>/dev/null)
    [ -z "$dirs" ] && return 0
    local dir req claimed age rc rbreq want arc top newest rbpid rbpct newbk
    while IFS= read -r dir; do
        [ -n "$dir" ] && [ -d "$dir" ] || continue
        # A claim nobody is working on any more. Only the run that created it
        # ever removes it, so anything that ends that run some other way - a
        # crash, a reboot, or a rollback restoring a snapshot that was taken
        # while a claim was in flight - used to leave the bot's own screen
        # saying "a job is running" for ever, with no way back from inside the
        # bot. Nothing else can be running while this tick holds the lock, so an
        # old one here is by definition finished.
        claimed="${dir}/${CIRO_UPDATE_REQUEST}.running"
        if [ -f "$claimed" ] \
            && [ $(( $(date +%s) - $(stat -c %Y "$claimed" 2>/dev/null || echo 0) )) -gt 1800 ]; then
            rm -f "$claimed"
            # deliberately no status write: whatever the last real run reported
            # is still the truth, and overwriting it would hide it
            echo "[$(date '+%F %T')] cleared a stale claim in $dir" >> /tmp/ciro_selfupdate.log
        fi
        # Refresh the list the bot shows only when a new snapshot has actually
        # appeared - rebuilding it means reading inside every archive, which is
        # not something to do every single minute for nothing.
        # A list naming more than are kept was written before the limit (or by
        # an older copy of this script) - rebuilding it prunes the folder too,
        # so the limit holds within the minute, not only after the next update.
        newest=$(ls -1t "$CIRO_BACKUP_DIR"/pre-update_*.tar.gz 2>/dev/null | head -1)
        if [ ! -f "${dir}/${CIRO_BACKUPS_LIST}" ] \
            || { [ -n "$newest" ] && [ "$newest" -nt "${dir}/${CIRO_BACKUPS_LIST}" ]; } \
            || [ "$(jq 'length' "${dir}/${CIRO_BACKUPS_LIST}" 2>/dev/null || echo 0)" -gt "$CIRO_SNAPSHOTS_KEEP" ]; then
            _selfupdate_publish_backups "$dir"
        fi
        _selfwatch_caps "$dir"
        # ── a backup the admin sent to the bot (📥 ایمپورت بکاپ) ──
        _selfrestore_run "$dir"
        # ── going back to an earlier snapshot ────────────────
        rbreq="${dir}/${CIRO_ROLLBACK_REQUEST}"
        if [ -f "$rbreq" ]; then
            want=$(head -c 200 "$rbreq" 2>/dev/null | tr -d '\r\n')
            rm -f "$rbreq"
            # www-data wrote this name, so it is treated as hostile input: a
            # bare snapshot filename and nothing else - no slashes, no "..",
            # and it must really be one of this bot's own snapshots.
            case "$want" in
                */*|*..*|"") _selfupdate_status "$dir" "failed" "نام نسخه پشتیبان معتبر نبود"; continue ;;
                pre-update_*.tar.gz) ;;
                *) _selfupdate_status "$dir" "failed" "نام نسخه پشتیبان معتبر نبود"; continue ;;
            esac
            arc="${CIRO_BACKUP_DIR}/${want}"
            top=$(tar -tzf "$arc" 2>/dev/null | head -1 | cut -d/ -f1)
            if [ ! -f "$arc" ] || [ "$top" != "$(basename "$dir")" ]; then
                _selfupdate_status "$dir" "failed" "نسخه پشتیبان پیدا نشد"
                continue
            fi
            # read before anything moves: _rollback_update swaps the whole
            # directory out and the context file lives inside it, so waiting
            # until the end would leave nothing to send the last message with
            _selfupdate_ctx_load "$dir"
            _selfupdate_status "$dir" "running" "در حال بازگشت به نسخه قبلی"
            _selfupdate_progress 10
            # Unpacking is one long call with no steps to count, so the bar is
            # walked forward on a timer instead - slowly, and never past 90, so
            # it cannot claim to be finished while the work is still running.
            ( _rollback_update "$arc" "$dir" >> /tmp/ciro_selfupdate.log 2>&1 ) &
            rbpid=$!
            rbpct=10
            while kill -0 "$rbpid" 2>/dev/null; do
                sleep 5
                rbpct=$(( rbpct + 10 ))
                [ "$rbpct" -gt 90 ] && rbpct=90
                _selfupdate_progress "$rbpct"
            done
            wait "$rbpid"
            rc=$?
            # the status file is written back onto the restored tree, which is
            # why it comes after the restore rather than before it
            # rebuilt FIRST: the restored tree brought its own, older copy of
            # this list back with it, and the finished screen is drawn from it
            _selfupdate_publish_backups "$dir"
            if [ "$rc" -eq 0 ]; then
                _selfupdate_status "$dir" "restored" "بازگشت به نسخه قبلی انجام شد"
                _selfupdate_finish_php "$dir" "rollback" "$want" 1 || _selfupdate_final "done" "$want"
            else
                _selfupdate_status "$dir" "failed" "بازگشت به نسخه قبلی ناموفق بود"
                _selfupdate_final "fail" "$want"
            fi
            rm -f "${dir}/${CIRO_PROGRESS_CTX}"
            continue
        fi
        req="${dir}/${CIRO_UPDATE_REQUEST}"
        [ -f "$req" ] || continue
        # Claim it by moving it aside FIRST. If this run is killed half way the
        # request is already gone, so the next minute can never start a second
        # update on top of one still running.
        claimed="${req}.running"
        mv -f "$req" "$claimed" 2>/dev/null || continue
        # An old request is a leftover (server was off, cron disabled). Acting
        # on it hours later would restart the bot at a moment nobody chose.
        age=$(( $(date +%s) - $(stat -c %Y "$claimed" 2>/dev/null || echo 0) ))
        if [ "$age" -gt 900 ]; then
            rm -f "$claimed"
            _selfupdate_status "$dir" "stale" "درخواست قدیمی بود و اجرا نشد"
            continue
        fi
        _selfupdate_ctx_load "$dir"
        _selfupdate_status "$dir" "running" "در حال آپدیت"
        _selfupdate_progress 0
        # STEP_TOTAL is what turns run_step's counter into a percentage. The
        # update path never set it, because a terminal gets a spinner instead -
        # seven is how many run_step calls update_bot makes, and run_step moves
        # the bar itself, so a step added later is counted without anyone
        # remembering to touch this.
        ( CIRO_NONINTERACTIVE=1 CIRO_BOT_DIR="$dir" STEP_TOTAL=7 update_bot ) >> /tmp/ciro_selfupdate.log 2>&1
        rc=$?
        rm -f "$claimed"
        # the snapshot this run just took is what the admin would go back to -
        # republished first so the finished message can name it
        _selfupdate_publish_backups "$dir"
        if [ "$rc" -eq 0 ]; then
            _selfupdate_status "$dir" "done" "آپدیت با موفقیت انجام شد"
            newbk=$(jq -r '.[0].name // empty' "${dir}/${CIRO_BACKUPS_LIST}" 2>/dev/null)
            _selfupdate_finish_php "$dir" "update" "$newbk" 1 || _selfupdate_final "done" "$newbk"
        else
            # update_bot restores its own pre-update snapshot before returning
            # non-zero, so "failed" always means "still on the old version"
            _selfupdate_status "$dir" "failed" "آپدیت ناموفق بود - نسخه قبلی برگردانده شد"
            _selfupdate_final "fail" ""
        fi
        rm -f "${dir}/${CIRO_PROGRESS_CTX}"
    done <<< "$dirs"
}

# The bot turns away an update without ITS webhook secret (setting.webhook_secret);
# a new one made up here would lock it out until it re-registered itself. So
# use the bot's own; one made up here only when it has none yet (its first update
# then registers the bot's own).
_bot_webhook_secret() {
    local s=""
    [ -f "$1/config.php" ] && s=$(cd "$1" && php -r 'error_reporting(0); require "config.php"; echo (string) $pdo->query("SELECT webhook_secret FROM setting LIMIT 1")->fetchColumn();' 2>/dev/null | tr -d '[:space:]')
    printf '%s' "$s" | grep -Eq '^[A-Za-z0-9_-]{1,256}$' || s=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-8)
    printf '%s' "$s"
}

# One crontab line, added once. Matched by its command text rather than the
# whole line so a hand-edited schedule is never duplicated.
_ensure_selfupdate_cron() {
    command -v crontab >/dev/null 2>&1 || return 0
    crontab -l 2>/dev/null | grep -Fq "Ciro selfupdate-watch" && return 0
    ( crontab -l 2>/dev/null; \
      echo "* * * * * flock -n /tmp/ciro_selfupdate.lock /usr/local/bin/Ciro selfupdate-watch >/dev/null 2>&1" \
    ) | crontab - 2>/dev/null
}

# Create the Apache vhost only when it is missing or points somewhere else.
# An operator's customised vhost is backed up, never silently overwritten.
_ensure_vhost() {
    local domain="$1" botdir="$2" port
    local vhost="/etc/apache2/sites-available/${domain}.conf"
    local vhost_ssl="/etc/apache2/sites-available/${domain}-ssl.conf"
    local need=0
    [ -f "$vhost" ] || need=1
    [ -f "$vhost" ] && ! grep -q "DocumentRoot $botdir" "$vhost" 2>/dev/null && need=1
    if [ "$need" -eq 0 ]; then
        printf "    ${C_OK}✔${CR} ${C_DIM}Apache vhost already correct - left untouched${CR}\n"
        return 0
    fi
    [ -f "$vhost" ] && cp "$vhost" "${vhost}.bak_$(date +%Y%m%d_%H%M%S)"
    tee "$vhost" >/dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $botdir
    <Directory $botdir>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
EOF
    port=$(bots_registry_bot_port "$botdir")
    if [ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ] && [ ! -f "$vhost_ssl" ]; then
        tee "$vhost_ssl" >/dev/null <<EOF
<VirtualHost *:${port}>
    ServerName $domain
    DocumentRoot $botdir
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/${domain}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/${domain}/privkey.pem
    <Directory $botdir>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
EOF
        a2ensite "${domain}-ssl.conf" >/dev/null 2>&1 || true
    fi
    a2ensite "${domain}.conf" >/dev/null 2>&1 || true
    a2enmod rewrite ssl >/dev/null 2>&1 || true
    ciro_listen_sync "$port"
    if apache2ctl configtest >/dev/null 2>&1; then
        systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1
        printf "    ${C_OK}✔${CR} ${C_DIM}Apache vhost written and reloaded${CR}\n"
    else
        printf "    ${C_WARN}!${CR} ${C_WARN}Apache config test failed - not reloading.${CR}\n"
    fi
    # The vhosts above carry no phpMyAdmin; put it back where its port says
    pma_sync_all
}


# Save a bot's database to /root before it is deleted: prints the file and
# returns 0, or returns 1 when it could not be saved.
_db_dump_before_remove() {
    local dbname="$1" rootpass dump
    rootpass=$(grep '$pass' /root/Ciro/dbroot.txt 2>/dev/null | cut -d"'" -f2)
    [ -n "$dbname" ] && [ -n "$rootpass" ] || return 1
    dump="/root/ciro_before_remove_${dbname}_$(date +%Y-%m-%d_%H-%M-%S).sql"
    if mysqldump -u root -p"$rootpass" --no-tablespaces "$dbname" > "$dump" 2>/dev/null && [ -s "$dump" ]; then
        echo "$dump"; return 0
    fi
    rm -f "$dump"; return 1
}

# Drop one bot's scheduled jobs: the cronbot/ lines its panel put in
# www-data's crontab, every one calling https://<its $domainhosts>/cronbot/.
# Every other line - another bot's, anything an admin added - stays.
# Returns 1 when there was nothing of this bot's to remove.
_bot_crons_remove() {
    local hosts="$1" cur
    [ -n "$hosts" ] && command -v crontab >/dev/null 2>&1 || return 1
    cur=$(crontab -u www-data -l 2>/dev/null) || return 1
    printf '%s\n' "$cur" | grep -qF "://${hosts}/cronbot/" || return 1
    printf '%s\n' "$cur" | grep -vF "://${hosts}/cronbot/" | crontab -u www-data -
}

# Remove exactly one bot - its directory, its own database and DB user, its
# own Apache vhost files, its own SSL certificate, its scheduled jobs and its
# rollback snapshots - and leave MySQL, Apache itself, phpMyAdmin and every
# other bot on this server untouched. Used only when more than one bot is
# registered; a single-bot server keeps the whole-server removal below.
# $4 = "yes" when the caller has already asked (Transfer does).
remove_bot_scoped() {
    local name="$1" dir="$2" domain="$3" asked="${4-}"
    local cfg="$dir/config.php" dbname="" dbuser="" hosts=""
    [ "$domain" = "null" ] && domain=""
    if [ -f "$cfg" ]; then
        dbname=$(grep '^\$dbname' "$cfg" | cut -d"'" -f2)
        dbuser=$(grep '^\$usernamedb' "$cfg" | cut -d"'" -f2)
        hosts=$(grep '^\$domainhosts' "$cfg" | cut -d"'" -f2)
    fi
    [ -z "$hosts" ] && hosts="$domain"

    _sec "Remove ${name}"
    _kv "Domain" "${C_DIM}${domain:--}${CR}"
    _kv "Directory" "${C_DIM}${dir}${CR}"
    [ -n "$dbname" ] && _kv "Database" "${C_DIM}${dbname}${CR}"
    echo ""
    printf "    ${C_WARN}!${CR} ${C_WARN}Deletes this bot's files, database, certificate, web config,${CR}\n"
    printf "    ${C_WARN}  scheduled jobs and rollback snapshots.${CR}\n"
    printf "    ${C_DIM}The other bot(s) on this server are not touched. The database is${CR}\n"
    printf "    ${C_DIM}saved to /root first.${CR}\n"
    if [ "$asked" != "yes" ]; then
        printf "  ${C_PROMPT}❯${CR} Remove %s? ${C_DIM}[y/N]${CR}: " "$name"
        local choice; read -r choice
        if [[ ! "$choice" =~ ^[Yy]$ ]]; then
            printf "\n    ${C_DIM}Cancelled - nothing was changed.${CR}\n"
            return 0
        fi
    fi
    echo ""

    # 1. Offline first: no webhook and no scheduled job may touch the
    #    database while it is being saved and dropped.
    if [ -n "$domain" ]; then
        a2dissite "${domain}.conf" >/dev/null 2>&1
        a2dissite "${domain}-ssl.conf" >/dev/null 2>&1
        a2dissite "${domain}-pma.conf" >/dev/null 2>&1
        rm -f "/etc/apache2/sites-available/${domain}.conf" "/etc/apache2/sites-available/${domain}-ssl.conf" "/etc/apache2/sites-available/${domain}-pma.conf"
        rm -f "/etc/apache2/sites-enabled/${domain}.conf" "/etc/apache2/sites-enabled/${domain}-ssl.conf" "/etc/apache2/sites-enabled/${domain}-pma.conf"
        systemctl reload apache2 >/dev/null 2>&1
        printf "    ${C_OK}✔${CR} ${C_DIM}Web config for %s removed${CR}\n" "$domain"
    fi
    _bot_crons_remove "$hosts" && printf "    ${C_OK}✔${CR} ${C_DIM}Scheduled jobs removed${CR}\n"

    # 2. The database: saved, then dropped
    if [ -n "$dbname" ]; then
        local dump
        if dump=$(_db_dump_before_remove "$dbname"); then
            printf "    ${C_OK}✔${CR} ${C_DIM}Database saved to %s${CR}\n" "$dump"
        else
            printf "    ${C_WARN}!${CR} ${C_WARN}Could not save the database %s first.${CR}\n" "$dbname"
            printf "  ${C_PROMPT}❯${CR} Delete it anyway? ${C_DIM}[y/N]${CR}: "
            local anyway; read -r anyway
            if [[ ! "$anyway" =~ ^[Yy]$ ]]; then
                printf "    ${C_DIM}Database %s kept.${CR}\n" "$dbname"
                dbname=""
            fi
        fi
    fi
    if [ -n "$dbname" ]; then
        local ROOT_PASS
        ROOT_PASS=$(grep '$pass' /root/Ciro/dbroot.txt 2>/dev/null | cut -d"'" -f2)
        if [ -n "$ROOT_PASS" ]; then
            if mysql -u root -p"$ROOT_PASS" -e "DROP DATABASE IF EXISTS \`$dbname\`;" 2>/dev/null; then
                printf "    ${C_OK}✔${CR} ${C_DIM}Database %s removed${CR}\n" "$dbname"
            else
                printf "    ${C_BAD}●${CR} ${C_BAD}Could not drop database %s (continuing).${CR}\n" "$dbname"
            fi
            if [ -n "$dbuser" ]; then
                mysql -u root -p"$ROOT_PASS" -e "DROP USER IF EXISTS '$dbuser'@'localhost'; DROP USER IF EXISTS '$dbuser'@'%';" 2>/dev/null
            fi
        else
            printf "    ${C_BAD}●${CR} ${C_BAD}Could not read the MySQL root password - database left in place.${CR}\n"
        fi
    fi

    # 3. Certificate, files, and the snapshots - a bot installed into this
    #    same directory later must never be offered this one's (restoring one
    #    would bring back its config.php, token and all)
    if [ -n "$domain" ] && command -v certbot >/dev/null 2>&1; then
        certbot delete --cert-name "$domain" --non-interactive >/dev/null 2>&1 \
            && printf "    ${C_OK}✔${CR} ${C_DIM}SSL certificate removed${CR}\n"
    fi
    CIRO_SNAPSHOTS_KEEP=0 _snapshots_prune "$dir"
    printf "    ${C_OK}✔${CR} ${C_DIM}Rollback snapshots removed${CR}\n"
    if [ -d "$dir" ]; then
        if rm -rf "$dir"; then
            printf "    ${C_OK}✔${CR} ${C_DIM}Bot directory removed: %s${CR}\n" "$dir"
        else
            printf "    ${C_BAD}●${CR} ${C_BAD}Failed to remove %s.${CR}\n" "$dir"
        fi
    fi

    bots_registry_remove_by_dir "$dir"
    # Its phpMyAdmin port may now be unused: drop that Listen
    pma_sync_all
    echo ""
    printf "    ${C_OK}✔${CR} ${C_OK}%s has been removed.${CR}\n" "$name"
}
function remove_bot() {
    bots_registry_ensure
    local _bot_count; _bot_count=$(jq 'length' "$BOTS_REGISTRY" 2>/dev/null)
    if [ -n "$_bot_count" ] && [ "$_bot_count" -gt 1 ]; then
        clear 2>/dev/null || true
        banner
        # [0] in the bot list goes straight back
        pick_bot_instance "remove" || { show_menu; return 0; }
        remove_bot_scoped "$PICKED_NAME" "$PICKED_DIR" "$PICKED_DOMAIN"
        echo ""
        printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
        read -r _
        show_menu
        return 0
    fi

    echo -e "\e[33mStarting Ciro removal process...\033[0m"
    LOG_FILE="/var/log/remove_bot.log"
    echo "Log file: $LOG_FILE" > "$LOG_FILE"
    # The last bot left is not always in the default directory: remove the
    # first of two bots and the second one is all that remains.
    BOT_DIR="/var/www/html/Ciro"
    [ "$_bot_count" = "1" ] && BOT_DIR=$(jq -r '.[0].dir' "$BOTS_REGISTRY" 2>/dev/null)
    if [ -z "$BOT_DIR" ] || [ ! -d "$BOT_DIR" ]; then
        echo -e "\e[31m[ERROR]\033[0m Ciro is not installed (${BOT_DIR:-/var/www/html/Ciro} not found)." | tee -a "$LOG_FILE"
        echo -e "\e[33mNothing to remove.\033[0m" | tee -a "$LOG_FILE"
        sleep 2
        show_menu
        return 1
    fi
    read -p "Are you sure you want to remove Ciro and its dependencies? (y/n): " choice
    if [[ ! "$choice" =~ ^[Yy]$ ]]; then
        echo "Aborting..." | tee -a "$LOG_FILE"
        sleep 1
        show_menu
        return 0
    fi
    echo "Removing Ciro..." | tee -a "$LOG_FILE"
    CONFIG_PATH="$BOT_DIR/config.php"
    # Before anything goes: a copy of the database (MySQL itself is purged
    # below), and what this bot has outside its directory - its scheduled
    # jobs, its rollback snapshots, and the update watcher (no bot is left
    # for it to watch).
    local _db _hosts _dump
    _db=$(grep '^\$dbname' "$CONFIG_PATH" 2>/dev/null | cut -d"'" -f2)
    _hosts=$(grep '^\$domainhosts' "$CONFIG_PATH" 2>/dev/null | cut -d"'" -f2)
    if [ -n "$_db" ]; then
        if _dump=$(_db_dump_before_remove "$_db"); then
            echo -e "\e[92mDatabase saved to $_dump\033[0m" | tee -a "$LOG_FILE"
        else
            echo -e "\e[93mCould not save the database $_db first.\033[0m" | tee -a "$LOG_FILE"
            read -p "Remove everything anyway? (y/n): " choice
            if [[ ! "$choice" =~ ^[Yy]$ ]]; then
                echo "Aborting..." | tee -a "$LOG_FILE"
                sleep 1
                show_menu
                return 0
            fi
        fi
    fi
    _bot_crons_remove "$_hosts" && echo -e "\e[92mScheduled jobs removed.\033[0m" | tee -a "$LOG_FILE"
    CIRO_SNAPSHOTS_KEEP=0 _snapshots_prune "$BOT_DIR"
    rmdir "$CIRO_BACKUP_DIR" 2>/dev/null
    ( crontab -l 2>/dev/null | grep -vF "Ciro selfupdate-watch" ) | crontab - 2>/dev/null
    if [ -f "$CONFIG_PATH" ]; then
        sudo shred -u -n 5 "$CONFIG_PATH" && echo -e "\e[92mConfig file securely removed: $CONFIG_PATH\033[0m" | tee -a "$LOG_FILE" || {
            echo -e "\e[91mFailed to securely remove config file: $CONFIG_PATH\033[0m" | tee -a "$LOG_FILE"
        }
    fi
    if [ -d "$BOT_DIR" ]; then
        sudo rm -rf "$BOT_DIR" && echo -e "\e[92mBot directory removed: $BOT_DIR\033[0m" | tee -a "$LOG_FILE" || {
            echo -e "\e[91mFailed to remove bot directory: $BOT_DIR. Exiting...\033[0m" | tee -a "$LOG_FILE"
            exit 1
        }
    fi
    echo -e "\e[33mRemoving MySQL and database...\033[0m" | tee -a "$LOG_FILE"
    sudo systemctl stop mysql
    sudo systemctl disable mysql
    sudo systemctl daemon-reload
    sudo apt --fix-broken install -y
    sudo apt-get purge -y mysql-server mysql-client mysql-common mysql-server-core-* mysql-client-core-*
    sudo rm -rf /etc/mysql /var/lib/mysql /var/log/mysql /var/log/mysql.* /usr/lib/mysql /usr/include/mysql /usr/share/mysql
    sudo rm /lib/systemd/system/mysql.service
    sudo rm /etc/init.d/mysql
    sudo dpkg --remove --force-remove-reinstreq mysql-server mysql-server-8.0 mysql-server-8.4
    sudo find /etc/systemd /lib/systemd /usr/lib/systemd -name "*mysql*" -exec rm -f {} \;
    sudo apt-get purge -y 'mysql-server*' 'mysql-client*'
    sudo apt-get purge -y mysql-common php-mysql php8.2-mysql php8.3-mysql php8.4-mysql php-mariadb-mysql-kbs
    sudo apt-get autoremove --purge -y
    sudo apt-get clean
    sudo apt-get update --allow-releaseinfo-change
    echo -e "\e[92mMySQL has been completely removed.\033[0m" | tee -a "$LOG_FILE"
    echo -e "\e[33mRemoving PHPMyAdmin...\033[0m" | tee -a "$LOG_FILE"
    if dpkg -s phpmyadmin &>/dev/null; then
        sudo apt-get purge -y phpmyadmin && echo -e "\e[92mPHPMyAdmin removed.\033[0m" | tee -a "$LOG_FILE"
        sudo apt-get autoremove -y && sudo apt-get autoclean -y
    else
        echo -e "\e[93mPHPMyAdmin is not installed.\033[0m" | tee -a "$LOG_FILE"
    fi
    echo -e "\e[33mRemoving Apache...\033[0m" | tee -a "$LOG_FILE"
    sudo systemctl stop apache2 || {
        echo -e "\e[91mFailed to stop Apache. Continuing anyway...\033[0m" | tee -a "$LOG_FILE"
    }
    sudo systemctl disable apache2 || {
        echo -e "\e[91mFailed to disable Apache. Continuing anyway...\033[0m" | tee -a "$LOG_FILE"
    }
    sudo apt-get purge -y apache2 apache2-utils apache2-bin apache2-data libapache2-mod-php* || {
        echo -e "\e[91mFailed to purge Apache packages.\033[0m" | tee -a "$LOG_FILE"
    }
    sudo apt-get autoremove --purge -y
    sudo apt-get autoclean -y
    sudo rm -rf /etc/apache2 /var/www/html
    echo -e "\e[33mRemoving Apache and PHP configurations...\033[0m" | tee -a "$LOG_FILE"
    sudo a2disconf phpmyadmin.conf &>/dev/null
    sudo rm -f /etc/apache2/conf-available/phpmyadmin.conf
    echo -e "\e[33mRemoving additional packages...\033[0m" | tee -a "$LOG_FILE"
    sudo apt-get remove -y php-soap php-ssh2 libssh2-1-dev libssh2-1 \
        && echo -e "\e[92mRemoved additional PHP packages.\033[0m" | tee -a "$LOG_FILE" || echo -e "\e[93mSome additional PHP packages may not be installed.\033[0m" | tee -a "$LOG_FILE"
    echo -e "\e[33mResetting firewall rules (except SSL)...\033[0m" | tee -a "$LOG_FILE"
    sudo ufw delete allow 'Apache' 2>/dev/null
    sudo ufw reload 2>/dev/null
    # Clear the install state so a fresh install is allowed afterwards
    sudo rm -rf /root/Ciro
    echo -e "\e[92mCiro, MySQL, and their dependencies have been completely removed.\033[0m" | tee -a "$LOG_FILE"
    [ -n "$_dump" ] && echo -e "\e[92mThe bot's database is kept in $_dump\033[0m" | tee -a "$LOG_FILE"
}

# ── Migrate a bot of the original project to Ciro ─────────────
# The old bot keeps running, untouched, until the new code is completely
# ready beside it. Its database stays exactly where it is - same name, user
# and password - and table.php only ADDS what Ciro needs, the way Update
# upgrades an original install, so no table, row, setting, admin, channel or
# panel status is dropped or changed. The database and the old files are
# copied to /root first, and a failure before the switch leaves the old bot
# exactly as it was; a failure during it puts the old bot back.

_mig_fail() {
    printf "\n    ${C_BAD}●${CR} ${C_BAD}%s${CR}\n" "$1"
    [ -n "${2-}" ] && printf "    ${C_DIM}%s${CR}\n" "$2"
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

# A bot of the original project on this server: its folder, in one of the
# places the original installers use, with a config.php and no version file
# of ours. Nothing when there is none. (/var/www/html/Ciro holding the
# original's code is not one of them: Update upgrades that in place.)
original_bot_dir() {
    local c
    for c in "$LEGACY_ORIGINAL_DIR" "$LEGACY_BOT_DIR"; do
        [ -f "$c/config.php" ] && [ ! -f "$c/version" ] && { echo "$c"; return 0; }
    done
    return 1
}
# Is a Ciro bot (one with our version file) installed here already?
ciro_bot_present() {
    local d
    for d in "$BOT_DIR_DEFAULT" "$LEGACY_BOT_DIR" \
        $( [ -f "$BOTS_REGISTRY" ] && jq -r '.[].dir' "$BOTS_REGISTRY" 2>/dev/null ); do
        [ -f "$d/config.php" ] && [ -f "$d/version" ] && return 0
    done
    return 1
}
# Install and Update call this first: a server that runs the original
# project's bot and no Ciro bot yet has that bot moved over (its database
# kept and upgraded) instead - nobody has to know about Migrate. Returns 0
# when it took over, 1 when there is nothing of the kind here.
auto_migrate_original() {
    { [ -n "${CIRO_NONINTERACTIVE:-}" ] || [ -n "${CIRO_TRANSFER:-}" ]; } && return 1
    original_bot_dir >/dev/null || return 1
    ciro_bot_present && return 1
    CIRO_MIGRATE_AUTO=1 migrate_to_ciro
    return 0
}

# Put the old bot's files back where they were ($1 = new dir, $2 = old dir)
_mig_undo_files() {
    local target="$1" old="$2"
    if [ -d "${old}.premigrate" ]; then
        rm -rf "$target"
        mv "${old}.premigrate" "$old"
    elif [ "$target" != "$old" ]; then
        rm -rf "$target"
    fi
}

function migrate_to_ciro() {
    clear 2>/dev/null || true
    banner
    if [ -n "${CIRO_MIGRATE_AUTO:-}" ]; then
        _sec "A bot of the original Mirza project is on this server"
        printf "    ${C_DIM}It is moved to Ciro first - its database is kept and only added to.${CR}\n\n"
    else
        _sec "Migrate to Ciro"
    fi

    # 1) The original install: a config.php and no version file of ours
    local OLD_BOT_DIR="" _cand _ours=""
    for _cand in "$LEGACY_ORIGINAL_DIR" "$LEGACY_BOT_DIR" "$BOT_DIR_DEFAULT"; do
        [ -f "$_cand/config.php" ] || continue
        if [ -f "$_cand/version" ]; then _ours="$_cand"; continue; fi
        OLD_BOT_DIR="$_cand"; break
    done
    if [ -z "$OLD_BOT_DIR" ]; then
        if [ -n "$_ours" ]; then
            _mig_fail "This server already runs Ciro - there is nothing to migrate." \
                "Use option 2 (Update) to bring it up to date."
        else
            _mig_fail "No bot of the original project found on this server."
        fi
        return 1
    fi
    local cfg="$OLD_BOT_DIR/config.php"
    local dbhost dbname dbuser dbpass token admin botname hosts domain
    dbhost=$(grep '^\$dbhost' "$cfg" | head -1 | cut -d"'" -f2)
    dbname=$(grep '^\$dbname' "$cfg" | head -1 | cut -d"'" -f2)
    dbuser=$(grep '^\$usernamedb' "$cfg" | head -1 | cut -d"'" -f2)
    dbpass=$(grep '^\$passworddb' "$cfg" | head -1 | cut -d"'" -f2)
    token=$(grep '^\$APIKEY' "$cfg" | head -1 | cut -d"'" -f2)
    admin=$(grep '^\$adminnumber' "$cfg" | head -1 | cut -d"'" -f2)
    botname=$(grep '^\$usernamebot' "$cfg" | head -1 | cut -d"'" -f2)
    hosts=$(grep '^\$domainhosts' "$cfg" | head -1 | cut -d"'" -f2)
    domain=$(printf '%s' "$hosts" | cut -d'/' -f1)
    [ -z "$dbhost" ] && dbhost="localhost"
    if [ -z "$dbname" ] || [ -z "$dbuser" ] || [ -z "$token" ] || [ -z "$domain" ]; then
        _mig_fail "Could not read the old bot's settings from $cfg." "Nothing was changed."
        return 1
    fi

    # 2) Everything that would stop it half way is checked before anything moves
    local phpv; phpv=$(php -r 'echo PHP_VERSION_ID;' 2>/dev/null)
    if [ -z "$phpv" ] || [ "$phpv" -lt 80200 ]; then
        _mig_fail "Ciro needs PHP 8.2 or newer; this server runs PHP $(active_php_ver)." "Nothing was changed."
        return 1
    fi
    if ! mysql -h "$dbhost" -u "$dbuser" -p"$dbpass" -e "SELECT 1;" "$dbname" >/dev/null 2>&1; then
        _mig_fail "Could not log in to the old database ($dbname) with the old bot's own credentials." \
            "Is MySQL running? Nothing was changed."
        return 1
    fi
    if [ ! -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
        _mig_fail "No SSL certificate found for ${domain} (/etc/letsencrypt/live/${domain})." \
            "The webhook needs HTTPS. Nothing was changed."
        return 1
    fi
    bots_registry_ensure
    if jq -e --arg d "$domain" --arg o "$OLD_BOT_DIR" 'any(.[]; .domain == $d and .dir != $o)' "$BOTS_REGISTRY" >/dev/null 2>&1; then
        _mig_fail "Another Ciro bot on this server already uses ${domain}." "Nothing was changed."
        return 1
    fi
    if ! ensure_connectivity; then
        _mig_fail "No internet connection (even after a DNS reset)." "Nothing was changed."
        return 1
    fi

    # 3) Where it goes: the first Ciro folder not already taken by another
    #    bot - so a Ciro bot already on this server is never written over
    local TARGET n=""
    while :; do
        TARGET="/var/www/html/Ciro${n}"
        { [ "$TARGET" = "$OLD_BOT_DIR" ] || [ ! -e "$TARGET" ]; } && break
        n=$(( ${n:-1} + 1 ))
    done
    # agents' own bots, living inside the old folder
    local agents=() _a
    for _a in "$OLD_BOT_DIR"/vpnbot/*/; do
        _a=$(basename "$_a")
        case "$_a" in Default|update|'*') continue ;; esac
        agents+=("$_a")
    done
    # anything else the old default site was serving from /var/www/html
    local others="" _d
    for _d in /var/www/html/*/; do
        _d=${_d%/}
        [ "$_d" = "$OLD_BOT_DIR" ] && continue
        jq -e --arg d "$_d" 'any(.[]; .dir == $d)' "$BOTS_REGISTRY" >/dev/null 2>&1 && continue
        others="${others}${_d}"$'\n'
    done

    _kv "Old bot" "${C_KEY}${botname:+@}${botname:-?}${CR} ${C_DIM}· original project${CR}"
    _kv "Domain" "${C_DIM}${domain}${CR}"
    _kv "From" "${C_DIM}${OLD_BOT_DIR}${CR}"
    _kv "To" "${C_DIM}${TARGET}${CR}"
    _kv "Database" "${C_DIM}${dbname} · kept as it is${CR}"
    [ "${#agents[@]}" -gt 0 ] && _kv "Agent bots" "${C_DIM}${#agents[@]} - moved along${CR}"
    echo ""
    printf "    ${C_DIM}Nothing is deleted from the database: users, orders, settings,${CR}\n"
    printf "    ${C_DIM}admins, channels and panels all stay; Ciro only adds to it.${CR}\n"
    printf "    ${C_DIM}The database and the old files are saved to /root first.${CR}\n"
    if [ -n "$others" ]; then
        printf "\n    ${C_WARN}!${CR} ${C_WARN}The old setup also served these folders; after the move they no${CR}\n"
        printf "    ${C_WARN}  longer answer on %s:${CR}\n" "$domain"
        printf '%s' "$others" | sed 's/^/      /'
    fi
    echo ""
    local confirm
    if [ -n "${CIRO_MIGRATE_AUTO:-}" ]; then
        printf "  ${C_PROMPT}❯${CR} Move it to Ciro now? ${C_DIM}[Y/n]${CR}: "
        read -r confirm
        [ -z "$confirm" ] && confirm=y
    else
        printf "  ${C_PROMPT}❯${CR} Migrate now? ${C_DIM}[y/N]${CR}: "
        read -r confirm
    fi
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        printf "\n    ${C_DIM}Nothing was changed.${CR}\n"
        sleep 1; show_menu; return 0
    fi
    echo ""

    # 4) Copies first
    local stamp; stamp=$(date +%Y-%m-%d_%H-%M-%S)
    local DB_BK="/root/ciro_before_migrate_${dbname}_${stamp}.sql"
    local FILES_BK="/root/ciro_before_migrate_files_${stamp}.tar.gz"
    run_step "Saving the database" \
        "mysqldump -h '$dbhost' -u '$dbuser' -p'$dbpass' --no-tablespaces '$dbname' > '$DB_BK' && [ -s '$DB_BK' ]" \
        || { show_step_error; rm -f "$DB_BK"; _mig_fail "Could not save the database." "Nothing was changed."; return 1; }
    run_step "Saving the old files" \
        "tar -czf '$FILES_BK' -C '$(dirname "$OLD_BOT_DIR")' '$(basename "$OLD_BOT_DIR")'" \
        || { show_step_error; rm -f "$FILES_BK"; _mig_fail "Could not save the old files." "Nothing was changed."; return 1; }

    # 5) The new code, built beside the running bot (outside the web root)
    choose_source
    local STAGE="/var/www/.ciro_migrate" TEMP_DIR="/tmp/cirobot_mig"
    rm -rf "$STAGE" "$TEMP_DIR"; mkdir -p "$TEMP_DIR"
    run_step "Downloading Ciro" \
        "curl -fsSL --max-time 180 -o '$TEMP_DIR/bot.zip' '$SRC_ZIP_URL' || wget -q -O '$TEMP_DIR/bot.zip' '$SRC_ZIP_URL'" \
        || { show_step_error; rm -rf "$TEMP_DIR"; _mig_fail "Download failed." "Nothing was changed."; return 1; }
    run_step "Extracting the package" "unzip -o -q '$TEMP_DIR/bot.zip' -d '$TEMP_DIR'" \
        || { show_step_error; rm -rf "$TEMP_DIR"; _mig_fail "The package could not be extracted." "Nothing was changed."; return 1; }
    local EXTRACTED; EXTRACTED=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -1)
    if [ -z "$EXTRACTED" ] || [ ! -f "$EXTRACTED/index.php" ] || [ ! -f "$EXTRACTED/table.php" ] || [ ! -f "$EXTRACTED/admin.php" ]; then
        rm -rf "$TEMP_DIR"
        _mig_fail "The downloaded package does not look like Ciro." "Nothing was changed."
        return 1
    fi
    rm -f "$EXTRACTED/config.php"
    mv "$EXTRACTED" "$STAGE"
    rm -rf "$TEMP_DIR"
    cat <<EOF > "$STAGE/config.php"
<?php
// This variable added for high load panels which their response time is long and bot can't communicate with online panel!
// null for default settings
\$request_exec_timeout = null;
\$dbhost = '${dbhost}';
\$dbname = '${dbname}';
\$usernamedb = '${dbuser}';
\$passworddb = '${dbpass}';
\$connect = mysqli_connect(\$dbhost, \$usernamedb, \$passworddb, \$dbname);
if (\$connect->connect_error) { die("error" . \$connect->connect_error); }
mysqli_set_charset(\$connect, "utf8mb4");
\$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
\$dsn = "mysql:host=\$dbhost;dbname=\$dbname;charset=utf8mb4";
try { \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options); } catch (\PDOException \$e) { error_log("Database connection failed: " . \$e->getMessage()); }
\$APIKEY = '${token}';
\$adminnumber = '${admin}';
\$domainhosts = '${domain}';
\$usernamebot = '${botname}';
?>
EOF
    run_step "Installing PHP dependencies (composer)" "install_php_deps '$STAGE'" \
        || { show_step_error; rm -rf "$STAGE"; _mig_fail "Could not install PHP dependencies." "Nothing was changed."; return 1; }
    local f bad=""
    for f in index.php admin.php function.php keyboard.php table.php config.php; do
        [ -f "$STAGE/$f" ] || continue
        php -l "$STAGE/$f" >/dev/null 2>&1 || bad="$bad $f"
    done
    if [ -n "$bad" ]; then
        rm -rf "$STAGE"
        _mig_fail "PHP rejects the new code on this server:${bad}" "Nothing was changed."
        return 1
    fi

    # 6) The switch. From here a failure puts the old bot back.
    if [ "$TARGET" = "$OLD_BOT_DIR" ]; then
        rm -rf "${OLD_BOT_DIR}.premigrate"
        mv "$OLD_BOT_DIR" "${OLD_BOT_DIR}.premigrate"
    fi
    mv "$STAGE" "$TARGET"
    local OLD_NOW="$OLD_BOT_DIR"
    [ -d "${OLD_BOT_DIR}.premigrate" ] && OLD_NOW="${OLD_BOT_DIR}.premigrate"
    # agents' bots move along, keeping their data; a config naming the old
    # folder by its full path is pointed at the new one
    for _a in "${agents[@]}"; do
        cp -a "$OLD_NOW/vpnbot/$_a" "$TARGET/vpnbot/" 2>/dev/null
        [ -f "$TARGET/vpnbot/$_a/config.php" ] \
            && sed -i "s#${OLD_BOT_DIR}/#${TARGET}/#g" "$TARGET/vpnbot/$_a/config.php"
    done
    chown -R www-data:www-data "$TARGET"
    chmod -R 755 "$TARGET"

    # additive only: creates what is missing, adds missing columns, keeps rows
    if ! run_step "Upgrading the database (adds only)" "cd '$TARGET' && php table.php"; then
        show_step_error
        _mig_undo_files "$TARGET" "$OLD_BOT_DIR"
        _mig_fail "The database upgrade failed - the old bot was put back." \
            "Anything table.php added is harmless to it. Copy of the database: $DB_BK"
        return 1
    fi
    chown -R www-data:www-data "$TARGET" 2>/dev/null
    # its scheduled jobs (payment checks, notices...), made now as the web
    # server's user would - not left for the first time the panel is opened
    local mig_crons=0
    cat > /tmp/ciro_cron_setup.php <<PHPEOF
<?php
chdir('$TARGET');
require 'config.php';
require 'botapi.php';
require 'panels.php';
require 'function.php';
require_once 'jdf.php';
require 'vendor/autoload.php';
if (!function_exists('activecron')) { exit(3); }
activecron();
PHPEOF
    chmod 644 /tmp/ciro_cron_setup.php
    ( cd "$TARGET" && runuser -u www-data -- php /tmp/ciro_cron_setup.php ) >/dev/null 2>&1 && mig_crons=1
    rm -f /tmp/ciro_cron_setup.php

    local abak; abak=$(mktemp -d)
    cp -a /etc/apache2/sites-available /etc/apache2/sites-enabled \
          /etc/apache2/conf-available /etc/apache2/conf-enabled "$abak"/
    a2dissite 000-default.conf >/dev/null 2>&1
    a2dissite 000-default-le-ssl.conf >/dev/null 2>&1
    a2dissite default-ssl.conf >/dev/null 2>&1
    tee "/etc/apache2/sites-available/${domain}.conf" > /dev/null <<EOF
<VirtualHost *:80>
    ServerName $domain
    DocumentRoot $TARGET
    <Directory $TARGET>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    Include /etc/apache2/conf-available/phpmyadmin.conf
    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
EOF
    tee "/etc/apache2/sites-available/${domain}-ssl.conf" > /dev/null <<EOF
<VirtualHost *:443>
    ServerName $domain
    DocumentRoot $TARGET
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$domain/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$domain/privkey.pem
    <Directory $TARGET>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    Include /etc/apache2/conf-available/phpmyadmin.conf
    ErrorLog \${APACHE_LOG_DIR}/${domain}-error.log
    CustomLog \${APACHE_LOG_DIR}/${domain}-access.log combined
</VirtualHost>
EOF
    a2ensite "${domain}.conf" >/dev/null 2>&1
    a2ensite "${domain}-ssl.conf" >/dev/null 2>&1
    a2enmod ssl rewrite >/dev/null 2>&1
    local aout
    if ! aout=$(apache2ctl configtest 2>&1); then
        _pma_restore "$abak"; rm -rf "$abak"
        _mig_undo_files "$TARGET" "$OLD_BOT_DIR"
        printf '%s\n' "$aout" | tail -n 5 | sed 's/^/      /'
        _mig_fail "Apache rejected the new site - everything was put back as it was."
        return 1
    fi
    systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1
    if ! systemctl is-active --quiet apache2; then
        _pma_restore "$abak"; rm -rf "$abak"
        _mig_undo_files "$TARGET" "$OLD_BOT_DIR"
        systemctl restart apache2 >/dev/null 2>&1
        _mig_fail "Apache would not start with the new site - everything was put back as it was."
        return 1
    fi
    rm -rf "$abak"
    printf "    ${C_OK}✔${CR} ${C_DIM}Apache now serves %s from %s${CR}\n" "$domain" "$TARGET"

    # 7) Telegram: the bot, and every agent bot that came along
    local secret hook
    secret=$(_bot_webhook_secret "$TARGET")
    hook=$(curl -s --max-time 20 -F "url=https://${domain}/index.php" -F "secret_token=${secret}" \
        "https://api.telegram.org/bot${token}/setWebhook" 2>/dev/null)
    local hook_ok=0
    printf '%s' "$hook" | grep -q '"ok":true' && hook_ok=1
    local agents_hooked=0 _row _tok _uid _uname
    if [ "${#agents[@]}" -gt 0 ]; then
        while IFS=$'\t' read -r _tok _uid _uname; do
            [ -n "$_tok" ] && [ -d "$TARGET/vpnbot/${_uid}${_uname}" ] || continue
            curl -s --max-time 20 "https://api.telegram.org/bot${_tok}/setWebhook?url=https://${domain}/vpnbot/${_uid}${_uname}/index.php" \
                | grep -q '"ok":true' && agents_hooked=$((agents_hooked + 1))
        done < <(mysql -N -B -h "$dbhost" -u "$dbuser" -p"$dbpass" "$dbname" \
                    -e "SELECT bot_token, id_user, username FROM botsaz;" 2>/dev/null)
    fi

    # 8) A Ciro bot like any other from here on
    bots_registry_add "${botname:-Bot}" "$TARGET" "$domain"
    _ensure_selfupdate_cron
    # the old bot's scheduled jobs call its old address; the new bot adds its
    # own the first time the admin opens the panel (activecron)
    [ "$hosts" != "$domain" ] && _bot_crons_remove "$hosts"
    ssl_renewal_ensure "$domain"
    if [ -d "${OLD_BOT_DIR}.premigrate" ]; then
        rm -rf "${OLD_BOT_DIR}.premigrate"
    elif [ "$TARGET" != "$OLD_BOT_DIR" ]; then
        rm -rf "$OLD_BOT_DIR"
    fi
    ln -sf /root/install.sh /usr/local/bin/Ciro 2>/dev/null

    _sec "Migrated to Ciro"
    _kv "Bot" "${C_KEY}${botname:+@}${botname:-?}${CR}"
    _kv "Domain" "${C_DIM}https://${domain}${CR}"
    _kv "Folder" "${C_DIM}${TARGET}${CR}"
    _kv "Database" "${C_DIM}${dbname} · kept, upgraded in place${CR}"
    if [ "$hook_ok" -eq 1 ]; then
        _kv "Webhook" "$(_dot ok) ${C_OK}set${CR}"
    else
        _kv "Webhook" "$(_dot bad) ${C_BAD}not set${CR} ${C_DIM}· run option 2 (Update) once to retry${CR}"
    fi
    [ "${#agents[@]}" -gt 0 ] && _kv "Agent bots" "${C_DIM}${#agents[@]} moved · ${agents_hooked} webhook(s) re-pointed${CR}"
    _kv "Copies" "${C_DIM}${DB_BK}${CR}"
    _kv "" "${C_DIM}${FILES_BK}${CR}"
    [ "$mig_crons" -eq 1 ] && _kv "Jobs" "$(_dot ok) ${C_OK}scheduled${CR}"
    echo ""
    if [ "$mig_crons" -ne 1 ]; then
        printf "    ${C_WARN}!${CR} ${C_WARN}Open the admin panel in the bot once: that is when it sets up its${CR}\n"
        printf "    ${C_WARN}  scheduled jobs (payment checks, notices).${CR}\n"
    fi
    printf "    ${C_DIM}Manage it with ${CR}${C_KEY}Ciro${CR}${C_DIM} from now on.${CR}\n"
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

# ── Transfer one bot to another server ────────────────────────
# Moves a bot - its database, its folder (agents' bots and vendor/ included)
# and its SSL certificate - to another server over SSH, without touching
# what that server already runs.
#
#   1. The destination is surveyed first (transfer-probe, read only): other
#      Ciro bots, Apache sites, MySQL databases, Docker containers, panels,
#      every listening port.
#   2. How the bot lands there follows from it:
#        fresh   nothing is in use there: the installer sets the server up,
#                as on any new server, and the data goes over it;
#        new     something is: the bot is placed beside it (transfer-receive)
#                - its own folder, database, DB user and vhosts, under names
#                nobody there uses; only missing packages are added; no MySQL
#                password, Apache site or PHP version there changes;
#        resume  this very bot (same token) is already there from an earlier
#                try: its data is laid over again, after a dump of what it held.
#   3. Ports: the bot keeps its port and phpMyAdmin its own when they are free
#      there - no question; only a port in use asks, on a board of free (green)
#      and taken (red) ports, and a picked port is checked on the server again.
#   4. This bot is frozen only now - its scheduled jobs here stop and its
#      webhook is taken down (Telegram keeps the messages) - so the final dump
#      misses nothing; the files go over with checksums, are checked there,
#      and only once the data is in does the webhook point at the new server.
#      Anything failing on the way puts the jobs and the webhook back here.
TR_SSH_OPTS="-o StrictHostKeyChecking=accept-new -o ConnectTimeout=15 -o ServerAliveInterval=15 -o LogLevel=ERROR"
C_FAINT_BAD=$'\033[2;31m'
# phpMyAdmin ports offered when its own is taken there (any other can be typed)
TR_PMA_SUGGEST="2083 2087 2096 8080 8888 9443"

# _tr_ssh [-t] CMD... - run on the destination (TR_HOST/TR_PORT/TR_PASS)
_tr_ssh() {
    local tty=""
    [ "$1" = "-t" ] && { tty="-t"; shift; }
    if [ -n "$TR_PASS" ]; then
        SSHPASS="$TR_PASS" sshpass -e ssh $tty $TR_SSH_OPTS -p "$TR_PORT" "root@$TR_HOST" "$@"
    else
        ssh $tty $TR_SSH_OPTS -o BatchMode=yes -p "$TR_PORT" "root@$TR_HOST" "$@"
    fi
}
# _tr_scp LOCAL_FILE REMOTE_PATH
_tr_scp() {
    if [ -n "$TR_PASS" ]; then
        SSHPASS="$TR_PASS" sshpass -e scp -q $TR_SSH_OPTS -P "$TR_PORT" "$1" "root@$TR_HOST:$2"
    else
        scp -q $TR_SSH_OPTS -o BatchMode=yes -P "$TR_PORT" "$1" "root@$TR_HOST:$2"
    fi
}

_tr_back() {
    [ -n "${1-}" ] && printf "\n    ${C_BAD}●${CR} ${C_BAD}%s${CR}\n" "$1"
    [ -n "${2-}" ] && printf "    ${C_DIM}%s${CR}\n" "$2"
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

# ── On the destination: what is there (read only) ─────────────
# One KEY=value per line; a key may repeat (BOT, SITE, DB, CONTAINER, LISTEN).
# $1 the domain being moved, $2 the sha256 of its bot token: a bot already
# there with the same token is this very bot, from an earlier try.
transfer_probe() {
    local domain="${1:-}" tokensum="${2:-}" f sn dr line
    . /etc/os-release 2>/dev/null
    echo "OS=${ID:-}"
    echo "OSVER=${VERSION_ID:-}"
    echo "FREE=$(df -Pm / 2>/dev/null | awk 'NR==2{print $4}')"
    # Ciro: its registry, its MySQL login, an install left unfinished
    if [ -f "$BOTS_REGISTRY" ] || [ -f /root/Ciro/dbroot.txt ] || [ -f "$CONFIG_FILE_DEFAULT" ]; then
        echo "CIRO=yes"
    else
        echo "CIRO=no"
    fi
    if [ -f "$STATE_FILE" ] && ! grep -qx "PHASE:COMPLETE" "$STATE_FILE" 2>/dev/null; then
        echo "CIRO_PARTIAL=$(grep '^DOMAIN=' "$STATE_FILE" 2>/dev/null | head -1 | cut -d= -f2-)"
    fi
    if [ -f /root/Ciro/dbroot.txt ]; then
        local ru rp
        ru=$(grep '$user' /root/Ciro/dbroot.txt | cut -d"'" -f2); [ -z "$ru" ] && ru=root
        rp=$(grep '$pass' /root/Ciro/dbroot.txt | cut -d"'" -f2)
        if mysql -u "$ru" -p"$rp" -N -e 'SELECT 1' >/dev/null 2>&1; then echo "CIRO_ROOT=ok"; else echo "CIRO_ROOT=bad"; fi
    fi
    # every Ciro bot: dir|domain|port|same token as the one moved (1/0)
    local d dom port tok same
    for d in $( { [ -f "$BOTS_REGISTRY" ] && grep -o '"dir"[^,}]*' "$BOTS_REGISTRY" | cut -d'"' -f4; [ -f "$CONFIG_FILE_DEFAULT" ] && echo "$BOT_DIR_DEFAULT"; } | sort -u); do
        [ -f "$d/config.php" ] || continue
        dom=$(grep '^\$domainhosts' "$d/config.php" | head -1 | cut -d"'" -f2)
        port=$(hosts_port "$dom")
        tok=$(grep '^\$APIKEY' "$d/config.php" | head -1 | cut -d"'" -f2)
        same=0; [ -n "$tokensum" ] && [ "$(printf '%s' "$tok" | sha256sum | cut -d' ' -f1)" = "$tokensum" ] && same=1
        echo "BOT=${d}|$(hosts_domain "$dom")|${port}|${same}"
    done
    # web servers and their sites
    if _pkg_installed apache2; then
        if systemctl is-active apache2 >/dev/null 2>&1; then echo "APACHE=active"; else echo "APACHE=installed"; fi
        for f in /etc/apache2/sites-enabled/*; do
            [ -f "$f" ] || continue
            sn=$(grep -iE '^[[:space:]]*Server(Name|Alias)[[:space:]]' "$f" 2>/dev/null | awk '{for(i=2;i<=NF;i++) printf "%s ", $i}' | xargs)
            dr=$(grep -iE '^[[:space:]]*DocumentRoot[[:space:]]' "$f" 2>/dev/null | head -1 | awk '{print $2}')
            echo "SITE=$(basename "$f")|${sn:--}|${dr:--}"
        done
        if php -r 'exit(0);' >/dev/null 2>&1 && apache2ctl -M 2>/dev/null | grep -qi 'php'; then echo "APACHE_PHP=mod"; fi
    else
        echo "APACHE=none"
    fi
    # a vhost already answering for this domain
    if [ -n "$domain" ] && [ -d /etc/apache2/sites-available ]; then
        for f in /etc/apache2/sites-available/*; do
            [ -f "$f" ] || continue
            if grep -qiE "^[[:space:]]*Server(Name|Alias)[[:space:]].*(^|[[:space:]])${domain//./\\.}([[:space:]]|$)" "$f" 2>/dev/null; then
                echo "DOMAIN_SITE=$(basename "$f")|$(grep -iE '^[[:space:]]*DocumentRoot[[:space:]]' "$f" | head -1 | awk '{print $2}')"
            fi
        done
        [ -d "/etc/letsencrypt/live/$domain" ] && echo "DOMAIN_CERT=1"
    fi
    if systemctl is-active nginx >/dev/null 2>&1; then echo "NGINX=active"; elif _pkg_installed nginx; then echo "NGINX=installed"; fi
    # MySQL: there or not, reachable as root without a password (socket), its databases
    if command -v mysqld >/dev/null 2>&1 || _pkg_installed mysql-server || _pkg_installed mariadb-server; then
        if systemctl is-active mysql >/dev/null 2>&1 || systemctl is-active mariadb >/dev/null 2>&1; then echo "MYSQL=active"; else echo "MYSQL=installed"; fi
        if mysql -N -e 'SELECT 1' >/dev/null 2>&1; then
            echo "MYSQL_SOCKET=ok"
            mysql -N -B -e 'SHOW DATABASES' 2>/dev/null | grep -vxE '(mysql|information_schema|performance_schema|sys|phpmyadmin)' | sed 's/^/DB=/'
        fi
    else
        echo "MYSQL=none"
    fi
    _pkg_installed phpmyadmin && echo "PMA=yes"
    if command -v php >/dev/null 2>&1; then echo "PHP=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)"; else echo "PHP=none"; fi
    # Docker and VPN panels
    if command -v docker >/dev/null 2>&1; then
        echo "DOCKER=yes"
        docker ps --format '{{.Names}}|{{.Ports}}' 2>/dev/null | head -20 | sed 's/^/CONTAINER=/'
    fi
    { [ -d /opt/marzban ] || [ -d /var/lib/marzban ]; } && echo "PANEL=Marzban"
    { [ -d /opt/rebecca ] || [ -d /var/lib/rebecca ]; } && echo "PANEL=Rebecca"
    { [ -d /opt/hiddify-manager ] || [ -d /opt/hiddify-config ]; } && echo "PANEL=Hiddify"
    { [ -d /etc/x-ui ] || [ -d /usr/local/x-ui ]; } && echo "PANEL=x-ui"
    # every TCP port listened on, and by what
    ss -ltnpH 2>/dev/null | while read -r line; do
        port=$(printf '%s' "$line" | awk '{print $4}' | sed 's/.*://')
        who=$(printf '%s' "$line" | grep -o 'users:(("[^"]*"' | head -1 | cut -d'"' -f2)
        [ -n "$port" ] && echo "${port}|${who:-?}"
    done | sort -t'|' -k1,1n -u | sed 's/^/LISTEN=/'
    # nothing in use that the full installer would take over
    if [ ! -f "$BOTS_REGISTRY" ] && [ ! -f /root/Ciro/dbroot.txt ] && precheck_fresh_server >/dev/null 2>&1; then
        echo "FRESH=yes"
    else
        echo "FRESH=no"
    fi
}

# ── On the destination: the bot, placed beside what is there ──
# $1 the transfer's folder: manifest.env (written by the source), SHA256SUMS,
# db.sql.gz, files.tar.gz, cert.tar.gz. TR_TARGET says where it goes:
#   fresh   into the bot the installer just made here (its folder, database
#           and config.php stay; the data replaces its empty tables);
#   resume  into this same bot left here by an earlier try (TR_DIR) - its
#           database is dumped first, the dump kept in /root/Ciro-backups;
#   new     beside everything here: new folder, database, user, vhosts.
# Prints KEY=value lines and RESULT=OK, or RESULT=<why> after undoing what
# this run made.
transfer_receive() {
    local rdir="${1:-}"
    [ -f "$rdir/manifest.env" ] || { echo "RESULT=NO_MANIFEST"; return 9; }
    cd "$rdir" || { echo "RESULT=NO_DIR"; return 9; }
    # shellcheck disable=SC1091
    . ./manifest.env
    if ! sha256sum -c --quiet SHA256SUMS >/dev/null 2>&1; then
        echo "RESULT=CHECKSUM"; return 3
    fi
    export DEBIAN_FRONTEND=noninteractive
    local dir="" db="" dbuser="" dbpass="" made_dir=0 made_db=0 made_user=0 made_vhost="" note="" before="" emptied=0
    local RU RP PHP_V PHP_MODE="mod" APACHE_NEW=0 NO80=0 PMA_OK=0 MYSQL_NEW=0
    _trr_fail() {
        echo "RESULT=$1"
        [ -n "${2:-}" ] && echo "WHY=$2"
        # undo what this run made - nothing that was here before
        if [ -n "$made_vhost" ]; then
            local v; for v in $made_vhost; do a2dissite "$v" >/dev/null 2>&1; rm -f "/etc/apache2/sites-available/$v"; done
            if [ -d "$rdir/apache_before" ]; then
                cp -a "$rdir/apache_before/ports.conf" /etc/apache2/ports.conf 2>/dev/null
                if [ -f "$rdir/apache_before/ciro-pma-ports.conf" ]; then
                    cp -a "$rdir/apache_before/ciro-pma-ports.conf" "$PMA_PORTS_CONF"
                else
                    a2disconf ciro-pma-ports >/dev/null 2>&1; rm -f "$PMA_PORTS_CONF"
                fi
            fi
            systemctl reload apache2 >/dev/null 2>&1
        fi
        if [ "$made_db" -eq 1 ] && [ -n "$db" ]; then _trr_mysql -e "DROP DATABASE IF EXISTS \`$db\`;" >/dev/null 2>&1; fi
        if [ "$made_user" -eq 1 ] && [ -n "$dbuser" ]; then _trr_mysql -e "DROP USER IF EXISTS '$dbuser'@'localhost'; DROP USER IF EXISTS '$dbuser'@'%';" >/dev/null 2>&1; fi
        # the bot that was here gets its own data back
        if [ "$emptied" -eq 1 ] && [ -n "$before" ] && [ -s "$before" ]; then
            _trr_empty_db
            if { echo 'SET FOREIGN_KEY_CHECKS=0;'; gunzip -c "$before"; } | _trr_mysql "$db" >/dev/null 2>&1; then
                echo "RESTORED=$before"
            fi
        fi
        [ "$made_dir" -eq 1 ] && [ -n "$dir" ] && rm -rf "$dir"
        return 1
    }
    # no password (socket login) must not stop at a password prompt
    _trr_mysql() { if [ -n "$RP" ]; then mysql -u "$RU" -p"$RP" "$@"; else mysql -u "$RU" "$@"; fi; }
    _trr_mysqldump() { if [ -n "$RP" ]; then mysqldump -u "$RU" -p"$RP" "$@"; else mysqldump -u "$RU" "$@"; fi; }
    # only this bot's own tables go - the database itself, its user and grants stay
    _trr_empty_db() {
        local drop
        drop=$(_trr_mysql -N -B "$db" -e 'SHOW FULL TABLES' 2>/dev/null | while IFS=$'\t' read -r t ty; do
            if [ "$ty" = "VIEW" ]; then echo "DROP VIEW IF EXISTS \`$t\`;"; else echo "DROP TABLE IF EXISTS \`$t\`;"; fi
        done)
        printf 'SET FOREIGN_KEY_CHECKS=0;\n%s\n' "$drop" | _trr_mysql "$db" 2>/dev/null
    }

    # ── 1. The stack: what is missing is added; nothing there is reconfigured ──
    if [ "$TR_TARGET" = "new" ]; then
        _trr_stack || return 1
    fi
    if [ -z "$RU" ]; then
        RU=$(grep '$user' /root/Ciro/dbroot.txt 2>/dev/null | cut -d"'" -f2); [ -z "$RU" ] && RU=root
        RP=$(grep '$pass' /root/Ciro/dbroot.txt 2>/dev/null | cut -d"'" -f2)
    fi
    _trr_mysql -N -e 'SELECT 1' >/dev/null 2>&1 || { _trr_fail NO_DB_ACCESS "MySQL did not accept the login in /root/Ciro/dbroot.txt"; return 1; }

    # ── 2. Where: the folder and database ──
    if [ "$TR_TARGET" = "new" ]; then
        local n="" base="Ciro"
        while [ -e "/var/www/html/${base}${n}" ] \
            || [ -n "$(_trr_mysql -N -e "SHOW DATABASES LIKE '${base}${n}'" 2>/dev/null)" ] \
            || grep -q "\"/var/www/html/${base}${n}\"" "$BOTS_REGISTRY" 2>/dev/null; do
            n=$(( ${n:-1} + 1 ))
        done
        dir="/var/www/html/${base}${n}"; db="${base}${n}"
        # its own DB user: the old name when nobody here has it, else a new one
        dbuser="$TR_SRC_DBUSER"
        if ! printf '%s' "$dbuser" | grep -qE '^[A-Za-z0-9_]{1,32}$' \
            || [ "$(_trr_mysql -N -e "SELECT COUNT(*) FROM mysql.user WHERE user='${dbuser}'" 2>/dev/null)" != "0" ]; then
            dbuser="ciro_$(openssl rand -hex 3)"
        fi
        dbpass=$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | cut -c1-16)
    else
        dir="$TR_DIR"
        [ -f "$dir/config.php" ] || { _trr_fail NO_TARGET "$dir/config.php is missing"; return 1; }
        db=$(grep '^\$dbname' "$dir/config.php" | head -1 | cut -d"'" -f2)
        [ -n "$db" ] || { _trr_fail NO_TARGET "no database in $dir/config.php"; return 1; }
    fi
    echo "DIR=$dir"
    echo "DB=$db"

    # ── 3. The files ──
    if [ "$TR_TARGET" = "new" ]; then
        mkdir -p "$dir" && made_dir=1
        tar -xzf files.tar.gz -C "$dir" || { _trr_fail FILES_FAILED; return 1; }
        if [ ! -f "$dir/vendor/autoload.php" ]; then
            install_php_deps "$dir" >/dev/null 2>&1 || { _trr_fail FILES_FAILED "composer could not install vendor/"; return 1; }
        fi
        cat > "$dir/config.php" <<EOF
<?php
// This variable added for high load panels which their response time is long and bot can't communicate with online panel!
// null for default settings
\$request_exec_timeout = null;
\$dbhost = 'localhost';
\$dbname = '$db';
\$usernamedb = '$dbuser';
\$passworddb = '$dbpass';
\$connect = mysqli_connect(\$dbhost, \$usernamedb, \$passworddb, \$dbname);
if (\$connect->connect_error) { die("error" . \$connect->connect_error); }
mysqli_set_charset(\$connect, "utf8mb4");
\$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
\$dsn = "mysql:host=\$dbhost;dbname=\$dbname;charset=utf8mb4";
try { \$pdo = new PDO(\$dsn, \$usernamedb, \$passworddb, \$options); } catch (\PDOException \$e) { error_log("Database connection failed: " . \$e->getMessage()); }
\$APIKEY = '${TR_TOKEN}';
\$adminnumber = '${TR_ADMIN}';
\$domainhosts = '$(hosts_of "$TR_DOMAIN" "$TR_BOT_PORT")';
\$usernamebot = '${TR_BOTNAME}';
?>
EOF
    else
        # what the bot here held, kept before it is replaced
        mkdir -p /root/Ciro-backups
        if [ "$TR_TARGET" = "resume" ] && [ -n "$(_trr_mysql -N -e "SHOW TABLES FROM \`$db\`" 2>/dev/null | head -1)" ]; then
            before="/root/Ciro-backups/${db}_before_transfer_$(date +%Y-%m-%d_%H-%M-%S).sql.gz"
            if ( set -o pipefail; _trr_mysqldump --single-transaction --no-tablespaces "$db" 2>/dev/null | gzip > "$before" ); then
                echo "BEFORE=$before"
            else
                rm -f "$before"
                _trr_fail BACKUP_FAILED "could not save what $db holds now - nothing was replaced"; return 1
            fi
        fi
        tar -xzf files.tar.gz -C "$dir" || { _trr_fail FILES_FAILED; return 1; }
    fi
    # agents' bots keep the source's path in their own config.php
    if [ -n "$TR_SRC_DIR" ] && [ "$TR_SRC_DIR" != "$dir" ]; then
        local c; for c in "$dir"/vpnbot/*/config.php; do
            [ -f "$c" ] && sed -i "s#${TR_SRC_DIR}/#${dir}/#g" "$c"
        done
    fi
    chown -R www-data:www-data "$dir"

    # ── 4. The database: its own, then the data, then a count ──
    if [ "$TR_TARGET" = "new" ]; then
        _trr_mysql -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || { _trr_fail DB_FAILED "could not create $db"; return 1; }
        made_db=1
        _trr_mysql -e "CREATE USER '$dbuser'@'localhost' IDENTIFIED BY '$dbpass'; GRANT ALL PRIVILEGES ON \`$db\`.* TO '$dbuser'@'localhost'; CREATE USER '$dbuser'@'%' IDENTIFIED BY '$dbpass'; GRANT ALL PRIVILEGES ON \`$db\`.* TO '$dbuser'@'%'; FLUSH PRIVILEGES;" \
            || { _trr_fail DB_FAILED "could not create the user $dbuser"; return 1; }
        made_user=1
    else
        _trr_empty_db; emptied=1
    fi
    if ! { echo 'SET FOREIGN_KEY_CHECKS=0;'; gunzip -c db.sql.gz | sed -E '/^(CREATE DATABASE |USE `)/d'; } | _trr_mysql "$db" 2>"$rdir/import.err"; then
        _trr_fail IMPORT_FAILED "$(tail -n 2 "$rdir/import.err" | tr '\n' ' ')"; return 1
    fi
    local tables
    tables=$(_trr_mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db'" 2>/dev/null)
    echo "TABLES=${tables:-0}"
    if [ -n "$TR_TABLES" ] && [ "${tables:-0}" -lt "$TR_TABLES" ]; then
        _trr_fail IMPORT_SHORT "${tables:-0} of $TR_TABLES tables arrived"; return 1
    fi

    # ── 5. Certificate, sites and ports (a new place only) ──
    if [ "$TR_TARGET" = "new" ]; then
        _trr_site || return 1
    fi

    # ── 6. Its schema, jobs, registry entry and - last - its webhook ──
    ( cd "$dir" && php table.php >/dev/null 2>&1 ) || echo "TABLE_WARN=1"
    chown -R www-data:www-data "$dir"
    cat > /tmp/ciro_cron_setup.php <<PHPEOF
<?php
chdir('$dir');
require 'config.php';
require 'botapi.php';
require 'panels.php';
require 'function.php';
require_once 'jdf.php';
require 'vendor/autoload.php';
if (!function_exists('activecron')) { exit(3); }
activecron();
PHPEOF
    chmod 644 /tmp/ciro_cron_setup.php
    if ( cd "$dir" && runuser -u www-data -- php /tmp/ciro_cron_setup.php ) >/dev/null 2>&1; then echo "CRONS=1"; fi
    rm -f /tmp/ciro_cron_setup.php
    if [ "$TR_TARGET" = "new" ]; then
        bots_registry_add "${TR_NAME:-Bot}" "$dir" "$TR_DOMAIN"
        bots_registry_set_bot_port "$dir" "$TR_BOT_PORT"
        if [ "$PMA_OK" -eq 1 ] && [ -n "$TR_PMA_PORT" ] && [ "$TR_PMA_PORT" != "$TR_BOT_PORT" ]; then
            pma_set_port "$dir" "$TR_PMA_PORT" >/dev/null 2>&1 || echo "PMA_WARN=1"
        fi
        [ "$PMA_OK" -eq 1 ] || echo "PMA_NONE=1"
        echo "DBUSER=$dbuser"
        echo "DBPASS=$dbpass"
    fi
    local hosts secret hook
    hosts=$(grep '^\$domainhosts' "$dir/config.php" | head -1 | cut -d"'" -f2)
    secret=$(_bot_webhook_secret "$dir")
    hook=$(curl -s --max-time 20 -F "url=https://${hosts}/index.php" -F "secret_token=${secret}" "https://api.telegram.org/bot${TR_TOKEN}/setWebhook")
    printf '%s' "$hook" | grep -q '"ok":true' && echo "WEBHOOK=1"
    echo "HOSTS=$hosts"
    [ "$NO80" -eq 1 ] && echo "NO80=1"
    echo "RESULT=OK"
}

# The services a new place needs that this server does not have yet - added
# without starting them on ports someone else holds; whatever is already here
# (Apache, PHP, MySQL, phpMyAdmin) is used as it is. Sets RU/RP (the MySQL
# login the bot's database is made with), PHP_V, PHP_MODE, APACHE_NEW, NO80,
# PMA_OK, MYSQL_NEW.
_trr_stack() {
    local held80 held3306 pol="/usr/sbin/policy-rc.d" pol_had=0
    _trr_quiet_start() {
        # a package installed now does not start on its own: its ports are set first
        [ -f "$pol" ] && { pol_had=1; cp -a "$pol" "$rdir/policy-rc.d.before"; }
        printf '#!/bin/sh\nexit 101\n' > "$pol"; chmod +x "$pol"
    }
    _trr_quiet_end() {
        if [ "$pol_had" -eq 1 ]; then cp -a "$rdir/policy-rc.d.before" "$pol"; else rm -f "$pol"; fi
    }
    _trr_held() { ss -ltnpH "( sport = :$1 )" 2>/dev/null | grep -o 'users:(("[^"]*"' | head -1 | cut -d'"' -f2; }
    apt_recover >/dev/null 2>&1
    apt-get update -o DPkg::Lock::Timeout=180 >/dev/null 2>&1
    command -v jq >/dev/null 2>&1 || apt-get install -y jq >/dev/null 2>&1

    # Apache
    if ! _pkg_installed apache2; then
        held80=$(_trr_held 80)
        _trr_quiet_start
        apt-get install -y -o DPkg::Lock::Timeout=180 apache2 >/dev/null 2>&1 || { _trr_quiet_end; _trr_fail STACK_FAILED "apache2 could not be installed"; return 1; }
        _trr_quiet_end
        APACHE_NEW=1
        # port 80 belongs to someone else here: this Apache does not ask for it
        if [ -n "$held80" ]; then
            sed -i -E 's/^([[:space:]]*)(Listen 80([[:space:]]|$))/\1#ciro# \2/' /etc/apache2/ports.conf
            a2dissite 000-default.conf >/dev/null 2>&1
            NO80=1
        fi
    elif [ -n "$(_trr_held 80)" ] && [ "$(_trr_held 80)" != "apache2" ]; then
        NO80=1
    fi
    mkdir -p "$rdir/apache_before" && cp -a /etc/apache2/ports.conf "$rdir/apache_before/" 2>/dev/null
    [ -f "$PMA_PORTS_CONF" ] && cp -a "$PMA_PORTS_CONF" "$rdir/apache_before/"
    a2enmod ssl rewrite >/dev/null 2>&1

    # PHP - the version here is kept; only missing extensions are added for it
    if command -v php >/dev/null 2>&1; then
        PHP_V=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    else
        setup_php_repo >/dev/null 2>&1
        PHP_V="$(resolve_php_ver)"; [ -z "$PHP_V" ] && PHP_V="8.2"
        apt-get install -y -o DPkg::Lock::Timeout=180 "php${PHP_V}" "php${PHP_V}-cli" >/dev/null 2>&1 || { _trr_fail STACK_FAILED "PHP ${PHP_V} could not be installed"; return 1; }
    fi
    local ext pkg missing=""
    for ext in mysqli:mysql pdo_mysql:mysql curl:curl mbstring:mbstring gd:gd zip:zip dom:xml intl:intl bcmath:bcmath; do
        php -m 2>/dev/null | grep -qix "${ext%%:*}" || missing="$missing php${PHP_V}-${ext##*:}"
    done
    missing=$(printf '%s\n' $missing | sort -u | tr '\n' ' ')
    if [ -n "${missing// /}" ]; then
        apt-get install -y -o DPkg::Lock::Timeout=180 $missing >/dev/null 2>&1 || { _trr_fail STACK_FAILED "PHP extensions could not be installed:$missing"; return 1; }
    fi
    # how Apache runs PHP for this bot: its own module when Apache has one;
    # a fresh Apache gets mod_php; one that runs without it serves this bot
    # through PHP-FPM from inside the bot's own vhost - its MPM never changes
    if [ "$APACHE_NEW" -eq 1 ]; then
        apt-get install -y -o DPkg::Lock::Timeout=180 "libapache2-mod-php${PHP_V}" >/dev/null 2>&1
        a2dismod mpm_event mpm_worker >/dev/null 2>&1; a2enmod mpm_prefork "php${PHP_V}" >/dev/null 2>&1
        PHP_MODE="mod"
    elif apache2ctl -M 2>/dev/null | grep -qi 'php'; then
        PHP_MODE="mod"
    else
        apt-get install -y -o DPkg::Lock::Timeout=180 "php${PHP_V}-fpm" >/dev/null 2>&1 || { _trr_fail STACK_FAILED "php${PHP_V}-fpm could not be installed"; return 1; }
        systemctl enable --now "php${PHP_V}-fpm" >/dev/null 2>&1
        a2enmod proxy_fcgi setenvif >/dev/null 2>&1
        PHP_MODE="fpm"
    fi
    echo "PHP=${PHP_V} (${PHP_MODE})"

    # MySQL: Ciro's own login; or this server's MySQL through a login of its
    # own (root's password is never changed); or a new MySQL beside a busy 3306
    if [ -f /root/Ciro/dbroot.txt ]; then
        RU=$(grep '$user' /root/Ciro/dbroot.txt | cut -d"'" -f2); [ -z "$RU" ] && RU=root
        RP=$(grep '$pass' /root/Ciro/dbroot.txt | cut -d"'" -f2)
    elif command -v mysqld >/dev/null 2>&1 || _pkg_installed mysql-server || _pkg_installed mariadb-server; then
        systemctl is-active mysql >/dev/null 2>&1 || systemctl is-active mariadb >/dev/null 2>&1 \
            || systemctl start mysql >/dev/null 2>&1 || systemctl start mariadb >/dev/null 2>&1
        local admin_cmd=""
        if mysql -N -e 'SELECT 1' >/dev/null 2>&1; then
            admin_cmd="mysql"
        elif [ -n "$TR_MYSQL_PASS" ] && mysql -u root -p"$TR_MYSQL_PASS" -N -e 'SELECT 1' >/dev/null 2>&1; then
            admin_cmd="mysql -u root -p$TR_MYSQL_PASS"
        else
            _trr_fail NO_DB_ACCESS "MySQL here needs its root password"; return 1
        fi
        RU="ciro_admin"
        RP=$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | cut -c1-16)
        $admin_cmd -e "CREATE USER IF NOT EXISTS '${RU}'@'localhost' IDENTIFIED BY '${RP}'; ALTER USER '${RU}'@'localhost' IDENTIFIED BY '${RP}'; GRANT ALL PRIVILEGES ON *.* TO '${RU}'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;" >/dev/null 2>&1 \
            || { _trr_fail NO_DB_ACCESS "could not make the ciro_admin login"; return 1; }
        mkdir -p /root/Ciro && chmod 700 /root/Ciro
        { echo "\$user = '${RU}';"; echo "\$pass = '${RP}';"; echo "\$path = '$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | cut -c1-12)';"; } > /root/Ciro/dbroot.txt
        chmod 600 /root/Ciro/dbroot.txt
    else
        held3306=$(_trr_held 3306)
        if [ -n "$held3306" ]; then
            # someone (a container's MySQL) has 3306: this one listens elsewhere
            mkdir -p /etc/mysql/conf.d
            printf '[mysqld]\nport = 3307\nbind-address = 127.0.0.1\n' > /etc/mysql/conf.d/zz-ciro-port.cnf
            echo "MYSQL_PORT=3307"
        fi
        _trr_quiet_start
        apt-get install -y -o DPkg::Lock::Timeout=180 mysql-server >/dev/null 2>&1 || { _trr_quiet_end; _trr_fail STACK_FAILED "mysql-server could not be installed"; return 1; }
        _trr_quiet_end
        systemctl enable --now mysql >/dev/null 2>&1
        setup_mysql_root >/dev/null 2>&1 || { _trr_fail STACK_FAILED "MySQL root could not be set up"; return 1; }
        RU=root
        RP=$(grep '$pass' /root/Ciro/dbroot.txt | cut -d"'" -f2)
        MYSQL_NEW=1
    fi

    # phpMyAdmin: the one here is used; a new one only next to a MySQL made now
    if _pkg_installed phpmyadmin && [ -e /etc/apache2/conf-available/phpmyadmin.conf ]; then
        PMA_OK=1
    elif [ "$MYSQL_NEW" -eq 1 ]; then
        echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | debconf-set-selections
        echo "phpmyadmin phpmyadmin/mysql/admin-pass password ${RP}" | debconf-set-selections
        echo "phpmyadmin phpmyadmin/mysql/app-pass password $(openssl rand -hex 8)" | debconf-set-selections
        # not for every site here: only the bot's own vhost includes it
        echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect' | debconf-set-selections
        if apt-get install -y -o DPkg::Lock::Timeout=180 phpmyadmin >/dev/null 2>&1; then
            [ -e /etc/apache2/conf-available/phpmyadmin.conf ] || ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf
            a2disconf phpmyadmin >/dev/null 2>&1
            PMA_OK=1
        fi
    fi
    command -v certbot >/dev/null 2>&1 || apt-get install -y -o DPkg::Lock::Timeout=180 certbot python3-certbot-apache >/dev/null 2>&1
    return 0
}

# The bot's certificate and its two vhosts, beside every other site: Apache's
# config is checked before it is reloaded, and a config Apache refuses is
# taken back out.
_trr_site() {
    local d="$TR_DOMAIN" port="$TR_BOT_PORT" sa=/etc/apache2/sites-available handler="" pmainc=""
    if [ ! -f "/etc/letsencrypt/live/$d/fullchain.pem" ] && [ -f cert.tar.gz ]; then
        # another site's files with the same names are never overwritten
        mkdir -p /etc/letsencrypt && tar -xzf cert.tar.gz --skip-old-files -C /etc/letsencrypt 2>/dev/null
    fi
    if [ ! -f "/etc/letsencrypt/live/$d/fullchain.pem" ]; then
        if [ "$NO80" -eq 1 ]; then
            _trr_fail NO_CERT "no certificate came with the bot, and port 80 here is someone else's"; return 1
        fi
        certbot certonly --apache --non-interactive --agree-tos --register-unsafely-without-email -d "$d" >/dev/null 2>&1 \
            || { _trr_fail NO_CERT "Let's Encrypt refused $d - does it point at this server?"; return 1; }
    fi
    [ "$PHP_MODE" = "fpm" ] && handler="    <FilesMatch \"\\.php\$\">
        SetHandler \"proxy:unix:/run/php/php${PHP_V}-fpm.sock|fcgi://localhost\"
    </FilesMatch>"
    [ "$PMA_OK" -eq 1 ] && pmainc="    IncludeOptional /etc/apache2/conf-available/phpmyadmin.conf"
    if [ "$NO80" -ne 1 ]; then
        cat > "$sa/${d}.conf" <<EOF
<VirtualHost *:80>
    ServerName $d
    DocumentRoot $dir
    <Directory $dir>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
${handler}
${pmainc}
    ErrorLog \${APACHE_LOG_DIR}/${d}-error.log
    CustomLog \${APACHE_LOG_DIR}/${d}-access.log combined
</VirtualHost>
EOF
        made_vhost="${d}.conf"
    fi
    cat > "$sa/${d}-ssl.conf" <<EOF
<VirtualHost *:${port}>
    ServerName $d
    DocumentRoot $dir
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/$d/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$d/privkey.pem
    <Directory $dir>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
${handler}
${pmainc}
    ErrorLog \${APACHE_LOG_DIR}/${d}-error.log
    CustomLog \${APACHE_LOG_DIR}/${d}-access.log combined
</VirtualHost>
EOF
    made_vhost="$made_vhost ${d}-ssl.conf"
    local v; for v in $made_vhost; do a2ensite "$v" >/dev/null 2>&1; done
    ciro_listen_sync "$port"
    if ! apache2ctl configtest >/dev/null 2>&1; then
        _trr_fail APACHE_CONFIG "$(apache2ctl configtest 2>&1 | grep -v '^Syntax OK' | tail -n 2 | tr '\n' ' ')"; return 1
    fi
    local was_up=0
    if systemctl is-active apache2 >/dev/null 2>&1; then
        was_up=1
        systemctl reload apache2 >/dev/null 2>&1
    else
        systemctl enable --now apache2 >/dev/null 2>&1
    fi
    # an Apache that served other sites must still be serving them
    sleep 2
    if [ "$was_up" -eq 1 ] && ! systemctl is-active apache2 >/dev/null 2>&1; then
        _trr_fail APACHE_DOWN "Apache stopped on the new config - it was put back"
        systemctl start apache2 >/dev/null 2>&1
        return 1
    fi
    # a reload does not always open a brand-new port
    [ -z "$(ss -ltn "( sport = :${port} )" 2>/dev/null | tail -n +2)" ] && systemctl restart apache2 >/dev/null 2>&1
    [ -n "$(ss -ltn "( sport = :${port} )" 2>/dev/null | tail -n +2)" ] || { _trr_fail APACHE_PORT "Apache did not open port $port"; return 1; }
    return 0
}

# ── Ports, as the destination sees them ───────────────────────
# What holds PORT there now ('' = free), asked live, not from the survey
_tr_port_holder() {
    _tr_ssh "ss -ltnpH '( sport = :$1 )' 2>/dev/null | grep -o 'users:((\"[^\"]*\"' | head -1 | cut -d'\"' -f2" 2>/dev/null | tr -d '\r'
}
# Can the bot (or its phpMyAdmin) use PORT there? Free, or Apache's - which
# serves sites side by side on one port - when the bot joins that Apache.
_tr_port_usable() {
    local who; who=$(_tr_port_holder "$1")
    [ -z "$who" ] && return 0
    [ "$who" = "apache2" ] && [ "$TR_TARGET" != "fresh" ] && return 0
    return 1
}
# One row of the board: free in green, Apache-shared in green, taken in red
_tr_port_row() {
    local p="$1" note="${2:-}" who
    who=$(printf '%s\n' "$TR_INFO" | sed -n "s/^LISTEN=${p}|//p" | head -1)
    if [ -z "$who" ]; then
        printf "      ${C_KEY}%-6s${CR} $(_dot ok) ${C_OK}free${CR}%b\n" "$p" "$note"
    elif [ "$who" = "apache2" ] && [ "$TR_TARGET" != "fresh" ]; then
        printf "      ${C_KEY}%-6s${CR} $(_dot ok) ${C_OK}shared with the Apache there${CR}%b\n" "$p" "$note"
    else
        printf "      ${C_BAD}%-6s${CR} $(_dot bad) ${C_BAD}in use · %s${CR}%b\n" "$p" "$who" "$note"
    fi
}
_tr_port_free_listed() {
    local who; who=$(printf '%s\n' "$TR_INFO" | sed -n "s/^LISTEN=${1}|//p" | head -1)
    [ -z "$who" ] || { [ "$who" = "apache2" ] && [ "$TR_TARGET" != "fresh" ]; }
}

# The bot's port there: its own when usable - no question - else a board of
# 443 / 8443 / 88 (the only ports Telegram reaches) and a choice, checked again.
_tr_pick_bot_port() {
    local want="$1" p def="" who
    if _tr_port_usable "$want"; then
        TR_BOT_PORT="$want"
        printf "    ${C_OK}●${CR} ${C_DIM}Bot port ${CR}${C_KEY}%s${CR}${C_DIM} is free on %s${CR}\n" "$want" "$TR_HOST"
        return 0
    fi
    who=$(_tr_port_holder "$want")
    printf "    ${C_BAD}●${CR} ${C_BAD}Port %s is in use on %s by %s.${CR}\n" "$want" "$TR_HOST" "${who:-another program}"
    printf "    ${C_DIM}Telegram reaches a bot on these ports only:${CR}\n"
    for p in $BOT_PORTS_ALLOWED; do
        _tr_port_row "$p"
        [ -z "$def" ] && _tr_port_free_listed "$p" && def="$p"
    done
    if [ -z "$def" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}443, 8443 and 88 are all taken on %s - free one of them first.${CR}\n" "$TR_HOST"
        return 1
    fi
    while :; do
        printf "  ${C_PROMPT}❯${CR} Bot port ${C_DIM}[%s]${CR}: " "$def"
        read -r p
        [ -z "$p" ] && p="$def"
        case " $BOT_PORTS_ALLOWED " in
            *" $p "*) ;;
            *) printf "    ${C_FAINT_BAD}Telegram only reaches a bot on 443, 8443 or 88.${CR}\n"; continue ;;
        esac
        if _tr_port_usable "$p"; then
            TR_BOT_PORT="$p"
            printf "    ${C_OK}●${CR} ${C_OK}Port %s is free on %s${CR}\n" "$p" "$TR_HOST"
            return 0
        fi
        printf "    ${C_FAINT_BAD}Port %s is in use on %s by %s - pick another.${CR}\n" "$p" "$TR_HOST" "$(_tr_port_holder "$p")"
    done
}

# phpMyAdmin's port there: its own when usable (or the bot's) - no question -
# else a board: the bot's port (always fine) and a few others, free or taken.
_tr_pick_pma_port() {
    local want="$1" p def="$TR_BOT_PORT" who
    if [ "$want" = "$TR_BOT_PORT" ] || _tr_port_usable "$want"; then
        TR_PMA_PORT="$want"
        [ "$want" != "$TR_BOT_PORT" ] && printf "    ${C_OK}●${CR} ${C_DIM}phpMyAdmin port ${CR}${C_KEY}%s${CR}${C_DIM} is free on %s${CR}\n" "$want" "$TR_HOST"
        return 0
    fi
    who=$(_tr_port_holder "$want")
    printf "    ${C_BAD}●${CR} ${C_BAD}phpMyAdmin's port %s is in use on %s by %s.${CR}\n" "$want" "$TR_HOST" "${who:-another program}"
    _tr_port_row "$TR_BOT_PORT" "  ${C_DIM}· the bot's own address${CR}"
    for p in $TR_PMA_SUGGEST; do
        [ "$p" = "$TR_BOT_PORT" ] && continue
        _tr_port_row "$p"
    done
    while :; do
        printf "  ${C_PROMPT}❯${CR} phpMyAdmin port ${C_DIM}[%s · or any 1024-65535]${CR}: " "$def"
        read -r p
        [ -z "$p" ] && p="$def"
        if ! [[ "$p" =~ ^[1-9][0-9]{0,4}$ ]] || { [ "$p" != "$TR_BOT_PORT" ] && [ "$p" != 443 ] && { [ "$p" -lt 1024 ] || [ "$p" -gt 65535 ]; }; }; then
            printf "    ${C_FAINT_BAD}Use the bot's port (%s), 443, or a port from 1024 to 65535.${CR}\n" "$TR_BOT_PORT"
            continue
        fi
        if [ "$p" = "$TR_BOT_PORT" ] || _tr_port_usable "$p"; then
            TR_PMA_PORT="$p"
            printf "    ${C_OK}●${CR} ${C_OK}phpMyAdmin will answer on port %s${CR}\n" "$p"
            return 0
        fi
        printf "    ${C_FAINT_BAD}Port %s is in use on %s by %s - pick another.${CR}\n" "$p" "$TR_HOST" "$(_tr_port_holder "$p")"
    done
}

# This bot here, frozen for the final copy: its jobs stopped (kept to put
# back) and its webhook taken down - Telegram holds the messages meanwhile
# and hands them to whichever webhook is set next.
_tr_freeze_here() {
    TR_HERE_CRONS=$(crontab -u www-data -l 2>/dev/null | grep -F "://${hosts}/cronbot/")
    _bot_crons_remove "$hosts" >/dev/null 2>&1
    curl -s --max-time 15 "https://api.telegram.org/bot${token}/deleteWebhook?drop_pending_updates=false" >/dev/null 2>&1
    TR_FROZEN=1
}
# ...and back as it was, when the move does not go through
_tr_thaw_here() {
    [ "${TR_FROZEN:-0}" -eq 1 ] || return 0
    if [ -n "$TR_HERE_CRONS" ]; then
        { crontab -u www-data -l 2>/dev/null; printf '%s\n' "$TR_HERE_CRONS"; } | awk '!seen[$0]++' | crontab -u www-data -
    fi
    curl -s --max-time 15 -F "url=https://${hosts}/index.php" -F "secret_token=$(_bot_webhook_secret "$dir")" \
        "https://api.telegram.org/bot${token}/setWebhook" >/dev/null 2>&1
    TR_FROZEN=0
    printf "    ${C_OK}●${CR} ${C_DIM}This bot's jobs and webhook are back as they were here.${CR}\n"
}

# Menu 10
function transfer_bot() {
    clear 2>/dev/null || true
    banner
    _sec "Transfer a bot to another server"
    if ! pick_bot_instance "move to another server"; then
        [ "$(bots_registry_count)" = "0" ] && sleep 2
        show_menu; return 0
    fi
    local name="$PICKED_NAME" dir="$PICKED_DIR" domain="$PICKED_DOMAIN" cfg="$PICKED_DIR/config.php"
    local dbhost dbname dbuser dbpass token admin botname hosts
    [ "$domain" = "null" ] && domain=""
    if [ ! -f "$cfg" ]; then _tr_back "config.php not found in $dir."; return 1; fi
    dbhost=$(grep '^\$dbhost' "$cfg" | head -1 | cut -d"'" -f2); [ -z "$dbhost" ] && dbhost="localhost"
    dbname=$(grep '^\$dbname' "$cfg" | head -1 | cut -d"'" -f2)
    dbuser=$(grep '^\$usernamedb' "$cfg" | head -1 | cut -d"'" -f2)
    dbpass=$(grep '^\$passworddb' "$cfg" | head -1 | cut -d"'" -f2)
    token=$(grep '^\$APIKEY' "$cfg" | head -1 | cut -d"'" -f2)
    admin=$(grep '^\$adminnumber' "$cfg" | head -1 | cut -d"'" -f2)
    botname=$(grep '^\$usernamebot' "$cfg" | head -1 | cut -d"'" -f2)
    hosts=$(grep '^\$domainhosts' "$cfg" | head -1 | cut -d"'" -f2)
    [ -z "$domain" ] && domain=$(hosts_domain "$hosts")
    if [ -z "$dbname" ] || [ -z "$token" ] || [ -z "$domain" ]; then
        _tr_back "Could not read this bot's settings from $cfg."; return 1
    fi
    _kv "Bot" "${C_KEY}${name}${CR} ${C_DIM}· ${domain}${CR}"
    echo ""

    # 1) The destination, and a login that works
    local TR_HOST TR_PORT TR_PASS=""
    while :; do
        printf "  ${C_PROMPT}❯${CR} New server's IP ${C_DIM}[0 = back]${CR}: "
        read -r TR_HOST
        [ -z "$TR_HOST" ] || [ "$TR_HOST" = "0" ] && { show_menu; return 0; }
        [[ "$TR_HOST" =~ ^[A-Za-z0-9.:-]+$ ]] && break
        printf "    ${C_FAINT_BAD}That does not look like an IP address.${CR}\n"
    done
    printf "  ${C_PROMPT}❯${CR} SSH port ${C_DIM}[22]${CR}: "
    read -r TR_PORT
    [ -z "$TR_PORT" ] && TR_PORT=22
    if ! command -v sshpass >/dev/null 2>&1; then
        run_step "Installing sshpass (for the password login)" "apt-get install -y sshpass" \
            || { show_step_error; _tr_back "sshpass could not be installed."; return 1; }
    fi
    local err rc
    while :; do
        printf "  ${C_PROMPT}❯${CR} root password of %s ${C_DIM}[hidden · Enter alone = SSH key]${CR}: " "$TR_HOST"
        read -rs TR_PASS; echo ""
        err=$(_tr_ssh true 2>&1); rc=$?
        if [ "$rc" -eq 0 ]; then
            printf "    ${C_OK}●${CR} ${C_OK}Logged in to %s${CR}\n" "$TR_HOST"
            break
        fi
        # sshpass says "wrong password" with exit code 5; plain ssh in words
        if [ "$rc" -eq 5 ] || printf '%s' "$err" | grep -qiE 'permission denied|incorrect|authentication'; then
            printf "    ${C_FAINT_BAD}Wrong password - try again.${CR}\n"
            continue
        fi
        printf "    ${C_FAINT_BAD}Could not reach %s:%s - %s${CR}\n" "$TR_HOST" "$TR_PORT" "$(printf '%s' "$err" | tail -n 1)"
        printf "  ${C_PROMPT}❯${CR} ${C_DIM}Enter = try again, 0 = back${CR}: "
        local again; read -r again
        [ "$again" = "0" ] && { show_menu; return 0; }
    done

    # 2) What the new server already runs - read only. This script goes over
    #    first (to a folder of this transfer's own) and looks for itself.
    local stamp; stamp=$(date +%Y-%m-%d_%H-%M-%S)
    local RDIR="/root/ciro_transfer_${stamp}"
    local script_src="/root/install.sh"
    [ -f "$script_src" ] || script_src=$(readlink -f "$0")
    if ! _tr_ssh "mkdir -p '$RDIR' && chmod 700 '$RDIR'" || ! _tr_scp "$script_src" "$RDIR/install.sh"; then
        _tr_back "Could not prepare $TR_HOST."; return 1
    fi
    local TR_INFO tokensum
    tokensum=$(printf '%s' "$token" | sha256sum | cut -d' ' -f1)
    TR_INFO=$(_tr_ssh "CIRO_TRANSFER=1 bash '$RDIR/install.sh' transfer-probe '$domain' '$tokensum'" 2>/dev/null | tr -d '\r')
    local os free need TR_TARGET="" TR_DIR=""
    os=$(printf '%s\n' "$TR_INFO" | sed -n 's/^OS=//p')
    free=$(printf '%s\n' "$TR_INFO" | sed -n 's/^FREE=//p')
    need=$(( $(du -sm "$dir" 2>/dev/null | cut -f1) + 3000 ))
    case "$os" in
        ubuntu|debian) ;;
        *) _tr_ssh "rm -rf '$RDIR'" >/dev/null 2>&1; _tr_back "The new server must run Ubuntu or Debian (it runs: ${os:-unknown})."; return 1 ;;
    esac
    if [ -n "$free" ] && [ "$free" -lt "$need" ]; then
        _tr_ssh "rm -rf '$RDIR'" >/dev/null 2>&1
        _tr_back "Not enough disk space on $TR_HOST (${free} MB free, about ${need} MB needed)."
        return 1
    fi
    # where it lands: this very bot from an earlier try, the clean-server
    # install, or beside whatever runs there
    local row same_dir="" other_dir=""
    while IFS= read -r row; do
        [ -z "$row" ] && continue
        if [ "$(printf '%s' "$row" | cut -d'|' -f2)" = "$domain" ]; then
            if [ "$(printf '%s' "$row" | cut -d'|' -f4)" = "1" ]; then same_dir=$(printf '%s' "$row" | cut -d'|' -f1); else other_dir=$(printf '%s' "$row" | cut -d'|' -f1); fi
        fi
    done < <(printf '%s\n' "$TR_INFO" | sed -n 's/^BOT=//p')
    if [ -n "$other_dir" ]; then
        _tr_ssh "rm -rf '$RDIR'" >/dev/null 2>&1
        _tr_back "Another bot already runs on ${domain} at ${TR_HOST} (${other_dir})." "It was left as it is. Give this bot another domain, or move that one away first."
        return 1
    fi
    local site_row
    site_row=$(printf '%s\n' "$TR_INFO" | sed -n 's/^DOMAIN_SITE=//p' | head -1)
    if [ -n "$same_dir" ]; then
        TR_TARGET="resume"; TR_DIR="$same_dir"
    elif [ -n "$site_row" ]; then
        _tr_ssh "rm -rf '$RDIR'" >/dev/null 2>&1
        _tr_back "${TR_HOST} already serves ${domain} (${site_row%%|*})." "Nothing there was changed. That site has to stop answering for this domain first."
        return 1
    elif [ "$(printf '%s\n' "$TR_INFO" | sed -n 's/^CIRO_PARTIAL=//p')" = "$domain" ] \
        || { [ "$(printf '%s\n' "$TR_INFO" | sed -n 's/^FRESH=//p')" = "yes" ] && [ -z "$(printf '%s\n' "$TR_INFO" | grep -E '^LISTEN=80\|' | grep -v '|apache2$')" ]; }; then
        TR_TARGET="fresh"; TR_DIR="$BOT_DIR_DEFAULT"
    else
        TR_TARGET="new"
    fi

    # what is there, so the admin sees nothing of it is in the way
    echo ""
    _sec "On ${TR_HOST}"
    local bots_there sites_there dbs_there ctrs_there panels_there
    bots_there=$(printf '%s\n' "$TR_INFO" | sed -n 's/^BOT=//p' | cut -d'|' -f2 | paste -sd',' - | sed 's/,/, /g')
    sites_there=$(printf '%s\n' "$TR_INFO" | sed -n 's/^SITE=//p' | grep -vE '^(000-default|default-ssl)' | wc -l)
    dbs_there=$(printf '%s\n' "$TR_INFO" | grep -c '^DB=')
    ctrs_there=$(printf '%s\n' "$TR_INFO" | sed -n 's/^CONTAINER=//p' | cut -d'|' -f1 | paste -sd',' - | sed 's/,/, /g')
    panels_there=$(printf '%s\n' "$TR_INFO" | sed -n 's/^PANEL=//p' | paste -sd',' - | sed 's/,/, /g')
    _kv "System" "${C_DIM}${os} $(printf '%s\n' "$TR_INFO" | sed -n 's/^OSVER=//p') · ${free:-?} MB free${CR}"
    [ -n "$bots_there" ] && _kv "Ciro bots" "${C_DIM}${bots_there}${CR}"
    [ "$sites_there" -gt 0 ] && _kv "Web sites" "${C_DIM}${sites_there} served by Apache${CR}"
    [ "$dbs_there" -gt 0 ] && _kv "Databases" "${C_DIM}${dbs_there}${CR}"
    [ -n "$ctrs_there" ] && _kv "Containers" "${C_DIM}${ctrs_there}${CR}"
    [ -n "$panels_there" ] && _kv "Panels" "${C_DIM}${panels_there}${CR}"
    case "$TR_TARGET" in
        fresh)  _kv "Plan" "${C_OK}clean server - set up from scratch${CR}" ;;
        resume) _kv "Plan" "${C_OK}this bot is already there (${TR_DIR}) - its data is laid over again${CR}" ;;
        new)    _kv "Plan" "${C_OK}placed beside what runs there - none of it is changed${CR}" ;;
    esac

    # MySQL there without a way in for this script: its root password is
    # asked - only to make a login of the bot's own; it is never changed
    local TR_MYSQL_PASS=""
    if [ "$TR_TARGET" = "new" ] && [ "$(printf '%s\n' "$TR_INFO" | sed -n 's/^CIRO_ROOT=//p')" != "ok" ] \
        && printf '%s\n' "$TR_INFO" | grep -qE '^MYSQL=(active|installed)' \
        && ! printf '%s\n' "$TR_INFO" | grep -qx 'MYSQL_SOCKET=ok'; then
        echo ""
        printf "    ${C_DIM}MySQL there is password-protected. Its root password is used once, to make${CR}\n"
        printf "    ${C_DIM}a login of the bot's own - it is not changed or kept.${CR}\n"
        while :; do
            printf "  ${C_PROMPT}❯${CR} MySQL root password on %s ${C_DIM}[hidden · 0 = back]${CR}: " "$TR_HOST"
            read -rs TR_MYSQL_PASS; echo ""
            [ "$TR_MYSQL_PASS" = "0" ] && { _tr_ssh "rm -rf '$RDIR'" >/dev/null 2>&1; show_menu; return 0; }
            if _tr_ssh "mysql -u root -p$(printf '%q' "$TR_MYSQL_PASS") -N -e 'SELECT 1'" >/dev/null 2>&1; then
                printf "    ${C_OK}●${CR} ${C_OK}MySQL accepted it${CR}\n"
                break
            fi
            printf "    ${C_FAINT_BAD}MySQL did not accept that password - try again.${CR}\n"
        done
    fi

    # 3) The domain has to point at the new server
    echo ""
    _sec "Point the domain at the new server"
    printf "    Change the A record of ${C_KEY}%s${CR} to ${C_KEY}%s${CR}\n" "$domain" "$TR_HOST"
    printf "    ${C_DIM}(at your domain's DNS; on Cloudflare leave it \"DNS only\"),${CR}\n"
    printf "    ${C_DIM}then press Enter.${CR}\n"
    local resolved k
    while :; do
        printf "  ${C_PROMPT}❯${CR} Press Enter when done ${C_DIM}[0 = back]${CR}: "
        read -r k
        [ "$k" = "0" ] && { _tr_ssh "rm -rf '$RDIR'" >/dev/null 2>&1; show_menu; return 0; }
        resolved=$(getent ahostsv4 "$domain" 2>/dev/null | awk '{print $1; exit}')
        if [ "$resolved" = "$TR_HOST" ]; then
            printf "    ${C_OK}●${CR} ${C_OK}%s now points at %s${CR}\n" "$domain" "$TR_HOST"
            break
        fi
        printf "    ${C_FAINT_BAD}%s still points at %s - DNS can take a few minutes.${CR}\n" "$domain" "${resolved:-nothing}"
        printf "  ${C_PROMPT}❯${CR} ${C_DIM}Enter = check again, c = continue anyway, 0 = back${CR}: "
        read -r k
        [ "$k" = "0" ] && { _tr_ssh "rm -rf '$RDIR'" >/dev/null 2>&1; show_menu; return 0; }
        [[ "$k" =~ ^[Cc]$ ]] && break
    done

    # 4) Ports there: kept when free, asked only when taken
    local TR_BOT_PORT TR_PMA_PORT here_pma
    here_pma=$(bots_registry_pma_port "$dir"); [ -z "$here_pma" ] && here_pma=$(hosts_port "$hosts")
    if [ "$TR_TARGET" = "resume" ]; then
        TR_BOT_PORT=$(printf '%s\n' "$TR_INFO" | sed -n 's/^BOT=//p' | awk -F'|' -v d="$TR_DIR" '$1==d{print $3; exit}')
        TR_PMA_PORT="$TR_BOT_PORT"
    else
        echo ""
        _tr_pick_bot_port "$(hosts_port "$hosts")" || { _tr_ssh "rm -rf '$RDIR'" >/dev/null 2>&1; _tr_back "" ""; return 1; }
        # phpMyAdmin inside the bot's own site here stays inside it there
        [ "$here_pma" = "$(hosts_port "$hosts")" ] && here_pma="$TR_BOT_PORT"
        _tr_pick_pma_port "$here_pma"
    fi

    # 5) Keep a copy here?
    echo ""
    printf "  ${C_PROMPT}❯${CR} Save a copy of the database and bot files in /root? ${C_DIM}[Y/n]${CR}: "
    local keep; read -r keep
    local keep_copy=1; [[ "$keep" =~ ^[Nn]$ ]] && keep_copy=0
    echo ""

    # the certificate goes over before anything is set up there, which then
    # uses it as it is; the web ports open in its firewall
    local work; work=$(mktemp -d /root/.ciro_transfer.XXXXXX)
    local have_cert=0
    if [ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
        tar -czf "$work/cert.tar.gz" -C /etc/letsencrypt accounts "archive/${domain}" "live/${domain}" "renewal/${domain}.conf" 2>/dev/null \
            && have_cert=1
    fi
    if [ "$have_cert" -eq 1 ] && ! printf '%s\n' "$TR_INFO" | grep -qx 'DOMAIN_CERT=1'; then
        _tr_scp "$work/cert.tar.gz" "$RDIR/cert.tar.gz" \
            && _tr_ssh "mkdir -p /etc/letsencrypt && tar -xzf '$RDIR/cert.tar.gz' --skip-old-files -C /etc/letsencrypt" >/dev/null 2>&1
    fi
    _tr_ssh "if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q 'Status: active'; then ufw allow 80/tcp >/dev/null; ufw allow ${TR_BOT_PORT}/tcp >/dev/null; [ '${TR_PMA_PORT}' != '${TR_BOT_PORT}' ] && ufw allow ${TR_PMA_PORT}/tcp >/dev/null; fi" >/dev/null 2>&1

    # 6) A clean server is set up first - the real installer, in this terminal.
    #    It sets no webhook: that waits for the data.
    if [ "$TR_TARGET" = "fresh" ]; then
        echo ""
        _sec "Installing on ${TR_HOST}"
        printf "    ${C_DIM}The installer now runs on the new server. Its answers are filled in;${CR}\n"
        printf "    ${C_DIM}if it asks anything (bot name), answer as usual.${CR}\n"
        echo ""
        local icmd
        icmd=$(printf 'CIRO_TRANSFER=1 bash %q install --name %q --token %q --admin %q --domain %q --port %q --pma-port %q --db-user %q --db-pass %q' \
            "$RDIR/install.sh" "$botname" "$token" "$admin" "$domain" "$TR_BOT_PORT" "$TR_PMA_PORT" "$dbuser" "$dbpass")
        _tr_ssh -t "$icmd"
        if ! _tr_ssh 'grep -qx "PHASE:COMPLETE" /root/Ciro/.install_state 2>/dev/null && [ -f /var/www/html/Ciro/config.php ]'; then
            rm -rf "$work"
            _tr_back "The install on $TR_HOST did not finish." \
                "Nothing here was changed. Fix what it reported, then run Transfer again (it resumes)."
            return 1
        fi
    fi

    # 7) Only now this bot stops here - jobs off, webhook down (Telegram keeps
    #    the messages) - so the copy below misses nothing
    _tr_freeze_here
    printf "    ${C_OK}●${CR} ${C_DIM}This bot is paused here: scheduled jobs off, webhook down (Telegram keeps the messages)${CR}\n"
    local src_tables
    src_tables=$(mysql -h "$dbhost" -u "$dbuser" -p"$dbpass" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$dbname'" 2>/dev/null)
    run_step "Saving the database" \
        "set -o pipefail; mysqldump -h '$dbhost' -u '$dbuser' -p'$dbpass' --single-transaction --no-tablespaces '$dbname' | gzip > '$work/db.sql.gz' && [ \$(gunzip -c '$work/db.sql.gz' | head -c 100 | wc -c) -gt 0 ]" \
        || { show_step_error; rm -rf "$work"; _tr_thaw_here; _tr_back "Could not save the database." "Nothing was changed."; return 1; }
    run_step "Packing the bot folder" \
        "tar --anchored --exclude=./config.php --exclude=./update_request --exclude=./update_request.running --exclude=./rollback_request --exclude=./update_progress.json --exclude=./update_status.json --exclude=./update_backups.json --exclude=./restore_request --exclude=./restore_request.running --exclude=./restore_progress.json --exclude=./restore_status.json --exclude=./watch_caps.json --exclude=./storage/restore -czf '$work/files.tar.gz' -C '$dir' ." \
        || { show_step_error; rm -rf "$work"; _tr_thaw_here; _tr_back "Could not pack the bot folder." "Nothing was changed."; return 1; }
    if [ "$keep_copy" -eq 1 ]; then
        cp "$work/db.sql.gz" "/root/ciro_transfer_${dbname}_${stamp}.sql.gz"
        cp "$work/files.tar.gz" "/root/ciro_transfer_files_${dbname}_${stamp}.tar.gz"
        printf "    ${C_OK}✔${CR} ${C_DIM}Copies kept in /root (ciro_transfer_*_%s)${CR}\n" "$stamp"
    fi

    # 8) Send it, with what the other side needs to know and to check
    {
        printf 'TR_TARGET=%q\n' "$TR_TARGET"
        printf 'TR_DIR=%q\n' "$TR_DIR"
        printf 'TR_NAME=%q\n' "$name"
        printf 'TR_BOTNAME=%q\n' "$botname"
        printf 'TR_TOKEN=%q\n' "$token"
        printf 'TR_ADMIN=%q\n' "$admin"
        printf 'TR_DOMAIN=%q\n' "$domain"
        printf 'TR_BOT_PORT=%q\n' "$TR_BOT_PORT"
        printf 'TR_PMA_PORT=%q\n' "$TR_PMA_PORT"
        printf 'TR_SRC_DIR=%q\n' "$dir"
        printf 'TR_SRC_DBUSER=%q\n' "$dbuser"
        printf 'TR_TABLES=%q\n' "$src_tables"
        printf 'TR_MYSQL_PASS=%q\n' "$TR_MYSQL_PASS"
    } > "$work/manifest.env"
    chmod 600 "$work/manifest.env"
    ( cd "$work" && sha256sum db.sql.gz files.tar.gz > SHA256SUMS )
    local f sent=1
    printf "    ${C_DIM}Sending to %s...${CR}\n" "$TR_HOST"
    for f in db.sql.gz files.tar.gz manifest.env SHA256SUMS; do
        _tr_scp "$work/$f" "$RDIR/" || sent=0
    done
    [ "$have_cert" -eq 1 ] && { _tr_scp "$work/cert.tar.gz" "$RDIR/" || sent=0; }
    if [ "$sent" -ne 1 ]; then
        rm -rf "$work"; _tr_thaw_here
        _tr_back "Sending the files to $TR_HOST failed." "Nothing was changed there; this bot runs here again."; return 1
    fi
    if ! _tr_ssh "cd '$RDIR' && sha256sum -c --quiet SHA256SUMS" >/dev/null 2>&1; then
        rm -rf "$work"; _tr_thaw_here
        _tr_back "The files arrived damaged on $TR_HOST (checksum)." "Nothing was changed there; this bot runs here again. Run Transfer again."; return 1
    fi
    printf "    ${C_OK}✔${CR} ${C_DIM}Database, bot folder%s sent and checked${CR}\n" "$([ "$have_cert" -eq 1 ] && echo " and SSL certificate")"

    # 9) The bot's own data in its place there - then, last, its webhook
    echo ""
    local res
    printf "    ${C_DIM}Placing the bot on %s...${CR}\n" "$TR_HOST"
    res=$(_tr_ssh "CIRO_TRANSFER=1 bash '$RDIR/install.sh' transfer-receive '$RDIR'" 2>&1 | tr -d '\r')
    if ! printf '%s\n' "$res" | grep -qx "RESULT=OK"; then
        rm -rf "$work"
        printf '%s\n' "$res" | grep -E '^(RESULT|WHY)=' | sed 's/^/      /'
        _tr_thaw_here
        _tr_back "Moving the data onto $TR_HOST failed." \
            "What this run made there was taken back out; nothing else there was touched. The files are left in $RDIR on $TR_HOST."
        return 1
    fi
    TR_FROZEN=0
    printf "    ${C_OK}✔${CR} ${C_DIM}Database and bot folder in place on %s (%s, %s tables)${CR}\n" "$TR_HOST" \
        "$(printf '%s\n' "$res" | sed -n 's/^DIR=//p')" "$(printf '%s\n' "$res" | sed -n 's/^TABLES=//p')"
    printf '%s\n' "$res" | grep -q '^TABLE_WARN=1' \
        && printf "    ${C_WARN}!${CR} ${C_WARN}table.php reported a problem there - run option 2 (Update) on it once.${CR}\n"
    # what the bot there held before, when it was there already
    printf '%s\n' "$res" | grep -q '^BEFORE=' \
        && printf "    ${C_DIM}Its earlier data there is kept in %s${CR}\n" "$(printf '%s\n' "$res" | sed -n 's/^BEFORE=//p')"
    _tr_ssh "rm -f '$RDIR/db.sql.gz' '$RDIR/files.tar.gz' '$RDIR/cert.tar.gz' '$RDIR/manifest.env' && rm -rf '$RDIR'" >/dev/null 2>&1
    rm -rf "$work"

    # 10) Is it the live one now? (on the port the new server gave it)
    local code hook new_hosts
    new_hosts=$(printf '%s\n' "$res" | sed -n 's/^HOSTS=//p')
    [ -z "$new_hosts" ] && new_hosts="$domain"
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 --resolve "${domain}:$(hosts_port "$new_hosts"):${TR_HOST}" "https://${new_hosts}/" 2>/dev/null)
    hook=$(curl -s --max-time 10 "https://api.telegram.org/bot${token}/getWebhookInfo" 2>/dev/null)

    _sec "Transferred"
    _kv "Bot" "${C_KEY}${name}${CR}"
    _kv "Now on" "${C_OK}${TR_HOST}${CR} ${C_DIM}· https://${new_hosts}${CR}"
    if [ "$code" = "200" ] || [ "$code" = "301" ] || [ "$code" = "302" ]; then
        _kv "Site" "$(_dot ok) ${C_OK}answers (HTTP ${code})${CR}"
    else
        _kv "Site" "$(_dot warn) ${C_WARN}HTTP ${code:-timeout}${CR} ${C_DIM}· check it once DNS has settled${CR}"
    fi
    if printf '%s' "$hook" | grep -q "\"url\":\"https://${new_hosts}/index.php\""; then
        _kv "Webhook" "$(_dot ok) ${C_OK}https://${new_hosts}/index.php${CR}"
    else
        _kv "Webhook" "$(_dot warn) ${C_WARN}not confirmed${CR} ${C_DIM}· run option 2 (Update) on the new server${CR}"
    fi
    if printf '%s\n' "$res" | grep -qx 'CRONS=1'; then
        _kv "Jobs" "$(_dot ok) ${C_OK}registered on the new server${CR}"
    else
        _kv "Jobs" "$(_dot warn) ${C_WARN}not registered${CR} ${C_DIM}· open the bot's admin panel once${CR}"
    fi
    if [ "$TR_TARGET" = "new" ]; then
        _kv "Folder" "${C_DIM}$(printf '%s\n' "$res" | sed -n 's/^DIR=//p')${CR}"
        _kv "Database" "${C_KEY}$(printf '%s\n' "$res" | sed -n 's/^DB=//p')${CR} ${C_DIM}· user${CR} ${C_KEY}$(printf '%s\n' "$res" | sed -n 's/^DBUSER=//p')${CR} ${C_DIM}· pass${CR} ${C_KEY}$(printf '%s\n' "$res" | sed -n 's/^DBPASS=//p')${CR}"
        if printf '%s\n' "$res" | grep -qx 'PMA_NONE=1'; then
            _kv "phpMyAdmin" "${C_DIM}not installed there - left so${CR}"
        else
            _kv "phpMyAdmin" "${C_DIM}$(pma_url "$domain" "$TR_PMA_PORT")${CR}"
        fi
        printf '%s\n' "$res" | grep -qx 'NO80=1' \
            && printf "    ${C_WARN}!${CR} ${C_WARN}Port 80 there belongs to another program: the certificate cannot renew itself${CR}\n    ${C_WARN}  over HTTP - renew it by DNS, or free port 80, before it runs out.${CR}\n"
    fi
    _kv "Here" "${C_DIM}scheduled jobs stopped; files and database left as they were${CR}"
    echo ""

    # 11) This server's copy: stopped by default - if the new server does not
    #     work out, point the domain back and start it with option 11 - or
    #     deleted for good
    printf "  ${C_TXT}What should happen to %s on THIS server?${CR}\n" "$name"
    _mi "1" "Stop it"   "keep its files and database - start it again with option 11"
    _mi "2" "Delete it" "remove its files, database and certificate from this server"
    printf "  ${C_PROMPT}❯${CR} Choose ${C_DIM}[1-2, Enter = 1]${CR}: "
    local here_choice; read -r here_choice
    echo ""
    if [ "$here_choice" = "2" ]; then
        remove_bot_scoped "$name" "$dir" "$domain" yes
    else
        bot_stop_scoped "$name" "$dir" "$domain"
    fi
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

# ── Stop / start a bot on this server ─────────────────────────
# Stopping switches the bot's sites off and removes its scheduled jobs, and
# nothing else: files, database and certificate stay. So a transfer can be
# undone - point the domain back here, start it, and it carries on.

# Registers a bot's scheduled jobs the way its own admin panel does -
# activecron(), run as www-data, whose crontab they live in. Returns
# non-zero when the bot's code could not do it.
_bot_register_crons() {
    local dir="$1" php_file rc
    php_file=$(mktemp /tmp/ciro_cron_XXXXXX.php)
    cat > "$php_file" <<EOF
<?php
chdir('$dir');
require 'config.php';
require 'botapi.php';
require 'panels.php';
require 'function.php';
require_once 'jdf.php';
require 'vendor/autoload.php';
if (!function_exists('activecron')) { exit(3); }
activecron();
EOF
    chmod 644 "$php_file"
    ( cd "$dir" && runuser -u www-data -- php "$php_file" ) >/dev/null 2>&1
    rc=$?
    rm -f "$php_file"
    return $rc
}

# bot_stop_scoped NAME DIR DOMAIN
bot_stop_scoped() {
    local name="$1" dir="$2" domain="$3" hosts
    [ "$domain" = "null" ] && domain=""
    hosts=$(grep '^\$domainhosts' "$dir/config.php" 2>/dev/null | head -1 | cut -d"'" -f2)
    [ -z "$hosts" ] && hosts="$domain"
    # flagged first, so the phpMyAdmin port sync below leaves its sites off
    bots_registry_set_stopped "$dir" true
    _bot_crons_remove "$hosts" >/dev/null 2>&1
    printf "    ${C_OK}✔${CR} ${C_DIM}Scheduled jobs removed${CR}\n"
    if [ -n "$domain" ]; then
        a2dissite "${domain}.conf" >/dev/null 2>&1
        a2dissite "${domain}-ssl.conf" >/dev/null 2>&1
        a2dissite "${domain}-pma.conf" >/dev/null 2>&1
        # its phpMyAdmin port may now be unused
        pma_sync_all >/dev/null 2>&1
        apache2ctl configtest >/dev/null 2>&1 && systemctl reload apache2 >/dev/null 2>&1
        printf "    ${C_OK}✔${CR} ${C_DIM}%s switched off on this server${CR}\n" "$domain"
    fi
    printf "    ${C_OK}✔${CR} ${C_OK}%s is stopped here - files, database and certificate kept.${CR}\n" "$name"
    printf "    ${C_DIM}Start it again any time with option 11.${CR}\n"
}

# bot_start_scoped NAME DIR DOMAIN - returns 2 when the admin backs out
bot_start_scoped() {
    local name="$1" dir="$2" domain="$3" token myip resolved k secret hook
    [ "$domain" = "null" ] && domain=""
    if [ ! -f "$dir/config.php" ] || [ -z "$domain" ]; then
        printf "    ${C_BAD}●${CR} ${C_BAD}%s has no config.php or domain here - it cannot be started.${CR}\n" "$name"
        return 1
    fi
    token=$(grep '^\$APIKEY' "$dir/config.php" | head -1 | cut -d"'" -f2)
    # 1) Only once the domain points here again: until then the other server
    #    gets the traffic, and with jobs on both, every job would run twice.
    myip=$(get_server_ip)
    while :; do
        resolved=$(getent ahostsv4 "$domain" 2>/dev/null | awk '{print $1; exit}')
        if [ "$resolved" = "$myip" ]; then
            printf "    ${C_OK}●${CR} ${C_OK}%s points at this server (%s)${CR}\n" "$domain" "$myip"
            break
        fi
        printf "    ${C_WARN}!${CR} ${C_WARN}%s points at %s, not at this server (%s).${CR}\n" "$domain" "${resolved:-nothing}" "$myip"
        printf "    ${C_DIM}Change its A record back to %s first (on Cloudflare: DNS only).${CR}\n" "$myip"
        printf "  ${C_PROMPT}❯${CR} ${C_DIM}Enter = check again, c = start anyway, 0 = back${CR}: "
        read -r k
        [ "$k" = "0" ] && return 2
        [[ "$k" =~ ^[Cc]$ ]] && break
    done
    # 2) Its sites back on (and its phpMyAdmin port, if it has its own)
    bots_registry_set_stopped "$dir" false
    [ -f "/etc/apache2/sites-available/${domain}.conf" ] && a2ensite "${domain}.conf" >/dev/null 2>&1
    [ -f "/etc/apache2/sites-available/${domain}-ssl.conf" ] && a2ensite "${domain}-ssl.conf" >/dev/null 2>&1
    pma_sync_all >/dev/null 2>&1
    if ! apache2ctl configtest >/dev/null 2>&1; then
        a2dissite "${domain}.conf" >/dev/null 2>&1
        a2dissite "${domain}-ssl.conf" >/dev/null 2>&1
        bots_registry_set_stopped "$dir" true
        printf "    ${C_BAD}●${CR} ${C_BAD}Apache rejected the bot's sites - it stays stopped.${CR}\n"
        return 1
    fi
    systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1
    printf "    ${C_OK}✔${CR} ${C_DIM}%s answers on this server again${CR}\n" "$domain"
    # 3) Telegram delivers here again
    secret=$(_bot_webhook_secret "$dir")
    hook=$(curl -s --max-time 20 -F "url=https://${domain}/index.php" -F "secret_token=${secret}" \
        "https://api.telegram.org/bot${token}/setWebhook" 2>/dev/null)
    if printf '%s' "$hook" | grep -q '"ok":true'; then
        printf "    ${C_OK}✔${CR} ${C_DIM}Webhook set to https://%s/index.php${CR}\n" "$domain"
    else
        printf "    ${C_WARN}!${CR} ${C_WARN}Telegram did not confirm the webhook - run option 2 (Update) once.${CR}\n"
    fi
    # 4) and its scheduled jobs run here again
    if _bot_register_crons "$dir"; then
        printf "    ${C_OK}✔${CR} ${C_DIM}Scheduled jobs registered${CR}\n"
    else
        printf "    ${C_WARN}!${CR} ${C_WARN}Could not register the scheduled jobs - open the bot's admin panel once.${CR}\n"
    fi
    printf "    ${C_OK}✔${CR} ${C_OK}%s is running on this server again.${CR}\n" "$name"
}

# Menu 11
function startstop_menu() {
    clear 2>/dev/null || true
    banner
    _sec "Start / stop a bot"
    if ! pick_bot_instance "start or stop"; then
        [ "$(bots_registry_count)" = "0" ] && sleep 2
        show_menu; return 0
    fi
    local name="$PICKED_NAME" dir="$PICKED_DIR" domain="$PICKED_DOMAIN" k
    [ "$domain" = "null" ] && domain=""
    _kv "Bot" "${C_KEY}${name}${CR} ${C_DIM}· ${domain:--}${CR}"
    if bots_registry_is_stopped "$dir"; then
        _kv "State" "$(_dot none) ${C_DIM}stopped on this server${CR}"
        echo ""
        printf "  ${C_PROMPT}❯${CR} Start it again here? ${C_DIM}[Y/n]${CR}: "
        read -r k
        if [[ ! "$k" =~ ^[Nn]$ ]]; then
            echo ""
            bot_start_scoped "$name" "$dir" "$domain"
            [ $? -eq 2 ] && { show_menu; return 0; }
        fi
    else
        _kv "State" "$(_dot ok) ${C_OK}running${CR}"
        echo ""
        printf "    ${C_DIM}Stopping switches its site off here and removes its scheduled jobs.${CR}\n"
        printf "    ${C_DIM}Files, database and certificate stay - start it again any time.${CR}\n"
        printf "  ${C_PROMPT}❯${CR} Stop it on this server? ${C_DIM}[y/N]${CR}: "
        read -r k
        if [[ "$k" =~ ^[Yy]$ ]]; then
            echo ""
            bot_stop_scoped "$name" "$dir" "$domain"
        fi
    fi
    echo ""
    printf "  ${C_PROMPT}❯${CR} Press Enter to return to the menu... "
    read -r _
    show_menu
}

# ── Command-line argument parsing ────────────────────────────
# Globals filled from flags (consumed by install/update where relevant)
ARG_NAME=""     ARG_TOKEN=""   ARG_ADMIN=""    ARG_DOMAIN=""
ARG_DBUSER=""   ARG_DBPASS=""  ARG_PORT=""     ARG_PMA_PORT=""

print_usage() {
    cat <<USAGE

  Ciro - install and management script

  Usage:
    Ciro                       open the interactive menu
    Ciro <command> [options]

  Commands:
    install            Set up the bot on a clean server (Ubuntu/Debian)
    update             Fetch the newest code and migrate the database.
                       Your config.php, your database rows and any file the
                       project does not ship are kept. A rollback archive is
                       written to /root/Ciro-backups/ before anything moves.
                       Works on a bot of the original project too - it upgrades
                       in place, it does not reinstall.
    remove             Delete the bot directory and the packages it installed
    migrate            Migrate a bot of the original project to Ciro (Install and
                       Update do this by themselves when they find one)
    addbot             Install a second, independent bot on this server
    renew              Reissue the domain's SSL certificate
    backup             Dump the database and send it to Telegram
    import             Restore the database from a .sql or .zip backup, or the
                       bot's files from its botfolder zip (beta). What it
                       replaces is kept in /root/Ciro-backups/ first.
    version            Show the bot's version and the newest one on GitHub
    menu               Open the interactive menu (default)

  Options:
    --name    <user>   Bot username, without the @
    --token   <token>  Telegram bot token from @BotFather
    --admin   <id>     Your numeric Telegram id (from @userinfobot)
    --domain  <fqdn>   Domain already pointed at this server, e.g. bot.example.com
    --port    <port>   The bot's HTTPS port: 443, 8443 or 88 (Telegram allows
                       only these). Use 8443 or 88 when Xray already has 443.
    --pma-port <port>  phpMyAdmin's port (the bot's port, 443, or 1024-65535)
    --db-user <user>   Database user to create
    --db-pass <pass>   Database password (letters, digits, underscore; 6-64)
    -h, --help         Show this help

  Examples:
    Ciro install
    Ciro install --name myvpnbot --token 123456:ABC --admin 111222333 \\
                 --domain bot.example.com --db-user botuser --db-pass s3cret_1
    Ciro update

  Notes:
    - Run as root.
    - The domain must already resolve to this server before installing;
      the certificate step needs it.
    - Update never drops a database column or table: it only adds what is
      missing, so upgrading keeps every existing user, order and setting.

USAGE
}

# ── Servers set up before the rename ─────────────────────────
# This project used to go by another name, and a server installed under it
# still carries that name on everything this script left there: the state
# folder, the update snapshots, the command, root's cron line, the phpMyAdmin
# port file and the bots' folders. LEGACY_NAME is that name - spelled in
# character codes, so the word itself is nowhere in the code - and it is only
# here because those things have to be found to be moved; _legacy_migrate
# gives each of them its Ciro name once, and on a server with nothing old on
# it, does nothing at all.
#
# A second old layout is moved the same way: for a short while the folders and
# the command were written with a small c (/root/ciro, /var/www/html/ciro, the
# ciro command). They are written Ciro now, and Linux tells the two apart, so a
# server still on the small-c names would otherwise lose track of its bot.
SMALLC_STATE_DIR="/root/ciro"
SMALLC_BACKUP_DIR="/root/ciro-backups"
SMALLC_BIN="/usr/local/bin/ciro"
LEGACY_NAME=$(printf '\155\151\162\172\141')
LEGACY_STATE_DIR="/root/conf${LEGACY_NAME}"
LEGACY_BACKUP_DIR="/root/${LEGACY_NAME}-backups"
LEGACY_BIN="/usr/local/bin/${LEGACY_NAME}"
LEGACY_LOCK="/tmp/${LEGACY_NAME}_selfupdate.lock"
LEGACY_PMA_CONF="/etc/apache2/conf-available/${LEGACY_NAME}-pma-ports.conf"
LEGACY_BOT_DIR="/var/www/html/${LEGACY_NAME}probotconfig"
# where the original project installs its own bot - migrate looks there
LEGACY_ORIGINAL_DIR="/var/www/html/${LEGACY_NAME}botconfig"

_legacy_present() {
    [ -d "$LEGACY_STATE_DIR" ] || [ -d "$LEGACY_BACKUP_DIR" ] || [ -L "$LEGACY_BIN" ] \
        || [ -d "$SMALLC_STATE_DIR" ] || [ -d "$SMALLC_BACKUP_DIR" ] || [ -L "$SMALLC_BIN" ] \
        || [ -f "$LEGACY_PMA_CONF" ] \
        || grep -qs "#${LEGACY_NAME}# " /etc/apache2/ports.conf \
        || grep -qs "/${LEGACY_NAME}[a-z]*[0-9]*\"" "$BOTS_REGISTRY" \
        || grep -qs "/var/www/html/ciro[0-9]*\"" "$BOTS_REGISTRY" \
        || { command -v crontab >/dev/null 2>&1 \
             && crontab -l 2>/dev/null | grep -Fq -e "${LEGACY_NAME} selfupdate-watch" -e "ciro selfupdate-watch"; }
}

# an old state folder is still on the server
_legacy_state_left() {
    [ -d "$LEGACY_STATE_DIR" ] || [ -d "$SMALLC_STATE_DIR" ]
}

# Move what is in folder $1 into folder $2, never over something that is
# already there (a bots list this script just created empty is not one to
# keep). The first folder is left for the caller to remove.
_legacy_merge_into() {
    local src="$1" dst="$2" f b
    mkdir -p "$dst"
    for f in "$src"/* "$src"/.[!.]*; do
        [ -e "$f" ] || continue
        b=$(basename "$f")
        case "$b" in
            "dbroot${LEGACY_NAME}.txt") b="dbroot.txt" ;;
            ".${LEGACY_NAME}_install_state") b=".install_state" ;;
        esac
        if [ ! -e "$dst/$b" ] \
            || { [ "$b" = "bots.json" ] && [ "$(jq 'length' "$dst/bots.json" 2>/dev/null)" = "0" ]; }; then
            rm -rf "${dst:?}/$b"
            mv "$f" "$dst/$b"
        fi
    done
}

# A bot's folder is renamed only when it is served the way this script serves
# it - a site of its own whose DocumentRoot is the folder. Any other layout
# has the folder's name inside its addresses (webhook, scheduled jobs), and
# renaming it would cut them off, so such a bot keeps its folder.
_legacy_dir_movable() {
    local d="$1" hosts
    [ -f "$d/config.php" ] || return 1
    hosts=$(grep '^\$domainhosts' "$d/config.php" 2>/dev/null | head -1 | cut -d"'" -f2)
    case "$hosts" in ""|*/*) return 1 ;; esac
    grep -qsE "^[[:space:]]*DocumentRoot[[:space:]]+\"?${d}/?\"?[[:space:]]*$" /etc/apache2/sites-available/*.conf
}

# Point everything that names folder $1 at $2: Apache's sites, the bots list
# and the agents' bots inside it. The snapshots follow later, once Apache has
# taken the change.
_legacy_repoint() {
    local old="$1" new="$2" f tmp
    for f in /etc/apache2/sites-available/*.conf /etc/apache2/sites-enabled/*.conf; do
        [ -f "$f" ] && [ ! -L "$f" ] || continue
        grep -qF "$old" "$f" || continue
        sed -i -E "s#${old}([/[:space:]\">]|\$)#${new}\1#g" "$f"
    done
    tmp=$(mktemp)
    jq --arg o "$old" --arg n "$new" 'map(if .dir == $o then .dir = $n else . end)' "$BOTS_REGISTRY" > "$tmp" \
        && mv "$tmp" "$BOTS_REGISTRY"
    for f in "$new"/vpnbot/*/config.php; do
        [ -f "$f" ] && grep -qF "${old}/" "$f" && sed -i "s#${old}/#${new}/#g" "$f"
    done
}

# A snapshot belongs to the bot whose folder it unpacks to, so the ones taken
# while a bot had its old folder name are repacked under the new one - with
# their dates kept - or that bot's «بازگشت به نسخه قبلی» would lose them.
_legacy_repack_snapshots() {
    local oldbase="$1" newbase="$2" f top tmp
    for f in "$CIRO_BACKUP_DIR"/pre-*.tar.gz; do
        [ -f "$f" ] || continue
        top=$(tar -tzf "$f" 2>/dev/null | head -1 | cut -d/ -f1)
        [ "$top" = "$oldbase" ] || continue
        tmp=$(mktemp -d "$CIRO_BACKUP_DIR/.repack.XXXXXX") || continue
        if tar -xzf "$f" -C "$tmp" 2>/dev/null && mv "$tmp/$oldbase" "$tmp/$newbase" \
            && tar -czf "$tmp/new.tar.gz" -C "$tmp" "$newbase" 2>/dev/null; then
            touch -r "$f" "$tmp/new.tar.gz"
            mv -f "$tmp/new.tar.gz" "${f//_${oldbase}_/_${newbase}_}"
            [ "${f//_${oldbase}_/_${newbase}_}" != "$f" ] && rm -f "$f"
        fi
        rm -rf "$tmp"
    done
}

# $1 = "locked" from the watcher, whose cron line already holds its lock.
# Anyone else takes both the watcher's lock and the one its old cron line
# used, so nothing is moved while an update runs; returns 2 when it cannot.
_legacy_migrate() {
    _legacy_present || return 0
    if [ "${1:-}" != "locked" ]; then
        exec 8>"/tmp/ciro_selfupdate.lock" 9>"$LEGACY_LOCK"
        if ! flock -n 8 || ! flock -n 9; then
            exec 8>&- 9>&-
            return 2
        fi
    fi
    _ensure_jq
    local f b did="" moved="" abak="" d base suffix target n

    # the state folder: MySQL's root login, the bots list, the install progress
    for f in "$LEGACY_STATE_DIR" "$SMALLC_STATE_DIR"; do
        [ -d "$f" ] || continue
        _legacy_merge_into "$f" "$STATE_DIR"
        rm -rf "$f"
        did=1
    done
    # the update snapshots
    for f in "$LEGACY_BACKUP_DIR" "$SMALLC_BACKUP_DIR"; do
        [ -d "$f" ] || continue
        _legacy_merge_into "$f" "$CIRO_BACKUP_DIR"
        rmdir "$f" 2>/dev/null
        did=1
    done

    # the bots' folders, and Apache's files that carry the old name
    bots_registry_ensure
    local todo="" apache_changed=""
    if command -v apache2ctl >/dev/null 2>&1; then
        while IFS= read -r d; do
            [ -n "$d" ] && [ -d "$d" ] || continue
            base=$(basename "$d")
            case "$base" in
                "${LEGACY_NAME}probotconfig"*) suffix="${base#"${LEGACY_NAME}probotconfig"}" ;;
                "${LEGACY_NAME}botconfig") suffix="" ;;
                ciro|ciro[0-9]*) suffix="${base#ciro}" ;;
                *) continue ;;
            esac
            [[ "$suffix" =~ ^[0-9]*$ ]] || continue
            _legacy_dir_movable "$d" && todo="${todo}${d}"$'\t'"${suffix}"$'\n'
        done < <(jq -r '.[].dir // empty' "$BOTS_REGISTRY" 2>/dev/null)
    fi
    # Apache has to be fine before anything moves, or a failure afterwards
    # could not be told apart from one that was already there; and a rename
    # Apache once refused is not tried again every minute
    if { [ -n "$todo" ] || [ -f "$LEGACY_PMA_CONF" ]; } \
        && [ ! -f /root/Ciro/.legacy_folders_kept ] && apache2ctl configtest >/dev/null 2>&1; then
        abak=$(mktemp -d)
        cp -a /etc/apache2/sites-available /etc/apache2/sites-enabled \
              /etc/apache2/conf-available /etc/apache2/conf-enabled "$abak"/ 2>/dev/null
        cp -a /etc/apache2/ports.conf "$abak"/ 2>/dev/null
        cp -a "$BOTS_REGISTRY" "$abak/bots.json" 2>/dev/null
        while IFS=$'\t' read -r d suffix; do
            [ -n "$d" ] || continue
            target="/var/www/html/Ciro${suffix}"
            n=2
            while [ -e "$target" ]; do target="/var/www/html/Ciro${n}"; n=$((n + 1)); done
            mv "$d" "$target" || continue
            _legacy_repoint "$d" "$target"
            moved="${moved}${d}"$'\t'"${target}"$'\n'
            apache_changed=1
        done <<< "$todo"
        if [ -f "$LEGACY_PMA_CONF" ]; then
            local pma_on=""
            [ -e "/etc/apache2/conf-enabled/$(basename "$LEGACY_PMA_CONF")" ] && pma_on=1
            a2disconf "$(basename "$LEGACY_PMA_CONF" .conf)" >/dev/null 2>&1
            [ -f "$PMA_PORTS_CONF" ] || sed "1s/.*/# Written by the Ciro script: the HTTPS ports a bot or its phpMyAdmin uses besides 443./" \
                "$LEGACY_PMA_CONF" > "$PMA_PORTS_CONF"
            rm -f "$LEGACY_PMA_CONF"
            [ -n "$pma_on" ] && a2enconf ciro-pma-ports >/dev/null 2>&1
            apache_changed=1
        fi
        if [ -n "$apache_changed" ]; then
            local ok=1
            apache2ctl configtest >/dev/null 2>&1 || ok=0
            if [ "$ok" -eq 1 ]; then
                systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1
                systemctl is-active --quiet apache2 || ok=0
            fi
            if [ "$ok" -eq 0 ]; then
                # every folder back where it was, Apache and the list as they were
                while IFS=$'\t' read -r d target; do
                    [ -n "$d" ] && mv "$target" "$d"
                    for f in "$d"/vpnbot/*/config.php; do
                        [ -f "$f" ] && sed -i "s#${target}/#${d}/#g" "$f"
                    done
                done <<< "$moved"
                _pma_restore "$abak"
                cp -a "$abak/bots.json" "$BOTS_REGISTRY" 2>/dev/null
                systemctl restart apache2 >/dev/null 2>&1
                touch /root/Ciro/.legacy_folders_kept
                echo -e "  \e[93m!\033[0m Apache did not accept the renamed folders - they were left as they were."
                moved=""
            else
                did=1
                while IFS=$'\t' read -r d target; do
                    [ -n "$d" ] || continue
                    _legacy_repack_snapshots "$(basename "$d")" "$(basename "$target")"
                    _selfupdate_publish_backups "$target"
                    echo "  ✔ ${d} → ${target}"
                done <<< "$moved"
            fi
        fi
        rm -rf "$abak"
    fi
    # only a marker on a commented-out line: nothing for Apache to reload
    grep -qs "#${LEGACY_NAME}# " /etc/apache2/ports.conf \
        && sed -i "s/#${LEGACY_NAME}# /#ciro# /" /etc/apache2/ports.conf && did=1

    # root's watcher line and the command; the line first, so nothing is left
    # calling a command that is gone
    if command -v crontab >/dev/null 2>&1 \
        && crontab -l 2>/dev/null | grep -Fq -e "${LEGACY_NAME} selfupdate-watch" -e "ciro selfupdate-watch"; then
        ( crontab -l 2>/dev/null | grep -vF -e "${LEGACY_NAME} selfupdate-watch" -e "ciro selfupdate-watch" ) | crontab - 2>/dev/null
        _ensure_selfupdate_cron
        did=1
    fi
    [ -f /root/install.sh ] && _link_ciro /root/install.sh /usr/local/bin/Ciro
    for f in "$LEGACY_BIN" "$SMALLC_BIN"; do
        if [ -L "$f" ]; then
            rm -f "$f"
            did=1
        fi
    done
    # what the script left behind under the old name elsewhere
    if [ -f "${RESOLV}.${LEGACY_NAME}.bak" ]; then
        [ -f "${RESOLV}.ciro.bak" ] || mv "${RESOLV}.${LEGACY_NAME}.bak" "${RESOLV}.ciro.bak"
        rm -f "${RESOLV}.${LEGACY_NAME}.bak"
    fi
    for f in /etc/apt/sources.list.d/*".disabled-by-${LEGACY_NAME}"; do
        [ -f "$f" ] && mv -f "$f" "${f%".disabled-by-${LEGACY_NAME}"}.disabled-by-ciro"
    done

    [ -n "$did" ] && echo -e "  \e[92m●\033[0m This server's files now carry the Ciro name; the command is \e[1mCiro\033[0m."
    [ "${1:-}" != "locked" ] && exec 8>&- 9>&-
    return 0
}

process_arguments() {
    local cmd="menu"
    # First non-flag token is the command
    case "$1" in
        install|update|remove|migrate|renew|backup|import|addbot|menu|selfupdate-watch) cmd="$1"; shift ;;
        version|-v|--version) print_version; exit 0 ;;
        -h|--help) print_usage; exit 0 ;;
        "") cmd="menu" ;;
        --*) cmd="menu" ;;            # only flags given -> menu, but still parse flags
        *) cmd="menu" ;;
    esac

    # Parse remaining flags
    while [ $# -gt 0 ]; do
        case "$1" in
            --name)    ARG_NAME="$2";    shift 2 ;;
            --token)   ARG_TOKEN="$2";   shift 2 ;;
            --admin)   ARG_ADMIN="$2";   shift 2 ;;
            --domain)  ARG_DOMAIN="$2";  shift 2 ;;
            --port)    ARG_PORT="$2";    shift 2 ;;
            --pma-port) ARG_PMA_PORT="$2"; shift 2 ;;
            --db-user) ARG_DBUSER="$2";  shift 2 ;;
            --db-pass) ARG_DBPASS="$2";  shift 2 ;;
            -h|--help) print_usage; exit 0 ;;
            *) echo -e "\e[91mUnknown option: $1\033[0m"; print_usage; exit 1 ;;
        esac
    done

    case "$cmd" in
        install) install_bot ;;
        update)  update_bot ;;
        # internal: root's crontab, not something an admin types
        selfupdate-watch) selfupdate_watch ;;
        remove)  remove_bot ;;
        migrate) migrate_to_ciro ;;
        renew)   renew_ssl ;;
        backup)  backup_bot ;;
        import)  import_bot ;;
        addbot)  install_second_bot ;;
        menu|*)  show_menu ;;
    esac
}

# A server set up under the old name has its files renamed before anything
# reads a path (the watcher does it from its own tick, under its own lock).
case "${1:-}" in
    -h|--help|-v|--version|version|selfupdate-watch|transfer-probe|transfer-receive) ;;
    *)
        # a transfer only adds a bot: what this server already runs is not renamed
        if [ -z "${CIRO_TRANSFER:-}" ]; then
            _legacy_migrate
            if [ $? -eq 2 ] && _legacy_state_left; then
                echo -e "\e[93mAn update is running on this server right now - run the command again in a minute.\033[0m"
                exit 1
            fi
        fi
        ;;
esac
# internal: the transfer runs only the renaming on the new server
[ "${1:-}" = "rename-legacy" ] && exit 0
# internal: Transfer's two halves on the destination (see transfer_probe)
case "${1:-}" in
    transfer-probe)   shift; transfer_probe "$@"; exit $? ;;
    transfer-receive) shift; transfer_receive "$@"; exit $? ;;
esac
process_arguments "$@"