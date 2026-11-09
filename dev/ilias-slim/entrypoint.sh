#!/bin/sh
#
# Start des ILIAS-Slim-Containers.
#
# Beim ersten Start (noch kein ilias.ini.php):
#   1. ILIAS Slim von Codeberg holen (git clone, Branch "slim")
#   2. npm clean-install und composer install (wie in install.md)
#   3. ILIAS-Setup ausführen (php cli/setup.php install)
# Danach – und bei jedem weiteren Start – den Webserver starten.
#
# Der Code liegt im Docker-Volume "code" (/var/www/html), die Daten in
# "files" (/var/www/files) und in der Datenbank. Neu beginnen: ./ilias-slim.sh reset

set -eu

ILIAS_DIR=/var/www/html
CONFIG=/var/www/config/ilias.json
REPO="${ILIAS_REPO:-https://codeberg.org/Essential-ILIAS-27/ILIAS-Slim.git}"
BRANCH="${ILIAS_BRANCH:-slim}"

log() { printf '\n[ilias-slim] %s\n' "$*"; }
# Bei einem Abbruch (z.B. Netzwerkfehler) eine klare Meldung; ilias-slim.sh wartet darauf.
# Der Container startet danach von selbst neu und versucht es noch einmal.
trap 'rc=$?; [ "$rc" -eq 0 ] || log "FEHLER: Start abgebrochen (Code $rc) – Details stehen oben."' EXIT
as_www() { runuser -u www-data -- env HOME=/var/www COMPOSER_HOME=/tmp/composer "$@"; }

if [ ! -f "$ILIAS_DIR/ilias.ini.php" ]; then
    chown www-data:www-data "$ILIAS_DIR" /var/www/files /var/www/logs /var/www/logs/errors /var/www/config

    # 1. Code holen
    if [ ! -f "$ILIAS_DIR/ilias_version.php" ]; then
        if [ -n "$(ls -A "$ILIAS_DIR" 2>/dev/null)" ]; then
            log "FEHLER: $ILIAS_DIR ist nicht leer, enthält aber kein ILIAS (abgebrochener erster Start?)."
            log "Neu beginnen mit: ./ilias-slim.sh reset"
            exit 1
        fi
        log "Lade ILIAS Slim (Branch $BRANCH) von Codeberg … (einige Minuten)"
        as_www git clone --depth 1 --branch "$BRANCH" "$REPO" "$ILIAS_DIR"
    fi

    # 2. Abhängigkeiten – Reihenfolge wie in install.md: zuerst npm, denn
    #    composer kopiert danach JavaScript aus node_modules nach public/
    log "npm clean-install …"
    (cd "$ILIAS_DIR" && as_www npm clean-install --omit=dev --ignore-scripts --no-audit --no-fund)
    log "composer install … (einige Minuten)"
    (cd "$ILIAS_DIR" && as_www composer install --no-dev --no-interaction --no-progress)

    # 3. Setup-Konfiguration (ausserhalb der Dokumentenwurzel) und Installation
    log "Warte auf die Datenbank …"
    i=0
    # --skip-ssl: der Client (MariaDB 11.8) verlangt sonst TLS, der Server im
    # internen Docker-Netz bietet keines an
    until mariadb-admin ping --skip-ssl -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" --silent >/dev/null 2>&1; do
        i=$((i + 1))
        [ "$i" -lt 60 ] || { log "FEHLER: Datenbank nicht erreichbar."; exit 1; }
        sleep 2
    done

    php -r '
        $c = [
            "common" => [
                "client_id" => "ilias",
                "master_password" => getenv("ILIAS_MASTER_PASSWORD"),
                "server_timezone" => "Europe/Zurich",
            ],
            "database" => [
                "type" => "innodb",
                "host" => getenv("DB_HOST"),
                "port" => 3306,
                "database" => getenv("DB_NAME"),
                "user" => getenv("DB_USER"),
                "password" => getenv("DB_PASSWORD"),
                "create_database" => false,
            ],
            "filesystem" => ["data_dir" => "/var/www/files"],
            "http" => ["path" => getenv("ILIAS_URL")],
            "logging" => [
                "enable" => true,
                "path_to_logfile" => "/var/www/logs/ilias.log",
                "errorlog_dir" => "/var/www/logs/errors",
            ],
            "systemfolder" => [
                "client" => [
                    "name" => "ILIAS Slim",
                    "description" => "Lokale Testinstallation",
                    "institution" => "",
                ],
                "contact" => [
                    "firstname" => "Lokale",
                    "lastname" => "Installation",
                    "email" => "admin@example.org",
                ],
            ],
            "utilities" => [
                "path_to_convert" => "/usr/bin/convert",
                "path_to_zip" => "/usr/bin/zip",
                "path_to_unzip" => "/usr/bin/unzip",
            ],
            "preview" => ["path_to_ghostscript" => "/usr/bin/gs"],
            "virusscanner" => ["virusscanner" => "none"],
        ];
        file_put_contents($argv[1], json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    ' "$CONFIG"
    chown www-data:www-data "$CONFIG"
    chmod 0600 "$CONFIG"

    log "ILIAS-Setup … (einige Minuten)"
    (cd "$ILIAS_DIR" && as_www php cli/setup.php install --yes "$CONFIG")

    # Das Setup installiert nur Englisch. Deutsch und Französisch als installiert
    # markieren (wie "Installieren" unter Administration › Sprachen), Deutsch als
    # Standardsprache und für root; "setup.php update" lädt dann die Sprachdateien.
    log "Installiere die Sprachen Deutsch und Französisch …"
    mariadb --skip-ssl -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
        UPDATE object_data SET description = 'installed' WHERE type = 'lng' AND title IN ('de', 'fr');
        UPDATE settings SET value = 'de' WHERE module = 'common' AND keyword = 'language';
        UPDATE usr_pref SET value = 'de' WHERE usr_id = 6 AND keyword = 'language';"
    (cd "$ILIAS_DIR" && as_www php cli/setup.php update --yes "$CONFIG")

    log "ILIAS Slim ist installiert: $ILIAS_URL  (Benutzer root, Passwort homer – bitte nach dem ersten Login ändern)"
fi

log "ILIAS Slim ist bereit: $ILIAS_URL"
exec docker-php-entrypoint "$@"
