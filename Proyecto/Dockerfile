FROM php:8.2-apache

# Habilitar mod_rewrite (útil para frameworks como Laravel o rutas limpias)
RUN a2enmod rewrite

# Instalar dependencias para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Copiar el contenido del proyecto al contenedor
COPY ./public /var/www/html

# Dar permisos (opcional)
RUN chown -R www-data:www-data /var/www/html