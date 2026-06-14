<?php
$pageTitle = 'Cadastro de Clínica';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['usuario_tipo'] === 'gestor' ? 'dashboard_gestor.php' : 'dashboard_paciente.php'));
    exit;
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['nome'] ?? '');
    $cnpj      = trim($_POST['cnpj'] ?? '');
    $telefone  = trim($_POST['telefone'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $hora_inicio = trim($_POST['hora_inicio'] ?? '08:00');
    $hora_fim = trim($_POST['hora_fim'] ?? '18:00');
    $dias = $_POST['dias_atendimento'] ?? [];
    $rua       = trim($_POST['rua'] ?? '');
    $numero    = trim($_POST['numero'] ?? '');
    $bairro    = trim($_POST['bairro'] ?? '');
    $cidade    = trim($_POST['cidade'] ?? '');
    $cep       = trim($_POST['cep'] ?? '');

    if ($nome === '') {
        $erros[] = 'Nome da clínica é obrigatório.';
    }
    if (!validarCNPJ($cnpj)) {
        $erros[] = 'CNPJ inválido.';
    }
    if ($telefone === '') {
        $erros[] = 'Telefone é obrigatório.';
    }
    if ($rua === '' || $numero === '' || $bairro === '' || $cidade === '') {
        $erros[] = 'Preencha todos os campos do endereço.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $hora_inicio) || !preg_match('/^\d{2}:\d{2}$/', $hora_fim)) {
        $erros[] = 'Horários inválidos. Use o formato HH:MM.';
    } else {
        if (strtotime($hora_inicio) >= strtotime($hora_fim)) {
            $erros[] = 'Horário de início deve ser anterior ao horário de término.';
        }
    }
    if (empty($dias) || !is_array($dias)) {
        $erros[] = 'Selecione pelo menos um dia de atendimento.';
    } else {
        // sanitize days: expect strings like '1'..'7'
        $dias = array_values(array_filter(array_map('intval', $dias), function($d){ return $d>=1 && $d<=7; }));
        if (empty($dias)) {
            $erros[] = 'Selecione pelo menos um dia válido para atendimento.';
        }
    }
    if (!validarCEP($cep)) {
        $erros[] = 'CEP inválido.';
    }

    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id_clinica FROM tb_clinica WHERE cnpj = ?');
        $stmt->execute([formatarCNPJ($cnpj)]);
        if ($stmt->fetch()) {
            $erros[] = 'CNPJ já cadastrado.';
        }
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            // Em PostgreSQL, usar RETURNING para obter o id inserido
            $stmt = $pdo->prepare('
                INSERT INTO tb_clinica (nome, cnpj, telefone, descricao, hora_inicio, hora_fim, dias_atendimento)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                RETURNING id_clinica
            ');
            $stmt->execute([
                $nome,
                formatarCNPJ($cnpj),
                formatarTelefone($telefone),
                $descricao !== '' ? $descricao : null,
                $hora_inicio,
                $hora_fim,
                implode(',', $dias),
            ]);
            $idClinica = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('
                INSERT INTO tb_endereco (rua, numero, bairro, cidade, cep, id_clinica)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $rua,
                $numero,
                $bairro,
                $cidade,
                formatarCEP($cep),
                $idClinica,
            ]);

            $pdo->commit();
            setFlash('success', 'Clínica cadastrada! Agora cadastre o gestor responsável.');
            header('Location: cadastro_gestor.php?id_clinica=' . $idClinica);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erros[] = 'Erro ao cadastrar clínica. Tente novamente.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card auth-card-wide">
    <div class="card card-custom">
        <div class="card-header card-header-custom text-center py-3">
            <h4 class="mb-0"><i class="fa-solid fa-hospital me-2"></i>Cadastro de Clínica</h4>
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

            <form method="POST" action="cadastro_clinica.php">
                <h6 class="text-muted mb-3">Dados da Clínica</h6>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" maxlength="100"
                               value="<?= e($_POST['nome'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="cnpj" class="form-label">CNPJ *</label>
                        <input type="text" class="form-control" id="cnpj" name="cnpj" data-mask="cnpj"
                               value="<?= e($_POST['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="telefone" class="form-label">Telefone *</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" data-mask="telefone"
                           value="<?= e($_POST['telefone'] ?? '') ?>" placeholder="(00) 00000-0000" required>
                </div>
                <div class="mb-4">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3"
                              maxlength="2000"><?= e($_POST['descricao'] ?? '') ?></textarea>
                </div>

                <h6 class="text-muted mb-3">Horário de Atendimento</h6>
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <label for="hora_inicio" class="form-label">Início *</label>
                        <input type="time" class="form-control" id="hora_inicio" name="hora_inicio"
                               value="<?= e($_POST['hora_inicio'] ?? '08:00') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="hora_fim" class="form-label">Término *</label>
                        <input type="time" class="form-control" id="hora_fim" name="hora_fim"
                               value="<?= e($_POST['hora_fim'] ?? '18:00') ?>" required>
                    </div>
                </div>
                <h6 class="text-muted mb-3">Dias de Atendimento</h6>
                <div class="row mb-4">
                    <?php $diasSelecionados = $_POST['dias_atendimento'] ?? ['1','2','3','4','5']; ?>
                    <?php $dias = ['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','7'=>'Dom']; ?>
                    <?php foreach ($dias as $k => $label): ?>
                        <div class="col-auto form-check">
                            <input class="form-check-input" type="checkbox" id="dia_<?= $k ?>" name="dias_atendimento[]" value="<?= $k ?>"
                                   <?= in_array((string)$k, (array)$diasSelecionados, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="dia_<?= $k ?>"><?= $label ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h6 class="text-muted mb-3">Endereço</h6>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="rua" class="form-label">Rua *</label>
                        <input type="text" class="form-control" id="rua" name="rua" maxlength="100"
                               value="<?= e($_POST['rua'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="numero" class="form-label">Número *</label>
                        <input type="text" class="form-control" id="numero" name="numero" maxlength="10"
                               value="<?= e($_POST['numero'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="bairro" class="form-label">Bairro *</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" maxlength="100"
                               value="<?= e($_POST['bairro'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="cidade" class="form-label">Cidade *</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" maxlength="100"
                               value="<?= e($_POST['cidade'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="cep" class="form-label">CEP *</label>
                        <input type="text" class="form-control" id="cep" name="cep" data-mask="cep"
                               value="<?= e($_POST['cep'] ?? '') ?>" placeholder="00000-000" required>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-success-custom btn-lg">Cadastrar Clínica</button>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
