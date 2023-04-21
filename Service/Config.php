<?php
namespace Maschinengeist\Services\Environment\Radioactivity\ODLInfo;
class Config {

    public static function getVersion() : string {
        return '1.0.0';
    }

    public static function getMqttHost() : string {
        return $_ENV['MQTT_HOST'] ?? 'message-broker';
    }

    public static function getMqttPort() : int {
        return (int) ($_ENV['MQTT_PORT'] ?? 1883);
    }

    public static function getMqttUsername() : string {
        return $_ENV['MQTT_USERNAME'] ?? '';
    }

    public static function getMqttPassword() : string {
        return $_ENV['MQTT_PASSWORD'] ?? '';
    }

    public static function getMqttKeepAlive() : bool {
        return $_ENV['MQTT_KEEP_ALIVE'] ?? true;
    }

    public static function getMqttBaseTopic() : string {
        return $_ENV['MQTT_BASE_TOPIC'] ?? 'maschinengeist/services/environment/radioactivity/odlinfo';
    }

    public static function getMqttResultTopic() : string {
        return self::getMqttBaseTopic() . '/results';
    }

    public static function getMqttCommandTopic() : string {
        return self::getMqttBaseTopic() . '/command';
    }

    public static function getMqttUpdateStatusTopic() : string {
        return self::getMqttBaseTopic() . '/update-status';
    }

    public static function getMqttErrorTopic() : string {
        return self::getMqttBaseTopic() . '/errors';
    }

    /**
     * Get the configured timezone
     *
     * @see https://www.php.net/manual/en/timezones.php
     * @return string Timezone, defaults to Europe/Berlin
     */
    public static function getTimeZone() : string {
        $tz = $_ENV['TZ'] ?? '';

        if (true === in_array($tz, \DateTimeZone::listIdentifiers()))  {
            return $tz;
        }

        return 'Europe/Berlin';
    }

    public static function getCurrentConfig(): array {
        return array(
            'mqtt topics' => array(
                'base' => self::getMqttBaseTopic(),
                'result' => self::getMqttResultTopic(),
                'command' => self::getMqttCommandTopic(),
                'update status' => self::getMqttUpdateStatusTopic(),
            ),
            'connection data' => array(
                'host' => self::getMqttHost(),
                'port' => self::getMqttPort(),
                'user' => self::getMqttUsername(),
                'password' => self::getMqttPassword(),
                'keep alive' => self::getMqttKeepAlive(),
            ),
            'application config' => array(
                'timezone' => self::getTimeZone(),
            ),
        );
    }
}
