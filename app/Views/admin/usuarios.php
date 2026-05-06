<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/datatables.responsive.bootstrap4.min.css') ?>">
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
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="usuariosTable" class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Correo</th>
                        <th>Ticket Activo</th>
                        <th>Nombre</th>
                        <th>Contraseña <small class="text-muted">(clic para editar)</small></th>
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
                                <a href="<?= base_url('admin/usuarios/liberar/' . (int)$user['Id']) ?>"
                                   class="btn btn-xs btn-outline-secondary ml-1" title="Liberar ticket">
                                    <i class="simple-icon-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($user['nombre'] ?? '') ?></td>
                        <td contenteditable="true"
                            id="pass:<?= (int)$user['Id'] ?>"
                            style="cursor: text; min-width: 120px;">
                            <?= esc($user['pass'] ?? '') ?>
                        </td>
                        <td>
                            <a href="<?= base_url('admin/usuarios/eliminar/' . (int)$user['Id']) ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('¿Eliminar este usuario?')">
                                <i class="simple-icon-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
                            <option value="4">G Ventas (4)</option>
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

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/vendor/dataTables.bootstrap4.min.js') ?>"></script>
<script>
$(document).ready(function() {
    $('#usuariosTable').DataTable({
        responsive: true,
        pageLength: 25,
        language: { url: '/assets/js/vendor/datatables.spanish.json' }
    });

    // Edición inline de contraseña — igual que el original
    $('td[contenteditable="true"]').on('blur', function() {
        var campo = $(this).attr('id'); // "pass:5"
        var valor = $(this).text().trim();
        if (!campo || !valor) return;

        var data = {};
        data[campo] = valor;

        $.post('<?= base_url('admin/ajax/usuarios') ?>', data, function(resp) {
            // Respuesta OK — no hacer nada extra
        });
    });

    $('td[contenteditable="true"]').on('keydown', function(e) {
        if (e.which === 13) { e.preventDefault(); $(this).blur(); }
    });
});
</script>
<?= $this->endSection() ?>
