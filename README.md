# Services-Environment-Radioactivity-ODLInfo
MQTT Gateway Service for German Federal BfS Ortsdosisleistung (Ambient Dose Rate)

## Configuration

The service uses a set of environment variables for configuration in the Dockerfile:

| Variable          | Use                                                                | Default value    |
|-------------------|--------------------------------------------------------------------|------------------|
| `MQTT_HOST`       | Specifies the MQTT broker host name                                | `message-broker` |
| `MQTT_PORT`       | Specifies the MQTT port                                            | `1883`           |
| `MQTT_RETAIN`     | Retain messages or not                                             | `1` (retain)     |
| `MQTT_KEEPALIVE`  | Keep alive the connection to the MQTT broker every ```n``` seconds | `120`            |
| `MQTT_BASE_TOPIC` | MQTT base topic, will e                                            | `odlinfo`        |

## How to pull and run this image
Pull this image by

    docker pull ghcr.io/maschinengeist-hab/services-environment-radioactivity-odlinfo:release-1.0.0

Run this image by

    docker run -d --name odlinfo ghcr.io/maschinengeist-hab/services-environment-radioactivity-odlinfo:release-1.0.0

## Command examples
Note: `odlinfo`, the default value for `MQTT_BASE_TOPIC`, will be used as an example value for the MQTT topic.

### Update station data
Publish

    update-station-data

to `odlinfo/command`


### Get data for a specific station
Publish

    {
        "command": "station-data",
        "stations": [
            "064110003"
        ]
    }

to `odlinfo/command`

### Get data for a set of stations
Publish

    {
        "command": "station-data",
        "stations": [
            "064110003", "057110000"
        ]
    }

to `odlinfo/command`