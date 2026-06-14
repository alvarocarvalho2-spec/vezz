<?php
$pageTitle = 'Acompanhar Fila';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

checkAuth('paciente');

$dados = obterDadosFilaPaciente($pdo, (int) $_SESSION['usuario_id']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h2 class="mb-0"><i class="fa-solid fa-users me-2"></i>Acompanhamento da Fila</h2>
    <span class="text-muted small"><i class="fa-solid fa-arrows-rotate"></i> Atualização automática a cada 5 segundos</span>
</div>

<div class="card card-custom">
    <div class="card-header card-header-custom">Sua Posição na Fila</div>
    <div class="card-body" id="fila-paciente-dados">
        <?php if (!$dados['tem_consulta']): ?>
            <div class="alert alert-info mb-0">
                <i class="fa-solid fa-circle-info"></i> <?= e($dados['mensagem']) ?>
            </div>
        <?php else: ?>
            <div class="text-center mb-4">
                <p class="text-muted mb-1"><?= e($dados['nome_clinica']) ?></p>
                <p class="mb-3"><?= e($dados['data_hora']) ?></p>
                <span class="badge <?= badgeStatus($dados['status']) ?> fs-6"><?= e($dados['status']) ?></span>
            </div>
            <div class="row text-center g-4">
                <div class="col-md-4">
                    <div class="stat-card card card-custom h-100">
                        <div class="card-body">
                            <div class="queue-position"><?= $dados['posicao'] ?: '-' ?></div>
                            <div class="stat-label">Sua Posição</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card card card-custom h-100">
                        <div class="card-body">
                            <div class="stat-number"><?= (int) $dados['pacientes_a_frente'] ?></div>
                            <div class="stat-label">Pacientes à Frente</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card card card-custom h-100">
                        <div class="card-body">
                            <div class="stat-number stat-number-sm"><?= e($dados['tempo_estimado']) ?></div>
                            <div class="stat-label">Tempo Estimado</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3">
    <a href="dashboard_paciente.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left"></i> Voltar ao Painel
    </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
