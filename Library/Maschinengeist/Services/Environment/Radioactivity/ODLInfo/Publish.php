<?php
namespace Maschinengeist\Services\Environment\Radioactivity\ODLInfo;

use PhpMqtt\Client\MqttClient;

class Publish extends ODLInfo
{
    private $client;
    private $station;

    public function __construct(MqttClient $client)
    {
        $this->client = $client;
    }

    public function connect() {
        if (false === $this->client->isConnected()) {
            $this->client->connect();
        }
    }

    public function publish(Station $station) {
        $this->station = $station;
        $id = $this->station->id;
        $topic = $this->mqtt_default_topic . '/' . $id;

        $this->connect();

        $this->publishData($topic, array(
            'name' => $this->station->name,
            'code' => $this->station->code,
        ));

        $this->publishData("$topic/h1", $this->station->h1);
        $this->publishData("$topic/h24", $this->station->h24);
    }

    private function  publishData(string $topic, array $data) {
        foreach ($data as $name => $value) {
            $this->client->publish("$topic/$name", $value);
        }
    }
}