<?php

declare(strict_types=1);

/**
 * Lademodus und „Dauerhaft laden" folgen der evcc-Version (Mode-Redesign in evcc 0.316.0):
 *  - bis 0.315: Modus Aus/Nur PV/Min + PV/Schnell, keine Variable alwaysCharge;
 *  - ab 0.316: Modus Aus/Smart/Schnell, Variable alwaysCharge (off/on/once), schaltbar.
 * Erkannt wird die Version an den Daten selbst (Topic alwaysCharge bzw. Modus smart/pv/minpv),
 * die Erkennung überdauert ApplyChanges.
 *
 * Aufruf: php tests/check-loadpoint-mode.php
 */

require_once __DIR__ . '/harness.php';

/** Werte der Aufzählungsoptionen einer Variable, in Reihenfolge. */
function optionen(object $inst, string $ident): array
{
    $vid = @IPS_GetObjectIDByIdent($ident, $inst->id());
    if (!$vid) {
        return [];
    }
    $p = IPS_GetVariable($vid)['VariablePresentation'];
    return array_column(json_decode($p['OPTIONS'] ?? '[]', true, 512, JSON_THROW_ON_ERROR), 'Caption', 'Value');
}

function hatVariable(object $inst, string $ident): bool
{
    return (bool) @IPS_GetObjectIDByIdent($ident, $inst->id());
}

echo "== evcc 0.315.0 (alte Modi)\n";
$alt = neueInstanz('evccLoadPointId');
spieleMitschnitt($alt, 'evcc-0.315.0');
pruefe(array_keys(optionen($alt, 'mode')) === ['off', 'pv', 'minpv', 'now'], 'Modus bietet off/pv/minpv/now an: ' . json_encode(array_keys(optionen($alt, 'mode'))));
pruefe($alt->werte()['mode'] === 'pv', 'Modus steht auf pv');
pruefe(!hatVariable($alt, 'alwaysCharge'), 'keine Variable alwaysCharge');

echo "== evcc 0.316.0 (Smart + Dauerhaft laden)\n";
$neu = neueInstanz('evccLoadPointId');
spieleMitschnitt($neu, 'evcc-0.316.0');
$modi = optionen($neu, 'mode');
pruefe(array_keys($modi) === ['off', 'smart', 'now'], 'Modus bietet off/smart/now an: ' . json_encode(array_keys($modi)));
pruefe(($modi['smart'] ?? '') === 'Smart', 'Option smart heißt „Smart"');
pruefe(($modi['now'] ?? '') === 'Schnell', 'Option now heißt wie in evcc „Schnell": ' . ($modi['now'] ?? '-'));
pruefe(($neu->werte()['mode'] ?? '') === 'smart', 'Modus steht auf smart');
pruefe(hatVariable($neu, 'alwaysCharge'), 'Variable alwaysCharge angelegt');
$ac = optionen($neu, 'alwaysCharge');
pruefe(array_keys($ac) === ['off', 'on', 'once'], 'alwaysCharge bietet off/on/once an: ' . json_encode(array_keys($ac)));
pruefe(($ac['on'] ?? '') === 'Dauerhaft' && ($ac['once'] ?? '') === 'Bis Ladeende', 'alwaysCharge-Optionen deutsch beschriftet: ' . json_encode($ac, JSON_UNESCAPED_UNICODE));
pruefe(($neu->werte()['alwaysCharge'] ?? null) === 'off', 'alwaysCharge steht auf off');
$vid = @IPS_GetObjectIDByIdent('alwaysCharge', $neu->id());
pruefe($vid && IPS_GetVariable($vid)['VariableAction'] > 0, 'alwaysCharge ist schaltbar');

IPS_ApplyChanges($neu->id());
pruefe(array_keys(optionen($neu, 'mode')) === ['off', 'smart', 'now'], 'nach ApplyChanges bleibt es bei off/smart/now');

echo "== Schalten\n";
$neu->gesendet = [];
$abbruch = '';
try {
    $neu->schalte('alwaysCharge', 'once');
    $neu->schalte('mode', 'smart');
} catch (Throwable $e) {
    $abbruch = $e->getMessage();
}
pruefe($abbruch === '', 'Schalten ohne Abbruch' . ($abbruch ? " - $abbruch" : ''));
$befehle = array_map(static fn(array $b): string => $b['Topic'] . ' = ' . $b['Payload'], $neu->gesendet);
pruefe($befehle === ['evcc/loadpoints/1/alwaysCharge/set = once', 'evcc/loadpoints/1/mode/set = smart'], 'Befehle: ' . json_encode($befehle));
$fehlerLogs = array_filter($neu->logs(), static fn(array $l): bool => $l['Type'] === KL_ERROR);
pruefe($fehlerLogs === [], 'keine Fehler im Log' . ($fehlerLogs ? ' - ' . implode(' | ', array_column($fehlerLogs, 'Message')) : ''));

echo "== Rückkehr zu einer alten evcc-Version\n";
empfange($neu, 'evcc/loadpoints/1/mode', 'minpv');
pruefe(array_keys(optionen($neu, 'mode')) === ['off', 'pv', 'minpv', 'now'], 'nach Modus minpv wieder off/pv/minpv/now');
pruefe(hatVariable($neu, 'alwaysCharge'), 'alwaysCharge bleibt stehen (kein Löschen samt Archiv)');

ergebnis();
