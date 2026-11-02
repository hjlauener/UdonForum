#!/usr/bin/env bash
#
# UdonForum – Entwicklungs-Helfer für eine lokale ILIAS-Docker-Installation
#
#   ./dev/reload.sh            Code ins ILIAS bringen und Plugin neu laden
#   ./dev/reload.sh --pull     vorher den neusten Stand von GitHub holen (git pull)
#   ./dev/reload.sh --watch    bei jeder Dateiänderung automatisch neu laden (braucht fswatch)
#   ./dev/reload.sh install    Plugin das erste Mal installieren und aktivieren
#   ./dev/reload.sh status     gefundene Konfiguration anzeigen
#   ./dev/reload.sh logs       Docker-Logs des ILIAS-Containers verfolgen
#   ./dev/reload.sh shell      Shell im ILIAS-Container öffnen
#   ./dev/reload.sh error CODE ILIAS-Fehlerlog zu "Sorry, an error occured … code CODE" anzeigen
#   ./dev/reload.sh error      neuestes ILIAS-Fehlerlog anzeigen
#   ./dev/reload.sh ilias-log  letzte Einträge des ILIAS-Logs anzeigen
#
# Unterstützt ILIAS 9 (setup/setup.php) und ILIAS 10/11 (cli/setup.php, public/Customizing).
# Einstellungen (optional) in dev/config.env – siehe dev/config.env.example.
# Läuft mit der macOS-Standard-Bash (3.2).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$SCRIPT_DIR/config.env"

PLUGIN_NAME="UdonForum"
PLUGIN_SLOT_PATH="Services/Repository/RepositoryObject/$PLUGIN_NAME"

# Defaults (können per config.env oder Umgebungsvariable überschrieben werden)
CONTAINER="${CONTAINER:-}"           # Name des ILIAS-Containers (leer = automatisch suchen)
ILIAS_DIR="${ILIAS_DIR:-}"           # ILIAS-Verzeichnis im Container (leer = automatisch suchen)
WEB_USER="${WEB_USER:-www-data}"     # Benutzer des Webservers im Container
CODE_USER="${CODE_USER:-}"           # Besitzer der ILIAS-Dateien (leer = automatisch)
SYNC_MODE="${SYNC_MODE:-auto}"       # auto | mount | copy
ILIAS_URL="${ILIAS_URL:-}"           # z.B. http://localhost:8080 – wird nach dem Reload geöffnet
OPEN_BROWSER="${OPEN_BROWSER:-0}"    # 1 = Browser nach dem Reload öffnen

# shellcheck disable=SC1090
if [ -f "$CONFIG_FILE" ]; then . "$CONFIG_FILE"; fi

CANDIDATE_DIRS="/var/www/html /var/www/html/ilias /var/www/ilias /var/www /srv/www/ilias /app /opt/ilias"

# werden von detect_layout gesetzt
SETUP_PHP=""
PLUGIN_OPT=""
PLUGIN_BASE=""
ILIAS_MAJOR=""
ILIAS_VERSION=""

# ----------------------------------------------------------------------
# Ausgabe
# ----------------------------------------------------------------------
if [ -t 1 ]; then
    C_BLUE=$'\033[1;34m'; C_GREEN=$'\033[1;32m'; C_YELLOW=$'\033[1;33m'; C_RED=$'\033[1;31m'; C_OFF=$'\033[0m'
else
    C_BLUE=""; C_GREEN=""; C_YELLOW=""; C_RED=""; C_OFF=""
fi
step() { printf '%s==>%s %s\n' "$C_BLUE" "$C_OFF" "$*"; }
ok()   { printf '%s ✔ %s %s\n' "$C_GREEN" "$C_OFF" "$*"; }
warn() { printf '%s ! %s %s\n' "$C_YELLOW" "$C_OFF" "$*" >&2; }
die()  { printf '%s ✘ %s %s\n' "$C_RED" "$C_OFF" "$*" >&2; exit 1; }

# ----------------------------------------------------------------------
# Docker / Container
# ----------------------------------------------------------------------
check_docker() {
    command -v docker >/dev/null 2>&1 || die "docker wurde nicht gefunden. Läuft Docker Desktop?"
    docker info >/dev/null 2>&1 || die "Docker läuft nicht. Bitte Docker Desktop starten."
}

container_has_ilias() { # $1 container, $2 dir
    docker exec "$1" test -f "$2/ilias.ini.php" >/dev/null 2>&1
}

find_ilias_dir_in() { # $1 container -> gibt Verzeichnis aus
    local dir
    if [ -n "$ILIAS_DIR" ]; then
        container_has_ilias "$1" "$ILIAS_DIR" && { echo "$ILIAS_DIR"; return 0; }
        return 1
    fi
    for dir in $CANDIDATE_DIRS; do
        if container_has_ilias "$1" "$dir"; then
            echo "$dir"
            return 0
        fi
    done
    return 1
}

detect_container() {
    local name dir found=""
    if [ -n "$CONTAINER" ]; then
        docker ps --format '{{.Names}}' | grep -qx "$CONTAINER" \
            || die "Container '$CONTAINER' läuft nicht (siehe 'docker ps')."
        dir="$(find_ilias_dir_in "$CONTAINER")" \
            || die "Im Container '$CONTAINER' wurde kein ILIAS gefunden. Bitte ILIAS_DIR in dev/config.env setzen."
        ILIAS_DIR="$dir"
        return
    fi

    for name in $(docker ps --format '{{.Names}}'); do
        if dir="$(find_ilias_dir_in "$name")"; then
            if [ -z "$found" ]; then
                found="$name"
                ILIAS_DIR="$dir"
            else
                warn "Mehrere ILIAS-Container gefunden ($found, $name) – verwende '$found'. Mit CONTAINER=... in dev/config.env festlegen."
            fi
        fi
    done
    [ -n "$found" ] || die "Kein laufender ILIAS-Container gefunden. Läuft ILIAS? Sonst CONTAINER und ILIAS_DIR in dev/config.env setzen."
    CONTAINER="$found"
}

in_container() { docker exec "$CONTAINER" "$@"; }

# ILIAS 9: setup/setup.php + Customizing/   ILIAS 10/11: cli/setup.php + public/Customizing/
detect_layout() {
    if in_container test -f "$ILIAS_DIR/cli/setup.php" >/dev/null 2>&1; then
        SETUP_PHP="cli/setup.php"
        PLUGIN_OPT="--legacy-plugin"
        PLUGIN_BASE="public/Customizing/global/plugins"
    elif in_container test -f "$ILIAS_DIR/setup/setup.php" >/dev/null 2>&1; then
        SETUP_PHP="setup/setup.php"
        PLUGIN_OPT="--plugin"
        PLUGIN_BASE="Customizing/global/plugins"
    else
        die "Weder cli/setup.php noch setup/setup.php in $ILIAS_DIR gefunden."
    fi

    ILIAS_VERSION="$(in_container php -r '
        foreach (["ilias_version.php", "include/inc.ilias_version.php"] as $f) {
            if (is_file($argv[1] . "/" . $f)) { include $argv[1] . "/" . $f; break; }
        }
        echo defined("ILIAS_VERSION_NUMERIC") ? ILIAS_VERSION_NUMERIC : "?";
    ' "$ILIAS_DIR" 2>/dev/null || echo "?")"
    ILIAS_MAJOR="${ILIAS_VERSION%%.*}"

    if [ -z "$CODE_USER" ]; then
        CODE_USER="$(in_container stat -c %u "$ILIAS_DIR/composer.json" 2>/dev/null || echo 0)"
    fi
}

check_web_user() {
    if ! in_container id -u "$WEB_USER" >/dev/null 2>&1; then
        warn "Benutzer '$WEB_USER' existiert im Container nicht – verwende root. (WEB_USER in dev/config.env anpassen)"
        WEB_USER="root"
    fi
}

# Befehl als Webserver-Benutzer (Setup-Schritte, die DB/Daten schreiben)
in_web() {
    docker exec -u "$WEB_USER" -w "$ILIAS_DIR" -e HOME=/tmp "$CONTAINER" "$@"
}

# Befehl als Besitzer der ILIAS-Dateien (composer, Artifacts im Code-Verzeichnis)
in_code() {
    docker exec -u "$CODE_USER" -w "$ILIAS_DIR" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer \
        -e COMPOSER_ALLOW_SUPERUSER=1 "$CONTAINER" "$@"
}

in_root() {
    docker exec -u 0 -w "$ILIAS_DIR" "$CONTAINER" "$@"
}

# Plugin-Verzeichnis im Container (bricht ab, falls ILIAS_DIR leer ist)
target_dir() { echo "${ILIAS_DIR:?ILIAS_DIR ist leer}/${PLUGIN_BASE:?}/$PLUGIN_SLOT_PATH"; }

setup_context() {
    check_docker
    detect_container
    detect_layout
    check_web_user
}

# ----------------------------------------------------------------------
# Code in den Container bringen
# ----------------------------------------------------------------------
is_mounted() {
    local marker=".mount-check-$$-$RANDOM"
    local marker_path="${PLUGIN_DIR:?}/${marker:?}"
    local result=1
    touch "$marker_path"
    if in_container test -f "$(target_dir)/$marker" >/dev/null 2>&1; then
        result=0
    fi
    rm -f "$marker_path"
    return $result
}

# Ältere Skript-Versionen haben bei ILIAS 10+ fälschlich nach <ILIAS>/Customizing kopiert.
cleanup_wrong_location() {
    local old dir
    [ "$PLUGIN_BASE" = "public/Customizing/global/plugins" ] || return 0
    old="${ILIAS_DIR:?}/Customizing/global/plugins/$PLUGIN_SLOT_PATH"
    if in_container grep -qs '"xudf"' "$old/plugin.php"; then
        step "Entferne alte Kopie am falschen Ort ($old)"
        in_root find "$old" -mindepth 1 -delete
        in_root rmdir "$old"
        # leere Elternordner bis <ILIAS>/Customizing entfernen
        dir="$(dirname "$old")"
        while [ "$dir" != "$ILIAS_DIR" ] && in_root rmdir "$dir" >/dev/null 2>&1; do
            dir="$(dirname "$dir")"
        done
        ok "Alte Kopie entfernt."
    fi
}

sync_code() {
    local mode="$SYNC_MODE" target
    cleanup_wrong_location

    if [ "$mode" = "auto" ]; then
        if is_mounted; then mode="mount"; else mode="copy"; fi
    fi

    if [ "$mode" = "mount" ]; then
        ok "Plugin-Ordner ist in den Container gemountet – Änderungen sind sofort sichtbar."
        return
    fi

    target="$(target_dir)"
    case "$target" in
        */RepositoryObject/"$PLUGIN_NAME") ;;
        *) die "Unerwarteter Zielordner: $target" ;;
    esac

    step "Kopiere Plugin nach $CONTAINER:$target"
    in_root mkdir -p "$target"
    # alten Stand im Container entfernen (nur Inhalt des Plugin-Ordners)
    in_root find "$target" -mindepth 1 -delete
    # COPYFILE_DISABLE verhindert macOS-Metadaten (._*-Dateien)
    COPYFILE_DISABLE=1 tar -C "$PLUGIN_DIR" \
        --exclude=.git --exclude='./dev/config.env' --exclude='.DS_Store' --exclude='.mount-check-*' \
        -cf - . | docker exec -i -u 0 "$CONTAINER" tar -xf - -C "$target" 2>/dev/null \
        || die "Kopieren fehlgeschlagen."
    in_root chown -R "$CODE_USER" "$target"
    in_root chmod -R a+rX "$target"
    ok "Code kopiert."
}

# ----------------------------------------------------------------------
# Neu laden
# ----------------------------------------------------------------------
lint_php() {
    step "Prüfe PHP-Syntax"
    local out
    out="$(in_web sh -c "find '$(target_dir)/' -name '*.php' -not -path '*/vendor/*' -exec php -l {} \; 2>&1" | grep -v '^No syntax errors' || true)"
    if [ -n "$out" ]; then
        printf '%s\n' "$out" >&2
        die "PHP-Syntaxfehler – bitte beheben."
    fi
    ok "Syntax ok."
}

# Autoloader (neue Klassen) und Artifacts (Plugin-Infos, ilCtrl-Struktur) neu erzeugen
build() {
    local out
    in_code sh -c 'command -v composer' >/dev/null 2>&1 || die "composer ist im Container nicht installiert."

    if [ "$SETUP_PHP" = "cli/setup.php" ]; then
        # ILIAS 10+: composer dump-autoload ruft selbst "cli/setup.php build" auf
        step "Erzeuge Autoloader und ILIAS-Artifacts (composer dump-autoload)"
        if ! out="$(in_code composer dump-autoload --no-interaction 2>&1)"; then
            printf '%s\n' "$out" >&2
            die "composer dump-autoload fehlgeschlagen."
        fi
        ok "Autoloader und Artifacts erzeugt."
    else
        step "Aktualisiere Autoloader (composer dump-autoload)"
        if ! out="$(in_code composer dump-autoload --no-interaction 2>&1)"; then
            printf '%s\n' "$out" >&2
            die "composer dump-autoload fehlgeschlagen."
        fi
        ok "Autoloader aktualisiert."
        step "Erzeuge ILIAS-Artifacts (Plugin-Infos, ilCtrl-Struktur)"
        if ! out="$(in_code php setup/setup.php build-artifacts --yes 2>&1)"; then
            printf '%s\n' "$out" >&2
            die "build-artifacts fehlgeschlagen."
        fi
        ok "Artifacts erzeugt."
    fi
}

update_plugin() {
    step "Aktualisiere Plugin (DB-Schritte, Sprachen, Aktivierung)"
    local out
    if out="$(in_web php "$SETUP_PHP" update "$PLUGIN_OPT" "$PLUGIN_NAME" --yes 2>&1)"; then
        ok "Plugin aktualisiert."
    else
        printf '%s\n' "$out" >&2
        warn "Plugin-Update fehlgeschlagen. Ist das Plugin schon installiert? → udf install"
        return 1
    fi
}

open_browser() {
    if [ "$OPEN_BROWSER" = "1" ] && [ -n "$ILIAS_URL" ] && command -v open >/dev/null 2>&1; then
        open "$ILIAS_URL"
    fi
}

do_reload() {
    local started
    started="$(date +%s)"
    sync_code
    lint_php
    build
    update_plugin || true
    ok "Fertig in $(( $(date +%s) - started ))s – Seite im Browser neu laden."
}

do_install() {
    sync_code
    lint_php
    build
    step "Installiere und aktiviere Plugin"
    if in_web php "$SETUP_PHP" install "$PLUGIN_OPT" "$PLUGIN_NAME" --yes; then
        ok "Plugin installiert und aktiviert."
    else
        warn "Installation per Kommandozeile fehlgeschlagen."
        warn "Alternative: in ILIAS unter Administration → Erweiterungen → Plugins → UdonForum → Installieren, dann Aktivieren."
        exit 1
    fi
}

do_watch() {
    command -v fswatch >/dev/null 2>&1 || die "fswatch fehlt. Installieren mit: brew install fswatch"
    do_reload
    step "Beobachte $PLUGIN_DIR – Abbrechen mit Ctrl+C"
    fswatch -o -l 1 -e '/\.git/' -e '\.mount-check-' -e '/dev/config\.env$' "$PLUGIN_DIR" | while read -r _; do
        echo
        step "Änderung erkannt ($(date +%H:%M:%S))"
        ( do_reload ) || warn "Reload fehlgeschlagen – warte auf nächste Änderung."
    done
}

do_pull() {
    step "Hole neusten Stand von GitHub"
    git -C "$PLUGIN_DIR" pull --ff-only || die "git pull fehlgeschlagen (lokale Änderungen?)."
    ok "Code ist aktuell: $(git -C "$PLUGIN_DIR" log -1 --format='%h %s')"
}

# Wert aus ilias.ini.php lesen: ini_value <Abschnitt> <Schlüssel>
ini_value() {
    in_container php -r '
        $ini = @parse_ini_file($argv[1], true);
        echo $ini[$argv[2]][$argv[3]] ?? "";
    ' "$ILIAS_DIR/ilias.ini.php" "$1" "$2" 2>/dev/null || true
}

do_error() { # $1 = Fehlercode (optional)
    local code="${1:-}" dir file
    dir="$(ini_value log error_path)"

    if [ -n "$code" ]; then
        code="$(printf '%s' "$code" | tr -cd 'A-Za-z0-9_.-')"
        file=""
        if [ -n "$dir" ] && in_root test -f "$dir/$code.log"; then
            file="$dir/$code.log"
        else
            step "Suche $code.log im Container …"
            file="$(in_root sh -c "find /var /tmp /srv /opt /home -name '$code.log' -type f 2>/dev/null | head -1" || true)"
        fi
        [ -n "$file" ] || die "Kein Fehlerlog '$code.log' gefunden (Fehler-Log-Verzeichnis laut ilias.ini.php: '${dir:-nicht gesetzt}')."
    else
        [ -n "$dir" ] || die "In ilias.ini.php ist kein Fehler-Log-Verzeichnis (error_path) gesetzt. Bitte den Fehlercode angeben: udf error CODE"
        file="$(in_root sh -c "ls -t '$dir'/*.log 2>/dev/null | head -1" || true)"
        [ -n "$file" ] || die "Keine Fehlerlogs in $dir."
    fi

    local out_dir="$SCRIPT_DIR/logs" local_file
    mkdir -p "$out_dir"
    local_file="$out_dir/$(basename "$file")"
    in_root cat "$file" | mask_secrets > "$local_file"

    step "Fehlerlog: $file (Anfang)"
    head -n 60 "$local_file"
    echo "…"
    ok "Vollständiges Log (Passwörter ausgeblendet): $local_file"
    if command -v pbcopy >/dev/null 2>&1; then
        head -n 150 "$local_file" | pbcopy
        ok "Die ersten 150 Zeilen sind in der Zwischenablage – im Chat einfach mit Cmd+V einfügen."
    fi
}

# Passwörter, Tokens, Session-Cookies in Logausgaben ausblenden
mask_secrets() {
    sed -E \
        -e 's/^([^:=]*(PASSWORD|PASSWD|SECRET|TOKEN|_PW|password|passwd|secret|token)[^:=]*[:=][[:space:]]*).*/\1*****/' \
        -e 's/((PHPSESSID|ilClientId|SESSID)[^A-Za-z0-9]+)[A-Za-z0-9,_-]+/\1*****/g'
}

do_ilias_log() {
    local path file log
    path="$(ini_value log path)"
    file="$(ini_value log file)"
    [ -n "$path" ] && [ -n "$file" ] || die "In ilias.ini.php ist kein ILIAS-Log (log path/file) konfiguriert."
    log="$path/$file"
    step "ILIAS-Log: $log (letzte 100 Zeilen)"
    in_root tail -n 100 "$log"
}

do_status() {
    echo "Plugin-Ordner (Mac): $PLUGIN_DIR"
    echo "Container:           $CONTAINER"
    echo "ILIAS-Version:       $ILIAS_VERSION"
    echo "ILIAS-Verzeichnis:   $ILIAS_DIR"
    echo "Setup:               php $SETUP_PHP ($PLUGIN_OPT)"
    echo "Plugin im Container: $(target_dir)"
    echo "Webserver-Benutzer:  $WEB_USER"
    echo "Code-Besitzer (uid): $CODE_USER"
    if is_mounted; then echo "Sync-Modus:          mount (live)"; else echo "Sync-Modus:          copy"; fi
    echo "Git-Branch:          $(git -C "$PLUGIN_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '-')"
}

# ----------------------------------------------------------------------
# Main
# ----------------------------------------------------------------------
PULL=0
WATCH=0
CMD="reload"
ERROR_CODE=""
for arg in "$@"; do
    if [ "$CMD" = "error" ] && [ -z "$ERROR_CODE" ] && [ "${arg#-}" = "$arg" ]; then
        ERROR_CODE="$arg"
        continue
    fi
    case "$arg" in
        --pull) PULL=1 ;;
        --watch) WATCH=1 ;;
        --open) OPEN_BROWSER=1 ;;
        reload|install|status|logs|shell|error|ilias-log) CMD="$arg" ;;
        -h|--help) sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) die "Unbekannte Option: $arg (siehe --help)" ;;
    esac
done

if [ "$PULL" = "1" ]; then do_pull; fi
setup_context

case "$CMD" in
    status) do_status ;;
    error) do_error "$ERROR_CODE" ;;
    ilias-log) do_ilias_log ;;
    logs) docker logs -f --tail 100 "$CONTAINER" ;;
    shell) docker exec -it -u "$WEB_USER" -w "$ILIAS_DIR" "$CONTAINER" sh -c 'command -v bash >/dev/null && exec bash || exec sh' ;;
    install) do_install; open_browser ;;
    reload)
        if [ "$WATCH" = "1" ]; then
            do_watch
        else
            do_reload
            open_browser
        fi
        ;;
esac
