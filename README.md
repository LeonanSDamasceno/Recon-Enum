1. Estrutura do projeto e arquivos

Criamos a pastarecon-lab com dois componentes: um servidor web (Apache + PHP) e um banco de dados (MySQL), ambos configurados de forma insegura. Tudo é orquestrado pelo Docker Compose.

docker-compose.yml (ambiente vulnerável)

version: "3.8"
services:
  web:
    build: ./apache-vuln      # Constrói a imagem a partir do Dockerfile na subpasta
    ports:
      - "8080:80"             # Mapeia porta 8080 do host para 80 do container
    container_name: web-vulneravel
    networks:
      - rede-lab

  database:
    image: mysql:5.7
    container_name: db-vulneravel
    environment:
      MYSQL_ROOT_PASSWORD: root   # Senha fraca (padrão "root")
      MYSQL_DATABASE: loja
    ports:
      - "3306:3306"               # Expõe a porta do MySQL no host
    command: --default-authentication-plugin=mysql_native_password
    networks:
      - rede-lab

networks:
  rede-lab:
    driver: bridge

Explicações:
-build: ./apache-vuln: diz ao Docker para montar uma imagem customizada usando o Dockerfile que está na pastaapache-vuln. Isso nos permite adicionar configurações inseguras.
-ports: "8080:80": o servidor web dentro do container escuta na porta 80, mas acessamos do Windows vialocalhost:8080.
-MYSQL_ROOT_PASSWORD: root: define a senha do usuário administrador do MySQL comoroot – uma credencial fraca e previsível.
-ports: "3306:3306": publica a porta do MySQL, tornando-o acessível a qualquer um que alcance o host.
-command: --default-authentication-plugin=mysql_native_password: força o MySQL 5.7 a usar o plugin de autenticação nativo (SHA-1), garantindo compatibilidade com clientes mais antigos.
-networks: cria uma rede bridge isolada para os containers se comunicarem diretamente pelos nomes (web-vulneravel,db-vulneravel).

apache-vuln/Dockerfile

FROM php:7.4-apache

# Habilita server-info e server-status (módulos de monitoração)
RUN a2enmod info status
RUN echo "<Location /server-info>\n  SetHandler server-info\n</Location>" > /etc/apache2/conf-available/server-info.conf \
    && echo "<Location /server-status>\n  SetHandler server-status\n</Location>" > /etc/apache2/conf-available/server-status.conf \
    && a2enconf server-info server-status

# Exibe versão completa do Apache nos cabeçalhos e assinatura
RUN echo "ServerTokens Full" >> /etc/apache2/conf-available/security.conf \
    && echo "ServerSignature On" >> /etc/apache2/conf-available/security.conf \
    && a2enconf security

# Permite listagem de diretórios (Indexes)
RUN echo "<Directory /var/www/html>\n  Options Indexes FollowSymLinks\n</Directory>" >> /etc/apache2/conf-available/directory.conf \
    && a2enconf directory

# Página inicial vazada (phpinfo)
COPY index.php /var/www/html/

Explicações:
-a2enmod info status: ativa os módulosserver-info eserver-status, que exibem informações detalhadas do servidor e conexões ativas.
-ServerTokens Full +ServerSignature On: faz com que o Apache envie nos cabeçalhos HTTP a versão exata e detalhes do sistema operacional (ex.:Apache/2.4.41 (Unix)).
-Options Indexes: se não houver um arquivo de índice (comoindex.html), o Apache lista os arquivos do diretório – vazamento de estrutura.





A páginaindex.php contémphpinfo(); – comando que exibe TODAS as configurações do PHP, variáveis de ambiente, módulos, etc. Isso é um desastre de segurança se acessível.



2. Comandos Docker e subida do ambiente vulnerável

docker compose up -d --build

-up: sobe todos os serviços definidos nodocker-compose.yml.
--d: "detached mode" – executa em segundo plano, liberando o terminal.
---build: força a reconstrução das imagens (necessário após alterar Dockerfile).

Na primeira execução, o Docker baixa as imagens base, executa as instruções do Dockerfile e inicia os containers. Após isso,docker ps lista:

CONTAINER ID   IMAGE                  ...   NAMES
abc123         recon-lab-web          ...   web-vulneravel
def456         mysql:5.7              ...   db-vulneravel

A rederecon-lab_rede-lab é criada automaticamente (nome prefixado com o diretório do projeto). Isso permite que os containers se comuniquem usando os nomes de serviço (web-vulneravel,db-vulneravel).



3. Container Kali e instalação de ferramentas

Para simular um atacante, usamos um container Kali Linux na mesma rede do laboratório. Não é necessário instalar uma VM completa.

docker run -it --network=recon-lab_rede-lab kalilinux/kali-rolling bash

--it: modo interativo com terminal.
---network=recon-lab_rede-lab: conecta o container à rede onde estão os alvos (o nome exato você obtém comdocker network ls).
-kalilinux/kali-rolling: imagem oficial do Kali Linux (sem ferramentas gráficas).

Dentro desse container, somos root. Atualizamos e instalamos as ferramentas:

apt update
apt install -y nmap netcat-openbsd whatweb dirb mariadb-client

-nmap: scanner de rede e portas.
-netcat-openbsd: utilitário para conexões TCP/UDP simples (usado para banner grabbing).
-whatweb: identifica tecnologias usadas em sites.
-dirb: força bruta de diretórios (enumeração de estrutura).
-mariadb-client: cliente de banco de dados compatível com MySQL (comandomysql).



4. Demonstração do ataque: reconhecimento e enumeração

Agora, do Kali, atacamos os serviços.

4.1nmap

nmap -sV -sC -O -p 80,3306 web-vulneravel

Explicação:
-nmap: ferramenta de exploração e auditoria de rede.
--sV: detecta versões dos serviços (service version).
--sC: roda scripts padrão de enumeração (NSE).
--O: tenta identificar o sistema operacional.
--p 80,3306: verifica apenas as portas 80 (web) e 3306 (MySQL).
-web-vulneravel: nome do container alvo (resolvido pelo DNS interno do Docker).

Saída esperada:

PORT     STATE SERVICE  VERSION
80/tcp   open  http     Apache httpd 2.4.x ((Unix))
|_http-title: Em Manutenção
| http-server-header: Apache/2.4.x (Unix)
3306/tcp open  mysql    MySQL 5.7.x
| mysql-info: ...

Isso mostra as versões exatas do Apache e MySQL, além de detalhes como o título da página. Essas informações seriam usadas pelo atacante para buscar exploits específicos.

4.2 Banner grabbing com netcat

echo -e "HEAD / HTTP/1.0\r\n" | nc web-vulneravel 80

-echo -e "HEAD / HTTP/1.0\r\n": envia uma requisição HEAD (apenas cabeçalhos) pelo pipe para onc.
-nc web-vulneravel 80: abre uma conexão TCP na porta 80 do alvo. Tudo que chega pelostdin é enviado e a resposta é impressa no terminal.

Saída:

HTTP/1.1 200 OK
Date: ...
Server: Apache/2.4.41 (Unix)
...

O cabeçalhoServer revela a versão exata do Apache. Em um cenário real, isso facilita a busca por vulnerabilidades conhecidas.

4.3 Páginas expostas comcurl

curl http://web-vulneravel/server-status
curl http://web-vulneravel/server-info
curl http://web-vulneravel/

-curl: ferramenta de linha de comando para transferir dados via URL.





Acessamos diretamente as URLs que deveriam ser protegidas.

Resultados:
-/server-status: mostra o status atual do servidor (IPs, requisições recentes, etc.).
-/server-info: exibe toda a configuração do servidor (módulos, parâmetros).
-/: a página comphpinfo() nos dá informações críticas: versão do PHP, módulos carregados, variáveis de ambiente, caminhos do sistema, etc.

Tudo isso é informação que um atacante adora para planejar ataques posteriores.

4.4 Enumeração do MySQL

Banner grabbing comnc

nc db-vulneravel 3306

Assim que a conexão é estabelecida, o servidor MySQL envia uma string com a versão (ex.:5.7.39-log). Basta pressionar Ctrl+C para encerrar.

Conexão com credenciais padrão

Tentamos o comando:

mysql -h db-vulneravel -u root -proot --ssl-mode=DISABLED -e "SHOW DATABASES;"

Explicação detalhada:
-mysql: cliente de linha de comando para interagir com o MySQL.
--h db-vulneravel: host onde o MySQL está rodando (nome do container).
--u root: usuário root.
--proot: a opção-p seguida da senharoot (sem espaço entre-p e a senha). Isso é uma credencial extremamente fraca.
---ssl-mode=DISABLED: desabilita a tentativa de conexão com SSL, porque o servidor usa um certificado autoassinado que o cliente não confia. Em um ataque real, o atacante também poderia desabilitar a verificação SSL para prosseguir com a enumeração.
--e "SHOW DATABASES;": executa o comando SQL e encerra, em vez de abrir um shell interativo.

Erro de SSL:
O erroError 2026 (HY000): TLS/SSL error: Certificate verification failure ocorreu porque o cliente tentou negociar SSL e o certificado do servidor não é confiável. Ao usar--ssl-mode=DISABLED contornamos isso, mas note que isso também é uma configuração fraca que permite tráfego não criptografado.

Saída esperada (após corrigir o SSL):

+--------------------+
| Database           |
+--------------------+
| information_schema |
| loja               |
| mysql              |
| performance_schema |
| sys                |
+--------------------+

Isso prova que conseguimos conectar como administrador do banco de dados e listar as bases. A partir daí, poderíamos extrair tabelas, dados, etc.



5. Correção / hardening

Após demonstrar o ataque, partimos para a correção. Paramos o ambiente vulnerável:

docker compose down

Criamos novos arquivos para a versão segura.

docker-compose-seguro.yml

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
      MYSQL_ROOT_PASSWORD: SenhaSegura@123!   # senha forte e diferente
      MYSQL_DATABASE: loja
    # ports:   <-- removido! não expõe a porta 3306 no host
    command: --default-authentication-plugin=mysql_native_password
    networks:
      - rede-lab-seguro

networks:
  rede-lab-seguro:
    driver: bridge

Mudanças:
-MYSQL_ROOT_PASSWORD: agora uma senha forte e não óbvia.





Removemosports: 3306:3306 do serviçodatabase. Isso significa que o container MySQL só é acessível dentro da rederede-lab-seguro, não mais externamente. Mesmo que alguém no host tente escanear a porta 3306, ela não estará mapeada.



A rede é isolada por padrão.

apache-seguro/Dockerfile

FROM php:7.4-apache

# Desabilita server-info e server-status
RUN a2dismod info status
RUN rm -f /etc/apache2/conf-enabled/server-info.conf /etc/apache2/conf-enabled/server-status.conf

# Banner restrito
RUN echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf \
    && echo "ServerSignature Off" >> /etc/apache2/conf-available/security.conf \
    && a2enconf security

# Remove listagem de diretório
RUN echo "<Directory /var/www/html>\n  Options -Indexes\n</Directory>" >> /etc/apache2/conf-available/directory.conf \
    && a2enconf directory

# Página normal, sem phpinfo
COPY index.php /var/www/html/

Mudanças:
-a2dismod info status+ remoção dos arquivos de config: ninguém mais acessa/server-status ou/server-info.
-ServerTokens Prod: exibe apenas "Apache" no cabeçalho, sem versão.
-ServerSignature Off: remove a assinatura do rodapé das páginas de erro.
-Options -Indexes: desabilita listagem de diretórios.
-index.php agora contém apenas HTML simples, sem vazamento de informações.

apache-seguro/index.php:

<h1>Bem-vindo à Loja</h1>
<p>Página inicial segura, sem informações sensíveis.</p>

Subimos o ambiente seguro:

docker compose -f docker-compose-seguro.yml up -d --build



6. Verificação da correção

Pelo container Kali (na nova rede)

Iniciamos outro container Kali conectado àrede-lab-seguro:

docker run -it --network=recon-lab_rede-lab-seguro kalilinux/kali-rolling bash

(Instalamos as ferramentas novamente, como antes.)

nmap

nmap -sV -sC -O -p 80,3306 web-seguro

Resultado:





Porta 80: aberta, mas serviço identificado comohttp genérico (ouApache httpd sem versão detalhada). Sem título suspeito.



Porta 3306: fechada ou filtrada, porque o MySQL não está publicando porta. O scanner não conseguirá detectar o serviço.

Banner Apache

echo -e "HEAD / HTTP/1.0\r\n" | nc web-seguro 80

Resposta:Server: Apache (sem versão).

Páginas restritas

curl -I http://web-seguro/server-status

Retorna404 Not Found (ou403 Forbidden), pois o módulo foi desabilitado.

MySQL inacessível

Se tentarmosnc db-seguro 3306, recebemos "Connection refused" (o container está rodando, mas não está mapeando a porta para o host, e o Kali está na mesma rede mas o MySQL dentro do container só escuta no IP interno, mas o nomedb-seguro resolve… Na verdade, como estão na mesma rede bridge, o Kali consegue alcançar o containerdb-seguro na porta 3306 diretamente, pois a porta não está mapeada para o host, mas o container está sim ouvindo na rede interna. Portanto, dentro da rede, onc db-seguro 3306 ainda pode funcionar e mostrar o banner, a menos que restrinjamos com firewall interno. Para a demonstração didática, podemos simplesmente mostrar que onmap não encontra a porta 3306 aberta se executarmos o scanner do host (Windows) onde apenas a 8080 está mapeada.)

Para uma verificação mais impactante, instale onmap no Windows (ou use o PowerShell) e execute:

nmap -sV -p 8080,3306 localhost

Saída:

PORT     STATE  SERVICE    VERSION
8080/tcp open   http       Apache httpd (sem detalhes)
3306/tcp closed mysql

Isso comprova que a porta do banco não está mais visível externamente.



7. Resumo dos conceitos de segurança





Reconhecimento e Enumeração: fase inicial de um ataque, onde o atacante coleta informações sobre alvos (portas, serviços, versões, usuários). Essas informações são usadas para identificar vulnerabilidades específicas.



Configurações inseguras:





Banners detalhados (ServerTokens Full)



Páginas de debug ou status (/server-info,/server-status,phpinfo())



Credenciais padrão ou fracas (root/root)



Mapeamento desnecessário de portas



Falta de criptografia ou SSL mal configurado



Mitigação:





Minimização de informações expostas (ServerTokens Prod, remover assinaturas)



Desabilitar funcionalidades desnecessárias (módulos de status)



Remover arquivos de debug



Política de senhas fortes



Segmentação de rede: apenas serviços estritamente necessários devem ser publicados



Hardening contínuo
