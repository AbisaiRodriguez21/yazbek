<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<?php /* DataTables CSS ya viene en el layout principal — no duplicar */ ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Soporta acceso desde admin (/admin/clientes) o mostrador (/mostrador/clientes)
$rutaBase = $rutaBase ?? 'mostrador';
?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Clientes</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?= base_url($rutaBase) ?>">
                        <?= $rutaBase === 'admin' ? 'Inicio' : 'Mostrador' ?>
                    </a>
                </li>
                <li class="breadcrumb-item active">Clientes</li>
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
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= session()->getFlashdata('error') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row mb-3">
    <div class="col-sm-7">
        <a href="#modal-form" class="btn btn-success" data-toggle="modal"
           onclick="newUpdateClient('', 0)">
            <i class="iconsminds-add"></i> Añadir Cliente
        </a>
    </div>
</div>

<div class="separator mb-5"></div>

<div class="row">
    <div class="col-12 mb-4">
        <table id="datatableProductos" class="table responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>RFC</th>
                    <th>Celular</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Modal Añadir / Actualizar Cliente -->
<div class="modal fade modal-right" id="modal-form" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body wrapper-lg">
                <form name="nuevoCliente" id="nuevoCliente" role="form"
                      method="POST"
                      action="<?= base_url($rutaBase . '/clientes/crear') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="fecha" value="<?= date('Y-m-d') ?>">

                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="m-t-none m-b header-clientes"></h3>
                            <p>Datos de contacto</p>

                            <div class="form-group">
                                <label>Nombre de empresa</label>
                                <input name="NombreEmpresa" type="text" class="form-control"
                                       id="NombreEmpresa" placeholder="Nombre de la empresa"
                                       style="text-transform:uppercase">
                            </div>
                            <div class="form-group">
                                <label>Nombre Completo <span class="text-danger">*</span></label>
                                <input name="nombre" type="text" required class="form-control"
                                       id="nombre" placeholder="Nombre completo"
                                       style="text-transform:uppercase">
                            </div>
                            <div class="form-group">
                                <label>Celular</label>
                                <input name="celular" type="text" class="form-control"
                                       id="celular" placeholder="No de celular">
                            </div>
                            <div class="form-group">
                                <label>Teléfono local</label>
                                <input name="telefono" type="text" class="form-control"
                                       id="telefono" placeholder="Teléfono Local">
                            </div>
                            <div class="form-group">
                                <label>E-mail</label>
                                <input name="mail" type="email" class="form-control"
                                       id="mail" placeholder="E-mail">
                            </div>
                            <div class="form-group">
                                <label>Cómo nos encontraste</label>
                                <select name="comoNosConoce" class="form-control" id="comoNosConoce">
                                    <option value="Sitio Web">Sitio Web</option>
                                    <option value="Teléfono">Teléfono</option>
                                    <option value="Sección Amarilla">Sección Amarilla</option>
                                    <option value="Recomendación">Recomendación</option>
                                    <option value="Redes sociales">Redes sociales</option>
                                    <option value="Búsqueda en internet">Búsqueda en internet</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <h4>Datos de Empresa</h4>
                            <div class="form-group">
                                <label>Razón Social</label>
                                <input name="razonSocial" type="text" class="form-control"
                                       id="razonSocial" placeholder="Razón Social"
                                       style="text-transform:uppercase">
                            </div>
                            <div class="form-group">
                                <label>RFC</label>
                                <input name="RFC" type="text" class="form-control"
                                       id="rfc" placeholder="RFC"
                                       style="text-transform:uppercase">
                            </div>
                            <div class="form-group">
                                <label>Dirección</label>
                                <input name="direccion" type="text" class="form-control"
                                       id="direccion" placeholder="Dirección"
                                       style="text-transform:uppercase">
                            </div>
                            <div class="form-group">
                                <label>Código Postal</label>
                                <input name="CP" type="text" class="form-control"
                                       id="cp" placeholder="Código Postal">
                            </div>
                            <div class="form-group">
                                <label>Estado</label>
                                <input name="estado" class="form-control"
                                       id="estado" style="text-transform:uppercase">
                            </div>
                            <div class="form-group">
                                <label>Ciudad</label>
                                <input name="ciudad" class="form-control"
                                       id="ciudad" style="text-transform:uppercase">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary" id="guardarCliente">
                            Guardar Cliente
                        </button>
                    </div>

                    <input type="hidden" name="MM_insert" value="" id="MM_insert">
                    <input type="hidden" name="clienteid" value="" id="clienteid">
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminar -->
<div class="modal fade" id="modalPregunta" tabindex="-1" role="dialog">
    <form name="eliminaCliente" id="eliminaCliente" method="POST"
          action="<?= base_url($rutaBase . '/clientes/eliminar') ?>">
        <?= csrf_field() ?>
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Mensaje</h4>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    ¿Estás seguro de eliminar este cliente?
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger"><strong>Eliminar</strong></button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
        <input type="hidden" name="MM_delete"     value="" id="MM_delete">
        <input type="hidden" name="clienteDelete" value="" id="clienteDelete">
    </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
/* DataTables JS ya cargado en el layout — no importar de nuevo */
var rutaBase = '<?= base_url($rutaBase) ?>';

var rutaDatatable = '<?= base_url($rutaBase . '/clientes/datatable') ?>';

(function initClientesTable() {
    var $table = $('#datatableProductos');
    if (!$table.length) return;

    // destroy:true elimina cualquier instancia previa (incluso huérfanas) antes de crear una nueva
    $table.DataTable({
        destroy:    true,
        processing: true,
        serverSide: true,
        ajax: {
            url:   rutaDatatable,
            type:  'GET',
            error: function(xhr, err) {
                console.error('[Clientes] DataTable AJAX error:', xhr.status, err, xhr.responseText);
            }
        },
        pageLength: 10,
        order: [[0, 'asc']],
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
        },
        columns: [
            { data: 'nombre' },
            { data: 'RFC' },
            { data: 'celular' },
            { data: 'telefono' },
            { data: 'mail' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(id, type, row) {
                    if (type !== 'display') return id;
                    try {
                        var rowJson = JSON.stringify(row).replace(/"/g, '&quot;');
                        return '<button class="btn btn-sm btn-primary mr-1" data-toggle="modal" data-target="#modal-form" onclick="newUpdateClient(' + id + ', 1, ' + rowJson + ')">'
                             + '<i class="simple-icon-pencil"></i></button>'
                             + '<button class="btn btn-sm btn-danger" data-toggle="modal" data-target="#modalPregunta" onclick="deleteClient(' + id + ')">'
                             + '<i class="simple-icon-trash"></i></button>';
                    } catch(e) {
                        console.error('[Clientes] render error:', e);
                        return '<button class="btn btn-sm btn-danger" data-toggle="modal" data-target="#modalPregunta" onclick="deleteClient(' + id + ')">'
                             + '<i class="simple-icon-trash"></i></button>';
                    }
                }
            }
        ]
    });
})();

function newUpdateClient(idCliente, operacion, row) {
    if (operacion === 1 && row) {
        $('.header-clientes').text('Actualizar Cliente');
        $('#NombreEmpresa').val((row.NombreEmpresa || '').trim());
        $('#nombre').val((row.nombre || '').trim());
        $('#celular').val((row.celular || '').trim());
        $('#telefono').val((row.telefono || '').trim());
        $('#mail').val((row.mail || '').trim());
        $('#razonSocial').val((row.razonSocial || '').trim());
        $('#rfc').val((row.RFC || '').trim());
        $('#direccion').val((row.direccion || '').trim());
        $('#cp').val((row.CP || '').trim());
        $('#estado').val((row.estado || '').trim());
        $('#ciudad').val((row.ciudad || '').trim());
        $('#guardarCliente').text('Actualizar Cliente');
        $('#MM_insert').val('actualizarCliente');
        $('#clienteid').val(idCliente);
        $('#nuevoCliente').attr('action', rutaBase + '/clientes/actualizar/' + idCliente);
    } else {
        $('.header-clientes').text('Nuevo Cliente');
        $('#NombreEmpresa, #nombre, #celular, #telefono, #mail').val('');
        $('#razonSocial, #rfc, #direccion, #cp, #estado, #ciudad').val('');
        $('#guardarCliente').text('Guardar Cliente');
        $('#MM_insert').val('nuevoCliente');
        $('#clienteid').val('');
        $('#nuevoCliente').attr('action', rutaBase + '/clientes/crear');
    }
}

function deleteClient(idCliente) {
    $('#MM_delete').val('MM_delete');
    $('#clienteDelete').val(idCliente);
}
</script>
<?= $this->endSection() ?>
