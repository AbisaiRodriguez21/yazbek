<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-datepicker3.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<style>
@media print {
    .sidebar, nav.navbar, .page-title-container .btn, footer { display: none !important; }
    main { margin-left: 0 !important; }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Corte Detallado por Productos</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('reportes') ?>">Reportes</a></li>
                    <li class="breadcrumb-item active">Corte Detallado</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="iconsminds-printer"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<!-- Filtro -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="<?= base_url('reportes/cortecaja2') ?>" class="form-inline">
            <label class="mr-2">Fecha:</label>
            <input type="text" name="fecha" id="inputFecha" class="form-control mr-2"
                   value="<?= esc($fecha) ?>" placeholder="YYYY-MM-DD">
            <button type="submit" class="btn btn-primary">Ver Reporte</button>
        </form>
    </div>
</div>

<!-- Tabla de productos vendidos -->
<div class="card">
    <div class="card-header font-weight-bold">
        Productos Vendidos — <?= esc($fecha) ?>
    </div>
    <div class="table-responsive">
        <table id="tablaDetalle" class="table table-striped table-sm mb-0 data-table">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>SKU</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio</th>
                    <th class="text-right">Importe</th>
                    <th>Tipo Pago</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalImporte = 0;
                $folioActual  = null;
                foreach ($notas as $n):
                    $totalImporte += $n['importe'] ?? 0;
                    $cambioFolio = ($folioActual !== (int)$n['folio']);
                    $folioActual = (int)$n['folio'];
                ?>
                <tr class="<?= $cambioFolio ? 'table-active' : '' ?>">
                    <td><?= (int)$n['folio'] ?></td>
                    <td><?= date('d/m/Y', strtotime($n['fecha_inicial'])) ?></td>
                    <td><?= esc($n['cliente'] ?? '') ?></td>
                    <td><?= esc($n['vendedor'] ?? '') ?></td>
                    <td><?= esc($n['sku']) ?></td>
                    <td class="text-right"><?= (int)$n['cantidad'] ?></td>
                    <td class="text-right">$<?= number_format($n['precio'] ?? 0, 2) ?></td>
                    <td class="text-right">$<?= number_format($n['importe'] ?? 0, 2) ?></td>
                    <td><?= esc($n['tipopago'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($notas)): ?>
                <tr><td colspan="9" class="text-center text-muted py-3">Sin notas para esta fecha.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($notas)): ?>
            <tfoot>
                <tr class="font-weight-bold bg-light">
                    <td colspan="7" class="text-right">Total Importe:</td>
                    <td class="text-right text-success">$<?= number_format($totalImporte, 2) ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/js/vendor/bootstrap-datepicker.min.js') ?>"></script>
<script>
$('#inputFecha').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true });
$('#tablaDetalle').DataTable({
    responsive: true,
    pageLength: 50,
    order: [[0, 'asc']],
    language: { url: '/assets/js/vendor/datatables.spanish.json' }
});
</script>
<?= $this->endSection() ?>
