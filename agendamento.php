<?php
$pageTitle = 'Agendamento';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

checkAuth('paciente');

$idClinica = (int) ($_GET['id_clinica'] ?? 0);
$idReagendar = (int) ($_GET['reagendar'] ?? 0);
$erros = [];
$consultaReagendar = null;

if ($idReagendar > 0) {
    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
        $res = supabase_request('GET', 'tb_consulta?select=*,tb_clinica(nome)&id_consulta=eq.' . rawurlencode($idReagendar) . '&id_paciente=eq.' . rawurlencode($_SESSION['usuario_id']) . '&status=eq.Agendada&limit=1');
        if ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) {
            $consultaReagendar = $res['body'][0];
            $rc = relation_first($consultaReagendar['tb_clinica'] ?? []);
            $consultaReagendar['nome_clinica'] = $rc['nome'] ?? null;
            if (empty($consultaReagendar['data_hora']) || strtotime($consultaReagendar['data_hora']) <= time()) {
                $consultaReagendar = null;
            }
        }
    } else {
        $stmt = $pdo->prepare("\n        SELECT c.*, cl.nome AS nome_clinica\n        FROM tb_consulta c\n        INNER JOIN tb_clinica cl ON cl.id_clinica = c.id_clinica\n        WHERE c.id_consulta = ? AND c.id_paciente = ? AND c.status = 'Agendada'\n          AND c.data_hora > NOW()\n    ");
        $stmt->execute([$idReagendar, $_SESSION['usuario_id']]);
        $consultaReagendar = $stmt->fetch();
    }

    if (!$consultaReagendar) {
        setFlash('danger', 'Consulta não encontrada ou não pode ser reagendada.');
        header('Location: dashboard_paciente.php');
        exit;
    }
    $idClinica = (int) $consultaReagendar['id_clinica'];
}

if ($idClinica <= 0) {
    setFlash('danger', 'Clínica não informada.');
    header('Location: pesquisar_clinicas.php');
    exit;
}

if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    $res = supabase_request('GET', 'tb_clinica?select=id_clinica,nome,hora_inicio,hora_fim,dias_atendimento&id_clinica=eq.' . rawurlencode($idClinica) . '&limit=1');
    $clinica = null;
    if ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) {
        $clinica = $res['body'][0];
    }
} else {
    $stmt = $pdo->prepare('SELECT id_clinica, nome, hora_inicio, hora_fim, dias_atendimento FROM tb_clinica WHERE id_clinica = ?');
    $stmt->execute([$idClinica]);
    $clinica = $stmt->fetch();
}

if (!$clinica) {
    setFlash('danger', 'Clínica não encontrada.');
    header('Location: pesquisar_clinicas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = trim($_POST['data_consulta'] ?? '');
    $horario = trim($_POST['horario_consulta'] ?? '');

    if ($data === '' || $horario === '') {
        $erros[] = 'Selecione data e horário.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $erros[] = 'Data inválida.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $horario)) {
        $erros[] = 'Horário inválido.';
    } else {
        $dataHoraObj = DateTime::createFromFormat('Y-m-d H:i', $data . ' ' . $horario);

        if (!$dataHoraObj) {
            $erros[] = 'Data ou horário inválidos.';
        } else {
                $erroValidacao = validarDataHoraAgendamento($dataHoraObj, $clinica['hora_inicio'] ?? null, $clinica['hora_fim'] ?? null);

            if ($erroValidacao) {
                $erros[] = $erroValidacao;
            } else {
                $idConsultaExcluir = $idReagendar > 0 ? $idReagendar : null;
                $dataHora = $dataHoraObj->format('Y-m-d H:i:s');

                $horariosDisp = obterHorariosDisponiveis(
                    $pdo,
                    $idClinica,
                    $data,
                    $idConsultaExcluir
                );

                if (!in_array($horario, $horariosDisp, true)) {
                    $erros[] = 'Horário indisponível. Escolha outro horário.';
                } elseif (horarioOcupadoNaClinica($pdo, $idClinica, $dataHora, $idConsultaExcluir)) {
                    $erros[] = 'Já existe uma consulta agendada neste horário para esta clínica.';
                } else {
                    if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
                        try {
                            // Double-check availability using helper (which itself supports API mode)
                            if (horarioOcupadoNaClinica($pdo, $idClinica, $dataHora, $idConsultaExcluir)) {
                                throw new Exception('Horário acabou de ser reservado. Escolha outro horário.');
                            }

                            if ($idReagendar > 0) {
                                // Update via Supabase REST (guarded internally to require service role key)
                                supabase_update('tb_consulta', 'id_consulta=eq.' . rawurlencode($idReagendar) . '&id_paciente=eq.' . rawurlencode($_SESSION['usuario_id']) . '&status=eq.Agendada', ['data_hora' => $dataHora]);
                                setFlash('success', 'Consulta reagendada com sucesso!');
                            } else {
                                // Insert via Supabase REST
                                supabase_insert('tb_consulta', ['data_hora' => $dataHora, 'status' => 'Agendada', 'id_paciente' => (int) $_SESSION['usuario_id'], 'id_clinica' => $idClinica]);
                                setFlash('success', 'Consulta agendada com sucesso!');
                            }

                            header('Location: dashboard_paciente.php');
                            exit;
                        } catch (Exception $e) {
                            $erros[] = $e->getMessage() ?: 'Erro ao salvar agendamento (API).';
                        }
                    } else {
                        try {
                            $pdo->beginTransaction();

                            if (horarioOcupadoNaClinica($pdo, $idClinica, $dataHora, $idConsultaExcluir)) {
                                throw new Exception('Horário acabou de ser reservado. Escolha outro horário.');
                            }

                            if ($idReagendar > 0) {
                                $stmt = $pdo->prepare("\n                                UPDATE tb_consulta\n                                SET data_hora = ?\n                                WHERE id_consulta = ? AND id_paciente = ? AND status = 'Agendada'\n                            ");
                                $stmt->execute([$dataHora, $idReagendar, $_SESSION['usuario_id']]);

                                if ($stmt->rowCount() === 0) {
                                    throw new Exception('Consulta não encontrada ou não pode ser reagendada.');
                                }

                                setFlash('success', 'Consulta reagendada com sucesso!');
                            } else {
                                $stmt = $pdo->prepare("\n                                INSERT INTO tb_consulta (data_hora, status, id_paciente, id_clinica)\n                                VALUES (?, 'Agendada', ?, ?)\n                            ");
                                $stmt->execute([$dataHora, $_SESSION['usuario_id'], $idClinica]);
                                setFlash('success', 'Consulta agendada com sucesso!');
                            }

                            $pdo->commit();
                            header('Location: dashboard_paciente.php');
                            exit;
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $erros[] = $e->getMessage() ?: 'Erro ao salvar agendamento. Tente novamente.';
                        }
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="mb-4">
    <i class="fa-solid fa-calendar-plus me-2"></i>
    <?= $idReagendar ? 'Reagendar Consulta' : 'Agendar Consulta' ?>
</h2>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-custom">
            <div class="card-header card-header-custom">
                <?= e($clinica['nome']) ?>
            </div>
            <div class="card-body">
                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($erros as $erro): ?>
                                <li><?= e($erro) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="agendamento.php?<?= $idReagendar ? 'reagendar=' . $idReagendar : 'id_clinica=' . $idClinica ?>">
                    <input type="hidden" id="id_clinica" value="<?= $idClinica ?>">
                    <?php if ($idReagendar): ?>
                        <input type="hidden" id="id_consulta_reagendar" value="<?= $idReagendar ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="data_consulta" class="form-label">Data *</label>
                        <input type="date" class="form-control" id="data_consulta" name="data_consulta"
                               min="<?= date('Y-m-d') ?>"
                               value="<?= e($_POST['data_consulta'] ?? '') ?>" required>
                        <?php
                        $diasMap = ['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','7'=>'Dom'];
                        $diasParts = isset($clinica['dias_atendimento']) ? array_filter(array_map('trim', explode(',', $clinica['dias_atendimento']))) : ['1','2','3','4','5'];
                        $diasLabels = array_map(function($d) use ($diasMap){ return $diasMap[$d] ?? $d; }, $diasParts);
                        $horaIni = e(substr($clinica['hora_inicio'] ?? '08:00',0,5));
                        $horaFim = e(substr($clinica['hora_fim'] ?? '18:00',0,5));
                        ?>
                        <small class="text-muted"><?= e(implode(', ', $diasLabels)) ?> — <?= $horaIni ?> às <?= $horaFim ?>. No mesmo dia, só é possível agendar horários futuros.</small>
                    </div>

                    <div class="mb-4">
                        <label for="horario_consulta" class="form-label">Horário *</label>
                        <select class="form-select" id="horario_consulta" name="horario_consulta" disabled required>
                            <option value="">Selecione a data primeiro</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success-custom">
                            <i class="fa-solid fa-check"></i> Confirmar
                        </button>
                        <a href="dashboard_paciente.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>