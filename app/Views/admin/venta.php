<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Ventas del Día</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                    <li class="breadcrumb-item active">Venta</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2 d-flex">
            <a href="<?= base_url('reportes/cortecaja?fecha=' . date('Y-m-d')) ?>"
               class="btn btn-outline-primary mr-2">
                <i class="iconsminds-bar-chart-4"></i> Reporte Completo
            </a>
            <a href="<?= base_url('mostrador/venta') ?>" class="btn btn-primary">
                <i class="iconsminds-add"></i> Nueva Nota
            </a>
        </div>
    </div>
</div>

<!-- Tarjetas resumen -->
<?php
    $totalNotas   = count($notas);
    $totalVentas  = array_sum(array_column($notas, 'total'));
    $notasPagadas = count(array_filter($notas, fn($n) => ($n['idstatus'] ?? 0) == 5));
    $notasAbiertas= count(array_filter($notas, fn($n) => in_array($n['idstatus'] ?? 0, [1,2])));
?>
<div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-gradient-primary text-white shadow">
            <div class="card-body">
                <div class="font-weight-bold small text-white-50 mb-1">TOTAL NOTAS HOY</div>
                <div class="h3 mb-0"><?= $totalNotas ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-gradient-success text-white shadow">
            <div class="card-body">
                <div class="font-weight-bold small text-white-50 mb-1">NOTAS PAGADAS</div>
                <div class="h3 mb-0"><?= $notasPagadas ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-gradient-warning text-white shadow">
            <div class="card-body">
                <div class="font-weight-bold small text-white-50 mb-1">NOTAS ABIERTAS</div>
                <div class="h3 mb-0"><?= $notasAbiertas ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-gradient-info text-white shadow">
            <div class="card-body">
                <div class="font-weight-bold small text-white-50 mb-1">TOTAL VENDIDO</div>
                <div class="h3 mb-0">$<?= number_format($totalVentas, 0) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de notas -->
<div class="card">
    <div class="card-header font-weight-bold">
        Notas — <?= date('d/m/Y') ?>
    </div>
    <div class="table-responsive">
        <table id="tablaVentas" class="table table-striped table-sm mb-0 data-table">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha/Hora</th>
                    <th>Cliente</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">Total</th>
                    <th>Estatus</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($notas)): ?>
                    <?php foreach ($notas as $n):
                        $status = (int)($n['idstatus'] ?? 0);
                        $badges = [
                            1 => ['Abierta',    'warning'],
                            2 => ['En proceso', 'info'],
                            3 => ['Cancelada',  'danger'],
                            4 => ['Anticipo',   'secondary'],
                            5 => ['Pagada',     'success'],
                        ];
                        [$label, $color] = $badges[$status] ?? ['—', 'light'];
                    ?>
                    <tr>
                        <td><strong><?= (int)$n['folio'] ?></strong></td>
                        <td><?= esc($n['fecha_inicial']) ?></td>
                        <td><?= esc($n['nombreCliente'] ?? '—') ?></td>
                        <td class="text-right">$<?= number_format($n['subTotal'] ?? 0, 2) ?></td>
                        <td class="text-right font-weight-bold">$<?= number_format($n['total'] ?? 0, 2) ?></td>
                        <td><span class="badge badge-<?= $color ?>"><?= $label ?></span></td>
                        <td>
                            <?php if ($status == 5): ?>
                                <a href="<?= base_url('caja/folio/' . (int)$n['folio']) ?>"
                                   class="btn btn-xs btn-outline-success" title="Ver nota">
                                    <i class="simple-icon-eye"></i>
                                </a>
                            <?php elseif (in_array($status, [1, 2])): ?>
                                <a href="<?= base_url('mostrador/venta/' . (int)$n['folio'] . '/productos') ?>"
                                   class="btn btn-xs btn-outline-primary" title="Continuar">
                                    <i class="simple-icon-pencil"></i>
                                </a>
                                <a href="<?= base_url('caja/folio/' . (int)$n['folio']) ?>"
                                   class="btn btn-xs btn-outline-info" title="Cobrar">
                                    <i class="iconsminds-cash-register-2"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="iconsminds-receipt-4 icon-dual icon-lg d-block mb-2"></i>
                            No hay notas registradas hoy.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($notas)): ?>
            <tfoot>
                <tr class="font-weight-bold bg-light">
                    <td colspan="4" class="text-right">Total General:</td>
                    <td class="text-right text-success">$<?= number_format($totalVentas, 2) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
$('#tablaVentas').DataTable({
    responsive: true,
    pageLength: 25,
    order: [[0, 'desc']],
    language: { url: '/assets/js/vendor/datatables.spanish.json' }
});
</script>
<?= $this->endSection() ?>
