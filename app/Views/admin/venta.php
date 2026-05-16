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
    animation: statsScroll 18s linear infinite;
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
    // Solo sumar notas pagadas (5) o con anticipo (4) — excluir canceladas y abiertas
    $totalVentas   = array_sum(array_column(
        array_filter($notas, fn($n) => in_array((int)($n['idstatus'] ?? 0), [4, 5])),
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
                                <th class="text-right">Subtotal</th>
                                <th class="text-right">Total</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($notas)): ?>
                                <?php foreach ($notas as $n):
                                    $status = (int)($n['idstatus'] ?? 0);
                                    $badges = [
                                        1 => ['Abierta',    'warning'],
                                        2 => ['En proceso', 'info'],
                                        3 => ['Cancelada',  'danger'],
                                        4 => ['Anticipo',   'secondary'],
                                        5 => ['Pagada',     'success'],
                                    ];
                                    [$label, $color] = $badges[$status] ?? ['—', 'light'];
                                    $folio = (int)$n['folio'];
                                ?>
                                <tr>
                                    <td><strong><?= $folio ?></strong></td>
                                    <td><?= esc($n['fecha_inicial']) ?></td>
                                    <td><?= esc($n['nombreCliente'] ?? '—') ?></td>
                                    <td class="text-right">$<?= number_format($n['subTotal'] ?? 0, 2) ?></td>
                                    <td class="text-right font-weight-bold">$<?= number_format($n['total'] ?? 0, 2) ?></td>
                                    <td><span class="badge badge-<?= $color ?>"><?= $label ?></span></td>
                                    <td>
                                        <?php if ($status !== 3): ?>
                                        <a href="#" class="btn btn-xs btn-outline-primary mr-1"
                                           onclick="adminVerFolioVenta(<?= $folio ?>); return false;">
                                            <i class="simple-icon-eye"></i> Ver
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($status !== 5 && $status !== 3): ?>
                                        <a href="#" class="btn btn-xs btn-outline-danger"
                                           onclick="adminCancelarFolioVenta(<?= $folio ?>); return false;">
                                            Cancelar
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="iconsminds-receipt-4 icon-dual icon-lg d-block mb-2"></i>
                                        No hay notas registradas hoy.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($notas)): ?>
                        <tfoot>
                            <tr class="font-weight-bold bg-light">
                                <td colspan="4" class="text-right">Total General:</td>
                                <td class="text-right text-success">$<?= number_format($totalVentas, 2) ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Folio -->
<div class="modal fade" id="modalVerFolioVenta" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Folio</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="modalVerFolioVentaBody">
                <p class="text-center"><i>Cargando...</i></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>

function postFetchVenta(url, folio, onSuccess, onError) {
    var fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fd.append('folio', folio);
    fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.text(); })
        .then(onSuccess)
        .catch(onError);
}

function adminVerFolioVenta(folio) {
    document.getElementById('modalVerFolioVentaBody').innerHTML = '<p class="text-center"><i>Cargando...</i></p>';
    $('#modalVerFolioVenta').modal('show');
    postFetchVenta(
        '<?= base_url('admin/caja/ajax') ?>',
        folio,
        function(html) {
            document.getElementById('modalVerFolioVentaBody').innerHTML = html;
        },
        function() {
            document.getElementById('modalVerFolioVentaBody').innerHTML = '<p class="text-danger">Error al cargar el folio.</p>';
        }
    );
}

function adminCancelarFolioVenta(folio) {
    if (!confirm('¿Cancelar el folio ' + folio + '?')) return;
    postFetchVenta(
        '<?= base_url('admin/caja/cancelar') ?>',
        folio,
        function(resp) {
            if (resp.trim() === '1') {
                window.location.reload();
            } else {
                alert('No se pudo cancelar el folio ' + folio + '.');
            }
        },
        function() {
            alert('Error de comunicación al cancelar.');
        }
    );
}
</script>
<?= $this->endSection() ?>
