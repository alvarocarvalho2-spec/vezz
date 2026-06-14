<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

if (!isLoggedIn() || getUserType() !== 'gestor') {
    echo '<div class="alert alert-danger alert-vezz">Sessão expirada.</div>';
    exit;
}

$id_clinica = getClinicaId();

$stmt = $pdo->prepare("SELECT c.*, p.nome AS paciente_nome
                       FROM tb_consulta c
                       JOIN tb_paciente p ON p.id_paciente = c.id_paciente
                                             WHERE c.id_clinica = :id_clinica
                                                 AND DATE(c.data_hora) = CURRENT_DATE
                         AND c.status = 'Em Atendimento'
                       ORDER BY c.data_hora ASC
                       LIMIT 1");
$stmt->execute([':id_clinica' => $id_clinica]);
$atual = $stmt->fetch();

$stmt = $pdo->prepare("SELECT c.*, p.nome AS paciente_nome
                       FROM tb_consulta c
                       JOIN tb_paciente p ON p.id_paciente = c.id_paciente
                                             WHERE c.id_clinica = :id_clinica
                                                 AND DATE(c.data_hora) = CURRENT_DATE
                         AND c.status = 'Agendada'
                       ORDER BY c.data_hora ASC");
$stmt->execute([':id_clinica' => $id_clinica]);
$aguardando = $stmt->fetchAll();

ob_start();
?>

<?php if ($atual): ?>
    <div class="fila-item-vezz atendimento mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="mb-1 fw-bold"><i class="fa-solid fa-user-doctor me-2"></i><?= htmlspecialchars($atual['paciente_nome']) ?></h5>
                <p class="mb-0 text-vezz-secondary">Horário agendado: <?= formatarHora($atual['data_hora']) ?> — <span class="badge badge-vezz badge-atendimento">Em Atendimento</span></p>
            </div>
            <a href="controle_fila.php?finalizar=<?= (int)$atual['id_consulta'] ?>" class="btn btn-vezz-danger mt-2 mt-md-0"
               onclick="return confirmarAcao('Finalizar este atendimento?')">
                <i class="fa-solid fa-circle-check me-1"></i>Finalizar Atendimento
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info alert-vezz mb-4">
        <i class="fa-solid fa-circle-info me-2"></i>Nenhum atendimento em andamento.
    </div>
<?php endif; ?>

<h5 class="mb-3 fw-bold"><i class="fa-solid fa-hourglass-half me-2 text-vezz-primary"></i>Pacientes Aguardando</h5>

<?php if (empty($aguardando)): ?>
    <div class="alert alert-light alert-vezz border">Nenhum paciente aguardando no momento.</div>
<?php else: ?>
    <?php $pos = 1; foreach ($aguardando as $a): ?>
        <div class="fila-item-vezz">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <span class="badge badge-vezz badge-agendada me-2"><?= (int)$pos ?>º</span>
                    <strong class="fw-bold"><?= htmlspecialchars($a['paciente_nome']) ?></strong>
                    <span class="text-vezz-secondary ms-2"><i class="fa-regular fa-clock me-1"></i><?= formatarHora($a['data_hora']) ?></span>
                </div>
                <a href="controle_fila.php?iniciar=<?= (int)$a['id_consulta'] ?>" class="btn btn-vezz-success btn-sm mt-2 mt-md-0">
                    <i class="fa-solid fa-circle-play me-1"></i>Iniciar Atendimento
                </a>
            </div>
        </div>
    <?php $pos++; endforeach; ?>
<?php endif; ?>

<?php ob_end_flush(); ?>
