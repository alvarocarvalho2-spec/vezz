<?php

if (!isset($pageTitle)) {

    $pageTitle = 'VEZZ';

}

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/auth.php';

require_once __DIR__ . '/functions.php';

?>

<!DOCTYPE html>

<?php

if (!isset($pageTitle)) {

    $pageTitle = 'VEZZ';

}

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/auth.php';

require_once __DIR__ . '/functions.php';

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> - VEZZ</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">

    <link rel="icon" href="<?= BASE_URL ?>/assets/img/vezz-logo.svg" type="image/svg+xml">

</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary-custom sticky-top">

    <div class="container">

        <a class="navbar-brand vezz-brand" href="<?= BASE_URL ?>/index.php">

            <?= vezzBrand() ?>

        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="navbarNav">

            <ul class="navbar-nav me-auto">

                <li class="nav-item">

                    <a class="nav-link" href="<?= BASE_URL ?>/index.php">Início</a>

                </li>

                <li class="nav-item">

                    <a class="nav-link" href="<?= BASE_URL ?>/pesquisar_clinicas.php">Clínicas</a>

                </li>

                <?php if (isLoggedIn()): ?>

                    <?php if ($_SESSION['usuario_tipo'] === 'paciente'): ?>

                        <li class="nav-item">

                            <a class="nav-link" href="<?= BASE_URL ?>/dashboard_paciente.php">Meu Painel</a>

                        </li>

                    <?php else: ?>

                        <li class="nav-item">

                            <a class="nav-link" href="<?= BASE_URL ?>/dashboard_gestor.php">Painel Gestor</a>

                        </li>

                        <li class="nav-item">

                            <a class="nav-link" href="<?= BASE_URL ?>/controle_fila.php">Fila</a>

                        </li>

                    <?php endif; ?>

                <?php endif; ?>

            </ul>

            <ul class="navbar-nav">

                <?php if (isLoggedIn()): ?>

                    <li class="nav-item">

                        <span class="nav-link text-light opacity-75">

                            <i class="fa-solid fa-circle-user me-1"></i><?= e($_SESSION['usuario_nome']) ?>

                        </span>

                    </li>

                    <li class="nav-item">

                        <a class="nav-link btn btn-outline-light btn-sm ms-2 px-3" href="<?= BASE_URL ?>/logout.php">

                            <i class="fa-solid fa-right-from-bracket me-1"></i>Sair

                        </a>

                    </li>

                <?php else: ?>

                    <li class="nav-item">

                        <a class="nav-link" href="<?= BASE_URL ?>/login.php">Login</a>

                    </li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?= BASE_URL ?>/cadastro.php">Cadastro Paciente</a>

                    </li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?= BASE_URL ?>/cadastro_clinica.php">Cadastrar Clínica</a>

                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

</nav>

<main class="main-content">

    <div class="container py-4">

        <?php $flash = getFlash(); if ($flash): ?>

            <div class="alert alert-<?= e($flash['tipo']) ?> alert-dismissible fade show" role="alert">

                <?= e($flash['mensagem']) ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

            </div>

        <?php endif; ?>

