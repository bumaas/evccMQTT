<?php

declare(strict_types=1);

/**
 * Für jedes Modul der Bibliothek: Eine frisch angelegte Instanz registriert genau die
 * Variablen, die libs/Themes.php für sie vorsieht - mit Typ, Darstellung und Aktion wie dort
 * beschrieben, eindeutigen Idents und lückenlosen Positionen.
 *
 * Aufruf: php tests/check-variable-registration.php
 */

require_once __DIR__ . '/harness.php';

// Jedes Modulverzeichnis muss in der Harness stehen, sonst bliebe ein neues Modul ungeprüft.
foreach (glob(dirname(__DIR__) . '/*/module.json') as $datei) {
    $modul = basename(dirname($datei));
    pruefe(in_array($modul, MODULE, true), "$modul ist in tests/harness.php (MODULE) eingetragen");
}

foreach (MODULE as $modul) {
    echo "== $modul\n";
    $theme  = 'evccMQTT\\Themes\\' . substr($modul, 4);
    $enum   = $theme . 'Ident';
    $m      = neueInstanz($modul);

    $variablen = [];
    foreach (IPS_GetChildrenIDs($m->id()) as $vid) {
        $obj = IPS_GetObject($vid);
        if ($obj['ObjectType'] === 2 /* Variable */) {
            $variablen[$obj['ObjectIdent']] = ['obj' => $obj, 'var' => IPS_GetVariable($vid)];
        }
    }

    $erwartet = $enum::idents();
    pruefe(count($erwartet) === count(array_unique($erwartet)), "$modul: Idents in Themes.php sind eindeutig");
    $fehlend  = array_diff($erwartet, array_keys($variablen));
    $zuviel   = array_diff(array_keys($variablen), $erwartet);
    pruefe($fehlend === [], "$modul: alle " . count($erwartet) . ' Idents registriert' . ($fehlend ? ' - fehlt: ' . implode(', ', $fehlend) : ''));
    pruefe($zuviel === [], "$modul: keine Variable außerhalb von Themes.php" . ($zuviel ? ' - zusätzlich: ' . implode(', ', $zuviel) : ''));

    $abweichend = [];
    foreach ($erwartet as $ident) {
        if (!isset($variablen[$ident])) {
            continue;
        }
        $soll = $theme::getIPSVariable($ident);
        $ist  = $variablen[$ident]['var'];
        if ($ist['VariableType'] !== $soll['VarType']) {
            $abweichend[] = "$ident: Typ {$ist['VariableType']} statt {$soll['VarType']}";
        }
        if ($ist['VariablePresentation'] !== $soll['Presentation']) {
            $abweichend[] = "$ident: Darstellung weicht ab";
        }
        if (($ist['VariableAction'] > 0) !== $soll['VarAction']) {
            $abweichend[] = "$ident: Aktion " . ($soll['VarAction'] ? 'fehlt' : 'unerwartet');
        }
        if (($soll['Presentation']['PRESENTATION'] ?? '') === '') {
            $abweichend[] = "$ident: keine Darstellung";
        }
    }
    pruefe($abweichend === [], "$modul: Typ, Darstellung und Aktion wie in Themes.php" . ($abweichend ? ' - ' . implode('; ', $abweichend) : ''));

    $positionen = array_map(static fn(array $v): int => $v['obj']['ObjectPosition'], array_values($variablen));
    sort($positionen);
    pruefe($positionen === range(1, count($positionen)), "$modul: Positionen fortlaufend 1.." . count($positionen));
}

ergebnis();
