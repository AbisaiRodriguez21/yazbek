<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Dashboard Caja</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item active">Inicio</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<?php if (!empty($banner['texto'])): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="iconsminds-speaker mr-2"></i> <?= esc($banner['texto']) ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php if (!empty($mensaje)): ?>
<div class="row mb-4">
    <div class="col-12 mb-4">
        <div class="card">
            <?php if (!empty($mensaje['imagen'])): ?>
            <div class="position-relative">
                <img class="card-img-top" src="<?= base_url($mensaje['imagen']) ?>" alt="Banner">
            </div>
            <?php endif; ?>
            <div class="card-body">
                <div class="row">
                    <div class="col-10">
                        <?php if (!empty($mensaje['t_mensaje'])): ?>
                        <p class="list-item-heading mb-4 pt-1"><?= esc($mensaje['t_mensaje']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($mensaje['texto'])): ?>
                        <footer><p class="text-muted text-small mb-0 font-weight-light"><?= esc($mensaje['texto']) ?></p></footer>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($mensaje['fecha'])): ?>
                    <div class="col-2 text-right text-muted small"><?= esc($mensaje['fecha']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>


<?= $this->endSection() ?>
