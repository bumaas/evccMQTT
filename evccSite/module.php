<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSite extends IPSModule
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const PROP_TOPIC = 'topic';

    private const VAR_IDENT_PVPOWER              = 'pvPower';
    private const VAR_IDENT_PVENERGY             = 'pvEnergy';
    private const VAR_IDENT_BATTERYCAPACITY      = 'batteryCapacity';
    private const VAR_IDENT_BATTERYSOC           = 'batterySoc';
    private const VAR_IDENT_BATTERYPOWER         = 'batteryPower';
    private const VAR_IDENT_BATTERYENERGY        = 'batteryEnergy';
    private const VAR_IDENT_GRIDPOWER            = 'gridPower';
    private const VAR_IDENT_GRIDCURRENTS         = 'gridCurrents';
    private const VAR_IDENT_GRIDENERGY           = 'gridEnergy';
    private const VAR_IDENT_HOMEPOWER            = 'homePower';
    private const VAR_IDENT_GREENSHAREHOME       = 'greenShareHome';
    private const VAR_IDENT_GREENSHARELOADPOINTS = 'greenShareLoadpoints';
    private const VAR_IDENT_TARIFFPRICEHOME = 'tariffPriceHome';
    private const VAR_IDENT_TARIFFCO2HOME = 'tariffCo2Home';
    private const VAR_IDENT_TARIFFPRICELOADPOINTS = 'tariffPriceLoadpoints';
    private const VAR_IDENT_TARIFFCO2LOADPOINTS = 'tariffCo2Loadpoints';



    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent(self::MQTT_SERVER);
        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/');

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W', []);
        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', [], -1, 0, 1);

        $pos = 0;
        $this->RegisterVariableInteger(self::VAR_IDENT_PVPOWER, $this->Translate('PV Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_PVENERGY, $this->Translate('PV Energy'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_BATTERYCAPACITY, $this->Translate('Battery Capacity'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_BATTERYSOC, $this->Translate('Battery SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_BATTERYPOWER, $this->Translate('Battery Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_BATTERYENERGY, $this->Translate('Battery Energy'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_GRIDPOWER, $this->Translate('Grid Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_GRIDCURRENTS, $this->Translate('Grid Currents'), '', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_GRIDENERGY, $this->Translate('Grid Energy'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_HOMEPOWER, $this->Translate('Home Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_GREENSHAREHOME, $this->Translate('Green Share Home'), '~Intensity.1', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_GREENSHARELOADPOINTS, $this->Translate('Green Share Loadpoints'), '~Intensity.1', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFPRICEHOME, $this->Translate('Tariff Price Home'), '', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_TARIFFCO2HOME, $this->Translate('Tariff CO2 Home'), '', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFPRICELOADPOINTS, $this->Translate('Tariff Price Loadpoints'), '', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_TARIFFCO2LOADPOINTS, $this->Translate('Tariff CO2 Loadpoints'), '', ++$pos);
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
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC);
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData($JSONString)
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC);

        if (empty($MQTTTopic)) {
            return;
        }

        $this->SendDebug(__FUNCTION__, 'JSONString: ' . $JSONString, 0);
        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = $data['Payload'];

        switch ($topic) {
            case $MQTTTopic . self::VAR_IDENT_PVPOWER:
                $this->SetValue(self::VAR_IDENT_PVPOWER, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_PVENERGY:
                $this->SetValue(self::VAR_IDENT_PVENERGY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_BATTERYCAPACITY:
                $this->SetValue(self::VAR_IDENT_BATTERYCAPACITY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_BATTERYSOC:
                $this->SetValue(self::VAR_IDENT_BATTERYSOC, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_BATTERYPOWER:
                $this->SetValue(self::VAR_IDENT_BATTERYPOWER, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_BATTERYENERGY:
                $this->SetValue(self::VAR_IDENT_BATTERYENERGY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_GRIDPOWER:
                $this->SetValue(self::VAR_IDENT_GRIDPOWER, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_GRIDCURRENTS:
                $this->SetValue(self::VAR_IDENT_GRIDCURRENTS, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_GRIDENERGY:
                $this->SetValue(self::VAR_IDENT_GRIDENERGY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_HOMEPOWER:
                $this->SetValue(self::VAR_IDENT_HOMEPOWER, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_GREENSHAREHOME:
                $this->SetValue(self::VAR_IDENT_GREENSHAREHOME, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_GREENSHARELOADPOINTS:
                $this->SetValue(self::VAR_IDENT_GREENSHARELOADPOINTS, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_TARIFFPRICEHOME:
                $this->SetValue(self::VAR_IDENT_TARIFFPRICEHOME, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_TARIFFCO2HOME:
                $this->SetValue(self::VAR_IDENT_TARIFFCO2HOME, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_TARIFFPRICELOADPOINTS:
                $this->SetValue(self::VAR_IDENT_TARIFFPRICELOADPOINTS, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_TARIFFCO2LOADPOINTS:
                $this->SetValue(self::VAR_IDENT_TARIFFCO2LOADPOINTS, (int)$payload);
                break;
            default:
                $subTopic = substr($topic, strlen($MQTTTopic));
                $parts    = explode('/', $subTopic);
                if (count($parts) === 1) {
                    if (!in_array($parts[0], ['pv', 'battery'])) {
                        $this->SendDebug(__FUNCTION__, 'unexpected topic: ' . $topic, 0);
                    }
                }
                break;
        }
    }

    public function RequestAction($Ident, $Value)
    {
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
