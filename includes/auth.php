<?php
/**
 * Controle de autenticação e autorização
 */

require_once __DIR__ . '/config.php';

/**
 * Verifica se o usuário está autenticado e opcionalmente se é do tipo permitido.
 *
 * @param string|null $tipoPermitido 'paciente', 'gestor' ou null (qualquer autenticado)
 */
function checkAuth($tipoPermitido = null)
{
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['usuario_tipo'])) {
        header('Location: login.php');
        exit;
    }

    if ($tipoPermitido !== null && $_SESSION['usuario_tipo'] !== $tipoPermitido) {
        if ($_SESSION['usuario_tipo'] === 'gestor') {
            header('Location: dashboard_gestor.php');
        } else {
            header('Location: dashboard_paciente.php');
        }
        exit;
    }
}

/**
 * Retorna true se o usuário está logado
 */
function isLoggedIn()
{
    return !empty($_SESSION['usuario_id']) && !empty($_SESSION['usuario_tipo']);
}

/**
 * Cria sessão após login bem-sucedido
 */
function criarSessao($usuario, $tipo)
{
    session_regenerate_id(true);

    $_SESSION['usuario_id']    = (int) $usuario['id'];
    $_SESSION['usuario_nome']  = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo']  = $tipo;

    if ($tipo === 'gestor') {
        $_SESSION['id_clinica'] = (int) $usuario['id_clinica'];
    } else {
        unset($_SESSION['id_clinica']);
    }
}

/**
 * Encerra a sessão do usuário
 */
function destruirSessao()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
