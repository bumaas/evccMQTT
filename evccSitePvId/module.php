<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';

class evccSitePvId extends IPSModule
{
    use VariableProfileHelper;

    private const PROP_TOPIC    = 'topic';
    private const PROP_SITEPVID = 'sitePvId';

    private const VAR_IDENT_POWER        = 'power';
    private const VAR_IDENT_ENERGY       = 'energy';

    private const MQTT_SERVER            = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    private const DATA_ID_MQTT_SERVER_TX = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
    private const PT_PUBLISH             = 3; //Packet Type Publish
    private const QOS_0                  = 0; //Quality of Service 0


    public function Create()
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
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEPVID) . '/';
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData($JSONString)
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_SITEPVID) . '/';

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
        $bat = $this->ReadPropertyInteger(self::PROP_SITEPVID);
        switch ($Ident) {
            case 'SoC':
                $this->MQTTCommand('set/houseBattery/%Soc', intval($Value));
                break;
            case 'W':
                $this->MQTTCommand('set/houseBattery/W', floatval($Value));
                break;
            case 'WhExported':
                $this->MQTTCommand('set/houseBattery/WhExported', floatval($Value));
                break;
            case 'WhImported':
                $this->MQTTCommand('set/houseBattery/WhImported', floatval($Value));
                break;
            default:
                $this->LogMessage('Invalid Action', KL_WARNING);
                break;
        }
    }

    private function MQTTCommand($Topic, $Payload, $retain = 0)
    {
        $Topic                    = $this->ReadPropertyString('topic') . '/' . $Topic;
        $Data['DataID']           = self::DATA_ID_MQTT_SERVER_TX;
        $Data['PacketType']       = self::PT_PUBLISH;
        $Data['QualityOfService'] = self::QOS_0;
        $Data['Retain']           = boolval($retain);
        $Data['Topic']            = $Topic;
        $Data['Payload']          = strval($Payload);
        $JSON                     = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $result                   = @$this->SendDataToParent($JSON);

        if ($result === false) {
            $last_error = error_get_last();
            echo $last_error['message'];
        }
    }
}
