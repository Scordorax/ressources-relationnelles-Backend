FROM php:8.3-fpm-alpine

# Extensions système nécessaires
RUN apk add --no-cache \
    bash \
    git \
    curl \
    unzip \
    openssl \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        intl \
        zip \
        opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copier les fichiers de dépendances en premier (cache Docker)
COPY composer.json composer.lock ./

RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copier le reste du projet
COPY . .

# Finaliser l'autoloader et les scripts post-install
RUN composer dump-autoload --optimize \
    && composer run-script post-install-cmd --no-interaction || true

# Générer les clés JWT si elles n'existent pas
RUN mkdir -p config/jwt \
    && if [ ! -f config/jwt/private.pem ]; then \
        openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:${JWT_PASSPHRASE:-changeme}; \
        openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:${JWT_PASSPHRASE:-changeme}; \
    fi

# Permissions
RUN chown -R www-data:www-data var/ || true

EXPOSE 9000
