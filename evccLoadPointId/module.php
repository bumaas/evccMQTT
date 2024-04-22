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

    private const VAR_IDENT_CHARGEPOWER                    = 'chargePower';
    private const VAR_IDENT_SMARTCOSTACTIVE                = 'smartCostActive';
    private const VAR_IDENT_CHARGECURRENT                  = 'chargeCurrent';
    private const VAR_IDENT_SESSIONENERGY                  = 'sessionEnergy';
    private const VAR_IDENT_SESSIONSOLARPERCENTAGE         = 'sessionSolarPercentage';
    private const VAR_IDENT_SESSIONPRICEPERKWH             = 'sessionPricePerKWh';
    private const VAR_IDENT_SESSIONPRICE                   = 'sessionPrice';
    private const VAR_IDENT_SESSIONCO2PERKWH               = 'sessionCo2PerKWh';
    private const VAR_IDENT_CHARGEDENERGY                  = 'chargedEnergy';
    private const VAR_IDENT_CHARGEDURATION                 = 'chargeDuration';
    private const VAR_IDENT_EFFECTIVEPRIORITY              = 'effectivePriority';
    private const VAR_IDENT_EFFECTIVEPLANTIME              = 'effectivePlanTime';
    private const VAR_IDENT_EFFECTIVEPLANSOC               = 'effectivePlanSoc';
    private const VAR_IDENT_EFFECTIVEMINCURRENT            = 'effectiveMinCurrent';
    private const VAR_IDENT_EFFECTIVEMAXCURRENT            = 'effectiveMaxCurrent';
    private const VAR_IDENT_EFFECTIVELIMITSOC              = 'effectiveLimitSoc';
    private const VAR_IDENT_CONNECTED                      = 'connected';
    private const VAR_IDENT_CHARGING                       = 'charging';
    private const VAR_IDENT_VEHICLESOC                     = 'vehicleSoc';
    private const VAR_IDENT_CHARGEREMAININGDURATION        = 'chargeRemainingDuration';
    private const VAR_IDENT_CHARGEREMAININGENERGY          = 'chargeRemainingEnergy';
    private const VAR_IDENT_VEHICLERANGE                   = 'vehicleRange';
    private const VAR_IDENT_ENABLED                        = 'enabled';
    private const VAR_IDENT_MODE                           = 'mode';
    private const VAR_IDENT_PLANPROJECTEDSTART             = 'planProjectedStart';
    private const VAR_IDENT_PLANOVERRUN                    = 'planOverrun';
    private const VAR_IDENT_VEHICLEDETECTIONACTIVE         = 'vehicleDetectionActive';
    private const VAR_IDENT_CONNECTEDDURATION              = 'connectedDuration';
    private const VAR_IDENT_PHASESENABLED                  = 'phasesEnabled';
    private const VAR_IDENT_PHASESCONFIGURED               = 'phasesConfigured';
    private const VAR_IDENT_SMARTCOSTLIMIT                 = 'smartCostLimit';
    private const VAR_IDENT_PHASESACTIVE                   = 'phasesActive';
    private const VAR_IDENT_PVACTION                       = 'pvAction';
    private const VAR_IDENT_CHARGERFEATUREHEATING          = 'chargerFeatureHeating';
    private const VAR_IDENT_PHASEACTION                    = 'phaseAction';
    private const VAR_IDENT_PVREMAINING                    = 'pvRemaining'; //Bedeutung noch unbekannt
    private const VAR_IDENT_PLANENERGY                     = 'planEnergy';
    private const VAR_IDENT_VEHICLELIMITSOC                = 'vehicleLimitSoc';
    private const VAR_IDENT_LIMITSOC                       = 'limitSoc';
    private const VAR_IDENT_TITLE                          = 'title';
    private const VAR_IDENT_PRIORITY                       = 'priority';
    private const VAR_IDENT_ENABLETHRESHOLD                = 'enableThreshold';
    private const VAR_IDENT_DISABLETHRESHOLD               = 'disableThreshold';
    private const VAR_IDENT_LIMITENERGY                    = 'limitEnergy';
    private const VAR_IDENT_CHARGERPHASES1P3P              = 'chargerPhases1p3p';
    private const VAR_IDENT_CHARGERFEATUREINTEGRATEDDEVICE = 'chargerFeatureIntegratedDevice';
    private const VAR_IDENT_PHASEREMAINING                 = 'phaseRemaining'; //Bedeutung noch unbekannt
    private const VAR_IDENT_VEHICLEODOMETER                = 'vehicleOdometer';
    private const VAR_IDENT_VEHICLENAME                    = 'vehicleName';
    private const VAR_IDENT_MINCURRENT                     = 'minCurrent';
    private const VAR_IDENT_MAXCURRENT                     = 'maxCurrent';
    private const VAR_IDENT_PLANACTIVE                     = 'planActive';


    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent(self::MQTT_SERVER);

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/loadpoints/');
        $this->RegisterPropertyInteger(self::PROP_LOADPOINTID, 1);

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W', []);
        $this->RegisterProfileIntegerEx('evcc.km', '', '', ' km', []);
        $this->RegisterProfileIntegerEx('evcc.Phases', '', '', '', [
            [0, $this->Translate('auto'), '', -1],
            [1, '1', '', -1],
            [3, '3', '', -1]
        ],                              3, 1);
        $this->RegisterProfileStringEx('evcc.Mode', '', '', '', [
            ['off', $this->translate('Off'), '', -1],
            ['pv', $this->translate('Only PV'), '', -1],
            ['minpv', $this->translate('Min+PV'), '', -1],
            ['now', $this->translate('Now'), '', -1],
        ]);
        $this->RegisterProfileStringEx('evcc.Action', '', '', '', [
            ['inactive', $this->translate('inactive'), '', -1],
            ['enable', $this->translate('enable'), '', -1],
            ['disable', $this->translate('disable'), '', -1],
        ]);
        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', [], -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.Energy.Wh', '', '', ' Wh', [], -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.Current', '', '', ' A', [], -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.EUR', '', '', ' €', [], -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.g', '', '', ' g', [], -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.Intensity.100', '', '', ' %', [], 100, 0, 1);

        $this->registerVariables();
    }

    private function registerVariables(): void
    {
        $pos = 0;
        //sorted like https://github.com/evcc-io/evcc/blob/master/assets/js/components/Loadpoint.vue
        //main
        $this->RegisterVariableString(self::VAR_IDENT_TITLE, $this->Translate('Title'), '', ++$pos);
        $this->RegisterVariableString(self::VAR_IDENT_MODE, $this->Translate('Mode'), 'evcc.Mode', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_LIMITSOC, $this->Translate(' Limit SoC'), 'evcc.Intensity.100', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVELIMITSOC, $this->Translate('Effective Limit SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_LIMITENERGY, $this->Translate('Limit Energy'), 'evcc.Energy.Wh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_CHARGEDURATION, $this->Translate('Charge Duration'), '~UnixTimestampTime', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_CHARGING, $this->Translate('Charging'), '~Switch', ++$pos);

        //session
        $this->RegisterVariableFloat(self::VAR_IDENT_SESSIONENERGY, $this->Translate('Session Energy'), 'evcc.Energy.Wh', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SESSIONCO2PERKWH, $this->Translate('Session CO2 per kWh'), 'evcc.g', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SESSIONPRICEPERKWH, $this->Translate('Session Price per kWh'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SESSIONPRICE, $this->Translate('Session Price'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableFloat(
            self::VAR_IDENT_SESSIONSOLARPERCENTAGE,
            $this->Translate('Session Solar Percentage'),
            'evcc.Intensity.100',
            ++$pos
        );

        //charger
        $this->RegisterVariableBoolean(
            self::VAR_IDENT_CHARGERFEATUREINTEGRATEDDEVICE,
            $this->Translate('Charger Feature Integrated Device'),
            '~Switch',
            ++$pos
        );
        $this->RegisterVariableBoolean(self::VAR_IDENT_CHARGERFEATUREHEATING, $this->Translate('Charger Feature Heating'), '~Switch', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_CHARGERPHASES1P3P, $this->Translate('Charger Feature Phases 1P3P'), '~Switch', ++$pos);

        //vehicle
        $this->RegisterVariableBoolean(self::VAR_IDENT_CONNECTED, $this->Translate('Connected'), '~Switch', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_ENABLED, $this->Translate('Enabled'), '~Switch', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_VEHICLEDETECTIONACTIVE, $this->Translate('Vehicle Detection Active'), '~Switch', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_VEHICLERANGE, $this->Translate('Vehicle Range'), 'evcc.km', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_VEHICLESOC, $this->Translate('Vehicle SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableString(self::VAR_IDENT_VEHICLENAME, $this->Translate('Vehicle Name'), '', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_VEHICLELIMITSOC, $this->Translate('Vehicle Limit SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_VEHICLEODOMETER, $this->Translate('Vehicle Odometer'), 'evcc.km', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_PLANACTIVE, $this->Translate('Plan Active'), '~Switch', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PLANPROJECTEDSTART, $this->Translate('Plan Project Start'), '~UnixTimestamp', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PLANOVERRUN, $this->Translate('Plan Overrun'), '~UnixTimestampTime', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_PLANENERGY, $this->Translate('Plan Energy'), 'evcc.Energy.Wh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPLANTIME, $this->Translate('Effective Plantime'), '~UnixTimestamp', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPLANSOC, $this->Translate('Effective Plan SoC'), '~Battery.100', ++$pos);

        //details
        $this->RegisterVariableInteger(self::VAR_IDENT_CHARGEPOWER, $this->Translate('Charge Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_CHARGEDENERGY, $this->Translate('Charged Energy'), 'evcc.Energy.Wh', ++$pos);
        $this->RegisterVariableInteger(
            self::VAR_IDENT_CHARGEREMAININGDURATION,
            $this->Translate('Charge remaining Duration'),
            '~UnixTimestampTime',
            ++$pos
        );

        //other information
        $this->RegisterVariableInteger(self::VAR_IDENT_PHASESCONFIGURED, $this->Translate('Phases Configured'), 'evcc.Phases', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PHASESACTIVE, $this->Translate('Phases Active'), 'evcc.Phases', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_MINCURRENT, $this->Translate('Min Current'), 'evcc.Current', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_MAXCURRENT, $this->Translate('Max Current'), 'evcc.Current', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_CHARGECURRENT, $this->Translate('Charge Current'), 'evcc.Current', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_CONNECTEDDURATION, $this->Translate('Connected Duration'), '~UnixTimestampTime', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_CHARGEREMAININGENERGY, $this->Translate('Charge remaining Energy'), 'evcc.Energy.Wh', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PHASEREMAINING, $this->Translate('Phase Remaining'), '~UnixTimestampTime', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PVREMAINING, $this->Translate('PV Remaining'), '~UnixTimestampTime', ++$pos);
        $this->RegisterVariableString(self::VAR_IDENT_PVACTION, $this->Translate('PV Action'), 'evcc.Action', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SMARTCOSTLIMIT, $this->Translate('Smart Cost Limit'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_SMARTCOSTACTIVE, $this->Translate('Smart Cost Active'), '~Switch', ++$pos);

        //not mentioned/used
        $this->RegisterVariableInteger(self::VAR_IDENT_PHASESENABLED, $this->Translate('Phases Enabled'), 'evcc.Phases', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPRIORITY, $this->Translate('Effective Priority'), '', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_EFFECTIVEMINCURRENT, $this->Translate('Effective min Current'), 'evcc.Current', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_EFFECTIVEMAXCURRENT, $this->Translate('Effective max Current'), 'evcc.Current', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PRIORITY, $this->Translate('Priority'), '', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_ENABLETHRESHOLD, $this->Translate('Enable Threshold'), '', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_DISABLETHRESHOLD, $this->Translate('Disable Threshold'), '', ++$pos);

        $this->EnableAction(self::VAR_IDENT_MODE);
        $this->EnableAction(self::VAR_IDENT_LIMITSOC);
        $this->EnableAction(self::VAR_IDENT_LIMITENERGY);
        $this->EnableAction(self::VAR_IDENT_MINCURRENT);
        $this->EnableAction(self::VAR_IDENT_MAXCURRENT);
        $this->EnableAction(self::VAR_IDENT_PHASESCONFIGURED);
        $this->EnableAction(self::VAR_IDENT_SMARTCOSTLIMIT);
        $this->EnableAction(self::VAR_IDENT_ENABLETHRESHOLD);
        $this->EnableAction(self::VAR_IDENT_DISABLETHRESHOLD);
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

        $topicActions = [
            $MQTTTopic . self::VAR_IDENT_CHARGEPOWER                    => fn() => $this->SetValue(self::VAR_IDENT_CHARGEPOWER, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_SMARTCOSTACTIVE                => fn() => $this->SetValue(self::VAR_IDENT_SMARTCOSTACTIVE, (bool)$payload),
            $MQTTTopic . self::VAR_IDENT_CHARGECURRENT                  => fn() => $this->SetValue(self::VAR_IDENT_CHARGECURRENT, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_SESSIONENERGY                  => fn() => $this->SetValue(self::VAR_IDENT_SESSIONENERGY, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_SESSIONSOLARPERCENTAGE         => fn() => $this->SetValue(
                self::VAR_IDENT_SESSIONSOLARPERCENTAGE,
                (int)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_SESSIONPRICEPERKWH             => fn() => $this->SetValue(
                self::VAR_IDENT_SESSIONPRICEPERKWH,
                (float)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_SESSIONPRICE                   => fn() => $this->SetValue(self::VAR_IDENT_SESSIONPRICE, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_SESSIONCO2PERKWH               => fn() => $this->SetValue(self::VAR_IDENT_SESSIONCO2PERKWH, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_CHARGEDENERGY                  => fn() => $this->SetValue(self::VAR_IDENT_CHARGEDENERGY, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_CHARGEDURATION                 => fn() => $this->SetValue(self::VAR_IDENT_CHARGEDURATION, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVEPRIORITY              => fn() => $this->SetValue(self::VAR_IDENT_EFFECTIVEPRIORITY, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVEPLANTIME              => fn() => $this->SetValue(self::VAR_IDENT_EFFECTIVEPLANTIME, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVEMINCURRENT            => fn() => $this->SetValue(
                self::VAR_IDENT_EFFECTIVEMINCURRENT,
                (float)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVEMAXCURRENT            => fn() => $this->SetValue(
                self::VAR_IDENT_EFFECTIVEMAXCURRENT,
                (float)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVELIMITSOC              => fn() => $this->SetValue(self::VAR_IDENT_EFFECTIVELIMITSOC, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVEPLANSOC               => fn() => $this->SetValue(self::VAR_IDENT_EFFECTIVEPLANSOC, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_CONNECTED                      => fn() => $this->SetValue(self::VAR_IDENT_CONNECTED, (bool)$payload),
            $MQTTTopic . self::VAR_IDENT_CHARGING                       => fn() => $this->SetValue(self::VAR_IDENT_CHARGING, (bool)$payload),
            $MQTTTopic . self::VAR_IDENT_VEHICLESOC                     => fn() => $this->SetValue(self::VAR_IDENT_VEHICLESOC, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_CHARGEREMAININGDURATION        => fn() => $this->SetValue(
                self::VAR_IDENT_CHARGEREMAININGDURATION,
                (int)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_CHARGEREMAININGENERGY          => fn() => $this->SetValue(
                self::VAR_IDENT_CHARGEREMAININGENERGY,
                (int)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_VEHICLERANGE                   => fn() => $this->SetValue(self::VAR_IDENT_VEHICLERANGE, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_ENABLED                        => fn() => $this->SetValue(self::VAR_IDENT_ENABLED, (bool)$payload),
            $MQTTTopic . self::VAR_IDENT_MODE                           => fn() => $this->SetValue(self::VAR_IDENT_MODE, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_PLANPROJECTEDSTART             => fn() => $this->SetValue(self::VAR_IDENT_PLANPROJECTEDSTART, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PLANOVERRUN                    => fn() => $this->SetValue(self::VAR_IDENT_PLANOVERRUN, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PHASESCONFIGURED               => fn() => $this->SetValue(self::VAR_IDENT_PHASESCONFIGURED, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PHASESENABLED                  => fn() => $this->SetValue(self::VAR_IDENT_PHASESENABLED, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PHASEREMAINING                 => fn() => $this->SetValue(self::VAR_IDENT_PHASEREMAINING, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PVACTION                       => fn() => $this->SetValue(self::VAR_IDENT_PVACTION, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_PVREMAINING                    => fn() => $this->SetValue(self::VAR_IDENT_PVREMAINING, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PLANACTIVE                     => fn() => $this->SetValue(self::VAR_IDENT_PLANACTIVE, (bool)$payload),
            $MQTTTopic . self::VAR_IDENT_LIMITSOC                       => fn() => $this->SetValue(self::VAR_IDENT_LIMITSOC, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_ENABLETHRESHOLD                => fn() => $this->SetValue(self::VAR_IDENT_ENABLETHRESHOLD, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_DISABLETHRESHOLD               => fn() => $this->SetValue(self::VAR_IDENT_DISABLETHRESHOLD, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_MINCURRENT                     => fn() => $this->SetValue(self::VAR_IDENT_MINCURRENT, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_MAXCURRENT                     => fn() => $this->SetValue(self::VAR_IDENT_MAXCURRENT, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_LIMITENERGY                    => fn() => $this->SetValue(self::VAR_IDENT_LIMITENERGY, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_VEHICLENAME                    => fn() => $this->SetValue(self::VAR_IDENT_VEHICLENAME, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_VEHICLEODOMETER                => fn() => $this->SetValue(self::VAR_IDENT_VEHICLEODOMETER, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_VEHICLEDETECTIONACTIVE                => fn() => $this->SetValue(self::VAR_IDENT_VEHICLEDETECTIONACTIVE, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_CHARGERFEATUREINTEGRATEDDEVICE => fn() => $this->SetValue(
                self::VAR_IDENT_CHARGERFEATUREINTEGRATEDDEVICE,
                (bool)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_CHARGERPHASES1P3P              => fn() => $this->SetValue(self::VAR_IDENT_CHARGERPHASES1P3P, (bool)$payload),
            $MQTTTopic . self::VAR_IDENT_PRIORITY                       => fn() => $this->SetValue(self::VAR_IDENT_PRIORITY, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_TITLE                          => fn() => $this->SetValue(self::VAR_IDENT_TITLE, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_VEHICLELIMITSOC                => fn() => $this->SetValue(self::VAR_IDENT_VEHICLELIMITSOC, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PLANENERGY                     => fn() => $this->SetValue(self::VAR_IDENT_PLANENERGY, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_PHASEACTION                   => fn() => $this->SetValue(self::VAR_IDENT_PHASEACTION, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_PHASESACTIVE                   => fn() => $this->SetValue(self::VAR_IDENT_PHASESACTIVE, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_CONNECTEDDURATION              => fn() => $this->SetValue(self::VAR_IDENT_CONNECTEDDURATION, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_SMARTCOSTLIMIT              => fn() => $this->SetValue(self::VAR_IDENT_SMARTCOSTLIMIT, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_CHARGERFEATUREHEATING              => fn() => $this->SetValue(self::VAR_IDENT_CHARGERFEATUREHEATING, (bool)$payload),
        ];

        if (str_ends_with($topic, '/set')) {
            $this->SendDebug(__FUNCTION__, 'received: ' . $topic, 0);
        } elseif (in_array($topic, [
            $MQTTTopic . 'chargerPhysicalPhases',
            $MQTTTopic . 'chargerIcon',
            $MQTTTopic . 'vehicleClimaterActive',
            $MQTTTopic . 'planTime',
        ])){
            $this->SendDebug(__FUNCTION__, 'ignored: ' . $topic, 0);
        }
        elseif (array_key_exists($topic, $topicActions)) {
            $topicActions[$topic]();
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected topic: ' . $topic, 0);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $mqttTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_LOADPOINTID);
        switch ($Ident) {
            case self::VAR_IDENT_ENABLED:
                $this->mqttCommand($mqttTopic . '/' . $Ident . '/set', var_export($Value, true));
                break;
            case self::VAR_IDENT_MODE:
            case self::VAR_IDENT_LIMITSOC:
            case self::VAR_IDENT_LIMITENERGY:
            case self::VAR_IDENT_MINCURRENT:
            case self::VAR_IDENT_MAXCURRENT:
            case self::VAR_IDENT_SMARTCOSTLIMIT:
            case self::VAR_IDENT_ENABLETHRESHOLD:
            case self::VAR_IDENT_DISABLETHRESHOLD:
                $this->mqttCommand($mqttTopic . '/' . $Ident . '/set', (string)$Value);
                break;
            case self::VAR_IDENT_PHASESCONFIGURED:
                $this->mqttCommand($mqttTopic . '/phases/set', (string)$Value); //die zu nutzenden Phasen werden über das Topic 'phases' gesetzt.
            default:
                $this->LogMessage('Invalid Action', KL_WARNING);
                break;
        }
    }

}
