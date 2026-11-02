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

## Lokal entwickeln und testen

Wie beim Gruppenmanagement-Plugin: Der Terminal-Befehl **`udf`** holt den
neusten Stand von GitHub und lädt das Plugin im lokalen Docker-ILIAS neu.
Einrichtung und alle Befehle: **[dev/README.md](dev/README.md)**.

Kurzfassung:

```bash
mkdir -p ~/Projekte && cd ~/Projekte
```

```bash
git clone -b claude/sleepy-ritchie-yxpcul https://github.com/hjlauener/forum.git UdonForum
```

```bash
~/Projekte/UdonForum/dev/install-command.sh
```

Neues Terminal-Fenster öffnen, dann:

```bash
udf install
```

Danach genügt nach jeder Änderung:

```bash
udf
```

## Manuelle Installation

1. Plugin-Ordner nach
   `<ILIAS>/public/Customizing/global/plugins/Services/Repository/RepositoryObject/UdonForum`
   kopieren.
2. Im ILIAS-Verzeichnis `composer dump-autoload` ausführen (erzeugt ab ILIAS 10
   auch die Artifacts, u. a. die ilCtrl-Struktur).
3. In ILIAS unter **Administration › Erweiterungen › Plugins** das Plugin
   **UdonForum** installieren und aktivieren.
4. Das Recht **„Udon Forum anlegen“** (`create_xudf`) für die gewünschten
   Rollen freischalten.
5. Im Magazin über **Neues Objekt hinzufügen › Udon Forum** ein Forum anlegen.

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
templates/images/icon_xudf.svg     Objekt-Icon
dev/                               Entwicklungs-Helfer (Befehl "udf", Docker-ILIAS)
```

## Nächste Schritte

- Datenbanktabellen für Themen und Beiträge (`sql/dbupdate.php`)
- Themen anlegen, anzeigen, beantworten
- Sichtbarkeit (sichtbar / nicht sichtbar), Moderation
