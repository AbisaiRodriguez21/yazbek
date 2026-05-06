<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Detalle Folio #<?= (int)$folio ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Caja</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('caja/cobrar') ?>">Cobrar</a></li>
                    <li class="breadcrumb-item active">Folio #<?= (int)$folio ?></li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <a href="<?= base_url('caja/cobrar') ?>" class="btn btn-outline-secondary mr-2">
                <i class="iconsminds-arrow-left"></i> Volver
            </a>
            <?php if (($nota['status'] ?? 0) != 5): ?>
            <a href="<?= base_url('caja/venta/' . (int)$folio) ?>" class="btn btn-success">
                <i class="iconsminds-cash-register-2"></i> Cobrar
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$nota): ?>
<div class="alert alert-danger">Nota no encontrada.</div>
<?php else: ?>

<div class="row">
    <!-- Info general -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Información de la Nota</div>
            <div class="card-body">
                <?php
                $statusLabels = [1=>'Abierta',2=>'En proceso',3=>'Cancelada',4=>'Anticipo',5=>'Pagada'];
                $statusClass  = [1=>'primary',2=>'info',3=>'danger',4=>'warning',5=>'success'];
                $s = (int)$nota['status'];
                ?>
                <table class="table table-sm mb-0">
                    <tr><th>Folio</th><td><?= (int)$nota['folio'] ?></td></tr>
                    <tr><th>Fecha</th><td><?= date('d/m/Y', strtotime($nota['fecha_inicial'])) ?></td></tr>
                    <tr><th>Cliente</th><td><?= esc($nota['cliente'] ?? '') ?></td></tr>
                    <tr><th>Vendedor</th><td><?= esc($nota['vendedor'] ?? '') ?></td></tr>
                    <tr><th>Status</th>
                        <td><span class="badge badge-<?= $statusClass[$s] ?? 'secondary' ?>">
                            <?= $statusLabels[$s] ?? $s ?>
                        </span></td>
                    </tr>
                    <tr><th>Verificado</th>
                        <td><?= $nota['verificado'] ? '<span class="text-success">Sí</span>' : '<span class="text-muted">No</span>' ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Totales -->
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Totales</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Subtotal</th><td class="text-right">$<?= number_format($nota['subTotal'] ?? 0, 2) ?></td></tr>
                    <tr><th>Descuento</th><td class="text-right"><?= number_format($nota['descuento'] ?? 0, 2) ?>%</td></tr>
                    <tr><th>IVA</th><td class="text-right">$<?= number_format($nota['iva'] ?? 0, 2) ?></td></tr>
                    <tr class="font-weight-bold">
                        <th>Total</th>
                        <td class="text-right text-success">$<?= number_format($nota['total'] ?? 0, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Pagos registrados -->
        <div class="card">
            <div class="card-header font-weight-bold">Pagos Registrados</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Tipo</th><th class="text-right">Monto</th><th class="text-right">Cargo</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $p): ?>
                        <tr>
                            <td><?= esc($p['descripcion']) ?></td>
                            <td class="text-right">$<?= number_format($p['monto'], 2) ?></td>
                            <td class="text-right">$<?= number_format($p['cargos'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pagos)): ?>
                        <tr><td colspan="3" class="text-center text-muted">Sin pagos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detalle productos -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header font-weight-bold">Productos de la Nota</div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th class="text-right">P.U.</th>
                            <th class="text-right">Cant.</th>
                            <th class="text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $d): ?>
                        <tr>
                            <td><?= esc($d['sku']) ?></td>
                            <td><?= esc($d['estilo']) ?></td>
                            <td class="text-right">$<?= number_format($d['precio'], 2) ?></td>
                            <td class="text-right"><?= (int)$d['cantidad'] ?></td>
                            <td class="text-right">$<?= number_format($d['importe'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($detalle)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin productos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (($nota['status'] ?? 0) != 5): ?>
            <div class="card-footer text-right">
                <a href="<?= base_url('caja/cancelar/' . (int)$folio) ?>"
                   class="btn btn-outline-danger mr-2"
                   onclick="return confirm('¿Cancelar la nota #<?= (int)$folio ?>?')">
                    Cancelar Nota
                </a>
                <a href="<?= base_url('caja/venta/' . (int)$folio) ?>" class="btn btn-success">
                    <i class="iconsminds-cash-register-2"></i> Cobrar Nota
                </a>
            </div>
            <?php else: ?>
            <div class="card-footer">
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="iconsminds-printer"></i> Imprimir
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>

<?= $this->endSection() ?>
