# VEZZ – Plataforma Inteligente de Gestão de Atendimentos e Filas

Sistema completo para clínicas e consultórios gerenciarem filas de atendimento e agendamentos de pacientes.

## Tecnologias
- PHP 7.4+ (PDO, Sessions)
- MySQL

````markdown
# VEZZ – Plataforma Inteligente de Gestão de Atendimentos e Filas

Sistema completo para clínicas e consultórios gerenciarem filas de atendimento e agendamentos de pacientes.

## Tecnologias
- PHP 7.4+ (PDO, Sessions)
- MySQL
- Bootstrap 5
- JavaScript Vanilla + AJAX

## Estrutura de Pastas
```
/vezz
├── index.php
├── login.php
├── logout.php
├── cadastro.php
├── pesquisar_clinicas.php
├── clinica_detalhes.php
├── agendamento.php
├── dashboard_paciente.php
├── dashboard_gestor.php
├── controle_fila.php
├── acompanhamento_fila.php
├── recuperar_senha.php
├── ajax_fila_gestor.php
├── ajax_fila_paciente.php
├── assets/
│   ├── css/style.css
│   └── js/script.js
├── includes/
│   ├── config.php
│   ├── auth.php
│   ├── functions.php
│   ├── header.php
    └── footer.php
└── sql/
    └── "SQL script VEZZ.sql" (arquivo principal de criação do banco)
```

## Instalação (XAMPP / WAMP / similar)

1. **Copiar arquivos**
    - Copie a pasta `vezz` inteira para dentro de `C:\xampp\htdocs\` (Windows) ou `/var/www/html/` (Linux).

2. **Criar o banco de dados**
    - Abra o phpMyAdmin (http://localhost/phpmyadmin).
    - Importe o arquivo `vezz/sql/SQL script VEZZ.sql`.
    - O script criará o banco `vezz` e as tabelas (o arquivo não contém dados de exemplo).

3. **Configurar conexão (se necessário)**
    - Abra `vezz/includes/config.php`.
    - Ajuste `DB_USER` e `DB_PASS` conforme seu MySQL local:
       ```php
       define('DB_USER', 'root');
       define('DB_PASS', '');   // senha do root no XAMPP geralmente é vazia
       ```

4. **Acessar o sistema**
    - Navegue para: `http://localhost/vezz/`

## Dados de Exemplo

Este repositório **não** inclui dados de exemplo por padrão (para segurança em deploys). Se precisar de dados de desenvolvimento, crie um arquivo separado `sql/vezz.sample_data.sql` com os INSERTs e use-o apenas em ambientes de teste.

## Funcionalidades Principais
- Cadastro e login de pacientes (autenticação automática paciente/gestor).
- Pesquisa de clínicas por nome e cidade.
- Agendamento de consultas com validação de horários.
- Cancelamento e reagendamento de consultas futuras.
- Dashboard do paciente com posição na fila e tempo estimado.
- Dashboard do gestor com atendimentos do dia, fila e pacientes cadastrados.
- Controle de fila em tempo real (AJAX a cada 5 segundos).
- Iniciar, finalizar e chamar próximo paciente.
- Acompanhamento da fila pelo paciente (posição e tempo estimado).

## Requisitos Obrigatórios Atendidos
- Todos os RFs (RF001 a RF020) implementados.
- Todos os RNFs (RNF001 a RNF010) contemplados.
- Script SQL exato conforme especificado.
- Senhas criptografadas com `password_hash` (BCRYPT).
- Prepared Statements (PDO) em todas as queries.
- Interface responsiva com Bootstrap 5.

````
Crie um arquivo `docker-compose.yml` (exemplo abaixo) para testar localmente apontando para o Supabase ou outro Postgres:

```yaml
version: '3.8'
services:
   vezz:
      build: .
      ports:
         - "8080:80"
      environment:
         DB_HOST: "seu_host_supabase"
         DB_PORT: "5432"
         DB_NAME: "seu_db"
         DB_USER: "seu_user"
         DB_PASSWORD: "suasenha"
         BASE_URL: "/"
      restart: unless-stopped
```

Uso:

```bash
docker compose up --build
# depois abra http://localhost:8080
```

## Deploy no Render (usando Docker)

Resumo rápido:

1. Suba o projeto para GitHub/GitLab.
2. No Render: New → Web Service → conectar o repositório.
3. Em "Environment", escolha `Docker` para utilizar o `Dockerfile` do repositório.
4. Configure as variáveis de ambiente no painel do serviço (DB_*, BASE_URL).
5. Deploy — Render constrói a imagem e entrega com domínio SSL.

Detalhes e comandos locais estão em `DEPLOY_RENDER.md`.

## Deploy no Fly.io

Resumo rápido:

1. Instale `flyctl` e execute `fly launch --no-deploy` no repositório.
2. Configure segredos com `fly secret set DB_HOST=... DB_USER=... DB_PASSWORD=...`
3. Rode `fly deploy` — Fly construirá a imagem pelo `Dockerfile`.

Detalhes e comandos estão em `DEPLOY_FLY.md`.

## Observações sobre Vercel

- O Vercel não é indicado para apps PHP tradicionais (não tem runtime PHP oficial). Só é viável servir a parte estática do projeto (HTML/CSS/JS) no Vercel e manter o backend PHP em outro host.
- Existem runtimes comunitários para PHP no Vercel, porém não são oficiais e podem ter limitações (extensões, performance, conexões persistentes ao Postgres).

## Testes & Debug

- Verifique logs do container/provedor para mensagens de erro (conexão DB, permissões).
- Para rodar comandos de migração SQL no Supabase, use a interface SQL do Supabase ou a CLI `supabase` após autenticar.

## Segurança

- Nunca commit `DB_PASSWORD` ou `.env` no repositório.
- Use segredos/variáveis de ambiente do provedor.

