# Menggunakan image resmi PHP yang sudah dilengkapi web server Apache
FROM php:8.2-apache

# Menginstal ekstensi pdo_pgsql dan pgsql yang diwajibkan untuk PostgreSQL Supabase
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql

# Menyalin semua file PHP Anda dari GitHub ke dalam folder server web
COPY . /var/www/html/

# Membuka jalur akses port standar web (80)
EXPOSE 80
