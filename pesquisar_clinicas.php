<?php

$pageTitle = 'Pesquisar Clínicas';

require_once __DIR__ . '/includes/header.php';



$nome   = trim($_GET['nome'] ?? '');

$cidade = trim($_GET['cidade'] ?? '');

$clinicas = pesquisarClinicas($pdo, $nome, $cidade);

?>



<h2 class="mb-4"><i class="fa-solid fa-magnifying-glass me-2"></i>Pesquisar Clínicas</h2>



<div class="card card-custom mb-4">

    <div class="card-body">

        <form method="GET" action="pesquisar_clinicas.php" class="row g-3">

            <div class="col-md-5">

                <label for="nome" class="form-label">Nome da clínica</label>

                <input type="text" class="form-control" id="nome" name="nome"

                       value="<?= e($nome) ?>" placeholder="Digite o nome">

            </div>

            <div class="col-md-5">

                <label for="cidade" class="form-label">Localização (cidade)</label>

                <input type="text" class="form-control" id="cidade" name="cidade"

                       value="<?= e($cidade) ?>" placeholder="Digite a cidade">

            </div>

            <div class="col-md-2 d-flex align-items-end">

                <button type="submit" class="btn btn-primary-custom w-100">

                    <i class="fa-solid fa-magnifying-glass"></i> Pesquisar

                </button>

            </div>

        </form>

    </div>

</div>



<?php if (empty($clinicas)): ?>

    <div class="alert alert-warning">

        <i class="fa-solid fa-triangle-exclamation"></i> Nenhuma clínica encontrada com os critérios informados.

    </div>

<?php else: ?>

    <p class="text-muted mb-3"><?= count($clinicas) ?> clínica(s) encontrada(s)</p>

    <div class="row">

        <?php foreach ($clinicas as $clinica): ?>

        <div class="col-md-6 mb-3">

            <div class="card card-custom clinica-card h-100">

                <div class="card-body">

                    <h5 class="card-title"><?= e($clinica['nome']) ?></h5>

                    <p class="mb-1">

                        <i class="fa-solid fa-location-dot text-primary"></i>

                        <?= e($clinica['rua']) ?>, <?= e($clinica['numero']) ?> -

                        <?= e($clinica['bairro']) ?>, <?= e($clinica['cidade']) ?>

                    </p>

                    <p class="mb-3"><i class="fa-solid fa-phone text-primary"></i> <?= e($clinica['telefone']) ?></p>

                    <a href="clinica_detalhes.php?id=<?= (int) $clinica['id_clinica'] ?>"

                       class="btn btn-primary-custom btn-sm">

                        <i class="fa-solid fa-eye"></i> Visualizar Clínica

                    </a>

                </div>

            </div>

        </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>



<?php require_once __DIR__ . '/includes/footer.php'; ?>

