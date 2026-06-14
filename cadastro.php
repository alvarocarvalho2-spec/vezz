<?php
$pageTitle = 'Cadastro';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard_paciente.php');
    exit;
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $cpf      = trim($_POST['cpf'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $senha    = $_POST['senha'] ?? '';
    $confirma = $_POST['confirma_senha'] ?? '';

    if ($nome === '') {
        $erros[] = 'Nome é obrigatório.';
    }
    if (!validarCPF($cpf)) {
        $erros[] = 'CPF inválido.';
    }
    if ($telefone === '') {
        $erros[] = 'Telefone é obrigatório.';
    }
    if (!validarEmail($email)) {
        $erros[] = 'E-mail inválido.';
    }
    if (strlen($senha) < 6) {
        $erros[] = 'Senha deve ter no mínimo 6 caracteres.';
    }
    if ($senha !== $confirma) {
        $erros[] = 'As senhas não coincidem.';
    }

    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id_paciente FROM tb_paciente WHERE cpf = ? OR email = ?');
        $stmt->execute([formatarCPF($cpf), $email]);
        if ($stmt->fetch()) {
            $erros[] = 'CPF ou e-mail já cadastrado.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO tb_paciente (nome, cpf, email, telefone, senha)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $nome,
                formatarCPF($cpf),
                $email,
                formatarTelefone($telefone),
                password_hash($senha, PASSWORD_BCRYPT),
            ]);

            setFlash('success', 'Cadastro realizado com sucesso! Faça login para continuar.');
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
            <h4 class="mb-0"><i class="fa-solid fa-user-plus me-2"></i>Cadastro de Paciente</h4>
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

            <form method="POST" action="cadastro.php" novalidate>
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome *</label>
                    <input type="text" class="form-control" id="nome" name="nome" maxlength="100"
                           value="<?= e($_POST['nome'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="cpf" class="form-label">CPF *</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" data-mask="cpf"
                           value="<?= e($_POST['cpf'] ?? '') ?>" placeholder="000.000.000-00" required>
                </div>
                <div class="mb-3">
                    <label for="telefone" class="form-label">Telefone *</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" data-mask="telefone"
                           value="<?= e($_POST['telefone'] ?? '') ?>" placeholder="(00) 00000-0000" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail *</label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="100"
                           value="<?= e($_POST['email'] ?? '') ?>" required>
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
                    <button type="submit" class="btn btn-success-custom btn-lg">Cadastrar</button>
                    <a href="login.php" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
