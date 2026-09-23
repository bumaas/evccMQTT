<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/Themes.php';

use evccMQTT\Themes\SiteForecasts;
use evccMQTT\Themes\SiteForecastsIdent;

use const evccMQTT\Themes\IPS_PRESENTATION;
use const evccMQTT\Themes\IPS_VAR_ACTION;
use const evccMQTT\Themes\IPS_VAR_IDENT;
use const evccMQTT\Themes\IPS_VAR_NAME;
use const evccMQTT\Themes\IPS_VAR_TYPE;
use const evccMQTT\Themes\IPS_VAR_VALUE;

require_once __DIR__ . '/../libs/helper/MQTTHelper.php';
require_once __DIR__ . '/../libs/helper/IdentHelper.php';

class evccSiteForecasts extends IPSModuleStrict
{
    use MQTTHelper;
    use IdentHelper;

    private const string PROP_TOPIC = 'topic';
    private const array IGNORED_ELEMENTS = [
        'today',
        'tomorrow',
        'dayAfterTomorrow',
    ];

    private const string SOLAR_ELEMENT = 'solar';

    // Aufbau des Sammel-Payloads von '<topic>/solar' (evcc >= 0.314): JSON-Pfad -> Ident
    private const array SOLAR_PAYLOAD_MAP = [
        'scale'                     => SiteForecastsIdent::SolarScale->value,
        'today.energy'              => SiteForecastsIdent::TodayYield->value,
        'today.complete'            => SiteForecastsIdent::TodayComplete->value,
        'tomorrow.energy'           => SiteForecastsIdent::TomorrowYield->value,
        'tomorrow.complete'         => SiteForecastsIdent::TomorrowComplete->value,
        'dayAfterTomorrow.energy'   => SiteForecastsIdent::DayAfterTomorrowYield->value,
        'dayAfterTomorrow.complete' => SiteForecastsIdent::DayAfterTomorrowComplete->value,
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(self::PROP_TOPIC, 'evcc/site/forecast/');

        $this->registerVariables();
    }

    private function registerVariables(): void
    {
        // bis build 40 falsch geschrieben, evcc sendet das Topic in dieser Schreibweise
        $this->migrateIdents(['feedin' => SiteForecastsIdent::FeedIn->value]);

        $pos = 0;
        foreach (SiteForecastsIdent::idents() as $ident) {
            $variableValues = SiteForecasts::getIPSVariable($ident);
            $this->SendDebug(__FUNCTION__, sprintf('%s, VariableValues: %s', $ident, print_r($variableValues, true)), 0);

            $this->MaintainVariable(
                $variableValues[IPS_VAR_IDENT],
                $this->Translate($variableValues[IPS_VAR_NAME]),
                $variableValues[IPS_VAR_TYPE],
                $variableValues[IPS_PRESENTATION],
                ++$pos,
                true
            );

            if ($variableValues[IPS_VAR_ACTION]) {
                $this->EnableAction($ident);
            }
        }
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->registerVariables();

        $mqttTopic = $this->getMqttBaseTopic();
        $requiredRegexMatch = '.*' . str_replace('/', '\/', $mqttTopic) . '.*';
        $this->SendDebug(__FUNCTION__, 'ReceiveDataFilter: ' . $requiredRegexMatch, 0);
        $this->SetReceiveDataFilter($requiredRegexMatch);

        $this->SetSummary($mqttTopic);
    }

    private function getMqttBaseTopic(): string
    {
        return $this->ReadPropertyString(self::PROP_TOPIC);
    }

    private function shouldBeIgnored(string $lastElement, string $penultimateElement, string $topic, string $mqttTopic): bool
    {
        return in_array($lastElement, self::IGNORED_ELEMENTS, true)
               || is_numeric($lastElement);
    }

    public function ReceiveData(string $JSONString): string
    {
        $MQTTTopic = $this->getMqttBaseTopic();
        $mqtt = $this->prepareMQTTData($JSONString, $MQTTTopic);
        if (is_null($mqtt)) {
            return '';
        }

        if ($this->shouldBeIgnored($mqtt['LastElement'], $mqtt['PenultimateElement'], $mqtt['Topic'], $MQTTTopic)) {
            $this->SendDebug(__FUNCTION__, 'ignored: ' . $mqtt['Topic'], 0);
        } elseif ($mqtt['LastElement'] === self::SOLAR_ELEMENT) {
            // seit evcc 0.314: die Solarprognose kommt gesammelt als JSON, nicht mehr als einzelne Untertopics
            $payload = json_decode($mqtt['Payload'], true);
            if (is_array($payload)) {
                $this->applySolarForecast($payload);
            } else {
                $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected solar payload: ' . $mqtt['Payload'], 0);
            }
        } elseif (SiteForecasts::propertyIsValid($mqtt['LastElement'])) {
            $this->setForecastValue($mqtt['LastElement'], $mqtt['Payload']);
        } elseif (SiteForecasts::propertyIsValid($mqtt['PenultimateElement'] . '_' . $mqtt['LastElement'])) {
            // bis evcc 0.313: Einzeltopics wie 'solar/scale' oder 'solar/today/yield'
            $this->setForecastValue($mqtt['PenultimateElement'] . '_' . $mqtt['LastElement'], $mqtt['Payload']);
        } else {
            $this->SendDebug(__FUNCTION__ . '::HINT', 'unexpected topic: ' . $mqtt['Topic'], 0);
        }
        return '';
    }

    /**
     * Verteilt den Sammel-Payload von '<topic>/solar' (evcc >= 0.314) auf die Idents.
     */
    private function applySolarForecast(array $payload): void
    {
        foreach (self::SOLAR_PAYLOAD_MAP as $path => $ident) {
            $value = $payload;
            foreach (explode('.', $path) as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$key];
            }

            if (is_null($value)) {
                $this->SendDebug(__FUNCTION__, 'missing in payload: ' . $path, 0);
                continue;
            }

            $this->setForecastValue($ident, $value);
        }

        // 'timeseries' ist optional; fehlt sie, bleibt der zuletzt empfangene Verlauf stehen
        if (isset($payload['timeseries'])) {
            $this->setForecastValue(
                SiteForecastsIdent::SolarTimeseries->value,
                json_encode($payload['timeseries'], JSON_THROW_ON_ERROR)
            );
        } else {
            $this->SendDebug(__FUNCTION__, 'missing in payload: timeseries', 0);
        }
    }

    private function setForecastValue(string $ident, mixed $value): void
    {
        $variableValues = SiteForecasts::getIPSVariable($ident, $value);
        if (!is_null($variableValues[IPS_VAR_VALUE])) {
            $this->SetValue($variableValues[IPS_VAR_IDENT], $variableValues[IPS_VAR_VALUE]);
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        $this->LogMessage('No writable actions supported.', KL_WARNING);
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::MQTT_SERVER]], JSON_THROW_ON_ERROR);
    }
}
