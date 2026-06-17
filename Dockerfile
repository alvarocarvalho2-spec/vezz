FROM php:8.1-fpm

ENV DEBIAN_FRONTEND=noninteractive

# Instala dependências do sistema e extensões PHP necessárias
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        git \
        unzip \
        libpng-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy nginx and supervisor configs
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application
WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Configure php-fpm to listen on TCP localhost:9000 (fallback)
RUN if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then \
            sed -i "s|listen = .*|listen = 127.0.0.1:9000|" /usr/local/etc/php-fpm.d/www.conf || true; \
            sed -i "s|;listen.owner = www-data|listen.owner = www-data|" /usr/local/etc/php-fpm.d/www.conf || true; \
            sed -i "s|;listen.group = www-data|listen.group = www-data|" /usr/local/etc/php-fpm.d/www.conf || true; \
            sed -i "s|;listen.mode = 0660|listen.mode = 0660|" /usr/local/etc/php-fpm.d/www.conf || true; \
        fi

CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
