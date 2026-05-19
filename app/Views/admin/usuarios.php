<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<?php /* DataTables CSS ya viene en el layout — no duplicar */ ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-float-label.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Usuarios</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Inicio</a></li>
                    <li class="breadcrumb-item active">Usuarios</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <button class="btn btn-primary" data-toggle="modal" data-target="#modalCrearUsuario">
                <i class="iconsminds-add"></i> Crear usuario
            </button>
        </div>
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

<!-- Tabla Usuarios -->
<div class="separator mb-5"></div>
<div class="row">
    <div class="col-12 mb-4">
        <table id="usuariosTable" class="table responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Correo</th>
                    <th>Ticket Activo</th>
                    <th>Nombre</th>
                    <th>Contraseña</th>
                    <th>Acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $user): ?>
                <tr>
                    <td><?= (int)($user['Id'] ?? 0) ?></td>
                    <td class="font-weight-bold"><?= esc($user['mail'] ?? '') ?></td>
                    <td>
                        <?php if (($user['bandera'] ?? 0) == 1): ?>
                            <span class="badge badge-warning">Activo</span>
                            <form method="POST" action="<?= base_url('admin/usuarios/liberar/' . (int)$user['Id']) ?>"
                                  style="display:inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-xs btn-outline-secondary ml-1" title="Liberar ticket">
                                    <i class="simple-icon-arrow-right"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="badge badge-success">Libre</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($user['nombre'] ?? '') ?></td>
                    <td><?= esc($user['pass'] ?? '') ?></td>
                    <td>
                        <?php
                            $niveles = [1=>'Admin', 2=>'Caja', 3=>'Mostrador', 4=>'G Ventas'];
                            $acc = (int)($user['acceso'] ?? 0);
                            echo esc($niveles[$acc] ?? $acc);
                        ?>
                    </td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-xs btn-outline-primary mr-1"
                                onclick="abrirEditarUsuario(<?= (int)$user['Id'] ?>, '<?= esc($user['nombre'] ?? '', 'js') ?>', '<?= esc($user['mail'] ?? '', 'js') ?>', '<?= esc($user['pass'] ?? '', 'js') ?>', <?= $acc ?>)">
                            <i class="simple-icon-pencil"></i> Editar
                        </button>
                        <form method="POST" action="<?= base_url('admin/usuarios/eliminar/' . (int)$user['Id']) ?>"
                              style="display:inline"
                              onsubmit="return confirm('¿Eliminar este usuario? Podrás restaurarlo desde Usuarios Eliminados.')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-xs btn-danger">
                                <i class="simple-icon-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Usuario</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="<?= base_url('admin/usuarios/crear') ?>">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nombre de usuario</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre de usuario" required>
                    </div>
                    <div class="form-group">
                        <label>Correo electrónico</label>
                        <input type="email" name="mail" class="form-control" placeholder="Correo de usuario" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="text" name="pass" class="form-control" placeholder="Contraseña de usuario" required>
                    </div>
                    <div class="form-group">
                        <label>Nivel de acceso</label>
                        <select name="acceso" class="form-control" required>
                            <option value="">-- Seleccionar --</option>
                            <option value="1">Admin (1)</option>
                            <option value="2">Caja (2)</option>
                            <option value="3">Mostrador (3)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Usuario</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="formEditarUsuario" action="">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nombre de usuario</label>
                        <input type="text" name="nombre" id="editNombre" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Correo electrónico</label>
                        <input type="email" name="mail" id="editMail" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="text" name="pass" id="editPass" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Nivel de acceso</label>
                        <select name="acceso" id="editAcceso" class="form-control" required>
                            <option value="">-- Seleccionar --</option>
                            <option value="1">Admin (1)</option>
                            <option value="2">Caja (2)</option>
                            <option value="3">Mostrador (3)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
var BASE_EDITAR = '<?= base_url('admin/usuarios/editar/') ?>';

function abrirEditarUsuario(id, nombre, mail, pass, acceso) {
    document.getElementById('formEditarUsuario').action = BASE_EDITAR + id;
    document.getElementById('editNombre').value  = nombre;
    document.getElementById('editMail').value    = mail;
    document.getElementById('editPass').value    = pass;
    document.getElementById('editAcceso').value  = acceso;
    $('#modalEditarUsuario').modal('show');
}

/* DataTables JS ya cargado en el layout — no importar de nuevo */
(function initUsuariosTable() {
    var $table = $('#usuariosTable');
    if (!$table.length) return;

    if (!$.fn.DataTable.isDataTable($table[0])) {
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
})();
</script>
<?= $this->endSection() ?>
