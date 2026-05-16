<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<?php /* DataTables CSS ya viene en el layout — no duplicar */ ?>
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

            <!-- ERD — Exportar inventario completo como XLS -->
            <div class="col-md-6 col-lg-6 col-12 mb-4">
                <div class="card d-flex flex-row pt-3 pb-3">
                    <a href="<?= base_url('admin/inventario/exportar') ?>" class="d-flex">
                        <div class="rounded-circle m-4 align-self-center list-thumbnail-letters small bg-success">
                            ERD
                        </div>
                    </a>
                    <div class="d-flex flex-grow-1 min-width-zero">
                        <div class="card-body pl-0 align-self-center d-flex flex-column flex-lg-row justify-content-between min-width-zero">
                            <div class="min-width-zero">
                                <a href="<?= base_url('admin/inventario/exportar') ?>">
                                    <p class="list-item-heading mb-1 truncate">Reporte diario</p>
                                </a>
                                <p class="mb-2 text-muted text-small">Exportar inventario completo: <?= date('Y-m-d') ?></p>
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

        <!-- ── Sección de importación con 3 pestañas ── -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Importar datos al inventario</h5>

                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-4" id="importTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="tab-producto-lnk" data-toggle="tab"
                                   href="#tab-producto" role="tab">
                                    <i class="simple-icon-plus mr-1"></i> Nuevo Producto
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab-precios-lnk" data-toggle="tab"
                                   href="#tab-precios" role="tab">
                                    <i class="simple-icon-tag mr-1"></i> Actualizar Precios
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab-stock-lnk" data-toggle="tab"
                                   href="#tab-stock" role="tab">
                                    <i class="simple-icon-layers mr-1"></i> Actualizar Stock
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">

                            <!-- ── Tab 1: Nuevo Producto ── -->
                            <div class="tab-pane fade show active" id="tab-producto" role="tabpanel">
                                <p class="text-muted mb-2">
                                    Agrega productos que <strong>no existen</strong> aún en el inventario.
                                    Si el SKU ya existe, la fila se omite sin modificar nada.
                                </p>
                                <div class="alert alert-info py-2 mb-3" style="font-size:.85rem">
                                    <strong>Formato del CSV — mismo que exporta "Exportar base de datos":</strong><br>
                                    <code>Estilo, SKU, Descripcion Corta, Descripcion Larga, Talla, Color, Precio Menudeo, Precio Mayoreo, Piezas</code><br>
                                    <small>Puedes usar el archivo exportado por el botón <strong>EBD</strong> como plantilla. Si el SKU ya existe en el inventario, la fila se omite.</small>
                                </div>
                                <form action="<?= base_url('admin/inventario/importar/producto') ?>"
                                      method="POST" enctype="multipart/form-data"
                                      onsubmit="return validarCsv(this)">
                                    <?= csrf_field() ?>
                                    <div class="input-group mb-2">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" name="archivo_csv"
                                                   id="file-producto" accept=".csv,.txt">
                                            <label class="custom-file-label" for="file-producto">Selecciona archivo CSV</label>
                                        </div>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-success">
                                                <i class="simple-icon-cloud-upload mr-1"></i> Subir
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- ── Tab 2: Actualizar Precios ── -->
                            <div class="tab-pane fade" id="tab-precios" role="tabpanel">
                                <p class="text-muted mb-2">
                                    Actualiza <strong>solo los precios</strong> de los productos existentes.
                                    Se busca por SKU; si el SKU no existe en la BD se omite.
                                </p>
                                <div class="alert alert-info py-2 mb-3" style="font-size:.85rem">
                                    <strong>Formato del CSV — columnas requeridas:</strong><br>
                                    <code>SKU, pMayoreo, pMenudeo</code><br>
                                    <small>Ejemplo: <em>B0300P010001, 25.00, 36.00</em></small>
                                </div>
                                <form action="<?= base_url('admin/inventario/importar/precios') ?>"
                                      method="POST" enctype="multipart/form-data"
                                      onsubmit="return validarCsv(this)">
                                    <?= csrf_field() ?>
                                    <div class="input-group mb-2">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" name="archivo_csv"
                                                   id="file-precios" accept=".csv,.txt">
                                            <label class="custom-file-label" for="file-precios">Selecciona archivo CSV</label>
                                        </div>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-warning text-white">
                                                <i class="simple-icon-cloud-upload mr-1"></i> Subir
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- ── Tab 3: Actualizar Stock ── -->
                            <div class="tab-pane fade" id="tab-stock" role="tabpanel">
                                <p class="text-muted mb-2">
                                    Actualiza <strong>solo las piezas en stock</strong> de los productos existentes.
                                    El valor reemplaza el stock actual (no suma). Se busca por SKU.
                                </p>
                                <div class="alert alert-info py-2 mb-3" style="font-size:.85rem">
                                    <strong>Formato del CSV — columnas requeridas:</strong><br>
                                    <code>SKU, piezas</code><br>
                                    <small>Ejemplo: <em>B0300P010001, 20</em></small>
                                </div>
                                <form action="<?= base_url('admin/inventario/importar/stock') ?>"
                                      method="POST" enctype="multipart/form-data"
                                      onsubmit="return validarCsv(this)">
                                    <?= csrf_field() ?>
                                    <div class="input-group mb-2">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" name="archivo_csv"
                                                   id="file-stock" accept=".csv,.txt">
                                            <label class="custom-file-label" for="file-stock">Selecciona archivo CSV</label>
                                        </div>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="simple-icon-cloud-upload mr-1"></i> Subir
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                        </div><!-- /tab-content -->
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

        <!-- ── Editar producto por SKU ── -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Editar producto por SKU</h5>
                        <p class="text-muted mb-3">Busca un producto por su SKU y edita cualquier campo directamente.</p>
                        <div class="input-group" style="max-width:420px">
                            <input type="text" id="skuBusqueda" class="form-control"
                                   placeholder="Ej: B0300P010001" style="text-transform:uppercase">
                            <div class="input-group-append">
                                <button class="btn btn-primary" id="btnBuscarSku" type="button">
                                    <i class="simple-icon-magnifier mr-1"></i> Buscar
                                </button>
                            </div>
                        </div>
                        <div id="skuError" class="text-danger mt-2" style="display:none"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensaje de confirmación para edición inline -->
        <div id="status-inline" class="alert alert-success rounded" style="display:none"></div>

        <div class="separator mb-5"></div>

        <!-- Tabla de inventario — igual al original -->
        <div class="row">
            <div class="col-12 mb-4">
                <table id="datatableProductos" class="table responsive nowrap w-100">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Estilo / Descripción / Color / Talla</th>
                            <th>P. Mayoreo</th>
                            <th>P. Menudeo</th>
                            <th>Piezas</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $p): ?>
                        <tr data-id="<?= (int)$p['id'] ?>" data-sku="<?= esc($p['sku']) ?>">
                            <td><?= esc($p['sku']) ?></td>
                            <td><?= esc($p['estilo']) ?> - <?= esc($p['Descripcion_Larga']) ?> - <?= esc($p['Color']) ?> - <?= esc($p['Talla']) ?></td>
                            <td contenteditable="true" id="pMayoreo:<?= $p['id'] ?>"><?= $p['pMayoreo'] ?? 0 ?></td>
                            <td contenteditable="true" id="pMenudeo:<?= $p['id'] ?>"><?= $p['pMenudeo'] ?? 0 ?></td>
                            <td contenteditable="true" id="piezas:<?= $p['id'] ?>"><?= (int)($p['piezas'] ?? 0) ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-outline-danger btn-eliminar-producto"
                                        data-id="<?= (int)$p['id'] ?>" data-sku="<?= esc($p['sku']) ?>">
                                    <i class="simple-icon-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ── Modal Confirmar Eliminación ── -->
<div class="modal fade" id="modalEliminarProducto" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="simple-icon-trash mr-1"></i> Eliminar producto</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-1">¿Eliminar el producto?</p>
                <strong id="eliminarSkuLabel" class="d-block mb-2 text-danger"></strong>
                <small class="text-muted">Esta acción no se puede deshacer.</small>
                <div id="eliminarMsg" class="mt-3" style="display:none"></div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">
                    <i class="simple-icon-trash mr-1"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Editar Producto ── -->
<div class="modal fade" id="modalEditarProducto" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Producto — <span id="modalSkuLabel"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="formEditarProducto">
                    <input type="hidden" id="editId" name="id">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>SKU</label>
                                <input type="text" class="form-control" id="editSku" readonly
                                       style="background:#f5f5f5">
                            </div>
                            <div class="form-group">
                                <label>Estilo</label>
                                <input type="text" class="form-control" id="editEstilo" name="estilo">
                            </div>
                            <div class="form-group">
                                <label>Descripción Corta</label>
                                <input type="text" class="form-control" id="editDescripcionCorta" name="Descripcion_corta">
                            </div>
                            <div class="form-group">
                                <label>Descripción Larga</label>
                                <input type="text" class="form-control" id="editDescripcion" name="Descripcion_Larga">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Color</label>
                                <input type="text" class="form-control" id="editColor" name="Color">
                            </div>
                            <div class="form-group">
                                <label>Talla</label>
                                <input type="text" class="form-control" id="editTalla" name="Talla">
                            </div>
                            <div class="form-group">
                                <label>Precio Mayoreo</label>
                                <input type="number" step="0.01" class="form-control" id="editMayoreo" name="pMayoreo">
                            </div>
                            <div class="form-group">
                                <label>Precio Menudeo</label>
                                <input type="number" step="0.01" class="form-control" id="editMenudeo" name="pMenudeo">
                            </div>
                            <div class="form-group">
                                <label>Piezas en stock</label>
                                <input type="number" class="form-control" id="editPiezas" name="piezas">
                            </div>
                        </div>
                    </div>
                    <div id="editMsg" class="mt-2" style="display:none"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnGuardarProducto">
                    <i class="simple-icon-check mr-1"></i> Guardar cambios
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
(function initInventarioPage() {
    var $table = $('#datatableProductos');
    if ($table.length && !$.fn.DataTable.isDataTable($table[0])) {
        $table.DataTable({
            responsive: true,
            pageLength: 25,
            language: {
                search:       'Buscar:',
                lengthMenu:   'Mostrar _MENU_ registros',
                info:         'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty:    'Mostrando 0 a 0 de 0 registros',
                zeroRecords:  'No se encontraron resultados',
                paginate: { first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último' }
            }
        });
    }

    // Edición inline igual que el original (ajax.php → /admin/inventario/ajax)
    var message_status = $('#status-inline');

    $('td[contenteditable=true]').off('blur').on('blur', function () {
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

    // Mostrar nombre del archivo seleccionado en cualquier custom-file-input
    $(document).off('change', '.custom-file-input').on('change', '.custom-file-input', function () {
        var fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').text(fileName || 'Selecciona archivo CSV');
    });
})();

// ── Buscar producto por SKU y abrir modal ──
(function () {
    var csrfToken = '<?= csrf_token() ?>';
    var csrfHash  = '<?= csrf_hash() ?>';

    function buscarSku() {
        var sku = document.getElementById('skuBusqueda').value.trim().toUpperCase();
        var errEl = document.getElementById('skuError');
        errEl.style.display = 'none';

        if (!sku) { errEl.textContent = 'Ingresa un SKU.'; errEl.style.display = ''; return; }

        fetch('<?= base_url('admin/inventario/buscar-sku') ?>?sku=' + encodeURIComponent(sku), {
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                errEl.textContent = data.error || 'SKU no encontrado.';
                errEl.style.display = '';
                return;
            }
            var p = data.producto;
            document.getElementById('editId').value                 = p.id;
            document.getElementById('editSku').value                = p.sku;
            document.getElementById('editEstilo').value             = p.estilo || '';
            document.getElementById('editDescripcionCorta').value   = p.Descripcion_corta || '';
            document.getElementById('editDescripcion').value        = p.Descripcion_Larga || '';
            document.getElementById('editColor').value              = p.Color || '';
            document.getElementById('editTalla').value       = p.Talla || '';
            document.getElementById('editMayoreo').value     = p.pMayoreo || 0;
            document.getElementById('editMenudeo').value     = p.pMenudeo || 0;
            document.getElementById('editPiezas').value      = p.piezas || 0;
            document.getElementById('modalSkuLabel').textContent = p.sku;
            document.getElementById('editMsg').style.display = 'none';
            $('#modalEditarProducto').modal('show');
        })
        .catch(function() {
            errEl.textContent = 'Error de conexión. Intenta de nuevo.';
            errEl.style.display = '';
        });
    }

    document.getElementById('btnBuscarSku').addEventListener('click', buscarSku);
    document.getElementById('skuBusqueda').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') buscarSku();
    });

    document.getElementById('btnGuardarProducto').addEventListener('click', function () {
        var form = document.getElementById('formEditarProducto');
        var fd   = new FormData(form);
        fd.set(csrfToken, csrfHash);

        var msgEl = document.getElementById('editMsg');
        msgEl.style.display = 'none';

        // Capturar valores actuales del form para actualizar la tabla
        var skuActual      = document.getElementById('editSku').value;
        var estiloActual   = document.getElementById('editEstilo').value;
        var descActual     = document.getElementById('editDescripcion').value;
        var colorActual    = document.getElementById('editColor').value;
        var tallaActual    = document.getElementById('editTalla').value;
        var mayoreoActual  = parseFloat(document.getElementById('editMayoreo').value || 0).toFixed(2);
        var menudeoActual  = parseFloat(document.getElementById('editMenudeo').value || 0).toFixed(2);
        var piezasActual   = parseInt(document.getElementById('editPiezas').value  || 0);

        fetch('<?= base_url('admin/inventario/actualizar-producto') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.text(); })
        .then(function(texto) {
            var data = null;
            try { data = JSON.parse(texto); } catch(e) {}

            if (data && data.ok === false) {
                msgEl.className = 'mt-2 alert alert-danger';
                msgEl.textContent = data.error || 'Error al guardar.';
                msgEl.style.display = '';
                return;
            }

            // Éxito: mostrar mensaje y actualizar fila, luego cerrar al aceptar
            msgEl.className = 'mt-2 alert alert-success';
            msgEl.textContent = '✔ Producto actualizado correctamente.';
            msgEl.style.display = '';

            // Cambiar botón "Guardar" por "Aceptar" para cerrar
            var btnGuardar = document.getElementById('btnGuardarProducto');
            btnGuardar.textContent = 'Aceptar';
            btnGuardar.className = 'btn btn-success';
            btnGuardar.onclick = function () {
                $('#modalEditarProducto').modal('hide');
                // Restaurar botón para la próxima búsqueda
                setTimeout(function () {
                    btnGuardar.textContent = 'Guardar cambios';
                    btnGuardar.onclick = null;
                    msgEl.style.display = 'none';
                }, 400);
            };

            // Actualizar la fila del DataTable que coincida con el SKU
            var dt     = $('#datatableProductos').DataTable();
            var descCombinada = [estiloActual, descActual, colorActual, tallaActual]
                                    .filter(function(v){ return v.trim() !== ''; })
                                    .join(' - ');

            dt.rows().every(function() {
                var rowData = this.data();          // array de celdas
                var $tr = $(this.node());
                // Comparar SKU (primera columna)
                var skuCelda = $tr.find('td').eq(0).text().trim();
                if (skuCelda === skuActual) {
                    $tr.find('td').eq(1).text(descCombinada);
                    $tr.find('td').eq(2).text(mayoreoActual);
                    $tr.find('td').eq(3).text(menudeoActual);
                    $tr.find('td').eq(4).text(piezasActual);
                }
            });
        })
        .catch(function() {
            msgEl.className = 'mt-2 alert alert-warning';
            msgEl.textContent = 'No se pudo confirmar. Recarga la página para verificar.';
            msgEl.style.display = '';
        });
    });
})();

// ── Eliminar producto ──
(function () {
    var csrfToken = '<?= csrf_token() ?>';
    var csrfHash  = '<?= csrf_hash() ?>';
    var productoId = null;

    // Abrir modal al hacer clic en cualquier botón eliminar
    $(document).on('click', '.btn-eliminar-producto', function () {
        productoId = $(this).data('id');
        var sku    = $(this).data('sku');
        document.getElementById('eliminarSkuLabel').textContent = sku;
        var msgEl = document.getElementById('eliminarMsg');
        msgEl.style.display = 'none';
        var btn = document.getElementById('btnConfirmarEliminar');
        btn.disabled = false;
        btn.innerHTML = '<i class="simple-icon-trash mr-1"></i> Eliminar';
        $('#modalEliminarProducto').modal('show');
    });

    // Confirmar eliminación
    document.getElementById('btnConfirmarEliminar').addEventListener('click', function () {
        if (!productoId) return;
        var btn   = this;
        var msgEl = document.getElementById('eliminarMsg');
        btn.disabled = true;
        btn.textContent = 'Eliminando...';

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);
        fd.append('id', productoId);

        fetch('<?= base_url('admin/inventario/eliminar') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.text(); })
        .then(function(texto) {
            var data = null;
            try { data = JSON.parse(texto); } catch(e) {}

            if (data && data.ok === false) {
                msgEl.className = 'alert alert-danger';
                msgEl.textContent = data.error || 'Error al eliminar.';
                msgEl.style.display = '';
                btn.disabled = false;
                btn.innerHTML = '<i class="simple-icon-trash mr-1"></i> Eliminar';
                return;
            }

            // Éxito: eliminar la fila del DataTable y cerrar el modal
            var dt = $('#datatableProductos').DataTable();
            dt.rows(function(i, d, node) {
                return $(node).data('id') == productoId;
            }).remove().draw(false);

            $('#modalEliminarProducto').modal('hide');
        })
        .catch(function() {
            msgEl.className = 'alert alert-warning';
            msgEl.textContent = 'Error de conexión. Intenta de nuevo.';
            msgEl.style.display = '';
            btn.disabled = false;
            btn.innerHTML = '<i class="simple-icon-trash mr-1"></i> Eliminar';
        });
    });
})();

// Validación de extensión antes de enviar el formulario
function validarCsv(form) {
    var input = form.querySelector('input[type="file"]');
    if (!input || !input.files.length) {
        alert('Selecciona un archivo CSV antes de subir.');
        return false;
    }
    var nombre = input.files[0].name.toLowerCase();
    if (!nombre.endsWith('.csv') && !nombre.endsWith('.txt')) {
        alert('El archivo debe ser CSV (.csv).\nNo se aceptan archivos .xlsx ni otros formatos directamente.\nSi tienes un Excel, guárdalo como CSV desde "Guardar como" → CSV UTF-8.');
        return false;
    }
    return true;
}
</script>
<?= $this->endSection() ?>
