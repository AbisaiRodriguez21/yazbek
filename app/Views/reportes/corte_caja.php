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
            <h1>Corte de Caja</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('reportes') ?>">Reportes</a></li>
                    <li class="breadcrumb-item active">Corte de Caja</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2 d-flex gap-2">
            <a href="<?= base_url('reportes/excel?fecha=' . esc($fecha)) ?>"
               class="btn btn-success mr-2">
                <i class="iconsminds-microsoft"></i> Excel
            </a>
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="iconsminds-printer"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<!-- Filtro -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="<?= base_url('reportes/cortecaja') ?>" class="form-inline">
            <label class="mr-2">Fecha:</label>
            <input type="text" name="fecha" id="inputFecha" class="form-control mr-2"
                   value="<?= esc($fecha) ?>" placeholder="YYYY-MM-DD">
            <button type="submit" class="btn btn-primary mr-2">Ver Reporte</button>
            <a href="<?= base_url('reportes/excel?fecha=' . esc($fecha)) ?>" class="btn btn-success">
                Descargar Excel
            </a>
        </form>
    </div>
</div>

<!-- Desglose por tipo de pago -->
<div class="row mb-3">
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header font-weight-bold">
                Desglose por Tipo de Pago — <?= esc($fecha) ?>
            </div>
            <div class="card-body p-0 pt-2">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Tipo de Pago</th>
                            <th class="text-right">Monto</th>
                            <th class="text-right">Cargos</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($desglose as $d): ?>
                        <tr>
                            <td><?= esc($d['tipopago']) ?></td>
                            <td class="text-right">$<?= number_format($d['monto'] ?? 0, 2) ?></td>
                            <td class="text-right">$<?= number_format($d['cargos'] ?? 0, 2) ?></td>
                            <td class="text-right font-weight-bold">$<?= number_format($d['total'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($desglose)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos para esta fecha.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between font-weight-bold">
                <span>TOTAL GENERAL</span>
                <span class="text-success font-size-lg">$<?= number_format($totalGeneral, 2) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header font-weight-bold">Resumen Visual</div>
            <div class="card-body d-flex flex-column justify-content-center">
                <?php foreach ($desglose as $d): ?>
                <?php
                    $pct = $totalGeneral > 0 ? ($d['total'] / $totalGeneral * 100) : 0;
                    $badgeClass = ['Efectivo'=>'success','Tarjeta'=>'info','Crédito'=>'warning'];
                    $cls = 'primary';
                    foreach ($badgeClass as $k => $v) {
                        if (stripos($d['tipopago'], $k) !== false) { $cls = $v; break; }
                    }
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><?= esc($d['tipopago']) ?></span>
                        <span>$<?= number_format($d['total'] ?? 0, 2) ?> (<?= number_format($pct, 1) ?>%)</span>
                    </div>
                    <div class="progress" style="height:10px">
                        <div class="progress-bar bg-<?= $cls ?>"
                             style="width:<?= number_format($pct, 1) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($desglose)): ?>
                <p class="text-muted text-center">Sin datos para graficar.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detalle de notas -->
<div class="card">
    <div class="card-header font-weight-bold">Detalle de Notas Pagadas</div>
    <div class="table-responsive">
        <table id="tablaNotas" class="table table-striped table-sm mb-0 data-table">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha/Hora</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Tipo Pago</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">Desc.</th>
                    <th class="text-right">IVA</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notas as $n): ?>
                <tr>
                    <td><?= (int)$n['folio'] ?></td>
                    <td><?= esc($n['fecha_inicial']) ?></td>
                    <td><?= esc($n['cliente'] ?? '') ?></td>
                    <td><?= esc($n['vendedor'] ?? '') ?></td>
                    <td><?= esc($n['tipopago'] ?? '') ?></td>
                    <td class="text-right">$<?= number_format($n['subTotal'] ?? 0, 2) ?></td>
                    <td class="text-right"><?= number_format($n['descuento'] ?? 0, 2) ?>%</td>
                    <td class="text-right">$<?= number_format($n['iva'] ?? 0, 2) ?></td>
                    <td class="text-right font-weight-bold">$<?= number_format($n['total'] ?? 0, 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($notas)): ?>
                <tr><td colspan="9" class="text-center text-muted py-3">Sin notas para esta fecha.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($notas)): ?>
            <tfoot>
                <tr class="font-weight-bold bg-light">
                    <td colspan="8" class="text-right">Total General:</td>
                    <td class="text-right text-success">$<?= number_format($totalGeneral, 2) ?></td>
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
$('#tablaNotas').DataTable({
    responsive: true,
    pageLength: 25,
    order: [[0, 'desc']],
    language: { url: '/assets/js/vendor/datatables.spanish.json' }
});
</script>
<?= $this->endSection() ?>
