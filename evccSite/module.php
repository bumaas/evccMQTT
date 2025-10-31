<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/Themes.php';

use evccMQTT\Themes\Site;
use evccMQTT\Themes\SiteIdent;

use const evccMQTT\Themes\IPS_PRESENTATION;
use const evccMQTT\Themes\IPS_VAR_ACTION;
use const evccMQTT\Themes\IPS_VAR_IDENT;
use const evccMQTT\Themes\IPS_VAR_NAME;
use const evccMQTT\Themes\IPS_VAR_TYPE;
use const evccMQTT\Themes\IPS_VAR_VALUE;

require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSite extends IPSModuleStrict
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const string PROP_TOPIC = 'topic';
    private const array  IGNORED_ELEMENTS = ['pv', 'aux', 'battery', 'fatal', 'gridPowers', 'l1', 'l2', 'l3'];
    private const string STATISTICS_FLAG  = 'statistics';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/');

        $this->registerVariables();
    }

    private function registerVariables(): void
    {
        $pos = 0;
        foreach (SiteIdent::idents() as $ident) {
            $VariableValues = Site::getIPSVariable($ident);
            $this->SendDebug(__FUNCTION__, sprintf('%s, VariableValues: %s', $ident, print_r($VariableValues, true)), 0);

            $this->registerVariableByType(
                $VariableValues[IPS_VAR_TYPE],
                $VariableValues[IPS_VAR_IDENT],
                $this->Translate($VariableValues[IPS_VAR_NAME]),
                $VariableValues[IPS_PRESENTATION],
                $pos++
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


    private function shouldBeIgnored(string $lastElement, string $penultimateElement, string $topic, string $MQTTTopic): bool
    {
        return in_array($lastElement, self::IGNORED_ELEMENTS)
               || is_numeric($penultimateElement)
               || is_numeric($lastElement)
               || str_starts_with($topic, $MQTTTopic . self::STATISTICS_FLAG);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Setze Filter für ReceiveData
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC);
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC);

        if (empty($MQTTTopic)) {
            return '';
        }

        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = hex2bin($data['Payload']);
        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s', $topic, $payload), 0);

        $mqttSubTopics      = $this->getMqttSubTopics($topic);
        $lastElement        = $this->getLastElement($mqttSubTopics);
        $penultimateElement = $this->getPenultimateElement($mqttSubTopics);

        if ($this->isReceivedSetTopic($topic)) {
            //$this->SendDebug(__FUNCTION__, 'received: ' . $topic, 0);
            return '';
        }

        if ($this->shouldBeIgnored($lastElement, $penultimateElement, $topic, $MQTTTopic)) {
            $this->SendDebug(__FUNCTION__, 'ignored: ' . $topic, 0);
        } elseif (Site::propertyIsValid($lastElement)) {
            $VariableValues = Site::getIPSVariable($lastElement, $payload);
            if (!is_null($VariableValues[IPS_VAR_VALUE])) {
                $this->SetValue($VariableValues[IPS_VAR_IDENT], $VariableValues[IPS_VAR_VALUE]);
            }
        } elseif (Site::propertyIsValid($penultimateElement . '_' . $lastElement)) {
            $VariableValues = Site::getIPSVariable($penultimateElement . '_' . $lastElement, $payload);
            if (!is_null($VariableValues[IPS_VAR_VALUE])) {
                $this->SetValue($VariableValues[IPS_VAR_IDENT], $VariableValues[IPS_VAR_VALUE]);
            }
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected topic: ' . $topic, 0);
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        $mqttTopic = $this->ReadPropertyString(self::PROP_TOPIC);

        switch ($Ident) {
            case SiteIdent::PrioritySoc:
            case SiteIdent::BufferSoc:
            case SiteIdent::BufferStartSoc:
            case SiteIdent::ResidualPower:
            case SiteIdent::BatteryGridChargeLimit:
                $this->mqttCommand($mqttTopic . $Ident . '/set', (string)$Value);
                break;
            case SiteIdent::BatteryDischargeControl:
                $this->mqttCommand($mqttTopic . $Ident . '/set', $Value ? 'true' : 'false');
                break;
            default:
                $this->LogMessage('Invalid Action', KL_WARNING);
                break;
        }
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::MQTT_SERVER]], JSON_THROW_ON_ERROR);
    }

}
