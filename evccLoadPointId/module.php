<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/Themes.php';

use evccMQTT\Themes\LoadPointId;

use evccMQTT\Themes\LoadPointVariableIdent;

use const evccMQTT\Themes\IPS_PRESENTATION;
use const evccMQTT\Themes\IPS_VAR_ACTION;
use const evccMQTT\Themes\IPS_VAR_IDENT;
use const evccMQTT\Themes\IPS_VAR_NAME;
use const evccMQTT\Themes\IPS_VAR_TYPE;
use const evccMQTT\Themes\IPS_VAR_VALUE;

require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccLoadPointId extends IPSModuleStrict
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const string PROP_TOPIC       = 'topic';
    private const string PROP_LOADPOINTID = 'loadPointId';

    //private const string VAR_IDENT_CHARGEPOWER                    = 'chargePower';
    //private const string VAR_IDENT_SMARTCOSTACTIVE                = 'smartCostActive';
    //private const string VAR_IDENT_CHARGECURRENT                  = 'chargeCurrent';
    //private const string VAR_IDENT_SESSIONENERGY                  = 'sessionEnergy';
    //private const string VAR_IDENT_SESSIONSOLARPERCENTAGE         = 'sessionSolarPercentage';
    //private const string VAR_IDENT_SESSIONPRICEPERKWH             = 'sessionPricePerKWh';
    //private const string VAR_IDENT_SESSIONPRICE                   = 'sessionPrice';
    //private const string VAR_IDENT_SESSIONCO2PERKWH               = 'sessionCo2PerKWh';
    //private const string VAR_IDENT_CHARGEDENERGY                  = 'chargedEnergy';
    //private const string VAR_IDENT_CHARGEDURATION                 = 'chargeDuration';
    private const string VAR_IDENT_EFFECTIVEPRIORITY              = 'effectivePriority';
    //private const string VAR_IDENT_EFFECTIVEPLANTIME              = 'effectivePlanTime';
    //private const string VAR_IDENT_EFFECTIVEPLANSOC               = 'effectivePlanSoc';
    private const string VAR_IDENT_EFFECTIVEMINCURRENT            = 'effectiveMinCurrent';
    private const string VAR_IDENT_EFFECTIVEMAXCURRENT            = 'effectiveMaxCurrent';
    private const string VAR_IDENT_EFFECTIVELIMITSOC              = 'effectiveLimitSoc';
    //private const string VAR_IDENT_CONNECTED                      = 'connected';
    //private const string VAR_IDENT_CHARGING                       = 'charging';
    //private const string VAR_IDENT_VEHICLESOC                     = 'vehicleSoc';
    //private const string VAR_IDENT_CHARGEREMAININGDURATION        = 'chargeRemainingDuration';
    //private const string VAR_IDENT_CHARGEREMAININGENERGY          = 'chargeRemainingEnergy';
    //private const string VAR_IDENT_VEHICLERANGE                   = 'vehicleRange';
    //private const string VAR_IDENT_ENABLED                        = 'enabled';
    //private const string VAR_IDENT_MODE                           = 'mode';
    //private const string VAR_IDENT_PLANPROJECTEDSTART             = 'planProjectedStart';
    //private const string VAR_IDENT_PLANOVERRUN                    = 'planOverrun';
    //private const string VAR_IDENT_VEHICLEDETECTIONACTIVE         = 'vehicleDetectionActive';
    private const string VAR_IDENT_CONNECTEDDURATION              = 'connectedDuration';
    private const string VAR_IDENT_PHASESENABLED                  = 'phasesEnabled';
    //private const string VAR_IDENT_PHASESCONFIGURED               = 'phasesConfigured';
    private const string VAR_IDENT_SMARTCOSTLIMIT                 = 'smartCostLimit';
    //private const string VAR_IDENT_PHASESACTIVE                   = 'phasesActive';
    //private const string VAR_IDENT_PVACTION                       = 'pvAction';
    //private const string VAR_IDENT_CHARGERFEATUREHEATING          = 'chargerFeatureHeating';
    //private const string VAR_IDENT_PHASEACTION                    = 'phaseAction';
    //private const string VAR_IDENT_PVREMAINING                    = 'pvRemaining';
    //private const string VAR_IDENT_PLANENERGY                     = 'planEnergy';
    //private const string VAR_IDENT_VEHICLELIMITSOC                = 'vehicleLimitSoc';
    //private const string VAR_IDENT_LIMITSOC                       = 'limitSoc';
    //private const string VAR_IDENT_TITLE                          = 'title';
    //private const string VAR_IDENT_PRIORITY                       = 'priority';
    //private const string VAR_IDENT_ENABLETHRESHOLD                = 'enableThreshold';
    //private const string VAR_IDENT_DISABLETHRESHOLD               = 'disableThreshold';
    private const string VAR_IDENT_LIMITENERGY                    = 'limitEnergy';
    //private const string VAR_IDENT_CHARGERPHASES1P3P              = 'chargerPhases1p3p';
    //private const string VAR_IDENT_CHARGERFEATUREINTEGRATEDDEVICE = 'chargerFeatureIntegratedDevice';
    //private const string VAR_IDENT_PHASEREMAINING                 = 'phaseRemaining'; //Bedeutung ist noch unbekannt
    //private const string VAR_IDENT_VEHICLEODOMETER                = 'vehicleOdometer';
    //private const string VAR_IDENT_VEHICLENAME                    = 'vehicleName';
    //private const string VAR_IDENT_MINCURRENT                     = 'minCurrent';
    //private const string VAR_IDENT_MAXCURRENT                     = 'maxCurrent';
    //private const string VAR_IDENT_PLANACTIVE                     = 'planActive';

    private const string TO_BE_CHECKED = '? ';

    private const array IGNORED_ELEMENTS = [
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

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W');
        $this->RegisterProfileIntegerEx('evcc.km', '', '', ' km');
        $this->RegisterProfileIntegerEx('evcc.Phases', '', '', '', [
            [0, $this->Translate('auto'), '', -1],
            [1, '1', '', -1],
            [3, '3', '', -1]
        ]);
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
        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', -1, -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.LimitEnergy.kWh', '', '', ' kWh', 0, 100, 0, 1);
        $this->RegisterProfileFloatEx('evcc.Energy.Wh', '', '', ' Wh', -1, -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.Current', '', '', ' A', -1, -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.minCurrent', '', '', ' A', 1, 16, 1);
        $this->RegisterProfileFloatEx('evcc.maxCurrent', '', '', ' A', 1, 64, 1);
        $this->RegisterProfileFloatEx('evcc.EUR', '', '', ' €', -1, -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.EUR.3', '', '', ' €', -1, 1, 0.001, 3);
        $this->RegisterProfileFloatEx('evcc.g', '', '', ' g', -1, -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.Intensity.100', '', '', ' %', 0, 100, 1, 1);

        $this->registerVariables();
        $this->enableActions();
    }

    private function registerVariables(): void
    {
        $pos = 0;
        //sorted like https://github.com/evcc-io/evcc/blob/master/assets/js/components/Loadpoint.vue
        //main


        foreach (LoadPointVariableIdent::idents() as $ident) {
            $VariableValues = LoadPointId::getIPSVariable($ident);
            $this->SendDebug(__FUNCTION__.'!!!!', sprintf('%s, VariableValues: %s', $ident, print_r($VariableValues, true)), 0);

            switch ($VariableValues[IPS_VAR_TYPE]) {
                case VARIABLETYPE_INTEGER:
                    $ret = $this->RegisterVariableInteger(
                        $VariableValues[IPS_VAR_IDENT],
                        $this->Translate($VariableValues[IPS_VAR_NAME]),
                        $VariableValues[IPS_PRESENTATION],
                        ++$pos,
                    );
                    break;
                case VARIABLETYPE_FLOAT:
                    $ret = $this->RegisterVariableFloat(
                        $VariableValues[IPS_VAR_IDENT],
                        $this->Translate($VariableValues[IPS_VAR_NAME]),
                        $VariableValues[IPS_PRESENTATION],
                        ++$pos,
                    );

                    break;
                case VARIABLETYPE_STRING:
                    $ret = $this->RegisterVariableString(
                        $VariableValues[IPS_VAR_IDENT],
                        $this->Translate($VariableValues[IPS_VAR_NAME]),
                        $VariableValues[IPS_PRESENTATION],
                        ++$pos,
                    );
                    break;
                case VARIABLETYPE_BOOLEAN:
                    $ret = $this->RegisterVariableBoolean(
                        $VariableValues[IPS_VAR_IDENT],
                        $this->Translate($VariableValues[IPS_VAR_NAME]),
                        $VariableValues[IPS_PRESENTATION],
                        ++$pos
                    );
                    break;
            }

            $this->SendDebug(__FUNCTION__.'!!!!', sprintf('ret: %s', (int) $ret), 0);
            if ($VariableValues[IPS_VAR_ACTION]) {
                $this->EnableAction($ident);
            }
        }


        //$this->RegisterVariableString(self::VAR_IDENT_TITLE, $this->Translate('Title'), '', ++$pos);
        //$this->RegisterVariableString(self::VAR_IDENT_MODE, $this->Translate('Mode'), 'evcc.Mode', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_LIMITSOC, $this->Translate(' Limit SoC'), 'evcc.Intensity.100', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVELIMITSOC, self::TO_BE_CHECKED . $this->Translate('Effective Limit SoC'), '~Battery.100', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_LIMITENERGY, $this->Translate('Limit Energy'), 'evcc.LimitEnergy.kWh', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_CHARGEDURATION, $this->Translate('Charge Duration'), '', ++$pos);
        //$this->RegisterVariableBoolean(self::VAR_IDENT_CHARGING, $this->Translate('Charging'), '~Switch', ++$pos);

        //session
        //$this->RegisterVariableFloat(self::VAR_IDENT_SESSIONENERGY, $this->Translate('Session Energy'), 'evcc.Energy.Wh', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_SESSIONCO2PERKWH, $this->Translate('Session CO2 per kWh'), 'evcc.g', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_SESSIONPRICEPERKWH, $this->Translate('Session Price per kWh'), 'evcc.EUR', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_SESSIONPRICE, $this->Translate('Session Price'), 'evcc.EUR', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_SESSIONSOLARPERCENTAGE, $this->Translate('Session Solar Percentage'), 'evcc.Intensity.100', ++$pos);

        //charger
        //$this->RegisterVariableBoolean(self::VAR_IDENT_CHARGERFEATUREINTEGRATEDDEVICE, self::TO_BE_CHECKED . $this->Translate('Charger Feature Integrated Device'), '~Switch', ++$pos);
        //$this->RegisterVariableBoolean(self::VAR_IDENT_CHARGERFEATUREHEATING, self::TO_BE_CHECKED . $this->Translate('Charger Feature Heating'), '~Switch', ++$pos);
        //$this->RegisterVariableBoolean(self::VAR_IDENT_CHARGERPHASES1P3P, $this->Translate('Charger Feature Phases 1P3P'), '~Switch', ++$pos);
        //vehicle
        //$this->RegisterVariableBoolean(self::VAR_IDENT_CONNECTED, $this->Translate('Connected'), '~Switch', ++$pos);
        //$this->RegisterVariableBoolean(self::VAR_IDENT_ENABLED, $this->Translate('Enabled'), '~Switch', ++$pos);
        //$this->RegisterVariableBoolean(self::VAR_IDENT_VEHICLEDETECTIONACTIVE, $this->Translate('Vehicle Detection Active'), '~Switch', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_VEHICLERANGE, $this->Translate('Vehicle Range'), 'evcc.km', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_VEHICLESOC, $this->Translate('Vehicle SoC'), '~Battery.100', ++$pos);
        //$this->RegisterVariableString(self::VAR_IDENT_VEHICLENAME, $this->Translate('Vehicle Name'), '', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_VEHICLELIMITSOC, $this->Translate('Vehicle Limit SoC'), '~Battery.100', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_VEHICLEODOMETER, $this->Translate('Vehicle Odometer'), 'evcc.km', ++$pos);
        //$this->RegisterVariableBoolean(self::VAR_IDENT_PLANACTIVE, $this->Translate('Plan Active'), '~Switch', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_PLANPROJECTEDSTART, $this->Translate('Plan Project Start'), '~UnixTimestamp', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_PLANOVERRUN, $this->Translate('Plan Overrun'), '~UnixTimestampTime', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_PLANENERGY, $this->Translate('Plan Energy'), 'evcc.Energy.Wh', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPLANTIME, $this->Translate('Effective Plantime'), '~UnixTimestamp', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPLANSOC, $this->Translate('Effective Plan SoC'), '~Battery.100', ++$pos);

        //details
        //$this->RegisterVariableInteger(self::VAR_IDENT_CHARGEPOWER, $this->Translate('Charge Power'), 'evcc.Power', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_CHARGEDENERGY, $this->Translate('Charged Energy'), 'evcc.Energy.Wh', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_CHARGEREMAININGDURATION, $this->Translate('Charge remaining Duration'), '~UnixTimestampTime', ++$pos);

        //other information
        //$this->RegisterVariableInteger(self::VAR_IDENT_PHASESCONFIGURED, $this->Translate('Phases Configured'), 'evcc.Phases', ++$pos);
        //$this->RegisterVariableString(self::VAR_IDENT_PHASEACTION, $this->Translate('Phase Action'), '', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_PHASESACTIVE, $this->Translate('Phases Active'), 'evcc.Phases', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_MINCURRENT, $this->Translate('Min Current'), 'evcc.minCurrent', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_MAXCURRENT, $this->Translate('Max Current'), 'evcc.maxCurrent', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_CHARGECURRENT, $this->Translate('Charge Current'), 'evcc.Current', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_CONNECTEDDURATION, $this->Translate('Connected Duration'), '~UnixTimestampTime', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_CHARGEREMAININGENERGY, $this->Translate('Charge remaining Energy'), 'evcc.Energy.Wh', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_PHASEREMAINING, self::TO_BE_CHECKED . $this->Translate('Phase Remaining'), '~UnixTimestampTime', ++$pos);
        //$this->RegisterVariableInteger(self::VAR_IDENT_PVREMAINING, self::TO_BE_CHECKED . $this->Translate('PV Remaining'), '~UnixTimestampTime', ++$pos);
        //$this->RegisterVariableString(self::VAR_IDENT_PVACTION, self::TO_BE_CHECKED . $this->Translate('PV Action'), 'evcc.Action', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_SMARTCOSTLIMIT, $this->Translate('Smart Cost Limit'), 'evcc.EUR.3', ++$pos);
        //$this->RegisterVariableBoolean(self::VAR_IDENT_SMARTCOSTACTIVE, self::TO_BE_CHECKED . $this->Translate('Smart Cost Active'), '~Switch', ++$pos);

        //not mentioned/used
        $this->RegisterVariableInteger(
            self::VAR_IDENT_PHASESENABLED,
            self::TO_BE_CHECKED . $this->Translate('Phases Enabled'),
            'evcc.Phases',
            ++$pos
        );
        $this->RegisterVariableInteger(self::VAR_IDENT_EFFECTIVEPRIORITY, self::TO_BE_CHECKED . $this->Translate('Effective Priority'), '', ++$pos);
        $this->RegisterVariableFloat(
            self::VAR_IDENT_EFFECTIVEMINCURRENT,
            self::TO_BE_CHECKED . $this->Translate('Effective min Current'),
            'evcc.Current',
            ++$pos
        );
        $this->RegisterVariableFloat(
            self::VAR_IDENT_EFFECTIVEMAXCURRENT,
            self::TO_BE_CHECKED . $this->Translate('Effective max Current'),
            'evcc.Current',
            ++$pos
        );
        //$this->RegisterVariableInteger(self::VAR_IDENT_PRIORITY, self::TO_BE_CHECKED . $this->Translate('Priority'), '', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_ENABLETHRESHOLD, self::TO_BE_CHECKED . $this->Translate('Enable Threshold'), '', ++$pos);
        //$this->RegisterVariableFloat(self::VAR_IDENT_DISABLETHRESHOLD, self::TO_BE_CHECKED . $this->Translate('Disable Threshold'), '', ++$pos);
    }

    private function enableActions(): void
    {
        // see listenLoadpointSetters in https://github.com/evcc-io/evcc/blob/master/server/mqtt.go
        //$this->EnableAction(self::VAR_IDENT_MODE);
        //$this->EnableAction(self::VAR_IDENT_LIMITSOC);
        $this->EnableAction(self::VAR_IDENT_LIMITENERGY);
        //$this->EnableAction(self::VAR_IDENT_MINCURRENT);
        //$this->EnableAction(self::VAR_IDENT_MAXCURRENT);
        //$this->EnableAction(self::VAR_IDENT_PHASESCONFIGURED);
        $this->EnableAction(self::VAR_IDENT_SMARTCOSTLIMIT);
        //$this->EnableAction(self::VAR_IDENT_ENABLETHRESHOLD);
        //$this->EnableAction(self::VAR_IDENT_DISABLETHRESHOLD);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Setze Filter für ReceiveData
        $MQTTTopic          = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_LOADPOINTID) . '/';
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    private function shouldBeIgnored(string $lastElement, string $penultimateElement, string $topic, string $MQTTTopic): bool
    {
        return in_array($lastElement, self::IGNORED_ELEMENTS)
               || is_numeric($lastElement);
    }


    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_LOADPOINTID) . '/';

        if (empty($MQTTTopic)) {
            return '';
        }

        $this->SendDebug(__FUNCTION__, 'JSONString: ' . $JSONString, 0);

        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = hex2bin($data['Payload']);

        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s', $topic, $payload), 0);

        $topicActions = [
            //$MQTTTopic . self::VAR_IDENT_CHARGEPOWER                    => fn() => $this->SetValue(self::VAR_IDENT_CHARGEPOWER, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_SMARTCOSTACTIVE                => fn() => $this->SetValue(self::VAR_IDENT_SMARTCOSTACTIVE, $payload === 'true'),
            //$MQTTTopic . self::VAR_IDENT_CHARGECURRENT                  => fn() => $this->SetValue(self::VAR_IDENT_CHARGECURRENT, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_SESSIONENERGY                  => fn() => $this->SetValue(self::VAR_IDENT_SESSIONENERGY, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_SESSIONSOLARPERCENTAGE         => fn() => $this->SetValue(self::VAR_IDENT_SESSIONSOLARPERCENTAGE, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_SESSIONPRICEPERKWH             => fn() => $this->SetValue( self::VAR_IDENT_SESSIONPRICEPERKWH, (float)$payload ),
            //$MQTTTopic . self::VAR_IDENT_SESSIONPRICE                   => fn() => $this->SetValue(self::VAR_IDENT_SESSIONPRICE, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_SESSIONCO2PERKWH               => fn() => $this->SetValue(self::VAR_IDENT_SESSIONCO2PERKWH, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_CHARGEDENERGY                  => fn() => $this->SetValue(self::VAR_IDENT_CHARGEDENERGY, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_CHARGEDURATION                 => fn() => $this->SetValue(self::VAR_IDENT_CHARGEDURATION, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVEPRIORITY              => fn() => $this->SetValue(self::VAR_IDENT_EFFECTIVEPRIORITY, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_EFFECTIVEPLANTIME              => fn() => $this->SetValue(self::VAR_IDENT_EFFECTIVEPLANTIME, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVEMINCURRENT            => fn() => $this->SetValue(
                self::VAR_IDENT_EFFECTIVEMINCURRENT,
                (float)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVEMAXCURRENT            => fn() => $this->SetValue(
                self::VAR_IDENT_EFFECTIVEMAXCURRENT,
                (float)$payload
            ),
            $MQTTTopic . self::VAR_IDENT_EFFECTIVELIMITSOC              => fn() => $this->SetValue(self::VAR_IDENT_EFFECTIVELIMITSOC, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_EFFECTIVEPLANSOC               => fn() => $this->SetValue(self::VAR_IDENT_EFFECTIVEPLANSOC, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_CONNECTED                      => fn() => $this->SetValue(self::VAR_IDENT_CONNECTED, $payload === 'true'),
            //$MQTTTopic . self::VAR_IDENT_CHARGING                       => fn() => $this->SetValue(self::VAR_IDENT_CHARGING, $payload === 'true'),
            //$MQTTTopic . self::VAR_IDENT_VEHICLESOC                     => fn() => $this->SetValue(self::VAR_IDENT_VEHICLESOC, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_CHARGEREMAININGDURATION        => fn() => $this->SetValue(self::VAR_IDENT_CHARGEREMAININGDURATION, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_CHARGEREMAININGENERGY          => fn() => $this->SetValue(self::VAR_IDENT_CHARGEREMAININGENERGY, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_VEHICLERANGE                   => fn() => $this->SetValue(self::VAR_IDENT_VEHICLERANGE, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_ENABLED                        => fn() => $this->SetValue(self::VAR_IDENT_ENABLED, $payload === 'true'),
            //$MQTTTopic . self::VAR_IDENT_MODE                           => fn() => $this->SetValue(self::VAR_IDENT_MODE, (string)$payload),
            //$MQTTTopic . self::VAR_IDENT_PLANPROJECTEDSTART             => fn() => $this->SetValue(self::VAR_IDENT_PLANPROJECTEDSTART, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_PLANOVERRUN                    => fn() => $this->SetValue(self::VAR_IDENT_PLANOVERRUN, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_PHASESCONFIGURED               => fn() => $this->SetValue(self::VAR_IDENT_PHASESCONFIGURED, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PHASESENABLED                  => fn() => $this->SetValue(self::VAR_IDENT_PHASESENABLED, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_PHASEREMAINING                 => fn() => $this->SetValue(self::VAR_IDENT_PHASEREMAINING, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_PVACTION                       => fn() => $this->SetValue(self::VAR_IDENT_PVACTION, (string)$payload),
            //$MQTTTopic . self::VAR_IDENT_PVREMAINING                    => fn() => $this->SetValue(self::VAR_IDENT_PVREMAINING, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_PLANACTIVE                     => fn() => $this->SetValue(self::VAR_IDENT_PLANACTIVE, $payload === 'true'),
            //$MQTTTopic . self::VAR_IDENT_LIMITSOC                       => fn() => $this->SetValue(self::VAR_IDENT_LIMITSOC, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_ENABLETHRESHOLD                => fn() => $this->SetValue(self::VAR_IDENT_ENABLETHRESHOLD, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_DISABLETHRESHOLD               => fn() => $this->SetValue(self::VAR_IDENT_DISABLETHRESHOLD, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_MINCURRENT                     => fn() => $this->SetValue(self::VAR_IDENT_MINCURRENT, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_MAXCURRENT                     => fn() => $this->SetValue(self::VAR_IDENT_MAXCURRENT, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_LIMITENERGY                    => fn() => $this->SetValue(self::VAR_IDENT_LIMITENERGY, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_VEHICLENAME                    => fn() => $this->SetValue(self::VAR_IDENT_VEHICLENAME, (string)$payload),
            //$MQTTTopic . self::VAR_IDENT_VEHICLEODOMETER                => fn() => $this->SetValue(self::VAR_IDENT_VEHICLEODOMETER, (string)$payload),
            //$MQTTTopic . self::VAR_IDENT_VEHICLEDETECTIONACTIVE         => fn() => $this->SetValue(self::VAR_IDENT_VEHICLEDETECTIONACTIVE, (string)$payload),
            //$MQTTTopic . self::VAR_IDENT_CHARGERFEATUREINTEGRATEDDEVICE => fn() => $this->SetValue(self::VAR_IDENT_CHARGERFEATUREINTEGRATEDDEVICE, $payload === 'true'),
            //$MQTTTopic . self::VAR_IDENT_CHARGERPHASES1P3P              => fn() => $this->SetValue(self::VAR_IDENT_CHARGERPHASES1P3P, $payload === 'true'),
            //$MQTTTopic . self::VAR_IDENT_PRIORITY                       => fn() => $this->SetValue(self::VAR_IDENT_PRIORITY, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_TITLE                          => fn() => $this->SetValue(self::VAR_IDENT_TITLE, (string)$payload),
            //$MQTTTopic . self::VAR_IDENT_VEHICLELIMITSOC                => fn() => $this->SetValue(self::VAR_IDENT_VEHICLELIMITSOC, (int)$payload),
            //$MQTTTopic . self::VAR_IDENT_PLANENERGY                     => fn() => $this->SetValue(self::VAR_IDENT_PLANENERGY, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_PHASEACTION                    => fn() => $this->SetValue(self::VAR_IDENT_PHASEACTION, (string)$payload),
            //$MQTTTopic . self::VAR_IDENT_PHASESACTIVE                   => fn() => $this->SetValue(self::VAR_IDENT_PHASESACTIVE, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_CONNECTEDDURATION              => fn() => $this->SetValue(self::VAR_IDENT_CONNECTEDDURATION, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_SMARTCOSTLIMIT                 => fn() => $this->SetValue(self::VAR_IDENT_SMARTCOSTLIMIT, (float)$payload),
            //$MQTTTopic . self::VAR_IDENT_CHARGERFEATUREHEATING          => fn() => $this->SetValue(self::VAR_IDENT_CHARGERFEATUREHEATING, $payload === 'true'),
        ];

        $mqttSubTopics      = $this->getMqttSubTopics($topic);
        $lastElement        = $this->getLastElement($mqttSubTopics);
        $penultimateElement = $this->getPenultimateElement($mqttSubTopics);

        if ($this->isReceivedSetTopic($topic)) {
            //$this->SendDebug(__FUNCTION__, 'received: ' . $topic, 0);
            return '';
        }

        if ($this->shouldBeIgnored($lastElement, $penultimateElement, $topic, $MQTTTopic)) {
            $this->SendDebug(__FUNCTION__, 'ignored: ' . $topic, 0);
        } elseif (array_key_exists($topic, $topicActions)) {
            $topicActions[$topic]();
        } elseif (LoadPointId::propertyIsValid($lastElement)) {
            $this->SendDebug(__FUNCTION__ . '!!!!', sprintf('topic: %s, payload: %s', $topic, $payload), 0);
            $VariableValues = LoadPointId::getIPSVariable($lastElement, $payload);
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
        $mqttTopic = $this->ReadPropertyString(self::PROP_TOPIC) . $this->ReadPropertyInteger(self::PROP_LOADPOINTID);
        switch ($Ident) {
            case LoadPointVariableIdent::Enabled->value:
                $this->mqttCommand($mqttTopic . '/' . $Ident . '/set', var_export($Value, true));
                break;
            case LoadPointVariableIdent::Mode->value:
            case LoadPointVariableIdent::LimitSoc->value:
            case self::VAR_IDENT_LIMITENERGY:
            case LoadPointVariableIdent::MinCurrent->value:
            case LoadPointVariableIdent::MaxCurrent->value:
            case LoadPointVariableIdent::EnableThreshold->value:
            case LoadPointVariableIdent::DisableThreshold->value:
                $this->mqttCommand($mqttTopic . '/' . $Ident . '/set', (string)$Value);
                break;
            case LoadPointVariableIdent::PhasesConfigured->value:
                $this->mqttCommand($mqttTopic . '/phases/set', (string)$Value); //die zu nutzenden Phasen werden über das Topic 'phases' gesetzt.
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
