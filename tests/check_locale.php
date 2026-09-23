<?php

declare(strict_types=1);

/**
 * Prüft die Übersetzungs-Vollständigkeit der Module dieses Repos:
 *
 *  - Jeder caption/label/suffix-Text aus form.json braucht einen de-Schlüssel in der locale.json des Moduls.
 *  - Jeder Translate('...')-Text (module.php und form.json-Skripte, z. B. onClick) ebenso.
 *  - Jeder Variablenname (IPS_VAR_NAME) aus der Theme-Klasse des Moduls (libs/Themes.php)
 *    läuft durch $this->Translate() und braucht ebenfalls einen de-Schlüssel in der locale.json des Moduls.
 *  - Presentation-Texte (OPTIONS-Captions und PREFIX) aus libs/Themes.php werden über
 *    translatePresentationValue() mit libs/locale_profile.json übersetzt und brauchen dort einen
 *    de-Schlüssel. Rein numerische Captions sind ausgenommen; SUFFIXe sind Einheiten (" W", " kWh" …)
 *    und werden nicht geprüft.
 *  - Verwaiste Schlüssel werden nur gemeldet, nicht als Fehler gewertet
 *    (dynamische Nutzung wie zusammengesetzte Captions ist möglich).
 *
 * Exit-Code 1 bei fehlenden Übersetzungen (für die CI), sonst 0.
 * Aufruf: php tests/check_locale.php
 */

// IPS-Kernel-Konstanten stubben, damit libs/Themes.php ohne Symcon-Kernel lädt.
// Die konkreten Werte sind für die Prüfung irrelevant.
if (!defined('VARIABLETYPE_BOOLEAN')) {
    define('VARIABLETYPE_BOOLEAN', 0);
    define('VARIABLETYPE_INTEGER', 1);
    define('VARIABLETYPE_FLOAT', 2);
    define('VARIABLETYPE_STRING', 3);
}
foreach ([
             'VARIABLE_PRESENTATION_VALUE_PRESENTATION',
             'VARIABLE_PRESENTATION_ENUMERATION',
             'VARIABLE_PRESENTATION_SLIDER',
             'VARIABLE_PRESENTATION_DURATION',
             'VARIABLE_PRESENTATION_DATE_TIME',
             'VARIABLE_PRESENTATION_VALUE_INPUT',
             'VARIABLE_PRESENTATION_SWITCH',
         ] as $presentationConstant) {
    if (!defined($presentationConstant)) {
        define($presentationConstant, '{' . $presentationConstant . '}');
    }
}

$root = dirname(__DIR__);
require_once $root . '/libs/Themes.php';

$moduleThemes = [
    'evccSite'           => \evccMQTT\Themes\Site::class,
    'evccLoadPointId'    => \evccMQTT\Themes\LoadPointId::class,
    'evccVehicleName'    => \evccMQTT\Themes\VehicleName::class,
    'evccSitePvId'       => \evccMQTT\Themes\SitePvId::class,
    'evccSiteAuxId'      => \evccMQTT\Themes\SiteAuxId::class,
    'evccSiteBatteryId'  => \evccMQTT\Themes\SiteBatteryId::class,
    'evccSiteStatistics' => \evccMQTT\Themes\SiteStatistics::class,
    'evccSiteForecasts'  => \evccMQTT\Themes\SiteForecasts::class,
];

$fail = false;

foreach ($moduleThemes as $moduleDir => $themeClass) {
    $dir = $root . '/' . $moduleDir;
    echo "==== $moduleDir ====\n";

    $localeFile = $dir . '/locale.json';
    if (!is_file($localeFile)) {
        echo "keine locale.json vorhanden - übersprungen\n\n";
        continue;
    }
    $locale = json_decode(file_get_contents($localeFile), true, 512, JSON_THROW_ON_ERROR);
    $deKeys = array_keys($locale['translations']['de'] ?? []);

    $modulePhp = file_get_contents($dir . '/module.php');

    // 1) Alle caption/label/suffix-Texte aus form.json rekursiv einsammeln
    $formTexts = [];
    $formRaw   = '';
    $formFile  = $dir . '/form.json';
    if (is_file($formFile)) {
        $formRaw = file_get_contents($formFile);
        $form    = json_decode($formRaw, true, 512, JSON_THROW_ON_ERROR);
        collectFormTexts($form, '', $formTexts);
    }

    // 2) Translate-Aufrufe aus module.php und aus den Skripten in form.json (z. B. onClick)
    $translateTexts = collectTranslateTexts($modulePhp . "\n" . $formRaw);

    // 3) Variablennamen (IPS_VAR_NAME) aus der Theme-Klasse des Moduls
    $varNames = collectThemeVarNames($themeClass);

    // Fehlend: form.json-Text ohne de-Schlüssel
    $missingForm = [];
    foreach ($formTexts as $text => $paths) {
        if (!in_array($text, $deKeys, true)) {
            $missingForm[$text] = $paths[0];
        }
    }

    // Fehlend: Translate-Text ohne de-Schlüssel
    $missingPhp = [];
    foreach (array_keys($translateTexts) as $text) {
        if (!in_array($text, $deKeys, true)) {
            $missingPhp[] = $text;
        }
    }

    // Fehlend: Variablenname ohne de-Schlüssel
    $missingVarNames = [];
    foreach ($varNames as $name => $ident) {
        if (!in_array($name, $deKeys, true)) {
            $missingVarNames[$name] = $ident;
        }
    }

    // Verwaist: de-Schlüssel weder form.json-Text noch Translate-Text noch Variablenname
    $orphans = [];
    foreach ($deKeys as $key) {
        if (!isset($formTexts[$key]) && !isset($translateTexts[$key]) && !isset($varNames[$key])) {
            $inLiteral = str_contains($modulePhp, $key) || ($formRaw !== '' && str_contains($formRaw, $key));
            $orphans[] = [$key, $inLiteral ? 'kommt wörtlich in module.php/form.json vor' : 'nirgends gefunden'];
        }
    }

    echo 'form.json Texte (unique): ' . count($formTexts) . "\n";
    echo 'locale de-Schlüssel:      ' . count($deKeys) . "\n";
    echo 'Translate-Texte:          ' . count($translateTexts) . "\n";
    echo 'Theme-Variablennamen:     ' . count($varNames) . "\n";
    echo 'Sprachen in locale.json:  ' . implode(', ', array_keys($locale['translations'] ?? [])) . "\n\n";

    echo 'FEHLENDE ÜBERSETZUNGEN (form.json -> kein de-Schlüssel): ' . count($missingForm) . "\n";
    foreach ($missingForm as $text => $path) {
        echo "  - \"$text\"  ($path)\n";
    }
    echo 'FEHLENDE ÜBERSETZUNGEN (Translate -> kein de-Schlüssel): ' . count($missingPhp) . "\n";
    foreach ($missingPhp as $text) {
        echo "  - \"$text\"\n";
    }
    echo 'FEHLENDE ÜBERSETZUNGEN (Variablenname -> kein de-Schlüssel): ' . count($missingVarNames) . "\n";
    foreach ($missingVarNames as $name => $ident) {
        echo "  - \"$name\"  (Ident: $ident)\n";
    }
    echo 'VERWAISTE de-SCHLÜSSEL (nur Hinweis, kein Fehler): ' . count($orphans) . "\n";
    foreach ($orphans as [$key, $note]) {
        echo "  - \"$key\"  [$note]\n";
    }
    echo "\n";

    if ($missingForm !== [] || $missingPhp !== [] || $missingVarNames !== []) {
        $fail = true;
    }
}

// --- Presentation-Texte gegen libs/locale_profile.json ---
echo "==== libs/locale_profile.json (Presentation-Texte) ====\n";

$profileLocale = json_decode(file_get_contents($root . '/libs/locale_profile.json'), true, 512, JSON_THROW_ON_ERROR);
$profileDeKeys = array_keys($profileLocale['translations']['de'] ?? []);

$presentationTexts = [];
foreach (array_unique(array_values($moduleThemes)) as $themeClass) {
    collectPresentationTexts($themeClass, $presentationTexts);
}

$missingPresentation = [];
foreach ($presentationTexts as $text => $sources) {
    if (!in_array($text, $profileDeKeys, true)) {
        $missingPresentation[$text] = $sources[0];
    }
}

$profileOrphans = [];
foreach ($profileDeKeys as $key) {
    if (!isset($presentationTexts[$key])) {
        $profileOrphans[] = $key;
    }
}

echo 'Presentation-Texte (unique):    ' . count($presentationTexts) . "\n";
echo 'locale_profile de-Schlüssel:    ' . count($profileDeKeys) . "\n\n";

echo 'FEHLENDE ÜBERSETZUNGEN (Presentation -> kein de-Schlüssel): ' . count($missingPresentation) . "\n";
foreach ($missingPresentation as $text => $source) {
    echo "  - \"$text\"  ($source)\n";
}
echo 'VERWAISTE de-SCHLÜSSEL (nur Hinweis, kein Fehler): ' . count($profileOrphans) . "\n";
foreach ($profileOrphans as $key) {
    echo "  - \"$key\"\n";
}
echo "\n";

if ($missingPresentation !== []) {
    $fail = true;
}

if ($fail) {
    echo "FEHLER: Es fehlen Übersetzungen (siehe oben).\n";
    exit(1);
}

echo "OK: Alle Texte sind übersetzt.\n";

function collectFormTexts(array $node, string $path, array &$formTexts): void
{
    foreach ($node as $k => $v) {
        $p = $path . (is_int($k) ? '[' . $k . ']' : '.' . $k);
        if (in_array($k, ['caption', 'label', 'suffix'], true) && is_string($v) && $v !== '') {
            $formTexts[$v][] = $p;
        }
        if (is_array($v)) {
            collectFormTexts($v, $p, $formTexts);
        }
    }
}

/**
 * Sammelt die Argumente aller Translate('...')-/Translate("...")-Aufrufe im übergebenen Quelltext.
 *
 * @return array<string, true> Texte als Schlüssel (dedupliziert)
 */
function collectTranslateTexts(string $code): array
{
    $texts = [];
    foreach (
        [
            '/->Translate\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/s',
            '/->Translate\(\s*"((?:[^"\\\\]|\\\\.)*)"/s'
        ] as $pattern
    ) {
        if (preg_match_all($pattern, $code, $matches)) {
            foreach ($matches[1] as $text) {
                $texts[stripcslashes($text)] = true;
            }
        }
    }

    return $texts;
}

/**
 * Liest die protected static $properties einer Theme-Klasse per Reflection aus.
 */
function getThemeProperties(string $themeClass): array
{
    $reflection = new ReflectionProperty($themeClass, 'properties');

    return $reflection->getValue();
}

/**
 * Sammelt die Variablennamen (IPS_VAR_NAME, Fallback: Ident) einer Theme-Klasse.
 *
 * @return array<string, string> Name => Ident
 */
function collectThemeVarNames(string $themeClass): array
{
    $names = [];
    foreach (getThemeProperties($themeClass) as $ident => $property) {
        $names[$property[\evccMQTT\Themes\IPS_VAR_NAME] ?? $ident] = $ident;
    }

    return $names;
}

/**
 * Sammelt die über translatePresentationValue() laufenden Texte (OPTIONS-Captions, PREFIX)
 * einer Theme-Klasse. Rein numerische Captions und Leerstrings werden übersprungen.
 *
 * @param array<string, string[]> $texts Text => Fundstellen
 */
function collectPresentationTexts(string $themeClass, array &$texts): void
{
    $shortName = (new ReflectionClass($themeClass))->getShortName();
    foreach (getThemeProperties($themeClass) as $ident => $property) {
        // Hauptdarstellung und alternative Darstellungen (IPS_PRESENTATION_VARIANTS, z. B. die
        // Moduswahl ab evcc 0.316) laufen gleichermaßen durch translatePresentationValue().
        $presentations = ['' => $property[\evccMQTT\Themes\IPS_PRESENTATION] ?? []];
        foreach ($property[\evccMQTT\Themes\IPS_PRESENTATION_VARIANTS] ?? [] as $variante => $p) {
            $presentations[" ($variante)"] = $p;
        }
        foreach ($presentations as $zusatz => $presentation) {
            if (isset($presentation['PREFIX']) && $presentation['PREFIX'] !== '') {
                $texts[$presentation['PREFIX']][] = "$shortName.$ident$zusatz PREFIX";
            }
            foreach ($presentation['OPTIONS'] ?? [] as $option) {
                $caption = $option['Caption'] ?? '';
                if ($caption === '' || is_numeric($caption)) {
                    continue;
                }
                $texts[$caption][] = "$shortName.$ident$zusatz OPTIONS";
            }
        }
    }
}
