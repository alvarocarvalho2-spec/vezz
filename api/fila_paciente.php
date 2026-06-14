<?php
/**
 * API AJAX - Posição na fila para o paciente
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || $_SESSION['usuario_tipo'] !== 'paciente') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

echo json_encode(obterDadosFilaPaciente($pdo, (int) $_SESSION['usuario_id']));
