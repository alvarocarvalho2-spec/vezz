<?php
$pageTitle = 'Meu Painel';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

checkAuth('paciente');

$idPaciente = (int) $_SESSION['usuario_id'];

// Cancelamento de consulta (RF008)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cancelar') {
    $idConsulta = (int) ($_POST['id_consulta'] ?? 0);

    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        // Buscar consulta e validar condição em PHP (evita usar CURRENT_TIMESTAMP no PostgREST)
        $res = supabase_request('GET', 'tb_consulta?select=id_consulta,id_clinica,data_hora,status&id_consulta=eq.' . rawurlencode($idConsulta) . '&id_paciente=eq.' . rawurlencode($idPaciente) . '&limit=1');
        $ok = false;
        if ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) {
            $row = $res['body'][0];
            if (($row['status'] ?? '') === 'Agendada' && (!empty($row['data_hora']) && strtotime($row['data_hora']) > time())) {
                try {
                    supabase_update('tb_consulta', 'id_consulta=eq.' . rawurlencode($idConsulta), ['status' => 'Cancelada']);
                    setFlash('success', 'Consulta cancelada com sucesso.');
                    $ok = true;
                } catch (Exception $e) {
                    error_log('supabase_update cancelamento error: ' . $e->getMessage());
                    setFlash('danger', 'Erro ao cancelar a consulta (API).');
                }
            }
        }
        if (!$ok) {
            setFlash('danger', 'Não foi possível cancelar esta consulta.');
        }
        header('Location: dashboard_paciente.php');
        exit;
    }

    // fallback PDO
    $stmt = $pdo->prepare("\n        SELECT id_consulta FROM tb_consulta\n        WHERE id_consulta = ? AND id_paciente = ? AND status = 'Agendada' AND data_hora > CURRENT_TIMESTAMP\n    ");
    $stmt->execute([$idConsulta, $idPaciente]);

    if ($stmt->fetch()) {
        $upd = $pdo->prepare("UPDATE tb_consulta SET status = 'Cancelada' WHERE id_consulta = ?");
        $upd->execute([$idConsulta]);
        setFlash('success', 'Consulta cancelada com sucesso.');
    } else {
        setFlash('danger', 'Não foi possível cancelar esta consulta.');
    }
    header('Location: dashboard_paciente.php');
    exit;
}

// Próxima consulta futura
$proximaConsulta = null;
if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    $res = supabase_request('GET', 'tb_consulta?select=*,tb_clinica(nome)&id_paciente=eq.' . rawurlencode($idPaciente) . '&status=eq.Agendada&data_hora=gte.' . rawurlencode(date('Y-m-d\TH:i:s')) . '&order=data_hora.asc&limit=1');
    if ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) {
        $proximaConsulta = $res['body'][0];
        $rc = relation_first($proximaConsulta['tb_clinica'] ?? []);
        $proximaConsulta['nome_clinica'] = $rc['nome'] ?? null;
    }
} else {
    $stmt = $pdo->prepare("\n    SELECT c.*, cl.nome AS nome_clinica\n    FROM tb_consulta c\n    INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica\n    WHERE c.id_paciente = ? AND c.status = 'Agendada' AND c.data_hora >= CURRENT_TIMESTAMP\n    ORDER BY c.data_hora ASC\n    LIMIT 1\n");
    $stmt->execute([$idPaciente]);
    $proximaConsulta = $stmt->fetch();
}

// Consulta de hoje para fila
$consultaHoje = null;
if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    $day = date('Y-m-d');
    $path = 'tb_consulta?select=*,tb_clinica(nome)&id_paciente=eq.' . rawurlencode($idPaciente) . '&' . supabase_day_range_query($day) . '&status=in.(Agendada,Em%20Atendimento)&order=data_hora.asc&limit=1';
    $res = supabase_request('GET', $path);
    if ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) {
        $consultaHoje = $res['body'][0];
        $rc = relation_first($consultaHoje['tb_clinica'] ?? []);
        $consultaHoje['nome_clinica'] = $rc['nome'] ?? null;
    }
} else {
    $stmt = $pdo->prepare("\n    SELECT c.*, cl.nome AS nome_clinica\n    FROM tb_consulta c\n    INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica\n    WHERE c.id_paciente = ? AND DATE(c.data_hora) = CURRENT_DATE\n      AND c.status IN ('Agendada', 'Em Atendimento')\n    ORDER BY c.data_hora ASC\n    LIMIT 1\n");
    $stmt->execute([$idPaciente]);
    $consultaHoje = $stmt->fetch();
}

$posicaoFila = 0;
$tempoEstimado = '-';
if ($consultaHoje) {
    $posicaoFila = calcularPosicaoFila($pdo, $consultaHoje['id_clinica'], $consultaHoje['id_consulta']);
    $tempoEstimado = obterTempoEstimado(max(0, $posicaoFila - 1));
}

// Consultas futuras (RF010)
$consultasFuturas = [];
if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    $res = supabase_request('GET', 'tb_consulta?select=*,tb_clinica(nome)&id_paciente=eq.' . rawurlencode($idPaciente) . '&status=eq.Agendada&data_hora=gte.' . rawurlencode(date('Y-m-d\TH:i:s')) . '&order=data_hora.asc');
    if ($res['status'] >= 200 && is_array($res['body'])) {
        $consultasFuturas = $res['body'];
        foreach ($consultasFuturas as &$c) {
            $rc = relation_first($c['tb_clinica'] ?? []);
            $c['nome_clinica'] = $rc['nome'] ?? null;
        }
        unset($c);
    }
} else {
    $stmt = $pdo->prepare("\n    SELECT c.*, cl.nome AS nome_clinica\n    FROM tb_consulta c\n    INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica\n    WHERE c.id_paciente = ? AND c.status = 'Agendada' AND c.data_hora >= CURRENT_TIMESTAMP\n    ORDER BY c.data_hora ASC\n");
    $stmt->execute([$idPaciente]);
    $consultasFuturas = $stmt->fetchAll();
}

// Histórico (RF011) — consultas finalizadas
$historico = [];
if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    $res = supabase_request('GET', 'tb_consulta?select=*,tb_clinica(nome)&id_paciente=eq.' . rawurlencode($idPaciente) . '&status=eq.Finalizada&order=data_hora.desc&limit=50');
    if ($res['status'] >= 200 && is_array($res['body'])) {
        $historico = $res['body'];
        foreach ($historico as &$h) {
            $rc = relation_first($h['tb_clinica'] ?? []);
            $h['nome_clinica'] = $rc['nome'] ?? null;
        }
        unset($h);
    }
} else {
// Enriquecer nomes de clínica em lote caso relacionamento `tb_clinica` não venha populado
$missingClinicIds = [];
$collectFrom = array_merge($consultasFuturas, $historico);
foreach ($collectFrom as $it) {
    if (empty($it['nome_clinica']) && !empty($it['id_clinica'])) {
        $missingClinicIds[] = (int)$it['id_clinica'];
    }
}
if (!empty($proximaConsulta) && empty($proximaConsulta['nome_clinica']) && !empty($proximaConsulta['id_clinica'])) {
    $missingClinicIds[] = (int)$proximaConsulta['id_clinica'];
}
if (!empty($consultaHoje) && empty($consultaHoje['nome_clinica']) && !empty($consultaHoje['id_clinica'])) {
    $missingClinicIds[] = (int)$consultaHoje['id_clinica'];
}
$missingClinicIds = array_values(array_unique($missingClinicIds));
if (!empty($missingClinicIds) && defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    try {
        $in = implode(',', $missingClinicIds);
        $r = supabase_request('GET', 'tb_clinica?select=id_clinica,nome&id_clinica=in.(' . rawurlencode($in) . ')&limit=100');
        if ($r['status'] >= 200 && is_array($r['body']) && count($r['body']) > 0) {
            $byId = [];
            foreach ($r['body'] as $rc) {
                $byId[$rc['id_clinica']] = $rc['nome'] ?? null;
            }
            // aplicar aos arrays
            foreach ($consultasFuturas as &$c) {
                if (empty($c['nome_clinica']) && !empty($c['id_clinica']) && isset($byId[$c['id_clinica']])) {
                    $c['nome_clinica'] = $byId[$c['id_clinica']];
                }
            }
            unset($c);
            foreach ($historico as &$h) {
                if (empty($h['nome_clinica']) && !empty($h['id_clinica']) && isset($byId[$h['id_clinica']])) {
                    $h['nome_clinica'] = $byId[$h['id_clinica']];
                }
            }
            unset($h);
            if (!empty($proximaConsulta) && empty($proximaConsulta['nome_clinica']) && !empty($proximaConsulta['id_clinica']) && isset($byId[$proximaConsulta['id_clinica']])) {
                $proximaConsulta['nome_clinica'] = $byId[$proximaConsulta['id_clinica']];
            }
            if (!empty($consultaHoje) && empty($consultaHoje['nome_clinica']) && !empty($consultaHoje['id_clinica']) && isset($byId[$consultaHoje['id_clinica']])) {
                $consultaHoje['nome_clinica'] = $byId[$consultaHoje['id_clinica']];
            }
        }
    } catch (Exception $e) {
        // silencioso
    }
}
// Log quando nomes de clínica estiverem faltando após tentativa de enriquecimento
$logMissing = [];
foreach (array_merge($consultasFuturas, $historico) as $it) {
    if (empty($it['nome_clinica']) && !empty($it['id_clinica'])) {
        $logMissing[] = (int)$it['id_clinica'];
    }
}
if (!empty($proximaConsulta) && empty($proximaConsulta['nome_clinica']) && !empty($proximaConsulta['id_clinica'])) $logMissing[] = (int)$proximaConsulta['id_clinica'];
if (!empty($consultaHoje) && empty($consultaHoje['nome_clinica']) && !empty($consultaHoje['id_clinica'])) $logMissing[] = (int)$consultaHoje['id_clinica'];
$logMissing = array_values(array_unique($logMissing));
if (!empty($logMissing)) {
    $logDir = __DIR__ . '/logs'; if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/debug_supabase.log';
    $entry = ['ts' => date('c'), 'file' => 'dashboard_paciente.php', 'missing_ids' => $logMissing];
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}
    $stmt = $pdo->prepare("\n    SELECT c.*, cl.nome AS nome_clinica\n    FROM tb_consulta c\n    INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica\n    WHERE c.id_paciente = ? AND c.status = 'Finalizada'\n    ORDER BY c.data_hora DESC\n    LIMIT 50\n");
    $stmt->execute([$idPaciente]);
    $historico = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="mb-4"><i class="fa-solid fa-gauge me-2"></i>Dashboard do Paciente</h2>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card card-custom h-100">
            <div class="card-header card-header-custom">Próxima Consulta</div>
            <div class="card-body">
                <?php if ($proximaConsulta): ?>
                    <h5><?= e($proximaConsulta['nome_clinica']) ?></h5>
                    <p class="mb-0"><i class="fa-solid fa-calendar"></i> <?= formatarDataHora($proximaConsulta['data_hora']) ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">Nenhuma consulta agendada.</p>
                    <a href="pesquisar_clinicas.php" class="btn btn-primary-custom btn-sm mt-2">Agendar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-custom h-100">
            <div class="card-header card-header-custom">Fila Hoje</div>
            <div class="card-body text-center" id="dashboard-fila-paciente">
                <?php if ($consultaHoje): ?>
                    <div class="stat-number" id="dash-posicao"><?= $posicaoFila ?></div>
                    <p class="text-muted mb-2">Posição na fila</p>
                    <p class="small mb-0">Tempo estimado: <strong id="dash-tempo"><?= e($tempoEstimado) ?></strong></p>
                    <p class="small mb-0 mt-1">
                        Status: <span class="badge <?= badgeStatus($consultaHoje['status']) ?>" id="dash-status"><?= e($consultaHoje['status']) ?></span>
                    </p>
                    <a href="acompanhamento_fila.php" class="btn btn-outline-primary btn-sm mt-2">
                        Acompanhar em tempo real
                    </a>
                <?php else: ?>
                    <p class="text-muted mb-0">Sem consulta hoje na fila.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-custom h-100">
            <div class="card-header card-header-custom">Ações Rápidas</div>
            <div class="card-body d-grid gap-2">
                <a href="pesquisar_clinicas.php" class="btn btn-primary-custom btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar Clínicas
                </a>
                <?php if ($consultaHoje): ?>
                <a href="acompanhamento_fila.php" class="btn btn-success-custom btn-sm">
                    <i class="fa-solid fa-users"></i> Acompanhar Fila
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card card-custom mb-4">
    <div class="card-header card-header-custom">Minhas Consultas Agendadas</div>
    <div class="card-body">
        <?php if (empty($consultasFuturas)): ?>
            <p class="text-muted mb-0">Você não possui consultas agendadas.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle table-vezz">
                    <thead>
                        <tr>
                            <th>Clínica</th>
                            <th>Data/Hora</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultasFuturas as $c): ?>
                        <tr>
                            <td><?= e($c['nome_clinica']) ?></td>
                            <td><?= formatarDataHora($c['data_hora']) ?></td>
                            <td><span class="badge <?= badgeStatus($c['status']) ?>"><?= e($c['status']) ?></span></td>
                            <td>
                                <a href="agendamento.php?reagendar=<?= (int) $c['id_consulta'] ?>"
                                   class="btn btn-outline-primary btn-sm">Reagendar</a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Cancelar esta consulta?')">
                                    <input type="hidden" name="acao" value="cancelar">
                                    <input type="hidden" name="id_consulta" value="<?= (int) $c['id_consulta'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Cancelar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header card-header-custom">Histórico de Consultas Realizadas</div>
    <div class="card-body">
        <?php if (empty($historico)): ?>
            <p class="text-muted mb-0">Nenhum histórico disponível.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle table-vezz">
                    <thead>
                        <tr>
                            <th>Clínica</th>
                            <th>Data/Hora</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $h): ?>
                        <tr>
                            <td><?= e($h['nome_clinica']) ?></td>
                            <td><?= formatarDataHora($h['data_hora']) ?></td>
                            <td><span class="badge <?= badgeStatus($h['status']) ?>"><?= e($h['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
