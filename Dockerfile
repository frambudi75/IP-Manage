FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    libsnmp-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    snmp \
    nmap \
    traceroute \
    iputils-ping \
    && docker-php-ext-install mysqli pdo pdo_mysql gettext snmp curl \
    && a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/entrypoint.sh

EXPOSE 80

# Use the entrypoint script to run both Apache and background cron
ENTRYPOINT ["/var/www/html/entrypoint.sh"]
