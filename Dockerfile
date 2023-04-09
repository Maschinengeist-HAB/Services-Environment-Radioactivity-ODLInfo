FROM arm64v8/php:alpine

ENV MQTT_HOST=message-broker \
    MQTT_PORT=1883 \
    MQTT_RETAIN=1 \
    MQTT_KEEPALIVE=120 \
    MQTT_BASE_TOPIC="odlinfo" \
    TZ=Europe/Berlin \
    DEBUG=false

RUN apk add \
    bash

LABEL org.opencontainers.image.source=https://github.com/Maschinengeist-HAB/Services-Environment-Radioactivity-ODLInfo
LABEL org.opencontainers.image.description="BfS Ortsdosisleistung MQTT Gateway"
LABEL org.opencontainers.image.licenses=MIT

COPY Service /opt/Service
COPY Library /opt/Library

VOLUME [ "/opt/Service" ]
WORKDIR "/opt/Service/"
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
CMD ["bash", "./Entry.sh"]