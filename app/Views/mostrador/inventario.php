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

<div class="audit-card">
    <div class="audit-card-header">
        <i class="simple-icon-layers"></i>
        <span>Inventario de Productos</span>
        <span class="badge-count" id="totalProductosMostrador"><?= count($productos) ?> productos</span>
    </div>
    <div style="padding: 16px 20px 20px;">
        <table id="tablaInventario" class="table responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Descripción</th>
                    <th>Color</th>
                    <th class="text-right">P. Menudeo</th>
                    <th class="text-right">P. Mayoreo</th>
                    <th class="text-right">Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                <tr>
                    <td><?= esc($p['sku']) ?></td>
                    <td><?= esc($p['Descripcion_corta'] ?? $p['Descripcion_Larga'] ?? '') ?></td>
                    <td><?= esc($p['Color'] ?? '') ?></td>
                    <td class="text-right">$<?= number_format($p['pMenudeo'] ?? 0, 2) ?></td>
                    <td class="text-right">$<?= number_format($p['pMayoreo'] ?? 0, 2) ?></td>
                    <td class="text-right">
                        <?php $stock = (int)$p['piezas']; ?>
                        <span data-stock-sku="<?= esc($p['sku']) ?>"
                              class="badge badge-<?= $stock > 10 ? 'success' : ($stock > 0 ? 'warning' : 'danger') ?>">
                            <?= $stock ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
(function initInventarioMostradorTable() {
    var $table = $('#tablaInventario');
    if (!$table.length) return;
    if ($.fn.DataTable.isDataTable($table[0])) {
        $table.DataTable().destroy();
    }
    $table.DataTable({
        responsive: true,
        pageLength: 25,
        order: [[1, 'asc']],
        language: {
            processing:   'Procesando...',
            search:       'Buscar:',
            lengthMenu:   'Mostrar _MENU_ registros',
            info:         'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty:    'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            zeroRecords:  'No se encontraron resultados',
            emptyTable:   'No hay datos disponibles',
            paginate: { first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último' }
        }
    });
})();
</script>
<?= $this->endSection() ?>
