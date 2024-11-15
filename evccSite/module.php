<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSite extends IPSModuleStrict
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const PROP_TOPIC = 'topic';

    private const VAR_IDENT_GRIDCONFIGURED          = 'gridConfigured';
    private const VAR_IDENT_BATTERYDISCHARGECONTROL = 'batteryDischargeControl';
    private const VAR_IDENT_BATTERYGRIDCHARGEACTIVE = 'batteryGridChargeActive';
    private const VAR_IDENT_BATTERYGRIDCHARGELIMIT  = 'batteryGridChargeLimit';
    private const VAR_IDENT_PVPOWER                 = 'pvPower';
    private const VAR_IDENT_PVENERGY                = 'pvEnergy';
    private const VAR_IDENT_BATTERYCAPACITY         = 'batteryCapacity';
    private const VAR_IDENT_BATTERYSOC              = 'batterySoc';
    private const VAR_IDENT_BATTERYPOWER            = 'batteryPower';
    private const VAR_IDENT_BATTERYENERGY           = 'batteryEnergy';
    private const VAR_IDENT_BATTERYMODE             = 'batteryMode';
    private const VAR_IDENT_GRIDPOWER               = 'gridPower';
    private const VAR_IDENT_GRIDCURRENTS            = 'gridCurrents';
    private const VAR_IDENT_GRIDENERGY              = 'gridEnergy';
    private const VAR_IDENT_HOMEPOWER               = 'homePower';
    private const VAR_IDENT_AUXPOWER                = 'auxPower';
    private const VAR_IDENT_PRIORITYSOC             = 'prioritySoc';
    private const VAR_IDENT_BUFFERSOC               = 'bufferSoc';
    private const VAR_IDENT_BUFFERSTARTSOC          = 'bufferStartSoc';
    private const VAR_IDENT_SITETITLE               = 'siteTitle';
    private const VAR_IDENT_CURRENCY                = 'currency';
    private const VAR_IDENT_GREENSHAREHOME          = 'greenShareHome';
    private const VAR_IDENT_GREENSHARELOADPOINTS    = 'greenShareLoadpoints';
    private const VAR_IDENT_TARIFFFEEDIN            = 'tariffFeedIn';
    private const VAR_IDENT_TARIFFGRID              = 'tariffGrid';
    private const VAR_IDENT_TARIFFCO2               = 'tariffCo2';
    private const VAR_IDENT_TARIFFPRICEHOME         = 'tariffPriceHome';
    private const VAR_IDENT_TARIFFCO2HOME           = 'tariffCo2Home';
    private const VAR_IDENT_TARIFFPRICELOADPOINTS   = 'tariffPriceLoadpoints';
    private const VAR_IDENT_TARIFFCO2LOADPOINTS     = 'tariffCo2Loadpoints';
    private const VAR_IDENT_VERSION                 = 'version';
    private const VAR_IDENT_AVAILABLEVERSION        = 'availableVersion';
    private const VAR_IDENT_SMARTCOSTTYPE           = 'smartCostType';
    //https://docs.evcc.io/en/docs/reference/configuration/site#residualpower
    private const VAR_IDENT_RESIDUALPOWER           = 'residualPower';

    private const IGNORED_ELEMENTS = ['pv', 'aux', 'battery', 'fatal', 'gridPowers', 'l1', 'l2', 'l3'];
    private const STATISTICS_FLAG  = 'statistics';

    public function Create():void
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent(self::MQTT_SERVER);
        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/');

        $this->RegisterProfileIntegerEx('evcc.Power', '', '', ' W', []);
        $this->RegisterProfileStringEx('evcc.Battery.Mode', '', '', '', [
            ['unknown', $this->translate('unknown'), '', -1],
        ]);

        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', [], -1, 0, 1);
        $this->RegisterProfileFloatEx('evcc.Intensity.100', '', '', ' %', [], 100, 0, 1);
        $this->RegisterProfileFloatEx('evcc.EUR', '', '', ' €', [], -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.g', '', '', ' g', [], -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.EUR.3', '', '', ' €', [], -1, 0, 3);

        $this->registerVariables();
        $this->enableActions();
    }

    private function registerVariables(): void
    {
        $pos = 0;
        //details
        $this->RegisterVariableBoolean(self::VAR_IDENT_GRIDCONFIGURED, $this->Translate('Grid Configured'), '~Switch', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_GRIDPOWER, $this->Translate('Grid Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_HOMEPOWER, $this->Translate('Home Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_AUXPOWER, $this->Translate('Aux Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_PVPOWER, $this->Translate('PV Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_BATTERYPOWER, $this->Translate('Battery Power'), 'evcc.Power', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_BATTERYSOC, $this->Translate('Battery SoC'), '~Battery.100', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_BATTERYDISCHARGECONTROL, $this->Translate('Battery Discharge Control'), '~Switch', ++$pos);
        $this->RegisterVariableString(self::VAR_IDENT_BATTERYMODE, $this->Translate('Battery Mode'), 'evcc.Battery.Mode', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_PRIORITYSOC, $this->Translate('Priority SoC'), 'evcc.Intensity.100', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_BUFFERSOC, $this->Translate('Buffer SoC'), 'evcc.Intensity.100', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_BUFFERSTARTSOC, $this->Translate('Buffer Start SoC'), 'evcc.Intensity.100', ++$pos);
        $this->RegisterVariableString(self::VAR_IDENT_SITETITLE, $this->Translate('Site Title'), '', ++$pos);
        $this->RegisterVariableBoolean(self::VAR_IDENT_BATTERYGRIDCHARGEACTIVE, $this->Translate('Battery Gridcharge Active'), '~Switch', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_BATTERYGRIDCHARGELIMIT, $this->Translate('Battery Gridcharge Limit'), 'evcc.EUR.3', ++$pos);


        //tariffs
        $this->RegisterVariableString(self::VAR_IDENT_CURRENCY, $this->Translate('Currency'), '', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFFEEDIN, $this->Translate('Tariff Feed In'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFGRID, $this->Translate('Tariff Grid'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFCO2, $this->Translate('Tariff CO2'), 'evcc.g', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFPRICEHOME, $this->Translate('Tariff Price Home'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFCO2HOME, $this->Translate('Tariff CO2 Home'), 'evcc.g', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFPRICELOADPOINTS, $this->Translate('Tariff Price Loadpoints'), 'evcc.EUR', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_TARIFFCO2LOADPOINTS, $this->Translate('Tariff CO2 Loadpoints'), 'evcc.g', ++$pos);

        $this->RegisterVariableFloat(self::VAR_IDENT_PVENERGY, $this->Translate('PV Energy'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_BATTERYCAPACITY, $this->Translate('Battery Capacity'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_BATTERYENERGY, $this->Translate('Battery Energy'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_GRIDCURRENTS, $this->Translate('Grid Currents'), '', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_GRIDENERGY, $this->Translate('Grid Energy'), 'evcc.Energy.kWh', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_GREENSHAREHOME, $this->Translate('Green Share Home'), '~Intensity.1', ++$pos);
        $this->RegisterVariableFloat(self::VAR_IDENT_GREENSHARELOADPOINTS, $this->Translate('Green Share Loadpoints'), '~Intensity.1', ++$pos);
        $this->RegisterVariableString(self::VAR_IDENT_VERSION, $this->Translate('Version'), '', ++$pos);
        $this->RegisterVariableString(self::VAR_IDENT_AVAILABLEVERSION, $this->Translate('Available Version'), '', ++$pos);
        $this->RegisterVariableString(self::VAR_IDENT_SMARTCOSTTYPE, $this->Translate('Smart Cost Type'), '', ++$pos);
        $this->RegisterVariableInteger(self::VAR_IDENT_RESIDUALPOWER, $this->Translate('Residual Power'), 'evcc.Power', ++$pos);
    }


    private function shouldBeIgnored(string $lastElement, string $penultimateElement, string $topic, string $MQTTTopic)
    {
        return in_array($lastElement, self::IGNORED_ELEMENTS)
               || is_numeric($penultimateElement)
               || is_numeric($lastElement)
               || str_starts_with($topic, $MQTTTopic . self::STATISTICS_FLAG);
    }

    private function enableActions(): void
    {
        // see listenSiteSetters in https://github.com/evcc-io/evcc/blob/master/server/mqtt.go
        $this->EnableAction(self::VAR_IDENT_PRIORITYSOC);
        $this->EnableAction(self::VAR_IDENT_BUFFERSOC);
        $this->EnableAction(self::VAR_IDENT_BUFFERSTARTSOC);
        $this->EnableAction(self::VAR_IDENT_RESIDUALPOWER);
        $this->EnableAction(self::VAR_IDENT_BATTERYDISCHARGECONTROL);
        $this->EnableAction(self::VAR_IDENT_BATTERYGRIDCHARGELIMIT);
    }

    public function ApplyChanges(): void
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

    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->ReadPropertyString(self::PROP_TOPIC);

        if (empty($MQTTTopic)) {
            return '';
        }

        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = hex2bin($data['Payload']);
        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s', $topic, $payload), 0);

        $topicActions = [
            $MQTTTopic . self::VAR_IDENT_GRIDCONFIGURED          => fn() => $this->SetValue(self::VAR_IDENT_GRIDCONFIGURED, (bool)$payload),
            $MQTTTopic . self::VAR_IDENT_GRIDPOWER               => fn() => $this->SetValue(self::VAR_IDENT_GRIDPOWER, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_HOMEPOWER               => fn() => $this->SetValue(self::VAR_IDENT_HOMEPOWER, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_AUXPOWER                => fn() => $this->SetValue(self::VAR_IDENT_AUXPOWER, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_PVPOWER                 => fn() => $this->SetValue(self::VAR_IDENT_PVPOWER, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_BATTERYPOWER            => fn() => $this->SetValue(self::VAR_IDENT_BATTERYPOWER, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_BATTERYSOC              => fn() => $this->SetValue(self::VAR_IDENT_BATTERYSOC, (int)$payload),
            $MQTTTopic . self::VAR_IDENT_BATTERYDISCHARGECONTROL => fn() => $this->SetValue(self::VAR_IDENT_BATTERYDISCHARGECONTROL, $payload === 'true'),
            $MQTTTopic . self::VAR_IDENT_BATTERYGRIDCHARGEACTIVE => fn() => $this->SetValue(self::VAR_IDENT_BATTERYGRIDCHARGEACTIVE, $payload === 'true'),
            $MQTTTopic . self::VAR_IDENT_BATTERYGRIDCHARGELIMIT  => fn() => $this->SetValue(self::VAR_IDENT_BATTERYGRIDCHARGELIMIT, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_BATTERYMODE             => fn() => $this->SetValue(self::VAR_IDENT_BATTERYMODE, $payload),
            $MQTTTopic . self::VAR_IDENT_PRIORITYSOC             => fn() => $this->SetValue(self::VAR_IDENT_PRIORITYSOC, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_BUFFERSOC               => fn() => $this->SetValue(self::VAR_IDENT_BUFFERSOC, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_BUFFERSTARTSOC          => fn() => $this->SetValue(self::VAR_IDENT_BUFFERSTARTSOC, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_SITETITLE               => fn() => $this->SetValue(self::VAR_IDENT_SITETITLE, $payload),
            $MQTTTopic . self::VAR_IDENT_CURRENCY                => fn() => $this->SetValue(self::VAR_IDENT_CURRENCY, $payload),
            $MQTTTopic . self::VAR_IDENT_TARIFFFEEDIN            => fn() => $this->SetValue(self::VAR_IDENT_TARIFFFEEDIN, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_TARIFFGRID              => fn() => $this->SetValue(self::VAR_IDENT_TARIFFGRID, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_TARIFFCO2               => fn() => $this->SetValue(self::VAR_IDENT_TARIFFCO2, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_TARIFFPRICEHOME         => fn() => $this->SetValue(self::VAR_IDENT_TARIFFPRICEHOME, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_TARIFFCO2HOME           => fn() => $this->SetValue(self::VAR_IDENT_TARIFFCO2HOME, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_TARIFFPRICELOADPOINTS   => fn() => $this->SetValue(self::VAR_IDENT_TARIFFPRICELOADPOINTS, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_TARIFFCO2LOADPOINTS     => fn() => $this->SetValue(self::VAR_IDENT_TARIFFCO2LOADPOINTS, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_PVENERGY                => fn() => $this->SetValue(self::VAR_IDENT_PVENERGY, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_BATTERYCAPACITY         => fn() => $this->SetValue(self::VAR_IDENT_BATTERYCAPACITY, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_BATTERYENERGY           => fn() => $this->SetValue(self::VAR_IDENT_BATTERYENERGY, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_GRIDCURRENTS            => fn() => $this->SetValue(self::VAR_IDENT_GRIDCURRENTS, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_GRIDENERGY              => fn() => $this->SetValue(self::VAR_IDENT_GRIDENERGY, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_GREENSHAREHOME          => fn() => $this->SetValue(self::VAR_IDENT_GREENSHAREHOME, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_GREENSHARELOADPOINTS    => fn() => $this->SetValue(self::VAR_IDENT_GREENSHARELOADPOINTS, (float)$payload),
            $MQTTTopic . self::VAR_IDENT_VERSION                 => fn() => $this->SetValue(self::VAR_IDENT_VERSION, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_AVAILABLEVERSION        => fn() => $this->SetValue(self::VAR_IDENT_AVAILABLEVERSION, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_SMARTCOSTTYPE           => fn() => $this->SetValue(self::VAR_IDENT_SMARTCOSTTYPE, (string)$payload),
            $MQTTTopic . self::VAR_IDENT_RESIDUALPOWER           => fn() => $this->SetValue(self::VAR_IDENT_RESIDUALPOWER, (int)$payload),
        ];

        $mqttSubTopics      = $this->getMqttSubTopics($topic);
        $lastElement        = $this->getLastElement($mqttSubTopics);
        $penultimateElement = $this->getPenultimateElement($mqttSubTopics);

        if ($this->isReceivedSetTopic($topic)) {
            $this->SendDebug(__FUNCTION__, 'received: ' . $topic, 0);
        } elseif ($this->shouldBeIgnored($lastElement, $penultimateElement, $topic, $MQTTTopic)) {
            $this->SendDebug(__FUNCTION__, 'ignored: ' . $topic, 0);
        } elseif (array_key_exists($topic, $topicActions)) {
            $topicActions[$topic]();
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', sprintf('unexpected topic: %s, lastElement: %s', $topic, $lastElement), 0);
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        $mqttTopic = $this->ReadPropertyString(self::PROP_TOPIC);

        switch ($Ident) {
            case self::VAR_IDENT_PRIORITYSOC:
            case self::VAR_IDENT_BUFFERSOC:
            case self::VAR_IDENT_BUFFERSTARTSOC:
            case self::VAR_IDENT_RESIDUALPOWER:
            case self::VAR_IDENT_BATTERYGRIDCHARGELIMIT:
                $this->mqttCommand($mqttTopic . $Ident . '/set', (string)$Value);
                break;
            case self::VAR_IDENT_BATTERYDISCHARGECONTROL:
                $this->mqttCommand($mqttTopic . $Ident . '/set', $Value?'true':'false');
                break;
            default:
                $this->LogMessage('Invalid Action', KL_WARNING);
                break;
        }
    }

}
