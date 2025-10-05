# Gebruik de officiële PHP 8.3 Apache image
FROM php:8.3-apache

# Installeer systeemafhankelijkheden
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Schakel mod_rewrite in voor Apache (nodig voor Symfony routing)
RUN a2enmod rewrite

# Zorg ervoor dat Apache `.htaccess` toestaat
RUN sed -i 's|AllowOverride None|AllowOverride All|' /etc/apache2/apache2.conf

# Stel de correcte DocumentRoot in zodat Symfony werkt
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Geef de server een naam om Apache-warnings te voorkomen
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Stel de werkdirectory in
WORKDIR /var/www/html

# Maak een symfony-gebruiker aan met dezelfde UID en GID als de host
ARG UID=1000
ARG GID=1000
RUN groupadd -g $GID symfony && useradd -m -u $UID -g $GID symfony

# Kopieer projectbestanden naar de container (nog als root)
COPY . .

# Installeer Composer in de container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Fix git ownership en composer permissions
RUN git config --global --add safe.directory /var/www/html
RUN chown -R symfony:symfony /var/www/html

# Voer Composer-installatie uit als symfony-gebruiker
USER symfony
RUN composer install --optimize-autoloader
USER root

# Zorg ervoor dat var directory bestaat en de juiste permissions heeft
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/log
RUN chown -R www-data:www-data /var/www/html/var
RUN chmod -R 775 /var/www/html/var

# Geef Apache (www-data) toegang tot alle bestanden
RUN chown -R www-data:www-data /var/www/html
RUN usermod -a -G symfony www-data
