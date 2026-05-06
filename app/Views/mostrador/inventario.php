<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Inventario</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('mostrador') ?>">Mostrador</a></li>
                <li class="breadcrumb-item active">Inventario</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tablaInventario" class="table table-striped data-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th class="text-right">P. Menudeo</th>
                        <th class="text-right">P. Mayoreo</th>
                        <th class="text-right">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                    <tr>
                        <td><?= esc($p['sku']) ?></td>
                        <td><?= esc($p['Descripcion_corta']) ?></td>
                        <td><?= esc($p['categoria'] ?? '') ?></td>
                        <td class="text-right">$<?= number_format($p['precio_menudeo'] ?? $p['precio'] ?? 0, 2) ?></td>
                        <td class="text-right">$<?= number_format($p['precio_mayoreo'] ?? $p['precioMayoreo'] ?? 0, 2) ?></td>
                        <td class="text-right">
                            <?php $stock = (int)$p['piezas']; ?>
                            <span class="badge badge-<?= $stock > 10 ? 'success' : ($stock > 0 ? 'warning' : 'danger') ?>">
                                <?= $stock ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
$('#tablaInventario').DataTable({
    responsive: true,
    pageLength: 25,
    order: [[1, 'asc']],
    language: { url: '/assets/js/vendor/datatables.spanish.json' }
});
</script>
<?= $this->endSection() ?>
