<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/Themes.php';

use evccMQTT\Themes\SiteStatistics;

use evccMQTT\Themes\SiteStatisticsIdent;

use const evccMQTT\Themes\IPS_PRESENTATION;
use const evccMQTT\Themes\IPS_VAR_ACTION;
use const evccMQTT\Themes\IPS_VAR_IDENT;
use const evccMQTT\Themes\IPS_VAR_NAME;
use const evccMQTT\Themes\IPS_VAR_TYPE;
use const evccMQTT\Themes\IPS_VAR_VALUE;


require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSiteStatistics extends IPSModuleStrict
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const string PROP_TOPIC = 'topic';
    private const string PROP_SCOPE = 'scope';

    public function Create(): void
    {
        parent::Create();

        // Beispiel-Default: evcc/site/statistics/30d/
        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/statistics/');
        $this->RegisterPropertyString(self::PROP_SCOPE, 'total');

        $this->registerVariables();
    }

    private function registerVariables(): void
    {
        $pos = 0;
        foreach (SiteStatisticsIdent::idents() as $ident) {
            $VariableValues = SiteStatistics::getIPSVariable($ident);
            $this->SendDebug(__FUNCTION__, sprintf('%s, VariableValues: %s', $ident, print_r($VariableValues, true)), 0);

            // Position wird hier fortlaufend gesetzt
            $this->registerVariableByType(
                $VariableValues[IPS_VAR_TYPE],
                $VariableValues[IPS_VAR_IDENT],
                $this->Translate($VariableValues[IPS_VAR_NAME]),
                $VariableValues[IPS_PRESENTATION],
                ++$pos
            );

            if ($VariableValues[IPS_VAR_ACTION]) {
                $this->EnableAction($ident);
            }
        }
    }

    private function registerVariableByType(int $type, string $ident, string $name, array $presentation, int $position): bool
    {
        $map = [
            VARIABLETYPE_INTEGER => fn() => $this->RegisterVariableInteger($ident, $name, $presentation, $position),
            VARIABLETYPE_FLOAT   => fn() => $this->RegisterVariableFloat($ident, $name, $presentation, $position),
            VARIABLETYPE_STRING  => fn() => $this->RegisterVariableString($ident, $name, $presentation, $position),
            VARIABLETYPE_BOOLEAN => fn() => $this->RegisterVariableBoolean($ident, $name, $presentation, $position),
        ];
        return isset($map[$type]) ? $map[$type]() : false;
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $base      = rtrim($this->ReadPropertyString(self::PROP_TOPIC), '/') . '/';
        $scope     = $this->ReadPropertyString(self::PROP_SCOPE);
        $MQTTTopic = $base . $scope . '/';

        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData(string $JSONString): string
    {
        $base      = rtrim($this->ReadPropertyString(self::PROP_TOPIC), '/') . '/';
        $scope     = $this->ReadPropertyString(self::PROP_SCOPE);
        $MQTTTopic = $base . $scope . '/';

        if ($MQTTTopic === '/') {
            return '';
        }

        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = hex2bin($data['Payload']);

        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s', $topic, $payload), 0);

        $mqttSubTopics = $this->getMqttSubTopics($topic);
        $lastElement   = $this->getLastElement($mqttSubTopics);

        if (SiteStatistics::propertyIsValid($lastElement)) {
            $this->SendDebug(__FUNCTION__, sprintf('topic: %s, payload: %s', $topic, $payload), 0);
            $VariableValues = SiteStatistics::getIPSVariable($lastElement, $payload);
            $this->SetValue($VariableValues[IPS_VAR_IDENT], $VariableValues[IPS_VAR_VALUE]);
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected topic: ' . $topic, 0);
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        $this->LogMessage('No writable statistics actions supported.', KL_WARNING);
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::MQTT_SERVER]], JSON_THROW_ON_ERROR);
    }
}