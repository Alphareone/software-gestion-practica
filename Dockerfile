FROM php:8.1-apache

# Actualizar dependencias e instalar librerías del sistema necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libxml2-dev \
    libonig-dev \
    && docker-php-ext-install pdo_mysql zip xml mbstring gd \
    && a2enmod rewrite

# Copiar Composer desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar el DocumentRoot de Apache para que apunte a public_html/
ENV APACHE_DOCUMENT_ROOT=/var/www/SOFWARE-DE-GESTI-N/public_html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

WORKDIR /var/www

# Crear enlace simbólico para simular la estructura de producción
RUN ln -s /var/www/SOFWARE-DE-GESTI-N /var/www/backend-software

EXPOSE 80
