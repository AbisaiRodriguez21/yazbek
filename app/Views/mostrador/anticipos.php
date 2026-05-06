<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Anticipos</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('mostrador') ?>">Mostrador</a></li>
                <li class="breadcrumb-item active">Anticipos</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tablaAnticipos" class="table table-striped data-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anticipos as $a): ?>
                    <tr>
                        <td><?= (int)$a['folio'] ?></td>
                        <td><?= date('d/m/Y', strtotime($a['fecha_inicial'])) ?></td>
                        <td><?= esc($a['cliente'] ?? '') ?></td>
                        <td><?= esc($a['vendedor'] ?? '') ?></td>
                        <td class="text-right">$<?= number_format($a['total'] ?? 0, 2) ?></td>
                        <td><span class="badge badge-warning">Anticipo</span></td>
                        <td>
                            <a href="<?= base_url('mostrador/anticipos/folio/' . (int)$a['folio']) ?>"
                               class="btn btn-sm btn-outline-primary">
                                Ver Detalle
                            </a>
                            <a href="<?= base_url('mostrador/venta/' . (int)$a['folio'] . '/productos') ?>"
                               class="btn btn-sm btn-outline-success">
                                Continuar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($anticipos)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No hay anticipos pendientes.</td>
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
$('#tablaAnticipos').DataTable({
    responsive: true,
    order: [[0, 'desc']],
    language: { url: '/assets/js/vendor/datatables.spanish.json' }
});
</script>
<?= $this->endSection() ?>
