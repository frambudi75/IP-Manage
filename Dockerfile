FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    libsnmp-dev \
    libssl-dev \
    snmp \
    && docker-php-ext-install mysqli pdo pdo_mysql gettext snmp \
    && a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
