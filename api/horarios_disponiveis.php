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
    $horarios = obterHorariosDisponiveis(
        $pdo,
        $idClinica,
        $data,
        $idConsulta > 0 ? $idConsulta : null
    );

    if (!is_array($horarios)) {
        echo json_encode(['erro' => 'Resposta inválida do servidor']);
        exit;
    }

    echo json_encode($horarios);
} catch (Exception $e) {
    error_log('[horarios_disponiveis] ' . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno ao carregar horários']);
}
