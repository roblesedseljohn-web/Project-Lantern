FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo_mysql
RUN printf '%s\n' 'upload_max_filesize=2048M' 'post_max_size=2100M' 'memory_limit=1G' 'max_execution_time=600' 'max_input_time=600' > /usr/local/etc/php/conf.d/uploads.ini
RUN a2enmod headers
