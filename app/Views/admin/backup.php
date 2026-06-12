<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
#overlayGenerando {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(255,255,255,.75);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 16px;
}
#overlayGenerando.activo { display: flex; }
.spinner-ring {
    width: 56px; height: 56px;
    border: 6px solid #e0e0e0;
    border-top-color: #145388;
    border-radius: 50%;
    animation: spin .9s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.backup-tabla td, .backup-tabla th { font-size: .95rem; vertical-align: middle; }
.backup-info p { font-size: .95rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Overlay de "generando" — reemplaza el efecto de página apagada -->
<div id="overlayGenerando">
    <div class="spinner-ring"></div>
    <p class="font-weight-bold text-primary mb-0" style="font-size:1.05rem;">Generando respaldo…</p>
    <small class="text-muted">Esto puede tardar unos segundos.</small>
</div>

<div class="page-title-container">
    <div class="page-title">
        <h1>Respaldos de Base de Datos</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pt-0">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Respaldos BD</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="iconsminds-yes mr-1"></i> <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="iconsminds-danger mr-1"></i> <?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Info + Generar manual -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header font-weight-bold" style="padding-top:1rem;padding-bottom:1rem;">
                <i class="iconsminds-cloud-upload mr-1"></i> Generar Respaldo
            </div>
            <div class="card-body backup-info">
                <p class="text-muted mb-2">
                    Los respaldos automáticos se ejecutan cada noche de
                    <strong>lunes a sábado a las 00:00 h</strong>.
                </p>
                <p class="text-muted mb-4">
                    Cada lunes se elimina la semana anterior para liberar espacio.
                    También puedes generar uno manualmente ahora:
                </p>
                <form id="formGenerar" method="post" action="<?= base_url('admin/backup/generar') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" id="btnGenerar" class="btn btn-primary btn-block">
                        <i class="iconsminds-refresh"></i> Generar Respaldo Ahora
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Lista de respaldos -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header font-weight-bold d-flex align-items-center justify-content-between" style="padding-top:1rem;padding-bottom:1rem;">
                <span>
                    <i class="iconsminds-data-storage mr-1"></i>
                    Respaldos Disponibles
                    <span class="badge badge-info ml-2"><?= count($archivos) ?></span>
                </span>
                <?php if (!empty($archivos)): ?>
                <button type="button" id="btnEliminarTodo" class="btn btn-sm btn-danger">
                    <i class="iconsminds-close mr-1"></i> Eliminar todos
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($archivos)): ?>
                <div class="text-center text-muted py-5">
                    <i class="iconsminds-data-storage" style="font-size:3rem;opacity:.3;"></i>
                    <p class="mt-3" style="font-size:.95rem;">No hay respaldos aún.<br>
                    Genera uno manualmente o espera el respaldo automático.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 backup-tabla">
                        <thead class="thead-light">
                            <tr>
                                <th>Archivo</th>
                                <th class="text-right">Tamaño</th>
                                <th>Fecha</th>
                                <th class="text-center" style="width:120px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($archivos as $f): ?>
                            <tr>
                                <td>
                                    <i class="iconsminds-data-storage text-primary mr-1"></i>
                                    <?= esc($f['nombre']) ?>
                                </td>
                                <td class="text-right text-muted">
                                    <?= $f['tamano'] >= 1048576
                                        ? round($f['tamano'] / 1048576, 1) . ' MB'
                                        : round($f['tamano'] / 1024, 1) . ' KB' ?>
                                </td>
                                <td class="text-muted"><?= $f['fecha'] ?></td>
                                <td class="text-center">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-success mr-1 btn-descargar"
                                            data-url="<?= base_url('admin/backup/descargar/' . urlencode($f['nombre'])) ?>"
                                            title="Descargar">
                                        <i class="iconsminds-download-1"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger btn-eliminar"
                                            data-nombre="<?= esc($f['nombre']) ?>"
                                            title="Eliminar">
                                        <i class="iconsminds-close"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Form oculto para eliminar uno -->
<form id="formEliminar" method="post" style="display:none;">
    <?= csrf_field() ?>
</form>

<!-- Form para eliminar todos -->
<form id="formEliminarTodo" method="post" action="<?= base_url('admin/backup/eliminar-todo') ?>" style="display:none;">
    <?= csrf_field() ?>
</form>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
// Mostrar overlay al generar (evita que la página se vea "apagada")
document.getElementById('formGenerar').addEventListener('submit', function() {
    document.getElementById('overlayGenerando').classList.add('activo');
});

// Descargar via iframe oculto — evita que Chrome oscurezca la página
document.querySelectorAll('.btn-descargar').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var frame = document.createElement('iframe');
        frame.style.display = 'none';
        frame.src = this.dataset.url;
        document.body.appendChild(frame);
        setTimeout(function() { document.body.removeChild(frame); }, 10000);
    });
});

// Eliminar un respaldo
document.querySelectorAll('.btn-eliminar').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var nombre = this.dataset.nombre;
        if (!confirm('¿Eliminar el respaldo "' + nombre + '"?\nEsta acción no se puede deshacer.')) return;
        var form = document.getElementById('formEliminar');
        form.action = '<?= base_url('admin/backup/eliminar/') ?>' + encodeURIComponent(nombre);
        form.submit();
    });
});

// Eliminar todos los respaldos
document.getElementById('btnEliminarTodo').addEventListener('click', function() {
    if (!confirm('¿Eliminar TODOS los respaldos?\nEsta acción no se puede deshacer.')) return;
    document.getElementById('formEliminarTodo').submit();
});
</script>
<?= $this->endSection() ?>
