<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Mis Metas</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('mostrador') ?>">Mostrador</a></li>
                <li class="breadcrumb-item active">Metas</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="iconsminds-receipt-4 icon-dual icon-lg mb-2"></i>
                <p class="text-muted mb-1">Notas del Mes</p>
                <h2 class="font-weight-bold"><?= (int)($estadisticas['totalNotas'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="iconsminds-dollar icon-dual icon-lg mb-2"></i>
                <p class="text-muted mb-1">Ventas del Mes</p>
                <h2 class="font-weight-bold text-success">
                    $<?= number_format($estadisticas['totalVentas'] ?? 0, 2) ?>
                </h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="iconsminds-bullseye icon-dual icon-lg mb-2"></i>
                <p class="text-muted mb-1">Piezas Vendidas</p>
                <h2 class="font-weight-bold"><?= (int)($estadisticas['totalPiezas'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($notasDelMes)): ?>
<div class="card">
    <div class="card-header font-weight-bold">Notas del Mes</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notasDelMes as $n): ?>
                <tr>
                    <td><?= (int)$n['folio'] ?></td>
                    <td><?= date('d/m/Y', strtotime($n['fecha_inicial'])) ?></td>
                    <td><?= esc($n['cliente'] ?? '') ?></td>
                    <td class="text-right">$<?= number_format($n['total'] ?? 0, 2) ?></td>
                    <td>
                        <?php
                        $statusLabels = [1=>'Abierta',2=>'En proceso',3=>'Cancelada',4=>'Anticipo',5=>'Pagada'];
                        $statusClass  = [1=>'primary',2=>'info',3=>'danger',4=>'warning',5=>'success'];
                        $s = (int)$n['status'];
                        ?>
                        <span class="badge badge-<?= $statusClass[$s] ?? 'secondary' ?>">
                            <?= $statusLabels[$s] ?? $s ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
