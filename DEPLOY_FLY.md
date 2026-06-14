# Deploy no Fly.io — guia rápido

1. Instale o `flyctl`
   - Siga https://fly.io/docs/hands-on/install-flyctl/

2. Inicialize o app

```bash
cd path/to/repo
fly launch --name vezz --region gru --no-deploy
```

3. Configure segredos (variáveis de ambiente)

```bash
fly secret set DB_HOST=seu_host DB_PORT=5432 DB_NAME=seu_db DB_USER=seu_user DB_PASSWORD=suasenha BASE_URL=/
```

4. Deploy

```bash
fly deploy
```

5. Observações
 - O Fly usará o `Dockerfile` presente no repositório para construir a imagem.
 - Se preferir, crie `fly.toml` manualmente e ajuste valores de `services` e `env`.
 - Teste localmente com Docker antes de deploy (comandos no `DEPLOY_RENDER.md`).
