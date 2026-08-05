![evccMQTT Logo](/docs/pictures/logo.png)
# evccMQTT

[![Checks](https://github.com/bumaas/evccMQTT/actions/workflows/check.yml/badge.svg)](https://github.com/bumaas/evccMQTT/actions/workflows/check.yml)

Dieses Modul integriert **[evcc](https://evcc.io)** in **Symcon** über MQTT.  
Es liest evcc-Datenpunkte ein und unterstützt bei geeigneten Variablen auch das aktive Setzen von Werten über MQTT.

## Inhaltsverzeichnis

1. [Voraussetzungen](#1-voraussetzungen)
2. [Enthaltene Module](#2-enthaltene-module)
3. [Installation](#3-installation)
4. [Konfiguration in IP-Symcon](#4-konfiguration-in-ip-symcon)
5. [Betrieb und Hinweise](#5-betrieb-und-hinweise)
6. [Lizenz](#6-lizenz)
7. [Spenden](#7-spenden)

## 1. Voraussetzungen

- Symcon ab Version 8.1
- laufende evcc-Installation mit aktivierter MQTT-Ausgabe
- ein erreichbarer MQTT-Server (typisch derselbe Server/Broker, den evcc nutzt)
- in Symcon eingerichtetes und verbundenes MQTT-Client-IO zu diesem MQTT-Server

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
2. MQTT-Server bereitstellen bzw. vorhandene MQTT-Server verwenden.
3. In Symcon ein MQTT-Client-IO einrichten und mit diesem MQTT-Server verbinden.
4. Pro gewünschtem evcc-Bereich eine Instanz des entsprechenden Moduls anlegen.

## 4. Konfiguration in IP-Symcon

Alle Instanzen benötigen ein gültiges MQTT-Parent-IO.
Voraussetzung dafür ist ein erreichbarer MQTT-Server, zu dem das MQTT-Client-IO in Symcon verbunden ist.

### MQTT-Parent (wichtig)

Stelle sicher, dass im Parent-IO korrekt gesetzt sind:

- Broker-Adresse (Host/IP)
- Port
- Anmeldedaten (falls aktiviert)
- TLS/SSL-Einstellungen (falls genutzt)

Wenn keine Daten ankommen, liegt die Ursache meist hier oder im Topic-Präfix.

### MQTT Topic Basis

Standardmäßig verwenden die Module das Topic-Präfix `evcc/`.  
Wenn in deiner `evcc.yaml` ein anderes Präfix konfiguriert ist, muss `topic` in den Instanzen entsprechend angepasst werden.

### Modul-spezifische Felder

- **evcc Standort (Site)**  
  Feld: `topic`  
  Beispiel: `evcc/`

- **evcc Ladepunkt (Loadpoint)**  
  Felder: `topic`, `loadPointId`  
  Beispiel: `loadPointId = 1` entspricht Topics wie `evcc/loadpoints/1/...`

- **evcc PV Anlage**  
  Felder: `topic`, `sitePvId`  
  Beispiel: `sitePvId = 1` entspricht Topics wie `evcc/site/pv/1/...`

- **evcc Batterie**  
  Felder: `topic`, `siteBatteryId`  
  Standard-`topic`: `evcc/site/battery/devices/`  
  Beispiel: `siteBatteryId = 1` entspricht Topics wie `evcc/site/battery/devices/1/...`  
  Hinweis: Bis evcc 0.300.x lagen die Werte unter `evcc/site/battery/1/...` (ohne `devices/`) — siehe Migrationshinweis unten.

- **evcc Extern geregeltes Gerät (Aux)**  
  Felder: `topic`, `siteAuxId`  
  Beispiel: `siteAuxId = 1` entspricht Topics wie `evcc/site/aux/1/...`

- **evcc Fahrzeug (Vehicle)**  
  Felder: `topic`, `vehicleName`  
  Beispiel: `vehicleName = meinauto` entspricht Topics wie `evcc/vehicles/meinauto/...`

- **evcc Statistikdaten**  
  Felder: `topic`, `scope`  
  Unterstützte Werte für `scope`: `30d`, `365d`, `thisYear`, `total`

- **evcc Prognosen (Forecasts)**  
  Feld: `topic`  
  Standard: `evcc/site/forecast/`  
  Unterstützte Forecast-Daten: `co2`, `grid`, `planner`, `solar/scale` sowie `solar` für `today`, `tomorrow`, `dayAfterTomorrow` jeweils `yield` (Wh, Originalwert) und `complete` (vollständig/teilweise)

## 5. Betrieb und Hinweise

### Schreiben von Werten nach evcc

Einige Variablen unterstützen die Standardaktion in Symcon.  
Bei Änderung in Symcon publiziert das Modul den passenden MQTT-Set-Befehl (typisch auf `.../set`) zurück an evcc.

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

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## 7. Spenden

Die Nutzung des Moduls ist kostenfrei. Niemand sollte sich verpflichtet fühlen, aber wenn das Modul gefällt, freue ich mich über eine Spende.

<a href="https://www.paypal.me/bumaas" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>
