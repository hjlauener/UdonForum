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

Forum im Stil von Reddit: Beiträge werden bewertet, sortiert und nach
Kategorien gefiltert; auf jeden Beitrag kann man antworten (eine Ebene).

- Udon Forum nur in **Kursen und Gruppen** anlegbar
- **Online / offline** und **Markdown-Editor** direkt im Formular beim Anlegen
  (beide eingeschaltet, Cursor im Titel); später änderbar im Reiter *Einstellungen*
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
  Ist im Feed eine Kategorie gewählt (oder gibt es nur eine), ist sie beim neuen
  Beitrag vorausgewählt. Im Formular „Abbrechen“ links von „Veröffentlichen“
  bzw. „Speichern“. Tastatur: Der Cursor steht im Titel (bzw. in der Kategorie,
  falls noch keine gewählt ist); Tab im Titel springt direkt ins Textfeld.
- **Markdown-Editor** von ILIAS (UI-Formular): Knöpfe für fett, kursiv, Listen,
  Links, Überschriften und eine Vorschau. Angezeigt wird der Text mit der
  Markdown-Umwandlung von ILIAS (HTML im Text wird nicht ausgeführt).
  In den *Einstellungen* abschaltbar (bei neuen Foren eingeschaltet); dann gibt
  es ein einfaches Textfeld. Jeder Beitrag wird so angezeigt, wie er
  geschrieben wurde. Der Text eines Beitrags ist Pflicht.
- **Kategorien** (Reiter *Einstellungen*, Unterreiter *Kategorien*): Dozierende legen sie an, benennen um,
  sortieren und löschen sie. Neue Foren starten mit *Allgemeines*, *Fragen*
  und *Prüfung*.
- **Ungelesen**: Etikett „Neu“ für neue Beiträge anderer; „Alle als gelesen
  markieren“. Beim ersten Besuch gilt alles Bestehende als gelesen.
- **Antworten**: eigenes Antwortfeld unter jedem Beitrag (Markdown-Editor bzw.
  Textfeld je nach Einstellung, Text Pflicht); Antworten älteste zuerst,
  bearbeiten (Autor), löschen (Autor oder Kursleitung) mit Rückfrage
- **Statistik** (eigenes Recht „Statistik einsehen“): Reiter *Statistik*,
  - Unterreiter *Standard*: alle, die im Forum aktiv waren (Beitrag eröffnet
    oder geantwortet): Name, Vorname, E-Mail, „Forumsbeitrag eröffnet“,
    „Forumsantwort“, „Anzahl Wörter“ (Text der Beiträge und Antworten) –
    sortierbar über die Spaltenköpfe
  - Unterreiter *Spinnennetz*: jede aktive Person als Punkt mit Namen; der
    Kreis darum wächst mit jedem eröffneten Beitrag; Linien zwischen Personen,
    die einander geantwortet haben – je mehr Antworten, desto dicker
  - Unterreiter *Bubble-Charts*: jedes Thema (Kategorie) als Kreis, je
    grösser, desto mehr Beiträge; verwandte Themen (dieselben aktiven Personen)
    überlappen sich, nicht verwandte nie; in der Mitte ein Kuchendiagramm mit
    dem Anteil Beiträge und Antworten
  - Beim Einführen des Rechts erhalten es alle Rollen, die das Forum
    bearbeiten dürfen.
- **Mitglieder** (Reiter für alle): Unterreiter *Ansicht* mit allen Teilnehmenden
  des Kurses/der Gruppe – Name, Vorname, E-Mail, Rolle (Dozierende, Tutor/in,
  Mitglied), sortierbar
- **1000 ways Forum** (Einstellung): Jedes Mitglied (ausser Dozierenden) erhält
  zufällig und gleichmässig verteilt eine von vier Diskussionsrollen aus der
  japanischen Sagenwelt: *Hakutaku* (Fakten), *Kitsune* (Hinterfragen),
  *Tanuki* (Beispiele), *Kodama* (Verbinden und Schlichten). Zusätzliche Spalte
  in *Mitglieder*, Etikett mit Icon neben dem Namen in Beiträgen und Antworten,
  Hinweis über dem Schreibfeld. Dozierende ändern die Rollen im Unterreiter
  *Rollen zuteilen* oder mischen sie neu.
- Etikett **„Dozierende“** für Personen mit Schreibrecht, Hinweis „bearbeitet“

Rechte: *Lesen* = lesen, Beiträge und Antworten schreiben, bewerten;
*Bearbeiten* = Einstellungen, Kategorien, Beiträge/Antworten anderer löschen;
*Statistik einsehen* = Reiter *Statistik*.

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
  class.ilUdfReply.php             Antwort auf einen Beitrag
  class.ilUdfStatisticsTable.php   Daten und Sortierung der Statistik-Tabelle
  class.ilUdfNetworkGraph.php      Spinnennetz (Layout und SVG)
  class.ilUdfBubbleChart.php       Bubble-Charts (Layout und SVG)
  class.ilUdfCreatures.php         1000 ways Forum: die vier Figuren, Icons, Verteilung
  class.ilUdfMembersTable.php      Daten und Sortierung der Mitglieder-Tabelle
lang/ilias_de.lang, ilias_en.lang  Sprachvariablen
sql/dbupdate.php                   Datenbank-Updateschritte
templates/images/icon_xudf.svg     Objekt-Icon
dev/                               Entwicklungs-Helfer (Befehl "udf", Docker-ILIAS)
```

## Nächste Schritte

- Beiträge anheften, in eine andere Kategorie verschieben
- Benachrichtigungen, Merken, Bilder/Dateien, Suche
