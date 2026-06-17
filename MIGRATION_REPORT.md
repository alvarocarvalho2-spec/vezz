Migração para modo API-first (Supabase) — Relatório

Resumo
- O código agora suporta um modo "API-first" usando o cliente REST presente em `includes/supabase_api.php`.
- Quando as variáveis `SUPABASE_URL` e `SUPABASE_SERVICE_ROLE_KEY` (ou `SUPABASE_ANON_KEY`) estiverem definidas, o sistema define automaticamente `USE_SUPABASE_API` e usa o Supabase para leituras e, quando aplicável, para escritas.
- Operações de escrita (INSERT/UPDATE/DELETE) via Supabase exigem `SUPABASE_SERVICE_ROLE_KEY` — as funções helpers `supabase_insert` e `supabase_update` verificam isso e lançarão exceção se não estiver disponível.
- Em todos os pontos modificados foi mantido um fallback com PDO para compatibilidade caso a API não esteja habilitada.

Arquivos principais modificados
- `includes/functions.php` (fluxos de fila/atendimento, normalização de respostas)
- `clinica_detalhes.php`
- `dashboard_gestor.php`
- `cadastro.php`
- `cadastro_clinica.php`
- `cadastro_gestor.php`
- `recuperar_senha.php`
- `agendamento.php`
- `ajax_fila_gestor.php`
- `ajax_fila_paciente.php`
- `controle_fila.php`
- `dashboard_paciente.php`
- `login.php`

Observações importantes
- As operações de escrita via Supabase usam `supabase_insert` e `supabase_update` que exigem o Service Role Key por segurança. Nunca exponha essa chave no frontend.
- A detecção do modo API é automática em `includes/config.php` (baseada nas variáveis `SUPABASE_URL` e `SUPABASE_SERVICE_ROLE_KEY`/`SUPABASE_ANON_KEY`).
- O arquivo `includes/supabase_api.php` implementa os helpers e uma opção `SUPABASE_SKIP_SSL_VERIFY=1` útil para ambientes locais que usam certificados autoassinados.

Variáveis de ambiente necessárias
- SUPABASE_URL (ex: https://xyz.supabase.co)
- SUPABASE_SERVICE_ROLE_KEY (necessária para escritas via API)
- SUPABASE_ANON_KEY (apenas para leituras sem service role)
- SUPABASE_SKIP_SSL_VERIFY (opcional; `1` para desativar verificação SSL em desenvolvimento)

Como ativar o modo API localmente
- No arquivo `.env` do projeto (local na raiz), adicione as linhas:

SUPABASE_URL="https://seu-projeto.supabase.co"
SUPABASE_SERVICE_ROLE_KEY="seu_service_role_key"
# ou apenas para leitura
SUPABASE_ANON_KEY="seu_anon_key"
# Em dev local com cURL/SSL autoassinado
SUPABASE_SKIP_SSL_VERIFY=1

- Reinicie a sessão PHP (se necessário) ou reabra o navegador.

Comandos úteis (Windows, WAMP)
- Verificar sintaxe de um arquivo PHP:
```powershell
C:\wamp64\bin\php\php8.1.33\php.exe -l "c:\wamp64\www\VEZZ\agendamento.php"
```
- Verificar todo o projeto (exemplo com PowerShell glob):
```powershell
Get-ChildItem -Path . -Filter "*.php" -Recurse | ForEach-Object { & 'C:\\wamp64\\bin\\php\\php8.1.33\\php.exe' -l $_.FullName }
```

Testes de integração (exemplos)
- Inserir uma consulta via API (necessita `SUPABASE_SERVICE_ROLE_KEY`):
```bash
curl -X POST "${SUPABASE_URL}/rest/v1/tb_consulta?return=representation" \
  -H "apikey: ${SUPABASE_SERVICE_ROLE_KEY}" \
  -H "Authorization: Bearer ${SUPABASE_SERVICE_ROLE_KEY}" \
  -H "Content-Type: application/json" \
  -d '{"data_hora":"2026-06-20T10:00:00","status":"Agendada","id_paciente":1,"id_clinica":1}'
```
- Atualizar status via API (service role):
```bash
curl -X PATCH "${SUPABASE_URL}/rest/v1/tb_consulta?id_consulta=eq.123" \
  -H "apikey: ${SUPABASE_SERVICE_ROLE_KEY}" \
  -H "Authorization: Bearer ${SUPABASE_SERVICE_ROLE_KEY}" \
  -H "Content-Type: application/json" \
  -d '{"status":"Em Atendimento"}'
```

Verificações que fiz
- Varri o código por ocorrências de `INSERT`, `UPDATE`, `DELETE`, `prepare(` e normalizei/adicionei branches API onde necessário.
- Rodei `php -l` nos arquivos modificados e corrigi erros de sintaxe introduzidos durante os patches.

Próximos passos e recomendações
- Executar testes funcionais end-to-end com um ambiente Supabase configurado (com `SUPABASE_SERVICE_ROLE_KEY`) para validar fluxos de escrita (cadastro, agendamento, iniciar/finalizar atendimento).
- Remover logs sensíveis e garantir que `SUPABASE_SERVICE_ROLE_KEY` não apareça em arquivos versionados (`.env` não deve ser commitado).
- Opcional: adicionar um teste automatizado simples que valida uma inserção/atualização via as helpers `supabase_insert`/`supabase_update` usando variáveis de teste.

Se desejar, eu posso:
- Executar a varredura final novamente e abrir PR com todas as mudanças agrupadas.
- Implementar testes de integração mínimos que usam as helpers Supabase (mockáveis) para CI.
- Gerar um diff consolidado dos arquivos alterados para revisão.

