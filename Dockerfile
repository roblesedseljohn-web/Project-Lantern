FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo_mysql
RUN echo "upload_max_filesize=2G" >> /usr/local/etc/php/conf.d/uploads.ini
RUN echo "post_max_size=2G" >> /usr/local/etc/php/conf.d/uploads.ini
RUN echo "memory_limit=1G" >> /usr/local/etc/php/conf.d/uploads.ini
RUN echo "max_execution_time=600" >> /usr/local/etc/php/conf.d/uploads.ini
RUN echo "max_input_time=600" >> /usr/local/etc/php/conf.d/uploads.ini