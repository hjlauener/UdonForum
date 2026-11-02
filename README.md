# Forum – ILIAS 11 Repository-Plugin

Repository-Objekt-Plugin für **ILIAS 11**, mit dem sich im Magazin ein Objekt
vom Typ **„Forum“** hinzufügen lässt.

| | |
|---|---|
| Plugin-Name | `XForum` |
| Plugin-ID / Objekttyp | `xfrm` |
| Slot | `Services/Repository/RepositoryObject` (`robj`) |
| ILIAS | 11.0 – 11.999 |
| PHP | ≥ 8.3 |

> Der interne Name lautet `XForum` statt `Forum`, weil die Klassen
> `ilObjForum` / `ilObjForumGUI` bereits zum ILIAS-Core-Forum gehören.
> In der Oberfläche heisst das Objekt trotzdem **„Forum“**.

## Installation

1. Plugin in das Plugin-Verzeichnis von ILIAS klonen:

   ```bash
   mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject
   cd Customizing/global/plugins/Services/Repository/RepositoryObject
   git clone https://github.com/hjlauener/forum.git XForum
   ```

2. Im ILIAS-Hauptverzeichnis Autoloader und Artefakte neu erzeugen
   (u. a. damit die ilCtrl-Struktur das Plugin kennt):

   ```bash
   composer du
   php cli/setup.php build-artifacts
   php cli/setup.php update
   ```

3. In ILIAS unter **Administration › Erweiterungen › Plugins** das Plugin
   **XForum** installieren und aktivieren.

4. Rechte vergeben: In der Rechteverwaltung der Kategorie/des Kurses (bzw.
   in den Rollenvorlagen) das Recht **„Forum anlegen“** (`create_xfrm`)
   für die gewünschten Rollen freischalten.

5. Im Magazin über **Neues Objekt hinzufügen › Forum** ein Forum anlegen.

## Aktueller Stand

- Forum-Objekt anlegen (Titel, Beschreibung)
- Reiter **Inhalt** (Platzhalter für Themen/Beiträge)
- Reiter **Info**, **Einstellungen** (Titel/Beschreibung bearbeiten), **Rechte**
- Sprachdateien Deutsch/Englisch, eigenes Icon

## Struktur

```
plugin.php                         Plugin-Metadaten (ID, Version, ILIAS-Versionen)
classes/
  class.ilXForumPlugin.php         Plugin-Klasse
  class.ilObjXForum.php            Anwendungsklasse (Daten/Persistenz)
  class.ilObjXForumGUI.php         Oberfläche des Objekts
  class.ilObjXForumListGUI.php     Darstellung in Listen des Magazins
  class.ilObjXForumAccess.php      Zugriffsprüfung
lang/ilias_de.lang, ilias_en.lang  Sprachvariablen
sql/dbupdate.php                   Datenbank-Updateschritte
templates/images/icon_xfrm.svg     Objekt-Icon
```

## Nächste Schritte

- Datenbanktabellen für Themen und Beiträge (`sql/dbupdate.php`)
- Themen anlegen, anzeigen, beantworten
- Online/Offline-Einstellung, Moderation
