FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo_mysql
RUN printf '%s\n' 'upload_max_filesize=2G' 'post_max_size=2100M' 'memory_limit=1G' 'max_execution_time=600' 'max_input_time=600' > /usr/local/etc/php/conf.d/uploads.ini
# Deny interpreter-executable files in the user-uploaded folder.
RUN printf '%s\n' '<Directory "/var/www/html/uploads">' '  <FilesMatch "(?i)\\.(php[0-9]?|phtml|phar|cgi|pl|py|sh)$">' '    Require all denied' '  </FilesMatch>' '</Directory>' > /etc/apache2/conf-available/lantern-uploads.conf && a2enconf lantern-uploads
