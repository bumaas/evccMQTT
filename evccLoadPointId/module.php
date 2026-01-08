<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/Themes.php';

use evccMQTT\Themes\LoadPointId;
use evccMQTT\Themes\LoadPointIdIdent;

use const evccMQTT\Themes\IPS_PRESENTATION;
use const evccMQTT\Themes\IPS_VAR_ACTION;
use const evccMQTT\Themes\IPS_VAR_IDENT;
use const evccMQTT\Themes\IPS_VAR_NAME;
use const evccMQTT\Themes\IPS_VAR_TYPE;
use const evccMQTT\Themes\IPS_VAR_VALUE;

require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccLoadPointId extends IPSModuleStrict
{
    use MQTTHelper;

    private const string PROP_TOPIC       = 'topic';
    private const string PROP_LOADPOINTID = 'loadPointId';
    private const array  IGNORED_ELEMENTS = [
        'chargerPhysicalPhases',
        'chargeCurrents',
        'l1',
        'l2',
        'l3',
        'chargerIcon',
        'vehicleClimaterActive',
        'planTime',
    ];


    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/loadpoints/');
        $this->RegisterPropertyInteger(self::PROP_LOADPOINTID, 1);

        $this->registerVariables();
    }

    private function registerVariables(): void
    {
        $pos = 0;
        foreach (LoadPointIdIdent::idents() as $ident) {
            $VariableValues = LoadPointId::getIPSVariable($ident);
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
        return $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_LOADPOINTID) . '/';
    }

    private function shouldBeIgnored(string $lastElement, string $penultimateElement, string $topic, string $MQTTTopic): bool
    {
        return in_array($lastElement, self::IGNORED_ELEMENTS)
               || is_numeric($lastElement);
    }


    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->getMqttBaseTopic();
        $mqtt      = $this->prepareMQTTData($JSONString, $MQTTTopic);
        if (is_null($mqtt)) {
            return '';
        }

        if ($this->shouldBeIgnored($mqtt['LastElement'], $mqtt['PenultimateElement'], $mqtt['Topic'], $MQTTTopic)) {
            $this->SendDebug(__FUNCTION__, 'ignored: ' . $mqtt['Topic'], 0);
        } elseif (LoadPointId::propertyIsValid($mqtt['LastElement'])) {
            $VariableValues = LoadPointId::getIPSVariable($mqtt['LastElement'], $mqtt['Payload']);
            if (!is_null($VariableValues[IPS_VAR_VALUE])) {
                if (!$this->SetValue($VariableValues[IPS_VAR_IDENT], $VariableValues[IPS_VAR_VALUE])) {
                    IPS_LogMessage(__FUNCTION__, sprintf('ident: %s, value: %s', $VariableValues[IPS_VAR_IDENT], $VariableValues[IPS_VAR_VALUE]));
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected topic: ' . $mqtt['Topic'], 0);
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        $mqttBaseTopic = rtrim($this->getMqttBaseTopic(), '/');
        switch ($Ident) {
            case LoadPointIdIdent::Enabled->value:
                $this->mqttCommand($mqttBaseTopic . '/' . $Ident . '/set', $Value ? 'true' : 'false');
                break;
            case LoadPointIdIdent::Mode->value:
            case LoadPointIdIdent::LimitSoc->value:
            case LoadPointIdIdent::LimitEnergy->value:
            case LoadPointIdIdent::MinCurrent->value:
            case LoadPointIdIdent::MaxCurrent->value:
            case LoadPointIdIdent::EnableThreshold->value:
            case LoadPointIdIdent::SmartCostLimit->value:
                $this->mqttCommand($mqttBaseTopic . '/' . $Ident . '/set', (string)$Value);
                break;
            case LoadPointIdIdent::PhasesConfigured->value:
                $this->mqttCommand($mqttBaseTopic . '/phases/set', (string)$Value); //die zu nutzenden Phasen werden über das Topic 'phases' gesetzt.
                break;
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
