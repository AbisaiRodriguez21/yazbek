<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
    .format-box {
        background: #2d3e52;
        border: 1px solid #3d4e62;
        border-radius: 4px;
        padding: 1.5rem;
        margin: 1rem 0;
    }

    .format-box h6 {
        color: #5a7a9e;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .format-box code {
        background: #1e2d47;
        color: #a0bfd9;
        padding: 0.5rem 1rem;
        border-radius: 3px;
        display: block;
        overflow-x: auto;
        margin-bottom: 0.5rem;
    }

    .format-box ul {
        color: #aaa;
        margin: 0;
        padding-left: 1.5rem;
        line-height: 1.8;
    }

    .format-box ul li {
        margin-bottom: 0.5rem;
    }

    .upload-area {
        border: 2px dashed #5a7a9e;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        background: #2d3e52;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .upload-area:hover {
        border-color: #7a9abe;
        background: #354555;
    }

    .upload-area.drag-over {
        border-color: #5a9a9e;
        background: #354555;
    }

    .upload-area i {
        font-size: 2.5rem;
        color: #5a7a9e;
        margin-bottom: 0.5rem;
        display: block;
    }

    .upload-area p {
        color: #aaa;
        margin: 0.5rem 0 0 0;
    }

    .alert {
        margin-bottom: 1.5rem;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Page Title -->
<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Importar Productos desde CSV</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= base_url('admin') ?>">Inicio</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="<?= base_url('admin/inventario') ?>">Inventario</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Importar</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<!-- Alertas -->
<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="simple-icon-check"></i> <?= esc($success) ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="simple-icon-close"></i> <?= esc($error) ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Instrucciones -->
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Formato de Archivo</div>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    El archivo CSV debe contener las siguientes columnas en este orden:
                </p>

                <div class="format-box">
                    <h6>Columnas Requeridas</h6>
                    <ul>
                        <li><strong>estilo</strong> — Código de estilo del producto</li>
                        <li><strong>sku</strong> — SKU único del producto</li>
                        <li><strong>Descripcion_corta</strong> — Descripción corta</li>
                        <li><strong>Descripcion_Larga</strong> — Descripción larga completa</li>
                        <li><strong>Color</strong> — Color del producto</li>
                        <li><strong>Talla</strong> — Talla o tamaño</li>
                        <li><strong>pMenudeo</strong> — Precio de menudeo</li>
                        <li><strong>pMayoreo</strong> — Precio de mayoreo</li>
                        <li><strong>piezas</strong> — Cantidad de piezas en inventario</li>
                    </ul>
                </div>

                <p class="text-muted mt-3 mb-1">Ejemplo de CSV:</p>
                <code style="background: #1e2d47; color: #a0bfd9; padding: 0.75rem; border-radius: 3px; display: block; overflow-x: auto; font-size: 0.85rem;">estilo,sku,Descripcion_corta,Descripcion_Larga,Color,Talla,pMenudeo,pMayoreo,piezas<br>EST001,SKU001,Camisa azul,Camisa de algodón azul,Azul,M,150.00,120.00,50</code>

                <div class="alert alert-info mt-3 mb-0">
                    <i class="simple-icon-info"></i> Los precios deben ser números con decimales (ej: 150.00)
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario -->
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Cargar Archivo</div>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= base_url('admin/importar/procesar') ?>" enctype="multipart/form-data" id="formImportar">
                    <div class="form-group">
                        <label for="archivo">Seleccionar archivo CSV</label>
                        <div class="upload-area" id="uploadArea">
                            <i class="iconsminds-cloud-upload"></i>
                            <p class="mb-0">Arrastra el archivo aquí o haz clic para seleccionar</p>
                            <p class="text-muted small">Máximo 5 MB</p>
                            <input type="file" id="archivo" name="archivo" accept=".csv" style="display: none;" required>
                        </div>
                        <small class="text-muted d-block mt-2">
                            Archivo seleccionado: <span id="fileName">Ninguno</span>
                        </small>
                    </div>

                    <div class="form-group mt-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="confirmar" name="confirmar" required>
                            <label class="custom-control-label" for="confirmar">
                                Confirmo que el archivo tiene el formato correcto
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>
                        <i class="iconsminds-cloud-upload"></i> Importar Productos
                    </button>
                </form>

                <div class="alert alert-warning mt-3 mb-0">
                    <i class="simple-icon-exclamation"></i>
                    <strong>Advertencia:</strong> Esta acción importará o actualizará productos en la base de datos.
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
$(document).ready(function() {
    var uploadArea = $('#uploadArea');
    var fileInput = $('#archivo');
    var fileName = $('#fileName');
    var submitBtn = $('#submitBtn');
    var confirmar = $('#confirmar');

    // Click to upload
    uploadArea.click(function() {
        fileInput.click();
    });

    // File selected from input
    fileInput.change(function() {
        if (this.files && this.files[0]) {
            fileName.text(this.files[0].name);
            checkForm();
        }
    });

    // Drag and drop
    uploadArea.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.addClass('drag-over');
    });

    uploadArea.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.removeClass('drag-over');
    });

    uploadArea.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.removeClass('drag-over');

        var files = e.originalEvent.dataTransfer.files;
        if (files && files[0]) {
            fileInput[0].files = files;
            fileName.text(files[0].name);
            checkForm();
        }
    });

    // Enable/Disable submit button
    confirmar.change(function() {
        checkForm();
    });

    function checkForm() {
        var hasFile = fileInput[0].files && fileInput[0].files.length > 0;
        var isConfirmed = confirmar.is(':checked');
        submitBtn.prop('disabled', !(hasFile && isConfirmed));
    }

    // Form submission
    $('#formImportar').submit(function(e) {
        if (!fileInput[0].files || !fileInput[0].files[0]) {
            e.preventDefault();
            alert('Por favor selecciona un archivo CSV');
        }
    });
});
</script>
<?= $this->endSection() ?>
