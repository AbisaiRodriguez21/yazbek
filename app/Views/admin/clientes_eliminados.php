<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
/* ── Tabla principal responsive ── */
#tablaEliminados th, #tablaEliminados td { vertical-align: middle; }

/* ── Historial: tarjetas en mobile, tabla en desktop ── */
.nota-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
    background: #fff;
}
.nota-card .nota-folio  { font-size: 1rem; font-weight: 700; }
.nota-card .nota-total  { font-size: 1.1rem; font-weight: 700; color: #145388; }
.nota-card .nota-label  { font-size: .75rem; color: #888; text-transform: uppercase; }
.nota-card .nota-val    { font-size: .85rem; }

@media (max-width: 575.98px) {
    /* modal historial ocupa toda la pantalla */
    #modalHistorial .modal-dialog { margin: 0; max-width: 100%; }
    #modalHistorial .modal-content { border-radius: 0; min-height: 100vh; }
    #modalHistorial .modal-footer  { flex-wrap: wrap; gap: 8px; }
    #modalHistorial .modal-footer > div { width: 100%; display: flex; gap: 8px; }
    #modalHistorial .modal-footer > div .btn { flex: 1; }
    #modalHistorial .modal-footer > .btn-secondary { width: 100%; }
    /* Ocultar tabla en mobile, mostrar tarjetas */
    #wrapTablaHistorial { display: none !important; }
    #wrapCardsHistorial { display: block !important; }
}
@media (min-width: 576px) {
    /* Mostrar tabla en desktop, ocultar tarjetas */
    #wrapTablaHistorial { display: block; }
    #wrapCardsHistorial { display: none; }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1>Clientes Eliminados</h1>
        <nav class="breadcrumb-container d-none d-sm-block d-lg-inline-block" aria-label="breadcrumb">
            <ol class="breadcrumb pt-0">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url('admin/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item active">Eliminados</li>
            </ol>
        </nav>
        <div class="separator mb-5"></div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
    <div class="col-12">
        <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
    </div>
    <?php endif; ?>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Listado de clientes eliminados</h5>
                <p class="text-muted mb-3 d-none d-sm-block">
                    Estos clientes han sido eliminados de las vistas normales pero sus tickets siguen intactos en la base de datos.
                    Puedes <strong>restaurarlos</strong>, ver su <strong>historial de compras</strong> y
                    <strong>eliminarlos definitivamente</strong> cuando ya no tengan notas.
                </p>
                <table id="tablaEliminados" class="table w-100">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th class="d-none d-md-table-cell">RFC</th>
                            <th class="d-none d-lg-table-cell">Empresa</th>
                            <th class="d-none d-lg-table-cell">Celular</th>
                            <th class="d-none d-xl-table-cell">Email</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Historial ── -->
<div class="modal fade" id="modalHistorial" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:1rem">
                    <span id="historialNombre"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding: 12px">
                <div id="historialCargando" class="text-center py-4"><i>Cargando...</i></div>
                <div id="historialContenido" style="display:none">
                    <div class="alert alert-info py-2 mb-3" style="font-size:.85rem" id="historialResumen"></div>

                    <div id="historialVacio" class="text-center text-muted py-4" style="display:none">
                        <i class="iconsminds-receipt-4 icon-dual icon-lg d-block mb-2"></i>
                        Este cliente no tiene notas registradas.
                    </div>

                    <!-- Tabla (desktop) -->
                    <div id="wrapTablaHistorial" class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="tablaHistorial">
                            <thead class="thead-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Fecha</th>
                                    <th class="text-right">Subtotal</th>
                                    <th class="text-right">Total</th>
                                    <th>Estatus</th>
                                    <th class="text-center">Eliminar</th>
                                </tr>
                            </thead>
                            <tbody id="historialBody"></tbody>
                        </table>
                    </div>

                    <!-- Tarjetas (mobile) -->
                    <div id="wrapCardsHistorial"></div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button type="button" class="btn btn-success btn-sm" id="btnRestaurar">
                        <i class="simple-icon-reload mr-1"></i> Restaurar
                    </button>
                    <button type="button" class="btn btn-danger btn-sm ml-1" id="btnEliminarDefinitivo">
                        <i class="simple-icon-trash mr-1"></i> Eliminar definitivo
                    </button>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal confirmación eliminar nota ── -->
<div class="modal fade" id="modalEliminarNota" tabindex="-1" role="dialog" style="z-index:1060">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" style="font-size:.95rem">Eliminar nota permanentemente</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-1">¿Eliminar el folio <strong id="notaFolioLabel"></strong>?</p>
                <small class="text-muted">Borra nota, detalle y pagos. No se puede deshacer.</small>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmarEliminarNota">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
(function () {
    var csrfToken = '<?= csrf_token() ?>';
    var csrfHash  = '<?= csrf_hash() ?>';
    var clienteActivoId = null;
    var folioAEliminar  = null;

    // ── DataTable — columnas adaptadas al ancho disponible ──
    var dt = $('#tablaEliminados').DataTable({
        processing : true,
        serverSide : true,
        responsive : true,
        ajax: {
            url  : '<?= base_url('admin/clientes/eliminados/datatable') ?>',
            type : 'GET'
        },
        columns: [
            { data: 'nombre' },
            { data: 'RFC',           defaultContent: '—', className: 'd-none d-md-table-cell' },
            { data: 'NombreEmpresa', defaultContent: '—', className: 'd-none d-lg-table-cell' },
            { data: 'celular',       defaultContent: '—', className: 'd-none d-lg-table-cell' },
            { data: 'mail',          defaultContent: '—', className: 'd-none d-xl-table-cell' },
            {
                data: null, orderable: false, className: 'text-center',
                render: function(d) {
                    return '<button class="btn btn-xs btn-primary btn-ver-historial" '
                         + 'data-id="' + d.id + '" data-nombre="' + (d.nombre||'').replace(/"/g,'&quot;') + '">'
                         + '<i class="simple-icon-eye"></i>'
                         + '<span class="d-none d-sm-inline ml-1">Ver historial</span></button>';
                }
            }
        ],
        language: {
            search:       'Buscar:',
            lengthMenu:   'Mostrar _MENU_ registros',
            info:         'Mostrando _START_ a _END_ de _TOTAL_',
            infoEmpty:    'Sin registros',
            zeroRecords:  'No se encontraron clientes eliminados',
            processing:   'Cargando...',
            paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        }
    });

    // ── Abrir historial ──
    $(document).on('click', '.btn-ver-historial', function () {
        clienteActivoId = $(this).data('id');
        document.getElementById('historialNombre').textContent = $(this).data('nombre');
        document.getElementById('historialCargando').style.display = '';
        document.getElementById('historialContenido').style.display = 'none';
        $('#modalHistorial').modal('show');

        fetch('<?= base_url('admin/clientes/eliminados/') ?>' + clienteActivoId + '/historial', {
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('historialCargando').style.display = 'none';
            if (!data.ok) {
                document.getElementById('historialContenido').innerHTML =
                    '<p class="text-danger">' + (data.error || 'Error') + '</p>';
                document.getElementById('historialContenido').style.display = '';
                return;
            }
            renderHistorial(data.notas);
            document.getElementById('historialContenido').style.display = '';
        })
        .catch(function() {
            document.getElementById('historialCargando').style.display = 'none';
            document.getElementById('historialContenido').innerHTML =
                '<p class="text-danger">Error de conexión.</p>';
            document.getElementById('historialContenido').style.display = '';
        });
    });

    function renderHistorial(notas) {
        var tbody   = document.getElementById('historialBody');
        var cards   = document.getElementById('wrapCardsHistorial');
        var vacio   = document.getElementById('historialVacio');
        var wTabla  = document.getElementById('wrapTablaHistorial');
        var resumen = document.getElementById('historialResumen');

        tbody.innerHTML = '';
        cards.innerHTML = '';

        if (!notas || notas.length === 0) {
            wTabla.style.display  = 'none';
            cards.style.display   = 'none';
            vacio.style.display   = '';
            resumen.textContent   = 'Este cliente no tiene notas. Puedes eliminarlo definitivamente.';
        } else {
            vacio.style.display  = 'none';
            // desktop: dejar que CSS maneje display
            wTabla.style.display = '';
            resumen.textContent  = notas.length + ' nota(s). Elimínalas todas para borrar el cliente definitivamente.';

            notas.forEach(function(n) {
                var folio    = n.folio;
                var fecha    = (n.fecha_inicial || '—').split(' ')[0]; // solo fecha
                var subtotal = '$' + parseFloat(n.subTotal || 0).toFixed(2);
                var total    = '$' + parseFloat(n.total    || 0).toFixed(2);
                var status   = n.status || '—';
                var btnEl    = '<button class="btn btn-xs btn-outline-danger btn-eliminar-nota" data-folio="' + folio + '">'
                             + '<i class="simple-icon-trash"></i></button>';

                // Fila tabla desktop
                var tr = document.createElement('tr');
                tr.id  = 'nota-row-' + folio;
                tr.innerHTML =
                    '<td><strong>' + folio + '</strong></td>'
                  + '<td>' + fecha + '</td>'
                  + '<td class="text-right">' + subtotal + '</td>'
                  + '<td class="text-right font-weight-bold">' + total + '</td>'
                  + '<td>' + status + '</td>'
                  + '<td class="text-center">' + btnEl + '</td>';
                tbody.appendChild(tr);

                // Tarjeta mobile
                var card = document.createElement('div');
                card.className = 'nota-card';
                card.id        = 'nota-card-' + folio;
                card.innerHTML =
                    '<div class="d-flex justify-content-between align-items-start mb-1">'
                  +   '<span class="nota-folio">#' + folio + '</span>'
                  +   '<button class="btn btn-xs btn-outline-danger btn-eliminar-nota" data-folio="' + folio + '">'
                  +     '<i class="simple-icon-trash mr-1"></i>Eliminar</button>'
                  + '</div>'
                  + '<div class="row no-gutters">'
                  +   '<div class="col-6"><span class="nota-label">Fecha</span><div class="nota-val">' + fecha + '</div></div>'
                  +   '<div class="col-6 text-right"><span class="nota-label">Total</span><div class="nota-total">' + total + '</div></div>'
                  +   '<div class="col-6 mt-1"><span class="nota-label">Subtotal</span><div class="nota-val">' + subtotal + '</div></div>'
                  +   '<div class="col-6 mt-1 text-right"><span class="nota-label">Estatus</span><div class="nota-val">' + status + '</div></div>'
                  + '</div>';
                cards.appendChild(card);
            });
        }
    }

    // ── Botón eliminar nota ──
    $(document).on('click', '.btn-eliminar-nota', function () {
        folioAEliminar = $(this).data('folio');
        document.getElementById('notaFolioLabel').textContent = '#' + folioAEliminar;
        $('#modalEliminarNota').modal('show');
    });

    document.getElementById('btnConfirmarEliminarNota').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true; btn.textContent = 'Eliminando...';

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);
        fd.append('folio', folioAEliminar);

        fetch('<?= base_url('admin/clientes/eliminar-nota') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false; btn.textContent = 'Eliminar';
            if (data.ok) {
                $('#modalEliminarNota').modal('hide');
                // Quitar fila tabla y tarjeta mobile
                var row  = document.getElementById('nota-row-'  + folioAEliminar);
                var card = document.getElementById('nota-card-' + folioAEliminar);
                if (row)  row.remove();
                if (card) card.remove();
                // Actualizar resumen
                var restantes = document.getElementById('historialBody').querySelectorAll('tr').length;
                if (restantes === 0) {
                    document.getElementById('wrapTablaHistorial').style.display = 'none';
                    document.getElementById('wrapCardsHistorial').style.display = 'none';
                    document.getElementById('historialVacio').style.display = '';
                    document.getElementById('historialResumen').textContent =
                        'Este cliente no tiene notas. Puedes eliminarlo definitivamente.';
                } else {
                    document.getElementById('historialResumen').textContent = restantes + ' nota(s) encontrada(s).';
                }
            } else {
                alert(data.error || 'Error al eliminar la nota.');
            }
        })
        .catch(function() {
            btn.disabled = false; btn.textContent = 'Eliminar';
            alert('Error de conexión.');
        });
    });

    // ── Restaurar cliente ──
    document.getElementById('btnRestaurar').addEventListener('click', function () {
        if (!clienteActivoId || !confirm('¿Restaurar este cliente? Volverá a aparecer en las vistas normales.')) return;
        var fd = new FormData(); fd.append(csrfToken, csrfHash);
        fetch('<?= base_url('admin/clientes/restaurar/') ?>' + clienteActivoId, {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function() { $('#modalHistorial').modal('hide'); dt.ajax.reload(null, false); })
        .catch(function() { alert('Error de conexión.'); });
    });

    // ── Eliminar definitivamente ──
    document.getElementById('btnEliminarDefinitivo').addEventListener('click', function () {
        if (!clienteActivoId || !confirm('¿Eliminar permanentemente este cliente?\nSolo es posible si no tiene notas.')) return;
        var fd = new FormData(); fd.append(csrfToken, csrfHash);
        fetch('<?= base_url('admin/clientes/eliminar-definitivo/') ?>' + clienteActivoId, {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) { $('#modalHistorial').modal('hide'); dt.ajax.reload(null, false); }
            else { alert(data.error || 'No se pudo eliminar.'); }
        })
        .catch(function() { alert('Error de conexión.'); });
    });
})();
</script>
<?= $this->endSection() ?>
