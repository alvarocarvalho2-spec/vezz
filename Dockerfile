FROM php:8.2-apache

# Instala dependências necessárias (Postgres, curl, ferramentas básicas)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev zip unzip git curl \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Copia projeto para o diretório padrão do Apache
COPY . /var/www/html

WORKDIR /var/www/html

# Permissões mínimas
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

# Healthcheck simples
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]
