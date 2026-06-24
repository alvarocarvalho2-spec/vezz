# Deploy no Infinity Free (shared hosting)

Este repositório foi adaptado para rodar em hosting compartilhado (ex.: Infinity Free) usando a API Supabase (PostgREST). Siga os passos abaixo.

1) Pré-requisitos
- Conta no Supabase com projeto configurado.
- Chaves: `SUPABASE_URL`, `SUPABASE_ANON_KEY` (e `SUPABASE_SERVICE_ROLE_KEY` se necessário para escritas server-side).
- Acesso FTP/FTPS para seu espaço no Infinity Free.
- PHP (versão >= 8.0 preferível). Verifique suporte às extensões que o app usa (cURL, mbstring, json, gd, zip).

2) Arquivos importantes
- `includes/config.php` lê um arquivo `.env` na raiz se presente. Copie `.env.example` para `.env` e preencha com suas chaves.
- Não é necessário Docker nem supervisord em hosting compartilhado; estes arquivos foram removidos.

3) Preparar `.env`
- No seu ambiente local, copie `.env.example` para `.env` e preencha:
  - `USE_SUPABASE_API=1`
  - `SUPABASE_URL` e `SUPABASE_ANON_KEY` (e `SUPABASE_SERVICE_ROLE_KEY` se fizer escritas via API server-side)
  - `BASE_URL` se a aplicação estiver em subpasta

4) Fazer upload
- Faça upload de todo o projeto para o `htdocs`/`public_html` do seu espaço (ou o diretório público do host) via FTP/FTPS.
- Certifique-se de também enviar o arquivo `.env` (mantenha protegido).

5) Permissões
- Ajuste permissões de uploads/temporal se necessário (verifique pasta de uploads se existir).

6) Testes
- Acesse a URL pública e teste páginas básicas (login, listar).
- Se houver erros, verifique os logs de erro do host e valide as variáveis no `.env`.

7) Observações
- Infinity Free pode não permitir conexões de saída para determinados hosts/ports. Se a API Supabase não responder, verifique com o suporte do provedor.
- Para uploads de arquivos, recomendo usar armazenamento externo (S3/Spaces) porque o armazenamento compartilhado pode ser limitado.

Se quiser, eu posso:
- Gerar um pequeno script que verifica `SUPABASE_URL`/keys no carregamento e mostra instruções se faltarem.
- Converter rotinas que usam PDO direto para chamadas via `supabase_api.php` caso ainda existam trechos usando PDO.
