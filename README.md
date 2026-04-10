# wp_clubmgmt – Vereinsmanager

**Version:** 26.1.0-rc.1

WordPress-Plugin **Vereinsmanager** zur vollständigen Verwaltung gemeinnütziger
Fördervereine direkt im WordPress-Admin.

## Module

- **CRM** – Mitgliederverwaltung mit eigener DB-Tabelle, Status-Workflow
  (Interessent → Aktiv → Ehemalig), automatischer Mitgliedsnummer-Vergabe und
  WP_List_Table mit Suche / Filter / Bulk-Actions.
- **Finanzen** – Beitragsforderungen pro Jahr generieren, Status verfolgen,
  Soll/Ist-Übersicht, SEPA-CSV-Export.
- **Zuwendungsbestätigungen** – PDF-Erzeugung pro Mitglied + Jahr (Sammel­
  bestätigung) auf Basis des amtlichen BMF-Musters via TCPDF, einzeln oder als
  ZIP.
- **E-Mail-Versand** – Personalisierter Versand an gefilterte Empfänger­gruppen,
  TinyMCE-Editor, Platzhalter, Versand-Queue mit Cron + Log.
- **Import / Export** – CSV-Import mit Spalten-Mapping, Vorschau, Dry-Run,
  Duplikat­erkennung; CSV-Export mit Filter und Feld­auswahl (UTF-8 BOM).
- **DSGVO** – Datenauskunft (HTML), Anonymisierung, Hinweise auf abgelaufene
  Aufbewahrungs­fristen.
- **Dashboard** – Kennzahlen, Beitrags-Übersicht, Mitglieder­entwicklung der
  letzten 5 Jahre.
- **Zentrales Logging** – Fire-and-forget Versand an `log.broetzens.de`
  (BroetzensTools Central Logging), ohne PII. Übertragen werden nur die
  Events `plugin_error`, `member_created` und `email_sent`.

## Installation

1. Plugin-Verzeichnis `vereinsmanager/` nach `wp-content/plugins/` kopieren.
2. Im Plugin-Verzeichnis `composer install` ausführen, um TCPDF zu installieren.
3. Plugin in WordPress aktivieren – Tabellen, Rollen und Defaults werden
   automatisch angelegt.
4. Unter **Vereinsmanager → Einstellungen** Vereinsdaten, SEPA-Gläubiger-ID
   und ggf. den Logging-API-Key hinterlegen.

## Anforderungen

- WordPress 6.4+
- PHP 8.1+
- Optional: TCPDF (per Composer) für die PDF-Erzeugung der Zuwendungs­
  bestätigungen.

## Rollen

- `vm_admin` (Vereinsverwalter) – Vollzugriff
- `vm_board_member` (Vorstandsmitglied) – Mitglieder, E-Mails, Dashboard
- WordPress `administrator` erhält automatisch alle Capabilities

## Datumsformat

Alle sichtbaren Datumsfelder (Mitgliederliste, Zahlungen, DSGVO-Auskunft,
CSV-Export, Zuwendungsbestätigung) werden im deutschen Format `TT.MM.JJJJ`
ausgegeben. Intern speichert das Plugin weiterhin ISO-Datumswerte
(`JJJJ-MM-TT`); beim CSV-Import werden sowohl `JJJJ-MM-TT` als auch
`TT.MM.JJJJ` akzeptiert.

## Changelog

### 26.1.0-rc.1
- Alle Datumsausgaben auf deutsches Format `TT.MM.JJJJ` umgestellt.
- CSV-Import akzeptiert zusätzlich `TT.MM.JJJJ`.
- Zentrales Logging auf die drei Kern-Events `plugin_error`,
  `member_created` und `email_sent` reduziert.

### 1.0.0
- Initiales Release.

## Lizenz

GPL-2.0-or-later
