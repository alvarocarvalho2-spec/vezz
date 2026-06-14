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

    $stmt = $pdo->prepare("
        SELECT id_consulta FROM tb_consulta
        WHERE id_consulta = ? AND id_paciente = ? AND status = 'Agendada' AND data_hora > CURRENT_TIMESTAMP
    ");
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
$stmt = $pdo->prepare("
    SELECT c.*, cl.nome AS nome_clinica
    FROM tb_consulta c
    INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica
    WHERE c.id_paciente = ? AND c.status = 'Agendada' AND c.data_hora >= CURRENT_TIMESTAMP
    ORDER BY c.data_hora ASC
    LIMIT 1
");
$stmt->execute([$idPaciente]);
$proximaConsulta = $stmt->fetch();

// Consulta de hoje para fila
$stmt = $pdo->prepare("
    SELECT c.*, cl.nome AS nome_clinica
    FROM tb_consulta c
    INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica
    WHERE c.id_paciente = ? AND DATE(c.data_hora) = CURRENT_DATE
      AND c.status IN ('Agendada', 'Em Atendimento')
    ORDER BY c.data_hora ASC
    LIMIT 1
");
$stmt->execute([$idPaciente]);
$consultaHoje = $stmt->fetch();

$posicaoFila = 0;
$tempoEstimado = '-';
if ($consultaHoje) {
    $posicaoFila = calcularPosicaoFila($pdo, $consultaHoje['id_clinica'], $consultaHoje['id_consulta']);
    $tempoEstimado = obterTempoEstimado(max(0, $posicaoFila - 1));
}

// Consultas futuras (RF010)
$stmt = $pdo->prepare("
    SELECT c.*, cl.nome AS nome_clinica
    FROM tb_consulta c
    INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica
    WHERE c.id_paciente = ? AND c.status = 'Agendada' AND c.data_hora >= CURRENT_TIMESTAMP
    ORDER BY c.data_hora ASC
");
$stmt->execute([$idPaciente]);
$consultasFuturas = $stmt->fetchAll();

// Histórico (RF011) — consultas finalizadas
$stmt = $pdo->prepare("
    SELECT c.*, cl.nome AS nome_clinica
    FROM tb_consulta c
    INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica
    WHERE c.id_paciente = ? AND c.status = 'Finalizada'
    ORDER BY c.data_hora DESC
    LIMIT 50
");
$stmt->execute([$idPaciente]);
$historico = $stmt->fetchAll();

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
