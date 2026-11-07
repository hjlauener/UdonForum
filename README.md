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

Forum im Stil von Reddit, **ohne Kommentare**: Es gibt nur Beiträge, die
bewertet, sortiert und nach Kategorien gefiltert werden.

- Udon Forum nur in **Kursen und Gruppen** anlegbar
- **Sichtbar / nicht sichtbar** (Reiter *Einstellungen*)
- **Feed** mit Beitragskarten (Punkte, Kategorie, Titel, Autor, Zeit, Vorschau),
  25 pro Seite; Klick auf den Titel öffnet den ganzen Beitrag
- **Filter** links: Alle / pro Kategorie. Kategorie und Sortierung bleiben pro
  Forum erhalten, auch beim Hin- und Hernavigieren – bis zum Abmelden.
- **Sortierung** rechts: *Beliebt* (Punkte und Alter, wie Reddit), *Neu*,
  *Bestbewertet* (diese Woche / dieser Monat / gesamt)
- **Bewerten wie Reddit**: ▲/▼, erneuter Klick nimmt die Stimme zurück, ohne
  Neuladen der Seite; eigene Beiträge können nicht bewertet werden
- **Beiträge** erstellen, bearbeiten (Autor), löschen (Autor oder Kursleitung)
  – im Menü der Beitragskarte oder in der Beitragsansicht, mit Rückfrage.
  Ist im Feed eine Kategorie gewählt, ist sie beim neuen Beitrag vorausgewählt.
- **Markdown-Editor** von ILIAS (UI-Formular): Knöpfe für fett, kursiv, Listen,
  Links, Überschriften und eine Vorschau. Angezeigt wird der Text mit der
  Markdown-Umwandlung von ILIAS (HTML im Text wird nicht ausgeführt).
- **Kategorien** (Reiter *Kategorien*): Dozierende legen sie an, benennen um,
  sortieren und löschen sie. Neue Foren starten mit *Allgemeines*, *Fragen*
  und *Prüfung*.
- **Ungelesen**: Etikett „Neu“ für neue Beiträge anderer; „Alle als gelesen
  markieren“. Beim ersten Besuch gilt alles Bestehende als gelesen.
- Etikett **„Dozierende“** für Personen mit Schreibrecht, Hinweis „bearbeitet“

Rechte: *Lesen* = lesen, Beiträge schreiben und bewerten; *Bearbeiten* =
Einstellungen, Kategorien und Beiträge anderer löschen.

## Struktur

```
plugin.php                         Plugin-Metadaten (ID, Version, ILIAS-Versionen)
classes/
  class.ilUdonForumPlugin.php      Plugin-Klasse
  class.ilObjUdonForum.php         Anwendungsklasse (Daten/Persistenz)
  class.ilObjUdonForumGUI.php      Oberfläche des Objekts
  class.ilObjUdonForumListGUI.php  Darstellung in Listen des Magazins
  class.ilObjUdonForumAccess.php   Zugriffsprüfung
  class.ilUdfRepository.php        Datenbankzugriff (Beiträge, Kategorien, Stimmen)
  class.ilUdfPost.php              Beitrag
  class.ilUdfCategory.php          Kategorie
  class.ilUdfMarkdownRenderer.php  Markdown: Vorschau im Editor, Anzeige der Beiträge
lang/ilias_de.lang, ilias_en.lang  Sprachvariablen
sql/dbupdate.php                   Datenbank-Updateschritte
templates/images/icon_xudf.svg     Objekt-Icon
dev/                               Entwicklungs-Helfer (Befehl "udf", Docker-ILIAS)
```

## Nächste Schritte

- Beiträge anheften, in eine andere Kategorie verschieben
- Benachrichtigungen, Merken, Bilder/Dateien, Suche
