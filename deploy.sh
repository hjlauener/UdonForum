#!/usr/bin/env bash
#
# Copies the UdonForum plugin into a local ILIAS installation and rebuilds
# the autoloader / ILIAS artifacts.
#
# Usage:
#   ./deploy.sh              copy plugin + composer du + build-artifacts
#   ./deploy.sh --update     additionally run "php cli/setup.php update"
#   ./deploy.sh --copy-only  only copy the plugin files
#   ./deploy.sh --help
#
# Configuration: deploy.env (see deploy.env.example) or environment variables.

set -euo pipefail

PLUGIN_NAME="UdonForum"
PLUGIN_SLOT_PATH="global/plugins/Services/Repository/RepositoryObject"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ---------------------------------------------------------------- config ---
if [[ -f "$SCRIPT_DIR/deploy.env" ]]; then
    # shellcheck disable=SC1091
    source "$SCRIPT_DIR/deploy.env"
fi

ILIAS_ROOT="${ILIAS_ROOT:-}"            # path to ILIAS (inside the container when DOCKER_CONTAINER is set)
DOCKER_CONTAINER="${DOCKER_CONTAINER:-}" # optional: name of the docker container running ILIAS
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
WEB_USER="${WEB_USER:-}"                 # optional: owner of the plugin files, e.g. www-data or _www
USE_SUDO="${USE_SUDO:-false}"            # true: use sudo for file operations in ILIAS (local mode)

RUN_BUILD=true
RUN_UPDATE=false

for arg in "$@"; do
    case "$arg" in
        --update) RUN_UPDATE=true ;;
        --copy-only) RUN_BUILD=false ;;
        -h|--help) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unbekannte Option: $arg (siehe --help)" >&2; exit 1 ;;
    esac
done

info()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
ok()    { printf '\033[1;32m✔\033[0m  %s\n' "$*"; }
fail()  { printf '\033[1;31m✘\033[0m  %s\n' "$*" >&2; exit 1; }

[[ -n "$ILIAS_ROOT" ]] || fail "ILIAS_ROOT ist nicht gesetzt. Kopiere deploy.env.example nach deploy.env und passe es an."

# Runs a shell command in the ILIAS environment (locally or in the container).
in_ilias() {
    if [[ -n "$DOCKER_CONTAINER" ]]; then
        docker exec -i "$DOCKER_CONTAINER" sh -c "$1"
    elif [[ "$USE_SUDO" == "true" ]]; then
        sudo sh -c "$1"
    else
        sh -c "$1"
    fi
}

# Runs a command as the web user (if configured), from the ILIAS root.
in_ilias_root() {
    local cmd="cd '$ILIAS_ROOT' && $1"
    if [[ -n "$WEB_USER" ]]; then
        if [[ -n "$DOCKER_CONTAINER" ]]; then
            docker exec -i -u "$WEB_USER" "$DOCKER_CONTAINER" sh -c "$cmd"
        else
            sudo -u "$WEB_USER" sh -c "$cmd"
        fi
    else
        in_ilias "$cmd"
    fi
}

# ---------------------------------------------------------------- checks ---
if [[ -n "$DOCKER_CONTAINER" ]]; then
    command -v docker >/dev/null || fail "docker wurde nicht gefunden."
    docker inspect "$DOCKER_CONTAINER" >/dev/null 2>&1 || fail "Docker-Container '$DOCKER_CONTAINER' läuft nicht."
fi

in_ilias "test -f '$ILIAS_ROOT/cli/setup.php'" \
    || fail "'$ILIAS_ROOT' sieht nicht nach einer ILIAS-Installation aus (cli/setup.php fehlt)."

# ILIAS 10+ may keep Customizing below public/
if in_ilias "test -d '$ILIAS_ROOT/Customizing'"; then
    CUSTOMIZING="$ILIAS_ROOT/Customizing"
elif in_ilias "test -d '$ILIAS_ROOT/public/Customizing'"; then
    CUSTOMIZING="$ILIAS_ROOT/public/Customizing"
else
    CUSTOMIZING="$ILIAS_ROOT/Customizing"
fi
TARGET="$CUSTOMIZING/$PLUGIN_SLOT_PATH/$PLUGIN_NAME"

# ------------------------------------------------------------------ copy ---
info "Kopiere Plugin nach ${DOCKER_CONTAINER:+$DOCKER_CONTAINER:}$TARGET"

in_ilias "rm -rf '$TARGET' && mkdir -p '$TARGET'"

COPYFILE_DISABLE=1 tar -C "$SCRIPT_DIR" \
    --exclude='./.git' \
    --exclude='./.gitignore' \
    --exclude='./.idea' \
    --exclude='./vendor' \
    --exclude='./deploy.sh' \
    --exclude='./deploy.env' \
    --exclude='./deploy.env.example' \
    --exclude='.DS_Store' \
    -cf - . | in_ilias "tar -C '$TARGET' -xf -"

if [[ -n "$WEB_USER" ]]; then
    in_ilias "chown -R '$WEB_USER' '$TARGET'"
fi
ok "Plugin-Dateien kopiert"

# ----------------------------------------------------------------- build ---
if [[ "$RUN_BUILD" == "true" ]]; then
    info "Erzeuge Autoloader (composer du)"
    in_ilias_root "$COMPOSER_BIN du"
    ok "Autoloader erzeugt"

    info "Erzeuge ILIAS-Artefakte (cli/setup.php build-artifacts)"
    in_ilias_root "$PHP_BIN cli/setup.php build-artifacts"
    ok "Artefakte erzeugt"
fi

if [[ "$RUN_UPDATE" == "true" ]]; then
    info "Führe ILIAS-Update aus (cli/setup.php update)"
    in_ilias_root "$PHP_BIN cli/setup.php update --yes"
    ok "Update ausgeführt"
fi

ok "$PLUGIN_NAME wurde nach ILIAS deployt."
echo "   Beim ersten Mal: Administration › Erweiterungen › Plugins › $PLUGIN_NAME installieren und aktivieren."
echo "   Nach Änderungen an plugin.php (Version), sql/ oder lang/: dort 'Aktualisieren' bzw. 'Sprachen neu laden'."
