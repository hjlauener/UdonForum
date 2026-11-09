# ILIAS Slim lokal in Docker (Mac)

[ILIAS Slim](https://codeberg.org/Essential-ILIAS-27/ILIAS-Slim) ist eine
verschlankte Version von ILIAS 11 (Projekt „Essential ILIAS '27“ des ILUB der
Universität Bern und von CaT). Dieser Ordner richtet ILIAS Slim mit einem
einzigen Befehl in Docker ein – zum Testen des Udon-Forum-Plugins.

## Voraussetzungen

- **Docker Desktop** läuft (unten links „Engine running“), und im Terminal
  funktioniert `docker version`.
- Dieses Repository liegt auf dem Mac (siehe `dev/README.md`, Schritt 1).

## Starten

```bash
cd ~/Projekte/UdonForum/dev/ilias-slim
```

```bash
./ilias-slim.sh
```

Beim **ersten Start** (5–15 Minuten) passiert:

1. Das Webserver-Image wird gebaut (PHP 8.4, Apache, ImageMagick,
   Ghostscript, Composer, Node.js 24).
2. ILIAS Slim wird von Codeberg geholt (Branch `slim`).
3. `composer install` und `npm clean-install` laufen.
4. Das ILIAS-Setup installiert ILIAS mit der Datenbank (MariaDB 10.11).

Danach öffnet sich <http://localhost:8080> im Browser.
Anmelden mit **root** / **homer** – ILIAS verlangt danach ein neues Passwort.

Jeder **weitere Start** dauert nur Sekunden.

## Befehle

| Befehl | Wirkung |
| --- | --- |
| `./ilias-slim.sh` | starten |
| `./ilias-slim.sh stop` | anhalten (alles bleibt erhalten) |
| `./ilias-slim.sh status` | Container und Adresse anzeigen |
| `./ilias-slim.sh logs` | Ausgaben verfolgen (beenden mit Ctrl+C) |
| `./ilias-slim.sh shell` | Shell im ILIAS-Container |
| `./ilias-slim.sh reset` | **alles löschen** (Code, Daten, Datenbank) und neu beginnen |

## Udon Forum installieren

Wenn ILIAS Slim läuft, findet der Befehl `udf` den Container von selbst:

```bash
udf install
```

Danach wie gewohnt nach jeder Änderung: `udf`.

## Sicherheit

- ILIAS ist nur auf diesem Mac erreichbar (`127.0.0.1:8080`), nicht im WLAN.
- Die Datenbank hat keinen Port nach aussen.
- Die Passwörter für Datenbank und Setup werden beim ersten Start zufällig
  erzeugt und stehen in `dev/ilias-slim/.env` (nicht im Repository).
- Das ILIAS-Konto root/homer ist öffentlich bekannt: beim ersten Login ändern.
- Nur für Tests: keine echten Personendaten verwenden.

## Wo liegt was?

| Docker-Volume | Inhalt |
| --- | --- |
| `ilias-slim_code` | ILIAS-Code (`/var/www/html`), darin das Plugin |
| `ilias-slim_files` | ILIAS-Daten ausserhalb der Webwurzel (`/var/www/files`) |
| `ilias-slim_db` | Datenbank |
| `ilias-slim_logs` | ILIAS-Log und Fehlerlogs (`/var/www/logs`) |

Port ändern (z.B. wenn 8080 belegt ist): in `.env` `ILIAS_PORT=8081` setzen,
dann `./ilias-slim.sh reset` (die Adresse wird bei der Installation gespeichert).

## Probleme

- **„docker wurde nicht gefunden“**: Docker Desktop starten; in einem neuen
  Terminal-Fenster `docker version` prüfen.
- **Erster Start bricht ab** (z.B. Netzwerkfehler): `./ilias-slim.sh logs`
  zeigt den Grund; erneut `./ilias-slim.sh` – war der Code schon geholt,
  macht der Start dort weiter. Sonst `./ilias-slim.sh reset`.
- **Port 8080 belegt**: siehe oben (`ILIAS_PORT`).
