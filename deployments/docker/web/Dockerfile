ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm AS builder

USER root
ARG NGINX_VERSION=1.28.0

RUN apt-get update && apt-get install -y \
    git build-essential curl \
    libpcre2-dev zlib1g-dev libssl-dev libbrotli-dev \
    ca-certificates wget gnupg \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /tmp
RUN curl -fsSL "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -o nginx.tgz \
 && tar -xzf nginx.tgz && mv nginx-* nginx && rm nginx.tgz

WORKDIR /tmp/nginx
RUN git clone --recurse-submodules https://github.com/google/ngx_brotli.git

RUN ./configure \
    --prefix=/etc/nginx \
    --sbin-path=/usr/sbin/nginx \
    --modules-path=/etc/nginx/modules \
    --conf-path=/etc/nginx/nginx.conf \
    --error-log-path=/var/log/nginx/error.log \
    --http-log-path=/var/log/nginx/access.log \
    --pid-path=/run/nginx.pid \
    --with-compat \
    --with-file-aio \
    --with-threads \
    --with-http_ssl_module \
    --with-http_v2_module \
    --with-http_v3_module \
    --with-http_gzip_static_module \
    --with-http_realip_module \
    --add-dynamic-module=./ngx_brotli \
    && make -j"$(nproc)" \
    && make install

# ==========================================================
# Runtime Image
# ==========================================================

FROM php:${PHP_VERSION}-fpm
ARG INSTALL_XDEBUG=0

WORKDIR /srv/app/
ENV HOME=/srv/app

# ── MySQL 8 official client ────────────────────────────────────────────
# default-mysql-client (MariaDB) cannot authenticate with MySQL 8's
# caching_sha2_password. We add MySQL's APT repo to get the real client.
# mariadb-client is also installed to provide mariadb-dump for MariaDB servers.
# After both installs, the MySQL 8 mysqldump is pinned via update-alternatives.
#
# arm64 caveat: repo.mysql.com ships debian/trixie for amd64 ONLY — every
# component (mysql-8.4-lts, mysql-8.0, mysql-tools) 404s on binary-arm64, so
# `apt-get install mysql-community-client` dies with "Unable to locate package"
# on an Apple Silicon host. There is no arm64 build to fall back to, so on that
# architecture we skip MySQL's repo entirely and let mariadb-client provide
# mysqldump. Consequence, and it is a real one: mariadb-dump cannot log in to a
# MySQL 8 server that uses caching_sha2_password, so `make database-export`
# against such a server will fail on arm64 — a dev-machine-only limitation.
# amd64 (CI, the deploy gate, production) keeps the exact original behaviour.
ARG MYSQL_APT_VERSION=mysql-apt-config_0.8.36-1_all.deb
RUN apt-get update && apt-get install -y wget gnupg lsb-release \
 && if [ "$(dpkg --print-architecture)" = "amd64" ]; then \
      wget -qO /tmp/mysql-apt.deb "https://dev.mysql.com/get/${MYSQL_APT_VERSION}" \
   && echo "mysql-apt-config mysql-apt-config/select-server select mysql-8.4-lts" | debconf-set-selections \
   && DEBIAN_FRONTEND=noninteractive dpkg -i /tmp/mysql-apt.deb \
   && rm /tmp/mysql-apt.deb \
   && apt-get update \
   && apt-get install -y mysql-community-client \
   && cp /usr/bin/mysqldump /usr/bin/mysqldump-mysql8 \
   && apt-get install -y mariadb-client \
   && update-alternatives --install /usr/bin/mysqldump mysqldump /usr/bin/mysqldump-mysql8 200 \
   && update-alternatives --set mysqldump /usr/bin/mysqldump-mysql8 ; \
    else \
      echo "[web] $(dpkg --print-architecture): no MySQL apt arm64 build, using mariadb-client for mysqldump" \
   && apt-get install -y mariadb-client ; \
    fi \
 && mysqldump --version \
 && mariadb-dump --version \
 && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y \
    wget bash nano git \
    nodejs npm jq \
    rsync cron tzdata \
    libxml2-dev libxslt-dev \
    libzip-dev zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libicu-dev \
    libmagickwand-dev \
    librabbitmq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && corepack enable \
    && COREPACK_HOME=/opt/corepack corepack prepare yarn@stable --activate \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/log/nginx /var/cache/nginx
COPY --from=builder /etc/nginx /etc/nginx
COPY --from=builder /usr/sbin/nginx /usr/sbin/nginx

# Self-signed SSL (dev only)
RUN mkdir -p /etc/nginx/ssl && \
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/localhost.key \
    -out /etc/nginx/ssl/localhost.crt \
    -subj "/CN=localhost"

# Composer
RUN curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/local/bin --filename=composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# PHP extensions (core)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
       ftp xsl bcmath zip pdo_mysql exif gd intl opcache

# Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Imagick
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# AMQP
RUN pecl install amqp \
    && docker-php-ext-enable amqp

# php-git2 (libgit2 bindings, RogerGee/php-git2) - git/git-bundle reads each
# package's repository through it, in-process: its tags, its branches, their
# last commits (the versions a package page shows and the install selector's
# constraints). The same block as glitchr's image.
RUN apt-get update && apt-get install -y --no-install-recommends libgit2-dev \
    && rm -rf /var/lib/apt/lists/* \
    && git clone --depth 1 https://github.com/RogerGee/php-git2.git /usr/src/php-git2 \
    && cd /usr/src/php-git2 \
    && phpize \
    && ./configure --with-libgit2=/usr \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable git2 \
    && rm -rf /usr/src/php-git2

# APCu + igbinary
RUN pecl install apcu igbinary \
    && docker-php-ext-enable apcu igbinary

# Xdebug (opt-in via INSTALL_XDEBUG=1; kept out of prod builds by default)
RUN if [ "$INSTALL_XDEBUG" = "1" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

# Install Blackfire probe (Debian)
RUN curl -A "Docker" -fsSL https://blackfire.io/api/v1/releases/probe/php/linux/$(uname -m)/$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;") \
    -o /tmp/blackfire-probe.tar.gz \
 && mkdir -p /tmp/blackfire \
 && tar -xzf /tmp/blackfire-probe.tar.gz -C /tmp/blackfire \
 && mv /tmp/blackfire/blackfire-*.so "$(php -r 'echo ini_get("extension_dir");')/blackfire.so" \
 && echo "extension=blackfire.so" > /usr/local/etc/php/conf.d/blackfire.ini \
 && rm -rf /tmp/blackfire*