<?php
$pageTitle = 'Controle de Fila';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

checkAuth('gestor');

$idClinica = (int) $_SESSION['id_clinica'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $idConsulta = (int) ($_POST['id_consulta'] ?? 0);

    try {
        switch ($acao) {
            case 'iniciar':
                iniciarAtendimento($pdo, $idConsulta, $idClinica);
                setFlash('success', 'Atendimento iniciado.');
                break;
            case 'finalizar':
                finalizarAtendimento($pdo, $idConsulta, $idClinica);
                setFlash('success', 'Atendimento finalizado.');
                break;
            case 'proximo':
                chamarProximoPaciente($pdo, $idClinica);
                setFlash('success', 'Próximo paciente chamado.');
                break;
            default:
                setFlash('danger', 'Ação inválida.');
        }
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }

    header('Location: controle_fila.php');
    exit;
}

$dadosFila = obterDadosFilaGestor($pdo, $idClinica);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h2 class="mb-0"><i class="fa-solid fa-users me-2"></i>Controle de Fila</h2>
    <div>
        <span class="badge badge-posicao fs-6 me-2">
            Aguardando: <span id="total-aguardando"><?= $dadosFila['total_aguardando'] ?></span>
        </span>
        <span class="text-muted small"><i class="fa-solid fa-arrows-rotate"></i> Atualiza a cada 5s</span>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                <span>Atendimento Atual</span>
            </div>
            <div class="card-body" id="fila-atendimento-atual">
                <?php if ($dadosFila['atendimento_atual']): ?>
                    <?php $a = $dadosFila['atendimento_atual']; ?>
                    <div class="queue-item em-atendimento">
                        <h5 class="text-success mb-2"><i class="fa-solid fa-user-doctor"></i> Em Atendimento</h5>
                        <p class="mb-1"><strong><?= e($a['nome_paciente']) ?></strong></p>
                        <p class="mb-2 text-muted"><?= e($a['data_hora'] ?? '') ?> | <?= e($a['telefone'] ?? '') ?></p>
                        <form method="POST" action="controle_fila.php" class="d-inline">
                            <input type="hidden" name="acao" value="finalizar">
                            <input type="hidden" name="id_consulta" value="<?= (int) $a['id_consulta'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fa-solid fa-circle-check"></i> Finalizar Atendimento
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Nenhum atendimento em andamento.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header card-header-custom">Ações Rápidas</div>
            <div class="card-body d-grid gap-2">
                <form method="POST" action="controle_fila.php">
                    <input type="hidden" name="acao" value="proximo">
                    <button type="submit" class="btn btn-success-custom btn-lg w-100"
                            <?= $dadosFila['atendimento_atual'] ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-bullhorn"></i> Chamar Próximo Paciente
                    </button>
                </form>
                <a href="dashboard_gestor.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Voltar ao Painel
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header card-header-custom">
        Pacientes em Espera (consultas do dia — status Agendada)
    </div>
    <div class="card-body" id="fila-aguardando">
        <?php if (empty($dadosFila['aguardando'])): ?>
            <p class="text-muted mb-0">Nenhum paciente aguardando.</p>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($dadosFila['aguardando'] as $item): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="badge badge-posicao me-2">#<?= (int) $item['posicao'] ?></span>
                        <strong><?= e($item['nome_paciente']) ?></strong>
                        <br><small class="text-muted"><?= e($item['data_hora'] ?? '') ?> | <?= e($item['telefone'] ?? '') ?></small>
                    </div>
                    <form method="POST" action="controle_fila.php" class="d-inline">
                        <input type="hidden" name="acao" value="iniciar">
                        <input type="hidden" name="id_consulta" value="<?= (int) $item['id_consulta'] ?>">
                        <button type="submit" class="btn btn-success-custom btn-sm">
                            <i class="fa-solid fa-play"></i> Iniciar Atendimento
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
