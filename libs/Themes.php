<?php

declare(strict_types=1);

namespace evccMQTT {

    class Themes
    {
        public const LOADPOINTS                = 'loadpoints';
        public const POWERSWITCH               = 'PowerSwitch';
        public const POWERSWITCH_CONFIGURATION = 'PowerSwitchConfiguration';
        public const POWERSWITCH_PROGRAM       = 'PowerSwitchProgram';

        protected static array $themes = [
            self::LOADPOINTS,
            self::POWERSWITCH,
            self::POWERSWITCH_CONFIGURATION,
            self::POWERSWITCH_PROGRAM,
        ];

        public static function themeIsValid(string $theme): bool
        {
            return in_array($theme, self::$themes, true);
        }

    }
}

namespace evccMQTT\Themes {

    use IPSModule;

    const IPS_PRESENTATION = 'Presentation';
    const IPS_VAR_TYPE     = 'VarType';
    const IPS_VAR_FACTOR   = 'Factor';
    const IPS_VAR_NAME     = 'VarName';
    const IPS_VAR_VALUE    = 'VarValue';
    const IPS_VAR_ACTION   = 'VarAction';
    const IPS_VAR_IDENT    = 'VarIdent';
    abstract class ThemeBasics
    {
        protected static array  $properties = [];

        protected static string $state      = 'State';

        public static function propertyIsValid(string $property): bool
        {
            return isset(static::$properties[$property]);
        }

        public static function propertyHasAction(string $property): bool
        {
            return static::$properties[$property][IPS_VAR_ACTION] ?? false;
        }

        public static function getServiceStateRequest(string $property, mixed $value): string
        {
            $varType = static::$properties[$property][IPS_VAR_TYPE] ?? VARIABLETYPE_STRING;
            $factor  = static::$properties[$property][IPS_VAR_FACTOR] ?? 1;
            switch ($varType) {
                case VARIABLETYPE_FLOAT:
                case VARIABLETYPE_INTEGER:
                    $request[$property] = $value * $factor;
                    break;
                default:
                    if (isset(static::$properties[$property]['enum'])) {
                        $request[$property] = static::$properties[$property]['enum'][$value];
                    } else {
                        $request[$property] = $value;
                    }
                    break;
            }
            if (static::$properties[$property]['type'] === 'string') {
                $request[$property] = (string)$request[$property];
            }
            $request['@type'] = static::getServiceState();
            return json_encode($request, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        }

        public static function getIPSVariable(string $property, mixed $value = null): array
        {
            $result[IPS_VAR_TYPE] = static::getIPSVarType($property);
            $factor               = static::$properties[$property][IPS_VAR_FACTOR] ?? 1;
            if (!is_null($value)) {
                switch ($result[IPS_VAR_TYPE]) {
                    case VARIABLETYPE_FLOAT:
                        if (is_numeric($value)) {
                            $result[IPS_VAR_VALUE] = (float)$value / $factor;
                        } else {
                            $result[IPS_VAR_VALUE] = null;
                        }
                        break;
                    case VARIABLETYPE_INTEGER:
                        if (is_numeric($value)) {
                            $result[IPS_VAR_VALUE] = (int)($value / $factor);
                        } else {
                            $result[IPS_VAR_VALUE] = null;
                        }
                        break;
                    default:
                        if (isset(static::$properties[$property]['enum'])) {
                            $result[IPS_VAR_VALUE] = array_search($value, static::$properties[$property]['enum'], true);
                        } else {
                            $result[IPS_VAR_VALUE] = $value;
                        }
                        break;
                }
            }
            $result[IPS_PRESENTATION] = static::getIPSPresentation($property);
            $result[IPS_VAR_NAME]     = static::$properties[$property][IPS_VAR_NAME] ?? $property;
            $result[IPS_VAR_ACTION]   = static::$properties[$property][IPS_VAR_ACTION] ?? false;
            $result[IPS_VAR_IDENT]    = $property;
            return $result;
        }

        public static function translatePresentationValue(string $text): string
        {
            $translation = self::getPresentationTranslation();
            $language    = IPS_GetSystemLanguage();
            $code        = explode('_', $language)[0];
            if (isset($translation['translations'])) {
                if (isset($translation['translations'][$language])) {
                    if (isset($translation['translations'][$language][$text])) {
                        return $translation['translations'][$language][$text];
                    }
                } elseif (isset($translation['translations'][$code])) {
                    if (isset($translation['translations'][$code][$text])) {
                        return $translation['translations'][$code][$text];
                    }
                }
            }
            return $text;
        }

        private static function getIPSPresentation(string $property): array
        {
            $presentation = [];
            if (isset(static::$properties[$property][IPS_PRESENTATION])) {
                $presentation = static::$properties[$property][IPS_PRESENTATION];
                if (isset($presentation['PREFIX'])) {
                    $presentation['PREFIX'] = self::translatePresentationValue($presentation['PREFIX']);
                }
                if (isset($presentation['SUFFIX'])) {
                    $presentation['SUFFIX'] = self::translatePresentationValue($presentation['SUFFIX']);
                }
                if (isset($presentation['OPTIONS'])) {
                    $options = $presentation['OPTIONS'];
                    foreach ($options as &$option) {
                        $option['Caption'] = self::translatePresentationValue($option['Caption']);
                    }
                    unset($option);
                    $presentation['OPTIONS'] = json_encode($options, JSON_THROW_ON_ERROR);
                }
            }
            return $presentation;
        }

        private static function getPresentationTranslation(): array
        {
            return json_decode(file_get_contents(__DIR__ . '/locale_profile.json'), true, 512, JSON_THROW_ON_ERROR);
        }

        private static function getServiceState(): string
        {
            return lcfirst(explode('\\', static::class)[2]) . static::$state;
        }

        private static function getIPSVarType(string $property): int
        {
            return static::$properties[$property][IPS_VAR_TYPE] ?? VARIABLETYPE_STRING;
        }

    }
    enum LoadPointIdIdent: string
    {
        case Title = 'title';
        case Mode = 'mode';
        case LimitSoc = 'limitSoc';
        case EffectiveLimitSoc = 'effectiveLimitSoc';
        case LimitEnergy = 'limitEnergy';
        case ChargeDuration = 'chargeDuration';
        case Charging = 'charging';
        case SessionEnergy = 'sessionEnergy';
        case SessionCo2PerKWh = 'sessionCo2PerKWh';
        case SessionPricePerKWh = 'sessionPricePerKWh';
        case SessionPrice = 'sessionPrice';
        case SessionSolarPercentage = 'sessionSolarPercentage';
        case ChargerFeatureIntegratedDevice = 'chargerFeatureIntegratedDevice';
        case ChargerFeatureHeating = 'chargerFeatureHeating';
        case ChargerPhases1p3p = 'chargerPhases1p3p';
        case Connected = 'connected';
        case Enabled = 'enabled';
        case VehicleDetectionActive = 'vehicleDetectionActive';
        case VehicleRange = 'vehicleRange';
        case VehicleSoc = 'vehicleSoc';
        case VehicleName = 'vehicleName';
        case VehicleLimitSoc = 'vehicleLimitSoc';
        case VehicleOdometer = 'vehicleOdometer';
        case PlanActive = 'planActive';
        case PlanProjectedStart = 'planProjectedStart';
        case PlanProjectedEnd = 'planProjectedEnd';
        case PlanOverrun = 'planOverrun';
        case PlanEnergy = 'planEnergy';
        case EffectivePlanTime = 'effectivePlanTime';
        case EffectivePlanSoc = 'effectivePlanSoc';
        case ChargePower = 'chargePower';
        case ChargeCurrent = 'chargeCurrent';
        case ChargedEnergy = 'chargedEnergy';
        case ChargeRemainingDuration = 'chargeRemainingDuration';
        case ChargeRemainingEnergy = 'chargeRemainingEnergy';
        case PhasesConfigured = 'phasesConfigured';
        case PhasesEnabled = 'phasesEnabled';
        case PhasesActive = 'phasesActive';
        case PhaseAction = 'phaseAction';
        case MinCurrent = 'minCurrent';
        case MaxCurrent = 'maxCurrent';
        case EffectiveMinCurrent = 'effectiveMinCurrent';
        case EffectiveMaxCurrent = 'effectiveMaxCurrent';
        case ConnectedDuration = 'connectedDuration';
        case PhaseRemaining = 'phaseRemaining';
        case PvRemaining = 'pvRemaining';
        case PvAction = 'pvAction';
        case SmartCostActive = 'smartCostActive';
        case SmartCostLimit = 'smartCostLimit';
        case Priority = 'Priority';
        case effectivePriority = 'effectivePriority';
        case EnableThreshold = 'enableThreshold';
        case DisableThreshold = 'disableThreshold';


        public static function idents(): array
        {
            // Gibt ein Array von Strings (den Enum-Backing-Values) zurück
            return array_map(static fn(self $c): string => $c->value, self::cases());
        }
    }
    class LoadPointId extends ThemeBasics
    {
        protected static array $properties = [
            LoadPointIdIdent::Title->value                          => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Title',
            ],
            LoadPointIdIdent::Mode->value                           => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'OPTIONS'      => [
                        [
                            'Value'      => 'off',
                            'Caption'    => 'Off',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 'pv',
                            'Caption'    => 'Only PV',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 'minpv',
                            'Caption'    => 'Min + PV',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 'now',
                            'Caption'    => 'Now',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ]
                    ]
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Mode',
            ],
            LoadPointIdIdent::LimitSoc->value                       => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Limit SoC',
            ],
            LoadPointIdIdent::EffectiveLimitSoc->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Effective Limit SoC',
            ],
            LoadPointIdIdent::LimitEnergy->value                    => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Limit Energy',
            ],
            LoadPointIdIdent::ChargeDuration->value                 => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 3
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Charge Duration',
            ],
            LoadPointIdIdent::Charging->value                       => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Charging',
            ],
            LoadPointIdIdent::SessionEnergy->value                  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' Wh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session Energy',
            ],
            LoadPointIdIdent::SessionCo2PerKWh->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' gCO₂e',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session CO₂ per kWh',
            ],
            LoadPointIdIdent::SessionPricePerKWh->value             => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session Price per kWh',
            ],
            LoadPointIdIdent::SessionPrice->value                   => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session Price',
            ],
            LoadPointIdIdent::SessionSolarPercentage->value         => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session Solar Percentage',
            ],
            LoadPointIdIdent::ChargerFeatureIntegratedDevice->value => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Integrated Device',
            ],
            LoadPointIdIdent::ChargerFeatureHeating->value          => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Charger Feature Heating',
            ],
            LoadPointIdIdent::ChargerPhases1p3p->value              => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Charger Phases 1P3P',
            ],
            LoadPointIdIdent::Connected->value                      => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Connected',
            ],
            LoadPointIdIdent::Enabled->value                        => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Enabled',
            ],
            LoadPointIdIdent::VehicleDetectionActive->value         => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Vehicle Detection Active',
            ],
            LoadPointIdIdent::VehicleRange->value                   => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' km',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Vehicle Range',
            ],
            LoadPointIdIdent::VehicleSoc->value                     => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Vehicle SoC',
            ],
            LoadPointIdIdent::VehicleName->value                    => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Vehicle Name',
            ],
            LoadPointIdIdent::VehicleLimitSoc->value                => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Vehicle Limit SoC',
            ],
            LoadPointIdIdent::VehicleOdometer->value                => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' km',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Vehicle Odometer',
            ],
            LoadPointIdIdent::PlanActive->value                     => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Plan Active',
            ],
            LoadPointIdIdent::PlanProjectedStart->value             => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'         => 2,
                    'MONTH_TEXT'   => false,
                    'TIME'         => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Plan Projected Start',
            ],
            LoadPointIdIdent::PlanProjectedEnd->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'         => 2,
                    'MONTH_TEXT'   => false,
                    'TIME'         => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Plan Projected End',
            ],
            LoadPointIdIdent::PlanOverrun->value                    => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Plan Overrun',
            ],
            LoadPointIdIdent::PlanEnergy->value                     => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' kWh',
                    'DIGITS'              => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Plan Energy',
            ],
            LoadPointIdIdent::EffectivePlanTime->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'         => 2,
                    'MONTH_TEXT'   => false,
                    'TIME'         => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Effective Plantime',
            ],
            LoadPointIdIdent::EffectivePlanSoc->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Effective Plan SoC',
            ],
            LoadPointIdIdent::ChargePower->value                    => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Charge Power',
            ],
            LoadPointIdIdent::ChargeCurrent->value                  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Charge Current',
            ],
            LoadPointIdIdent::ChargedEnergy->value                  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' Wh',
                    'DIGITS'              => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Charged Energy',
            ],
            LoadPointIdIdent::ChargeRemainingDuration->value        => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 3
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Charge remaining Duration',
            ],
            LoadPointIdIdent::ChargeRemainingEnergy->value          => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' Wh',
                    'DIGITS'              => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Charge remaining Energy',
            ],
            LoadPointIdIdent::PhasesConfigured->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'OPTIONS'      => [
                        [
                            'Value'      => 0,
                            'Caption'    => 'auto',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 1,
                            'Caption'    => '1',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 3,
                            'Caption'    => '3',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ]
                    ]
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Phases Configured',
            ],
            LoadPointIdIdent::PhasesEnabled->value                  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'OPTIONS'      => [
                        [
                            'Value'      => 0,
                            'Caption'    => 'auto',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 1,
                            'Caption'    => '1',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 3,
                            'Caption'    => '3',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ]
                    ]
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Phases Enabled',
            ],
            LoadPointIdIdent::PhaseAction->value                    => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Phase Action',
            ],
            LoadPointIdIdent::PhasesActive->value                   => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Phases Active',
            ],
            LoadPointIdIdent::MinCurrent->value                     => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' A',
                    'MIN'          => '1',
                    'MAX'          => '16',
                    'STEP_SIZE'    => '1',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Min Current',
            ],
            LoadPointIdIdent::MaxCurrent->value                     => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' A',
                    'MIN'          => '1',
                    'MAX'          => '16',
                    'STEP_SIZE'    => '1',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Max Current',
            ],
            LoadPointIdIdent::EffectiveMinCurrent->value            => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' A',
                    'MIN'          => '1',
                    'MAX'          => '16',
                    'DIGITS'       => '1',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Effective min Current',
            ],
            LoadPointIdIdent::EffectiveMaxCurrent->value            => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' A',
                    'MIN'          => '1',
                    'MAX'          => '16',
                    'DIGITS'       => '1',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Effective max Current',
            ],
            LoadPointIdIdent::ConnectedDuration->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 3
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Connected Duration',
            ],
            LoadPointIdIdent::PhaseRemaining->value                 => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Phase Remaining',
            ],
            LoadPointIdIdent::PvRemaining->value                    => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 3
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'PV Remaining',
            ],
            LoadPointIdIdent::PvAction->value                       => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'PV Action',
            ],
            LoadPointIdIdent::SmartCostActive->value                => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Smart Cost Active',
            ],
            LoadPointIdIdent::SmartCostLimit->value                 => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'DIGITS'       => 3,
                    'MIN'          => '-0.10',
                    'MAX'          => '0.60',
                    'STEP_SIZE'    => '0.005',
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Smart Cost Limit',
            ],
            LoadPointIdIdent::Priority->value                       => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Priority',
            ],
            LoadPointIdIdent::effectivePriority->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Effective Priority',
            ],
            LoadPointIdIdent::EnableThreshold->value                => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Enable Threshold',
            ],
            LoadPointIdIdent::DisableThreshold->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Disable Threshold',
            ],
        ];
    }
    // ----------------------------------------------------------------------------------
    enum SiteIdent: string
    {
        //case GridConfigured = 'gridConfigured';
        case SiteTitle = 'siteTitle';
        case GridPower = 'grid_power';
        case GridEnergy = 'grid_energy';
        case PvPower = 'pvPower';
        case PvEnergy = 'pvEnergy';
        case BatteryPower = 'batteryPower';
        case BatteryEnergy = 'batteryEnergy';
        case HomePower = 'homePower';
        case AuxPower = 'auxPower';
        case BatterySoc = 'batterySoc';
        case BatteryMode = 'batteryMode';
        case BatteryDischargeControl = 'batteryDischargeControl';
        case BatteryGridChargeActive = 'batteryGridChargeActive';
        case BatteryGridChargeLimit = 'batteryGridChargeLimit';
        case PrioritySoc = 'prioritySoc';
        case BufferSoc = 'bufferSoc';
        case BufferStartSoc = 'bufferStartSoc';
        case Currency = 'currency';
        case GreenShareHome = 'greenShareHome';
        case GreenShareLoadPoints = 'greenShareLoadpoints';
        case TariffFeedIn = 'tariffFeedIn';
        case TariffGrid = 'tariffGrid';
        case TariffCo2 = 'tariffCo2';
        case TariffPriceHome = 'tariffPriceHome';
        case TariffCo2Home = 'tariffCo2Home';
        case TariffPriceLoadPoints = 'tariffPriceLoadpoints';
        case TariffCo2Loadpoints = 'tariffCo2Loadpoints';
        case BatteryCapacity = 'batteryCapacity';
        case Version = 'version';
        case AvailableVersion = 'availableVersion';
        case SmartCostType = 'smartCostType';
        case ResidualPower = 'residualPower';

        public static function idents(): array
        {
            // Gibt ein Array von Strings (den Enum-Backing-Values) zurück
            return array_map(static fn(self $c): string => $c->value, self::cases());
        }

    }
    class Site extends ThemeBasics
    {
        protected static array $properties = [
            /*            SiteIdent::GridConfigured->value => [
                            'type'           => 'boolean',
                            IPS_PRESENTATION => [
                                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                            ],
                            IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                            IPS_VAR_NAME     => 'Grid Configured',
                        ],
            */
            SiteIdent::SiteTitle->value               => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Site Title',
            ],
            SiteIdent::Version->value                 => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Version',
            ],
            SiteIdent::AvailableVersion->value        => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Available Version',
            ],
            SiteIdent::SmartCostType->value           => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Smart Cost Type',
            ],
            SiteIdent::GridPower->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Grid Power',
            ],
            SiteIdent::HomePower->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Home Power',
            ],
            SiteIdent::AuxPower->value                => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Aux Power',
            ],
            SiteIdent::PvPower->value                 => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'PV Power',
            ],
            SiteIdent::BatteryPower->value            => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Battery Power',
            ],
            SiteIdent::BatterySoc->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Battery SOC',
            ],
            SiteIdent::BatteryMode->value             => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'OPTIONS'      => [
                        [
                            'Value'      => 'unknown',
                            'Caption'    => 'unknown',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 'normal',
                            'Caption'    => 'normal',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 'charge',
                            'Caption'    => 'charge',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ],
                        [
                            'Value'      => 'discharge',
                            'Caption'    => 'discharge',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ]
                    ]
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Battery Mode',
            ],
            SiteIdent::GridEnergy->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Grid Energy',
            ],
            SiteIdent::PvEnergy->value                => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'PV Energy',
            ],
            SiteIdent::BatteryEnergy->value           => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Battery Energy',
            ],
            SiteIdent::BatteryCapacity->value         => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Battery Capacity',
            ],
            SiteIdent::Currency->value                => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Currency',
            ],
            SiteIdent::TariffGrid->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 3,
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Tariff Grid',
            ],
            SiteIdent::TariffFeedIn->value            => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 3,
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Tariff Feed In',
            ],
            SiteIdent::TariffCo2->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' gCO₂e',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Tariff CO₂',
            ],
            SiteIdent::TariffPriceHome->value         => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 3,
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Tariff Price Home',
            ],
            SiteIdent::TariffCo2Home->value           => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' gCO₂e',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Tariff CO₂ Home',
            ],
            SiteIdent::TariffPriceLoadPoints->value   => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 3,
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Tariff Price Loadpoints',
            ],
            SiteIdent::TariffCo2Loadpoints->value     => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' gCO₂e',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Tariff CO₂ Loadpoints',
            ],
            SiteIdent::GreenShareHome->value          => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Green Share Home',
            ],
            SiteIdent::GreenShareLoadPoints->value    => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Green Share Loadpoints',
            ],
            SiteIdent::PrioritySoc->value             => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Priority SoC',
            ],
            SiteIdent::BufferSoc->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Buffer SoC',
            ],
            SiteIdent::BufferStartSoc->value          => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Buffer Start SoC',
            ],
            SiteIdent::ResidualPower->value           => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_INPUT,
                    'DIGITS'              => 1,
                    'MIN'                 => -1000,
                    'MAX'                 => -1000,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Residual Power',
            ],
            SiteIdent::BatteryDischargeControl->value => [
                'type'           => 'boolean',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Battery Discharge Control',
            ],
            SiteIdent::BatteryGridChargeActive->value => [
                'type'           => 'boolean',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Battery Gridcharge Active',
            ],
            SiteIdent::BatteryGridChargeLimit->value  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
                    'SUFFIX'       => ' €',
                    'MIN'          => '-1',
                    'MAX'          => '+1',
                    'DIGITS'       => 3,
                    'STEP_SIZE'    => '0.001',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Battery Gridcharge Limit',
            ],
        ];
    }
    // ----------------------------------------------------------------------------------
    enum SiteAuxIdIdent: string
    {
        case Power = 'power';
        case Energy = 'energy';

        public static function idents(): array
        {
            // Gibt ein Array von Strings (den Enum-Backing-Values) zurück
            return array_map(static fn(self $c): string => $c->value, self::cases());
        }
    }
    class SiteAuxId extends ThemeBasics
    {
        protected static array $properties = [
            SiteAuxIdIdent::Power->value  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Power',
            ],
            SiteAuxIdIdent::Energy->value => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Energy',
            ]
        ];
    }
    // ----------------------------------------------------------------------------------
    enum SiteBatteryIdIdent: string
    {
        case Power = 'power';
        case Energy = 'energy';
        case Soc = 'soc';
        case Capacity = 'capacity';
        case Controllable = 'controllable';

        public static function idents(): array
        {
            // Gibt ein Array von Strings (den Enum-Backing-Values) zurück
            return array_map(static fn(self $c): string => $c->value, self::cases());
        }
    }
    class SiteBatteryId extends ThemeBasics
    {
        protected static array $properties = [
            SiteBatteryIdIdent::Power->value        => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Power',
            ],
            SiteBatteryIdIdent::Energy->value       => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Energy',
            ],
            SiteBatteryIdIdent::Soc->value          => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'SoC',
            ],
            SiteBatteryIdIdent::Capacity->value     => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Capacity',
            ],
            SiteBatteryIdIdent::Controllable->value => [
                'type'           => 'boolean',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Controllable',
            ],

        ];
    }
    // ----------------------------------------------------------------------------------
    enum SitePvIdIdent: string
    {
        case Power = 'power';
        case Energy = 'energy';

        public static function idents(): array
        {
            // Gibt ein Array von Strings (den Enum-Backing-Values) zurück
            return array_map(static fn(self $c): string => $c->value, self::cases());
        }
    }
    class SitePvId extends ThemeBasics
    {
        protected static array $properties = [
            SiteAuxIdIdent::Power->value  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Power',
            ],
            SiteAuxIdIdent::Energy->value => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Energy',
            ]
        ];
    }
    // ----------------------------------------------------------------------------------
    enum SiteStatisticsIdent: string
    {
        case AvgCo2 = 'avgCo2';
        case AvgPrice = 'avgPrice';
        case ChargedKWh = 'chargedKWh';
        case SolarPercentage = 'solarPercentage';

        public static function idents(): array
        {
            // Gibt ein Array von Strings (den Enum-Backing-Values) zurück
            return array_map(static fn(self $c): string => $c->value, self::cases());
        }
    }
    class SiteStatistics extends ThemeBasics
    {
        protected static array $properties = [
            SiteStatisticsIdent::AvgCo2->value          => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' gCO₂e/kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'avgCo2',
            ],
            SiteStatisticsIdent::AvgPrice->value        => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 3,
                    'SUFFIX'       => ' €/kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'avgPrice',
            ],
            SiteStatisticsIdent::ChargedKWh->value      => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'chargedKWh',
            ],
            SiteStatisticsIdent::SolarPercentage->value => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'solarPercentage',
            ],
        ];
    }
    // ----------------------------------------------------------------------------------
    // ----------------------------------------------------------------------------------
    enum VehicleNameIdent: string
    {
        //case GridConfigured = 'gridConfigured';
        case Title = 'title';
        case Capacity = 'capacity';

        public static function idents(): array
        {
            // Gibt ein Array von Strings (den Enum-Backing-Values) zurück
            return array_map(static fn(self $c): string => $c->value, self::cases());
        }

    }
    class VehicleName extends ThemeBasics
    {
        protected static array $properties = [
            VehicleNameIdent::Title->value    => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Title',
            ],
            VehicleNameIdent::Capacity->value => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'              => 1,
                    'THOUSANDS_SEPARATOR' => 'Client',
                    'SUFFIX'              => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Capacity',
            ],
        ];
    }
    /**
     * @method void UnregisterProfile(string $Name)
     */
    trait IPSProfile
    {
        protected function UnregisterProfiles(): void
        {
            $this->UnregisterProfile('BSH.PowerSwitchConfiguration.stateAfterPowerOutage');
            $this->UnregisterProfile('BSH.PowerSwitchProgram.operationMode');
            $this->UnregisterProfile('BSH.RoomClimateControl.setpointTemperature');
            $this->UnregisterProfile('BSH.RoomClimateControl.operationMode');
            $this->UnregisterProfile('BSH.RoomClimateControl.roomControlMode');
            $this->UnregisterProfile('BSH.HCWasher.operationState');
            $this->UnregisterProfile('BSH.HCDishwasher.operationState');
            $this->UnregisterProfile('BSH.HCOven.operationState');
            $this->UnregisterProfile('BSH.ShutterControl.operationState');
            $this->UnregisterProfile('BSH.SmokeDetectorCheck.value');
            $this->UnregisterProfile('BSH.SmokeSensitivity.smokeSensitivity');
            $this->UnregisterProfile('BSH.ValveTappet.value');
            $this->UnregisterProfile('BSH.AirQualityLevel.combinedRating');
            $this->UnregisterProfile('BSH.AirQualityLevel.temperatureRating');
            $this->UnregisterProfile('BSH.Keypad.keyName');
            $this->UnregisterProfile('BSH.Keypad.eventType');
            $this->UnregisterProfile('BSH.BatteryLevel.batteryLevel');
            $this->UnregisterProfile('BSH.VentilationDelay.delay');
            $this->UnregisterProfile('BSH.HueBlinking.blinkingState');
            $this->UnregisterProfile('BSH.HueBridgeSearcher.searcherState');
            $this->UnregisterProfile('BSH.CommunicationQuality.quality');
            $this->UnregisterProfile('BSH.MultiswitchConfiguration.updateState');
            $this->UnregisterProfile('BSH.WalkTest.walkState');
            $this->UnregisterProfile('BSH.DoorSensor.doorState');
            $this->UnregisterProfile('BSH.LockActuator.lockState');
            $this->UnregisterProfile('BSH.WaterAlarmSystem.state');
            $this->UnregisterProfile('BSH.WaterAlarmSystem.mute');
            $this->UnregisterProfile('BSH.Scenario.Trigger');
            $this->UnregisterProfile('BSH.SoftwareUpdate.swUpdateState');
            $this->UnregisterProfile('BSH.DisplayConfiguration.displayBrightness');
            $this->UnregisterProfile('BSH.DisplayConfiguration.displayOnTime');
            $this->UnregisterProfile('BSH.TemperatureOffset.offset');
            $this->UnregisterProfile('BSH.TerminalConfiguration.type');
            $this->UnregisterProfile('BSH.SurveillanceAlarm.value');
            $this->UnregisterProfile('BSH.IntrusionDetectionControl.value');
            $this->UnregisterProfile('BSH.IntrusionDetectionControl.activeProfile');
            $this->UnregisterProfile('BSH.IntrusionDetectionControl.DelayTime');
        }
    }
}