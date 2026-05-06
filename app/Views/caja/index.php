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
        <div class="pt-2">
            <a href="<?= base_url('caja/cobrar') ?>" class="btn btn-primary">
                <i class="iconsminds-cash-register-2"></i> Ir a Cobrar
            </a>
        </div>
    </div>
</div>

<?php if (!empty($banner['texto'])): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="iconsminds-speaker mr-2"></i> <?= esc($banner['texto']) ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>
<?php if (!empty($mensaje['texto'])): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="iconsminds-information mr-2"></i> <?= esc($mensaje['texto']) ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-6 col-md-4 mb-3">
        <a href="<?= base_url('caja/cobrar') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-cash-register-2 icon-dual icon-lg mb-2"></i>
                <p class="font-weight-bold mb-0">Cobrar</p>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 mb-3">
        <a href="<?= base_url('caja/corte') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-receipt-4 icon-dual icon-lg mb-2"></i>
                <p class="font-weight-bold mb-0">Corte de Caja</p>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 mb-3">
        <a href="<?= base_url('caja/corte/detalle') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-financial icon-dual icon-lg mb-2"></i>
                <p class="font-weight-bold mb-0">Detalle Corte</p>
            </div>
        </a>
    </div>
</div>

<?= $this->endSection() ?>
