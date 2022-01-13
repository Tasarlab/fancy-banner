FROM php:7.4.4-fpm-alpine3.11

# Install the system-level dependencies.
RUN apk add --no-cache \
  curl-dev \
  oniguruma-dev \
  libzip-dev \
  libtool \
  libxml2-dev \
  gettext-dev \
  curl \
  git \
  mysql-client \
  nodejs \
  npm \
  && apk add --no-cache --virtual buildDeps ${PHPIZE_DEPS} \
  && pecl install -o -f redis \
  && rm -rf /tmp/pear \
  && docker-php-ext-enable redis \
  && docker-php-ext-install \
  curl \
  iconv \
  mbstring \
  pdo \
  pdo_mysql \
  pcntl \
  tokenizer \
  xml \
  zip \
  opcache \
  gettext \
  && curl -s https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer \
  && rm -rf /var/cache/apk/* \
  && rm -rf /root/.cache \
  && apk del buildDeps

WORKDIR /app

# Copy and install Composer dependencies early on so that they can be cached.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

# Copy the application code itself.
COPY --chown=www-data:www-data . /app/
RUN composer dump-autoload --optimize
