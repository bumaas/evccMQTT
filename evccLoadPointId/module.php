<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccLoadPointId extends IPSModule
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const PROP_TOPIC       = 'topic';
    private const PROP_LOADPOINTID = 'loadPointId';

    private const VAR_IDENT_CHARGEPOWER             = 'chargePower';
    private const VAR_IDENT_SMARTCOSTACTIVE         = 'smartCostActive';
    private const VAR_IDENT_CHARGECURRENT           = 'chargeCurrent';
    private const VAR_IDENT_SESSIONENERGY           = 'sessionEnergy';
    private const VAR_IDENT_SESSIONSOLARPERCENTAGE  = 'sessionSolarPercentage';
    private const VAR_IDENT_SESSIONPRICEPERKWH      = 'sessionPricePerKWh';
    private const VAR_IDENT_SESSIONPRICE            = 'sessionPrice';
    private const VAR_IDENT_SESSIONCO2PERKWH        = 'sessionCo2PerKWh';
    private const VAR_IDENT_CHARGEDENERGY           = 'chargedEnergy';
    private const VAR_IDENT_CHARGEDURATION          = 'chargeDuration';
    private const VAR_IDENT_EFFECTIVEPRIORITY       = 'effectivePriority';
    private const VAR_IDENT_EFFECTIVEPLANTIME       = 'effectivePlanTime';
    private const VAR_IDENT_EFFECTIVEPLANSOC        = 'effectivePlanSoc';
    private const VAR_IDENT_EFFECTIVEMINCURRENT     = 'effectiveMinCurrent';
    private const VAR_IDENT_EFFECTIVEMAXCURRENT     = 'effectiveMaxCurrent';
    private const VAR_IDENT_EFFECTIVELIMITSOC       = 'effectiveLimitSoc';
    private const VAR_IDENT_CONNECTED               = 'connected';
    private const VAR_IDENT_CHARGING                = 'charging';
    private const VAR_IDENT_VEHICLESOC              = 'vehicleSoc';
    private const VAR_IDENT_CHARGEREMAININGDURATION = 'chargeRemainingDuration';
    private const VAR_IDENT_CHARGEREMAININGENERGY   = 'chargeRemainingEnergy';
    private const VAR_IDENT_VEHICLERANGE            = 'vehicleRange';
    private const VAR_IDENT_ENABLED                 = 'enabled';
    private const VAR_IDENT_MODE                    = 'mode';
    private const VAR_IDENT_PLANPROJECTEDSTART      = 'planProjectedStart';
    private const VAR_IDENT_PLANOVERRUN             = 'planOverrun';
    private const VAR_IDENT_VEHICLEDETECTIONACTIVE  = 'vehicleDetectionActive';
    private const VAR_IDENT_CONNECTEDDURATION       = 'connectedDuration';
    private const VAR_IDENT_PHASESENABLED           = 'phasesEnabled';
    private const VAR_IDENT_PHASESCONFIGURED        = 'phasesConfigured';
    private const VAR_IDENT_SMARTCOSTLIMIT          = 'smartCostLimit';
    private const VAR_IDENT_PHASESACTIVE            = 'phasesActive';


    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent(self::MQTT_SERVER);

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/loadpoints/');
        $this->RegisterPropertyInteger(self::PROP_LOADPOINTID, 1);

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W', []);
        $this->RegisterProfileIntegerEx('evcc.km', '', '', ' km', []);
        $this->RegisterProfileIntegerEx('evcc.Phases', '', '', '', [], 3, 1);
        $this->RegisterProfileStringEx('evcc.Mode', '', '', '', [
            ['off', $this->translate('Off'), '', -1],
            ['pv', $this->translate('Only PV'), '', -1],
            ['minpv', $this->translate('Min+PV'), '', -1],
            ['now', $this->translate('Now'), '', -1],
        ]);
        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', [], -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.Energy.Wh', '', '', ' Wh', [], -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.Current', '', '', ' A', [], -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.EUR', '', '', ' €', [], -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.g', '', '', ' g', [], -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.Intensity.100', '', '', ' %', [], 100, 0, 1);

        $pos = 0;
        $this->RegisterVariableBoolean(self::VAR_IDENT_ENABLED, $this->Translate('Enabled'), '~Switch', ++$pos);
        // $this->EnableAction(self::VAR_IDENT_ENABLED);
        $this->RegisterVariableString(self::VAR_IDENT_MODE, $this->Translate('Mode'), 'evcc.Mode', ++$pos);
        $this->EnableAction(self::VAR_IDENT_MODE);
        $this->RegisterVariableBoolean(self::VAR_IDENT_CONNECTED, $this->Translate('Connected'), '~Switch', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_CONNECTEDDURATION, $this->Translate('Connected Duration'), '~UnixTimestampTime', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PHASESCONFIGURED, $this->Translate('Phases Configured'), 'evcc.Phases', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PHASESENABLED, $this->Translate('Phases Enabled'), 'evcc.Phases', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PHASESACTIVE, $this->Translate('Phases Active'), 'evcc.Phases', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_CHARGING, $this->Translate('Charging'), '~Switch', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_CHARGEPOWER, $this->Translate('Charge Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_CHARGECURRENT, $this->Translate('Charge Current'), 'evcc.Current', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_CHARGEDENERGY, $this->Translate('Charged Energy'), 'evcc.Energy.Wh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_CHARGEDURATION, $this->Translate('Charge Duration'), '~UnixTimestampTime', ++$pos);
        $this->RegisterVariableInteger(
            self::VAR_IDENT_CHARGEREMAININGDURATION,
            $this->Translate('Charge remaining Duration'),
            '~UnixTimestampTime',
            ++$pos
        );
        $this->RegisterVariableBoolean(self::VAR_IDENT_SMARTCOSTACTIVE, $this->Translate('Smart Cost Active'), '~Switch', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SMARTCOSTLIMIT, $this->Translate('Smart Cost Limit'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SESSIONENERGY, $this->Translate('Session Energy'), 'evcc.Energy.Wh', ++$pos);
        $this->RegisterVariableFloat(
            self::VAR_IDENT_SESSIONSOLARPERCENTAGE,
            $this->Translate('Session Solar Percentage'),
            'evcc.Intensity.100',
            ++$pos
        );
        $this->RegisterVariableFloat(self::VAR_IDENT_SESSIONCO2PERKWH, $this->Translate('Session CO2 per kWh'), 'evcc.g', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SESSIONPRICEPERKWH, $this->Translate('Session Price per kWh'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SESSIONPRICE, $this->Translate('Session Price'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPRIORITY, $this->Translate('Effective Priority'), '', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPLANTIME, $this->Translate('Effective Plantime'), '~UnixTimestamp', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPLANSOC, $this->Translate('Effective Plan SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_EFFECTIVEMINCURRENT, $this->Translate('Effective min Current'), 'evcc.Current', ++$pos);
        $this->EnableAction(self::VAR_IDENT_EFFECTIVEMINCURRENT);
        $this->RegisterVariableFloat(self::VAR_IDENT_EFFECTIVEMAXCURRENT, $this->Translate('Effective max Current'), 'evcc.Current', ++$pos);
        $this->EnableAction(self::VAR_IDENT_EFFECTIVEMAXCURRENT);
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVELIMITSOC, $this->Translate('Effective Limit SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_VEHICLEDETECTIONACTIVE, $this->Translate('Vehicle Detection Active'), '~Switch', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_VEHICLESOC, $this->Translate('Vehicle SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_VEHICLERANGE, $this->Translate('Vehicle Range'), 'evcc.km', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_CHARGEREMAININGENERGY, $this->Translate('Charge remaining Energy'), 'evcc.Energy.Wh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PLANPROJECTEDSTART, $this->Translate('Plan Project Start'), '~UnixTimestamp', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PLANOVERRUN, $this->Translate('Plan Overrun'), '', ++$pos);
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
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_LOADPOINTID) . '/';
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData($JSONString)
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_LOADPOINTID) . '/';

        if (empty($MQTTTopic)) {
            return;
        }

        $this->SendDebug(__FUNCTION__, 'JSONString: ' . $JSONString, 0);

        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = $data['Payload'];

        switch ($topic) {
            case $MQTTTopic . self::VAR_IDENT_CHARGEPOWER:
                $this->SetValue(self::VAR_IDENT_CHARGEPOWER, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_SMARTCOSTACTIVE:
                $this->SetValue(self::VAR_IDENT_SMARTCOSTACTIVE, (bool)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CHARGECURRENT:
                $this->SetValue(self::VAR_IDENT_CHARGECURRENT, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_SESSIONENERGY:
                $this->SetValue(self::VAR_IDENT_SESSIONENERGY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_SESSIONSOLARPERCENTAGE:
                $this->SetValue(self::VAR_IDENT_SESSIONSOLARPERCENTAGE, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_SESSIONPRICEPERKWH:
                $this->SetValue(self::VAR_IDENT_SESSIONPRICEPERKWH, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_SESSIONPRICE:
                $this->SetValue(self::VAR_IDENT_SESSIONPRICE, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_SESSIONCO2PERKWH:
                $this->SetValue(self::VAR_IDENT_SESSIONCO2PERKWH, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CHARGEDENERGY:
                $this->SetValue(self::VAR_IDENT_CHARGEDENERGY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CHARGEDURATION:
                $this->SetValue(self::VAR_IDENT_CHARGEDURATION, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_EFFECTIVEPRIORITY:
                $this->SetValue(self::VAR_IDENT_EFFECTIVEPRIORITY, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_EFFECTIVEPLANTIME:
                $this->SetValue(self::VAR_IDENT_EFFECTIVEPLANTIME, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_EFFECTIVEPLANSOC:
                $this->SetValue(self::VAR_IDENT_EFFECTIVEPLANSOC, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_EFFECTIVEMINCURRENT:
                $this->SetValue(self::VAR_IDENT_EFFECTIVEMINCURRENT, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_EFFECTIVEMAXCURRENT:
                $this->SetValue(self::VAR_IDENT_EFFECTIVEMAXCURRENT, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_EFFECTIVELIMITSOC:
                $this->SetValue(self::VAR_IDENT_EFFECTIVELIMITSOC, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CONNECTED:
                $this->SetValue(self::VAR_IDENT_CONNECTED, (bool)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CHARGING:
                $this->SetValue(self::VAR_IDENT_CHARGING, (bool)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_VEHICLESOC:
                $this->SetValue(self::VAR_IDENT_VEHICLESOC, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CHARGEREMAININGDURATION:
                $this->SetValue(self::VAR_IDENT_CHARGEREMAININGDURATION, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_CHARGEREMAININGENERGY:
                $this->SetValue(self::VAR_IDENT_CHARGEREMAININGENERGY, (float)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_VEHICLERANGE:
                $this->SetValue(self::VAR_IDENT_VEHICLERANGE, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_ENABLED:
                $this->SetValue(self::VAR_IDENT_ENABLED, (bool)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_MODE:
                $this->SetValue(self::VAR_IDENT_MODE, (string)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_PLANPROJECTEDSTART:
                $this->SetValue(self::VAR_IDENT_PLANPROJECTEDSTART, (int)$payload);
                break;
            case $MQTTTopic . self::VAR_IDENT_PLANOVERRUN:
                $this->SetValue(self::VAR_IDENT_PLANOVERRUN, (int)$payload);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unexpected topic: ' . $topic, 0);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $mqttTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_LOADPOINTID) . '/' . $Ident . '/set';
        switch ($Ident) {
            case self::VAR_IDENT_ENABLED:
                $this->mqttCommand($mqttTopic, var_export($Value, true));
                break;
            case self::VAR_IDENT_MODE:
            case self::VAR_IDENT_EFFECTIVEMINCURRENT:
            case self::VAR_IDENT_EFFECTIVEMAXCURRENT:
                $this->mqttCommand($mqttTopic, (string)$Value);
                break;
            default:
                $this->LogMessage('Invalid Action', KL_WARNING);
                break;
        }
    }

}
