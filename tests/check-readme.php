<?php

declare(strict_types=1);

/**
 * Hält die README beim Code: Alles, was sich aus Modulen, Formularen und libs/Themes.php
 * ableiten lässt, muss dort stehen - und darf nicht veraltet sein.
 *
 *  - jedes Modul ist unter „Enthaltene Module" genannt;
 *  - jedes Formularfeld und jede Auswahloption (z. B. Statistik-Zeiträume) steht in Backticks;
 *  - das Standard-Topic jedes Moduls steht in Backticks;
 *  - jede Prognoseart (Ident ohne „_" in SiteForecasts) steht in Backticks, in evccs Schreibweise;
 *  - die Tabelle zwischen <!-- schaltbar:begin --> und <!-- schaltbar:end --> nennt je Modul genau
 *    die Variablen, die Themes.php als schaltbar kennzeichnet;
 *  - kein „IP-Symcon" (Produktname seit 2024 „Symcon").
 *
 * Was sich nicht ableiten lässt (Verhaltensänderungen, Migrationshinweise), deckt dieser Test
 * nicht ab - dafür bleibt die Durchsicht vor jeder Beta.
 *
 * Aufruf: php tests/check-readme.php
 */

require_once __DIR__ . '/harness.php';

$readme = file_get_contents(dirname(__DIR__) . '/README.md');

/** Steht der Text in Backticks in der README? */
function inBackticks(string $readme, string $text): bool
{
    return str_contains($readme, '`' . $text . '`');
}

pruefe(!str_contains($readme, 'IP-Symcon'), 'kein „IP-Symcon" (Produktname: Symcon)');

// Abschnitt „Enthaltene Module"
preg_match('/## \d+\. Enthaltene Module(.*?)\n## /s', $readme, $abschnitt);
foreach (MODULE as $modul) {
    pruefe(str_contains($abschnitt[1] ?? '', "(`$modul`)"), "$modul unter „Enthaltene Module\" genannt");
}

foreach (MODULE as $modul) {
    $form = json_decode(file_get_contents(dirname(__DIR__) . "/$modul/form.json"), true, 512, JSON_THROW_ON_ERROR);
    foreach ($form['elements'] as $element) {
        if (!isset($element['name'])) {
            continue;
        }
        pruefe(inBackticks($readme, $element['name']), "$modul: Formularfeld `{$element['name']}` beschrieben");
        foreach ($element['options'] ?? [] as $option) {
            pruefe(inBackticks($readme, (string) $option['value']), "$modul: Option `{$option['value']}` von `{$element['name']}` beschrieben");
        }
    }
    $inst  = neueInstanz($modul);
    $topic = IPS_GetProperty($inst->id(), 'topic');
    pruefe(inBackticks($readme, $topic), "$modul: Standard-Topic `$topic` genannt");
}

foreach (evccMQTT\Themes\SiteForecastsIdent::idents() as $ident) {
    if (!str_contains($ident, '_')) {
        pruefe(inBackticks($readme, $ident), "Prognoseart `$ident` genannt");
    }
}

// Tabelle der schaltbaren Variablen
$tabelle = preg_match('/<!-- schaltbar:begin -->(.*?)<!-- schaltbar:end -->/s', $readme, $t) === 1 ? $t[1] : '';
pruefe($tabelle !== '', 'Tabelle der schaltbaren Variablen vorhanden');
$gelistet = [];
foreach (explode("\n", $tabelle) as $zeile) {
    if (preg_match('/\(`(evcc\w+)`\)/', $zeile, $m) === 1) {
        preg_match_all('/`(\w+)`/', substr($zeile, strpos($zeile, $m[0]) + strlen($m[0])), $idents);
        $gelistet[$m[1]] = $idents[1];
    }
}
foreach (MODULE as $modul) {
    $theme = 'evccMQTT\\Themes\\' . substr($modul, 4);
    $soll  = array_values(array_filter(($theme . 'Ident')::idents(), static fn(string $i): bool => $theme::propertyHasAction($i)));
    $ist   = $gelistet[$modul] ?? [];
    sort($soll);
    sort($ist);
    $fehlt   = array_diff($soll, $ist);
    $zuviel  = array_diff($ist, $soll);
    $meldung = ($fehlt ? ' - fehlt: ' . implode(', ', $fehlt) : '') . ($zuviel ? ' - nicht schaltbar: ' . implode(', ', $zuviel) : '');
    if ($soll === [] && !isset($gelistet[$modul])) {
        continue; // Module ohne schaltbare Variablen brauchen keine Zeile
    }
    pruefe($fehlt === [] && $zuviel === [], "$modul: schaltbare Variablen in der README wie in Themes.php" . $meldung);
}

ergebnis();
