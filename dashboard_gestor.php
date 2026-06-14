<?php
$pageTitle = 'Painel do Gestor';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

checkAuth('gestor');

$idClinica = (int) $_SESSION['id_clinica'];
$buscaPaciente = trim($_GET['busca'] ?? '');

$stmt = $pdo->prepare('SELECT nome FROM tb_clinica WHERE id_clinica = ?');
$stmt->execute([$idClinica]);
$clinica = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT c.*, p.nome AS nome_paciente, p.telefone
    FROM tb_consulta c
    INNER JOIN tb_paciente p ON p.id_paciente = c.id_paciente
    WHERE c.id_clinica = ? AND DATE(c.data_hora) = CURRENT_DATE
      AND c.status IN ('Agendada', 'Em Atendimento', 'Finalizada')
    ORDER BY c.data_hora ASC
");
$stmt->execute([$idClinica]);
$consultasDia = $stmt->fetchAll();

$totalAguardando = 0;
$atendimentoAtual = null;
$proximoAtendimento = null;

foreach ($consultasDia as $c) {
    if ($c['status'] === 'Agendada') {
        $totalAguardando++;
        if ($proximoAtendimento === null) {
            $proximoAtendimento = $c;
        }
    }
    if ($c['status'] === 'Em Atendimento') {
        $atendimentoAtual = $c;
    }
}

$stmt = $pdo->prepare("
    SELECT DISTINCT p.id_paciente, p.nome, p.email, p.telefone, p.cpf,
           COUNT(c.id_consulta) AS total_consultas,
           MAX(c.data_hora) AS ultima_consulta
    FROM tb_paciente p
    INNER JOIN tb_consulta c ON c.id_paciente = p.id_paciente
    WHERE c.id_clinica = ?
    " . ($buscaPaciente !== '' ? "AND (p.nome LIKE ? OR p.email LIKE ? OR p.cpf LIKE ?)" : "") . "
    GROUP BY p.id_paciente, p.nome, p.email, p.telefone, p.cpf
    ORDER BY p.nome ASC
");
if ($buscaPaciente !== '') {
    $termo = '%' . $buscaPaciente . '%';
    $stmt->execute([$idClinica, $termo, $termo, $termo]);
} else {
    $stmt->execute([$idClinica]);
}
$pacientes = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="mb-1"><i class="fa-solid fa-gauge me-2"></i>Painel do Gestor</h2>
<p class="text-muted mb-4"><?= e($clinica['nome'] ?? 'Clínica') ?> — <?= formatarData(date('Y-m-d')) ?></p>

<div class="row mb-4">
    <div class="col-md-3 col-6 mb-3">
        <div class="card card-custom stat-card h-100">
            <div class="card-body">
                <div class="stat-number"><?= count($consultasDia) ?></div>
                <div class="stat-label">Consultas Hoje</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card card-custom stat-card h-100">
            <div class="card-body">
                <div class="stat-number text-warning"><?= $totalAguardando ?></div>
                <div class="stat-label">Aguardando</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card card-custom stat-card h-100">
            <div class="card-body">
                <div class="stat-number text-success"><?= $atendimentoAtual ? '1' : '0' ?></div>
                <div class="stat-label">Em Atendimento</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card card-custom stat-card h-100">
            <div class="card-body">
                <a href="controle_fila.php" class="btn btn-primary-custom w-100">
                    <i class="fa-solid fa-users"></i> Gerenciar Fila
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card card-custom h-100">
            <div class="card-header card-header-custom">Atendimento Atual</div>
            <div class="card-body">
                <?php if ($atendimentoAtual): ?>
                    <h5><?= e($atendimentoAtual['nome_paciente']) ?></h5>
                    <p class="mb-0 text-muted"><?= formatarDataHora($atendimentoAtual['data_hora']) ?></p>
                    <p class="mb-0"><i class="fa-solid fa-phone"></i> <?= e($atendimentoAtual['telefone']) ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">Nenhum atendimento em andamento.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card card-custom h-100">
            <div class="card-header card-header-custom">Próximo Atendimento</div>
            <div class="card-body">
                <?php if ($proximoAtendimento): ?>
                    <h5><?= e($proximoAtendimento['nome_paciente']) ?></h5>
                    <p class="mb-0 text-muted"><?= formatarDataHora($proximoAtendimento['data_hora']) ?></p>
                    <p class="mb-0"><i class="fa-solid fa-phone"></i> <?= e($proximoAtendimento['telefone']) ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">Nenhum paciente aguardando.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card card-custom mb-4">
    <div class="card-header card-header-custom">Atendimentos do Dia</div>
    <div class="card-body">
        <?php if (empty($consultasDia)): ?>
            <p class="text-muted mb-0">Nenhuma consulta para hoje.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle table-vezz">
                    <thead>
                        <tr>
                            <th>Horário</th>
                            <th>Paciente</th>
                            <th>Telefone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultasDia as $c): ?>
                        <tr>
                            <td><?= formatarDataHora($c['data_hora']) ?></td>
                            <td><?= e($c['nome_paciente']) ?></td>
                            <td><?= e($c['telefone']) ?></td>
                            <td><span class="badge <?= badgeStatus($c['status']) ?>"><?= e($c['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Pacientes da Clínica</span>
        <form method="GET" class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm" name="busca"
                   value="<?= e($buscaPaciente) ?>" placeholder="Buscar por nome, e-mail ou CPF">
            <button type="submit" class="btn btn-light btn-sm">Buscar</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($pacientes)): ?>
            <p class="text-muted mb-0">Nenhum paciente com consultas nesta clínica<?= $buscaPaciente !== '' ? ' para esta busca' : '' ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle table-vezz">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Consultas</th>
                            <th>Última Consulta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pacientes as $p): ?>
                        <tr>
                            <td><?= e($p['nome']) ?></td>
                            <td><?= e($p['cpf']) ?></td>
                            <td><?= e($p['email']) ?></td>
                            <td><?= e($p['telefone']) ?></td>
                            <td><?= (int) $p['total_consultas'] ?></td>
                            <td><?= formatarDataHora($p['ultima_consulta']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
