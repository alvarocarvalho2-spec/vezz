<?php
$pageTitle = 'Detalhes da Clínica';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$idClinica = (int) ($_GET['id'] ?? 0);

if ($idClinica <= 0) {
    setFlash('danger', 'Clínica não informada.');
    header('Location: pesquisar_clinicas.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*, e.rua, e.numero, e.bairro, e.cidade, e.cep
    FROM tb_clinica c
    INNER JOIN tb_endereco e ON e.id_clinica = c.id_clinica
    WHERE c.id_clinica = ?
");
$stmt->execute([$idClinica]);
$clinica = $stmt->fetch();

if (!$clinica) {
    setFlash('danger', 'Clínica não encontrada.');
    header('Location: pesquisar_clinicas.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="pesquisar_clinicas.php">Clínicas</a></li>
        <li class="breadcrumb-item active"><?= e($clinica['nome']) ?></li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-custom">
            <div class="card-header card-header-custom">
                <h4 class="mb-0"><i class="fa-solid fa-hospital me-2"></i><?= e($clinica['nome']) ?></h4>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted">Endereço</h6>
                        <p class="mb-0">
                            <?= e($clinica['rua']) ?>, <?= e($clinica['numero']) ?><br>
                            <?= e($clinica['bairro']) ?> - <?= e($clinica['cidade']) ?><br>
                            CEP: <?= e($clinica['cep']) ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted">Contato</h6>
                        <p class="mb-0">
                            <i class="fa-solid fa-phone"></i> <?= e($clinica['telefone']) ?><br>
                            <i class="fa-solid fa-building"></i> CNPJ: <?= e($clinica['cnpj']) ?>
                        </p>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="text-muted">Descrição</h6>
                    <p><?= e($clinica['descricao'] ?: 'Sem descrição disponível.') ?></p>
                </div>

                <div class="mb-4">
                    <h6 class="text-muted">Horários Disponíveis</h6>
                    <p class="mb-0">
                        <i class="fa-solid fa-clock text-primary"></i>
                        <?php
                        $horaIni = isset($clinica['hora_inicio']) ? substr($clinica['hora_inicio'],0,5) : '08:00';
                        $horaFim = isset($clinica['hora_fim']) ? substr($clinica['hora_fim'],0,5) : '18:00';
                        $dias = isset($clinica['dias_atendimento']) ? $clinica['dias_atendimento'] : '1,2,3,4,5';
                        $diasMap = ['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','7'=>'Dom'];
                        $diasParts = array_filter(array_map('trim', explode(',', $dias)));
                        $diasLabels = array_map(function($d) use ($diasMap){ return $diasMap[$d] ?? $d; }, $diasParts);
                        echo e(implode(', ', $diasLabels) . " — " . $horaIni . ' às ' . $horaFim);
                        ?>
                    </p>
                </div>

                <?php if (isLoggedIn() && $_SESSION['usuario_tipo'] === 'paciente'): ?>
                    <a href="agendamento.php?id_clinica=<?= $idClinica ?>" class="btn btn-success-custom btn-lg">
                        <i class="fa-solid fa-calendar-plus"></i> Agendar Consulta
                    </a>
                <?php elseif (!isLoggedIn()): ?>
                    <div class="alert alert-info">
                        <a href="login.php">Faça login</a> para agendar uma consulta nesta clínica.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
