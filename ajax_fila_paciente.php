<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

if (!isLoggedIn() || getUserType() !== 'paciente') {
    echo '<div class="alert alert-danger alert-vezz">Sessão expirada.</div>';
    exit;
}

$id_paciente = getUserId();
$id_consulta = isset($_GET['id_consulta']) ? (int)$_GET['id_consulta'] : 0;

if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    $today = date('Y-m-d');
    $path = 'tb_consulta?select=*,tb_clinica(nome)&id_consulta=eq.' . rawurlencode($id_consulta) . '&id_paciente=eq.' . rawurlencode($id_paciente) . '&' . supabase_day_range_query($today) . '&limit=1';
    $res = supabase_request('GET', $path);
    $consulta = null;
    if ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) {
        $consulta = $res['body'][0];
        $rc = relation_first($consulta['tb_clinica'] ?? []);
        $consulta['clinica_nome'] = $rc['nome'] ?? null;
    }
    if (!$consulta) {
        echo '<div class="alert alert-warning alert-vezz">Consulta não encontrada.</div>';
        exit;
    }
    $posicao = calcularPosicaoFila($pdo, (int)$consulta['id_clinica'], $id_consulta);
    $tempoEstimado = obterTempoEstimado($posicao);
} else {
    $stmt = $pdo->prepare("SELECT c.*, cl.nome AS clinica_nome
                       FROM tb_consulta c
                       JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica
                                             WHERE c.id_consulta = :id
                                                 AND c.id_paciente = :id_paciente
                                                 AND DATE(c.data_hora) = CURRENT_DATE
                       LIMIT 1");
    $stmt->execute([':id' => $id_consulta, ':id_paciente' => $id_paciente]);
    $consulta = $stmt->fetch();

    if (!$consulta) {
        echo '<div class="alert alert-warning alert-vezz">Consulta não encontrada.</div>';
        exit;
    }

    $posicao = calcularPosicaoFila($pdo, (int)$consulta['id_clinica'], $id_consulta);
    $tempoEstimado = obterTempoEstimado($posicao);
}

?>
<p class="text-vezz-secondary mb-1">Horário agendado</p>
<h4 class="mb-4 fw-bold"><?= formatarHora($consulta['data_hora']) ?></h4>

<hr>

<p class="text-vezz-secondary mb-1">Sua posição na fila</p>
<div class="posicao-atual-vezz mb-2">
    <?php if ($consulta['status'] === 'Em Atendimento'): ?>
        <span class="text-vezz-success"><i class="fa-solid fa-user-doctor me-2"></i>Em Atendimento</span>
    <?php elseif ($consulta['status'] === 'Finalizada'): ?>
        <span class="text-dark"><i class="fa-solid fa-circle-check me-2"></i>Finalizado</span>
    <?php else: ?>
        <?= (int)$posicao ?>º
    <?php endif; ?>
</div>

<p class="text-vezz-secondary mb-1">Tempo estimado de espera</p>
<h5 class="tempo-estimado-vezz mb-4">
    <?php if ($consulta['status'] === 'Agendada'): ?>
        <?= htmlspecialchars($tempoEstimado) ?>
    <?php elseif ($consulta['status'] === 'Em Atendimento'): ?>
        Você está sendo atendido agora
    <?php else: ?>
        —
    <?php endif; ?>
</h5>

<div class="alert alert-info alert-vezz">
    <i class="fa-solid fa-circle-info me-2"></i>Atualizações automáticas a cada 5 segundos.
</div>

<a href="dashboard_paciente.php" class="btn btn-vezz-outline">
    <i class="fa-solid fa-arrow-left me-1"></i>Voltar ao Painel
</a>
