<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/select2.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/select2-bootstrap.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$esMayoreo   = isset($tipoVenta) && $tipoVenta === 'mayoreo';
$tituloVenta = $esMayoreo ? 'Venta Mayoreo' : 'Venta';
$accionForm  = $esMayoreo ? base_url('mostrador/mayoreo') : base_url('mostrador/venta');
?>

<div class="page-title-container">
    <div class="page-title">
        <h1><?= $tituloVenta ?> — Paso 1</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('mostrador') ?>">Mostrador</a></li>
                <li class="breadcrumb-item active"><?= $tituloVenta ?></li>
            </ol>
        </nav>
    </div>
</div>

<?php if ($esMayoreo): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="iconsminds-information mr-1"></i>
    <strong>Modo Venta Mayoreo:</strong> Los precios de esta nota usarán tarifa de mayoreo sin importar la cantidad de piezas.
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Seleccionar Cliente</span>
                <span class="text-muted"><?= date('Y-m-d') ?></span>
            </div>
            <div class="card-body">

                <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= $accionForm ?>">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label>Nombre del Cliente <span class="text-danger">*</span></label>
                        <select name="idCliente" id="selectCliente" class="form-control" required>
                            <option value="">— Escribe para buscar un cliente —</option>
                        </select>
                    </div>

                    <!-- Datos del cliente (se llenan vía AJAX al seleccionar) -->
                    <div id="datosCliente" style="display:none;">
                        <div class="form-group">
                            <label>Dirección</label>
                            <input type="text" class="form-control" id="clienteDireccion" readonly>
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" class="form-control" id="clienteTelefono" readonly>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="text" class="form-control" id="clienteEmail" readonly>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label>Atendió</label>
                        <p class="font-weight-bold"><?= esc($usuario['nombre']) ?></p>
                        <input type="hidden" name="idVendedor" value="<?= (int)$usuario['Id'] ?>">
                    </div>

                    <div class="text-right mt-3">
                        <a href="<?= base_url('mostrador') ?>" class="btn btn-secondary mr-2">Cancelar</a>
                        <button type="submit" class="btn <?= $esMayoreo ? 'btn-success' : 'btn-primary' ?>">
                            Siguiente <i class="iconsminds-arrow-right ml-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/js/vendor/select2.full.js') ?>"></script>
<script>
(function() {
    function initSelectCliente() {
        if (typeof $.fn.select2 === 'undefined') {
            setTimeout(initSelectCliente, 100);
            return;
        }
        $('#selectCliente').select2({
        theme: 'bootstrap',
        placeholder: '— Escribe para buscar un cliente —',
        allowClear: true,
        width: '100%',
        minimumInputLength: 1,
        language: {
            inputTooShort: function() { return 'Escribe al menos 1 carácter para buscar...'; },
            searching: function() { return 'Buscando...'; },
            noResults: function() { return 'No se encontraron clientes.'; }
        },
        ajax: {
            url: '<?= base_url('mostrador/clientes/buscar') ?>',
            type: 'POST',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return {
                    termino: params.term,
                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
                };
            },
            processResults: function(data) {
                return {
                    results: $.map(data, function(c) {
                        return {
                            id: c.id,
                            text: (c.RFC ? c.RFC + ' | ' : '') + c.nombre
                        };
                    })
                };
            },
            cache: true
        }
    });

    $('#selectCliente').on('change', function() {
        var idCliente = $(this).val();
        if (!idCliente) {
            $('#datosCliente').hide();
            return;
        }
        $.post('<?= base_url('mostrador/clientes/datos') ?>', {
            idCliente: idCliente,
            '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
        }, function(data) {
            if (data.success) {
                $('#clienteDireccion').val(data.direccion);
                $('#clienteTelefono').val(data.telefono);
                $('#clienteEmail').val(data.email);
                $('#datosCliente').show();
            }
        }, 'json').fail(function() {
            $('#datosCliente').hide();
        });
    });
    }
    initSelectCliente();
})();
</script>
<?= $this->endSection() ?>
