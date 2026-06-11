FROM php:7.4-apache

# Ativa módulos de info e status (exposição indesejada)
RUN a2enmod info status \
    && echo "<Location /server-info>\n  SetHandler server-info\n</Location>" > /etc/apache2/conf-available/server-info.conf \
    && echo "<Location /server-status>\n  SetHandler server-status\n</Location>" > /etc/apache2/conf-available/server-status.conf \
    && a2enconf server-info server-status

# Banner completo com versão
RUN echo "ServerTokens Full" >> /etc/apache2/conf-available/security.conf \
    && echo "ServerSignature On" >> /etc/apache2/conf-available/security.conf \
    && a2enconf security

# Listagem de diretórios habilitada
RUN echo "<Directory /var/www/html>\n  Options Indexes FollowSymLinks\n</Directory>" >> /etc/apache2/conf-available/directory.conf \
    && a2enconf directory

# Cópia da página com phpinfo() (debug esquecido)
COPY index.php /var/www/html/
