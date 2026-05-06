<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-datepicker3.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Corte de Caja</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Caja</a></li>
                    <li class="breadcrumb-item active">Corte</li>
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

<!-- Filtro por fecha -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="<?= base_url('caja/corte') ?>" class="form-inline">
            <label class="mr-2">Fecha:</label>
            <input type="text" name="fecha" id="inputFecha" class="form-control mr-2"
                   value="<?= esc($fecha) ?>" placeholder="YYYY-MM-DD">
            <button type="submit" class="btn btn-primary">Ver Corte</button>
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
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Tipo de Pago</th>
                            <th class="text-right">Monto</th>
                            <th class="text-right">Cargos</th>
                            <th class="text-right">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($desglose as $d): ?>
                        <tr>
                            <td><?= esc($d['tipopago']) ?></td>
                            <td class="text-right">$<?= number_format($d['monto'] ?? 0, 2) ?></td>
                            <td class="text-right">$<?= number_format($d['cargos'] ?? 0, 2) ?></td>
                            <td class="text-right"><?= (int)($d['cantidad'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($desglose)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer font-weight-bold d-flex justify-content-between">
                <span>Total del Día</span>
                <span class="text-success">$<?= number_format($totalDia, 2) ?></span>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header font-weight-bold">Notas Pagadas</div>
            <div class="table-responsive">
                <table class="table table-striped table-sm mb-0" id="tablaCorte">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th class="text-right">Total</th>
                            <th>Tipo Pago</th>
                            <th>Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notas as $n): ?>
                        <tr>
                            <td><?= (int)$n['folio'] ?></td>
                            <td><?= esc($n['cliente'] ?? '') ?></td>
                            <td><?= esc($n['vendedor'] ?? '') ?></td>
                            <td class="text-right">$<?= number_format($n['total'] ?? 0, 2) ?></td>
                            <td><?= esc($n['tipopago'] ?? '') ?></td>
                            <td>
                                <a href="<?= base_url('caja/folio/' . (int)$n['folio']) ?>"
                                   class="btn btn-xs btn-outline-primary">Detalle</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($notas)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Sin notas pagadas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/js/vendor/bootstrap-datepicker.min.js') ?>"></script>
<script>
$('#inputFecha').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
});
$('#tablaCorte').DataTable({
    responsive: true,
    pageLength: 20,
    order: [[0, 'desc']],
    language: { url: '/assets/js/vendor/datatables.spanish.json' }
});
</script>
<?= $this->endSection() ?>
