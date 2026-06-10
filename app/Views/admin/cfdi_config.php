<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Configuración CFDI</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Configuración CFDI</li>
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

<form method="POST" action="<?= base_url('admin/cfdi-config/guardar') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <!-- ── CSD (Certificado de Sello Digital) ─────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header" style="background:#1F4E79;color:#fff;font-weight:bold;">
            🔐 Certificado de Sello Digital (CSD)
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="font-weight-bold">Archivo .cer (Certificado público)</label>
                    <?php if (is_array($cerInfo) && !empty($cerInfo['existe'])): ?>
                        <div class="alert alert-success py-1 px-2 mb-1" style="font-size:12px;">
                            ✅ Archivo actual: <b>csd.cer</b> — <?= $cerInfo['tamaño'] ?> bytes — subido el <?= $cerInfo['fecha'] ?>
                        </div>
                    <?php else: ?>
                    <?php endif; ?>
                    <input type="file" name="csd_cer" accept=".cer" class="form-control-file">
                    <small class="text-muted">Sube el nuevo .cer para reemplazar el actual</small>
                </div>
                <div class="col-md-6">
                    <label class="font-weight-bold">Archivo .key (Llave privada)</label>
                    <?php if (is_array($keyInfo) && !empty($keyInfo['existe'])): ?>
                        <div class="alert alert-success py-1 px-2 mb-1" style="font-size:12px;">
                            ✅ Archivo actual: <b>csd.key</b> — <?= $keyInfo['tamaño'] ?> bytes — subido el <?= $keyInfo['fecha'] ?>
                        </div>
                    <?php else: ?>
                    <?php endif; ?>
                    <input type="file" name="csd_key" accept=".key" class="form-control-file">
                    <small class="text-muted">Sube el nuevo .key para reemplazar el actual</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-5">
                    <label class="font-weight-bold">Contraseña del certificado (.key)</label>
                    <input type="password" name="csd_password" class="form-control"
                           placeholder="Dejar vacío para no cambiarla">
                </div>
            </div>
        </div>
    </div>

    <!-- ── Correo de copia interna de facturas ────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header" style="background:#1F4E79;color:#fff;font-weight:bold;">
            📧 Correo interno de facturación
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <label class="font-weight-bold">Correo donde se recibe copia de cada factura</label>
                    <input type="email" name="empresa_email_facturacion" class="form-control"
                           value="<?= esc($cfg['empresa_email_facturacion'] ?? 'facturacionyazbek0@gmail.com') ?>"
                           placeholder="facturacionyazbek0@gmail.com">
                    <small class="text-muted">Se envía CC a este correo cada vez que se timbra una factura.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="text-right mb-5">
        <button type="submit" class="btn btn-primary btn-lg">
            💾 Guardar configuración
        </button>
    </div>

</form>

<?= $this->endSection() ?>
