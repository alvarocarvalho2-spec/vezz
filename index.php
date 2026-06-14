<?php

$pageTitle = 'Início';

require_once __DIR__ . '/includes/config.php';

require_once __DIR__ . '/includes/auth.php';

require_once __DIR__ . '/includes/functions.php';



$nomeBusca = trim($_GET['nome'] ?? '');

$cidadeBusca = trim($_GET['cidade'] ?? '');



if ($nomeBusca !== '' || $cidadeBusca !== '') {

    header('Location: pesquisar_clinicas.php?nome=' . urlencode($nomeBusca) . '&cidade=' . urlencode($cidadeBusca));

    exit;

}



$stmt = $pdo->query("

    SELECT c.id_clinica, c.nome, c.telefone, e.cidade

    FROM tb_clinica c

    INNER JOIN tb_endereco e ON e.id_clinica = c.id_clinica

    ORDER BY c.nome ASC

    LIMIT 4

");

$clinicasDestaque = $stmt->fetchAll();



require_once __DIR__ . '/includes/header.php';

?>



<div class="hero-section text-center">

    <h1 class="vezz-brand"><?= vezzBrand() ?></h1>

    <p class="mb-4">Plataforma Inteligente de Gestão de Atendimentos e Filas para Clínicas e Consultórios</p>

    <div class="d-flex flex-wrap justify-content-center gap-3">

        <a href="login.php" class="btn btn-light btn-lg px-4">

            <i class="fa-solid fa-right-to-bracket me-2"></i>Login

        </a>

        <a href="cadastro.php" class="btn btn-outline-light btn-lg px-4">

            <i class="fa-solid fa-user-plus me-2"></i>Cadastro Paciente

        </a>

        <a href="cadastro_clinica.php" class="btn btn-outline-light btn-lg px-4">

            <i class="fa-solid fa-hospital me-2"></i>Cadastrar Clínica

        </a>

    </div>

</div>



<div class="search-box mb-5">

    <h4 class="mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Pesquisar Clínicas</h4>

    <form action="pesquisar_clinicas.php" method="GET" class="row g-3">

        <div class="col-md-5">

            <label for="nome" class="form-label">Nome da clínica</label>

            <input type="text" class="form-control" id="nome" name="nome" placeholder="Ex: Saúde Total">

        </div>

        <div class="col-md-5">

            <label for="cidade" class="form-label">Localização (cidade)</label>

            <input type="text" class="form-control" id="cidade" name="cidade" placeholder="Ex: São Paulo">

        </div>

        <div class="col-md-2 d-flex align-items-end">

            <button type="submit" class="btn btn-primary-custom w-100">

                <i class="fa-solid fa-magnifying-glass"></i> Pesquisar

            </button>

        </div>

    </form>

</div>



<div class="row mb-4">

    <div class="col-md-4 mb-3">

        <div class="card card-custom h-100 text-center p-4">

            <i class="fa-solid fa-calendar-check icon-feature"></i>

            <h5>Agendamento Online</h5>

            <p class="text-muted mb-0">Agende consultas de forma rápida e prática.</p>

        </div>

    </div>

    <div class="col-md-4 mb-3">

        <div class="card card-custom h-100 text-center p-4">

            <i class="fa-solid fa-users icon-feature"></i>

            <h5>Fila Inteligente</h5>

            <p class="text-muted mb-0">Acompanhe sua posição em tempo real.</p>

        </div>

    </div>

    <div class="col-md-4 mb-3">

        <div class="card card-custom h-100 text-center p-4">

            <i class="fa-solid fa-gauge icon-feature"></i>

            <h5>Gestão Completa</h5>

            <p class="text-muted mb-0">Painel completo para gestores de clínicas.</p>

        </div>

    </div>

</div>



<?php if (!empty($clinicasDestaque)): ?>

<h4 class="mb-3">Clínicas Cadastradas</h4>

<div class="row">

    <?php foreach ($clinicasDestaque as $clinica): ?>

    <div class="col-md-6 col-lg-3 mb-3">

        <div class="card card-custom clinica-card h-100">

            <div class="card-body">

                <h5 class="card-title"><?= e($clinica['nome']) ?></h5>

                <p class="text-muted small mb-2">

                    <i class="fa-solid fa-location-dot"></i> <?= e($clinica['cidade']) ?>

                </p>

                <p class="small mb-3"><i class="fa-solid fa-phone"></i> <?= e($clinica['telefone']) ?></p>

                <a href="clinica_detalhes.php?id=<?= (int) $clinica['id_clinica'] ?>" class="btn btn-outline-primary btn-sm">

                    Ver detalhes

                </a>

            </div>

        </div>

    </div>

    <?php endforeach; ?>

</div>

<?php endif; ?>



<?php require_once __DIR__ . '/includes/footer.php'; ?>

