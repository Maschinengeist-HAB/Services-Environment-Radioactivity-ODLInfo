<?php

namespace Maschinengeist\Services\Environment\Radioactivity\ODLInfo;

class ODLInfo
{
    const cache_dir = '/tmp';
    const cache =   array(
        'all_stations' => array(
            'file'  =>  'all_stations.json',
        ),
    );

    const url_all_stations          =   'https://www.imis.bfs.de/ogc/opendata/ows?service=WFS&version=1.1.0&request=GetFeature&'
                                    .   'typeName=opendata:odlinfo_odl_1h_latest&outputFormat=application/json';
    const site_status = array(
        'functional' => 1,
        'defunct' => 2,
        'test' => 3,
    );

    private array $data = array();

    public function __get($key)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $trace = debug_backtrace();
        throw new \Exception('Undefined property for __get(): ' . $key .
            ' in ' . $trace[0]['file'] .
            ' line ' . $trace[0]['line']);
    }

    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    private function __construct()
    {

    }

    private function getAllStations(?bool $use_cache = true, ?int $cache_max_age = 55*60): array
    {
        $odl_data   = null;
        $cache_used = false;
        $cache_file = self::cache_dir . DIRECTORY_SEPARATOR . self::cache['all_stations']['file'];

        do {

            if (!$use_cache) {
                error_log("Don't use cache at all");
                break;
            }

            error_log("Using cache file $cache_file");

            if (!file_exists($cache_file)) {
                error_log("Cache file does not exists");
                break;
            }

            if (!$cache_mtime = filemtime($cache_file)) {
                error_log("Cache modification time could not retrieved");
                break;
            }

            if (!$cache_size = filesize($cache_file)) {
                error_log("Can't retrieve size of cache file");
                break;
            }

            if ($cache_size < 1000*100) {
                error_log("Size of cache file is $cache_size and is below 100 kByte");
                break;
            }

            if ($cache_mtime + $cache_max_age < time()) {
                error_log(sprintf("Cache was invalidated at %d, now it's %d", $cache_mtime + $cache_max_age, time() ));
                break;
            }

            if (!$raw_data = file_get_contents($cache_file)) {
                error_log("Could not read the cache file");
                break;
            }

            $cache_used = true;

        } while (false);

        if (!$cache_used) {
            if (!$raw_data = file_get_contents(self::url_all_stations)) {
                error_log("Could not retrieve data from ODL");
            }
        }

        if (is_null($raw_data)) {
            throw new \Exception("No ODL data is available, neither by reading it directly, nor by using the cache");
        }

        $odl_data = json_decode($raw_data, false);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception('Parsing JSON api data was not successful', json_last_error());
        }

        if ( 0 === count(get_object_vars($odl_data))) {
            throw new \Exception("Data retrieved successfully, but no stations are available");
        }

        if (!file_put_contents($cache_file, $raw_data)) {
            error_log("Could not write cache");
        }

        $stations = array();

        foreach ($odl_data->features as $station_data) {
            $stations[] = new Station($station_data);
        }

        return $stations;
    }

    protected function convertToUnixtime(string $date): string
    {
        try {
            $dt = new \DateTime($date);
        } catch (\Exception $exception) {
            trigger_error(
                "Can't create DateTime object from $date: " . $exception->getMessage() .
                ' in ' . $exception->getFile() .
                ' line ' . $exception->getLine(),
                E_USER_ERROR
            );
        }

        return $dt->format('U');
    }

    public static function updateStationData(): bool
    {
        $odl_obj = new static();
        try {
            $odl_obj->getAllStations(false);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }

    public static function getStationByCode(array $filter_station_codes): mixed
    {
        $odl_obj = new static();
        $all_stations = $odl_obj->getAllStations();

        if (0 == count($filter_station_codes)) {
            throw new \Exception('No station codes to filter for');
        }

        $filtered_stations = array();

        foreach ($all_stations as $station) {
            if (in_array($station->kenn, $filter_station_codes)) {
                error_log("Station {$station->kenn} found");

                # save station to bucket
                $filtered_stations[] = $station;

                # remove station code from filter list
                $filter_station_codes = array_diff($filter_station_codes, [ $station->kenn ]);
            }
        }

        if (0 != count($filter_station_codes)) {
            error_log('Unknown station codes: ' . implode(', ', $filter_station_codes));
        }

        if (!is_array($filtered_stations) || 0 == count($filtered_stations)) {
            error_log('No stations found');
        }

        return $filtered_stations;
    }
}