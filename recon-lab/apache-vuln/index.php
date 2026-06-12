<h1>Em Manutenção</h1>
<?php phpinfo(); ?>

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

apache-seguro/index.php
