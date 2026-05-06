<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Cobrar — Notas del Día</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Caja</a></li>
                    <li class="breadcrumb-item active">Cobrar</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2 d-flex align-items-center">
            <span class="text-muted mr-3"><?= date('d/m/Y') ?></span>
            <a href="<?= base_url('caja/corte') ?>" class="btn btn-outline-secondary">
                <i class="iconsminds-receipt-4"></i> Corte
            </a>
        </div>
    </div>
</div>

<!-- Búsqueda rápida por folio -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-end">
            <div class="col-md-5">
                <label>Buscar folio específico</label>
                <div class="input-group">
                    <input type="number" id="inputFolio" class="form-control" placeholder="No. de folio">
                    <div class="input-group-append">
                        <a id="btnIrFolio" href="#" class="btn btn-primary">Ir</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de notas del día -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tablaCaja" class="table table-striped mb-0 data-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Tipo Pago</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                        <th>Verificado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notas as $n): ?>
                    <?php
                        $statusClass = [1=>'primary',2=>'info',3=>'danger',4=>'warning',5=>'success'];
                        $statusName  = ['','Abierta','En proceso','Cancelada','Anticipo','Pagada'];
                        $s = (int)$n['idstatus'];
                    ?>
                    <tr>
                        <td><strong><?= (int)$n['folio'] ?></strong></td>
                        <td><?= esc($n['fecha']) ?></td>
                        <td><?= esc($n['cliente']) ?></td>
                        <td><?= esc($n['vendedor']) ?></td>
                        <td><?= esc($n['tipopago']) ?></td>
                        <td class="text-right font-weight-bold">$<?= number_format($n['total'] ?? 0, 2) ?></td>
                        <td>
                            <span class="badge badge-<?= $statusClass[$s] ?? 'secondary' ?>">
                                <?= $statusName[$s] ?? $s ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($n['verificado']): ?>
                            <i class="iconsminds-yes text-success" title="Verificado"></i>
                            <?php else: ?>
                            <i class="iconsminds-close text-muted" title="Pendiente"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= base_url('caja/folio/' . (int)$n['folio']) ?>"
                               class="btn btn-sm btn-outline-primary">Ver</a>
                            <?php if ($s !== 5): ?>
                            <a href="<?= base_url('caja/venta/' . (int)$n['folio']) ?>"
                               class="btn btn-sm btn-success ml-1">Cobrar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($notas)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Sin notas para hoy.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
$('#tablaCaja').DataTable({
    responsive: true,
    order: [[0, 'desc']],
    pageLength: 25,
    language: { url: '/assets/js/vendor/datatables.spanish.json' }
});

$('#btnIrFolio').on('click', function(e) {
    e.preventDefault();
    var folio = $('#inputFolio').val().trim();
    if (folio) {
        window.location.href = '<?= base_url('caja/folio/') ?>' + folio;
    } else {
        alert('Ingresa un número de folio.');
    }
});
</script>
<?= $this->endSection() ?>
