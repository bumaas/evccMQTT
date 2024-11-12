<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSitePvId extends IPSModuleStrict
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const PROP_TOPIC    = 'topic';
    private const PROP_SITEPVID = 'sitePvId';

    private const VAR_IDENT_POWER        = 'power';
    private const VAR_IDENT_ENERGY       = 'energy';


    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent(self::MQTT_SERVER);

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/pv/');
        $this->RegisterPropertyInteger(self::PROP_SITEPVID, 1);

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W', []);
        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', [], -1, 0, 1);

        $pos = 0;
        $this->RegisterVariableInteger(self::VAR_IDENT_POWER, $this->Translate('Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_ENERGY, $this->Translate('Energy'), 'evcc.Energy.kWh', ++$pos);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        //Setze Filter für ReceiveData
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEPVID) . '/';
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEPVID) . '/';

        if (empty($MQTTTopic)) {
            return '';
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
            default:
                $this->SendDebug(__FUNCTION__, 'unexpected topic: ' . $topic, 0);
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        $bat = $this->ReadPropertyInteger(self::PROP_SITEPVID);
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
