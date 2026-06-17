<?php
$pageTitle = 'Painel do Gestor';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

checkAuth('gestor');

$idClinica = (int) $_SESSION['id_clinica'];
$buscaPaciente = trim($_GET['busca'] ?? '');


// Valores padrão — serão carregados pelo branch abaixo (PDO ou Supabase)
$clinica = [];
$consultasDia = [];

$clinica = [];
if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    $res = supabase_request('GET', 'tb_clinica?select=nome&id_clinica=eq.' . rawurlencode($idClinica));
    $clinica = ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) ? $res['body'][0] : [];

    // Buscar consultas do dia via helper (já aplica filtro de status e data)
    $consultasDia = obterConsultasFila($pdo, $idClinica);
    // Normalizar chave de telefone para compatibilidade com templates antigos
    foreach ($consultasDia as &$c) {
        if (isset($c['telefone_paciente']) && !isset($c['telefone'])) {
            $c['telefone'] = $c['telefone_paciente'];
        }
    }
    unset($c);
} else {
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
}

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

if (!defined('USE_SUPABASE_API') || !USE_SUPABASE_API) {
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
} else {
    // quando em modo API, a variável $pacientes será preenchida pelo branch abaixo
    $pacientes = [];
}

// Obter lista de pacientes relacionados às consultas da clínica
$pacientes = [];
if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    $path = 'tb_consulta?select=id_consulta,id_paciente,data_hora,tb_paciente(id_paciente,nome,email,telefone,cpf)&id_clinica=eq.' . rawurlencode($idClinica) . '&status=in.(Agendada,Em%20Atendimento,Finalizada)&order=data_hora.desc&limit=1000';
    $res = supabase_request('GET', $path);
    $map = [];
    if ($res['status'] >= 200 && is_array($res['body'])) {
        foreach ($res['body'] as $r) {
            // extrair id_paciente com checagens seguras para evitar warnings
            if (isset($r['id_paciente'])) {
                $pid = $r['id_paciente'];
            } else {
                $tp = relation_first($r['tb_paciente'] ?? []);
                $pid = $tp['id_paciente'] ?? null;
            }
            $pinfo = [];
            if (!empty($r['tb_paciente']) && is_array($r['tb_paciente'])) {
                $pinfo = relation_first($r['tb_paciente']);
            } else {
                // possíveis campos achatados
                $pinfo = [
                    'id_paciente' => $r['id_paciente'] ?? null,
                    'nome' => $r['nome_paciente'] ?? $r['paciente_nome'] ?? $r['nome'] ?? null,
                    'email' => $r['email'] ?? null,
                    'telefone' => $r['telefone'] ?? $r['telefone_paciente'] ?? null,
                    'cpf' => $r['cpf'] ?? null,
                ];
            }
            if ($pid === null) continue;
            if (!isset($map[$pid])) {
                $map[$pid] = [
                    'id_paciente' => $pid,
                    'nome' => $pinfo['nome'] ?? null,
                    'email' => $pinfo['email'] ?? null,
                    'telefone' => $pinfo['telefone'] ?? null,
                    'cpf' => $pinfo['cpf'] ?? null,
                    'total_consultas' => 0,
                    'ultima_consulta' => null,
                ];
            }
            $map[$pid]['total_consultas']++;
            if (empty($map[$pid]['ultima_consulta']) || strtotime($r['data_hora']) > strtotime($map[$pid]['ultima_consulta'])) {
                $map[$pid]['ultima_consulta'] = $r['data_hora'];
            }
        }
    }

    // Converter mapa para array e aplicar filtro de busca localmente
    foreach ($map as $p) {
        $match = true;
        if ($buscaPaciente !== '') {
            $term = mb_strtolower($buscaPaciente);
            $hay = mb_strtolower(implode(' ', [$p['nome'] ?? '', $p['email'] ?? '', $p['cpf'] ?? '']));
            $match = mb_strpos($hay, $term) !== false;
        }
        if ($match) $pacientes[] = $p;
    }

    // Ordenar por nome
    usort($pacientes, function ($a, $b) {
        return strcasecmp($a['nome'] ?? '', $b['nome'] ?? '');
    });
    // Enriquecer pacientes sem nome consultando tb_paciente em batch
    $missing = [];
    foreach ($pacientes as $idx => $p) {
        if (empty($p['nome']) && !empty($p['id_paciente'])) {
            $missing[] = (int) $p['id_paciente'];
        }
    }
    if (!empty($missing) && defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $in = implode(',', array_map('intval', $missing));
        try {
            $resp = supabase_request('GET', 'tb_paciente?select=id_paciente,nome,email,telefone,cpf&id_paciente=in.(' . rawurlencode($in) . ')&limit=100');
            if ($resp['status'] >= 200 && $resp['status'] < 300 && is_array($resp['body'])) {
                $byId = [];
                foreach ($resp['body'] as $rp) {
                    $byId[$rp['id_paciente']] = $rp;
                }
                foreach ($pacientes as $idx => $p) {
                    $pid = $p['id_paciente'] ?? null;
                    if ($pid && empty($p['nome']) && isset($byId[$pid])) {
                        $pacientes[$idx]['nome'] = $byId[$pid]['nome'] ?? $pacientes[$idx]['nome'];
                        $pacientes[$idx]['email'] = $byId[$pid]['email'] ?? $pacientes[$idx]['email'];
                        $pacientes[$idx]['telefone'] = $byId[$pid]['telefone'] ?? $pacientes[$idx]['telefone'];
                        $pacientes[$idx]['cpf'] = $byId[$pid]['cpf'] ?? $pacientes[$idx]['cpf'];
                    }
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }
    // Se não houver pacientes relacionados via consultas, tentar obter pacientes
    // que já tiveram qualquer consulta nesta clínica (histórico), em vez de listar
    // pacientes globais que podem não ter relação com a clínica.
    if (empty($pacientes)) {
        try {
            $respCons = supabase_request('GET', 'tb_consulta?select=id_paciente&id_clinica=eq.' . rawurlencode($idClinica) . '&limit=1000');
            if ($respCons['status'] >= 200 && is_array($respCons['body']) && count($respCons['body']) > 0) {
                $ids = [];
                foreach ($respCons['body'] as $rr) {
                    if (!empty($rr['id_paciente'])) $ids[] = (int)$rr['id_paciente'];
                }
                $ids = array_values(array_unique($ids));
                if (!empty($ids)) {
                    $in = implode(',', $ids);
                    $resp = supabase_request('GET', 'tb_paciente?select=id_paciente,nome,email,telefone,cpf&id_paciente=in.(' . rawurlencode($in) . ')&order=nome.asc&limit=1000');
                    if ($resp['status'] >= 200 && $resp['status'] < 300 && is_array($resp['body']) && count($resp['body']) > 0) {
                        foreach ($resp['body'] as $rp) {
                            $pacientes[] = [
                                'id_paciente' => $rp['id_paciente'] ?? null,
                                'nome' => $rp['nome'] ?? null,
                                'email' => $rp['email'] ?? null,
                                'telefone' => $rp['telefone'] ?? null,
                                'cpf' => $rp['cpf'] ?? null,
                                'total_consultas' => 0,
                                'ultima_consulta' => null,
                            ];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // silencioso — manter lista vazia
        }
    }
} else {
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
}

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
                    <p class="mb-0"><i class="fa-solid fa-phone"></i> <?= e($atendimentoAtual['telefone'] ?? '') ?></p>
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
                    <p class="mb-0"><i class="fa-solid fa-phone"></i> <?= e($proximoAtendimento['telefone'] ?? '') ?></p>
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
                            <td><?= e($c['telefone'] ?? '') ?></td>
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
                            <td><?= e($p['telefone'] ?? '') ?></td>
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
