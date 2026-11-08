# UdonForum – Projektregeln

ILIAS Repository-Objekt-Plugin (Name `UdonForum`, Anzeigename „Udon Forum“,
ID `xudf`). Ziel-Version: ILIAS 11 (lokale Testumgebung des Auftraggebers:
ILIAS 11 in Docker). Aufbau und Arbeitsweise wie beim Gruppenmanagement-Plugin
(`hjlauener/ilias`).

## Sprachdateien

- **Jeder neue oder geänderte Text kommt immer in alle drei Dateien:**
  `lang/ilias_de.lang`, `lang/ilias_en.lang` und `lang/ilias_fr.lang`. Die
  Schlüssel aller Dateien müssen identisch sein (gleiche Reihenfolge).
- Deutsch ohne englische Begriffe. Ausnahme auf Wunsch des Auftraggebers:
  „Online“ / „Offline“ für die Sichtbarkeit des Forums.
  Schweizer Schreibweise (ss statt ß).
- Kein Text fest im PHP-Code; Plugin-Texte über `$this->txt("key")`.
- Die Dateien sind in Abschnitte gegliedert (`# --- Abschnitt ---`; Zeilen mit
  `#` am Anfang ignoriert ILIAS). Neue Schlüssel in den passenden Abschnitt,
  in allen Dateien an derselben Stelle. Deutsch mit „Sie“, Englisch britisch,
  Französisch mit „vous“, Beitrag = « contribution », Antwort = « réponse »,
  geschütztes Leerzeichen (U+00A0) vor : ? ! und innerhalb « ».
- Vom Auftraggeber gewählte Namen bleiben: „Bubble-Charts“ (im Deutschen; Französisch « Graphiques à bulles »), „1000 ways Forum“ (in allen Sprachen).

Prüfen:

```bash
diff <(cut -d'#' -f1 lang/ilias_de.lang) <(cut -d'#' -f1 lang/ilias_en.lang)
diff <(cut -d'#' -f1 lang/ilias_de.lang) <(cut -d'#' -f1 lang/ilias_fr.lang)
```

## Oberfläche

- Rückfragen (z.B. Löschen) als Overlay wie im ILIAS-Kern (Interruptive Modal),
  keine eigenen Bestätigungsseiten.
- Nur UI-Aufrufe verwenden, die in ILIAS 11 funktionieren
  (z.B. kein `$f->legacy(...)` mit alter Signatur).

## Datenbank

- Neue DB-Schritte in `sql/dbupdate.php` anhängen und die Version in
  `plugin.php` erhöhen, sonst führt ILIAS sie nicht aus.
- Tabellen mit Präfix `rep_robj_xudf_`.
- Spaltennamen nicht aus der Liste reservierter Wörter (z.B. `status`).

## Entwickeln / Testen

- Der Auftraggeber testet mit `udf` (holt den Branch und lädt das Plugin im
  lokalen Docker-ILIAS neu), siehe `dev/README.md`.
- Vor jedem Push: `php -l` auf alle PHP-Dateien und den Sprachdatei-Check oben.
