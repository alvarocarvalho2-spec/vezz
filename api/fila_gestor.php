<?php
/**
 * API AJAX - Dados da fila para o gestor
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/supabase_api.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || $_SESSION['usuario_tipo'] !== 'gestor') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

try {
    $idClinica = (int) $_SESSION['id_clinica'];

    $today = date('Y-m-d');
    $start = $today . 'T00:00:00';
    $tomorrow = date('Y-m-d', strtotime($today . ' +1 day')) . 'T00:00:00';

    $path = "tb_consulta?select=id_consulta,data_hora,status,id_paciente,tb_paciente(nome,telefone)";
    $path .= "&id_clinica=eq.$idClinica";
    $path .= "&data_hora=gte.$start&data_hora=lt.$tomorrow";
    $path .= "&status=in.(Agendada,Em%20Atendimento)&order=data_hora.asc";

    $res = supabase_request('GET', $path);
    if ($res['status'] < 200 || $res['status'] >= 300) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao consultar Supabase']);
        exit;
    }

    $consultas = $res['body'];

    $aguardando = [];
    $atual = null;

    foreach ($consultas as $index => $c) {
        $tp = relation_first($c['tb_paciente'] ?? []);
        $item = [
            'id_consulta'   => $c['id_consulta'],
            'nome_paciente' => $tp['nome'] ?? ($c['nome_paciente'] ?? ''),
            'telefone'      => $tp['telefone'] ?? ($c['telefone_paciente'] ?? ''),
            'data_hora'     => date('d/m/Y H:i', strtotime($c['data_hora'])),
            'status'        => $c['status'],
            'posicao'       => $index + 1,
        ];

        if ($c['status'] === 'Em Atendimento') {
            $atual = $item;
        } else {
            $aguardando[] = $item;
        }
    }

    echo json_encode([
        'aguardando' => $aguardando,
        'atendimento_atual' => $atual,
        'total_aguardando' => count($aguardando),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno: ' . $e->getMessage()]);
}
