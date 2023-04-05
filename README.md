# Services-Environment-Radioactivity-ODLInfo
MQTT Gateway Service for [German Federal BfS Ortsdosisleistung](https://odlinfo.bfs.de/ODL/DE/home/home_node.html) (Ambient Dose Rate).

## Configuration
The service uses a set of environment variables for configuration in the Dockerfile:

| Variable          | Usage                                                                          | Default value    |
|-------------------|--------------------------------------------------------------------------------|------------------|
| `MQTT_HOST`       | Specifies the MQTT broker host name                                            | `message-broker` |
| `MQTT_PORT`       | Specifies the MQTT port                                                        | `1883`           |
| `MQTT_RETAIN`     | Retain messages or not                                                         | `1` (retain)     |
| `MQTT_KEEPALIVE`  | Keep alive the connection to the MQTT broker every *n* seconds                 | `120`            |
| `MQTT_BASE_TOPIC` | MQTT base topic, will prepend to the defined topics, i.e. `base_topic/command` | `odlinfo`        |

## How to pull and run this image
Pull this image by

    docker pull ghcr.io/maschinengeist-hab/services-environment-radioactivity-odlinfo:latest

Run this image by

    docker run -d --name odlinfo ghcr.io/maschinengeist-hab/services-environment-radioactivity-odlinfo:latest

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

## License

    Copyright 2023 Christoph 'knurd' Morrison

    Licensed under the MIT license:

    http://www.opensource.org/licenses/mit-license.php

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:
    
    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.
    
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.