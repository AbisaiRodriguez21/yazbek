<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
.config-section {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}
.config-section h5 {
    font-size: .9rem;
    font-weight: 700;
    color: #145388;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e8f0fe;
}
.config-section label {
    font-size: .8rem;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
}
.config-section .form-control {
    font-size: .85rem;
}
.preview-ticket {
    background: #f8f9fa;
    border: 1px dashed #adb5bd;
    border-radius: 6px;
    padding: 16px;
    font-size: 11px;
    font-family: Tahoma, sans-serif;
    line-height: 1.6;
    max-width: 330px;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Configuración del Ticket</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Ticket Config</li>
            </ol>
        </nav>
    </div>
</div>

<div class="separator mb-4"></div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <?= session()->getFlashdata('success') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<form method="POST" action="<?= base_url('admin/ticket-config/guardar') ?>">
    <?= csrf_field() ?>

    <div class="row">
        <!-- Columna izquierda: Formulario -->
        <div class="col-lg-7">

            <!-- Datos de la empresa -->
            <div class="config-section">
                <h5><i class="simple-icon-briefcase mr-2"></i>Datos de la Empresa</h5>
                <?php
                $camposEmpresa = [
                    'empresa_razon_social' => 'Razón Social',
                    'empresa_sucursal'     => 'Dirección / Sucursal',
                    'empresa_ciudad'       => 'Ciudad y C.P.',
                    'empresa_telefono'     => 'Teléfono',
                    'empresa_email_web'    => 'Correo y Web',
                    'empresa_rfc'          => 'RFC',
                    'empresa_regimen'      => 'Régimen Fiscal',
                ];
                foreach ($camposEmpresa as $clave => $label): ?>
                <div class="form-group">
                    <label for="<?= $clave ?>"><?= $label ?></label>
                    <input type="text" id="<?= $clave ?>" name="<?= $clave ?>"
                           class="form-control form-control-sm"
                           value="<?= esc($config[$clave] ?? '') ?>">
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Mensajes de pie -->
            <div class="config-section">
                <h5><i class="simple-icon-note mr-2"></i>Mensajes al Pie del Ticket</h5>
                <?php
                $camposMensajes = [
                    'msg_fiscal'     => 'Aviso Fiscal',
                    'msg_cambios'    => 'Política de Cambios / Devoluciones',
                    'msg_quejas'     => 'Contacto / Quejas',
                    'msg_privacidad' => 'Aviso de Privacidad',
                ];
                foreach ($camposMensajes as $clave => $label): ?>
                <div class="form-group">
                    <label for="<?= $clave ?>"><?= $label ?></label>
                    <textarea id="<?= $clave ?>" name="<?= $clave ?>"
                              class="form-control form-control-sm" rows="2"><?= esc($config[$clave] ?? '') ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary px-5">
                <i class="simple-icon-check mr-1"></i> Guardar cambios
            </button>
            <a href="<?= base_url('admin') ?>" class="btn btn-outline-secondary ml-2">Cancelar</a>

        </div>

        <!-- Columna derecha: Preview -->
        <div class="col-lg-5">
            <div class="config-section">
                <h5><i class="simple-icon-printer mr-2"></i>Vista previa del pie del ticket</h5>
                <div class="preview-ticket" id="previewTicket">
                    <div style="text-align:center; margin-bottom:8px;">
                        <strong id="prev_razon"><?= esc($config['empresa_razon_social'] ?? '') ?></strong><br>
                        <span id="prev_sucursal"><?= esc($config['empresa_sucursal'] ?? '') ?></span><br>
                        <span id="prev_ciudad"><?= esc($config['empresa_ciudad'] ?? '') ?></span><br>
                        <span id="prev_telefono"><?= esc($config['empresa_telefono'] ?? '') ?></span><br>
                        <span id="prev_email"><?= esc($config['empresa_email_web'] ?? '') ?></span><br>
                        <span id="prev_rfc"><?= esc($config['empresa_rfc'] ?? '') ?></span><br>
                        <span id="prev_regimen"><?= esc($config['empresa_regimen'] ?? '') ?></span>
                    </div>
                    <hr style="border-color:#7B7B7B; margin:6px 0;">
                    <div id="prev_fiscal" style="margin-bottom:4px;"><?= esc($config['msg_fiscal'] ?? '') ?></div>
                    <hr style="border-color:#7B7B7B; margin:6px 0;">
                    <div id="prev_cambios" style="margin-bottom:4px;"><?= esc($config['msg_cambios'] ?? '') ?></div>
                    <div id="prev_quejas"><?= esc($config['msg_quejas'] ?? '') ?></div>
                    <hr style="border-color:#7B7B7B; margin:6px 0;">
                    <div id="prev_privacidad"><?= esc($config['msg_privacidad'] ?? '') ?></div>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="simple-icon-info mr-1"></i>
                    La vista previa se actualiza en tiempo real mientras escribes.
                </small>
            </div>
        </div>
    </div>

</form>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
// Preview en tiempo real
var map = {
    'empresa_razon_social': 'prev_razon',
    'empresa_sucursal':     'prev_sucursal',
    'empresa_ciudad':       'prev_ciudad',
    'empresa_telefono':     'prev_telefono',
    'empresa_email_web':    'prev_email',
    'empresa_rfc':          'prev_rfc',
    'empresa_regimen':      'prev_regimen',
    'msg_fiscal':           'prev_fiscal',
    'msg_cambios':          'prev_cambios',
    'msg_quejas':           'prev_quejas',
    'msg_privacidad':       'prev_privacidad',
};
Object.keys(map).forEach(function(field) {
    var el = document.getElementById(field);
    if (!el) return;
    el.addEventListener('input', function() {
        var prev = document.getElementById(map[field]);
        if (prev) prev.textContent = el.value;
    });
});
</script>
<?= $this->endSection() ?>
