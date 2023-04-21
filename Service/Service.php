#! /usr/bin/env php
<?php
# ------------------------------------------------------------------------------------------ global
namespace Maschinengeist\Services\Environment\Radioactivity\ODLInfo;

error_reporting(E_ALL);
date_default_timezone_set($_ENV['TZ'] ?: 'Europe/Berlin');

# ------------------------------------------------------------------------------------------ set configuration from ENV

$_ENV['MQTT_HOST'] ?? exit('MQTT_HOST is not set, no default value is available, aborting.');

define('MQTT_HOST',         $_ENV['MQTT_HOST']);
define('MQTT_PORT',         $_ENV['MQTT_PORT']          ?: 1883);
define('MQTT_RETAIN',       $_ENV['MQTT_RETAIN']        ?: 1);
define('MQTT_KEEPALIVE',    $_ENV['MQTT_KEEPALIVE']     ?: 1);
define('MQTT_BASE_TOPIC',   $_ENV['MQTT_BASE_TOPIC']    ?: 'odlinfo');

$debug = $_ENV['DEBUG'] ?: false;
$debug = $debug == 'true';
define('DEBUG', $debug);

define('MQTT_PUBLISH_TOPIC', array(
    'result' => array(
        'topic'     =>  MQTT_BASE_TOPIC . '/station-data',
    ),
    'update-status' => array(
        'topic'     =>  MQTT_BASE_TOPIC . '/update-status',
    ),
    'command-status' => array(
        'topic'     =>  MQTT_BASE_TOPIC . '/command-status',
    ),
));

define('MQTT_SUBSCRIBE_TOPIC', array(
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
    $update_status = (ODLInfo::updateStationData()) ? 'true' : 'false';
    error_log("Updating stations exited with " . $update_status);

    $mqtt->publish(
        MQTT_PUBLISH_TOPIC['update-status']['topic'], $update_status, 0, 1
    );
}

function log_errors($error_msg, \PhpMqtt\Client\MqttClient $mqtt, $topic) {
    error_log($error_msg);
    $mqtt->publish($topic, $error_msg, 2, false);
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

# ------------------------------------------------------------------------------------------ start
error_log('*** WELCOME TO THE BfS Ortsdosisleistung MQTT Gateway Service');

if (DEBUG === true) {
    error_log('Configuration: ');

    $values = array(
        'MQTT Host' => MQTT_HOST,
        'MQTT Port' => MQTT_PORT,
        'MQTT Basetopic' => MQTT_BASE_TOPIC,
        'MQTT Keep alive' => MQTT_KEEPALIVE,
        'Retain messages' => MQTT_RETAIN,
    );

    foreach($values as $desc => $value) {
        error_log("\t$desc: $value");
    }

    error_log("\tMQTT Topics:");
    error_log("\t\tPublish:");

    foreach (array_keys(MQTT_PUBLISH_TOPIC) as $topics) {
        error_log("\t\t\t$topics -> " . MQTT_PUBLISH_TOPIC["$topics"]['topic'] );
    }

    error_log("\t\tSubscribe:");

    foreach (array_keys(MQTT_SUBSCRIBE_TOPIC) as $topics) {
        error_log("\t\t\t$topics -> " . MQTT_SUBSCRIBE_TOPIC["$topics"]['topic'] );
    }
}

# ------------------------------------------------------------------------------------------ subscribe to trigger and execute

$mqtt = new \PhpMqtt\Client\MqttClient(MQTT_HOST, MQTT_PORT);

$connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
    ->setReconnectAutomatically(false)
    ->setConnectTimeout(300)
    ->setSocketTimeout(300);
$mqtt->connect($connectionSettings);

$mqtt->subscribe(MQTT_SUBSCRIBE_TOPIC['commands']['topic'], callback: function ($topic, $message, $retained) use ($mqtt, $connectionSettings) {
    printf("Received message '%s' on topic '%s'". PHP_EOL, $message, $topic);

    if ($message_data = json_decode($message, true)) {
        switch( $message_data['command'] ) {
            case 'update-station-data':
                error_log('Requested update for station data');
                update_stations($mqtt);
                break;

            case 'station-data':
                if (!is_array($message_data['stations']) || 0 == count($message_data['stations'])) {
                    log_errors('No stations to filter for provided', $mqtt, MQTT_PUBLISH_TOPIC['update-status']);
                }

                try {
                    $stations_found = ODLInfo::getStationByCode($message_data['stations']);

                    foreach ($stations_found as $single_station) {
                        $pubdata = array();

                        build_topics(
                            $single_station->toArray(),
                            MQTT_PUBLISH_TOPIC['result']['topic'] . "/{$single_station->kenn}",
                            $pubdata
                        );

                        foreach ($pubdata as $topic => $message) {
                            $mqtt->publish($topic, $message, 2, true);
                        }
                    }
                } catch (\Exception $e) {
                    log_errors($e->getMessage(), $mqtt, MQTT_PUBLISH_TOPIC['update-status']);
                }

                break;
        }

        return;
    }

    if ($message == 'update-station-data') {
        log_errors('Requested update for station without JSON command', $mqtt, MQTT_PUBLISH_TOPIC['command-status']);
        update_stations($mqtt);
        return;
    }

    log_errors("$message is not a valid JSON string", $mqtt, MQTT_PUBLISH_TOPIC['command-status']);

if ( function_exists('pcntl_async_signals') ) {
    pcntl_async_signals(true);
}

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () use ($mqttClient) {
        $mqttClient->interrupt();
    });
}

}, qualityOfService: 2);

$mqtt->loop(true);
$mqtt->disconnect();
