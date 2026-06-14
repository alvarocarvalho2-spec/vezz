<?php
$pageTitle = 'Cadastro de Gestor';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['usuario_tipo'] === 'gestor' ? 'dashboard_gestor.php' : 'dashboard_paciente.php'));
    exit;
}

$clinicas = $pdo->query('
    SELECT c.id_clinica, c.nome, e.cidade
    FROM tb_clinica c
    INNER JOIN tb_endereco e ON e.id_clinica = c.id_clinica
    ORDER BY c.nome ASC
')->fetchAll();

if (empty($clinicas)) {
    setFlash('warning', 'Cadastre uma clínica antes de registrar o gestor.');
    header('Location: cadastro_clinica.php');
    exit;
}

$idClinicaPre = (int) ($_GET['id_clinica'] ?? $_POST['id_clinica'] ?? 0);
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = trim($_POST['nome'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $cargo      = trim($_POST['cargo'] ?? '');
    $senha      = $_POST['senha'] ?? '';
    $confirma   = $_POST['confirma_senha'] ?? '';
    $idClinica  = (int) ($_POST['id_clinica'] ?? 0);

    if ($nome === '') {
        $erros[] = 'Nome é obrigatório.';
    }
    if (!validarEmail($email)) {
        $erros[] = 'E-mail inválido.';
    }
    if ($cargo === '') {
        $erros[] = 'Cargo é obrigatório.';
    }
    if ($idClinica <= 0) {
        $erros[] = 'Selecione a clínica.';
    }
    if (strlen($senha) < 6) {
        $erros[] = 'Senha deve ter no mínimo 6 caracteres.';
    }
    if ($senha !== $confirma) {
        $erros[] = 'As senhas não coincidem.';
    }

    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id_clinica FROM tb_clinica WHERE id_clinica = ?');
        $stmt->execute([$idClinica]);
        if (!$stmt->fetch()) {
            $erros[] = 'Clínica selecionada não encontrada.';
        }
    }

    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id_gestor FROM tb_gestor WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erros[] = 'E-mail já cadastrado.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO tb_gestor (nome, email, senha, cargo, id_clinica)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $nome,
                $email,
                password_hash($senha, PASSWORD_BCRYPT),
                $cargo,
                $idClinica,
            ]);

            setFlash('success', 'Gestor cadastrado com sucesso! Faça login para acessar o painel.');
            header('Location: login.php');
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="card card-custom">
        <div class="card-header card-header-custom text-center py-3">
            <h4 class="mb-0"><i class="fa-solid fa-id-badge me-2"></i>Cadastro de Gestor</h4>
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

            <form method="POST" action="cadastro_gestor.php">
                <div class="mb-3">
                    <label for="id_clinica" class="form-label">Clínica *</label>
                    <select class="form-select" id="id_clinica" name="id_clinica" required>
                        <option value="">Selecione a clínica</option>
                        <?php foreach ($clinicas as $c): ?>
                            <option value="<?= (int) $c['id_clinica'] ?>"
                                <?= $idClinicaPre === (int) $c['id_clinica'] ? 'selected' : '' ?>>
                                <?= e($c['nome']) ?> — <?= e($c['cidade']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome *</label>
                    <input type="text" class="form-control" id="nome" name="nome" maxlength="100"
                           value="<?= e($_POST['nome'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail *</label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="100"
                           value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="cargo" class="form-label">Cargo *</label>
                    <input type="text" class="form-control" id="cargo" name="cargo" maxlength="50"
                           value="<?= e($_POST['cargo'] ?? '') ?>" placeholder="Ex: Administrador" required>
                </div>
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha *</label>
                    <input type="password" class="form-control" id="senha" name="senha" minlength="6" required>
                </div>
                <div class="mb-4">
                    <label for="confirma_senha" class="form-label">Confirmar Senha *</label>
                    <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success-custom btn-lg">Cadastrar Gestor</button>
                    <a href="cadastro_clinica.php" class="btn btn-outline-secondary">Cadastrar Nova Clínica</a>
                    <a href="login.php" class="btn btn-link">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
