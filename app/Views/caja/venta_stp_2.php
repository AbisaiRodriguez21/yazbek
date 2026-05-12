<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Cobrar Nota #<?= (int)$folio ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Caja</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('caja/cobrar') ?>">Cobrar</a></li>
                    <li class="breadcrumb-item active">Folio #<?= (int)$folio ?></li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <a href="<?= base_url('caja/folio/' . (int)$folio) ?>" class="btn btn-outline-secondary">
                <i class="iconsminds-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Resumen de la nota -->
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Datos de la Nota</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Folio</th><td><?= (int)$nota['folio'] ?></td></tr>
                    <tr><th>Cliente</th><td><?= esc($nota['cliente'] ?? '') ?></td></tr>
                    <tr><th>Vendedor</th><td><?= esc($nota['vendedor'] ?? '') ?></td></tr>
                    <tr><th>Fecha</th><td><?= date('d/m/Y', strtotime($nota['fecha_inicial'])) ?></td></tr>
                    <tr><th>Total Piezas</th><td><?= (int)$totalPiezas ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header font-weight-bold">Totales</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Subtotal</th><td class="text-right">$<?= number_format($nota['subTotal'] ?? 0, 2) ?></td></tr>
                    <tr><th>Descuento</th><td class="text-right"><?= number_format($nota['descuento'] ?? 0, 2) ?>%</td></tr>
                    <tr><th>IVA</th><td class="text-right">$<?= number_format($nota['iva'] ?? 0, 2) ?></td></tr>
                    <tr class="font-weight-bold">
                        <th>Total a Cobrar</th>
                        <td class="text-right text-success font-size-lg">
                            $<?= number_format($nota['total'] ?? 0, 2) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Pagos ya registrados -->
        <?php if (!empty($pagos)): ?>
        <div class="card">
            <div class="card-header font-weight-bold">Pagos ya Registrados</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Tipo</th><th class="text-right">Monto</th><th class="text-right">Cargo</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $p): ?>
                        <tr>
                            <td><?= esc($p['descripcion']) ?></td>
                            <td class="text-right">$<?= number_format($p['monto'], 2) ?></td>
                            <td class="text-right">$<?= number_format($p['cargos'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Productos y acción de pago -->
    <div class="col-md-7">
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Productos</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Descripción</th>
                            <th class="text-right">P.U.</th>
                            <th class="text-right">Cant.</th>
                            <th class="text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $d): ?>
                        <tr>
                            <td><?= esc($d['sku']) ?></td>
                            <td><?= esc($d['estilo']) ?></td>
                            <td class="text-right">$<?= number_format($d['precio'], 2) ?></td>
                            <td class="text-right"><?= (int)$d['cantidad'] ?></td>
                            <td class="text-right">$<?= number_format($d['importe'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Registrar pago -->
        <div class="card">
            <div class="card-header font-weight-bold">Registrar Pago</div>
            <div class="card-body">
                <form method="POST" action="<?= base_url('caja/pago/procesar') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="folio" value="<?= (int)$folio ?>">
                    <div class="form-group">
                        <label>Tipo de Pago <span class="text-danger">*</span></label>
                        <select name="tipoPago" class="form-control" required>
                            <option value="">— Selecciona —</option>
                            <?php foreach ($tipoPagos as $tp): ?>
                            <option value="<?= (int)$tp['id'] ?>"><?= esc($tp['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Monto <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" name="monto" class="form-control"
                                   min="0" step="0.01"
                                   value="<?= number_format($nota['total'] ?? 0, 2, '.', '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Cargos (TC/TD)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" name="cargos" class="form-control" min="0" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-success btn-lg"
                                onclick="return confirm('¿Confirmar el pago de la nota #<?= (int)$folio ?>?')">
                            <i class="iconsminds-yes"></i> Confirmar Pago
                        </button>
                    </div>
                </form>

                <hr>
                <!-- Verificar pago con un clic -->
                <p class="text-muted text-center mb-2">O simplemente verificar el pago ya registrado:</p>
                <div class="text-center">
                    <button type="button" class="btn btn-outline-primary" id="btnPagoVerificado">
                        <i class="iconsminds-yes"></i> Pago Verificado
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
(function() {
    var btn = document.getElementById('btnPagoVerificado');
    if (!btn) return;

    btn.addEventListener('click', function() {
        if (!confirm('¿Marcar la nota #<?= (int)$folio ?> como pagada?')) return;

        btn.disabled = true;
        btn.textContent = 'Procesando...';

        fetch('<?= base_url('caja/pago/verificado/' . (int)$folio) ?>', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            alert(data.mensaje || 'Pago verificado correctamente.');
            window.location.href = '<?= base_url('caja/consulta') ?>';
        })
        .catch(function() {
            alert('Error al verificar el pago. Intenta de nuevo.');
            btn.disabled = false;
            btn.innerHTML = '<i class="iconsminds-yes"></i> Pago Verificado';
        });
    });
})();
</script>
<?= $this->endSection() ?>
