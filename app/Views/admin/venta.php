<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<style>
.stats-ticker-wrap {
    overflow: hidden;
    width: 100%;
    padding: 8px 0;
}
.stats-ticker {
    display: flex;
    width: max-content;
    animation: statsScroll 40s linear infinite;
}
.stats-ticker:hover {
    animation-play-state: paused;
}
@keyframes statsScroll {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
.stat-card-item {
    min-width: 200px;
    max-width: 200px;
    margin-right: 16px;
    flex-shrink: 0;
}
.stat-card-item .card {
    border-radius: 10px;
    text-decoration: none;
    display: block;
    transition: box-shadow .2s;
}
.stat-card-item .card:hover {
    box-shadow: 0 4px 18px rgba(0,0,0,.13);
}
.stat-card-item .card-body {
    padding: 1.2rem .8rem;
    text-align: center;
}
.stat-card-item i {
    font-size: 2rem;
    color: #145388;
}
.stat-card-item .card-text {
    font-size: .78rem;
    color: #888;
    margin-bottom: 2px;
}
.stat-card-item .lead {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2d2d2d;
    margin: 0;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
    $totalNotas    = count($notas);
    // Sumar notas pagadas (5), anticipo (4) y liquidadas (6)
    $totalVentas   = array_sum(array_column(
        array_filter($notas, fn($n) => in_array((int)($n['idstatus'] ?? 0), [4, 5, 6])),
        'total'
    ));
    $notasPagadas    = count(array_filter($notas, fn($n) => ($n['idstatus'] ?? 0) == 5));
    $notasAbiertas   = count(array_filter($notas, fn($n) => in_array($n['idstatus'] ?? 0, [1, 2])));
    $notasCanceladas = count(array_filter($notas, fn($n) => ($n['idstatus'] ?? 0) == 3));
    $notasAnticipo   = count(array_filter($notas, fn($n) => ($n['idstatus'] ?? 0) == 4));
?>

<div class="row">
    <div class="col-12">
        <h1>Ventas del Día</h1>
        <nav class="breadcrumb-container d-none d-sm-block d-lg-inline-block" aria-label="breadcrumb">
            <ol class="breadcrumb pt-0">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Venta</li>
            </ol>
        </nav>
        <div class="separator mb-5"></div>
    </div>

    <!-- Tarjetas de contadores — scroll CSS infinito -->
    <div class="col-lg-12 mb-4">
        <div class="stats-ticker-wrap">
            <div class="stats-ticker">
                <?php
                $cards = [
                    ['icon' => 'iconsminds-basket-coins',  'label' => 'Notas del día',   'value' => $totalNotas],
                    ['icon' => 'iconsminds-mail-read',      'label' => 'Notas pagadas',   'value' => $notasPagadas],
                    ['icon' => 'iconsminds-clock',          'label' => 'Notas abiertas',  'value' => $notasAbiertas],
                    ['icon' => 'iconsminds-remove',         'label' => 'Canceladas',      'value' => $notasCanceladas],
                    ['icon' => 'iconsminds-coins',          'label' => 'Anticipo',        'value' => $notasAnticipo],
                    ['icon' => 'iconsminds-dollar-sign-2',  'label' => 'Total vendido',   'value' => '$' . number_format($totalVentas, 0)],
                ];
                // Duplicar para loop seamless
                $allCards = array_merge($cards, $cards);
                foreach ($allCards as $c): ?>
                <div class="stat-card-item">
                    <a href="#" class="card" onclick="return false;">
                        <div class="card-body">
                            <i class="<?= $c['icon'] ?>"></i>
                            <p class="card-text mb-0"><?= $c['label'] ?></p>
                            <p class="lead"><?= $c['value'] ?></p>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tabla de notas del día -->
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Notas — <?= date('d/m/Y') ?></h5>
                <div class="table-responsive">
                    <table id="tablaVentas" class="table data-table data-table-standard responsive nowrap" data-order='[[0,"desc"]]'>
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Fecha/Hora</th>
                                <th>Cliente</th>
                                <th class="text-right">Total</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notas as $n):
                                $status = (int)($n['idstatus'] ?? 0);
                                $folio = (int)$n['folio'];
                                $statusBadges = [
                                    1 => '<span class="badge badge-primary">Abierta</span>',
                                    2 => '<span class="badge badge-info">En proceso</span>',
                                    3 => '<span class="badge badge-danger">Cancelada</span>',
                                    4 => '<span class="badge badge-warning">Anticipo</span>',
                                    5 => '<span class="badge badge-success">Pagada</span>',
                                    6 => '<span class="badge" style="background-color:#0d6e6e;color:#fff;">Pagado</span>',
                                ];
                                $badgeHtml = $statusBadges[$status] ?? '<span class="badge badge-secondary">—</span>';
                            ?>
                            <tr>
                                <td><strong><?= $folio ?></strong></td>
                                <td><?= esc($n['fecha_inicial']) ?></td>
                                <td><?= esc($n['nombreCliente'] ?? '—') ?></td>
                                <td class="text-right font-weight-bold">$<?= number_format($n['total'] ?? 0, 2) ?></td>
                                <td><?= $badgeHtml ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold bg-light">
                                <td colspan="3" class="text-right">Total notas cobradas:</td>
                                <td class="text-right text-success">$<?= number_format($totalVentas, 2) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
