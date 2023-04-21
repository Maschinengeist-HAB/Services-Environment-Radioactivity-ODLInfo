FROM php:8.1-alpine

RUN apk add bash

LABEL org.opencontainers.image.source=https://github.com/Maschinengeist-HAB/Services-Environment-Radioactivity-ODLInfo
LABEL org.opencontainers.image.description="BfS Ortsdosisleistung MQTT Gateway"
LABEL org.opencontainers.image.licenses=MIT

COPY Service /opt/Service
COPY Library /opt/Library

VOLUME [ "/opt/Service" ]
WORKDIR "/opt/Service/"
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
CMD ["bash", "./Entry.sh"]