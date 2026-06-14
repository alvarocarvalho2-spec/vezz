<?php
/**
 * API AJAX - Dados da fila para o gestor
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || $_SESSION['usuario_tipo'] !== 'gestor') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

$idClinica = (int) $_SESSION['id_clinica'];
echo json_encode(obterDadosFilaGestor($pdo, $idClinica));
