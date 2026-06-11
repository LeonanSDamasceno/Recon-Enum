1. Pré-requisitos (se ainda não tiver)





Docker Desktop instalado e rodando com WSL2 habilitado.





Baixe do site oficial: https://docs.docker.com/docker-for-windows/install/



Durante a instalação, marque a opção de usar WSL2.



Após instalar, reinicie e abra o Docker Desktop; espere o ícone na bandeja ficar verde.



Windows Terminal ou PowerShell como administrador (para facilitar).



Um editor de texto (VS Code, Notepad++, até o Bloco de Notas serve).



2. Criar a pasta do projeto e os arquivos do ambiente vulnerável

Abra o terminal e crie a pasta do laboratório, por exemplo,C:\Users\SEUUSUARIO\recon-lab.
(Dentro do PowerShell, você pode usarmkdir recon-lab ecd recon-lab.)

Agora, crie os seguintes arquivos exatamente como descritos:

2.1docker-compose.yml

version: "3.8"
services:
  web:
    build: ./apache-vuln
    ports:
      - "8080:80"
    container_name: web-vulneravel
    networks:
      - rede-lab

  database:
    image: mysql:5.7
    container_name: db-vulneravel
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: loja
    ports:
      - "3306:3306"
    command: --default-authentication-plugin=mysql_native_password
    networks:
      - rede-lab

networks:
  rede-lab:
    driver: bridge

2.2 Pastaapache-vuln e seu conteúdo

Crie uma subpasta chamadaapache-vuln e dentro dela os dois arquivos:

apache-vuln/Dockerfile

FROM php:7.4-apache

# Ativa módulos de info e status (exposição indesejada)
RUN a2enmod info status
RUN echo "<Location /server-info>\n  SetHandler server-info\n</Location>" > /etc/apache2/conf-available/server-info.conf \
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

apache-vuln/index.php

<h1>Em Manutenção</h1>
<?php phpinfo(); ?>

2.3 Verifique a estrutura

Sua pastarecon-lab deve ficar assim:

recon-lab/
├── docker-compose.yml
└── apache-vuln/
    ├── Dockerfile
    └── index.php



3. Subir o ambiente vulnerável

No terminal, dentro da pastarecon-lab, execute:

docker compose up -d --build

(Se seu Docker for antigo, o comando pode serdocker-compose up -d --build; ambos funcionam.)

Aguarde a construção e inicialização. Para conferir:

docker ps

Você verá os containersweb-vulneravel edb-vulneravel rodando.



4. Preparar o container Kali para os ataques

Precisamos de um container Kali com acesso à mesma rede do laboratório. Primeiro, descubra o nome da rede criada:

docker network ls

Provavelmente aparecerá algo comorecon-lab_rede-lab (prefixo do projeto + nome da rede). Anote esse nome.

Agora execute um container Kali interativo, instalando as ferramentas necessárias:

docker run -it --network=recon-lab_rede-lab kalilinux/kali-rolling bash

Dentro do container Kali, atualize e instale as ferramentas:

apt update
apt install -y nmap netcat-openbsd whatweb dirb

(Isso pode levar alguns minutos; aproveite para tomar um café.)



5. Demonstração do ataque (reconhecimento e enumeração)

Agora, ainda dentro do Kali container, faça as varreduras contra os servidores do laboratório.
Acessaremos os serviços usando os nomes dos containers (resolução DNS funciona na rede Docker) ou os IPs. Vamos usar os nomes, que são mais didáticos.

5.1 Varredura de portas e detecção de versões (nmap)

nmap -sV -sC -O -p 80,3306 web-vulneravel

(Saída típica: Apache httpd 2.4.x com página “Em Manutenção”, MySQL 5.7.x, banner da versão.)

5.2 Banner grabbing no Apache

echo -e "HEAD / HTTP/1.0\r\n" | nc web-vulneravel 80

Você verá o cabeçalhoServer: Apache/2.4.x (Unix) ..., mostrando a versão exata.

5.3 Páginas expostas (acessíveis via linha de comando ou script)

Mostre que/server-status e/server-info estão disponíveis:

curl http://web-vulneravel/server-status   # status do Apache
curl http://web-vulneravel/server-info     # configuração do servidor
curl http://web-vulneravel/                # página com phpinfo()

5.4 Enumeração do MySQL

Tente um banner direto com netcat:

nc db-vulneravel 3306

(Você verá a string de versão do MySQL.)

E tente conectar com as credenciais padrão:

apt install -y mysql-client   # se ainda não tiver
mysql -h db-vulneravel -u root -proot -e "SHOW DATABASES;"

(Isso prova que a senha fracaroot concede acesso total.)

5.5 Enumeração de diretórios com dirb (opcional)

dirb http://web-vulneravel/

(Listará/server-status,/server-info, etc.)

Dica: capture a saída de cada comando (pode copiar do terminal ou tirar prints) para os slides.

Saia do container Kali (digiteexit), mas não pare o ambiente vulnerável ainda.



6. Correção – ambiente seguro (hardening)

Agora vamos criar os arquivos do ambiente corrigido, sem expor informações sensíveis.

6.1 Crie uma nova pastaapache-seguro dentro derecon-lab

Com esta estrutura:

recon-lab/
└── apache-seguro/
    ├── Dockerfile
    └── index.php

apache-seguro/Dockerfile

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

<h1>Bem-vindo à Loja</h1>
<p>Página inicial segura, sem informações sensíveis.</p>

6.2 Novodocker-compose-seguro.yml

Na raiz derecon-lab:

version: "3.8"
services:
  web:
    build: ./apache-seguro
    ports:
      - "8080:80"
    container_name: web-seguro
    networks:
      - rede-lab-seguro

  database:
    image: mysql:5.7
    container_name: db-seguro
    environment:
      MYSQL_ROOT_PASSWORD: SenhaSegura@123!
      MYSQL_DATABASE: loja
    # ❗ Não expõe porta no host (acesso apenas interno)
    # ports:
    #   - "3306:3306"
    command: --default-authentication-plugin=mysql_native_password
    networks:
      - rede-lab-seguro

networks:
  rede-lab-seguro:
    driver: bridge

Observe que a porta 3306 não é mais mapeada para o host; apenas o containerweb-seguro pode acessar o banco internamente.

6.3 Parar o ambiente vulnerável e subir o seguro

docker compose down
docker compose -f docker-compose-seguro.yml up -d --build

Verifique:

docker ps

Agora aparecerãoweb-seguro edb-seguro, e apenas a porta 8080 estará visível no host.



7. Demonstração da correção (ataque falhando)

Rode novamente um container Kali na nova rede (note que o nome da rede agora serárecon-lab_rede-lab-seguro). Confira comdocker network ls e inicie:

docker run -it --network=recon-lab_rede-lab-seguro kalilinux/kali-rolling bash

Instale as ferramentas de novo (ou aproveite se fez cache de imagem, mas o contêiner é novo). Melhor instalar de novo:

apt update && apt install -y nmap netcat-openbsd

Dentro do Kali, execute os mesmos testes:





Varredura nmap:

  nmap -sV -sC -O -p 80,3306 web-seguro

Resultado: porta 80 aparece comohttp sem detalhes da versão do Apache (apenasApache ouApache httpd), e a porta 3306 nem aparece porque não está exposta no mesmo segmento (está filtrada ou não existe).





Banner no Apache:

  echo -e "HEAD / HTTP/1.0\r\n" | nc web-seguro 80

OServer dirá apenasApache (sem versão e sem módulos).





Páginas restritas:

  curl -I http://web-seguro/server-status

Retorna 404 Not Found ou 403 Forbidden.





Tentativa de MySQL:

  nc db-seguro 3306

Conexão recusada (ou nem tenta, já que o nomedb-seguro resolve mas a porta não está mapeada fora do container, e o Kali está na mesma rede, consegue alcançar mas a política de acesso? Na verdade, o containerdb-seguro está na rede interna e o Kali consegue alcançá-lo pelo nome, mas a senha foi alterada. Se você tentarnc db-seguro 3306, ele até conecta na porta do MySQL, então você ainda veria o banner do MySQL. Para demonstrar a mitigação completa, podemos bloquear também o MySQL com firewall no container ou simplesmente mostrar que a senha não é maisroot. Vamos adicionar um firewall no container db-seguro para rejeitar conexões externas. Para simplificar, você pode modificar odocker-compose-seguro.yml do banco para adicionar umcap_add e um comando de entrada que usa iptables. Ou, mais simples: mostre que o scanner externo (de fora do Docker) não vê a porta 3306 de forma alguma, porque não foi publicada. Se você fizernmap localhost do Windows (com nmap instalado no Windows), verá que a 3306 está filtered. Para manter a demonstração dentro do Kali, podemos usar o IP do host (gateway) e ver que a porta não está acessível. Mas é mais fácil: pare o container Kali anterior, e instale o nmap no Windows (https://nmap.org/download.html#windows) e faça a varredura local:

  nmap -sV -p 8080,3306 localhost

Você verá apenas a 8080 como http, sem detalhes, e a 3306 comofiltered. Essa abordagem é simples e visualmente forte.

Adapte: use o nmap do Windows para o último teste. Instalar o nmap no Windows é rápido.



8. Resumo da demonstração





Antes: serviços expostos com versões, páginas de debug abertas, credenciais padrão, banco acessível.



Depois: serviços ocultos, sem banners detalhados, páginas de debug bloqueadas, MySQL inacessível externamente e com senha forte.

Você pode gravar as telas ou fazer ao vivo; a diferença é gritante.
