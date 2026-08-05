# evccMQTT — Projektwissen

Symcon-Modulbibliothek **„evcc"** (`library.json`, Kennung bumaas): integriert
[evcc](https://evcc.io) über MQTT in IP-Symcon. 8 Module, je eines pro
evcc-Datenbereich:

| Modul | evcc-Bereich (MQTT-Topic) |
|---|---|
| `evccSite` | `evcc/site/` (Standort-Aggregate, schreibbare Regler) |
| `evccLoadPointId` | `evcc/loadpoints/<id>/` (Ladepunkt) |
| `evccVehicleName` | `evcc/vehicles/<name>/` (Fahrzeug) |
| `evccSitePvId` | `evcc/site/pv/<id>/` (einzelne PV-Anlage) |
| `evccSiteBatteryId` | `evcc/site/battery/devices/<id>/` (einzelne Batterie, evcc ≥ 0.301) |
| `evccSiteAuxId` | `evcc/site/aux/<id>/` (extern geregeltes Gerät) |
| `evccSiteStatistics` | `evcc/site/statistics/<scope>/` |
| `evccSiteForecasts` | `evcc/site/forecast/` |

## Architektur

- **`libs/Themes.php`** ist die zentrale Datendatei: pro Modul ein Backed-Enum
  (`<Name>Ident`, Werte = MQTT-Topic-Elemente = Variablen-Idents) und eine
  Theme-Klasse (`<Name> extends ThemeBasics`) mit `$properties`
  (Ident → `IPS_VAR_TYPE`, `IPS_PRESENTATION`, `IPS_VAR_NAME`, optional
  `IPS_VAR_ACTION`/`IPS_VAR_FACTOR`). Die module.php sind dünn:
  `registerVariables()` via `MaintainVariable`, `ReceiveData()` mappt
  Topic-Endelemente auf Idents, `RequestAction()` published `<topic>/<ident>/set`.
- **Presentations statt Profile**: `IPS_PRESENTATION` enthält direkt die
  `VARIABLE_PRESENTATION_*`-Arrays (Legacy-Profile gibt es nicht mehr).
- **`libs/helper/`**: geteilte Helfer (relevant v. a. `MQTTHelper.php` mit
  `prepareMQTTData()`/`mqttCommand()`; Rest ist Standard-Symcon-Helper-Sammlung).

## Übersetzungskonzept (zweigleisig!)

1. **form.json-Captions und Variablennamen** (`IPS_VAR_NAME` läuft durch
   `$this->Translate()`) → `locale.json` **des jeweiligen Moduls** (de).
2. **Presentation-Texte** (OPTIONS-Captions wie „Off"/„Only PV", PREFIX) →
   zentrale **`libs/locale_profile.json`**, angewendet von
   `ThemeBasics::translatePresentationValue()` über `IPS_GetSystemLanguage()`.
   SUFFIXe sind Einheiten und werden nicht übersetzt.

## Tests / CI

- `C:/php/php tests/check_locale.php` — prüft beide Gleise auf Vollständigkeit
  (stubbt die IPS-Konstanten und lädt `libs/Themes.php` per Reflection;
  Exit-Code 1 bei fehlenden Übersetzungen). Bei neuen Variablen/Optionen in
  `Themes.php` schlägt der Check so lange fehl, bis die de-Schlüssel ergänzt sind.
- CI: `.github/workflows/check.yml` (php -l über alle PHP-Dateien,
  JSON-Validität, Locale-Check) — Badge im README.

## Konventionen

- Version/Build/Datum in `library.json`; Commit-Subject
  `<version> build <NN>: <Beschreibung>` (Details: globale CLAUDE.md,
  „Build-/Versionspflege").
- evcc-Topic-Änderungen der Vergangenheit (für Kompatibilitätsfragen):
  `phases/set` → `phasesConfigured/set`; Batterie-Aggregate liegen seit
  evcc 0.301 verschachtelt (`battery/power` → Ident `batteryPower`,
  Sonderbehandlung in `evccSite::ReceiveData()`).
