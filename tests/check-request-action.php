<?php

declare(strict_types=1);

/**
 * Jede Variable, die Themes.php als schaltbar kennzeichnet, erzeugt beim Schalten genau einen
 * MQTT-Befehl <Basistopic>/<Ident>/set, den evcc auch versteht: Das Topic steht in evccs
 * Setter-Liste, und die Payload hat das Format, das der Setter dort parst. Nicht schaltbare
 * Variablen senden nichts.
 *
 * Aufruf: php tests/check-request-action.php
 */

require_once __DIR__ . '/harness.php';

// Setter aus evcc server/mqtt.go (0.315.0: listenSiteSetters / listenLoadpointSetters),
// Ident => Payload-Art, wie der jeweilige Setter sie parst.
const EVCC_SETTER = [
    'evccSite' => [
        'bufferSoc'                => 'float',
        'bufferStartSoc'           => 'float',
        'batteryDischargeControl'  => 'bool',
        'batteryGridDischarge'     => 'bool',
        'prioritySoc'              => 'float',
        'residualPower'            => 'float',
        'gridExportLimit'          => 'float',
        'solarAdjusted'            => 'bool',
        'smartCostLimit'           => 'float',
        'smartFeedInPriorityLimit' => 'float',
        'batteryGridChargeLimit'   => 'float',
        'batteryMode'              => 'batteryMode',
    ],
    'evccLoadPointId' => [
        'mode'                     => 'chargeMode',
        'phasesConfigured'         => 'int',
        'limitSoc'                 => 'int',
        'minSoc'                   => 'int',
        'priority'                 => 'int',
        'minCurrent'               => 'float',
        'maxCurrent'               => 'float',
        'limitEnergy'              => 'float',
        'enableThreshold'          => 'float',
        'disableThreshold'         => 'float',
        'smartCostLimit'           => 'float',
        'smartFeedInPriorityLimit' => 'float',
        'batteryBoost'             => 'bool',
        'batteryBoostLimit'        => 'int',
    ],
];

const FORMAT = [
    'bool'        => '/^(true|false)$/',
    'int'         => '/^-?\d+$/',
    'float'       => '/^-?\d+(\.\d+)?$/',
    'chargeMode'  => '/^(off|now|minpv|pv)$/',
    'batteryMode' => '/^(unknown|normal|hold|charge)$/',
];

/** Ein gültiger Schaltwert je Variable: erste Option einer Aufzählung, sonst ein typischer Wert. */
function testwert(array $variable): mixed
{
    $optionen = json_decode($variable['Presentation']['OPTIONS'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    if ($optionen !== []) {
        return end($optionen)['Value'];
    }
    return match ($variable['VarType']) {
        VARIABLETYPE_BOOLEAN => true,
        VARIABLETYPE_INTEGER => 3,
        VARIABLETYPE_FLOAT   => 80.0,
        default              => 'x',
    };
}

$basis = [
    'evccLoadPointId' => 'evcc/loadpoints/1',
    'evccSite'        => 'evcc/site',
];

foreach (MODULE as $modul) {
    echo "== $modul\n";
    $theme = 'evccMQTT\\Themes\\' . substr($modul, 4);
    foreach (($theme . 'Ident')::idents() as $ident) {
        $variable = $theme::getIPSVariable($ident);
        $inst     = neueInstanz($modul);
        $wert     = testwert($variable);
        $inst->schalte($ident, $wert);
        $fehlerLogs = array_column(array_filter($inst->logs(), static fn(array $l): bool => $l['Type'] === KL_ERROR), 'Message');

        if (!$variable['VarAction']) {
            if ($inst->gesendet !== []) {
                pruefe(false, "$modul/$ident: nicht schaltbar, sendet aber " . json_encode($inst->gesendet));
            }
            continue;
        }

        $art = EVCC_SETTER[$modul][$ident] ?? null;
        pruefe($art !== null, "$modul/$ident: evcc kennt einen Setter für dieses Topic");
        pruefe($fehlerLogs === [], "$modul/$ident: Schalten ohne Fehler im Log" . ($fehlerLogs ? ' - ' . implode(' | ', $fehlerLogs) : ''));
        pruefe(count($inst->gesendet) === 1, "$modul/$ident: genau ein MQTT-Befehl (" . count($inst->gesendet) . ')');
        if (count($inst->gesendet) !== 1) {
            continue;
        }
        $befehl = $inst->gesendet[0];
        pruefe($befehl['Topic'] === "{$basis[$modul]}/$ident/set", "$modul/$ident: Topic {$befehl['Topic']}");
        if ($art !== null) {
            pruefe(preg_match(FORMAT[$art], $befehl['Payload']) === 1, "$modul/$ident: Payload \"{$befehl['Payload']}\" passt zum evcc-Setter ($art)");
        }
        pruefe($befehl['Retain'] === false, "$modul/$ident: nicht retained");
    }
}

ergebnis();
