FROM php:8.2-apache

# I-install ang mga kinakailangang libraries at PostgreSQL extensions para sa PHP
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Kopyahin ang lahat ng files patungo sa Apache web root
COPY . /var/www/html/

# I-enable ang Apache rewrite module
RUN a2enmod rewrite

# Buksan ang port 80 para sa web traffic
EXPOSE 80