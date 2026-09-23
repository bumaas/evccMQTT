<?php

declare(strict_types=1);

/*
 * Nimmt echte evcc-MQTT-Nachrichten als Fixture für die Tests auf.
 *
 *   php tests/tools/mitschnitt.php aufnehmen <host> <port> <sekunden> <rohdatei.json>
 *   php tests/tools/mitschnitt.php ablegen <rohdatei.json> <tests/fixtures/ziel.json> [--version=<x>]
 *
 * aufnehmen: abonniert evcc/# (MQTT 3.1.1, QoS 0) und schreibt jede Nachricht in
 * Empfangsreihenfolge mit. Einen vollständigen Stand gibt es nur, wenn evcc währenddessen
 * neu startet - Konfigurationswerte (title, limitSoc, version …) sendet evcc nur beim Start
 * oder bei Änderung, und nicht jeder Broker hält sie als retained vor.
 *
 * ablegen: anonymisiert die Rohdatei für das öffentliche Repo und legt sie als Fixture ab.
 * Ersetzt werden nur Werte, die auf eine Person oder ein Gerät zurückführen; alle anderen
 * Payloads bleiben byteweise so, wie evcc sie gesendet hat.
 */

const ANONYM = [
    // Topic (regulärer Ausdruck) => Ersatzwert
    '#^evcc/site/siteTitle$#'           => 'Musterstraße 1',
    '#^evcc/site/eebus/config/certificate/(public|private)$#' => 'anonymisiert',
    '#^evcc/site/eebus/config/shipID$#' => 'EVCC-0000000000000000',
    '#^evcc/site/eebus/status/(qR|ski)$#' => 'anonymisiert',
];

function str16(string $s): string
{
    return pack('n', strlen($s)) . $s;
}

function paket(int $typ, string $body): string
{
    $n   = strlen($body);
    $len = '';
    do {
        $b = $n % 128;
        $n = intdiv($n, 128);
        $len .= chr($n > 0 ? $b | 0x80 : $b);
    } while ($n > 0);
    return chr($typ) . $len . $body;
}

function aufnehmen(string $host, int $port, int $sekunden, string $ziel): void
{
    $s = stream_socket_client("tcp://$host:$port", $errno, $err, 5);
    if ($s === false) {
        fwrite(STDERR, "Verbindung zu $host:$port gescheitert: $err\n");
        exit(1);
    }
    fwrite($s, paket(0x10, str16('MQTT') . chr(4) . chr(0x02) . pack('n', 120) . str16('evcc-mitschnitt-' . getmypid())));
    fwrite($s, paket(0x82, pack('n', 1) . str16('evcc/#') . chr(0)));

    $start  = microtime(true);
    $ende   = $start + $sekunden;
    $buf    = '';
    $liste  = [];
    while (microtime(true) < $ende) {
        $r = [$s];
        $w = $e = null;
        if (stream_select($r, $w, $e, 1) > 0) {
            $d = fread($s, 65536);
            if ($d === '' || $d === false) {
                break;
            }
            $buf .= $d;
        }
        while (($paket = naechstesPaket($buf)) !== null) {
            [$kopf, $body] = $paket;
            if ($kopf >> 4 !== 3) { // nur PUBLISH
                continue;
            }
            $tl      = unpack('n', substr($body, 0, 2))[1];
            $qos     = ($kopf >> 1) & 3;
            $liste[] = [
                't'       => round(microtime(true) - $start, 3),
                'retain'  => (bool) ($kopf & 1),
                'topic'   => substr($body, 2, $tl),
                'payload' => substr($body, 2 + $tl + ($qos > 0 ? 2 : 0)),
            ];
        }
    }
    fwrite($s, chr(0xE0) . chr(0));
    fclose($s);

    file_put_contents($ziel, json_encode($liste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    printf("%d Nachrichten, %d Topics -> %s\n", count($liste), count(array_unique(array_column($liste, 'topic'))), $ziel);
}

/** Schneidet ein vollständiges MQTT-Paket vom Pufferanfang ab, sonst null. */
function naechstesPaket(string &$buf): ?array
{
    $len  = 0;
    $mult = 1;
    $i    = 1;
    do {
        if ($i >= strlen($buf)) {
            return null;
        }
        $b = ord($buf[$i++]);
        $len += ($b & 0x7F) * $mult;
        $mult *= 128;
    } while ($b & 0x80);
    if (strlen($buf) < $i + $len) {
        return null;
    }
    $paket = [ord($buf[0]), substr($buf, $i, $len)];
    $buf   = substr($buf, $i + $len);
    return $paket;
}

function ablegen(string $roh, string $ziel, string $versionVorgabe = ''): void
{
    $liste   = json_decode(file_get_contents($roh), true, 512, JSON_THROW_ON_ERROR);
    $ersetzt = 0;
    $version = '';
    $t0      = $liste[0]['t'] ?? 0;
    foreach ($liste as &$m) {
        if ($t0 > 1e9) { // absolute Zeitstempel auf Sekunden seit Aufnahmebeginn umrechnen
            $m['t'] = round($m['t'] - $t0, 3);
        }
        foreach (ANONYM as $muster => $ersatz) {
            if (preg_match($muster, $m['topic']) === 1 && $m['payload'] !== '') {
                $m['payload'] = $ersatz;
                $ersetzt++;
            }
        }
        if ($m['topic'] === 'evcc/site/version') {
            $version = $m['payload'];
        }
    }
    unset($m);
    $mitNeustart = $version !== '';
    $version     = $mitNeustart ? $version : $versionVorgabe;
    if ($version === '') {
        fwrite(STDERR, "evcc/site/version fehlt - evcc ist während der Aufnahme nicht neu gestartet, der Mitschnitt ist unvollständig (bei gewollten Ergänzungsmitschnitten --version=<x> angeben)\n");
        exit(1);
    }
    $fixture = [
        'evcc'        => $version,
        'aufgenommen' => date('Y-m-d', filemtime($roh)),
        'hinweis'     => 'Echter Mitschnitt von evcc/# ' . ($mitNeustart ? 'inkl. Neustart von evcc' : 'ohne Neustart (Ergänzung, Version von Hand angegeben)') . ', erzeugt mit tests/tools/mitschnitt.php; anonymisiert: ' . implode(', ', array_keys(ANONYM)),
        'nachrichten' => $liste,
    ];
    file_put_contents($ziel, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    printf("evcc %s, %d Nachrichten, %d Werte anonymisiert -> %s\n", $version, count($liste), $ersetzt, $ziel);
}

match ($argv[1] ?? '') {
    'aufnehmen' => aufnehmen($argv[2], (int) $argv[3], (int) $argv[4], $argv[5]),
    'ablegen'   => ablegen($argv[2], $argv[3], substr($argv[4] ?? '', strlen('--version='))),
    default     => (static function (): never {
        fwrite(STDERR, "Aufruf: mitschnitt.php aufnehmen <host> <port> <sekunden> <roh.json> | ablegen <roh.json> <ziel.json>\n");
        exit(1);
    })(),
};
