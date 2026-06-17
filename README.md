#  Reconhecimento e Enumeração

Este repositório contém o ambiente prático desenvolvido para demonstrar **falhas de configuração** que permitem a um atacante realizar reconhecimento e enumeração de serviços e, em seguida, aplicar as correções necessárias (hardening). 

Ao decorrer da apresentação serão mostrados:
- Construção de ambiente vulnerável
- Demonstração do ataque
- Correção/mitigação
- Apresentação ao vivo


## O que é Reconhecimento e Enumeração?

No contexto de segurança em sistemas de informação , o **reconhecimento (reconnaissance)** é a primeira fase de um ataque. O atacante busca coletar o máximo de informações sobre o alvo, sem necessariamente invadir nada.

- **Passivo:** sem interagir diretamente com o alvo (ex.: pesquisas em redes sociais, consultas DNS, Shodan).
- **Ativo:** interagindo com os sistemas (ex.: escaneamento de portas, requisições HTTP).

A **enumeração** é o aprofundamento do reconhecimento ativo. Nela o atacante extrai detalhes específicos:
- Quais serviços e versões estão rodando?
- Que páginas ou diretórios ocultos existem?
- Quais usuários ou credenciais padrão estão presentes?
- Há informações internas expostas?

Essas informações permitem planejar ataques direcionados, explorar vulnerabilidades conhecidas e escalar privilégios. Estão mapeadas em frameworks como **MITRE ATT&CK (Discovery)** e na fase de recon da **Cyber Kill Chain**.

## Como o laboratório reproduz isso?

Criamos um ambiente propositadamente mal configurado (OWASP A05 – Security Misconfiguration) com Apache e MySQL expondo banners detalhados, páginas de debug/status e credenciais padrão. Em seguida, usamos ferramentas de ataque para:

1. Varrer portas e serviços (`nmap`)
2. Capturar banners (`netcat`)
3. Acessar páginas expostas (`curl`)
4. Conectar ao banco com senha fraca (`mysql`)

Depois, aplicamos correções e repetimos os testes para mostrar que o ataque falha.

---

## Estrutura do ambiente

recon-lab/

├── docker-compose.yml # Ambiente vulnerável

├── docker-compose-seguro.yml # Ambiente corrigido

├── apache-vuln/ # Dockerfile + index.php vulnerável

└── apache-seguro/ # Dockerfile + index.php seguro

Utiliza **Docker Compose** com três contêineres na mesma rede bridge:
- `web-vulneravel` (Apache + PHP)
- `db-vulneravel` (MySQL 5.7)
- `kali` (Kali Linux para ataques)

---

## 🔥 Fase 1 – Ambiente vulnerável

### Arquivos principais

#### `docker-compose.yml` (inseguro)

yaml
services:
web:
build: ./apache-vuln
ports: ["8080:80"]
database:
image: mysql:5.7
environment:
MYSQL_ROOT_PASSWORD: root # Senha fraca
ports: ["3306:3306"] # Porta exposta

####`apache-vuln/Dockerfile`
-`ServerTokens Full` → versão completa no cabeçalho
- Módulos`server-info` e`server-status` ativados
-`phpinfo()` exposto na raiz
- Listagem de diretórios habilitada

### Subir o ambiente

bash
docker compose up -d --build

---

## 🚀 Fase 2 – Ataque (reconhecimento e enumeração)

A partir de um contêiner Kali conectado à mesma rede, executamos os seguintes comandos.

> Todos os comandos são executados dentro do Kali (`docker run -it --network=recon-lab_rede-lab kalilinux/kali-rolling bash`).

### 2.1 Varredura de portas e serviços –`nmap`

bash
nmap -sV -sC -O -p 80,3306 web-vulneravel

-`-sV`: detecta versão dos serviços
-`-sC`: scripts padrão de enumeração
-`-O`: tenta identificar SO

**Resultado:** exibe versão exata do Apache e MySQL, título da página, etc.

### 2.2 Banner grabbing –`netcat`

bash
echo -e "HEAD / HTTP/1.0\r\n" | nc web-vulneravel 80

Envia uma requisição HEAD e imprime a resposta, revelando o cabeçalho`Server` com a versão completa.

### 2.3 Acesso a páginas expostas –`curl`

bash
curl http://web-vulneravel/server-status # status do Apache
curl http://web-vulneravel/server-info # configuração do servidor
curl http://web-vulneravel/ # phpinfo() exposto

Essas páginas fornecem informações internas críticas.

### 2.4 Enumeração do MySQL –`netcat` +`mysql`
Banner do MySQL:

bash
nc db-vulneravel 3306 # mostra versão na saudação do banco

Conexão com credencial padrão:

bash
mysql -h db-vulneravel -u root -proot --ssl=0 -e "SHOW DATABASES;"

-`-u root -proot`: usuário e senha fracos
-`--ssl=0`: desabilita SSL (necessário pois o certificado é autoassinado)
-`-e "SHOW DATABASES;"`: lista as bases de dados

**Resultado:** o atacante tem acesso total ao banco, podendo extrair qualquer dado.

---

## 🛡️ Fase 3 – Correção (hardening)

Paramos o ambiente vulnerável e subimos a versão segura.

### Mudanças no`docker-compose-seguro.yml`
- Senha forte no MySQL (`SenhaSegura@123!`)
- **Porta 3306 não é mais mapeada** para o host (apenas rede interna)

### Mudanças no`apache-seguro/Dockerfile`
-`ServerTokens Prod` → exibe apenas “Apache”
- Módulos`info` e`status` desabilitados e removidos
- Página inicial sem`phpinfo()`
- Listagem de diretórios desabilitada (`-Indexes`)

### Subir ambiente seguro

bash
docker compose down
docker compose -f docker-compose-seguro.yml up -d --build

---

## ✅ Fase 4 – Verificação da correção

Repetimos os mesmos comandos de ataque, agora contra`web-seguro` e`db-seguro` (na nova rede).

### Novo`nmap`

bash
nmap -sV -sC -O -p 80,3306 web-seguro

<img width="942" height="603" alt="image" src="https://github.com/user-attachments/assets/0e132648-35ec-4d26-ac6a-5b2d53b79ff5" />

- Porta 80: serviço identificado apenas como`http`, sem detalhes de versão
- Porta 3306: fechada ou filtrada (não está mais exposta)

### Banner grabbing

bash
echo -e "HEAD / HTTP/1.0\r\n" | nc web-seguro 80

<img width="501" height="160" alt="image" src="https://github.com/user-attachments/assets/a1c42147-8fec-4e2f-9470-c7b1be9f3aa8" />


- Cabeçalho`Server: Apache` (sem versão ou SO)

### Páginas restritas

bash
curl -I http://web-seguro/server-status # 404 Not Found
curl -I http://web-seguro/ # HTML simples, sem phpinfo

<img width="440" height="133" alt="image" src="https://github.com/user-attachments/assets/16ab5c7a-41b3-41fd-8130-f173e7d56e97" />


### MySQL inacessível
-`nc db-seguro 3306` → conexão recusada (porta fechada externamente)
-`mysql -h db-seguro -u root -proot ...` → falha na conexão

<img width="447" height="106" alt="image" src="https://github.com/user-attachments/assets/cf588360-58b2-459e-b8b9-a10f3c9e2aaf" />

<img width="879" height="240" alt="image" src="https://github.com/user-attachments/assets/90c81fde-fdc9-4396-b8f3-64f161b9e427" />


**Conclusão:** As mesmas técnicas que antes forneciam um mapa completo do ambiente agora não retornam informações útil. A superfície de ataque foi drasticamente reduzida.

---

## 🧠 Conceitos aplicados

- **Reconhecimento ativo**: interagimos diretamente com os serviços para obter informações.
- **Enumeração**: extraímos versões, páginas ocultas, credenciais.
- **Falha de configuração (A05:2021)**: banners detalhados, páginas de debug, senhas padrão.
- **Hardening**: princípios de defesa em profundidade, mínima exposição de informação, desabilitação de funcionalidades desnecessárias.

## 📄 Referências
- OWASP Top 10 – A05 Security Misconfiguration
- MITRE ATT&CK – Tactic: Discovery
- CIS Benchmarks
- NIST Special Publication 800-123 (Guide to General Server Security)

---





