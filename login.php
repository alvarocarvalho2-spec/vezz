<?php
$pageTitle = 'Login';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
    require_once __DIR__ . '/includes/supabase_api.php';
}

if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['usuario_tipo'] === 'gestor' ? 'dashboard_gestor.php' : 'dashboard_paciente.php'));
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } elseif (!validarEmail($email)) {
        $erro = 'E-mail inválido.';
    } else {
        $tipoLogin = $_POST['tipo'] ?? 'paciente';

        if ($tipoLogin === 'gestor') {
            if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
                $path = 'tb_gestor?select=id_gestor,nome,email,senha,id_clinica&email=eq.' . rawurlencode($email);
                $res = supabase_request('GET', $path);
                $usuario = ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) ? $res['body'][0] : null;
                if ($usuario) {
                    // mapear id
                    $usuario['id'] = $usuario['id_gestor'] ?? $usuario['id'] ?? null;
                }
            } else {
                $stmt = $pdo->prepare('
                    SELECT id_gestor AS id, nome, email, senha, id_clinica
                    FROM tb_gestor WHERE email = ?
                ');
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();
            }

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                unset($usuario['senha']);
                criarSessao($usuario, 'gestor');
                setFlash('success', 'Bem-vindo(a), ' . $usuario['nome'] . '!');
                header('Location: dashboard_gestor.php');
                exit;
            }
        } else {
            if (defined('USE_SUPABASE_API') && USE_SUPABASE_API) {
                $path = 'tb_paciente?select=id_paciente,nome,email,senha&email=eq.' . rawurlencode($email);
                $res = supabase_request('GET', $path);
                $usuario = ($res['status'] >= 200 && is_array($res['body']) && count($res['body']) > 0) ? $res['body'][0] : null;
                if ($usuario) {
                    $usuario['id'] = $usuario['id_paciente'] ?? $usuario['id'] ?? null;
                }
            } else {
                $stmt = $pdo->prepare('SELECT id_paciente AS id, nome, email, senha FROM tb_paciente WHERE email = ?');
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();
            }

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                unset($usuario['senha']);
                criarSessao($usuario, 'paciente');
                setFlash('success', 'Bem-vindo(a), ' . $usuario['nome'] . '!');
                header('Location: dashboard_paciente.php');
                exit;
            }
        }

        $erro = 'E-mail ou senha incorretos.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <div class="card card-custom">
        <div class="card-header card-header-custom text-center py-3">
            <h4 class="mb-0"><i class="fa-solid fa-right-to-bracket me-2"></i>Login</h4>
        </div>
        <div class="card-body">
            <?php if ($erro): ?>
                <div class="alert alert-danger"><?= e($erro) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label class="form-label">Tipo de acesso *</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_paciente" value="paciente"
                                   <?= ($_POST['tipo'] ?? 'paciente') === 'paciente' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tipo_paciente">Paciente</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_gestor" value="gestor"
                                   <?= ($_POST['tipo'] ?? '') === 'gestor' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tipo_gestor">Gestor</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="mb-4">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary-custom btn-lg">Entrar</button>
                    <a href="recuperar_senha.php" class="btn btn-link">Recuperar senha</a>
                    <a href="cadastro.php" class="btn btn-outline-secondary">Criar conta (Paciente)</a>
                    <a href="cadastro_gestor.php" class="btn btn-outline-secondary">Cadastro de Gestor</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
