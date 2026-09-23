<?php

declare(strict_types=1);

/**
 * Idents, die bis build 40 anders geschrieben waren als das evcc-Topic, werden beim
 * nächsten ApplyChanges umbenannt: Die bestehende Variable bleibt erhalten (gleiche
 * Objekt-ID, also auch ihr Archiv), und es entsteht keine zweite.
 *
 * Aufruf: php tests/check-ident-migration.php
 */

require_once __DIR__ . '/harness.php';

$faelle = [
    ['evccLoadPointId', 'Priority', 'priority'],
    ['evccSiteForecasts', 'feedin', 'feedIn'],
];

foreach ($faelle as [$modul, $alt, $neu]) {
    echo "== $modul: $alt -> $neu\n";
    $inst = neueInstanz($modul);

    // Stand einer Installation vor build 41 nachstellen: die Variable trägt den alten Ident.
    // (Die Variable wird über beide Schreibweisen gesucht, damit der Test auch gegen den
    // alten Code rot statt fatal endet.)
    $vid = 0;
    foreach (IPS_GetChildrenIDs($inst->id()) as $id) {
        if (in_array(IPS_GetObject($id)['ObjectIdent'], [$alt, $neu], true)) {
            $vid = $id;
        }
    }
    pruefe($vid > 0, "$modul: Variable $neu/$alt vorhanden");
    IPS_SetIdent($vid, $alt);
    $vorher = count(IPS_GetChildrenIDs($inst->id()));

    IPS_ApplyChanges($inst->id());

    $idents = array_map(static fn(int $id): string => IPS_GetObject($id)['ObjectIdent'], IPS_GetChildrenIDs($inst->id()));
    pruefe(IPS_GetObject($vid)['ObjectIdent'] === $neu, "$modul: Variable #$vid trägt jetzt den Ident $neu");
    pruefe(!in_array($alt, $idents, true), "$modul: kein Objekt mehr mit Ident $alt");
    pruefe(count(array_keys($idents, $neu, true)) === 1, "$modul: genau eine Variable mit Ident $neu");
    pruefe(count($idents) === $vorher, "$modul: Zahl der Variablen unverändert ($vorher)");
}

ergebnis();
