![evccMQTT Logo](/docs/pictures/logo.png)
# evccMQTT

[![Checks](https://github.com/bumaas/evccMQTT/actions/workflows/check.yml/badge.svg)](https://github.com/bumaas/evccMQTT/actions/workflows/check.yml)

Dieses Modul integriert **[evcc](https://evcc.io)** in **Symcon** über MQTT.  
Es liest evcc-Datenpunkte ein und unterstützt bei geeigneten Variablen auch das aktive Setzen von Werten über MQTT.

## Inhaltsverzeichnis

1. [Voraussetzungen](#1-voraussetzungen)
2. [Enthaltene Module](#2-enthaltene-module)
3. [Installation](#3-installation)
4. [Konfiguration in Symcon](#4-konfiguration-in-symcon)
5. [Betrieb und Hinweise](#5-betrieb-und-hinweise)
6. [Lizenz](#6-lizenz)
7. [Spenden](#7-spenden)

## 1. Voraussetzungen

- Symcon ab Version 8.1
- laufende evcc-Installation mit aktivierter MQTT-Ausgabe
- in Symcon eine Instanz **MQTT Server**, an die evcc seine Daten sendet

## 2. Enthaltene Module

- evcc Standort (`evccSite`)
- evcc Ladepunkt (`evccLoadPointId`)
- evcc PV Anlage (`evccSitePvId`)
- evcc Batterie (`evccSiteBatteryId`)
- evcc extern geregeltes Gerät (`evccSiteAuxId`)
- evcc Fahrzeug (`evccVehicleName`)
- evcc Statistikdaten (`evccSiteStatistics`)
- evcc Prognosen (`evccSiteForecasts`)

## 3. Installation

1. Modul über den Module Store installieren.
2. In Symcon eine Instanz **MQTT Server** anlegen, falls noch keine vorhanden ist. Im zugehörigen **Server Socket** einen freien Port einstellen (z. B. `1883`).
3. In evcc diesen Server als MQTT-Broker eintragen: Adresse des Symcon-Rechners und der Port aus Schritt 2, Topic `evcc`.
4. Pro gewünschtem evcc-Bereich eine Instanz des entsprechenden Moduls anlegen. Symcon verbindet sie mit dem MQTT Server.

## 4. Konfiguration in Symcon

### MQTT Server (wichtig)

evcc verbindet sich direkt mit dem MQTT Server in Symcon, ein eigener Broker ist nicht nötig. Stimmen müssen:

- der Port im Server Socket des MQTT Servers und der in evcc eingetragene Port
- Benutzername und Passwort, falls im MQTT Server gesetzt
- das Topic-Präfix in evcc und in den Instanzen (siehe unten)

Wenn keine Daten ankommen, liegt die Ursache meist hier oder im Topic-Präfix.

### MQTT Topic Basis

Standardmäßig verwenden die Module das Topic-Präfix `evcc/`; das vollständige Standard-Topic je Modul steht unten.  
Ist in evcc ein anderes Präfix konfiguriert, muss `topic` in den Instanzen entsprechend angepasst werden.

### Modul-spezifische Felder

- **evcc Standort (Site)**  
  Feld: `topic`  
  Standard-`topic`: `evcc/site/`

- **evcc Ladepunkt (Loadpoint)**  
  Felder: `topic`, `loadPointId`  
  Standard-`topic`: `evcc/loadpoints/`  
  Beispiel: `loadPointId = 1` entspricht Topics wie `evcc/loadpoints/1/...`

- **evcc PV Anlage**  
  Felder: `topic`, `sitePvId`  
  Standard-`topic`: `evcc/site/pv/`  
  Beispiel: `sitePvId = 1` entspricht Topics wie `evcc/site/pv/1/...`

- **evcc Batterie**  
  Felder: `topic`, `siteBatteryId`  
  Standard-`topic`: `evcc/site/battery/devices/`  
  Beispiel: `siteBatteryId = 1` entspricht Topics wie `evcc/site/battery/devices/1/...`  
  Hinweis: Bis evcc 0.300.x lagen die Werte unter `evcc/site/battery/1/...` (ohne `devices/`) — siehe Migrationshinweis unten.

- **evcc Extern geregeltes Gerät (Aux)**  
  Felder: `topic`, `siteAuxId`  
  Standard-`topic`: `evcc/site/aux/`  
  Beispiel: `siteAuxId = 1` entspricht Topics wie `evcc/site/aux/1/...`

- **evcc Fahrzeug (Vehicle)**  
  Felder: `topic`, `vehicleName`  
  Standard-`topic`: `evcc/vehicles/`  
  Beispiel: `vehicleName = meinauto` entspricht Topics wie `evcc/vehicles/meinauto/...`  
  `vehicleName` ist der interne Name des Fahrzeugs in evcc, nicht sein Titel. Fahrzeuge, die in der evcc-Oberfläche angelegt wurden, heißen dort `db:<Nummer>` (z. B. `db:6`).

- **evcc Statistikdaten**  
  Felder: `topic`, `scope`  
  Standard-`topic`: `evcc/site/statistics/`  
  Unterstützte Werte für `scope`: `30d`, `365d`, `thisYear`, `total`

- **evcc Prognosen (Forecasts)**  
  Feld: `topic`  
  Standard: `evcc/site/forecast/`  
  Unterstützte Forecast-Daten: `co2`, `feedIn`, `grid`, `planner`, `temperature` sowie die Solarprognose (Skalierung, Zeitreihe und je `today`, `tomorrow`, `dayAfterTomorrow` der Ertrag in Wh und `complete` = vollständig/teilweise)

  Ab evcc 0.314 sendet evcc je Prognoseart eine Nachricht mit vollständigem JSON: `.../forecast/solar` enthält `scale`, `today`, `tomorrow`, `dayAfterTomorrow` und die Zeitreihe, die übrigen Topics enthalten die Ratenliste als `[[start, ende, wert], ...]` mit Zeitstempeln in Unix-Sekunden. Ältere evcc-Versionen, die diese Werte auf Einzeltopics (`solar/scale`, `solar/today/yield` …) verteilen, werden weiterhin unterstützt.

## 5. Betrieb und Hinweise

### Schreiben von Werten nach evcc

Diese Variablen lassen sich in Symcon schalten. Das Modul sendet den neuen Wert als MQTT-Befehl `<topic>/<Ident>/set` an evcc; die Variable zeigt ihn an, sobald evcc ihn bestätigt.

<!-- schaltbar:begin -->
| Modul | Schaltbare Variablen |
|---|---|
| evcc Standort (`evccSite`) | `prioritySoc` Priorisierung SoC, `bufferSoc` Puffer SoC, `bufferStartSoc` Puffer Start SoC, `residualPower` Sollarbeitspunkt Überschussregelung, `batteryDischargeControl` Batterie Entladen Steuerung, `batteryGridChargeLimit` Batterie Netzladen Preislimit |
| evcc Ladepunkt (`evccLoadPointId`) | `mode` Lademodus, `alwaysCharge` Dauerhaft laden (ab evcc 0.316), `limitSoc` Ladelimit SoC, `limitEnergy` Ladelimit Energie, `phasesConfigured` Phasen konfiguriert, `minCurrent` Min. Ladestrom, `maxCurrent` Max. Ladestrom, `smartCostLimit` Intelligente Preisgrenze, `enableThreshold` Einschaltgrenze, `disableThreshold` Abschaltgrenze |
<!-- schaltbar:end -->

Die übrigen Module zeigen nur Werte an.

### Typische Fehlerquellen

- falsches Topic-Präfix
- falsche ID (z. B. `loadPointId`, `sitePvId`)
- falscher Fahrzeugname (`vehicleName`)
- MQTT-Parent nicht verbunden oder falsch authentifiziert

### Migrationshinweis: Batterie-Topics ab evcc 0.301.0

Mit evcc **0.301.0** (Februar 2026, [PR #24887](https://github.com/evcc-io/evcc/pull/24887)) wurde die MQTT-Struktur der Batterie geändert:

- Die Daten je Batterie liegen nun unter `evcc/site/battery/devices/<n>/...` statt `evcc/site/battery/<n>/...`.
- Die aggregierten Batteriewerte (`power`, `soc`, `energy`, `capacity`) liegen nun verschachtelt unter `evcc/site/battery/...` statt als flache Keys `batteryPower`, `batterySoc` usw.

Auswirkungen:

- **evcc Batterie:** Bei bestehenden Instanzen das Feld `topic` einmalig von `evcc/site/battery/` auf `evcc/site/battery/devices/` anpassen. Neue Instanzen verwenden diesen Wert bereits als Standard.
- **evcc Standort:** Die aggregierten Batteriewerte werden ab dieser Modulversion automatisch aus der neuen, verschachtelten Struktur gelesen — keine Anpassung nötig.
- **PV und Aux** sind von der Änderung nicht betroffen.

### Hinweis: Lademodi ab evcc 0.316.0

Mit evcc **0.316.0** (September 2026, [PR #32490](https://github.com/evcc-io/evcc/pull/32490)) wurden die Lademodi neu geordnet:

- Aus „Nur PV" (`pv`) wurde „Smart" (`smart`).
- „Min + PV" (`minpv`) entfällt. Dasselbe Verhalten erreicht man jetzt mit „Smart" und der neuen Einstellung **Dauerhaft laden** (`alwaysCharge`: Aus, Dauerhaft, Bis Ladeende). „Bis Ladeende" setzt evcc beim Abstecken selbst zurück.

Das Modul **evcc Ladepunkt** erkennt die evcc-Version selbst: Sobald evcc die neuen Werte sendet, bietet der Lademodus Aus/Smart/Schnell an, und die Variable „Dauerhaft laden" wird angelegt. Mit älteren evcc-Versionen bleibt es bei Aus/Nur PV/Min + PV/Schnell. Eine Anpassung an der Instanz ist nicht nötig.

Skripte, die den Lademodus auf `pv` oder `minpv` vergleichen, müssen angepasst werden: evcc meldet ab 0.316 nur noch `smart`. Schalten mit den alten Werten nimmt evcc weiterhin an.

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## 7. Spenden

Die Nutzung des Moduls ist kostenfrei. Niemand sollte sich verpflichtet fühlen, aber wenn das Modul gefällt, freue ich mich über eine Spende.

<a href="https://www.paypal.me/bumaas" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>
