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

- Udon Forum nur in **Kursen und Gruppen** anlegbar
- **Sichtbar / nicht sichtbar** (Reiter *Einstellungen*)
- **Feed** mit Beitragskarten (Kategorie, Titel, Autor, Zeit, Vorschau, Anzahl
  Kommentare), neueste zuerst, 25 pro Seite
- **Filter** im Feed: Alle / pro Kategorie, zusätzlich „Unbeantwortet“
- **Beiträge** erstellen, bearbeiten (Autor), löschen (Autor oder Dozierende)
- **Kommentare** mit Antworten bis zur **4. Ebene**, einklappbar;
  gelöschte Kommentare mit Antworten bleiben als „[gelöscht]“ stehen
- **Kategorien** (Reiter *Kategorien*): Dozierende legen sie an, benennen um,
  sortieren und löschen sie. Neue Foren starten mit *Allgemeines*, *Fragen*
  und *Prüfung*.
- **Akzeptierte Antwort**: eine Antwort pro Beitrag, zuoberst grün angezeigt,
  Beitrag erhält „✔ Beantwortet“. In den *Einstellungen* wählbar, wer
  akzeptieren darf: Ersteller/in des Beitrags oder Kursleitung.
- Etikett **„Dozierende“** für Personen mit Schreibrecht, Hinweis „bearbeitet“
- Web-Adressen im Text werden zu Links, Rückfragen als Overlay

Rechte: *Lesen* = lesen und mitschreiben; *Bearbeiten* = Einstellungen und
Beiträge/Kommentare anderer löschen.

## Struktur

```
plugin.php                         Plugin-Metadaten (ID, Version, ILIAS-Versionen)
classes/
  class.ilUdonForumPlugin.php      Plugin-Klasse
  class.ilObjUdonForum.php         Anwendungsklasse (Daten/Persistenz)
  class.ilObjUdonForumGUI.php      Oberfläche des Objekts
  class.ilObjUdonForumListGUI.php  Darstellung in Listen des Magazins
  class.ilObjUdonForumAccess.php   Zugriffsprüfung
  class.ilUdfRepository.php        Datenbankzugriff (Beiträge, Kommentare)
  class.ilUdfPost.php              Beitrag
  class.ilUdfComment.php           Kommentar (max. 4 Ebenen)
  class.ilUdfCategory.php          Kategorie
lang/ilias_de.lang, ilias_en.lang  Sprachvariablen
sql/dbupdate.php                   Datenbank-Updateschritte
templates/images/icon_xudf.svg     Objekt-Icon
dev/                               Entwicklungs-Helfer (Befehl "udf", Docker-ILIAS)
```

## Nächste Schritte

- Etappe 2: Bewerten wie Reddit (▲/▼, Punktzahl), Sortierung Beliebt/Neu/Top,
  Ungelesen-Markierung
- Etappe 3 (Rest): Anheften/Schliessen von Beiträgen, Beiträge verschieben
- Etappe 4: Benachrichtigungen, @Erwähnungen, Merken, Bilder/Dateien, Suche
