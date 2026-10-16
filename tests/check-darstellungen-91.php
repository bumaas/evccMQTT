<?php

declare(strict_types=1);

/**
 * Prüft jede Darstellung aus libs/Themes.php (auch die Varianten) so, wie Symcon 9.1 sie beim
 * Setzen prüft: Parameter müssen zur Darstellung UND zum Variablentyp passen, die Einträge von
 * Listenparametern (OPTIONS, INTERVALS) dürfen nur bekannte Unterfelder haben. 9.0 nahm alles an,
 * 9.1 lehnt ab; die Sync Remote Neustadt (9.0) → nuc (9.1) brach daran ab.
 *
 * Die Regeln stammen aus dem Mitschnitt tests/fixtures/darstellungen-9.1.json
 * (IPS_GetPresentation auf Testbox und nuc, 9.1 Rust). Gegenprobe: die beiden echten, von 9.1
 * abgelehnten Darstellungen aus Neustadt (tests/fixtures/darstellungen-neustadt-abgelehnt.json)
 * muss der Prüfer ebenfalls ablehnen.
 *
 * Aufruf: php tests/check-darstellungen-91.php
 */

require_once __DIR__ . '/harness.php';

$definitionen = [];
foreach (json_decode(file_get_contents(__DIR__ . '/fixtures/darstellungen-9.1.json'), true, 512, JSON_THROW_ON_ERROR)['darstellungen'] as $d) {
    $definitionen[strtoupper($d['guid'])] = $d['definition'];
}

/** Liefert die Beanstandungen einer Darstellung für einen Variablentyp; leer = 9.1 nimmt sie an. */
function beanstandungen(array $darstellung, int $typ, array $definitionen): array
{
    $guid = strtoupper((string) ($darstellung['PRESENTATION'] ?? ''));
    if (!isset($definitionen[$guid])) {
        return ["unbekannte Darstellung $guid"];
    }
    $def       = $definitionen[$guid];
    $erlaubt   = $def['presentationParameters'] ?? [];
    $typErlaubt = in_array($typ, $def['restrictions']['variableType'] ?? [$typ], true);
    if (!$typErlaubt) {
        return ["Darstellung {$def['caption']} gibt es für Variablentyp $typ nicht"];
    }
    foreach ($def['groups'] ?? [] as $gruppe) {
        if (in_array($typ, $gruppe['restrictions']['variableType'] ?? [], true)) {
            $erlaubt += $gruppe['presentationParameters'] ?? [];
        }
    }

    $befunde = [];
    foreach ($darstellung as $parameter => $wert) {
        if ($parameter === 'PRESENTATION') {
            continue;
        }
        if (!isset($erlaubt[$parameter])) {
            $befunde[] = "Parameter $parameter gibt es in {$def['caption']} für Typ $typ nicht";
            continue;
        }
        $unterfelder = $erlaubt[$parameter]['parameters'] ?? null;
        if ($unterfelder === null) {
            continue;
        }
        $eintraege = is_string($wert) ? json_decode($wert, true, 512, JSON_THROW_ON_ERROR) : $wert;
        foreach ($eintraege as $eintrag) {
            $fremd = array_diff(array_keys($eintrag), array_keys($unterfelder));
            if ($fremd !== []) {
                $befunde[] = "Eintrag in $parameter hat unbekannte Unterfelder: " . implode(', ', $fremd);
                break;
            }
        }
    }
    return $befunde;
}

echo "== Gegenprobe: echte, von 9.1 abgelehnte Darstellungen aus Neustadt\n";
$abgelehnt = json_decode(file_get_contents(__DIR__ . '/fixtures/darstellungen-neustadt-abgelehnt.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($abgelehnt['abgelehnt_von_91'] as $id => $fall) {
    $b = beanstandungen($fall['VariablePresentation'], $fall['variablentyp'], $definitionen);
    pruefe($b !== [], "#$id {$fall['ident']} wird abgelehnt (9.1: {$fall['fehler_91']})");
}

echo "== Darstellungen aus libs/Themes.php\n";
$themen = [
    \evccMQTT\Themes\Site::class,
    \evccMQTT\Themes\LoadPointId::class,
    \evccMQTT\Themes\VehicleName::class,
    \evccMQTT\Themes\SitePvId::class,
    \evccMQTT\Themes\SiteAuxId::class,
    \evccMQTT\Themes\SiteBatteryId::class,
    \evccMQTT\Themes\SiteStatistics::class,
    \evccMQTT\Themes\SiteForecasts::class,
];
foreach ($themen as $klasse) {
    $properties = (new ReflectionClass($klasse))->getStaticPropertyValue('properties');
    foreach ($properties as $ident => $eintrag) {
        $varianten = array_merge([null], array_keys($eintrag[\evccMQTT\Themes\IPS_PRESENTATION_VARIANTS] ?? []));
        foreach ($varianten as $variante) {
            $variable = $klasse::getIPSVariable((string) $ident, null, $variante);
            $darstellung = $variable[\evccMQTT\Themes\IPS_PRESENTATION];
            if ($darstellung === []) {
                continue;
            }
            $b    = beanstandungen($darstellung, $variable[\evccMQTT\Themes\IPS_VAR_TYPE], $definitionen);
            $name = (new ReflectionClass($klasse))->getShortName() . "::$ident" . ($variante !== null ? " ($variante)" : '');
            pruefe($b === [], $name . ($b === [] ? '' : ': ' . implode('; ', $b)));
        }
    }
}

ergebnis();
