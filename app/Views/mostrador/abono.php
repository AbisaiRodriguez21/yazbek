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
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header font-weight-bold" style="padding-top: 1rem; padding-bottom: 1rem;">Nuevo Abono</div>
            <div class="card-body">
                <form method="POST" action="<?= base_url($basePrefix . '/venta/' . (int)$folio . '/abono') ?>">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Cantidad <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                            <input type="number" name="monto" class="form-control" min="0.01" step="0.01"
                                   value="<?= $restante > 0 ? number_format($restante, 2, '.', '') : '' ?>" required>
                        </div>
                    </div>
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
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="chkAbonoAnticipo" checked disabled>
                            <label class="custom-control-label" for="chkAbonoAnticipo">Es Anticipo</label>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 mb-3">
                        <i class="simple-icon-info"></i> Este abono se envía a Caja como un folio nuevo,
                        pendiente de que Caja lo reciba y confirme. No se descuenta del restante hasta
                        que Caja lo confirme.
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
