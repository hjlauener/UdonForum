# ILIAS Slim (ILIAS 11 ohne einige Komponenten) – Webserver mit PHP 8.4
# Anforderungen laut docs/configuration/install.md von ILIAS 11:
# PHP 8.3/8.4, MariaDB 10.5–11.8, Node.js 22/24, ImageMagick, Ghostscript.
FROM php:8.4-apache

# Hilfsprogramme, die ILIAS braucht oder nutzt
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        git unzip zip imagemagick ghostscript mariadb-client ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# PHP-Erweiterungen (Pflicht laut install.md: gd, dom, xsl, pdo, pdo_mysql, curl,
# json, simplexml, libxml, xml, zip, imagick, mbstring – viele sind im Image schon dabei)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd xsl zip pdo_mysql mysqli intl opcache imagick

# Composer und Node.js 24 (für "composer install" und "npm clean-install")
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Webserver: Dokumentenwurzel ist public/ (ILIAS 10+)
COPY apache-ilias.conf /etc/apache2/sites-available/000-default.conf
COPY php-ilias.ini /usr/local/etc/php/conf.d/zz-ilias.ini
RUN a2enmod rewrite headers

# Ordner ausserhalb der Dokumentenwurzel: Daten, Logs, Setup-Konfiguration
RUN mkdir -p /var/www/files /var/www/logs/errors /var/www/config /var/www/.npm \
 && chown -R www-data:www-data /var/www

COPY entrypoint.sh /usr/local/bin/ilias-entrypoint
RUN chmod 0755 /usr/local/bin/ilias-entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["ilias-entrypoint"]
CMD ["apache2-foreground"]
