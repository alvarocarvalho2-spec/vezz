<?php
/**
 * Funções auxiliares do sistema VEZZ
 */

require_once __DIR__ . '/config.php';
// Se em modo API, carregamos o cliente Supabase
if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    require_once __DIR__ . '/supabase_api.php';
}
/** Minutos estimados por posição na fila */
define('MINUTOS_POR_POSICAO', 10);

/** Horário de funcionamento padrão das clínicas */
define('HORA_INICIO', 8);
define('HORA_FIM', 18);

/**
 * Sanitiza string para exibição HTML
 */
function e($str)
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

/**
 * Helpers de sessão: obter informações do usuário atual
 */
function getUserId()
{
    return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;
}

function getUserType()
{
    return $_SESSION['usuario_tipo'] ?? null;
}

function getClinicaId()
{
    return isset($_SESSION['id_clinica']) ? (int) $_SESSION['id_clinica'] : 0;
}

/**
 * Retorna o logotipo textual VE⚡Z (HTML seguro — não usar com e())
 */
function vezzBrand()
{
    // Retorna a marca textual sem o ícone (raio)
    // Retorna texto da marca sem o ícone: sempre envolvido por uma classe específica
    return '<span class="vezz-brand-text">VEZZ</span>';
}

/**
 * Valida formato de e-mail
 */
function validarEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida CPF (formato básico: 11 dígitos)
 */
function validarCPF($cpf)
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11) {
        return false;
    }
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += (int) $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int) $cpf[$c] !== $d) {
            return false;
        }
    }
    return true;
}

/**
 * Formata CPF para exibição
 */
function formatarCPF($cpf)
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11) {
        return $cpf;
    }
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.'
        . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

/**
 * Valida CNPJ (14 dígitos + dígitos verificadores)
 */
function validarCNPJ($cnpj)
{
    $cnpj = preg_replace('/\D/', '', $cnpj);
    if (strlen($cnpj) !== 14) {
        return false;
    }
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $calcDigito = function ($base, $pesos) {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (int) $base[$i] * $peso;
        }
        $resto = $soma % 11;
        return $resto < 2 ? 0 : 11 - $resto;
    };

    $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    if ($calcDigito(substr($cnpj, 0, 12), $pesos1) !== (int) $cnpj[12]) {
        return false;
    }
    if ($calcDigito(substr($cnpj, 0, 13), $pesos2) !== (int) $cnpj[13]) {
        return false;
    }

    return true;
}

/**
 * Formata CNPJ para exibição
 */
function formatarCNPJ($cnpj)
{
    $cnpj = preg_replace('/\D/', '', $cnpj);
    if (strlen($cnpj) !== 14) {
        return $cnpj;
    }
    return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.'
        . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
}

/**
 * Valida CEP (8 dígitos)
 */
function validarCEP($cep)
{
    return strlen(preg_replace('/\D/', '', $cep)) === 8;
}

/**
 * Formata CEP para exibição
 */
function formatarCEP($cep)
{
    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) !== 8) {
        return $cep;
    }
    return substr($cep, 0, 5) . '-' . substr($cep, 5);
}

/**
 * Formata telefone
 */
function formatarTelefone($tel)
{
    $tel = preg_replace('/\D/', '', $tel);
    if (strlen($tel) === 11) {
        return '(' . substr($tel, 0, 2) . ') ' . substr($tel, 2, 5) . '-' . substr($tel, 7);
    }
    if (strlen($tel) === 10) {
        return '(' . substr($tel, 0, 2) . ') ' . substr($tel, 2, 4) . '-' . substr($tel, 6);
    }
    return $tel;
}

/**
 * Formata data/hora para exibição
 */
function formatarDataHora($datetime)
{
    if (empty($datetime)) {
        return '-';
    }
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Formata apenas data
 */
function formatarData($date)
{
    if (empty($date)) {
        return '-';
    }
    return date('d/m/Y', strtotime($date));
}

/**
 * Retorna horários de funcionamento da clínica (texto fixo)
 */
function obterHorariosClinica()
{
    return 'Seg-Sex, das 08h às 18h';
}

/**
 * Calcula tempo estimado de espera com base na posição
 */
function obterTempoEstimado($posicao)
{
    if ($posicao <= 0) {
        return 'Em atendimento';
    }
    $minutos = $posicao * MINUTOS_POR_POSICAO;
    if ($minutos < 60) {
        return $minutos . ' minuto(s)';
    }
    $horas = floor($minutos / 60);
    $mins  = $minutos % 60;
    return $horas . 'h' . ($mins > 0 ? ' ' . $mins . 'min' : '');
}

/**
 * Obtém consultas na fila de uma clínica (dia atual, Agendada ou Em Atendimento)
 */
// Normaliza o valor de uma relação retornada pelo PostgREST: aceita array numericamente indexado
// ou um objeto associativo único e retorna o primeiro registro como array associativo.
function relation_first($rel)
{
    if (empty($rel)) return [];
    if (!is_array($rel)) return [];
    if (isset($rel[0])) return $rel[0];
    // já é um objeto associativo (PostgREST às vezes retorna objeto em vez de array)
    return $rel;
}

function obterConsultasFila($pdo, $idClinica)
{
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $today = date('Y-m-d');
        $path = "tb_consulta?select=*,tb_paciente(id_paciente,nome,telefone)&id_clinica=eq." . rawurlencode($idClinica)
            . "&status=in.(Agendada,Em%20Atendimento)&" . supabase_day_range_query($today) . "&order=data_hora.asc";
        $res = supabase_request('GET', $path);
        if ($res['status'] >= 200 && $res['status'] < 300 && is_array($res['body'])) {
            $out = [];
            foreach ($res['body'] as $row) {
                // Normalizar diferentes formatos de resultado (relações mapeadas ou campos já achatados)
                $nome = null;
                $telefone = null;
                if (!empty($row['tb_paciente']) && is_array($row['tb_paciente'])) {
                    $p = relation_first($row['tb_paciente']);
                    $nome = $p['nome'] ?? null;
                    $telefone = $p['telefone'] ?? null;
                }
                // fallback: campos achatados que podem existir em alguns outputs
                if (empty($nome)) {
                    $nome = $row['nome_paciente'] ?? $row['paciente_nome'] ?? $row['nome'] ?? null;
                }
                if (empty($telefone)) {
                    $telefone = $row['telefone_paciente'] ?? $row['telefone'] ?? null;
                }
                // garantir nomes/telefones em chaves consistentes para todas as views
                $row['nome_paciente'] = $nome;
                $row['telefone_paciente'] = $telefone;
                // chaves comuns usadas em templates
                $row['telefone'] = $telefone;
                $row['nome'] = $nome;
                if (isset($row['tb_paciente'])) unset($row['tb_paciente']);
                $out[] = $row;
            }
            return $out;
        }
        return [];
    }

    $stmt = $pdo->prepare("\n                SELECT c.*, p.nome AS nome_paciente, p.telefone AS telefone_paciente\n                FROM tb_consulta c\n                INNER JOIN tb_paciente p ON p.id_paciente = c.id_paciente\n                WHERE c.id_clinica = ?\n                    AND DATE(c.data_hora) = CURRENT_DATE\n                    AND c.status IN ('Agendada', 'Em Atendimento')\n                ORDER BY c.data_hora ASC\n                ");
    $stmt->execute([$idClinica]);
    return $stmt->fetchAll();
}

/**
 * Calcula posição na fila para uma consulta específica
 */
function calcularPosicaoFila($pdo, $idClinica, $idConsulta = null)
{
    $consultas = obterConsultasFila($pdo, $idClinica);

    if ($idConsulta === null) {
        return count($consultas);
    }

    foreach ($consultas as $index => $consulta) {
        if ((int) $consulta['id_consulta'] === (int) $idConsulta) {
            return $index + 1;
        }
    }

    return 0;
}

/**
 * Recalcula e atualiza posições na tb_fila_atendimento
 */
function recalcularPosicoesFila($pdo, $idClinica)
{
    $consultas = obterConsultasFila($pdo, $idClinica);

    foreach ($consultas as $index => $consulta) {
        $posicao = $index + 1;

        if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
            // atualiza via REST (service role key necessário)
            $query = 'id_consulta=eq.' . rawurlencode($consulta['id_consulta']);
            try {
                supabase_update('tb_fila_atendimento', $query, ['posicao' => $posicao]);
            } catch (Exception $e) {
                error_log('recalcularPosicoesFila supabase_update error: ' . $e->getMessage());
            }
            continue;
        }

        $stmt = $pdo->prepare("\n            SELECT id_fila_atendimento FROM tb_fila_atendimento WHERE id_consulta = ?\n        ");
        $stmt->execute([$consulta['id_consulta']]);
        $fila = $stmt->fetch();

        if ($fila) {
            $upd = $pdo->prepare("UPDATE tb_fila_atendimento SET posicao = ? WHERE id_consulta = ?");
            $upd->execute([$posicao, $consulta['id_consulta']]);
        }
    }
}

/**
 * Inicia atendimento de uma consulta
 */
function iniciarAtendimento($pdo, $idConsulta, $idClinica)
{
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        // Fluxo via Supabase REST (sem transação atômica): validar e aplicar alterações
        $today = date('Y-m-d');
        // buscar consulta
        $path = 'tb_consulta?select=id_consulta,data_hora,status&id_consulta=eq.' . rawurlencode($idConsulta)
            . '&id_clinica=eq.' . rawurlencode($idClinica) . '&' . supabase_day_range_query($today);
        $res = supabase_request('GET', $path);
        $consulta = ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) ? $res['body'][0] : null;

        if (!$consulta || ($consulta['status'] ?? '') !== 'Agendada') {
            throw new Exception('Consulta não encontrada ou não está agendada.');
        }

        // Verifica se já existe atendimento em andamento
        $path2 = 'tb_consulta?id_clinica=eq.' . rawurlencode($idClinica) . '&status=eq.Em%20Atendimento&' . supabase_day_range_query($today);
        $res2 = supabase_request('GET', $path2);
        if ($res2['status'] >= 200 && is_array($res2['body']) && count($res2['body']) > 0) {
            throw new Exception('Já existe um atendimento em andamento. Finalize-o antes de iniciar outro.');
        }

        // atualiza status da consulta (write via service role)
        supabase_update('tb_consulta', 'id_consulta=eq.' . rawurlencode($idConsulta), ['status' => 'Em Atendimento']);

        $posicao = calcularPosicaoFila($pdo, $idClinica, $idConsulta);

        // verificar fila existente
        $rf = supabase_request('GET', 'tb_fila_atendimento?select=id_fila_atendimento&id_consulta=eq.' . rawurlencode($idConsulta));
        $filaExistente = ($rf['status'] >= 200 && is_array($rf['body']) && count($rf['body']) > 0) ? $rf['body'][0] : null;

        if ($filaExistente) {
            supabase_update('tb_fila_atendimento', 'id_consulta=eq.' . rawurlencode($idConsulta), ['hora_inicio' => date('c'), 'posicao' => $posicao]);
        } else {
            $horaEntrada = $consulta['data_hora'] ?? null;
            supabase_insert('tb_fila_atendimento', ['posicao' => $posicao, 'hora_entrada' => $horaEntrada, 'hora_inicio' => date('c'), 'id_consulta' => (int)$idConsulta]);
        }

        recalcularPosicoesFila($pdo, $idClinica);
        return true;
    }

    // Fallback PDO
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("\n            SELECT id_consulta, data_hora, status\n                FROM tb_consulta\n                WHERE id_consulta = ? AND id_clinica = ? AND DATE(data_hora) = CURRENT_DATE\n        ");
        $stmt->execute([$idConsulta, $idClinica]);
        $consulta = $stmt->fetch();

        if (!$consulta || $consulta['status'] !== 'Agendada') {
            throw new Exception('Consulta não encontrada ou não está agendada.');
        }

        // Verifica se já existe atendimento em andamento
        $stmt = $pdo->prepare("\n            SELECT id_consulta FROM tb_consulta\n                WHERE id_clinica = ? AND DATE(data_hora) = CURRENT_DATE AND status = 'Em Atendimento'\n        ");
        $stmt->execute([$idClinica]);
        if ($stmt->fetch()) {
            throw new Exception('Já existe um atendimento em andamento. Finalize-o antes de iniciar outro.');
        }

        $upd = $pdo->prepare("UPDATE tb_consulta SET status = 'Em Atendimento' WHERE id_consulta = ?");
        $upd->execute([$idConsulta]);

        $posicao = calcularPosicaoFila($pdo, $idClinica, $idConsulta);

        $stmt = $pdo->prepare("SELECT id_fila_atendimento FROM tb_fila_atendimento WHERE id_consulta = ?");
        $stmt->execute([$idConsulta]);
        $filaExistente = $stmt->fetch();

        if ($filaExistente) {
            $upd = $pdo->prepare("\n                UPDATE tb_fila_atendimento\n                SET hora_inicio = CURRENT_TIMESTAMP, posicao = ?\n                WHERE id_consulta = ?\n            ");
            $upd->execute([$posicao, $idConsulta]);
        } else {
            $ins = $pdo->prepare("\n                INSERT INTO tb_fila_atendimento (posicao, hora_entrada, hora_inicio, id_consulta)\n                VALUES (?, ?, CURRENT_TIMESTAMP, ?)\n            ");
            $ins->execute([$posicao, $consulta['data_hora'], $idConsulta]);
        }

        recalcularPosicoesFila($pdo, $idClinica);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Finaliza atendimento em andamento
 */
function finalizarAtendimento($pdo, $idConsulta, $idClinica)
{
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        // via Supabase REST
        // verificar existência
        $path = 'tb_consulta?select=id_consulta,status&id_consulta=eq.' . rawurlencode($idConsulta)
            . '&id_clinica=eq.' . rawurlencode($idClinica) . '&status=eq.Em%20Atendimento';
        $res = supabase_request('GET', $path);
        $consulta = ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) ? $res['body'][0] : null;
        if (!$consulta) {
            throw new Exception('Nenhum atendimento em andamento encontrado.');
        }

        supabase_update('tb_consulta', 'id_consulta=eq.' . rawurlencode($idConsulta), ['status' => 'Finalizada']);
        supabase_update('tb_fila_atendimento', 'id_consulta=eq.' . rawurlencode($idConsulta), ['hora_fim' => date('c')]);
        recalcularPosicoesFila($pdo, $idClinica);
        return true;
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("\n            SELECT id_consulta, status FROM tb_consulta\n            WHERE id_consulta = ? AND id_clinica = ? AND status = 'Em Atendimento'\n        ");
        $stmt->execute([$idConsulta, $idClinica]);
        $consulta = $stmt->fetch();

        if (!$consulta) {
            throw new Exception('Nenhum atendimento em andamento encontrado.');
        }

        $upd = $pdo->prepare("UPDATE tb_consulta SET status = 'Finalizada' WHERE id_consulta = ?");
        $upd->execute([$idConsulta]);

        $upd = $pdo->prepare("\n            UPDATE tb_fila_atendimento SET hora_fim = CURRENT_TIMESTAMP WHERE id_consulta = ?\n        ");
        $upd->execute([$idConsulta]);

        recalcularPosicoesFila($pdo, $idClinica);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Chama o próximo paciente da fila
 */
function chamarProximoPaciente($pdo, $idClinica)
{
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $today = date('Y-m-d');
        $res = supabase_request('GET', 'tb_consulta?id_clinica=eq.' . rawurlencode($idClinica) . '&status=eq.Em%20Atendimento&' . supabase_day_range_query($today));
        if ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) {
            throw new Exception('Finalize o atendimento atual antes de chamar o próximo.');
        }

        $res2 = supabase_request('GET', 'tb_consulta?select=id_consulta&id_clinica=eq.' . rawurlencode($idClinica) . '&status=eq.Agendada&' . supabase_day_range_query($today) . '&order=data_hora.asc&limit=1');
        $proximo = ($res2['status'] >= 200 && is_array($res2['body']) && count($res2['body']) > 0) ? $res2['body'][0] : null;

        if (!$proximo) {
            throw new Exception('Não há pacientes aguardando na fila.');
        }

        return iniciarAtendimento($pdo, $proximo['id_consulta'], $idClinica);
    }

    $stmt = $pdo->prepare(" \n        SELECT id_consulta FROM tb_consulta\n        WHERE id_clinica = ? AND DATE(data_hora) = CURRENT_DATE AND status = 'Em Atendimento'\n    ");
    $stmt->execute([$idClinica]);
    if ($stmt->fetch()) {
        throw new Exception('Finalize o atendimento atual antes de chamar o próximo.');
    }

    $stmt = $pdo->prepare(" \n        SELECT id_consulta FROM tb_consulta\n        WHERE id_clinica = ? AND DATE(data_hora) = CURRENT_DATE AND status = 'Agendada'\n        ORDER BY data_hora ASC\n        LIMIT 1\n    ");
    $stmt->execute([$idClinica]);
    $proximo = $stmt->fetch();

    if (!$proximo) {
        throw new Exception('Não há pacientes aguardando na fila.');
    }

    return iniciarAtendimento($pdo, $proximo['id_consulta'], $idClinica);
}

/**
 * Gera lista de horários do expediente (intervalos de 30 min)
 */
/**
 * Gera lista de horários do expediente (intervalos de 30 min).
 * Aceita strings de hora no formato 'HH:MM' ou usa constantes padrão.
 */
function gerarHorariosExpediente($horaInicio = null, $horaFim = null)
{
    if ($horaInicio === null) {
        $horaInicio = sprintf('%02d:00', HORA_INICIO);
    }
    if ($horaFim === null) {
        $horaFim = sprintf('%02d:00', HORA_FIM);
    }

    // Normaliza possíveis formatos 'HH:MM:SS' -> 'HH:MM'
    if (is_string($horaInicio) && preg_match('/^\d{2}:\d{2}/', $horaInicio, $m)) {
        $horaInicioNorm = substr($horaInicio, 0, 5);
    } else {
        $horaInicioNorm = $horaInicio;
    }
    if (is_string($horaFim) && preg_match('/^\d{2}:\d{2}/', $horaFim, $m2)) {
        $horaFimNorm = substr($horaFim, 0, 5);
    } else {
        $horaFimNorm = $horaFim;
    }

    $start = DateTime::createFromFormat('H:i', $horaInicioNorm);
    $end = DateTime::createFromFormat('H:i', $horaFimNorm);
    if (!$start || !$end) {
        return [];
    }

    $horarios = [];
    $current = clone $start;
    while ($current < $end) {
        $horarios[] = $current->format('H:i');
        $current->modify('+30 minutes');
    }

    return $horarios;
}

/**
 * Valida data/hora de agendamento (permite mesmo dia com horário futuro)
 */
function validarDataHoraAgendamento(DateTime $dataHora, $horaInicio = null, $horaFim = null)
{
    $agora = new DateTime('now');

    if ($dataHora <= $agora) {
        return 'Selecione um horário futuro. No mesmo dia, escolha um horário posterior ao momento atual.';
    }

    $diaSemana = (int) $dataHora->format('N');
    // se a clinica definiu dias específicos, a verificação será feita fora desta função

    $hora = (int) $dataHora->format('H');
    $minuto = (int) $dataHora->format('i');

    // Se horários específicos da clínica foram fornecidos, use-os
    if ($horaInicio !== null && $horaFim !== null) {
        // normaliza formatos como 'HH:MM:SS' -> 'HH:MM' e valida
        $normalizeHora = function ($h) {
            if (!is_string($h)) return null;
            if (preg_match('/^\d{2}:\d{2}$/', $h)) return $h;
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $h)) return substr($h, 0, 5);
            return null;
        };

        $horaInicioNorm = $normalizeHora($horaInicio);
        $horaFimNorm = $normalizeHora($horaFim);

        if (!$horaInicioNorm || !$horaFimNorm) {
            return "Horário inválido da clínica.";
        }

        $hIniDT = DateTime::createFromFormat('H:i', $horaInicioNorm);
        $hFimDT = DateTime::createFromFormat('H:i', $horaFimNorm);
        if (!$hIniDT || !$hFimDT) {
            return "Horário inválido da clínica.";
        }

        $hIni = (int) $hIniDT->format('H');
        $hFim = (int) $hFimDT->format('H');

        if ($hora < $hIni || $hora >= $hFim) {
            return "Horário fora do expediente ({$horaInicioNorm} às {$horaFimNorm}).";
        }
    } else {
        if ($hora < HORA_INICIO || $hora >= HORA_FIM) {
            return 'Horário fora do expediente (08h às 18h).';
        }
    }

    if (!in_array($minuto, [0, 30], true)) {
        return 'Horário inválido. Selecione um horário de 30 em 30 minutos.';
    }

    $horarioTexto = $dataHora->format('H:i');
    $slots = gerarHorariosExpediente($horaInicio, $horaFim);
    if (!in_array($horarioTexto, $slots, true)) {
        return 'Horário fora dos intervalos disponíveis para agendamento.';
    }

    return null;
}

/**
 * Verifica se já existe consulta ativa no mesmo horário da clínica
 */
function horarioOcupadoNaClinica($pdo, $idClinica, $dataHora, $idConsultaExcluir = null)
{
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        // consulta via REST por igualdade de timestamp
        $path = 'tb_consulta?select=id_consulta&id_clinica=eq.' . rawurlencode($idClinica)
            . '&data_hora=eq.' . rawurlencode($dataHora) . '&status=in.(Agendada,Em%20Atendimento)';
        if ($idConsultaExcluir) {
            $path .= '&id_consulta=neq.' . rawurlencode($idConsultaExcluir);
        }
        $res = supabase_request('GET', $path);
        if ($res['status'] >= 200 && $res['status'] < 300 && is_array($res['body'])) {
            return count($res['body']) > 0;
        }
        return false;
    }

    $sql = "\n        SELECT COUNT(*) FROM tb_consulta\n        WHERE id_clinica = ?\n          AND data_hora = ?\n          AND status IN ('Agendada', 'Em Atendimento')\n    ";
    $params = [$idClinica, $dataHora];

    if ($idConsultaExcluir) {
        $sql .= ' AND id_consulta != ?';
        $params[] = $idConsultaExcluir;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Retorna horários disponíveis para agendamento em uma data
 */
function obterHorariosDisponiveis($pdo, $idClinica, $data, $idConsultaExcluir = null)
{
    $dataConsulta = DateTime::createFromFormat('Y-m-d', $data);
    if (!$dataConsulta) {
        return [];
    }

    $diaSemana = (int) $dataConsulta->format('N');

    // buscar horários e dias da clínica
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $res = supabase_request('GET', 'tb_clinica?select=hora_inicio,hora_fim,dias_atendimento&id_clinica=eq.' . rawurlencode($idClinica));
        $hr = ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) ? $res['body'][0] : null;
    } else {
        $stmt = $pdo->prepare('SELECT hora_inicio, hora_fim, dias_atendimento FROM tb_clinica WHERE id_clinica = ?');
        $stmt->execute([$idClinica]);
        $hr = $stmt->fetch();
    }
    if (!$hr) {
        return [];
    }
    $hora_inicio = $hr['hora_inicio'] ?? null;
    $hora_fim = $hr['hora_fim'] ?? null;
    $dias_atendimento = $hr['dias_atendimento'] ?? '1,2,3,4,5';

    // checar se o dia solicitado está na lista de dias da clínica
    $diasArr = array_map('intval', array_filter(array_map('trim', explode(',', $dias_atendimento))));
    if (!in_array($diaSemana, $diasArr, true)) {
        return [];
    }
    $horarios = gerarHorariosExpediente($hora_inicio, $hora_fim);

    // Log de depuração: mostrar horários/configuração da clínica
    error_log('[horarios_disponiveis_debug] id_clinica=' . $idClinica . ' hora_inicio=' . ($hora_inicio ?? 'NULL') . ' hora_fim=' . ($hora_fim ?? 'NULL') . ' dias=' . $dias_atendimento);
    error_log('[horarios_disponiveis_debug] slots_gerados=' . count($horarios) . ' primeiro=' . ($horarios[0] ?? 'NULL') . ' ultimo=' . ($horarios[count($horarios)-1] ?? 'NULL'));
    $agora = new DateTime('now');

    if ($data === $agora->format('Y-m-d')) {
        $horarios = array_values(array_filter($horarios, function ($horario) use ($data, $agora) {
            $slot = DateTime::createFromFormat('Y-m-d H:i', $data . ' ' . $horario);
            return $slot && $slot > $agora;
        }));
    }

    // obter consultas ocupadas (por data)
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $dayQ = supabase_day_range_query($data);
        $path = 'tb_consulta?select=data_hora&id_clinica=eq.' . rawurlencode($idClinica) . '&' . $dayQ . '&status=in.(Agendada,Em%20Atendimento)';
        if ($idConsultaExcluir) $path .= '&id_consulta=neq.' . rawurlencode($idConsultaExcluir);
        $res = supabase_request('GET', $path);
        $ocupados = [];
        if ($res['status'] >= 200 && is_array($res['body'])) {
            foreach ($res['body'] as $r) {
                if (!empty($r['data_hora'])) {
                    $ocupados[] = substr($r['data_hora'], 11, 5);
                }
            }
        }
        error_log('[horarios_disponiveis_debug] ocupados_count=' . count($ocupados) . ' ocupados=' . implode(',', $ocupados));
        return array_values(array_diff($horarios, $ocupados));
    }

    $sql = "\n                SELECT CAST(data_hora AS time) AS hora\n                FROM tb_consulta\n                WHERE id_clinica = ?\n                    AND DATE(data_hora) = ?\n                    AND status IN ('Agendada', 'Em Atendimento')\n        ";
    $params = [$idClinica, $data];

    if ($idConsultaExcluir) {
        $sql .= ' AND id_consulta != ?';
        $params[] = $idConsultaExcluir;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $ocupadosFormatados = array_map(function ($h) {
        return substr($h, 0, 5);
    }, $ocupados);

    error_log('[horarios_disponiveis_debug] ocupados_count=' . count($ocupadosFormatados) . ' ocupados=' . implode(',', $ocupadosFormatados));

    return array_values(array_diff($horarios, $ocupadosFormatados));
}

/**
 * Busca clínicas por nome e/ou cidade
 */
function pesquisarClinicas($pdo, $nome = '', $cidade = '')
{
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $query = [];
        if ($nome !== '') {
            $query[] = 'nome=ilike.%25' . rawurlencode($nome) . '%25';
        }
        if ($cidade !== '') {
            $query[] = 'tb_endereco.cidade=ilike.%25' . rawurlencode($cidade) . '%25';
        }
        $path = 'tb_clinica?select=id_clinica,nome,telefone,descricao,tb_endereco(rua,numero,bairro,cidade,cep)';
        if (!empty($query)) $path .= '&' . implode('&', $query);
        $path .= '&order=nome.asc';
        $res = supabase_request('GET', $path);
        if ($res['status'] >= 200 && is_array($res['body'])) {
            $out = [];
            foreach ($res['body'] as $r) {
                $addr = relation_first($r['tb_endereco'] ?? []);
                $out[] = [
                    'id_clinica' => $r['id_clinica'],
                    'nome' => $r['nome'],
                    'telefone' => $r['telefone'],
                    'descricao' => $r['descricao'],
                    'rua' => $addr['rua'] ?? null,
                    'numero' => $addr['numero'] ?? null,
                    'bairro' => $addr['bairro'] ?? null,
                    'cidade' => $addr['cidade'] ?? null,
                    'cep' => $addr['cep'] ?? null,
                ];
            }
            return $out;
        }
        return [];
    }

    $sql = "\n        SELECT c.id_clinica, c.nome, c.telefone, c.descricao,\n               e.rua, e.numero, e.bairro, e.cidade, e.cep\n        FROM tb_clinica c\n        INNER JOIN tb_endereco e ON e.id_clinica = c.id_clinica\n        WHERE 1=1\n    ";
    $params = [];

    if ($nome !== '') {
        $sql .= " AND c.nome LIKE ?";
        $params[] = '%' . $nome . '%';
    }

    if ($cidade !== '') {
        $sql .= " AND e.cidade LIKE ?";
        $params[] = '%' . $cidade . '%';
    }

    $sql .= " ORDER BY c.nome ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Retorna badge CSS class para status da consulta
 */
function badgeStatus($status)
{
    $map = [
        'Agendada'        => 'badge-agendado',
        'Aguardando'      => 'badge-agendado',
        'Em Atendimento'  => 'badge-em-atendimento',
        'Finalizada'      => 'badge-finalizado',
        'Finalizado'      => 'badge-finalizado',
        'Cancelada'       => 'badge-cancelado',
        'Cancelado'       => 'badge-cancelado',
    ];
    return 'badge-status ' . ($map[$status] ?? 'badge-cancelado');
}

/**
 * Define mensagem flash na sessão
 */
function setFlash($tipo, $mensagem)
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

/**
 * Exibe e remove mensagem flash
 */
function getFlash()
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Retorna dados da fila para AJAX (gestor)
 */
function obterDadosFilaGestor($pdo, $idClinica)
{
    $consultas = obterConsultasFila($pdo, $idClinica);

    $aguardando = [];
    $atual = null;

    foreach ($consultas as $c) {
        $item = [
            'id_consulta'    => $c['id_consulta'],
            'nome_paciente'  => $c['nome_paciente'],
            'telefone'       => $c['telefone_paciente'],
            'data_hora'      => formatarDataHora($c['data_hora']),
            'status'         => $c['status'],
            'posicao'        => calcularPosicaoFila($pdo, $idClinica, $c['id_consulta']),
        ];

        if ($c['status'] === 'Em Atendimento') {
            $atual = $item;
        } else {
            $aguardando[] = $item;
        }
    }

    return [
        'aguardando'      => $aguardando,
        'atendimento_atual' => $atual,
        'total_aguardando'  => count($aguardando),
    ];
}

/**
 * Retorna dados da fila para AJAX (paciente)
 */
function obterDadosFilaPaciente($pdo, $idPaciente)
{
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $today = date('Y-m-d');
        $path = 'tb_consulta?select=*,tb_clinica(nome)&id_paciente=eq.' . rawurlencode($idPaciente) . '&' . supabase_day_range_query($today)
            . '&status=in.(Agendada,Em%20Atendimento,Finalizada)&order=data_hora.desc&limit=1';
        $res = supabase_request('GET', $path);
        $consulta = ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) ? $res['body'][0] : null;

        if (!$consulta) {
            return [
                'tem_consulta' => false,
                'mensagem'     => 'Você não possui consulta agendada para hoje.',
            ];
        }

        $idClinica = $consulta['id_clinica'] ?? null;
        $posicao = 0;
        $pacientesAFrente = 0;
        $tempoEstimado = '-';

        if (in_array($consulta['status'], ['Agendada', 'Em Atendimento'], true)) {
            $posicao = calcularPosicaoFila($pdo, $idClinica, $consulta['id_consulta']);
            $pacientesAFrente = max(0, $posicao - 1);
        }

        $statusExibicao = $consulta['status'];
        if ($consulta['status'] === 'Agendada') {
            $statusExibicao = 'Aguardando';
            $tempoEstimado = obterTempoEstimado($pacientesAFrente);
        } elseif ($consulta['status'] === 'Em Atendimento') {
            $tempoEstimado = 'Em atendimento';
            $pacientesAFrente = 0;
        } elseif ($consulta['status'] === 'Finalizada') {
            $statusExibicao = 'Finalizado';
            $tempoEstimado = '-';
        }

        $rc = relation_first($consulta['tb_clinica'] ?? []);
        return [
            'tem_consulta'       => true,
            'nome_clinica'       => $rc['nome'] ?? $consulta['nome_clinica'] ?? null,
            'data_hora'          => formatarDataHora($consulta['data_hora']),
            'status'             => $statusExibicao,
            'posicao'            => $posicao,
            'pacientes_a_frente' => $pacientesAFrente,
            'tempo_estimado'     => $tempoEstimado,
            'id_consulta'        => $consulta['id_consulta'],
        ];
    }

    $stmt = $pdo->prepare("\n        SELECT c.*, cl.nome AS nome_clinica\n        FROM tb_consulta c\n        INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica\n                WHERE c.id_paciente = ?\n                    AND DATE(c.data_hora) = CURRENT_DATE\n          AND c.status IN ('Agendada', 'Em Atendimento', 'Finalizada')\n        ORDER BY c.data_hora DESC\n        LIMIT 1\n    ");
    $stmt->execute([$idPaciente]);
    $consulta = $stmt->fetch();

    if (!$consulta) {
        return [
            'tem_consulta' => false,
            'mensagem'     => 'Você não possui consulta agendada para hoje.',
        ];
    }

    $posicao = 0;
    $pacientesAFrente = 0;
    $tempoEstimado = '-';

    if (in_array($consulta['status'], ['Agendada', 'Em Atendimento'], true)) {
        $posicao = calcularPosicaoFila($pdo, $consulta['id_clinica'], $consulta['id_consulta']);
        $pacientesAFrente = max(0, $posicao - 1);
    }

    $statusExibicao = $consulta['status'];
    if ($consulta['status'] === 'Agendada') {
        $statusExibicao = 'Aguardando';
        $tempoEstimado = obterTempoEstimado($pacientesAFrente);
    } elseif ($consulta['status'] === 'Em Atendimento') {
        $tempoEstimado = 'Em atendimento';
        $pacientesAFrente = 0;
    } elseif ($consulta['status'] === 'Finalizada') {
        $statusExibicao = 'Finalizado';
        $tempoEstimado = '-';
    }

    return [
        'tem_consulta'       => true,
        'nome_clinica'       => $consulta['nome_clinica'],
        'data_hora'          => formatarDataHora($consulta['data_hora']),
        'status'             => $statusExibicao,
        'posicao'            => $posicao,
        'pacientes_a_frente' => $pacientesAFrente,
        'tempo_estimado'     => $tempoEstimado,
        'id_consulta'        => $consulta['id_consulta'],
    ];
}
