#! /usr/bin/env php
<?php
# ------------------------------------------------------------------------------------------ global
namespace Maschinengeist\Services\Environment\Radioactivity\ODLInfo;
use Maschinengeist\Core\Helper as MG_Core_Helper;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\InvalidMessageException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\Exceptions\ProtocolNotSupportedException;
use PhpMqtt\Client\Exceptions\ProtocolViolationException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\MqttClient;

error_reporting(E_ALL);

# ------------------------------------------------------------------------------------------ set configuration from ENV
require_once 'Config.php';
date_default_timezone_set(Config::getTimeZone());

# ------------------------------------------------------------------------------------------ resolve dependencies
require_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function ($class_name) {
    $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    require '/opt/Library/' . $class_name . '.php';
});

# ------------------------------------------------------------------------------------------ mqtt connection

try {
    $mqttClient = new MqttClient(Config::getMqttHost(), Config::getMqttPort());
} catch (ProtocolNotSupportedException $e) {
    error_log($e->getMessage());
    exit(121);
}

$mqttConnectionSettings = (new ConnectionSettings)
    ->setReconnectAutomatically(false)
    ->setConnectTimeout(300)
    ->setKeepAliveInterval(Config::getMqttKeepAlive())
    ->setSocketTimeout(300);

if (Config::getMqttUsername()) {
    $mqttConnectionSettings->setUsername(Config::getMqttUsername());
}

if (Config::getMqttPassword()) {
    $mqttConnectionSettings->setPassword(Config::getMqttPassword());
}

try {
    $mqttClient->connect($mqttConnectionSettings);
} catch (ConfigurationInvalidException|ConnectingToBrokerFailedException $e) {
    error_log("Can't connect to MQTT: " . $e->getMessage());
    exit(107);
}

# ------------------------------------------------------------------------------------------ helper function
/**
 * @param MqttClient $mqttClient
 * @return bool
 */
function update_stations(MqttClient $mqttClient) : bool {

    $update_status = (ODLInfo::updateStationData()) ? 'true' : 'false';

    try {
        $mqttClient->publish(
            Config::getMqttResultTopic(), $update_status, 0, 1
        );
    } catch (DataTransferException|RepositoryException $e) {
        error_log(sprintf(
            "Couldn't push the update status '%s' to %s: %s",
            $update_status, Config::getMqttResultTopic(), $e->getMessage()
        ));
    }

    return true;
}

$command_channel_handler = function ($topic, $message, $retained) use ($mqttClient, $mqttConnectionSettings) {

    printf("Received message '%s' on topic '%s'". PHP_EOL, $message, $topic);

    if ($message_data = json_decode($message, true)) {
        switch( $message_data['command'] ) {
            case 'update-station-data':
                error_log('Requested update for station data');
                update_stations($mqttClient);
                break;

            case 'station-data':

                if (!is_array($message_data['stations']) || 0 == count($message_data['stations'])) {
                    MG_Core_Helper::logToMqtt('No stations to filter for provided', $mqttClient, Config::getMqttUpdateStatusTopic());
                }

                try {
                    $stations_found = ODLInfo::getStationByCode($message_data['stations']);

                    foreach ($stations_found as $single_station) {
                        $pubdata = array();

                        MG_Core_Helper::flattenArrayToMqttTopics(
                            $single_station->toArray(),
                            Config::getMqttResultTopic() . "/$single_station->kenn",
                            $pubdata
                        );

                        foreach ($pubdata as $topic => $message) {
                            $mqttClient->publish($topic, $message, 2, true);
                        }
                    }
                } catch (\Exception $e) {
                    MG_Core_Helper::logToMqtt($e->getMessage(), $mqttClient, Config::getMqttUpdateStatusTopic());
                }

                break;
        }

        return;
    }

    if ($message == 'update-station-data') {
        MG_Core_Helper::logToMqtt('Requested update for station without JSON command', $mqttClient, Config::getMqttErrorTopic());
        update_stations($mqttClient);
        return;
    }

    MG_Core_Helper::logToMqtt("$message is not a valid JSON string", $mqttClient, Config::getMqttErrorTopic());
};

# ------------------------------------------------------------------------------------------ banner
error_log(sprintf('*** WELCOME TO THE BfS Ortsdosisleistung MQTT Gateway Service, v%s', Config::getVersion()));
error_log("Configuration:");
error_log(print_r(Config::getCurrentConfig(), true));

# ------------------------------------------------------------------------------------------ subscribe to trigger and execute

if ( function_exists('pcntl_async_signals') ) {
    pcntl_async_signals(true);
}

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () use ($mqttClient) {
        $mqttClient->interrupt();
    });
}

try {
    $mqttClient->subscribe(Config::getMqttCommandTopic(), callback: $command_channel_handler, qualityOfService: 2);
} catch (DataTransferException|RepositoryException $e) {
    MG_Core_Helper::logToMqtt(
        $e->getMessage(),
        $mqttClient,
        Config::getMqttErrorTopic()
    );
}

try {
    $mqttClient->loop();
} catch (DataTransferException|InvalidMessageException|ProtocolViolationException|MqttClientException $e) {
    MG_Core_Helper::logToMqtt(
        $e->getMessage(),
        $mqttClient,
        Config::getMqttErrorTopic()
    );
}

try {
    $mqttClient->disconnect();
} catch (DataTransferException $e) {
    MG_Core_Helper::logToMqtt(
        "Can't disconnect from MQTT: " . $e->getMessage(),
        $mqttClient,
        Config::getMqttErrorTopic()
    );
}
