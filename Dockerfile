# osTicket for Railway: the official osTicket release zip on the official PHP 8.4 Apache image.
# osTicket publishes no maintained Docker image, so the release zip is downloaded and checked
# against the sha256 GitHub publishes for the release asset.
FROM php:8.4.27-apache-trixie@sha256:884f64b47174de2c0e813270fe7b24a18605729fcebe201302d98c38805530d0

ARG OSTICKET_VERSION=1.18.4
ARG OSTICKET_SHA256=a4187df5f52dd41625e6b61b322245853542e12b5969a45f0a6d81f7ab8151a7

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates curl unzip \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libicu-dev libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" mysqli gd intl zip gettext opcache; \
    rm -rf /var/lib/apt/lists/*; \
    curl -fsSL -o /tmp/osticket.zip \
        "https://github.com/osTicket/osTicket/releases/download/v${OSTICKET_VERSION}/osTicket-v${OSTICKET_VERSION}.zip"; \
    echo "${OSTICKET_SHA256}  /tmp/osticket.zip" | sha256sum -c -; \
    mkdir -p /tmp/osticket /opt/osticket; \
    unzip -q /tmp/osticket.zip -d /tmp/osticket; \
    rm -rf /var/www/html; \
    mv /tmp/osticket/upload /var/www/html; \
    mv /var/www/html/setup /opt/osticket/setup; \
    rm -rf /tmp/osticket /tmp/osticket.zip; \
    chown -R root:root /var/www/html; \
    a2enmod rewrite headers

COPY railway/php.ini /usr/local/etc/php/conf.d/zz-osticket.ini
COPY railway/apache.conf /etc/apache2/conf-enabled/zz-osticket.conf
COPY railway/healthz.php /var/www/html/healthz.php
COPY railway/setup.php railway/install.php railway/start.sh /opt/osticket/railway/

RUN chmod 0755 /opt/osticket/railway/start.sh && echo "v${OSTICKET_VERSION}" > /opt/osticket/VERSION

ENV PORT=8080
EXPOSE 8080

CMD ["/opt/osticket/railway/start.sh"]
