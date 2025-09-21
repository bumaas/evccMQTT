<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccVehicleName extends IPSModuleStrict
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const string PROP_TOPIC       = 'topic';
    private const string PROP_VEHICLENAME = 'vehicleName';


    private const string VAR_IDENT_TITLE       = 'title';
    private const string VAR_IDENT_CAPACITY     = 'capacity';


    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/vehicles/');
        $this->RegisterPropertyString(self::PROP_VEHICLENAME, '');

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W', []);
        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', [], -1, 0, 1);
        $this->RegisterProfileBooleanEx('evcc.Controllable', '', '', '', [
            [0, $this->Translate('no'), '', -1],
            [1, $this->Translate('yes'), '', -1]
        ]);

        $pos = 0;
        $this->RegisterVariableString(self::VAR_IDENT_TITLE, $this->Translate('Title'),'', ++$pos);

        //$this->RegisterVariableInteger(self::VAR_IDENT_POWER, $this->Translate('Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_CAPACITY, $this->Translate('Capacity'), 'evcc.Energy.kWh', ++$pos);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        //$this->ConnectParent(self::MQTT_SERVER);

        //Setze Filter für ReceiveData
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyString(self::PROP_VEHICLENAME) . '/';
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyString(self::PROP_VEHICLENAME) . '/';

        if (empty($MQTTTopic)) {
            return '';
        }

        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = hex2bin($data['Payload']);
        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s', $topic, $payload), 0);

        switch ($topic) {
            case $MQTTTopic . self::VAR_IDENT_TITLE:
                $this->SetValue(self::VAR_IDENT_TITLE, $payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CAPACITY:
                $this->SetValue(self::VAR_IDENT_CAPACITY, (float)$payload);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unexpected topic: ' . $topic, 0);
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        //$bat = $this->ReadPropertyInteger(self::PROP_SITEBATTERYID);
        switch ($Ident) {
            case 'SoC':
                $this->mqttCommand('set/houseBattery/%Soc', (int)$Value);
                break;
            case 'W':
                $this->mqttCommand('set/houseBattery/W', (float)$Value);
                break;
            case 'WhExported':
                $this->mqttCommand('set/houseBattery/WhExported', (float)$Value);
                break;
            case 'WhImported':
                $this->mqttCommand('set/houseBattery/WhImported', (float)$Value);
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
