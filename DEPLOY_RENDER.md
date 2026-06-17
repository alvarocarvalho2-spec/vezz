# Deploy no Render

Passos mínimos para publicar este projeto no Render usando o `Dockerfile` fornecido:

1. Commit + push do repositório para GitHub/GitLab.
2. No painel do Render, crie um novo **Web Service** e selecione "Docker".
3. Aponte para o repositório e branch desejados.
4. Defina as environment variables necessárias no painel do serviço (no mínimo):
   - `SUPABASE_URL`
   - `SUPABASE_ANON_KEY`
   - `SUPABASE_SERVICE_ROLE_KEY` (NÃO expor no cliente; apenas server-side)
   - `USE_SUPABASE_API` = `1` ou `0` conforme desejado
5. Escolha a porta padrão (o container expõe `80`). Render injeta `PORT` automaticamente, mas o `Dockerfile` usa 80 por padrão — não é necessário alterar.
6. (Opcional) Configure health checks e escalonamento conforme tráfego.

Testes locais:

Construir e subir via docker-compose:

```bash
docker-compose build --no-cache
docker-compose up -d
# abra http://localhost:8080
```

Observações:
- Render irá usar o `Dockerfile` para construir o container. Garanta que as variáveis de ambiente estejam corretas no painel do serviço.
- Se preferir, posso adicionar um `render.yaml` que descreva o serviço para deploy automático.
