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
    const IPS_VAR_POSITION = 'VarPosition';
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
            $result[IPS_VAR_POSITION] = static::$properties[$property][IPS_VAR_POSITION] ?? 0;
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
    enum LoadPointIdVariableIdent: string
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
            LoadPointIdVariableIdent::Title->value                          => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Title',
                IPS_VAR_POSITION => 1
            ],
            LoadPointIdVariableIdent::Mode->value                           => [
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
                IPS_VAR_POSITION => 2
            ],
            LoadPointIdVariableIdent::LimitSoc->value                       => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Limit SoC',
                IPS_VAR_POSITION => 3
            ],
            LoadPointIdVariableIdent::EffectiveLimitSoc->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Effective Limit SoC',
                IPS_VAR_POSITION => 4
            ],
            LoadPointIdVariableIdent::LimitEnergy->value                    => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'SUFFIX'       => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Limit Energy',
                IPS_VAR_POSITION => 5
            ],
            LoadPointIdVariableIdent::ChargeDuration->value                 => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 3
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Charge Duration',
                IPS_VAR_POSITION => 6
            ],
            LoadPointIdVariableIdent::Charging->value                       => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Charging',
                IPS_VAR_POSITION => 7
            ],
            LoadPointIdVariableIdent::SessionEnergy->value                  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' Wh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session Energy',
                IPS_VAR_POSITION => 8
            ],
            LoadPointIdVariableIdent::SessionCo2PerKWh->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' gCO₂e',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session CO₂ per kWh',
                IPS_VAR_POSITION => 9
            ],
            LoadPointIdVariableIdent::SessionPricePerKWh->value             => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session Price per kWh',
                IPS_VAR_POSITION => 10
            ],
            LoadPointIdVariableIdent::SessionPrice->value                   => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 2,
                    'SUFFIX'       => ' €',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session Price',
                IPS_VAR_POSITION => 11
            ],
            LoadPointIdVariableIdent::SessionSolarPercentage->value         => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Session Solar Percentage',
                IPS_VAR_POSITION => 12
            ],
            LoadPointIdVariableIdent::ChargerFeatureIntegratedDevice->value => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Integrated Device',
                IPS_VAR_POSITION => 13
            ],
            LoadPointIdVariableIdent::ChargerFeatureHeating->value          => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Charger Feature Heating',
                IPS_VAR_POSITION => 14
            ],
            LoadPointIdVariableIdent::ChargerPhases1p3p->value              => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Charger Phases 1P3P',
                IPS_VAR_POSITION => 15
            ],
            LoadPointIdVariableIdent::Connected->value                      => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Connected',
                IPS_VAR_POSITION => 16
            ],
            LoadPointIdVariableIdent::Enabled->value                        => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Enabled',
                IPS_VAR_POSITION => 17
            ],
            LoadPointIdVariableIdent::VehicleDetectionActive->value         => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Vehicle Detection Active',
                IPS_VAR_POSITION => 18
            ],
            LoadPointIdVariableIdent::VehicleRange->value                   => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' km',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Vehicle Range',
                IPS_VAR_POSITION => 19
            ],
            LoadPointIdVariableIdent::VehicleSoc->value                     => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Vehicle SoC',
                IPS_VAR_POSITION => 20
            ],
            LoadPointIdVariableIdent::VehicleName->value                    => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Vehicle Name',
                IPS_VAR_POSITION => 21
            ],
            LoadPointIdVariableIdent::VehicleLimitSoc->value                => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Vehicle Limit SoC',
                IPS_VAR_POSITION => 22
            ],
            LoadPointIdVariableIdent::VehicleOdometer->value                => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' km',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Vehicle Odometer',
                IPS_VAR_POSITION => 23
            ],
            LoadPointIdVariableIdent::PlanActive->value                     => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Plan Active',
                IPS_VAR_POSITION => 24
            ],
            LoadPointIdVariableIdent::PlanProjectedStart->value             => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'         => 2,
                    'MONTH_TEXT'   => false,
                    'TIME'         => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Plan Projected Start',
                IPS_VAR_POSITION => 25
            ],
            LoadPointIdVariableIdent::PlanProjectedEnd->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'         => 2,
                    'MONTH_TEXT'   => false,
                    'TIME'         => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Plan Projected End',
                IPS_VAR_POSITION => 26
            ],
            LoadPointIdVariableIdent::PlanOverrun->value                    => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Plan Overrun',
                IPS_VAR_POSITION => 27
            ],
            LoadPointIdVariableIdent::PlanEnergy->value                     => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' kWh',
                    'DIGITS'       => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Plan Energy',
                IPS_VAR_POSITION => 28
            ],
            LoadPointIdVariableIdent::EffectivePlanTime->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'         => 2,
                    'MONTH_TEXT'   => false,
                    'TIME'         => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Effective Plantime',
                IPS_VAR_POSITION => 29
            ],
            LoadPointIdVariableIdent::EffectivePlanSoc->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' %',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Effective Plan SoC',
                IPS_VAR_POSITION => 30
            ],
            LoadPointIdVariableIdent::ChargePower->value                    => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Charge Power',
                IPS_VAR_POSITION => 31
            ],
            LoadPointIdVariableIdent::ChargeCurrent->value                  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Charge Current',
                IPS_VAR_POSITION => 32
            ],
            LoadPointIdVariableIdent::ChargedEnergy->value                  => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' Wh',
                    'DIGITS'       => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Charged Energy',
                IPS_VAR_POSITION => 33
            ],
            LoadPointIdVariableIdent::ChargeRemainingDuration->value        => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 3
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Charge remaining Duration',
                IPS_VAR_POSITION => 34
            ],
            LoadPointIdVariableIdent::ChargeRemainingEnergy->value          => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' Wh',
                    'DIGITS'       => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Charge remaining Energy',
                IPS_VAR_POSITION => 34
            ],
            LoadPointIdVariableIdent::PhasesConfigured->value               => [
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
                IPS_VAR_POSITION => 36
            ],
            LoadPointIdVariableIdent::PhasesEnabled->value                  => [
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
                IPS_VAR_POSITION => 37
            ],
            LoadPointIdVariableIdent::PhaseAction->value                    => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'Phase Action',
                IPS_VAR_POSITION => 38
            ],
            LoadPointIdVariableIdent::PhasesActive->value                   => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Phases Active',
                IPS_VAR_POSITION => 39
            ],
            LoadPointIdVariableIdent::MinCurrent->value                     => [
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
                IPS_VAR_POSITION => 40
            ],
            LoadPointIdVariableIdent::MaxCurrent->value                     => [
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
                IPS_VAR_POSITION => 41
            ],
            LoadPointIdVariableIdent::EffectiveMinCurrent->value            => [
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
                IPS_VAR_POSITION => 42
            ],
            LoadPointIdVariableIdent::EffectiveMaxCurrent->value            => [
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
                IPS_VAR_POSITION => 43
            ],
            LoadPointIdVariableIdent::ConnectedDuration->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 3
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Connected Duration',
                IPS_VAR_POSITION => 44
            ],
            LoadPointIdVariableIdent::PhaseRemaining->value                 => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 1
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Phase Remaining',
                IPS_VAR_POSITION => 45
            ],
            LoadPointIdVariableIdent::PvRemaining->value                    => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 3
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'PV Remaining',
                IPS_VAR_POSITION => 46
            ],
            LoadPointIdVariableIdent::PvAction->value                       => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_NAME     => 'PV Action',
                IPS_VAR_POSITION => 47
            ],
            LoadPointIdVariableIdent::SmartCostActive->value                => [
                'type'           => 'bool',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Smart Cost Active',
                IPS_VAR_POSITION => 48
            ],
            LoadPointIdVariableIdent::SmartCostLimit->value                 => [
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
                IPS_VAR_POSITION => 49
            ],
            LoadPointIdVariableIdent::Priority->value                       => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Priority',
                IPS_VAR_POSITION => 50
            ],
            LoadPointIdVariableIdent::effectivePriority->value              => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Effective Priority',
                IPS_VAR_POSITION => 51
            ],
            LoadPointIdVariableIdent::EnableThreshold->value                => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Enable Threshold',
                IPS_VAR_POSITION => 52
            ],
            LoadPointIdVariableIdent::DisableThreshold->value               => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Disable Threshold',
                IPS_VAR_POSITION => 53
            ],
        ];
    }
    enum SiteIdent: string
    {
        case GridConfigured = 'gridConfigured';
        case GridPower = 'grid_power';
        case GridEnergy = 'grid_energy';
        case HomePower = 'homePower';
        public static function idents(): array
        {
            // Gibt ein Array von Strings (den Enum-Backing-Values) zurück
            return array_map(static fn(self $c): string => $c->value, self::cases());
        }

    }
    class Site extends ThemeBasics
    {
        protected static array $properties = [
            SiteIdent::GridConfigured->value => [
                'type'           => 'boolean',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_NAME     => 'Grid Configured',
                IPS_VAR_POSITION => 1
            ],
            SiteIdent::GridPower->value => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Grid Power',
                IPS_VAR_POSITION => 2
            ],
            SiteIdent::HomePower->value => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'DIGITS'       => 1,
                    'SUFFIX'       => ' W',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_FLOAT,
                IPS_VAR_NAME     => 'Home Power',
                IPS_VAR_POSITION => 4
            ],
            SiteIdent::GridEnergy->value => [
                'type'           => 'number',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => ' kWh',
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_INTEGER,
                IPS_VAR_NAME     => 'Grid Energy',
                IPS_VAR_POSITION => 5
            ],
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
                IPS_VAR_POSITION => 1
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
                IPS_VAR_POSITION => 2
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
                IPS_VAR_POSITION => 3
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
                IPS_VAR_POSITION => 4
            ],
        ];
    }
    class PowerSwitch extends ThemeBasics
    {
        protected static array $properties = [
            'switchState' => [
                'type'           => 'string',
                'enum'           => [
                    true  => 'ON',
                    false => 'OFF',
                ],
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
                ],
                IPS_VAR_TYPE     => VARIABLETYPE_BOOLEAN,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Switch'
            ]
            /** @todo
             * 'automaticPowerOffTime' INTEGER
             */
        ];
    }
    class PowerSwitchConfiguration extends ThemeBasics
    {
        protected static array $properties = [
            'stateAfterPowerOutage' => [
                'type'           => 'string',
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'OPTIONS'      => [
                        [
                            'Value'      => 'OFF',
                            'Caption'    => 'off',
                            'IconActive' => true,
                            'IconValue'  => 'plug-circle-xmark',
                            'Color'      => 0xff0000
                        ],
                        [
                            'Value'      => 'LAST_STATE',
                            'Caption'    => 'laste state',
                            'IconActive' => true,
                            'IconValue'  => 'plug-circle-check',
                            'Color'      => 0xffff00
                        ],
                        [
                            'Value'      => 'ON',
                            'Caption'    => 'on',
                            'IconActive' => true,
                            'IconValue'  => 'plug-circle-bolt',
                            'Color'      => 0x00ff00
                        ]
                    ]

                ],
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'State after power outage'
            ]
        ];
    }
    class PowerSwitchProgram extends ThemeBasics
    {
        protected static array $properties = [
            'operationMode' => [
                'type'           => 'string',
                IPS_PRESENTATION => [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'OPTIONS'      => [
                        [
                            'Value'      => 'MANUAL',
                            'Caption'    => 'manual',
                            'IconActive' => true,
                            'IconValue'  => 'hand-back-point-up',
                            'Color'      => 0xF48D43
                        ],
                        [
                            'Value'      => 'SCHEDULE',
                            'Caption'    => 'schedule',
                            'IconActive' => true,
                            'IconValue'  => 'calendar-check',
                            'Color'      => 0x00CDAB
                        ]
                    ]

                ],
                IPS_VAR_TYPE     => VARIABLETYPE_STRING,
                IPS_VAR_ACTION   => true,
                IPS_VAR_NAME     => 'Operation mode'
            ]
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