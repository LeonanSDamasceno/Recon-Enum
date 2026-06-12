# Laboratório de Reconhecimento e Enumeração (Tema 12)

Este repositório contém um ambiente controlado para demonstrar a fase de **reconhecimento e enumeração** em testes de intrusão, bem como as medidas de hardening que impedem a coleta indevida de informações. O laboratório utiliza Docker para simular um servidor web e um banco de dados propositadamente mal configurados, permitindo ataques reais e posterior correção.

## 📚 Conceitos: Reconhecimento e Enumeração

No contexto de segurança ofensiva, o **reconhecimento (reconnaissance)** é a primeira fase de um ataque. O atacante busca coletar o máximo de informações sobre o alvo, sem necessariamente invadir nada. Pode ser:

- **Passivo:** sem interagir diretamente com o alvo (ex.: pesquisar em redes sociais, consultar registros DNS, Shodan).
- **Ativo:** interagindo com os sistemas (ex.: escaneamento de portas, envio de requisições HTTP).

A **enumeração** é um aprofundamento do reconhecimento ativo. Aqui o atacante extrai detalhes específicos:

- Quais serviços e versões estão rodando?
- Que páginas ou diretórios ocultos existem?
- Quais usuários ou contas são válidos?
- Há credenciais padrão ou configurações expostas?

Essas informações permitem ao atacante:

- Identificar vulnerabilidades conhecidas (ex.: exploits para Apache 2.4.41).
- Planejar ataques direcionados (ex.: força bruta, SQL injection).
- Escalar privilégios ou se mover lateralmente na rede.

Este laboratório demonstra exatamente essas etapas: a partir de serviços mal configurados, revelamos banners, páginas de debug e credenciais padrão. Depois aplicamos correções que quebram a capacidade de enumeração, reduzindo drasticamente a superfície de ataque.

## 🧱 Arquitetura do Ambiente

Utilizamos três containers Docker:

1. **web-vulneravel (Apache + PHP):** servidor com banners detalhados, módulos `server-info`/`server-status` ativos e página `phpinfo()` exposta.
2. **db-vulneravel (MySQL 5.7):** banco com senha `root:root` e porta 3306 publicada no host.
3. **Kali Linux (atacante):** container temporário com ferramentas como `nmap`, `netcat`, `curl` e `mysql`.

Todos os containers compartilham uma rede bridge interna. No final, substituímos os serviços vulneráveis por versões corrigidas (`web-seguro` e `db-seguro`).

## 💻 Pré-requisitos

- **Docker Desktop** (Windows, macOS ou Linux) com WSL2 ativado (no Windows).
- **PowerShell** ou terminal equivalente.
- Editor de texto (VS Code, Notepad++, etc.).

## 📁 Estrutura do Projeto
