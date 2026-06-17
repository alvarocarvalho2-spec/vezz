# Migração para modo API (Supabase)

Arquivos movidos de `tasks/`:

---

## migrate_to_supabase_prompt.txt

Prompt — tarefa: migrar app PHP para modo API (Supabase) com fallback PDO
---
Você é um assistente de desenvolvimento experiente. Objetivo: converter automaticamente os pontos do código que ainda dependem de conexão direta PostgreSQL (`$pdo->prepare`, `$pdo->query`, `$pdo->beginTransaction`, `$pdo->commit`, `$pdo->rollBack`) para um modo “API-first” usando o cliente REST já presente em `includes/supabase_api.php`, mantendo fallback PDO quando `USE_SUPABASE_API` não estiver habilitado. Faça mudanças seguras, testáveis e reversíveis.

Regras obrigatórias
- Nunca remova arquivos originais sem criar backup (ex.: copiar `file.php` → `file.php.bak` antes de editar).
- Todas as operações de escrita (INSERT/UPDATE/DELETE) via Supabase devem usar a função helper que requer `SUPABASE_SERVICE_ROLE_KEY` (ex.: `supabase_insert`, `supabase_update`) e devem recusar operação se a chave de serviço não estiver disponível.
- Leituras devem usar `supabase_request('GET', ...)` ou os helpers existentes (ex.: `supabase_select`) e manter a estrutura de dados compatível com as views existentes (normalizar nomes de campos quando necessário).
- Preservar lógica de negócio e segurança: nunca expor `SUPABASE_SERVICE_ROLE_KEY` client-side; alterações server-side somente.
- Mantém transações lógicas: se uma operação precisa ser atômica, preferir criar chamadas RPC/RLS no Supabase ou comentar no código que a conversão exige stored procedures — não implementar sequência insegura que cause perda de atomicidade sem sinalizar.
- Rodar lint/sintaxe PHP após cada arquivo modificado: `php -l <arquivo>` (ou ferramenta equivalente). Se não houver `php` no PATH, registrar esse aviso no relatório.

Passos operacionais (faça em ordem)
1. Detectar arquivos a alterar:
   - Buscar ocorrências de `$pdo->prepare`, `$pdo->query`, `$pdo->beginTransaction`, `$pdo->commit`, `$pdo->rollBack` em todo o projeto.
2. Para cada arquivo detectado, aplicar este padrão de conversão:
   a. Criar backup: copiar `file.php` → `file.php.bak`.
   b. Inserir no topo (se já não existir): `require_once __DIR__ . '/includes/functions.php';` (usar helpers).
   c. Substituir blocos de SELECT simples por chamadas a `supabase_request('GET', 'tb_xxx?select=...')` ou usar helper em `includes/functions.php` (ex.: `pesquisarClinicas`, `obterConsultasFila`, `obterDadosFilaGestor`, etc.). Normalizar o JSON retornado para manter campos usados na view.
   d. Para updates/inserts: trocar `$pdo->prepare(...)->execute(...)` por `supabase_insert`/`supabase_update`; verificar se `SUPABASE_SERVICE_ROLE_KEY` existe — se não, lançar erro amigável e logar.
   e. Para transações complexas (vários updates dependentes), não substituir por múltiplas chamadas REST sem marcar a falta de atomicidade; criar comentário TODO sugerindo RPC no Supabase e preservar fallback PDO quando disponível.
   f. Rodar `php -l` no arquivo modificado e corrigir alertas de sintaxe.
   g. Testar funcionalidade do endpoint (verificar via curl ou navegador) e registrar resposta esperada/observada.
   h. Commit com mensagem clara.
3. Ajustes comuns:
   - Quando PostgREST não aceita CASTs ou funções SQL na seleção, mover parse para PHP (ex.: extrair hora com `substr($r['data_hora'], 11, 5)`).
   - Garantir nomes de campo compatíveis: ex., quando `supabase` retorna `tb_paciente` como sub-array, mapear para `nome_paciente`/`telefone` conforme templates existentes.
   - Em páginas AJAX, preferir retornar HTML gerado pelo PHP já compatível (mesmo formato) — não mudar contrato de resposta.
4. Testes automáticos e manuais:
   - Após cada arquivo editado, executar `php -l <arquivo>` e registrar saída.
   - Testes HTTP: usar `curl` para verificar endpoints convertidos. Exemplo:
     - `curl -sS -D - http://localhost/VEZZ/ajax_fila_gestor.php -o /dev/null`
   - Verificar páginas principais (`/VEZZ/`, `login.php`, `dashboard_gestor.php`, `dashboard_paciente.php`) carregam sem erros HTTP 500.
5. Relatório final:
   - Gerar lista de arquivos modificados, backups criados, commits feitos e endpoints testados com status (OK/Erro).
   - Listar arquivos que necessitam de RPC/stored procedure para garantir atomicidade.
   - Incluir instruções de deploy: adicionar `SUPABASE_SERVICE_ROLE_KEY` ao `.env` no servidor, remover qualquer `SUPABASE_SKIP_SSL_VERIFY` antes de produção.

Contexto do repositório (para usar no prompt)
- Projeto root: `c:\wamp64\www\VEZZ`
- Helpers importantes: `includes/config.php`, `includes/supabase_api.php`, `includes/functions.php` (contém helpers já adaptados).
- Flag de modo API: `USE_SUPABASE_API` (definida em `includes/config.php` quando `SUPABASE_URL`/chaves existirem).
- Variáveis de ambiente necessárias: `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY` (somente server-side para writes).
- Política de segurança: escrever no DB via REST SÓ com `SUPABASE_SERVICE_ROLE_KEY`.

Fim do prompt.

---

## pdo_usage_files.txt

List of files with direct PDO/transaction usage (scan result):

- includes/functions.php
- index.php
- agendamento.php
- ajax_fila_gestor.php
- ajax_fila_paciente.php
- cadastro.php
- cadastro_clinica.php
- cadastro_gestor.php
- clinica_detalhes.php
- dashboard_gestor.php
- dashboard_paciente.php
- login.php
- recuperar_senha.php

Notes:
- Many occurrences are inside `includes/functions.php` (complex logic and transactions).
- Suggested next step: create `.bak` backups for these files, then convert in small batches (e.g., convert all AJAX endpoints first, then dashboards, then forms/registration pages).
