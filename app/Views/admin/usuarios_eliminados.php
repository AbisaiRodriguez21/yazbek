<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Usuarios Eliminados</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Inicio</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url('admin/usuarios') ?>">Usuarios</a></li>
                <li class="breadcrumb-item active">Eliminados</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= session()->getFlashdata('success') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="separator mb-5"></div>
<div class="row">
    <div class="col-12 mb-4">
        <p class="text-muted mb-3">
            Usuarios eliminados de las vistas normales. Puedes <strong>restaurarlos</strong> o
            <strong>eliminarlos definitivamente</strong>.
        </p>
        <table id="tablaUsuariosEliminados" class="table responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Acceso</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Modal confirmar restaurar -->
<div class="modal fade" id="modalRestaurar" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restaurar usuario</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center">
                <p>¿Restaurar a <strong id="restNombre"></strong>?</p>
                <small class="text-muted">Volverá a aparecer en la lista de usuarios activos.</small>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="btnConfirmarRestaurar">
                    <i class="simple-icon-reload mr-1"></i> Restaurar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal confirmar eliminar definitivo -->
<div class="modal fade" id="modalEliminarDef" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Eliminar permanentemente</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body text-center">
                <p>¿Eliminar definitivamente a <strong id="defNombre"></strong>?</p>
                <small class="text-muted">Esta acción no se puede deshacer.</small>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmarEliminarDef">
                    <i class="simple-icon-trash mr-1"></i> Eliminar
                </button>
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
    var usuarioActivoId = null;

    var NIVELES = { 1: 'Admin', 2: 'Caja', 3: 'Mostrador', 4: 'G Ventas' };

    var dt = $('#tablaUsuariosEliminados').DataTable({
        processing : true,
        serverSide : true,
        ajax: {
            url  : '<?= base_url('admin/usuarios/eliminados/datatable') ?>',
            type : 'GET'
        },
        columns: [
            { data: 'Id' },
            { data: 'nombre', defaultContent: '—' },
            { data: 'mail',   defaultContent: '—' },
            { data: 'acceso', render: function(d) {
                return NIVELES[parseInt(d, 10)] || ('Nivel ' + d);
            }},
            { data: null, orderable: false, className: 'text-center',
              render: function(d) {
                var n = (d.nombre || '').replace(/"/g, '&quot;');
                return '<button class="btn btn-xs btn-outline-success mr-1 btn-restaurar" '
                     + 'data-id="' + d.Id + '" data-nombre="' + n + '">'
                     + '<i class="simple-icon-reload"></i> Restaurar</button>'
                     + '<button class="btn btn-xs btn-outline-danger btn-eliminar-def" '
                     + 'data-id="' + d.Id + '" data-nombre="' + n + '">'
                     + '<i class="simple-icon-trash"></i> Eliminar</button>';
              }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            search:       'Buscar:',
            lengthMenu:   'Mostrar _MENU_ registros',
            info:         'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty:    'Mostrando 0 a 0 de 0 registros',
            zeroRecords:  'No hay usuarios eliminados',
            processing:   'Cargando...',
            paginate: { first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último' }
        }
    });

    /* ── Restaurar ── */
    $(document).on('click', '.btn-restaurar', function () {
        usuarioActivoId = $(this).data('id');
        document.getElementById('restNombre').textContent = $(this).data('nombre');
        $('#modalRestaurar').modal('show');
    });

    document.getElementById('btnConfirmarRestaurar').addEventListener('click', function () {
        if (! usuarioActivoId) return;
        var btn = this;
        btn.disabled = true;

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);

        fetch('<?= base_url('admin/usuarios/restaurar/') ?>' + usuarioActivoId, {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function () {
            btn.disabled = false;
            $('#modalRestaurar').modal('hide');
            dt.ajax.reload(null, false);
        })
        .catch(function () {
            btn.disabled = false;
            alert('Error de conexión.');
        });
    });

    /* ── Eliminar definitivo ── */
    $(document).on('click', '.btn-eliminar-def', function () {
        usuarioActivoId = $(this).data('id');
        document.getElementById('defNombre').textContent = $(this).data('nombre');
        $('#modalEliminarDef').modal('show');
    });

    document.getElementById('btnConfirmarEliminarDef').addEventListener('click', function () {
        if (! usuarioActivoId) return;
        var btn = this;
        btn.disabled = true;

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);

        fetch('<?= base_url('admin/usuarios/eliminar-definitivo/') ?>' + usuarioActivoId, {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            if (data.ok) {
                $('#modalEliminarDef').modal('hide');
                dt.ajax.reload(null, false);
            } else {
                alert(data.error || 'No se pudo eliminar.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            alert('Error de conexión.');
        });
    });
})();
</script>
<?= $this->endSection() ?>
