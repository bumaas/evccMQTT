<?php

declare(strict_types=1);

/*
 * Gemeinsamer Testrahmen: bindet alle Module der Bibliothek an den offiziellen Kernel-Stub
 * (symcon/SymconStubs, Submodul tests/stubs, gepinnt auf bf2950f).
 *
 * Einbinden mit require_once __DIR__ . '/harness.php'; Instanzen über neueInstanz().
 * MQTT-Nachrichten gehen über empfange() bzw. spieleMitschnitt() in die Instanz - mit
 * demselben ReceiveDataFilter wie im Echtbetrieb. Was ein Modul veröffentlicht, zeichnet
 * die Netz-Naht SendDataToParent() in $gesendet auf.
 */

require_once __DIR__ . '/stubs/autoload.php';

// PHP-Warnungen/-Notices sollen Tests abbrechen, nicht still durchlaufen.
set_error_handler(static function (int $nr, string $text, string $datei, int $zeile): bool {
    if (!(error_reporting() & $nr)) {
        return false;
    }
    if ($nr & (E_USER_ERROR | E_USER_WARNING | E_WARNING | E_NOTICE | E_USER_NOTICE | E_DEPRECATED)) {
        throw new ErrorException($text, 0, $nr, $datei, $zeile);
    }
    return false;
});

const MODULE = [
    'evccLoadPointId',
    'evccSite',
    'evccSiteAuxId',
    'evccSiteBatteryId',
    'evccSiteForecasts',
    'evccSitePvId',
    'evccSiteStatistics',
    'evccVehicleName',
];

foreach (MODULE as $modul) {
    require_once dirname(__DIR__) . '/' . $modul . '/module.php';
}

/** Netz-Naht und Beobachtungspunkte, gemeinsam für alle Module. */
trait EvccHarness
{
    /** @var list<array{0: string, 1: mixed}> jedes SetValue */
    public array $writes = [];
    /** @var list<array{Topic: string, Payload: string, Retain: bool}> jede veröffentlichte MQTT-Nachricht */
    public array $gesendet = [];

    protected function SetValue(string $Ident, mixed $Value): bool
    {
        $ok             = parent::SetValue($Ident, $Value);
        $this->writes[] = [$Ident, $Value];
        return $ok;
    }

    /** @var list<array{0: string, 1: string}> jedes SendDebug (Nachricht, Daten) */
    public array $debug = [];

    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        $this->debug[] = [$Message, $Data];
        return true;
    }

    /** Topics, die das Modul als unerwartet verworfen hat (SendDebug '…::HINT'). */
    public function unerwartet(): array
    {
        $topics = [];
        foreach ($this->debug as [$nachricht, $daten]) {
            if (str_ends_with($nachricht, '::HINT') && str_starts_with($daten, 'unexpected topic: ')) {
                $topics[] = substr($daten, strlen('unexpected topic: '));
            }
        }
        return array_values(array_unique($topics));
    }

    protected function SendDataToParent(string $Data): string
    {
        $d                = json_decode($Data, true, 512, JSON_THROW_ON_ERROR);
        $this->gesendet[] = ['Topic' => $d['Topic'], 'Payload' => hex2bin($d['Payload']), 'Retain' => $d['Retain']];
        return '';
    }

    /** InstanceID ist in IPSModuleStrict geschützt. */
    public function id(): int
    {
        return $this->InstanceID;
    }

    /** Alle Variablenwerte der Instanz (Ident -> Wert), typgetreu aus dem Kernel-Stub. */
    public function werte(): array
    {
        $werte = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $vid) {
            $obj = IPS_GetObject($vid);
            if ($obj['ObjectType'] === 2 /* Variable */) {
                $werte[$obj['ObjectIdent']] = GetValue($vid);
            }
        }
        return $werte;
    }

    /** Idents, die seit dem Anlegen mindestens einmal geschrieben wurden. */
    public function geschrieben(): array
    {
        return array_values(array_unique(array_column($this->writes, 0)));
    }

    /** @return list<array{Message: string, Type: int}> */
    public function logs(): array
    {
        return IPS\LogServer::getLogMessages((string) $this->InstanceID);
    }

    /** Öffentlicher Zugang zu RequestAction, wie ihn der Kernel beim Schalten einer Variable nutzt. */
    public function schalte(string $Ident, mixed $Wert): void
    {
        $this->RequestAction($Ident, $Wert);
    }
}

final class evccLoadPointIdHarness extends evccLoadPointId { use EvccHarness; }
final class evccSiteHarness extends evccSite { use EvccHarness; }
final class evccSiteAuxIdHarness extends evccSiteAuxId { use EvccHarness; }
final class evccSiteBatteryIdHarness extends evccSiteBatteryId { use EvccHarness; }
final class evccSiteForecastsHarness extends evccSiteForecasts { use EvccHarness; }
final class evccSitePvIdHarness extends evccSitePvId { use EvccHarness; }
final class evccSiteStatisticsHarness extends evccSiteStatistics { use EvccHarness; }
final class evccVehicleNameHarness extends evccVehicleName { use EvccHarness; }

/**
 * Legt eine Instanz im Kernel-Stub an (Create + ApplyChanges laufen in createInstance).
 * $properties werden danach gesetzt und per IPS_ApplyChanges übernommen.
 */
function neueInstanz(string $modul, array $properties = []): object
{
    $info = json_decode(file_get_contents(dirname(__DIR__) . '/' . $modul . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
    $id   = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, [
        'ModuleID'   => $info['id'],
        'ModuleName' => $info['name'],
        'ModuleType' => $info['type'],
        'Class'      => $modul . 'Harness',
    ]);
    if ($properties !== []) {
        foreach ($properties as $name => $wert) {
            IPS_SetProperty($id, $name, $wert);
        }
        IPS_ApplyChanges($id);
    }
    return IPS\InstanceManager::getInstanceInterface($id);
}

/**
 * Stellt eine MQTT-Nachricht so zu, wie der MQTT-Server sie an seine Kinder gibt:
 * nur wenn der ReceiveDataFilter der Instanz auf das JSON passt.
 * Rückgabe: true, wenn die Nachricht die Instanz erreicht hat.
 */
function empfange(object $instanz, string $topic, string $payload, bool $retain = true): bool
{
    $json = json_encode([
        'DataID'           => '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}',
        'PacketType'       => 3,
        'QualityOfService' => 0,
        'Retain'           => $retain,
        'Topic'            => $topic,
        'Payload'          => bin2hex($payload),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $filter = $instanz->GetReceiveDataFilter();
    if ($filter !== '' && preg_match('/' . $filter . '/', $json) !== 1) {
        return false;
    }
    $instanz->ReceiveData($json);
    return true;
}

/**
 * Spielt einen committeten Mitschnitt (tests/fixtures/<name>.json) in der aufgezeichneten
 * Reihenfolge in die Instanz ein. Rückgabe: Zahl der Nachrichten, die sie erreicht haben.
 */
function spieleMitschnitt(object $instanz, string $name): int
{
    $datei = __DIR__ . '/fixtures/' . $name . '.json';
    if (!is_file($datei)) {
        throw new RuntimeException("Mitschnitt fehlt: $datei");
    }
    $n = 0;
    foreach (json_decode(file_get_contents($datei), true, 512, JSON_THROW_ON_ERROR)['nachrichten'] as $m) {
        $n += empfange($instanz, $m['topic'], $m['payload'], $m['retain']) ? 1 : 0;
    }
    return $n;
}

$pruefungen = 0;
$fehler     = [];
function pruefe(bool $ok, string $text): void
{
    global $pruefungen, $fehler;
    $pruefungen++;
    if (!$ok) {
        $fehler[] = $text;
    }
    echo ($ok ? '  ok   ' : '  FEHL ') . $text . "\n";
}

/** Schlusszeile und Exit-Code - Format ist Pflicht (K2), rotgruen.php parst genau das. */
function ergebnis(): never
{
    global $pruefungen, $fehler;
    echo "\n$pruefungen Prüfungen, " . count($fehler) . " Fehler\n";
    exit($fehler === [] ? 0 : 1);
}

IPS\Kernel::reset(); // einmal je Testlauf; weitere Instanzen entstehen im selben Kernel
