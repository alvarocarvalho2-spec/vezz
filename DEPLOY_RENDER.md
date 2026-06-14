# Deploy no Render (Docker) — guia rápido

1. Preparar repositório
   - Suba o projeto para um repositório GitHub/GitLab.

2. Criar serviço no Render
   - Em Render, clique em "New" → "Web Service".
   - Conecte o repositório e escolha a branch desejada.
   - Em "Environment", selecione "Docker" (Render usará o `Dockerfile` do repositório).

3. Variáveis de ambiente
   - Adicione variáveis no painel do serviço (Environment > Environment Variables):
     - `DB_HOST` = host do Supabase (p.ex. `db.xxxxxx.supabase.co`)
     - `DB_PORT` = `5432`
     - `DB_NAME` = nome do banco (p.ex. `postgres`)
     - `DB_USER` = usuário do banco
     - `DB_PASSWORD` = senha
     - ou use as chaves `SUPABASE_HOST`, `SUPABASE_USER`, `SUPABASE_PASSWORD`, `SUPABASE_DB` — o `includes/config.php` aceita ambas
     - `BASE_URL` = `/` ou caminho da aplicação (se for servir em subpath)

4. Build & Deploy
   - Render irá construir a imagem usando o `Dockerfile` e fazer deploy automaticamente.

5. Testes e ajustes
   - Verifique logs no painel do Render para checar erros de conexão/permissão.
   - Se precisar executar comandos de migração, rode localmente e importe via Supabase SQL UI (já que o banco está no Supabase).

6. Rodando localmente com Docker

```bash
docker build -t vezz .
docker run -p 8080:80 \
  -e DB_HOST=seu_host \
  -e DB_PORT=5432 \
  -e DB_NAME=seu_db \
  -e DB_USER=seu_user \
  -e DB_PASSWORD=suasenha \
  -e BASE_URL=/ \
  vezz
```

Observações
 - O `includes/config.php` já faz `sslmode=require`, então o Supabase funcionará com a configuração padrão.
 - Não armazene `.env` no repositório; use variáveis de ambiente no Render.
