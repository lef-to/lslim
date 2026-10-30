# The 8.3 tag follows the latest PHP 8.3 security release.
FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libsqlite3-dev \
        libzip-dev \
        procps \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath intl mbstring pcntl pdo_mysql pdo_sqlite zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear \
    && groupadd --gid 1000 vscode \
    && useradd --uid 1000 --gid vscode --create-home --shell /bin/bash vscode

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-development.ini

USER vscode
WORKDIR /workspaces/lslim

CMD ["sleep", "infinity"]
