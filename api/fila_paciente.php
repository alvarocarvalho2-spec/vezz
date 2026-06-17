<?php
/**
 * API AJAX - Posição na fila para o paciente
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/supabase_api.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || $_SESSION['usuario_tipo'] !== 'paciente') {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

try {
    $idPaciente = (int) $_SESSION['usuario_id'];
    $today = date('Y-m-d');
    $start = $today . 'T00:00:00';
    $tomorrow = date('Y-m-d', strtotime($today . ' +1 day')) . 'T00:00:00';

    // buscar última consulta do paciente hoje
    $path = "tb_consulta?select=id_consulta,data_hora,status,id_clinica&id_paciente=eq.$idPaciente";
    $path .= "&data_hora=gte.$start&data_hora=lt.$tomorrow";
    $path .= "&status=in.(Agendada,Em%20Atendimento,Finalizada)&order=data_hora.desc&limit=1";

    $res = supabase_request('GET', $path);
    if ($res['status'] < 200 || $res['status'] >= 300) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao consultar Supabase']);
        exit;
    }

    $consulta = $res['body'][0] ?? null;
    if (!$consulta) {
        echo json_encode(['tem_consulta' => false, 'mensagem' => 'Você não possui consulta agendada para hoje.']);
        exit;
    }

    $response = [
        'tem_consulta' => true,
        'nome_clinica' => '',
        'data_hora' => date('d/m/Y H:i', strtotime($consulta['data_hora'])),
        'status' => $consulta['status'],
        'id_consulta' => $consulta['id_consulta'],
    ];

    // buscar nome da clinica
    try {
        $resC = supabase_request('GET', 'tb_clinica?select=nome&id_clinica=eq.' . (int)$consulta['id_clinica']);
        if (!empty($resC['body'][0]['nome'])) $response['nome_clinica'] = $resC['body'][0]['nome'];
    } catch (Exception $e) {
        // ignore
    }

    // calcular posição se aplicável
    if (in_array($consulta['status'], ['Agendada', 'Em Atendimento'], true)) {
        $idClinica = (int) $consulta['id_clinica'];
        $path2 = "tb_consulta?select=id_consulta,status&";
        $path2 .= "id_clinica=eq.$idClinica&data_hora=gte.$start&data_hora=lt.$tomorrow";
        $path2 .= "&status=in.(Agendada,Em%20Atendimento)&order=data_hora.asc";
        $res2 = supabase_request('GET', $path2);
        $consultas = $res2['body'];
        $posicao = 0;
        foreach ($consultas as $index => $c) {
            if ((int) $c['id_consulta'] === (int) $consulta['id_consulta']) {
                $posicao = $index + 1;
                break;
            }
        }
        $response['posicao'] = $posicao;
        $pacientesAFrente = max(0, $posicao - 1);
        if ($consulta['status'] === 'Agendada') {
            $response['status'] = 'Aguardando';
            $response['tempo_estimado'] = ($posicao > 0) ? ($pacientesAFrente * MINUTOS_POR_POSICAO) . ' minuto(s)' : '-';
            $response['pacientes_a_frente'] = $pacientesAFrente;
        } elseif ($consulta['status'] === 'Em Atendimento') {
            $response['tempo_estimado'] = 'Em atendimento';
            $response['pacientes_a_frente'] = 0;
        }
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno: ' . $e->getMessage()]);
}
