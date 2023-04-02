#! /usr/bin/env php
<?php
# ------------------------------------------------------------------------------------------ global
namespace Maschinengeist\Services\Environment\Radioactivity\ODLInfo;

error_reporting(E_ALL);
date_default_timezone_set($_ENV['TZ'] ?: 'Europe/Berlin');

# ------------------------------------------------------------------------------------------ set date from ENV

$_ENV['MQTT_HOST'] ?? exit(1);
$_ENV['MQTT_PORT'] ?? exit(1);

define('MQTT_HOST',         $_ENV['MQTT_HOST']);
define('MQTT_PORT',         $_ENV['MQTT_PORT']);
define('MQTT_RETAIN',       $_ENV['MQTT_RETAIN'] ?: 1);
define('MQTT_KEEPALIVE',    $_ENV['MQTT_KEEPALIVE'] ?: 1);
define('MQTT_BASE_TOPIC',   $_ENV['MQTT_BASE_TOPIC'] ?: 'odlinfo');

define('MQTT_PUBLISH', array(
    'result' => array(
        'topic'     =>  MQTT_BASE_TOPIC . '/station-data',
    ),
    'update-status' => array(
        'topic'     =>  MQTT_BASE_TOPIC . '/update-status',
    ),
));

define('MQTT_SUBSCRIBE', array(
    'commands' => array(
        'topic' =>  MQTT_BASE_TOPIC . '/command',
    )
));

# ------------------------------------------------------------------------------------------ resolve dependencies
require_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function ($class_name) {
    $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    require '/opt/Library/' . $class_name . '.php';
});

# ------------------------------------------------------------------------------------------ helper function
function update_stations(\PhpMqtt\Client\MqttClient $mqtt) {
    $update_status = ODLInfo::updateStationData();
    $update_status = ($update_status) ? 'true' : 'false';
    error_log("Updating stations exited with " . $update_status);

    $mqtt->publish(
        MQTT_PUBLISH['update-status']['topic'], $update_status, 0, 1
    );
}

function build_topics($array, $basetopic, &$returnVal) {

    foreach ($array as $key => $value) {

        if (is_array($value)) {
            build_topics($value, "$basetopic/$key", $returnVal);
            continue;
        }

        # convert php internal types to strings
        if ($value === true)    { $value = 'true';  }
        if ($value === false)   { $value = 'false'; }

        $returnVal["$basetopic/$key"] = $value;
    }
}

# ------------------------------------------------------------------------------------------ subscribe to trigger and execute
$mqtt = new \PhpMqtt\Client\MqttClient(MQTT_HOST, MQTT_PORT);

$connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
    ->setReconnectAutomatically(false)
    ->setConnectTimeout(300)
    ->setSocketTimeout(300);
$mqtt->connect($connectionSettings);

$mqtt->subscribe(MQTT_SUBSCRIBE['commands']['topic'], callback: function ($topic, $message, $retained) use ($mqtt, $connectionSettings) {
    printf("Received message '%s' on topic '%s'". PHP_EOL, $message, $topic);

    if ($message_data = json_decode($message, true)) {
        switch( $message_data['command'] ) {
            case 'update-station-data':
                error_log('Requested update for station data');
                update_stations($mqtt);
                break;

            case 'station-data':
                error_log("Requested data for stations: ". print_r($message_data['stations'], true));

                if (!is_array($message_data['stations']) || 0 == count($message_data['stations'])) {
                    error_log('No stations to filter for provided');
                }

                try {
                    $stations_found = ODLInfo::getStationByCode($message_data['stations']);

                    foreach ($stations_found as $single_station) {
                        $pubdata = array();

                        build_topics(
                            $single_station->toArray(),
                            MQTT_PUBLISH['result']['topic'] . "/{$single_station->kenn}",
                            $pubdata
                        );

                        foreach ($pubdata as $topic => $message) {
                            $mqtt->publish($topic, $message, 2, true);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Fehler: " . $e->getMessage());
                }

                break;
        }

        return;
    }

    if ($message == 'update-station-data') {
        error_log('Requested update for station without JSON command');
        update_stations($mqtt);
        return;
    }

    error_log("$message is not a valid JSON string");

}, qualityOfService: 2);

$mqtt->loop(true);
$mqtt->disconnect();
