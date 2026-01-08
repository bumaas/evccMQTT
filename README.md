![Red Square](/docs/pictures/logo.png)
# evccMQTT
Dieses Modul ermöglicht die vollständige Integration der Ladesteuerung **[evcc](https://evcc.io)** in **Symcon** auf Basis der MQTT-API.


## Inhaltverzeichnis

1. [Voraussetzungen](#1-voraussetzungen)
2. [Enthaltene Module](#2-enthaltene-module)
3. [Installation](#3-installation)
4. [Konfiguration in IP-Symcon](#4-konfiguration-in-ip-symcon)
5. [Lizenz](#5-lizenz)
6. [Spenden](#6-spenden)

## 1. Voraussetzungen

* mindestens IPS Version 8.1
* eine installierte Version von evcc

## 2. Enthaltene Module

* MQTT API
    * evcc Standort
    * evcc Ladepunkt
    * evcc PV Anlage
    * evcc Batterie
    * evcc Extern geregeltes Gerät
    * evcc Fahrzeug
    * evcc Statistikdaten

## 3. Installation

Über den IP-Symcon Module Store.

## 4. Konfiguration in IP-Symcon

Nach der Installation müssen die Instanzen der gewünschten Module angelegt werden. Alle Module benötigen eine Verbindung zum **MQTT Server** (Parent IO), auf dem evcc seine Daten publiziert.

### MQTT Topic Basis
Standardmäßig verwenden die Module das Topic-Präfix `evcc/`. Sollte dies in der `evcc.yaml` geändert worden sein, muss das Präfix in der Instanzkonfiguration entsprechend angepasst werden.

### Modul-spezifische Einstellungen
*   **evcc Standort (Site):** Abonniert allgemeine Daten wie Netzbezug, PV-Leistung etc..
*   **evcc Ladepunkt (Loadpoint):** Erfordert die Angabe der **Loadpoint ID**.
*   **evcc PV Anlage / Batterie / Externes Gerät:** Erfordert die jeweilige **ID** der Komponente, wie sie von evcc über MQTT nummeriert wird (z.B. `evcc/site/pv/1`).
*   **evcc Fahrzeug (Vehicle):** Erfordert den in der evcc-Konfiguration vergebenen **Namen** des Fahrzeugs (z.B. `evcc/vehicles/meinauto/`).

### Schalten von Funktionen
Einige Variablen (z.B. Lademodus, Phasenanzahl oder Ziel-SoC) unterstützen die **Standardaktion**. Wenn eine Änderung in IP-Symcon vorgenommen wird, sendet das Modul den entsprechenden Befehl via MQTT (z.B. an `.../set`) zurück an evcc.


## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## 6. Spenden

Die Nutzung des Moduls ist kostenfrei. Niemand sollte sich verpflichtet fühlen, aber wenn das Modul gefällt, dann freue ich mich über eine Spende.

<a href="https://www.paypal.me/bumaas" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

