FROM php:8.2-apache

# Kopyahin ang lahat ng files patungo sa Apache web root
COPY . /var/www/html/

# I-enable ang Apache rewrite module para sa clean URLs kung kailangan
RUN a2enmod rewrite

# Buksan ang port 80 para sa web traffic
EXPOSE 80