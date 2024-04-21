<?php

declare(strict_types=1);

trait MQTTHelper
{
    public const MQTT_SERVER            = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    public const DATA_ID_MQTT_SERVER_TX = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
    private const PT_PUBLISH             = 3; //Packet Type Publish
    private const QOS_0                  = 0; //Quality of Service 0

    private function mqttCommand(string $topic, $payload, bool $retain = false): void
    {
        $data['DataID']           = self::DATA_ID_MQTT_SERVER_TX;
        $data['PacketType']       = self::PT_PUBLISH;
        $data['QualityOfService'] = self::QOS_0;
        $data['Retain']           = $retain;
        $data['Topic']            = $topic;
        $data['Payload']          = $payload;
        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s',  $data['Topic'], $data['Payload']), 0);

        $result                   = @$this->SendDataToParent(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if ($result !== '') {
            $last_error = error_get_last();
            echo $last_error['message'];
        }
    }

}