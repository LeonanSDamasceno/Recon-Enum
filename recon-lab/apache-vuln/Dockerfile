
Ativa módulos de info e status (exposição indesejada)
RUN a2enmod info status RUN echo "<Location /server-info>\n SetHandler server-info\n" > /etc/apache2/conf-available/server-info.conf
&& echo "<Location /server-status>\n SetHandler server-status\n" > /etc/apache2/conf-available/server-status.conf
&& a2enconf server-info server-status

Banner completo com versão
RUN echo "ServerTokens Full" >> /etc/apache2/conf-available/security.conf
&& echo "ServerSignature On" >> /etc/apache2/conf-available/security.conf
&& a2enconf security

Listagem de diretórios habilitada
RUN echo "<Directory /var/www/html>\n Options Indexes FollowSymLinks\n" >> /etc/apache2/conf-available/directory.conf
&& a2enconf directory

Cópia da página com phpinfo() (debug esquecido)
COPY index.php /var/www/html/

apache-vuln/index.php
