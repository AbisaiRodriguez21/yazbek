<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $basePrefix = $base ?? 'mostrador'; ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Nota #<?= (int)$folio ?> — Registrar Abono</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url($basePrefix) ?>"><?= $basePrefix === 'admin' ? 'Admin' : 'Mostrador' ?></a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url($basePrefix . '/consulta') ?>">Consulta</a></li>
                    <li class="breadcrumb-item active">Abono</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <a href="<?= base_url($basePrefix . '/consulta') ?>" class="btn btn-outline-secondary">
                <i class="iconsminds-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Datos de la Nota</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Folio</th><td><?= (int)$folio ?></td></tr>
                    <tr><th>Cliente</th><td><?= esc($nota['cliente'] ?? '') ?></td></tr>
                    <tr><th>Total de la nota</th><td>$<?= number_format($nota['total'] ?? 0, 2) ?></td></tr>
                    <tr><th>Ya confirmado por Caja</th><td class="text-success">$<?= number_format($yaPagado, 2) ?></td></tr>
                    <tr class="font-weight-bold"><th>Restante</th><td class="text-danger">$<?= number_format($restante, 2) ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Historial de Abonos</div>
            <div class="card-body p-0">
                <?php if (empty($historialAbonos)): ?>
                <p class="text-muted text-center my-3">Aún no se ha registrado ningún abono para esta nota.</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Forma de Pago</th>
                            <th class="text-right">Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historialAbonos as $h):
                            $statusId = (int) ($h['folio_status'] ?? 0);
                            if ($statusId === 3) {
                                $badge = '<span class="badge badge-danger">Cancelado</span>';
                            } elseif ($statusId === 5 || $statusId === 6) {
                                $badge = '<span class="badge badge-success">Confirmado por Caja</span>';
                            } else {
                                $badge = '<span class="badge badge-warning">Pendiente de Caja</span>';
                            }
                            $esRowCancelada = $statusId === 3;
                        ?>
                        <tr style="<?= $esRowCancelada ? 'opacity:.5;text-decoration:line-through;' : '' ?>">
                            <td>#<?= (int) $h['folio_pago'] ?></td>
                            <td><?= esc(substr((string) ($h['fecha'] ?? ''), 0, 16)) ?></td>
                            <td><?= esc($h['tipopago']) ?></td>
                            <td class="text-right">$<?= number_format($h['monto'] ?? 0, 2) ?></td>
                            <td><?= $badge ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Nuevo Abono</div>
            <div class="card-body">
                <form method="POST" action="<?= base_url($basePrefix . '/venta/' . (int)$folio . '/abono') ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cantidad <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                    <input type="number" name="monto" class="form-control" min="0.01" step="0.01"
                                           value="<?= $restante > 0 ? number_format($restante, 2, '.', '') : '' ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="d-block">Este pago es</label>
                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                    <label class="btn btn-outline-primary active">
                                        <input type="radio" name="tipoAbono" value="anticipo" id="rdAbonoAnticipo" checked> Anticipo
                                    </label>
                                    <label class="btn btn-outline-primary">
                                        <input type="radio" name="tipoAbono" value="liquidar" id="rdAbonoLiquidar"> Liquidar
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipo de Pago <span class="text-danger">*</span></label>
                                <select name="tipoPago" class="form-control" required>
                                    <option value="">— Selecciona —</option>
                                    <?php foreach ($tipoPagos as $tp): ?>
                                    <option value="<?= (int)$tp['id'] ?>"><?= esc($tp['descripcion']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 mb-3" id="abonoInfoAlert">
                        <i class="simple-icon-info"></i>
                        <span id="abonoInfoTexto">Este es un <strong>anticipo</strong>: se envía a Caja como un folio nuevo,
                        pendiente de que lo reciba y confirme. No se descuenta del restante hasta que Caja lo confirme.</span>
                    </div>
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="iconsminds-add"></i> Registrar Abono
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
function abonoActualizarInfo() {
    var esLiquidar = document.getElementById('rdAbonoLiquidar').checked;
    var alertBox   = document.getElementById('abonoInfoAlert');
    var texto      = document.getElementById('abonoInfoTexto');

    if (esLiquidar) {
        alertBox.classList.remove('alert-info');
        alertBox.classList.add('alert-warning');
        texto.innerHTML = 'Esto <strong>liquida</strong> la nota (no es un adelanto): se envía a Caja como un ' +
            'folio nuevo, pendiente de que lo reciba y confirme. No se descuenta del restante hasta que Caja lo confirme.';
    } else {
        alertBox.classList.remove('alert-warning');
        alertBox.classList.add('alert-info');
        texto.innerHTML = 'Este es un <strong>anticipo</strong>: se envía a Caja como un folio nuevo, ' +
            'pendiente de que lo reciba y confirme. No se descuenta del restante hasta que Caja lo confirme.';
    }
}

document.getElementById('rdAbonoAnticipo').addEventListener('change', abonoActualizarInfo);
document.getElementById('rdAbonoLiquidar').addEventListener('change', abonoActualizarInfo);
</script>
<?= $this->endSection() ?>
