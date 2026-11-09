#!/usr/bin/env bash
#
# ILIAS Slim lokal in Docker
#
#   ./ilias-slim.sh            starten (beim ersten Mal: herunterladen und installieren)
#   ./ilias-slim.sh stop       anhalten (Daten bleiben erhalten)
#   ./ilias-slim.sh status     Container und Adresse anzeigen
#   ./ilias-slim.sh logs       Ausgaben des ILIAS-Containers verfolgen
#   ./ilias-slim.sh shell      Shell im ILIAS-Container
#   ./ilias-slim.sh reset      ALLES löschen (Code, Daten, Datenbank) und neu beginnen
#
# Läuft mit der macOS-Standard-Bash (3.2).

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"
ENV_FILE=".env"

C_BLUE=$'\033[1;34m'; C_GREEN=$'\033[1;32m'; C_RED=$'\033[1;31m'; C_OFF=$'\033[0m'
[ -t 1 ] || { C_BLUE=""; C_GREEN=""; C_RED=""; C_OFF=""; }
step() { printf '%s==>%s %s\n' "$C_BLUE" "$C_OFF" "$*"; }
ok()   { printf '%s ✔ %s %s\n' "$C_GREEN" "$C_OFF" "$*"; }
die()  { printf '%s ✘ %s %s\n' "$C_RED" "$C_OFF" "$*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || die "docker wurde nicht gefunden. Ist Docker Desktop installiert und gestartet?"
docker info >/dev/null 2>&1 || die "Docker läuft nicht. Bitte Docker Desktop starten und warten, bis 'Engine running' erscheint."

random_password() { LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 24; }

ensure_env() {
    if [ ! -f "$ENV_FILE" ]; then
        step "Erzeuge zufällige Passwörter in dev/ilias-slim/$ENV_FILE"
        umask 077
        {
            echo "# Lokale Passwörter für ILIAS Slim – nicht ins Repository (steht in .gitignore)"
            echo "DB_PASSWORD=$(random_password)"
            echo "DB_ROOT_PASSWORD=$(random_password)"
            echo "ILIAS_MASTER_PASSWORD=$(random_password)"
            echo "ILIAS_PORT=8080"
        } > "$ENV_FILE"
        ok "Passwörter erzeugt."
    fi
}

port() { grep -E '^ILIAS_PORT=' "$ENV_FILE" 2>/dev/null | cut -d= -f2 || true; }
url() { echo "http://localhost:$(p="$(port)"; echo "${p:-8080}")"; }

wait_ready() {
    step "Warte, bis ILIAS bereit ist (beim ersten Start 5–15 Minuten) …"
    echo "    (Fortschritt: in einem zweiten Terminal ./ilias-slim.sh logs)"
    local i=0 last="" since="$1" out
    while :; do
        out="$(docker compose logs --since "$since" ilias 2>/dev/null || true)"
        if printf '%s' "$out" | grep -q '\[ilias-slim\] ILIAS Slim ist bereit'; then
            break
        fi
        if printf '%s' "$out" | grep -q '\[ilias-slim\] FEHLER'; then
            docker compose logs --since "$since" --tail 40 ilias
            die "Der Start ist fehlgeschlagen (siehe oben). Nochmals versuchen: ./ilias-slim.sh"
        fi
        if [ -z "$(docker compose ps -q ilias --status running 2>/dev/null)" ] && [ "$i" -gt 10 ]; then
            docker compose logs --tail 40 ilias
            die "Der ILIAS-Container läuft nicht mehr (siehe oben)."
        fi
        # letzte Statuszeile anzeigen
        local now
        now="$(printf '%s' "$out" | grep '\[ilias-slim\]' | tail -1 | sed 's/.*\[ilias-slim\] //')"
        if [ -n "$now" ] && [ "$now" != "$last" ]; then echo "    $now"; last="$now"; fi
        i=$((i + 1))
        sleep 5
    done
    ok "ILIAS Slim läuft: $(url)"
    echo "    Anmelden: Benutzer root, Passwort homer (beim ersten Login ändern)"
}

case "${1:-start}" in
    start|up)
        ensure_env
        step "Starte ILIAS Slim (beim ersten Mal wird das Image gebaut – einige Minuten)"
        started="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        docker compose up -d --build
        wait_ready "$started"
        if command -v open >/dev/null 2>&1; then open "$(url)"; fi
        ;;
    stop|down)
        docker compose stop
        ok "ILIAS Slim angehalten. Starten mit: ./ilias-slim.sh"
        ;;
    status)
        docker compose ps
        echo "Adresse: $(url)"
        ;;
    logs)
        docker compose logs -f --tail 100 ilias
        ;;
    shell)
        docker compose exec ilias bash
        ;;
    reset)
        printf 'Wirklich ALLES löschen (ILIAS-Code, Daten, Datenbank)? Zum Bestätigen "ja" eingeben: '
        read -r answer
        [ "$answer" = "ja" ] || die "Abgebrochen."
        docker compose down -v
        ok "Gelöscht. Neu installieren mit: ./ilias-slim.sh"
        ;;
    *)
        sed -n '3,12p' "$0"
        exit 1
        ;;
esac
