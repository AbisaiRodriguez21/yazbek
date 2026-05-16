<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/datatables.responsive.bootstrap4.min.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1>Dashboard Admin</h1>
        <nav class="breadcrumb-container d-none d-sm-block d-lg-inline-block" aria-label="breadcrumb">
            <ol class="breadcrumb pt-0">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Home</a></li>
            </ol>
        </nav>
        <div class="separator mb-5"></div>
    </div>

    <!-- Tarjetas de contadores — 4 fichas fijas del día -->
    <div class="col-lg-12 col-xl-6 mb-4">
        <div class="row">
            <div class="col-6 col-xl-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="iconsminds-basket-coins" style="font-size:2.5rem; color:#145388;"></i>
                        <p class="card-text mb-0 mt-2 text-muted small">Órdenes del día</p>
                        <p class="lead mb-0 font-weight-bold"><?= (int)($totalHoy ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="iconsminds-clock" style="font-size:2.5rem; color:#145388;"></i>
                        <p class="card-text mb-0 mt-2 text-muted small">Órdenes en Anticipo</p>
                        <p class="lead mb-0 font-weight-bold"><?= (int)($totalAnticipo ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="iconsminds-arrow-refresh" style="font-size:2.5rem; color:#145388;"></i>
                        <p class="card-text mb-0 mt-2 text-muted small">Órdenes canceladas</p>
                        <p class="lead mb-0 font-weight-bold"><?= (int)($totalCancelado ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="iconsminds-mail-read" style="font-size:2.5rem; color:#145388;"></i>
                        <p class="card-text mb-0 mt-2 text-muted small">Órdenes confirmadas</p>
                        <p class="lead mb-0 font-weight-bold"><?= (int)($totalPagado ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lo más vendidos -->
        <div class="row">
            <div class="col-xl-12 col-lg-12 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Lo más vendidos</h5>
                        <table id="masvendidosTable" class="table data-table data-table-standard responsive nowrap" data-order='[[1,"desc"]]'>
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Productos vendidos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($masVendidos)): ?>
                                    <?php foreach ($masVendidos as $item): ?>
                                    <tr>
                                        <td><?= esc($item['sku'] ?? '') ?></td>
                                        <td><?= (int)($item['totalVentas'] ?? 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Órdenes recientes -->
    <div class="col-xl-6 col-lg-12 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Órdenes recientes</h5>
                <?php if (!empty($recientes)): ?>
                    <div class="scroll-area-lg">
                        <div class="scrollbar-container">
                            <?php foreach ($recientes as $orden): ?>
                            <div class="p-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="font-weight-bold mb-0">Folio: <?= (int)($orden['folio'] ?? $orden['Id_Notas_1'] ?? 0) ?></p>
                                        <p class="text-muted small mb-0"><?= esc($orden['nombre'] ?? $orden['nombreCliente'] ?? '—') ?></p>
                                        <p class="text-muted small mb-0"><?= esc($orden['fecha_inicial'] ?? '') ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-weight-bold">$<?= number_format($orden['total'] ?? 0, 2) ?></span>
                                        <?php
                                            $st = (int)($orden['idstatus'] ?? $orden['status'] ?? 0);
                                            $bmap = [3=>'danger',4=>'warning',5=>'success',1=>'secondary',2=>'info'];
                                            $lmap = [3=>'Cancelada',4=>'Anticipo',5=>'Pagada',1=>'Abierta',2=>'En proceso'];
                                            if (isset($bmap[$st])):
                                        ?>
                                        <br><span class="badge badge-<?= $bmap[$st] ?>"><?= $lmap[$st] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="iconsminds-receipt-4 icon-dual icon-lg d-block mb-2" style="font-size:3rem"></i>
                        <p>Sin datos por el momento</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
// DataTable lo inicializa dore.script.js via clase .data-table-standard
</script>
<?= $this->endSection() ?>