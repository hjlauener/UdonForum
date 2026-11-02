# Udon Forum – ILIAS 11 Repository-Plugin

Repository-Objekt-Plugin für **ILIAS 11**, mit dem sich im Magazin ein Objekt
vom Typ **„Udon Forum“** hinzufügen lässt.

| | |
|---|---|
| Plugin-Name | `UdonForum` |
| Anzeigename | Udon Forum |
| Plugin-ID / Objekttyp | `xudf` |
| Slot | `Services/Repository/RepositoryObject` (`robj`) |
| ILIAS | 11.0 – 11.999 |
| PHP | ≥ 8.3 |

## Installation

1. Plugin in das Plugin-Verzeichnis von ILIAS klonen:

   ```bash
   mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject
   cd Customizing/global/plugins/Services/Repository/RepositoryObject
   git clone https://github.com/hjlauener/UdonForum.git UdonForum
   ```

2. Im ILIAS-Hauptverzeichnis Autoloader und Artefakte neu erzeugen
   (u. a. damit die ilCtrl-Struktur das Plugin kennt):

   ```bash
   composer du
   php cli/setup.php build-artifacts
   php cli/setup.php update
   ```

3. In ILIAS unter **Administration › Erweiterungen › Plugins** das Plugin
   **UdonForum** installieren und aktivieren.

4. Rechte vergeben: In der Rechteverwaltung der Kategorie/des Kurses (bzw.
   in den Rollenvorlagen) das Recht **„Udon Forum anlegen“** (`create_xudf`)
   für die gewünschten Rollen freischalten.

5. Im Magazin über **Neues Objekt hinzufügen › Udon Forum** ein Forum anlegen.

## Deployment ins lokale ILIAS (Terminal)

Mit `deploy.sh` wird das Plugin per Befehl in ein lokales ILIAS kopiert
(direkt auf dem Rechner oder in einem Docker-Container).

Einmalig einrichten:

```bash
cp deploy.env.example deploy.env
```

In `deploy.env` mindestens `ILIAS_ROOT` (Pfad zum ILIAS-Verzeichnis) setzen,
bei Docker zusätzlich `DOCKER_CONTAINER`. `deploy.env` wird nicht eingecheckt.

Danach:

```bash
./deploy.sh              # kopieren + composer du + build-artifacts
./deploy.sh --update     # zusätzlich: php cli/setup.php update
./deploy.sh --copy-only  # nur Dateien kopieren
```

Das Skript kopiert das Plugin nach
`<ILIAS>/Customizing/global/plugins/Services/Repository/RepositoryObject/UdonForum`
(bzw. `public/Customizing/...`, falls vorhanden) und ersetzt dabei eine ältere Version.
Beim ersten Mal muss das Plugin danach noch in der ILIAS-Administration
installiert und aktiviert werden.

## Aktueller Stand

- Udon-Forum-Objekt anlegen (Titel, Beschreibung)
- Reiter **Inhalt** (Platzhalter für Themen/Beiträge)
- Reiter **Info**, **Einstellungen** (Titel/Beschreibung bearbeiten), **Rechte**
- Sprachdateien Deutsch/Englisch, eigenes Icon

## Struktur

```
plugin.php                         Plugin-Metadaten (ID, Version, ILIAS-Versionen)
classes/
  class.ilUdonForumPlugin.php      Plugin-Klasse
  class.ilObjUdonForum.php         Anwendungsklasse (Daten/Persistenz)
  class.ilObjUdonForumGUI.php      Oberfläche des Objekts
  class.ilObjUdonForumListGUI.php  Darstellung in Listen des Magazins
  class.ilObjUdonForumAccess.php   Zugriffsprüfung
lang/ilias_de.lang, ilias_en.lang  Sprachvariablen
sql/dbupdate.php                   Datenbank-Updateschritte
deploy.sh, deploy.env.example      Deployment ins lokale ILIAS
templates/images/icon_xudf.svg     Objekt-Icon
```

## Nächste Schritte

- Datenbanktabellen für Themen und Beiträge (`sql/dbupdate.php`)
- Themen anlegen, anzeigen, beantworten
- Online/Offline-Einstellung, Moderation
