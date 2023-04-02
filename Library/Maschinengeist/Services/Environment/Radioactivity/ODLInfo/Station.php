<?php
namespace Maschinengeist\Services\Environment\Radioactivity\ODLInfo;

class Station extends \Maschinengeist\Services\Environment\Radioactivity\ODLInfo\ODLInfo
{
    private array $data;

    public function __get($key)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        error_log("$key does not exist in station data");
        return null;
    }

    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function toArray() {
        return (array) $this->data;
    }

    public function __construct(object $station_data)
    {
        $this->data = (array) $station_data->properties;

        $this->start_measure_ts = ($this->start_measure)
            ? (int) $this->convertToUnixtime($this->start_measure) : null;

        $this->end_measure_ts   = ($this->end_measure)
            ? (int) $this->convertToUnixtime($this->end_measure) : null;

        return $this;
    }
}