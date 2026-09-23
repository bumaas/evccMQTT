<?php

declare(strict_types=1);

/**
 * Spielt jeden echten evcc-Mitschnitt (tests/fixtures/evcc-*.json) in jedes Modul ein und prüft:
 *  - kein Abbruch und kein Fehler im Log - auch nicht bei den leeren Payloads, mit denen evcc
 *    beim Neustart retained Topics löscht;
 *  - Topic und Ident stimmen in der Schreibweise überein (evcc unterscheidet Groß/klein -
 *    `priority` ist nicht `Priority`, `feedIn` nicht `feedin`);
 *  - jeder Ident, für den evcc einen Wert gesendet hat, ist danach auch geschrieben worden.
 *
 * Aufruf: php tests/check-receive-data.php
 */

require_once __DIR__ . '/harness.php';

$mitschnitte = glob(__DIR__ . '/fixtures/evcc-*.json');
pruefe($mitschnitte !== [], 'mindestens ein Mitschnitt in tests/fixtures/');

foreach ($mitschnitte as $datei) {
    $name        = basename($datei, '.json');
    $nachrichten = json_decode(file_get_contents($datei), true, 512, JSON_THROW_ON_ERROR)['nachrichten'];

    // Instanz-Eigenschaften aus dem Mitschnitt: das erste Fahrzeug, Statistik-Zeitraum "total".
    $fahrzeug = '';
    foreach ($nachrichten as $m) {
        if (preg_match('#^evcc/vehicles/([^/]+)/title$#', $m['topic'], $t) === 1) {
            $fahrzeug = $t[1];
            break;
        }
    }
    $properties = ['evccVehicleName' => ['vehicleName' => $fahrzeug], 'evccSiteStatistics' => ['scope' => 'total']];

    foreach (MODULE as $modul) {
        echo "== $name / $modul\n";
        $theme  = 'evccMQTT\\Themes\\' . substr($modul, 4);
        $idents = ($theme . 'Ident')::idents();
        $inst   = neueInstanz($modul, $properties[$modul] ?? []);

        $erreicht = [];
        $abbruch  = '';
        foreach ($nachrichten as $m) {
            try {
                if (empfange($inst, $m['topic'], $m['payload'], $m['retain'])) {
                    $erreicht[] = $m;
                }
            } catch (Throwable $e) {
                $abbruch = sprintf('%s bei %s = "%s": %s', get_class($e), $m['topic'], substr($m['payload'], 0, 40), $e->getMessage());
                break;
            }
        }
        pruefe($abbruch === '', "$modul: Mitschnitt ohne Abbruch verarbeitet" . ($abbruch ? " - $abbruch" : ''));

        $fehlerLogs = array_filter($inst->logs(), static fn(array $l): bool => $l['Type'] === KL_ERROR);
        pruefe($fehlerLogs === [], "$modul: keine Fehler im Log" . ($fehlerLogs ? ' - ' . implode(' | ', array_column($fehlerLogs, 'Message')) : ''));

        $schreibweise = [];
        $gesendet     = [];
        foreach ($erreicht as $m) {
            $letztes = basename($m['topic']);
            foreach ($idents as $ident) {
                if ($letztes !== $ident && strcasecmp($letztes, $ident) === 0) {
                    $schreibweise[$m['topic']] = "{$m['topic']} ↔ Ident $ident";
                }
            }
            if ($m['payload'] !== '' && in_array($letztes, $idents, true)) {
                $gesendet[$letztes] = true;
            }
        }
        pruefe($schreibweise === [], "$modul: Topics und Idents gleich geschrieben" . ($schreibweise ? ' - ' . implode(', ', $schreibweise) : ''));

        $nieGeschrieben = array_diff(array_keys($gesendet), $inst->geschrieben());
        pruefe($nieGeschrieben === [], "$modul: jeder gesendete Wert geschrieben (" . count($gesendet) . ')' . ($nieGeschrieben ? ' - fehlt: ' . implode(', ', $nieGeschrieben) : ''));
    }
}

ergebnis();
