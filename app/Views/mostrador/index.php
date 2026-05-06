<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Dashboard Mostrador</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item active">Inicio</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <a href="<?= base_url('mostrador/venta') ?>" class="btn btn-primary btn-lg">
                <i class="iconsminds-add mr-1"></i> Nueva Nota
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

<!-- Accesos rápidos -->
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
        <a href="<?= base_url('mostrador/venta') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-shopping-cart icon-dual icon-lg mb-2"></i>
                <p class="font-weight-bold mb-0">Nueva Nota</p>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <a href="<?= base_url('mostrador/consulta') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-search-1 icon-dual icon-lg mb-2"></i>
                <p class="font-weight-bold mb-0">Consultar Folios</p>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <a href="<?= base_url('mostrador/clientes') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-business-man-woman icon-dual icon-lg mb-2"></i>
                <p class="font-weight-bold mb-0">Clientes</p>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <a href="<?= base_url('mostrador/inventario') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-box-close icon-dual icon-lg mb-2"></i>
                <p class="font-weight-bold mb-0">Inventario</p>
            </div>
        </a>
    </div>
</div>

<?= $this->endSection() ?>
