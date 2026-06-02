<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
.cred-section {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}
.cred-section h5 {
    font-size: .88rem;
    font-weight: 700;
    color: #145388;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e8f0fe;
}
.cred-section label {
    font-size: .8rem;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
}
.cred-section .form-control,
.cred-section .custom-select {
    font-size: .85rem;
}
.form-text {
    font-size: .75rem;
    color: #888;
}
.upload-zone {
    border: 2px dashed #b0c4de;
    border-radius: 8px;
    padding: 28px 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    background: #f8fbff;
}
.upload-zone:hover,
.upload-zone.drag-over {
    border-color: #145388;
    background: #eef4fb;
}
.upload-zone i {
    font-size: 2rem;
    color: #145388;
    display: block;
    margin-bottom: 8px;
}
.upload-zone p {
    margin: 0;
    font-size: .85rem;
    color: #555;
}
.upload-zone .file-name {
    margin-top: 8px;
    font-size: .8rem;
    color: #145388;
    font-weight: 600;
}
.badge-env {
    font-size: .7rem;
    padding: 3px 8px;
    border-radius: 20px;
    font-weight: 600;
    vertical-align: middle;
}
.badge-prod { background: #fde8e8; color: #c0392b; }
.badge-dev  { background: #e8f5e9; color: #27ae60; }
.badge-test { background: #fff3cd; color: #856404; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>
            Credenciales del Servidor
            <?php
                $amb = $env['CI_ENVIRONMENT'] ?? 'development';
                $badgeClass = $amb === 'production' ? 'badge-prod' : ($amb === 'testing' ? 'badge-test' : 'badge-dev');
                $badgeLabel = $amb === 'production' ? 'PRODUCCIÓN' : ($amb === 'testing' ? 'TESTING' : 'DESARROLLO');
            ?>
            <span class="badge badge-env <?= $badgeClass ?>"><?= $badgeLabel ?></span>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Credenciales</li>
            </ol>
        </nav>
    </div>
</div>

<div class="separator mb-4"></div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="simple-icon-check mr-2"></i><?= session()->getFlashdata('success') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4">
    <i class="simple-icon-exclamation mr-2"></i><?= session()->getFlashdata('error') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row">

    <!-- ── Columna izquierda: formulario manual ─────────────────────── -->
    <div class="col-lg-7">

        <form method="POST" action="<?= base_url('admin/credenciales/guardar') ?>">
            <?= csrf_field() ?>

            <?php foreach ($campos as $seccionKey => $seccion): ?>
            <div class="cred-section">
                <h5>
                    <i class="<?= esc($seccion['icon']) ?> mr-2"></i>
                    <?= esc($seccion['label']) ?>
                </h5>

                <div class="row">
                <?php foreach ($seccion['campos'] as $key => $meta): ?>
                    <?php
                        $valActual = $env[$key] ?? '';
                        $inputId   = 'campo_' . preg_replace('/[^a-zA-Z0-9]/', '_', $key);
                        $colClass  = ($meta['tipo'] ?? 'text') === 'select' ? 'col-md-6' : 'col-md-6';
                    ?>
                    <div class="<?= $colClass ?> mb-3">
                        <label for="<?= $inputId ?>">
                            <?= esc($meta['label']) ?>
                        </label>

                        <?php if (($meta['tipo'] ?? 'text') === 'select'): ?>
                            <select name="<?= esc($key) ?>" id="<?= $inputId ?>" class="form-control custom-select">
                                <?php foreach ($meta['opciones'] as $optVal => $optLabel): ?>
                                    <option value="<?= esc($optVal) ?>"
                                        <?= $valActual === $optVal ? 'selected' : '' ?>>
                                        <?= esc($optLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif (($meta['tipo'] ?? 'text') === 'password'): ?>
                            <div class="input-group">
                                <input type="password"
                                    id="<?= $inputId ?>"
                                    name="<?= esc($key) ?>"
                                    class="form-control"
                                    value="<?= esc($valActual) ?>"
                                    placeholder="<?= esc($meta['placeholder'] ?? '') ?>"
                                    autocomplete="new-password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary btn-toggle-pass" type="button"
                                        data-target="#<?= $inputId ?>">
                                        <i class="simple-icon-eye"></i>
                                    </button>
                                </div>
                            </div>

                        <?php else: ?>
                            <input type="<?= esc($meta['tipo'] ?? 'text') ?>"
                                id="<?= $inputId ?>"
                                name="<?= esc($key) ?>"
                                class="form-control"
                                value="<?= esc($valActual) ?>"
                                placeholder="<?= esc($meta['placeholder'] ?? '') ?>">
                        <?php endif; ?>

                        <?php if (!empty($meta['ayuda'])): ?>
                            <small class="form-text"><?= esc($meta['ayuda']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>

            </div>
            <?php endforeach; ?>

            <div class="text-right mb-5">
                <button type="submit" class="btn btn-primary">
                    <i class="simple-icon-check mr-1"></i> Guardar Cambios
                </button>
            </div>

        </form>

    </div>

    <!-- ── Columna derecha: subir archivo .env ────────────────────── -->
    <div class="col-lg-5">

        <div class="cred-section">
            <h5><i class="simple-icon-cloud-upload mr-2"></i>Subir Archivo .env</h5>

            <p class="text-muted mb-3" style="font-size:.82rem;">
                Sube un archivo <code>.env</code> de producción para aplicar sus credenciales
                automáticamente. Se creará un respaldo (<code>.env.bak_FECHA</code>) antes de cualquier cambio.
            </p>

            <form method="POST"
                  action="<?= base_url('admin/credenciales/subir') ?>"
                  enctype="multipart/form-data"
                  id="formSubirEnv">
                <?= csrf_field() ?>

                <!-- Zona de drop / clic -->
                <div class="upload-zone mb-3" id="uploadZone">
                    <input type="file"
                           name="env_file"
                           id="envFile"
                           accept=".env,.txt"
                           style="display:none;">
                    <i class="simple-icon-cloud-upload"></i>
                    <p>Arrastra tu archivo <strong>.env</strong> aquí<br>
                       o <a href="#" id="triggerFile">haz clic para seleccionarlo</a></p>
                    <div class="file-name" id="nombreArchivo" style="display:none;"></div>
                </div>

                <!-- Modo de aplicación -->
                <div class="mb-3">
                    <label class="d-block mb-1" style="font-size:.8rem;font-weight:700;color:#555;">
                        Modo de aplicación
                    </label>
                    <div class="custom-control custom-radio mb-1">
                        <input type="radio" id="modoFusion" name="modo_reemplazo"
                               value="0" class="custom-control-input" checked>
                        <label class="custom-control-label" for="modoFusion" style="font-size:.82rem;">
                            <strong>Fusionar</strong> — solo actualiza las claves conocidas del formulario
                        </label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="modoReemplazo" name="modo_reemplazo"
                               value="1" class="custom-control-input">
                        <label class="custom-control-label" for="modoReemplazo" style="font-size:.82rem;">
                            <strong>Reemplazar todo</strong> — aplica <em>todas</em> las claves del archivo subido
                        </label>
                    </div>
                </div>

                <!-- Alerta de advertencia en modo reemplazo -->
                <div class="alert alert-warning py-2 px-3 mb-3" id="alertaReemplazo" style="display:none;font-size:.8rem;">
                    <i class="simple-icon-exclamation mr-1"></i>
                    <strong>Cuidado:</strong> el modo reemplazo total sobrescribirá todas las claves del archivo.
                    Asegúrate de que el archivo sea completo y correcto.
                </div>

                <button type="submit" class="btn btn-success btn-block" id="btnSubir" disabled>
                    <i class="simple-icon-cloud-upload mr-1"></i> Aplicar Archivo
                </button>
            </form>
        </div>

        <!-- Info de respaldos -->
        <div class="cred-section">
            <h5><i class="simple-icon-shield mr-2"></i>Respaldos automáticos</h5>
            <p style="font-size:.82rem;color:#555;margin:0;">
                Cada vez que guardes cambios (manual o por archivo), se crea automáticamente
                un respaldo del <code>.env</code> actual en la raíz del proyecto con el formato
                <code>.env.bak_YYYYMMDD_HHmmss</code>. Puedes restaurarlo manualmente si algo sale mal.
            </p>
        </div>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_js') ?>
<script>
(function () {
    // ── Toggle password visibility ─────────────────────────────────
    document.querySelectorAll('.btn-toggle-pass').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.querySelector(btn.dataset.target);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.querySelector('i').className =
                input.type === 'password' ? 'simple-icon-eye' : 'simple-icon-eye-off';
        });
    });

    // ── Upload zone ────────────────────────────────────────────────
    var zone    = document.getElementById('uploadZone');
    var input   = document.getElementById('envFile');
    var nombre  = document.getElementById('nombreArchivo');
    var btnSub  = document.getElementById('btnSubir');
    var trigger = document.getElementById('triggerFile');

    trigger.addEventListener('click', function (e) {
        e.preventDefault();
        input.click();
    });

    zone.addEventListener('click', function (e) {
        if (e.target !== trigger) input.click();
    });

    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('drag-over');
    });

    zone.addEventListener('dragleave', function () {
        zone.classList.remove('drag-over');
    });

    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
            setFile(e.dataTransfer.files[0]);
        }
    });

    input.addEventListener('change', function () {
        if (input.files.length) setFile(input.files[0]);
    });

    function setFile(file) {
        // Transferir el archivo al input real si vino por drop
        if (file !== input.files[0]) {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        }
        nombre.textContent = '📄 ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        nombre.style.display = 'block';
        btnSub.disabled = false;
    }

    // ── Alerta modo reemplazo ──────────────────────────────────────
    document.querySelectorAll('input[name="modo_reemplazo"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('alertaReemplazo').style.display =
                r.value === '1' && r.checked ? 'block' : 'none';
        });
    });
})();
</script>
<?= $this->endSection() ?>
