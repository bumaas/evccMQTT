<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/Themes.php';

use evccMQTT\Themes\SiteStatistics;

use evccMQTT\Themes\SiteStatisticsIdent;

use const evccMQTT\Themes\IPS_PRESENTATION;
use const evccMQTT\Themes\IPS_VAR_ACTION;
use const evccMQTT\Themes\IPS_VAR_IDENT;
use const evccMQTT\Themes\IPS_VAR_NAME;
use const evccMQTT\Themes\IPS_VAR_TYPE;
use const evccMQTT\Themes\IPS_VAR_VALUE;
use const evccMQTT\Themes\IPS_VAR_POSITION;


require_once __DIR__ . '/../libs/helper/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/helper/MQTTHelper.php';

class evccSiteStatistics extends IPSModuleStrict
{
    use VariableProfileHelper;
    use MQTTHelper;

    private const string PROP_TOPIC = 'topic';
    private const string PROP_SCOPE = 'scope';

    private const string VAR_IDENT_AVG_CO2          = 'avgCo2';
    private const string VAR_IDENT_AVG_PRICE        = 'avgPrice';
    private const string VAR_IDENT_CHARGED_KWH      = 'chargedKWh';
    private const string VAR_IDENT_SOLAR_PERCENTAGE = 'solarPercentage';

    public function Create(): void
    {
        parent::Create();

        // Beispiel-Default: evcc/site/statistics/30d/
        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/statistics/');
        $this->RegisterPropertyString(self::PROP_SCOPE, 'total');

        $this->RegisterProfileFloatEx('evcc.Energy.kWh', '', '', ' kWh', -1, -1, 0, 2);
        $this->RegisterProfileFloatEx('evcc.Intensity.100', '', '', ' %', 0, 100, 0.001, 3);
        $this->RegisterProfileFloatEx('evcc.EUR', '', '', ' €', -1, -1, 0, 4);
        $this->RegisterProfileFloatEx('evcc.g', '', '', ' g', -1, -1, 0, 3);

        $this->registerVariables();
    }

    private function registerVariables(): void
    {
        $pos = 0;
        //sorted like https://github.com/evcc-io/evcc/blob/master/assets/js/components/Loadpoint.vue
        //main


        foreach (SiteStatisticsIdent::idents() as $ident) {
            $VariableValues = SiteStatistics::getIPSVariable($ident);
            $this->SendDebug(__FUNCTION__ . '!!!!', sprintf('VariableValues: %s', print_r($VariableValues, true)), 0);

            switch ($VariableValues[IPS_VAR_TYPE]) {
                case VARIABLETYPE_INTEGER:
                    $ret = $this->RegisterVariableInteger(
                        $ident,
                        $this->Translate($VariableValues[IPS_VAR_NAME]),
                        $VariableValues[IPS_PRESENTATION],
                        $VariableValues[IPS_VAR_POSITION],
                    );
                    break;
                case VARIABLETYPE_FLOAT:
                    $ret = $this->RegisterVariableFloat(
                        $ident,
                        $this->Translate($VariableValues[IPS_VAR_NAME]),
                        $VariableValues[IPS_PRESENTATION],
                        $VariableValues[IPS_VAR_POSITION],
                    );

                    break;
                case VARIABLETYPE_STRING:
                    $ret = $this->RegisterVariableString(
                        $ident,
                        $this->Translate($VariableValues[IPS_VAR_NAME]),
                        $VariableValues[IPS_PRESENTATION],
                        $VariableValues[IPS_VAR_POSITION],
                    );
                    break;
                case VARIABLETYPE_BOOLEAN:
                    $ret = $this->RegisterVariableBoolean(
                        $ident,
                        $this->Translate($VariableValues[IPS_VAR_NAME]),
                        $VariableValues[IPS_PRESENTATION],
                        $VariableValues[IPS_VAR_POSITION],
                    );
                    break;
            }

            $this->SendDebug(__FUNCTION__ . '!!!!', sprintf('ret: %s', (int)$ret), 0);
            if ($VariableValues[IPS_VAR_ACTION]) {
                $this->EnableAction($ident);
            }
        }
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $base      = rtrim($this->ReadPropertyString(self::PROP_TOPIC), '/') . '/';
        $scope     = $this->ReadPropertyString(self::PROP_SCOPE);
        $MQTTTopic = $base . $scope . '/';

        $requiredRegexMatch = '.*' . str_replace('/', '\/', $MQTTTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($MQTTTopic);
    }

    public function ReceiveData(string $JSONString): string
    {
        $base      = rtrim($this->ReadPropertyString(self::PROP_TOPIC), '/') . '/';
        $scope     = $this->ReadPropertyString(self::PROP_SCOPE);
        $MQTTTopic = $base . $scope . '/';

        if ($MQTTTopic === '/') {
            return '';
        }

        $data    = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $topic   = $data['Topic'];
        $payload = hex2bin($data['Payload']);

        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s', $topic, $payload), 0);

        $mqttSubTopics = $this->getMqttSubTopics($topic);
        $lastElement   = $this->getLastElement($mqttSubTopics);

        if (SiteStatistics::propertyIsValid($lastElement)) {
            $this->SendDebug(__FUNCTION__ . '!!!!', sprintf('topic: %s, payload: %s', $topic, $payload), 0);
            $VariableValues = SiteStatistics::getIPSVariable($lastElement, $payload);
            $this->SetValue($VariableValues[IPS_VAR_IDENT], $VariableValues[IPS_VAR_VALUE]);
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected topic: ' . $topic, 0);
        }
        return '';
    }

    public function RequestAction($Ident, $Value): void
    {
        $this->LogMessage('No writable statistics actions supported.', KL_WARNING);
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::MQTT_SERVER]], JSON_THROW_ON_ERROR);
    }
}