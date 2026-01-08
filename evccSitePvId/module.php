<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/Themes.php';

use evccMQTT\Themes\SitePvId;
use evccMQTT\Themes\SitePvIdIdent;

use const evccMQTT\Themes\IPS_PRESENTATION;
use const evccMQTT\Themes\IPS_VAR_ACTION;
use const evccMQTT\Themes\IPS_VAR_IDENT;
use const evccMQTT\Themes\IPS_VAR_NAME;
use const evccMQTT\Themes\IPS_VAR_TYPE;
use const evccMQTT\Themes\IPS_VAR_VALUE;

require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSitePvId extends IPSModuleStrict
{
    use MQTTHelper;

    private const string PROP_TOPIC    = 'topic';
    private const string PROP_SITEPVID = 'sitePvId';

    private const array IGNORED_ELEMENTS = [];


    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/pv/');
        $this->RegisterPropertyInteger(self::PROP_SITEPVID, 1);

        $this->registerVariables();
    }

    private function registerVariables(): void
    {
        $pos = 0;
        foreach (SitePvIdIdent::idents() as $ident) {
            $VariableValues = SitePvId::getIPSVariable($ident);
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
        //Never delete this line!
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
        return $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEPVID) . '/';
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
        } elseif (SitePvId::propertyIsValid($mqtt['LastElement'])) {
            $VariableValues = SitePvId::getIPSVariable($mqtt['LastElement'], $mqtt['Payload']);
            if (!is_null($VariableValues[IPS_VAR_VALUE])) {
                $this->SetValue($VariableValues[IPS_VAR_IDENT], $VariableValues[IPS_VAR_VALUE]);
            }
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected topic: ' . $mqtt['Topic'], 0);
        }
        return '';

    }

    public function RequestAction($Ident, $Value): void
    {
        $mqttTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEPVID);

        switch ($Ident) {
            default:
                $this->LogMessage(sprintf('Invalid Action: %s, Value: %s', $Ident, $Value), KL_ERROR);
                break;
        }
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::MQTT_SERVER]], JSON_THROW_ON_ERROR);
    }

}
