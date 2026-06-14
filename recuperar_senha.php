<?php
$pageTitle = 'Recuperar Senha';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard_paciente.php');
    exit;
}

$erros = [];
$sucesso = false;
$etapa = 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'verificar';

    if ($acao === 'verificar') {
        $email = trim($_POST['email'] ?? '');

        if (!validarEmail($email)) {
            $erros[] = 'Informe um e-mail válido.';
        } else {
            $stmt = $pdo->prepare('SELECT id_paciente AS id, nome FROM tb_paciente WHERE email = ?');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            $tipo = 'paciente';
            if (!$usuario) {
                $stmt = $pdo->prepare('SELECT id_gestor AS id, nome FROM tb_gestor WHERE email = ?');
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();
                $tipo = 'gestor';
            }

            if ($usuario) {
                $_SESSION['recuperacao_email'] = $email;
                $_SESSION['recuperacao_tipo'] = $tipo;
                $_SESSION['recuperacao_nome'] = $usuario['nome'];
                $etapa = 'nova_senha';
            } else {
                $sucesso = true;
            }
        }
    } elseif ($acao === 'redefinir') {
        $email = $_SESSION['recuperacao_email'] ?? '';
        $tipo = $_SESSION['recuperacao_tipo'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $confirma = $_POST['confirma_senha'] ?? '';

        if ($email === '' || !in_array($tipo, ['paciente', 'gestor'], true)) {
            header('Location: recuperar_senha.php');
            exit;
        }

        if (strlen($senha) < 6) {
            $erros[] = 'Senha deve ter no mínimo 6 caracteres.';
        }
        if ($senha !== $confirma) {
            $erros[] = 'As senhas não coincidem.';
        }

        if (empty($erros)) {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            if ($tipo === 'paciente') {
                $stmt = $pdo->prepare('UPDATE tb_paciente SET senha = ? WHERE email = ?');
            } else {
                $stmt = $pdo->prepare('UPDATE tb_gestor SET senha = ? WHERE email = ?');
            }
            $stmt->execute([$hash, $email]);

            unset($_SESSION['recuperacao_email'], $_SESSION['recuperacao_tipo'], $_SESSION['recuperacao_nome']);
            setFlash('success', 'Senha redefinida com sucesso! Faça login com a nova senha.');
            header('Location: login.php');
            exit;
        }

        $etapa = 'nova_senha';
    }
}

if (!empty($_SESSION['recuperacao_email']) && $etapa === 'email' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $etapa = 'nova_senha';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="card card-custom">
        <div class="card-header card-header-custom text-center py-3">
            <h4 class="mb-0"><i class="fa-solid fa-key me-2"></i>Recuperar Senha</h4>
        </div>
        <div class="card-body">
            <?php if ($sucesso): ?>
                <div class="alert alert-info">
                    Se o e-mail estiver cadastrado, você poderá redefinir a senha na próxima etapa.
                </div>
                <p class="text-muted small">Por segurança, a mensagem é genérica quando o e-mail não é encontrado.</p>
                <a href="login.php" class="btn btn-primary-custom w-100">Voltar ao Login</a>
            <?php elseif ($etapa === 'nova_senha'): ?>
                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($erros as $erro): ?>
                                <li><?= e($erro) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p class="text-muted mb-3">
                    Redefinir senha para <strong><?= e($_SESSION['recuperacao_nome'] ?? '') ?></strong>
                    (<?= e($_SESSION['recuperacao_email'] ?? '') ?>)
                </p>

                <form method="POST" action="recuperar_senha.php">
                    <input type="hidden" name="acao" value="redefinir">
                    <div class="mb-3">
                        <label for="senha" class="form-label">Nova senha *</label>
                        <input type="password" class="form-control" id="senha" name="senha" minlength="6" required>
                    </div>
                    <div class="mb-4">
                        <label for="confirma_senha" class="form-label">Confirmar nova senha *</label>
                        <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-custom btn-lg">Redefinir Senha</button>
                        <a href="login.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            <?php else: ?>
                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($erros as $erro): ?>
                                <li><?= e($erro) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p class="text-muted mb-3">Informe o e-mail da sua conta para redefinir a senha.</p>

                <form method="POST" action="recuperar_senha.php">
                    <input type="hidden" name="acao" value="verificar">
                    <div class="mb-4">
                        <label for="email" class="form-label">E-mail *</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-custom btn-lg">Continuar</button>
                        <a href="login.php" class="btn btn-outline-secondary">Voltar ao Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
