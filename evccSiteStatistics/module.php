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


require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSiteStatistics extends IPSModuleStrict
{
    use MQTTHelper;

    private const string PROP_TOPIC = 'topic';
    private const string PROP_SCOPE = 'scope';

    private const array IGNORED_ELEMENTS = [];

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
            $this->MaintainVariable(
                $VariableValues[IPS_VAR_IDENT],
                $this->Translate($VariableValues[IPS_VAR_NAME]),
                $VariableValues[IPS_VAR_TYPE],
                $VariableValues[IPS_PRESENTATION],
                ++$pos,
                true
            );

            if ($VariableValues[IPS_VAR_ACTION]) {
                $this->EnableAction($ident);
            }
        }
    }


    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        //Setze Filter für ReceiveData
        $MQTTTopic          = $this->getMqttBaseTopic();
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    private function getMqttBaseTopic(): string
    {
        return $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyString(self::PROP_SCOPE) . '/';
    }

    private function shouldBeIgnored(string $lastElement, string $penultimateElement, string $topic, string $MQTTTopic): bool
    {
        return in_array($lastElement, self::IGNORED_ELEMENTS)
               || is_numeric($lastElement);
    }

    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->getMqttBaseTopic();
        $mqtt = $this->prepareMQTTData($JSONString, $MQTTTopic);
        if (is_null($mqtt)) {
            return '';
        }

        if ($this->shouldBeIgnored($mqtt['LastElement'], $mqtt['PenultimateElement'], $mqtt['Topic'], $MQTTTopic)) {
            $this->SendDebug(__FUNCTION__, 'ignored: ' . $mqtt['Topic'], 0);
        } elseif (SiteStatistics::propertyIsValid($mqtt['LastElement'])) {
            $VariableValues = SiteStatistics::getIPSVariable($mqtt['LastElement'], $mqtt['Payload']);
            $this->SetValue($VariableValues[IPS_VAR_IDENT], $VariableValues[IPS_VAR_VALUE]);
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected topic: ' . $mqtt['Topic'], 0);
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