<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSiteAuxId extends IPSModule
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const PROP_TOPIC     = 'topic';
    private const PROP_SITEAUXID = 'siteAuxId';

    private const VAR_IDENT_POWER        = 'power';
    private const VAR_IDENT_ENERGY       = 'energy';


    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent(self::MQTT_SERVER);

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/aux/');
        $this->RegisterPropertyInteger(self::PROP_SITEAUXID, 1);

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W', []);
        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', [], -1, 0, 1);

        $pos = 0;
        $this->RegisterVariableInteger(self::VAR_IDENT_POWER, $this->Translate('Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_ENERGY, $this->Translate('Energy'), 'evcc.Energy.kWh', ++$pos);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        //Setze Filter für ReceiveData
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEAUXID) . '/';
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData($JSONString)
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEAUXID) . '/';

        if (empty($MQTTTopic)) {
            return;
        }
        $this->SendDebug(__FUNCTION__, $JSONString, 0);

        $data    = json_decode($JSONString, true);
        $topic   = $data['Topic'];
        $payload = $data['Payload'];

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
    }

    public function RequestAction($Ident, $Value)
    {
        $bat = $this->ReadPropertyInteger(self::PROP_SITEAUXID);
        switch ($Ident) {
            case 'SoC':
                $this->mqttCommand('set/houseBattery/%Soc', intval($Value));
                break;
            case 'W':
                $this->mqttCommand('set/houseBattery/W', floatval($Value));
                break;
            case 'WhExported':
                $this->mqttCommand('set/houseBattery/WhExported', floatval($Value));
                break;
            case 'WhImported':
                $this->mqttCommand('set/houseBattery/WhImported', floatval($Value));
                break;
            default:
                $this->LogMessage('Invalid Action', KL_WARNING);
                break;
        }
    }

}
