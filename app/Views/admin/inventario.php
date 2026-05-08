<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/datatables.responsive.bootstrap4.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1>Inventario</h1>
        <nav class="breadcrumb-container d-none d-sm-block d-lg-inline-block" aria-label="breadcrumb">
            <ol class="breadcrumb pt-0">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="#">Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">Data</li>
            </ol>
        </nav>

        <!-- Tarjetas de acciones — igual al original -->
        <div class="row mb-4">

            <!-- ERD — Reporte diario (descarga XLS igual que el original) -->
            <div class="col-md-6 col-lg-6 col-12 mb-4">
                <div class="card d-flex flex-row pt-3 pb-3">
                    <a href="<?= base_url('admin/reportediario/dia') ?>" class="d-flex">
                        <div class="rounded-circle m-4 align-self-center list-thumbnail-letters small bg-success">
                            ERD
                        </div>
                    </a>
                    <div class="d-flex flex-grow-1 min-width-zero">
                        <div class="card-body pl-0 align-self-center d-flex flex-column flex-lg-row justify-content-between min-width-zero">
                            <div class="min-width-zero">
                                <a href="<?= base_url('admin/reportediario/dia') ?>">
                                    <p class="list-item-heading mb-1 truncate">Reporte diario</p>
                                </a>
                                <p class="mb-2 text-muted text-small">Exportar lista de productos del dia de hoy: <?= date('Y-m-d') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EBD — Exportar base de datos -->
            <div class="col-md-6 col-lg-6 col-12 mb-4">
                <div class="card d-flex flex-row pt-3 pb-3">
                    <a href="<?= base_url('admin/exportar') ?>" class="d-flex">
                        <div class="rounded-circle m-4 align-self-center list-thumbnail-letters small bg-info">
                            EBD
                        </div>
                    </a>
                    <div class="d-flex flex-grow-1 min-width-zero">
                        <div class="card-body pl-0 align-self-center d-flex flex-column flex-lg-row justify-content-between min-width-zero">
                            <div class="min-width-zero">
                                <a href="<?= base_url('admin/exportar') ?>">
                                    <p class="list-item-heading mb-1 truncate">Exportar base de datos</p>
                                </a>
                                <p class="mb-2 text-muted text-small">Exporta base de datos completa</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row mb-4">
            <!-- ABD — Actualizar base de datos (subir CSV) -->
            <div class="col-md-12 col-lg-12 col-12 mb-4">
                <div class="card d-flex flex-row">
                    <a class="d-flex" href="#">
                        <div class="rounded-circle m-4 align-self-center list-thumbnail-letters small bg-danger">
                            ABD
                        </div>
                    </a>
                    <div class="d-flex flex-grow-1 min-width-zero">
                        <div class="card-body pl-0 align-self-center d-flex flex-column flex-lg-row justify-content-between min-width-zero">
                            <div class="min-width-zero">
                                <p class="list-item-heading mb-1 truncate">Actualizar base de datos</p>
                                <p class="mb-2 text-muted text-small">Actualiza únicamente los productos a sumar</p>
                                <form action="<?= base_url('admin/importar/subir') ?>" method="POST" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <div class="input-group mb-3">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" name="dataCliente" id="file-input" accept=".csv">
                                            <label class="custom-file-label" for="file-input">Selecciona archivo CSV</label>
                                        </div>
                                        <div class="input-group-append">
                                            <input type="submit" name="subir" class="input-group-text" value="Actualizar">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success rounded"><?= session()->getFlashdata('success') ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger rounded"><?= session()->getFlashdata('error') ?></div>
        <?php endif; ?>

        <!-- Mensaje de confirmación para edición inline -->
        <div id="status-inline" class="alert alert-success rounded" style="display:none"></div>

        <div class="separator mb-5"></div>

        <!-- Tabla de inventario — igual al original -->
        <div class="row">
            <div class="col-12 mb-4">
                <table id="datatableProductos" class="data-table responsive nowrap w-100">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Estilo / Descripción / Color / Talla</th>
                            <th>P. Mayoreo</th>
                            <th>P. Menudeo</th>
                            <th>Piezas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $p): ?>
                        <tr>
                            <td><?= esc($p['sku']) ?></td>
                            <td><?= esc($p['estilo']) ?> - <?= esc($p['Descripcion_Larga']) ?> - <?= esc($p['Color']) ?> - <?= esc($p['Talla']) ?></td>
                            <td contenteditable="true" id="pMayoreo:<?= $p['id'] ?>"><?= $p['pMayoreo'] ?? 0 ?></td>
                            <td contenteditable="true" id="pMenudeo:<?= $p['id'] ?>"><?= $p['pMenudeo'] ?? 0 ?></td>
                            <td contenteditable="true" id="piezas:<?= $p['id'] ?>"><?= (int)($p['piezas'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/js/vendor/datatables.min.js') ?>"></script>
<script>
$(document).ready(function () {
    $('#datatableProductos').DataTable({
        responsive: true,
        pageLength: 25,
        language: { url: '<?= base_url('assets/js/vendor/datatables.spanish.json') ?>' }
    });

    // Edición inline igual que el original (ajax.php → /admin/inventario/ajax)
    var message_status = $('#status-inline');

    $('td[contenteditable=true]').on('blur', function () {
        var fieldId = $(this).attr('id');   // ej: pMayoreo:42
        var value   = $(this).text().trim();

        $.post('<?= base_url('admin/inventario/ajax') ?>', {
            '<?= csrf_token() ?>': '<?= csrf_hash() ?>',
            [fieldId]: value
        }, function (data) {
            if (data !== '') {
                message_status.text(data).show();
                setTimeout(function () { message_status.hide(); }, 3000);
            }
        });
    });

    // Mostrar nombre del archivo seleccionado en el label
    $('#file-input').on('change', function () {
        var fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').text(fileName || 'Selecciona archivo CSV');
    });
});
</script>
<?= $this->endSection() ?>
