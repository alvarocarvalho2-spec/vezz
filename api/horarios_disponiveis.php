<?php
// Permite, em ambiente local, fornecer o ID da sessão via query string `PHPSESSID` ou header `X-Session-Id`.
// Deve ser definido antes de `session_start()` em `includes/config.php`.
$debugSid = $_GET['PHPSESSID'] ?? null;
if (empty($debugSid) && !empty($_SERVER['HTTP_X_SESSION_ID'])) {
    $debugSid = $_SERVER['HTTP_X_SESSION_ID'];
}
if ($debugSid) {
    // só aceitar esse fallback em localhost para reduzir risco
    $host = $_SERVER['SERVER_NAME'] ?? '';
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($host, 'localhost') !== false || $remote === '127.0.0.1' || $remote === '::1') {
        session_id($debugSid);
    }
}
/**
 * API AJAX - Horários disponíveis para agendamento
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/supabase_api.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id']) || ($_SESSION['usuario_tipo'] ?? '') !== 'paciente') {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$idClinica = (int) ($_GET['id_clinica'] ?? 0);
$data = trim($_GET['data'] ?? '');
$idConsulta = (int) ($_GET['id_consulta'] ?? 0);

if ($idClinica <= 0 || $data === '') {
    echo json_encode(['erro' => 'Parâmetros inválidos']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    echo json_encode(['erro' => 'Data inválida']);
    exit;
}

try {
    // buscar configurações da clínica (API ou PDO)
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $res = supabase_request('GET', 'tb_clinica?select=hora_inicio,hora_fim,dias_atendimento&id_clinica=eq.' . $idClinica);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new Exception('Erro ao consultar clínica (API)');
        }
        $hr = $res['body'][0] ?? null;
        $hora_inicio = $hr['hora_inicio'] ?? null;
        $hora_fim = $hr['hora_fim'] ?? null;
        $dias_atendimento = $hr['dias_atendimento'] ?? '1,2,3,4,5';
    } else {
        if (!isset($pdo)) throw new Exception('PDO não inicializado');
        $stmt = $pdo->prepare('SELECT hora_inicio, hora_fim, dias_atendimento FROM tb_clinica WHERE id_clinica = ?');
        $stmt->execute([$idClinica]);
        $hr = $stmt->fetch();
        $hora_inicio = $hr['hora_inicio'] ?? null;
        $hora_fim = $hr['hora_fim'] ?? null;
        $dias_atendimento = $hr['dias_atendimento'] ?? '1,2,3,4,5';
    }

    // validar dia da semana
    $dataConsulta = DateTime::createFromFormat('Y-m-d', $data);
    if (!$dataConsulta) {
        echo json_encode(['erro' => 'Data inválida']);
        exit;
    }
    $diaSemana = (int) $dataConsulta->format('N');
    $diasArr = array_map('intval', array_filter(array_map('trim', explode(',', $dias_atendimento))));
    if (!in_array($diaSemana, $diasArr, true)) {
        echo json_encode([]);
        exit;
    }

    $horarios = gerarHorariosExpediente($hora_inicio, $hora_fim);

    // remover horários passados se for hoje
    $agora = new DateTime('now');
    if ($data === $agora->format('Y-m-d')) {
        $horarios = array_values(array_filter($horarios, function ($horario) use ($data, $agora) {
            $slot = DateTime::createFromFormat('Y-m-d H:i', $data . ' ' . $horario);
            return $slot && $slot > $agora;
        }));
    }

    // buscar consultas ocupadas nesta data (API ou PDO)
    $ocupados = [];
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $start = $data . 'T00:00:00';
        $tomorrow = date('Y-m-d', strtotime($data . ' +1 day')) . 'T00:00:00';
        $path = 'tb_consulta?select=data_hora&id_clinica=eq.' . $idClinica . '&data_hora=gte.' . $start . '&data_hora=lt.' . $tomorrow . '&status=in.(Agendada,Em%20Atendimento)';
        $res2 = supabase_request('GET', $path);
        if ($res2['status'] < 200 || $res2['status'] >= 300) {
            throw new Exception('Erro ao consultar consultas (API)');
        }
        $ocupadosRaw = $res2['body'];
        foreach ($ocupadosRaw as $r) {
            if (!empty($r['data_hora'])) {
                $ocupados[] = substr($r['data_hora'], 11, 5);
            }
        }
    } else {
        if (!isset($pdo)) throw new Exception('PDO não inicializado');
        $start = $data . ' 00:00:00';
        $tomorrow = date('Y-m-d', strtotime($data . ' +1 day')) . ' 00:00:00';
        $stmt = $pdo->prepare("SELECT data_hora FROM tb_consulta WHERE id_clinica = ? AND data_hora >= ? AND data_hora < ? AND status IN ('Agendada','Em Atendimento')");
        $stmt->execute([$idClinica, $start, $tomorrow]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            if (!empty($r['data_hora'])) {
                $ocupados[] = substr($r['data_hora'], 11, 5);
            }
        }
    }
    $ocupadosFormatados = array_map(function ($h) { return substr($h, 0, 5); }, $ocupados);

    $disponiveis = array_values(array_diff($horarios, $ocupadosFormatados));

    echo json_encode($disponiveis);
} catch (Exception $e) {
    error_log('[horarios_disponiveis] ' . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno ao carregar horários']);
}
