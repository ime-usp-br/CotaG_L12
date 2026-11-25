# =============================================================================
# CotaG - Imagem de produção (Nginx + PHP-FPM no mesmo container)
# =============================================================================
# Modelo baseado no projeto Nova Europa:
#   - build multi-stage (assets/dependências separadas do runtime)
#   - imagem final enxuta, sem Node.js
#   - extensões necessárias para MySQL, SQL Server (Replicado) e FreeTDS
#
# A aplicação é exposta apenas na rede Docker "web"; o Nginx Proxy Manager
# (NPM) faz a terminação TLS e o roteamento para o container.
# =============================================================================

# =============================================================================
# STAGE 1: build de dependências PHP e assets frontend
# =============================================================================
FROM php:8.2-cli AS builder

ARG NODE_VERSION=22

WORKDIR /build

ENV DEBIAN_FRONTEND=noninteractive

RUN echo 'Acquire::Retries "10";' > /etc/apt/apt.conf.d/80-retries \
    && apt-get update -o Acquire::Retries=10 \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        gnupg \
        curl \
        git \
        zip \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        unixodbc-dev \
        freetds-dev \
    && rm -rf /var/lib/apt/lists/* \
    && for i in 1 2 3; do \
        curl -fsSL -o /usr/local/bin/install-php-extensions \
            https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        && break; \
        sleep 15; \
    done \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql \
        pdo_sqlite \
        intl \
        gd \
        zip \
        bcmath \
        opcache \
        xml \
        curl \
        sqlsrv \
        pdo_sqlsrv \
    && docker-php-ext-install pdo_dblib \
    && php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');" \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet \
    && rm -f /tmp/composer-setup.php \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update -o Acquire::Retries=10 \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Dependências PHP (sem scripts: o código ainda não foi copiado)
COPY composer.json composer.lock package.json package-lock.json ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

# Código + assets
COPY . .

RUN npm ci --production=false \
    && npm run build \
    && composer dump-autoload --optimize --no-dev

# =============================================================================
# STAGE 2: testes (usado somente por docker-compose.test.yml)
# =============================================================================
FROM builder AS test

# Reinstala dependencias de desenvolvimento e executa a suite isolada.
RUN composer install \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader

CMD ["php", "artisan", "test"]

# =============================================================================
# STAGE 3: runtime (Nginx + PHP-FPM no mesmo container)
# =============================================================================
FROM php:8.2-fpm

LABEL maintainer="IME-USP Development Team"
LABEL description="CotaG - Production Image (Nginx + PHP-FPM)"

ARG WWWGROUP=1000
ARG WWWUSER=1000

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Sao_Paulo

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && echo 'Acquire::Retries "10";' > /etc/apt/apt.conf.d/80-retries

RUN apt-get update -o Acquire::Retries=10 && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
        curl \
        ca-certificates \
        default-mysql-client \
        postgresql-client \
        zip \
        unzip \
        supervisor \
        nginx \
        libpng-dev \
        libicu-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        unixodbc-dev \
        freetds-bin \
        freetds-common \
        freetds-dev \
    && rm -rf /var/lib/apt/lists/*

# TLS legado exigido pelo servidor legado do Replicado
RUN mkdir -p /etc/gnutls \
    && echo 'LEGACY' > /etc/gnutls/config.tds

RUN for i in 1 2 3; do \
        curl -fsSL -o /usr/local/bin/install-php-extensions \
            https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        && break; \
        sleep 15; \
    done \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql \
        intl \
        gd \
        zip \
        bcmath \
        opcache \
        xml \
        curl \
        sqlsrv \
        pdo_sqlsrv \
    && docker-php-ext-install pdo_dblib

# Ajusta UID/GID do www-data para casar com o host (volume nomeado de storage)
RUN if getent group $WWWGROUP > /dev/null 2>&1; then \
        echo "Group $WWWGROUP already exists"; \
    else \
        groupmod -g $WWWGROUP www-data; \
    fi \
    && if getent passwd $WWWUSER > /dev/null 2>&1; then \
        echo "User $WWWUSER already exists"; \
    else \
        usermod -u $WWWUSER www-data; \
    fi \
    && usermod -g www-data www-data

# Código compilado no stage anterior
COPY --from=builder --chown=www-data:www-data /build /var/www/html

# O runtime nao leva testes nem arquivos de ambiente de teste
RUN rm -rf \
        /var/www/html/tests \
        /var/www/html/phpunit.xml \
        /var/www/html/.env.testing \
        /var/www/html/.env.dusk.ci \
        /var/www/html/.env.dusk.local

# Configurações de runtime
COPY docker/production/nginx.conf /etc/nginx/nginx.conf.template
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/supervisord.worker.conf /etc/supervisor/conf.d/supervisord.worker.conf
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/production/freetds.conf.template /etc/freetds/freetds.conf.template
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /var/www/html/scripts/*.sh \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/private \
        storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV WEB_PORT=8080

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=5 \
    CMD ["/var/www/html/scripts/healthcheck.sh"]

STOPSIGNAL SIGTERM

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
