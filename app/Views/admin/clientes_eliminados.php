<?= $this->extend('layouts/main') ?>

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
    <?php if (session()->getFlashdata('error')): ?>
    <div class="col-12">
        <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
    </div>
    <?php endif; ?>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Listado de clientes eliminados</h5>
                <p class="text-muted mb-3">
                    Estos clientes han sido eliminados de las vistas normales pero sus tickets siguen intactos en la base de datos.
                    Puedes <strong>restaurarlos</strong>, ver su <strong>historial de compras</strong>, eliminar sus notas y finalmente
                    <strong>eliminarlos definitivamente</strong> cuando ya no tengan notas.
                </p>
                <table id="tablaEliminados" class="table w-100">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>RFC</th>
                            <th>Empresa</th>
                            <th>Celular</th>
                            <th>Email</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Historial de Cliente Eliminado ── -->
<div class="modal fade" id="modalHistorial" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historial de compras — <span id="historialNombre"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="historialCargando" class="text-center py-4"><i>Cargando...</i></div>
                <div id="historialContenido" style="display:none">
                    <div class="alert alert-info py-2 mb-3" id="historialResumen"></div>
                    <table class="table table-sm table-hover" id="tablaHistorial">
                        <thead class="thead-light">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th class="text-right">Subtotal</th>
                                <th class="text-right">Total</th>
                                <th>Estatus</th>
                                <th class="text-center">Eliminar nota</th>
                            </tr>
                        </thead>
                        <tbody id="historialBody"></tbody>
                    </table>
                    <div id="historialVacio" class="text-center text-muted py-4" style="display:none">
                        <i class="iconsminds-receipt-4 icon-dual icon-lg d-block mb-2"></i>
                        Este cliente no tiene notas registradas.
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button type="button" class="btn btn-success" id="btnRestaurar">
                        <i class="simple-icon-reload mr-1"></i> Restaurar cliente
                    </button>
                    <button type="button" class="btn btn-danger ml-2" id="btnEliminarDefinitivo">
                        <i class="simple-icon-trash mr-1"></i> Eliminar definitivamente
                    </button>
                </div>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal confirmación eliminar nota ── -->
<div class="modal fade" id="modalEliminarNota" tabindex="-1" role="dialog" style="z-index:1060">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Eliminar nota permanentemente</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center">
                <p>¿Eliminar el folio <strong id="notaFolioLabel"></strong>?</p>
                <small class="text-muted">Esta acción borrará la nota, su detalle y sus pagos de la BD. No se puede deshacer.</small>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminarNota">Eliminar</button>
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

    // ── Inicializar DataTable server-side ──
    var dt = $('#tablaEliminados').DataTable({
        processing : true,
        serverSide : true,
        ajax       : {
            url  : '<?= base_url('admin/clientes/eliminados/datatable') ?>',
            type : 'GET',
            data : function(d) {
                d[csrfToken] = csrfHash;
            }
        },
        columns: [
            { data: 'nombre' },
            { data: 'RFC',          defaultContent: '—' },
            { data: 'NombreEmpresa',defaultContent: '—' },
            { data: 'celular',      defaultContent: '—' },
            { data: 'mail',         defaultContent: '—' },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(d) {
                    return '<button class="btn btn-xs btn-primary btn-ver-historial" data-id="' + d.id + '" data-nombre="' + d.nombre + '">'
                         + '<i class="simple-icon-eye mr-1"></i> Ver historial</button>';
                }
            }
        ],
        language: {
            search:       'Buscar:',
            lengthMenu:   'Mostrar _MENU_ registros',
            info:         'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty:    'Sin registros',
            zeroRecords:  'No se encontraron clientes eliminados',
            processing:   'Cargando...',
            paginate: { first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último' }
        }
    });

    // ── Abrir historial ──
    $(document).on('click', '.btn-ver-historial', function () {
        clienteActivoId = $(this).data('id');
        var nombre = $(this).data('nombre');
        document.getElementById('historialNombre').textContent = nombre;
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
                document.getElementById('historialContenido').innerHTML = '<p class="text-danger">' + (data.error || 'Error') + '</p>';
                document.getElementById('historialContenido').style.display = '';
                return;
            }
            renderHistorial(data.notas);
            document.getElementById('historialContenido').style.display = '';
        })
        .catch(function() {
            document.getElementById('historialCargando').style.display = 'none';
            document.getElementById('historialContenido').innerHTML = '<p class="text-danger">Error de conexión.</p>';
            document.getElementById('historialContenido').style.display = '';
        });
    });

    function renderHistorial(notas) {
        var body = document.getElementById('historialBody');
        var vacio = document.getElementById('historialVacio');
        var tabla = document.getElementById('tablaHistorial');
        var resumen = document.getElementById('historialResumen');

        body.innerHTML = '';

        if (!notas || notas.length === 0) {
            tabla.style.display = 'none';
            vacio.style.display = '';
            resumen.textContent = 'Este cliente no tiene notas. Puedes eliminarlo definitivamente.';
        } else {
            tabla.style.display = '';
            vacio.style.display = 'none';
            resumen.textContent = notas.length + ' nota(s) encontrada(s). Elimínalas todas para poder borrar el cliente definitivamente.';

            notas.forEach(function(n) {
                var tr = document.createElement('tr');
                tr.id = 'nota-row-' + n.folio;
                tr.innerHTML =
                    '<td><strong>' + n.folio + '</strong></td>'
                  + '<td>' + (n.fecha_inicial || '—') + '</td>'
                  + '<td class="text-right">$' + parseFloat(n.subTotal || 0).toFixed(2) + '</td>'
                  + '<td class="text-right font-weight-bold">$' + parseFloat(n.total || 0).toFixed(2) + '</td>'
                  + '<td>' + (n.status || '—') + '</td>'
                  + '<td class="text-center">'
                  + '<button class="btn btn-xs btn-outline-danger btn-eliminar-nota" data-folio="' + n.folio + '">'
                  + '<i class="simple-icon-trash"></i> Eliminar</button></td>';
                body.appendChild(tr);
            });
        }
    }

    // ── Botón eliminar nota (abre confirmación) ──
    $(document).on('click', '.btn-eliminar-nota', function () {
        folioAEliminar = $(this).data('folio');
        document.getElementById('notaFolioLabel').textContent = '#' + folioAEliminar;
        $('#modalEliminarNota').modal('show');
    });

    // ── Confirmar eliminación de nota ──
    document.getElementById('btnConfirmarEliminarNota').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Eliminando...';

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);
        fd.append('folio', folioAEliminar);

        fetch('<?= base_url('admin/clientes/eliminar-nota') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Eliminar';
            if (data.ok) {
                $('#modalEliminarNota').modal('hide');
                // Quitar fila de la tabla del historial
                var row = document.getElementById('nota-row-' + folioAEliminar);
                if (row) row.remove();
                // Actualizar resumen
                var cuerpo = document.getElementById('historialBody');
                var restantes = cuerpo.querySelectorAll('tr').length;
                if (restantes === 0) {
                    document.getElementById('tablaHistorial').style.display = 'none';
                    document.getElementById('historialVacio').style.display = '';
                    document.getElementById('historialResumen').textContent = 'Este cliente no tiene notas. Puedes eliminarlo definitivamente.';
                } else {
                    document.getElementById('historialResumen').textContent = restantes + ' nota(s) encontrada(s).';
                }
            } else {
                alert(data.error || 'Error al eliminar la nota.');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Eliminar';
            alert('Error de conexión.');
        });
    });

    // ── Restaurar cliente ──
    document.getElementById('btnRestaurar').addEventListener('click', function () {
        if (!clienteActivoId || !confirm('¿Restaurar este cliente? Volverá a aparecer en las vistas normales.')) return;

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);

        fetch('<?= base_url('admin/clientes/restaurar/') ?>' + clienteActivoId, {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.text(); })
        .then(function() {
            $('#modalHistorial').modal('hide');
            dt.ajax.reload(null, false);
        })
        .catch(function() { alert('Error de conexión.'); });
    });

    // ── Eliminar definitivamente ──
    document.getElementById('btnEliminarDefinitivo').addEventListener('click', function () {
        if (!clienteActivoId || !confirm('¿Eliminar permanentemente este cliente de la base de datos?\nSolo es posible si no tiene notas.')) return;

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);

        fetch('<?= base_url('admin/clientes/eliminar-definitivo/') ?>' + clienteActivoId, {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                $('#modalHistorial').modal('hide');
                dt.ajax.reload(null, false);
            } else {
                alert(data.error || 'No se pudo eliminar.');
            }
        })
        .catch(function() { alert('Error de conexión.'); });
    });
})();
</script>
<?= $this->endSection() ?>
