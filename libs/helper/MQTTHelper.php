<?php

declare(strict_types=1);

trait MQTTHelper
{
    public const MQTT_SERVER            = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    public const DATA_ID_MQTT_SERVER_TX = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
    private const PT_PUBLISH             = 3; //Packet Type Publish
    private const QOS_0                  = 0; //Quality of Service 0

    private const SET_FLAG         = '/set';


    private function mqttCommand(string $topic, $payload, bool $retain = false): void
    {
        $this->SendDebug(__FUNCTION__, sprintf('Topic: %s, Payload: %s',  $topic, $payload), 0);
        $data['DataID']           = self::DATA_ID_MQTT_SERVER_TX;
        $data['PacketType']       = self::PT_PUBLISH;
        $data['QualityOfService'] = self::QOS_0;
        $data['Retain']           = $retain;
        $data['Topic']            = $topic;
        $data['Payload']          = bin2hex($payload);

        $result                   = @$this->SendDataToParent(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if ($result !== '') {
            $last_error = error_get_last();
            echo $last_error['message'];
        }
    }

    private function getMqttSubTopics(string $topic): array
    {
        return explode('/', $topic);
    }

    private function getLastElement(array $mqttSubTopics): string
    {
        return end($mqttSubTopics);
    }

    private function getPenultimateElement(array $mqttSubTopics): string
    {
        return $mqttSubTopics[count($mqttSubTopics) - 2];
    }

    private function isReceivedSetTopic(string $topic): bool
    {
        return str_ends_with($topic, self::SET_FLAG);
    }

}