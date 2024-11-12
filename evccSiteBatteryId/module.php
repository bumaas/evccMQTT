<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSiteBatteryId extends IPSModuleStrict
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const PROP_TOPIC         = 'topic';
    private const PROP_SITEBATTERYID = 'siteBatteryId';

    private const VAR_IDENT_POWER        = 'power';
    private const VAR_IDENT_ENERGY       = 'energy';
    private const VAR_IDENT_SOC          = 'soc';
    private const VAR_IDENT_CAPACITY     = 'capacity';
    private const VAR_IDENT_CONTROLLABLE = 'controllable';


    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent(self::MQTT_SERVER);

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/battery/');
        $this->RegisterPropertyInteger(self::PROP_SITEBATTERYID, 1);

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W', []);
        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', [], -1, 0, 1);
        $this->RegisterProfileBooleanEx('evcc.Controllable', '', '', '', [
            [0, $this->Translate('no'), '', -1],
            [1, $this->Translate('yes'), '', -1]
        ]);

        $pos = 0;
        $this->RegisterVariableInteger(self::VAR_IDENT_POWER, $this->Translate('Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_ENERGY, $this->Translate('Energy'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_SOC, $this->Translate('SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_CAPACITY, $this->Translate('Capacity'), 'evcc.Energy.kWh', ++$pos);

        $this->RegisterVariableBoolean(self::VAR_IDENT_CONTROLLABLE, $this->Translate('Controllable'), 'evcc.Controllable', ++$pos);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        //Setze Filter für ReceiveData
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEBATTERYID) . '/';
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEBATTERYID) . '/';

        if (empty($MQTTTopic)) {
            return'';
        }

        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = hex2bin($data['Payload']);
        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s', $topic, $payload), 0);

        switch ($topic) {
            case $MQTTTopic . self::VAR_IDENT_POWER:
                $this->SetValue(self::VAR_IDENT_POWER, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_ENERGY:
                $this->SetValue(self::VAR_IDENT_ENERGY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_SOC:
                $this->SetValue(self::VAR_IDENT_SOC, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CAPACITY:
                $this->SetValue(self::VAR_IDENT_CAPACITY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CONTROLLABLE:
                $this->SetValue(self::VAR_IDENT_CONTROLLABLE, filter_var($payload, FILTER_VALIDATE_BOOLEAN));
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unexpected topic: ' . $topic, 0);
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        $bat = $this->ReadPropertyInteger(self::PROP_SITEBATTERYID);
        switch ($Ident) {
            case 'SoC':
                $this->mqttCommand('set/houseBattery/%Soc', (int) $Value);
                break;
            case 'W':
                $this->mqttCommand('set/houseBattery/W', (float) $Value);
                break;
            case 'WhExported':
                $this->mqttCommand('set/houseBattery/WhExported', (float) $Value);
                break;
            case 'WhImported':
                $this->mqttCommand('set/houseBattery/WhImported', (float) $Value);
                break;
            default:
                $this->LogMessage('Invalid Action', KL_WARNING);
                break;
        }
    }

}
