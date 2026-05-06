<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Reportes</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Reportes</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <a href="<?= base_url('reportes/cortecaja') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-receipt-4 icon-dual icon-lg mb-2"></i>
                <h5 class="font-weight-bold">Corte de Caja</h5>
                <p class="text-muted mb-0">Desglose de pagos por tipo y detalle de notas del día.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-3">
        <a href="<?= base_url('reportes/cortecaja2') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-financial icon-dual icon-lg mb-2"></i>
                <h5 class="font-weight-bold">Corte Detallado</h5>
                <p class="text-muted mb-0">Reporte con productos vendidos por nota.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-3">
        <a href="<?= base_url('reportes/excel') ?>" class="card text-center p-3 d-block text-decoration-none">
            <div class="card-body">
                <i class="iconsminds-microsoft icon-dual icon-lg mb-2"></i>
                <h5 class="font-weight-bold">Exportar Excel</h5>
                <p class="text-muted mb-0">Descargar el corte del día en formato Excel/CSV.</p>
            </div>
        </a>
    </div>
</div>

<?= $this->endSection() ?>
