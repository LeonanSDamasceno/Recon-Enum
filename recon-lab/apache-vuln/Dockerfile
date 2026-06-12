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

FROM php:7.4-apache

# Desabilita módulos de info e status
RUN a2dismod info status
RUN rm -f /etc/apache2/conf-enabled/server-info.conf /etc/apache2/conf-enabled/server-status.conf

# Banner restrito (mínimo de informação)
RUN echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf \
    && echo "ServerSignature Off" >> /etc/apache2/conf-available/security.conf \
    && a2enconf security

# Remove listagem de diretórios
RUN echo "<Directory /var/www/html>\n  Options -Indexes\n</Directory>" >> /etc/apache2/conf-available/directory.conf \
    && a2enconf directory

# Página normal, sem debug
COPY index.php /var/www/html/
