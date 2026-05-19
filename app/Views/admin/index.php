<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
/* ── KPI Cards ── */
.kpi-card { border-radius: 12px; border: none; transition: box-shadow .2s; }
.kpi-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.12); }
.kpi-icon { font-size: 2rem; }
.kpi-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #888; }
.kpi-value { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
.kpi-sub   { font-size: .75rem; color: #aaa; margin-top: 2px; }

/* ── Chart cards ── */
.chart-card { border-radius: 12px; border: none; }
.chart-card .card-title { font-size: .85rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #555; }
.chart-container { position: relative; }

/* ── Tabla recientes ── */
.nota-reciente { transition: background .15s; }
.nota-reciente:hover { background: #f8f9fa; }

/* ── Stock bajo ── */
.stock-badge { display: inline-block; min-width: 36px; text-align: center; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Helpers PHP
$fmt = fn($v) => '$' . number_format($v, 0, '.', ',');
$bmap = [1=>'secondary',2=>'info',3=>'danger',4=>'warning',5=>'success'];
$lmap = [1=>'Abierta',2=>'En proceso',3=>'Cancelada',4=>'Anticipo',5=>'Pagada'];
?>

<!-- ── Encabezado ── -->
<div class="row mb-2">
    <div class="col-12 d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h1 class="mb-0">Dashboard Admin</h1>
            <small class="text-muted"><?= fecha_es('full') ?> &nbsp;·&nbsp; Año <?= $anio ?></small>
        </div>
    </div>
    <div class="col-12"><div class="separator mt-3 mb-4"></div></div>
</div>

<!-- ── Selector de período ─────────────────────────────────────── -->
<?php
$mesesNombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                 'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$mesActualNum = (int)date('n');
?>
<div class="card mb-4" style="border-radius:10px;border:none;background:#f4f7fa">
    <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center flex-wrap" style="gap:10px">
            <span style="font-size:.82rem;font-weight:600;color:#555">
                <i class="simple-icon-calendar mr-1"></i> Período de análisis:
            </span>

            <!-- Mes -->
            <select id="selMes" class="form-control form-control-sm" style="width:auto">
                <?php foreach ($mesesNombres as $i => $mn):
                    $v = $i + 1;
                    $sel = $v === $mesActualNum ? ' selected' : '';
                ?>
                <option value="<?= $v ?>"<?= $sel ?>><?= $mn ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Año -->
            <select id="selAnio" class="form-control form-control-sm" style="width:auto">
                <?php foreach (array_reverse($aniosLabels) as $y):
                    $sel = (int)$y === $anio ? ' selected' : '';
                ?>
                <option value="<?= $y ?>"<?= $sel ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Botón reset -->
            <button id="btnHoy" class="btn btn-sm btn-outline-primary">
                <i class="simple-icon-refresh mr-1"></i> Período actual
            </button>

            <!-- Spinner de carga -->
            <span id="dashSpinner" style="display:none;font-size:.8rem;color:#888">
                <span class="spinner-border spinner-border-sm mr-1" role="status"></span> Actualizando…
            </span>

            <!-- Etiqueta período activo -->
            <span id="periodoActivo" class="badge badge-info ml-auto" style="font-size:.78rem;padding:5px 10px">
                <?= $mesNombreActual ?> &nbsp;vs&nbsp; <?= $mesNombreAnterior ?>
            </span>
        </div>
    </div>
</div>

<!-- Aviso general de criterio de ingresos -->
<div class="alert alert-info py-2 px-3 mb-3" style="font-size:.8rem;border-radius:8px">
    <i class="simple-icon-info mr-1"></i>
    <strong>Nota:</strong> Todos los montos de ingresos mostrados en este dashboard
    corresponden <strong>únicamente</strong> a notas con estatus
    <span class="badge badge-warning">Anticipo</span> o
    <span class="badge badge-success">Pagada</span>.
    Las notas abiertas, en proceso o canceladas no se contabilizan.
</div>

<!-- ══════════════════════════════════════════════════
     FILA 1 — KPI Cards
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- Ingresos hoy -->
    <div class="col-6 col-sm-4 col-lg mb-3">
        <div class="card kpi-card h-100" style="border-left:4px solid #28a745">
            <div class="card-body py-3 px-3">
                <i class="iconsminds-dollar-sign-2 kpi-icon d-block mb-1" style="color:#28a745"></i>
                <div class="kpi-label">Ingresos hoy</div>
                <div class="kpi-value"><?= $fmt($ingresosHoy) ?></div>
                <div class="kpi-sub"><?= $totalPagado ?> nota(s) pagada(s)</div>
            </div>
        </div>
    </div>

    <!-- Ingresos mes -->
    <div class="col-6 col-sm-4 col-lg mb-3">
        <div class="card kpi-card h-100" style="border-left:4px solid #145388">
            <div class="card-body py-3 px-3">
                <i class="iconsminds-calendar-4 kpi-icon d-block mb-1" style="color:#145388"></i>
                <div class="kpi-label">Ingresos del mes</div>
                <div class="kpi-value"><?= $fmt($ingresosMes) ?></div>
                <div class="kpi-sub"><?= fecha_es('mes') ?></div>
            </div>
        </div>
    </div>

    <!-- Ingresos año -->
    <div class="col-6 col-sm-4 col-lg mb-3">
        <div class="card kpi-card h-100" style="border-left:4px solid #6f42c1">
            <div class="card-body py-3 px-3">
                <i class="iconsminds-statistic kpi-icon d-block mb-1" style="color:#6f42c1"></i>
                <div class="kpi-label">Ingresos <?= $anio ?></div>
                <div class="kpi-value"><?= $fmt($ingresosAnio) ?></div>
                <div class="kpi-sub">año completo</div>
            </div>
        </div>
    </div>

    <!-- Notas del día -->
    <div class="col-6 col-sm-4 col-lg mb-3">
        <div class="card kpi-card h-100" style="border-left:4px solid #17a2b8">
            <div class="card-body py-3 px-3">
                <i class="iconsminds-basket-coins kpi-icon d-block mb-1" style="color:#17a2b8"></i>
                <div class="kpi-label">Notas hoy</div>
                <div class="kpi-value"><?= $totalHoy ?></div>
                <div class="kpi-sub"><?= $totalCancelado ?> cancelada(s)</div>
            </div>
        </div>
    </div>

    <!-- Clientes activos -->
    <div class="col-6 col-sm-4 col-lg mb-3">
        <div class="card kpi-card h-100" style="border-left:4px solid #fd7e14">
            <div class="card-body py-3 px-3">
                <i class="iconsminds-business-man kpi-icon d-block mb-1" style="color:#fd7e14"></i>
                <div class="kpi-label">Clientes</div>
                <div class="kpi-value"><?= number_format($clientesActivos) ?></div>
                <div class="kpi-sub">activos</div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     FILA HOY — Vendedores del día + Forma de pago hoy
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- Vendedores hoy -->
    <div class="col-lg-6 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-1">Ingresos por vendedor — <span id="labelFechaHoy"><?= date('d/m/Y') ?></span></h5>
                <p class="text-muted mb-3" style="font-size:.78rem">
                    Ingresos (Pagada + Anticipo) y notas canceladas del día
                </p>
                <div class="chart-container" style="height:260px; position:relative">
                    <div id="chartVendedoresHoy" style="width:100%;height:100%"></div>
                    <div id="loadVendedoresHoy" class="text-center text-muted pt-5">
                        <span class="spinner-border spinner-border-sm" role="status"></span> Cargando…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forma de pago hoy + botón exportar -->
    <div class="col-lg-6 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <h5 class="card-title mb-0">Forma de pago — <span id="labelFechaHoyPago"><?= date('d/m/Y') ?></span></h5>
                    <a href="<?= base_url('admin/reportediario/dia') ?>" class="btn btn-sm btn-outline-success" title="Exportar reporte del día">
                        <i class="simple-icon-cloud-download mr-1"></i> Exportar
                    </a>
                </div>
                <p class="text-muted mb-3" style="font-size:.78rem">Distribución de métodos de pago (notas Pagada + Anticipo)</p>
                <div class="chart-container flex-grow-1" style="min-height:220px">
                    <div id="chartTipoPagoHoy" style="width:100%;height:100%"></div>
                </div>
                <div class="mt-2 text-center text-muted" id="legendTipoPagoHoy" style="font-size:.8rem">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Cargando…
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     FILA 2 — Comparativa mensual
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- Notas por estatus: mes actual vs mes anterior -->
    <div class="col-lg-7 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-1">Notas por estatus — comparativa mensual</h5>
                <p class="text-muted mb-3" style="font-size:.78rem">
                    <span class="badge badge-primary" id="badgeMesActual"><?= $mesNombreActual ?></span>
                    vs
                    <span class="badge badge-secondary" id="badgeMesAnterior"><?= $mesNombreAnterior ?></span>
                    &nbsp;·&nbsp; Cantidad de folios por estatus
                </p>
                <div style="height:220px">
                    <div id="chartCompNotas" style="width:100%;height:100%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ingresos y pagadas: comparativa mensual -->
    <div class="col-lg-5 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-1">Ingresos — comparativa mensual</h5>
                <p class="text-muted mb-3" style="font-size:.78rem">
                    Solo notas <strong>Pagada</strong> + <strong>Anticipo</strong>
                </p>
                <div style="height:220px">
                    <div id="chartCompIngresos" style="width:100%;height:100%"></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     FILA 3 — Top productos mes anterior vs mes actual (AJAX)
     Orden: Anterior (izquierda) → Actual (derecha)
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- MES ANTERIOR (izquierda) -->
    <div class="col-lg-6 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-1">Top productos — <span id="labelMesAnterior"><?= $mesNombreAnterior ?></span></h5>
                <p class="text-muted mb-3" style="font-size:.78rem">Por piezas vendidas (notas pagadas/anticipo)</p>
                <div style="height:260px; position:relative">
                    <div id="chartTopMesAnterior" style="width:100%;height:100%"></div>
                    <div id="loadTopMesAnterior" class="text-center text-muted pt-5">
                        <span class="spinner-border spinner-border-sm"></span> Cargando…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MES ACTUAL (derecha) -->
    <div class="col-lg-6 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-1">Top productos — <span id="labelMesActual"><?= $mesNombreActual ?></span></h5>
                <p class="text-muted mb-3" style="font-size:.78rem">Por piezas vendidas (notas pagadas/anticipo)</p>
                <div style="height:260px; position:relative">
                    <div id="chartTopMesActual" style="width:100%;height:100%"></div>
                    <div id="loadTopMesActual" class="text-center text-muted pt-5">
                        <span class="spinner-border spinner-border-sm"></span> Cargando…
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     FILA 4 — Histórico anual + Forma de pago
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- Ingresos históricos por año -->
    <div class="col-lg-8 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-1">Ingresos históricos por año</h5>
                <p class="text-muted mb-3" style="font-size:.78rem">
                    <i class="simple-icon-info mr-1"></i>
                    Solo se contabilizan notas con estatus <strong>Pagada</strong> o <strong>Anticipo</strong>.
                    Las notas abiertas, en proceso o canceladas <u>no</u> se incluyen en los ingresos.
                </p>
                <div class="chart-container" style="height:240px">
                    <div id="chartAnual" style="width:100%;height:100%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tipo de pago (donut) -->
    <div class="col-lg-4 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Forma de pago <span id="anioTipoPago"><?= $anio ?></span></h5>
                <div class="chart-container" style="height:200px">
                    <div id="chartTipoPago" style="width:100%;height:100%"></div>
                </div>
                <div class="mt-2 text-center text-muted" id="legendTipoPago" style="font-size:.78rem">
                    <span class="spinner-border spinner-border-sm" role="status"></span> Cargando…
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     FILA 5 — Ingresos mensuales + Ingresos por vendedor
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- Ventas mensuales (mitad izquierda) -->
    <div class="col-lg-6 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-1">Ingresos mensuales <span id="anioMensual"><?= $anio ?></span></h5>
                <p class="text-muted mb-3" style="font-size:.78rem">
                    <i class="simple-icon-info mr-1"></i>
                    Solo notas con estatus <strong>Pagada</strong> o <strong>Anticipo</strong>.
                </p>
                <div class="chart-container" style="height:280px">
                    <div id="chartMensual" style="width:100%;height:100%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ingresos por vendedor (mitad derecha) -->
    <div class="col-lg-6 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Ingresos por vendedor <span id="anioVendedor"><?= $anio ?></span></h5>
                <div class="chart-container" style="height:280px; position:relative">
                    <div id="chartVendedor" style="width:100%;height:100%"></div>
                    <div id="loadingVend" class="text-center text-muted pt-5">
                        <span class="spinner-border spinner-border-sm" role="status"></span> Cargando…
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     FILA 6 — Top 10 productos (ancho completo)
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- Top 10 productos (ancho completo) -->
    <div class="col-12 mb-4">
        <div class="card chart-card">
            <div class="card-body">
                <h5 class="card-title mb-3">Top 10 productos — mayor cantidad de piezas <span id="anioTopProd"><?= $anio ?></span></h5>
                <div class="chart-container" style="height:360px; position:relative">
                    <div id="chartTopProductos" style="width:100%;height:100%"></div>
                    <div id="loadingTop" class="text-center text-muted pt-5">
                        <span class="spinner-border spinner-border-sm" role="status"></span> Cargando…
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     FILA 4 — Últimas notas del día
═══════════════════════════════════════════════════ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card chart-card">
            <div class="card-body">
                <h5 class="card-title mb-3">Notas del día — <?= fecha_es('short') ?></h5>
                <?php if (!empty($recientes)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Folio</th>
                                <th>Cliente</th>
                                <th>Hora</th>
                                <th class="text-right">Total</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recientes as $n):
                                $st = (int)($n['idstatus'] ?? 0);
                                $hora = isset($n['fecha_inicial']) ? date('H:i', strtotime($n['fecha_inicial'])) : '—';
                            ?>
                            <tr class="nota-reciente">
                                <td><strong>#<?= (int)$n['folio'] ?></strong></td>
                                <td><?= esc($n['nombre']) ?></td>
                                <td><?= $hora ?></td>
                                <td class="text-right font-weight-bold">$<?= number_format($n['total'] ?? 0, 2) ?></td>
                                <td>
                                    <span class="badge badge-<?= $bmap[$st] ?? 'secondary' ?>">
                                        <?= $lmap[$st] ?? '—' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold bg-light">
                                <td colspan="3" class="text-right">Ingresos del día:</td>
                                <td class="text-right text-success">$<?= number_format($ingresosHoy, 2) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="iconsminds-receipt-4 icon-dual icon-lg d-block mb-2" style="font-size:3rem"></i>
                    <p>Sin notas registradas hoy.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     FILA 5 — Productos con stock bajo (< 10 piezas)
═══════════════════════════════════════════════════ -->
<?php if (!empty($stockProductos)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card chart-card">
            <div class="card-body">
                <h5 class="card-title mb-1">
                    <i class="simple-icon-exclamation text-danger mr-1"></i>
                    Productos con poco stock
                    <span class="badge badge-danger ml-2" style="font-size:.8rem"><?= count($stockProductos) ?> producto(s) con menos de 10 piezas</span>
                </h5>
                <p class="text-muted mb-3" style="font-size:.8rem">Listado de productos con menos de 10 piezas en inventario, ordenados de menor a mayor.</p>
                <div class="table-responsive">
                    <table id="tablaStockBajo" class="table table-sm table-hover mb-0 w-100">
                        <thead class="thead-light">
                            <tr>
                                <th>SKU</th>
                                <th>Descripción</th>
                                <th>Color</th>
                                <th>Talla</th>
                                <th class="text-center">Piezas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockProductos as $p):
                                $pzs = (int)$p['piezas'];
                                $colorClass = $pzs <= 0 ? 'danger' : ($pzs <= 3 ? 'warning' : 'info');
                            ?>
                            <tr>
                                <td><code><?= esc($p['sku']) ?></code></td>
                                <td><?= esc($p['nombre'] ?? '—') ?></td>
                                <td><?= esc($p['Color'] ?? '—') ?></td>
                                <td><?= esc($p['Talla'] ?? '—') ?></td>
                                <td class="text-center">
                                    <span class="badge badge-<?= $colorClass ?> stock-badge"><?= $pzs ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js" crossorigin="anonymous"></script>
<script>
(function () {

    /* ── Paleta ── */
    var PAL = ['#145388','#28a745','#fd7e14','#6f42c1','#17a2b8',
               '#e83e8c','#20c997','#ffc107','#dc3545','#6c757d'];

    /* ── Defaults de período (cargados del servidor) ── */
    var defaultMes  = <?= (int)date('n') ?>;
    var defaultAnio = <?= $anio ?>;

    function peso(v) {
        return '$' + parseFloat(v||0).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    }

    /* ── DataTables stock (solo una vez) ── */
    var tblStock = document.getElementById('tablaStockBajo');
    if (tblStock && typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
        $(tblStock).DataTable({
            pageLength : 20,
            lengthMenu : [10, 20, 50],
            order      : [[4, 'asc']],
            language   : {
                search      : 'Buscar:',
                lengthMenu  : 'Mostrar _MENU_ registros',
                info        : '_START_-_END_ de _TOTAL_ productos',
                infoEmpty   : 'Sin resultados',
                zeroRecords : 'No encontrado',
                paginate    : { previous:'‹', next:'›' }
            }
        });
    }

    /* ══════════════════════════════════════════════════════════
       GESTIÓN DE INSTANCIAS ECHARTS
       dispose() automático antes de reinicializar en misma div
    ══════════════════════════════════════════════════════════ */
    var ecMap = {};

    function initChart(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        if (ecMap[id]) {
            try { ecMap[id].dispose(); } catch(e){}
        }
        ecMap[id] = echarts.init(el);
        return ecMap[id];
    }

    /* ── Esperar ECharts del CDN ── */
    function esperarEcharts(intentos) {
        if (typeof echarts === 'undefined') {
            if (intentos > 40) return;
            setTimeout(function(){ esperarEcharts(intentos + 1); }, 100);
            return;
        }
        bindSelector();
        fetchDashboard(defaultMes, defaultAnio);
    }
    esperarEcharts(0);

    /* ══════════════════════════════════════════════════════════
       SELECTOR DE PERÍODO
    ══════════════════════════════════════════════════════════ */
    function bindSelector() {
        var selMes  = document.getElementById('selMes');
        var selAnio = document.getElementById('selAnio');
        var btnHoy  = document.getElementById('btnHoy');

        function onChange() {
            var m = parseInt(selMes  ? selMes.value  : defaultMes);
            var a = parseInt(selAnio ? selAnio.value : defaultAnio);
            fetchDashboard(m, a);
        }

        if (selMes)  selMes.addEventListener('change', onChange);
        if (selAnio) selAnio.addEventListener('change', onChange);
        if (btnHoy)  btnHoy.addEventListener('click', function() {
            if (selMes)  selMes.value  = defaultMes;
            if (selAnio) selAnio.value = defaultAnio;
            fetchDashboard(defaultMes, defaultAnio);
        });
    }

    /* ── helper: actualizar texto de un elemento por id ── */
    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    /* ── helpers de spinners ── */
    function showSpinner(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'block';
    }
    function hideSpinner(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    /* ══════════════════════════════════════════════════════════
       FETCH PRINCIPAL — llama al AJAX con mes y año
    ══════════════════════════════════════════════════════════ */
    function fetchDashboard(mes, anio) {
        /* Mostrar spinner global */
        showSpinner('dashSpinner');
        showSpinner('loadingTop');
        showSpinner('loadingVend');
        showSpinner('loadTopMesActual');
        showSpinner('loadTopMesAnterior');

        var url = window['BASE' + '_URL'] + 'admin/dashboard/datos?mes=' + mes + '&anio=' + anio;
        fetch(url, { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                hideSpinner('dashSpinner');

                /* ── Actualizar etiquetas dinámicas ── */
                setText('badgeMesActual',    d.mesActual);
                setText('badgeMesAnterior',  d.mesAnterior);
                setText('labelMesActual',    d.mesActual);
                setText('labelMesAnterior',  d.mesAnterior);
                setText('anioMensual',       d.anio);
                setText('anioTipoPago',      d.anio);
                setText('anioTopProd',       d.anio);
                setText('anioVendedor',      d.anio);
                setText('periodoActivo', d.mesActual + '  vs  ' + d.mesAnterior);

                /* ── Renderizar todas las gráficas ── */
                renderVendedoresHoy( d.vendedoresHoy  || [], d.fechaHoy || '');
                renderTipoPagoHoy(   d.tipoPagoHoy    || [], d.fechaHoy || '');
                renderCompNotas(    d.comparativa,    d.mesActual, d.mesAnterior);
                renderCompIngresos( d.comparativa,    d.mesActual, d.mesAnterior);
                renderTopMes('chartTopMesAnterior', 'loadTopMesAnterior', d.topMesAnterior || []);
                renderTopMes('chartTopMesActual',   'loadTopMesActual',   d.topMesActual   || []);
                renderAnual(        d.aniosLabels, d.aniosTotales, d.aniosNotas, d.aniosPagadas, d.anio);
                renderMensual(      d.ventasMensuales, d.notasMensuales, d.mesesLabels, d.anio);
                renderTipoPago(     d.tipoPago      || []);
                renderTopProductos( d.topProductos  || [], d.anio);
                renderVendedor(     d.ventasVendedor || [], d.anio);
            })
            .catch(function(e){
                hideSpinner('dashSpinner');
                console.error('dashboard-ajax:', e);
            });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA: Notas por estatus — comparativa mensual
    ══════════════════════════════════════════════════════════ */
    function renderCompNotas(comp, mesActual, mesAnterior) {
        var ec = initChart('chartCompNotas');
        if (!ec) return;

        var labels   = comp.map(function(r){ return r.label; });
        var actN     = comp.map(function(r){ return r.actual_n; });
        var antN     = comp.map(function(r){ return r.anterior_n; });

        ec.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: function(p) {
                    return '<b>' + p[0].axisValue + '</b><br>'
                         + p[0].marker + mesAnterior + ': <b>' + p[0].value + '</b> notas<br>'
                         + p[1].marker + mesActual   + ': <b>' + p[1].value + '</b> notas';
                }
            },
            legend: { data: [mesAnterior, mesActual], bottom: 0, textStyle: { fontSize: 10 } },
            grid:   { top: 10, left: 55, right: 10, bottom: 35 },
            xAxis:  { type: 'category', data: labels, axisLabel: { fontSize: 10 } },
            yAxis:  { type: 'value', axisLabel: { fontSize: 9 }, splitLine: { lineStyle: { color: '#eee' } } },
            series: [
                {
                    name: mesAnterior, type: 'bar', data: antN, barMaxWidth: 28,
                    itemStyle: { color: '#8aafc8' },
                    label: { show: true, position: 'top', fontSize: 9,
                             formatter: function(p){ return p.value > 0 ? p.value : ''; } }
                },
                {
                    name: mesActual, type: 'bar', data: actN, barMaxWidth: 28,
                    itemStyle: { color: '#145388' },
                    label: { show: true, position: 'top', fontSize: 9,
                             formatter: function(p){ return p.value > 0 ? p.value : ''; } }
                }
            ]
        });
        window.addEventListener('resize', function(){ if(ecMap['chartCompNotas']) ecMap['chartCompNotas'].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA: Ingresos — comparativa mensual
    ══════════════════════════════════════════════════════════ */
    function renderCompIngresos(comp, mesActual, mesAnterior) {
        var ec = initChart('chartCompIngresos');
        if (!ec) return;

        /* idx 3 = Anticipo (status 4), idx 4 = Pagada (status 5) */
        var ingAnt  = (comp[3] ? comp[3].anterior_t : 0) + (comp[4] ? comp[4].anterior_t : 0);
        var ingAct  = (comp[3] ? comp[3].actual_t   : 0) + (comp[4] ? comp[4].actual_t   : 0);
        var pagAnt  = (comp[3] ? comp[3].anterior_n : 0) + (comp[4] ? comp[4].anterior_n : 0);
        var pagAct  = (comp[3] ? comp[3].actual_n   : 0) + (comp[4] ? comp[4].actual_n   : 0);

        var delta      = ingAnt > 0 ? (((ingAct - ingAnt) / ingAnt) * 100).toFixed(1) : 0;
        var deltaColor = parseFloat(delta) >= 0 ? '#28a745' : '#dc3545';
        var deltaSign  = parseFloat(delta) >= 0 ? '▲' : '▼';

        ec.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: function(p) {
                    var esMesAnt = p[0].axisValue === mesAnterior;
                    return '<b>' + p[0].axisValue + '</b><br>'
                         + '💰 Ingresos: <b>' + peso(p[0].value) + '</b><br>'
                         + '✅ Notas cobradas: <b>' + (esMesAnt ? pagAnt : pagAct) + '</b>';
                }
            },
            legend: { data: [mesAnterior, mesActual], bottom: 0, textStyle: { fontSize: 10 } },
            grid:   { top: 40, left: 80, right: 15, bottom: 35 },
            graphic: [{
                type: 'text', left: 'center', top: 6,
                style: {
                    text: mesActual + ' ' + deltaSign + ' ' + Math.abs(delta) + '% vs ' + mesAnterior,
                    fill: deltaColor, fontSize: 12, fontWeight: 'bold'
                }
            }],
            xAxis: { type: 'category', data: [mesAnterior, mesActual], axisLabel: { fontSize: 11 } },
            yAxis: { type: 'value', axisLabel: { formatter: function(v){ return peso(v); }, fontSize: 9 },
                     splitLine: { lineStyle: { color: '#eee' } } },
            series: [
                {
                    name: mesAnterior, type: 'bar', data: [ingAnt, null], barMaxWidth: 70,
                    itemStyle: { color: '#8aafc8' },
                    label: { show: true, position: 'top', fontSize: 10, fontWeight: 'bold',
                             formatter: function(p){ return p.value != null ? peso(p.value) : ''; } }
                },
                {
                    name: mesActual, type: 'bar', data: [null, ingAct], barMaxWidth: 70,
                    itemStyle: { color: '#145388' },
                    label: { show: true, position: 'top', fontSize: 10, fontWeight: 'bold',
                             formatter: function(p){ return p.value != null ? peso(p.value) : ''; } }
                }
            ]
        });
        window.addEventListener('resize', function(){ if(ecMap['chartCompIngresos']) ecMap['chartCompIngresos'].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA: Top productos de un mes (anterior o actual)
    ══════════════════════════════════════════════════════════ */
    function renderTopMes(divId, loadId, filas) {
        hideSpinner(loadId);
        var ec = initChart(divId);
        if (!ec) return;

        if (!filas.length) {
            ec.clear();
            ec.setOption({ graphic: [{ type:'text', left:'center', top:'middle',
                style:{ text:'Sin ventas registradas', fill:'#aaa', fontSize:13 } }] });
            return;
        }

        var skus     = filas.map(function(r){ return r.sku || r.nombre || ''; });
        var nombres  = filas.map(function(r){ return r.nombre || r.sku || ''; });
        var piezas   = filas.map(function(r){ return parseInt(r.piezas); });
        var importes = filas.map(function(r){ return parseFloat(r.importe); });

        ec.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: function(p) {
                    var i = p[0].dataIndex;
                    return '<b>' + skus[filas.length - 1 - i] + '</b><br>'
                         + '<span style="font-size:11px;color:#666">' + nombres[filas.length - 1 - i] + '</span><br>'
                         + p[0].marker + ' Piezas: <b>' + p[0].value + '</b><br>'
                         + '💰 Importe: <b>' + peso(importes[filas.length - 1 - i]) + '</b>';
                }
            },
            grid:   { top: 10, left: 120, right: 20, bottom: 25 },
            xAxis:  { type: 'value', axisLabel: { fontSize: 9 }, splitLine: { lineStyle: { color: '#eee' } } },
            yAxis:  { type: 'category', data: skus.slice().reverse(),
                      axisLabel: { fontSize: 9, width: 112, overflow: 'truncate' } },
            series: [{
                type: 'bar', data: piezas.slice().reverse(), barMaxWidth: 20,
                itemStyle: { color: function(p){ return PAL[p.dataIndex % PAL.length]; } },
                label: { show: true, position: 'right', fontSize: 9,
                         formatter: function(p){ return p.value; } }
            }]
        });
        window.addEventListener('resize', function(){ if(ecMap[divId]) ecMap[divId].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA: Histórico anual (resalta el año seleccionado)
    ══════════════════════════════════════════════════════════ */
    function renderAnual(aniosLabels, aniosTotales, aniosNotas, aniosPagadas, anioSel) {
        var ec = initChart('chartAnual');
        if (!ec) return;

        var labStr = aniosLabels.map(String);
        var selStr = String(anioSel);

        ec.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: function(p) {
                    var idx = p[0].dataIndex;
                    return '<b>' + p[0].name + '</b><br>'
                         + '💰 Ingresos: <b>'      + peso(aniosTotales[idx]) + '</b><br>'
                         + '📋 Notas totales: <b>' + aniosNotas[idx]         + '</b><br>'
                         + '✅ Notas pagadas: <b>' + aniosPagadas[idx]        + '</b>';
                }
            },
            legend: { data: ['Ingresos (pagadas)','Total notas'], bottom: 0, textStyle: { fontSize: 11 } },
            grid:   { top: 35, left: 70, right: 60, bottom: 35 },
            xAxis:  { type: 'category', data: labStr, axisLabel: { fontSize: 11 } },
            yAxis: [
                { type: 'value', name: '$',
                  axisLabel: { formatter: function(v){ return peso(v); }, fontSize: 9 },
                  splitLine: { lineStyle: { color: '#eee' } } },
                { type: 'value', name: 'Notas',
                  axisLabel: { fontSize: 9 }, splitLine: { show: false } }
            ],
            series: [
                {
                    name: 'Ingresos (pagadas)', type: 'bar', yAxisIndex: 0,
                    data: aniosTotales.map(function(v, i) {
                        return {
                            value: v,
                            itemStyle: { color: labStr[i] === selStr ? '#145388' : '#8aafc8' }
                        };
                    }),
                    barMaxWidth: 60,
                    label: {
                        show: true, position: 'top', fontSize: 10, fontWeight: 'bold',
                        formatter: function(p){ return p.value > 0 ? peso(p.value) : ''; }
                    }
                },
                {
                    name: 'Total notas', type: 'line', yAxisIndex: 1,
                    data: aniosNotas,
                    lineStyle: { color: PAL[2] }, itemStyle: { color: PAL[2] },
                    symbol: 'circle', symbolSize: 7, smooth: false,
                    label: { show: true, fontSize: 10, color: PAL[2],
                             formatter: function(p){ return p.value; } }
                }
            ]
        });
        window.addEventListener('resize', function(){ if(ecMap['chartAnual']) ecMap['chartAnual'].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA: Ingresos mensuales del año
    ══════════════════════════════════════════════════════════ */
    function renderMensual(ventas, notas, meses, anio) {
        var ec = initChart('chartMensual');
        if (!ec) return;

        ec.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: function(p) {
                    var s = p[0].name + '<br>';
                    p.forEach(function(item){
                        s += item.marker + item.seriesName + ': <b>'
                          + (item.seriesName === 'Notas' ? item.value : peso(item.value))
                          + '</b><br>';
                    });
                    return s;
                }
            },
            legend:  { data: ['Ingresos','Notas'], bottom: 0, textStyle: { fontSize: 11 } },
            grid:    { top: 20, left: 60, right: 50, bottom: 40 },
            xAxis:   { type: 'category', data: meses, axisLabel: { fontSize: 10 } },
            yAxis: [
                { type: 'value', name: '$', axisLabel: { formatter: function(v){ return peso(v); }, fontSize: 9 },
                  splitLine: { lineStyle: { color: '#eee' } } },
                { type: 'value', name: 'Notas', axisLabel: { fontSize: 9 }, splitLine: { show: false } }
            ],
            series: [
                { name: 'Ingresos', type: 'bar',  yAxisIndex: 0, data: ventas,
                  itemStyle: { color: PAL[0] }, barMaxWidth: 40 },
                { name: 'Notas',    type: 'line', yAxisIndex: 1, data: notas,
                  lineStyle: { color: PAL[2] }, itemStyle: { color: PAL[2] },
                  symbol: 'circle', symbolSize: 5, smooth: true }
            ]
        });
        window.addEventListener('resize', function(){ if(ecMap['chartMensual']) ecMap['chartMensual'].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA: Forma de pago (donut)
    ══════════════════════════════════════════════════════════ */
    function renderTipoPago(filas) {
        var legEl = document.getElementById('legendTipoPago');
        var ec = initChart('chartTipoPago');
        if (!ec) return;

        if (!filas.length) {
            ec.clear();
            if (legEl) legEl.innerHTML = '<em class="text-muted">Sin datos</em>';
            return;
        }

        var total = filas.reduce(function(s,r){ return s + parseFloat(r.total); }, 0);
        var datos = filas.map(function(r,i){
            return { value: parseFloat(r.total), name: r.tipo,
                     itemStyle: { color: PAL[i % PAL.length] } };
        });

        if (legEl) {
            legEl.innerHTML = filas.map(function(r,i){
                var pct = total > 0 ? (parseFloat(r.total)/total*100).toFixed(1) : '0.0';
                return '<span style="display:inline-flex;align-items:center;margin:2px 8px 2px 0">'
                     + '<span style="width:10px;height:10px;border-radius:50%;background:'
                     + PAL[i % PAL.length] + ';display:inline-block;margin-right:5px"></span>'
                     + r.tipo + ' <strong style="margin-left:3px">' + pct + '%</strong></span>';
            }).join('');
        }

        ec.setOption({
            tooltip: { trigger: 'item', formatter: function(p){
                return p.name + '<br><b>' + peso(p.value) + '</b> (' + p.percent + '%)';
            }},
            legend:  { show: false },
            series:  [{ type: 'pie', radius: ['50%','75%'], data: datos,
                label: { show: false },
                emphasis: { label: { show: true, fontSize: 12, fontWeight: 'bold' } }
            }]
        });
        window.addEventListener('resize', function(){ if(ecMap['chartTipoPago']) ecMap['chartTipoPago'].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA: Top 10 productos del año (barra horizontal)
    ══════════════════════════════════════════════════════════ */
    function renderTopProductos(filas, anio) {
        hideSpinner('loadingTop');
        var ec = initChart('chartTopProductos');
        if (!ec) return;

        if (!filas.length) {
            ec.clear();
            ec.setOption({ graphic: [{ type:'text', left:'center', top:'middle',
                style:{ text:'Sin ventas registradas en ' + anio, fill:'#aaa', fontSize:13 } }] });
            return;
        }

        var skus     = filas.map(function(r){ return r.sku || r.nombre || ''; });
        var nombres  = filas.map(function(r){ return r.nombre || r.sku || ''; });
        var piezas   = filas.map(function(r){ return parseInt(r.piezas); });
        var importes = filas.map(function(r){ return parseFloat(r.importe); });

        ec.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: function(p){
                    var i = p[0].dataIndex;
                    return '<b>' + skus[filas.length - 1 - i] + '</b><br>'
                         + '<span style="font-size:11px;color:#666">' + nombres[filas.length - 1 - i] + '</span><br>'
                         + p[0].marker + ' Piezas: <b>' + p[0].value + '</b><br>'
                         + '💰 Importe: <b>' + peso(importes[filas.length - 1 - i]) + '</b>';
                }
            },
            grid:   { top: 10, left: 120, right: 20, bottom: 30 },
            xAxis:  { type: 'value', axisLabel: { fontSize: 9 }, splitLine: { lineStyle: { color: '#eee' } } },
            yAxis:  { type: 'category', data: skus.slice().reverse(),
                      axisLabel: { fontSize: 9, width: 112, overflow: 'truncate' } },
            series: [{ type: 'bar', data: piezas.slice().reverse(), barMaxWidth: 26,
                itemStyle: { color: function(p){ return PAL[p.dataIndex % PAL.length]; } }
            }]
        });
        window.addEventListener('resize', function(){ if(ecMap['chartTopProductos']) ecMap['chartTopProductos'].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA: Ingresos por vendedor
    ══════════════════════════════════════════════════════════ */
    function renderVendedor(filas, anio) {
        hideSpinner('loadingVend');
        var ec = initChart('chartVendedor');
        if (!ec) return;

        if (!filas.length) {
            ec.clear();
            ec.setOption({ graphic: [{ type:'text', left:'center', top:'middle',
                style:{ text:'Sin ventas en ' + anio, fill:'#aaa', fontSize:13 } }] });
            return;
        }

        var nombres    = filas.map(function(r){ return r.vendedor; });
        var totales    = filas.map(function(r){ return parseFloat(r.total); });
        var canceladas = filas.map(function(r){ return parseInt(r.canceladas, 10) || 0; });

        ec.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: function(params) {
                    var out = '<b>' + params[0].name + '</b><br>';
                    params.forEach(function(p) {
                        out += p.marker + ' ' + p.seriesName + ': <b>';
                        out += p.seriesIndex === 0 ? peso(p.value) : p.value + ' nota(s)';
                        out += '</b><br>';
                    });
                    return out;
                }
            },
            legend: { data: ['Ingresos', 'Canceladas'], top: 0, right: 0, itemWidth: 10, textStyle: { fontSize: 9 } },
            grid:   { top: 28, left: 70, right: 55, bottom: 40 },
            xAxis:  { type: 'category', data: nombres,
                      axisLabel: { fontSize: 9, rotate: nombres.length > 4 ? 15 : 0 } },
            yAxis: [
                { type: 'value', name: '',
                  axisLabel: { formatter: function(v){ return peso(v); }, fontSize: 8 },
                  splitLine: { lineStyle: { color: '#eee' } } },
                { type: 'value', name: 'Canceladas', nameTextStyle: { fontSize: 8 }, minInterval: 1,
                  axisLabel: { fontSize: 8 },
                  splitLine: { show: false } }
            ],
            series: [
                { name: 'Ingresos', type: 'bar', yAxisIndex: 0, data: totales, barMaxWidth: 40,
                  itemStyle: { color: function(p){ return PAL[p.dataIndex % PAL.length]; } } },
                { name: 'Canceladas', type: 'bar', yAxisIndex: 1, data: canceladas, barMaxWidth: 20,
                  itemStyle: { color: 'rgba(220,53,69,0.75)' } }
            ]
        });
        window.addEventListener('resize', function(){ if(ecMap['chartVendedor']) ecMap['chartVendedor'].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA HOY: Ingresos por vendedor del día
    ══════════════════════════════════════════════════════════ */
    function renderVendedoresHoy(filas, fecha) {
        hideSpinner('loadVendedoresHoy');
        var ec = initChart('chartVendedoresHoy');
        if (!ec) return;

        if (!filas.length) {
            ec.clear();
            ec.setOption({ graphic: [{ type:'text', left:'center', top:'middle',
                style:{ text:'Sin actividad hoy', fill:'#aaa', fontSize:13 } }] });
            return;
        }

        var nombres    = filas.map(function(r){ return r.vendedor; });
        var totales    = filas.map(function(r){ return parseFloat(r.total); });
        var canceladas = filas.map(function(r){ return parseInt(r.canceladas, 10) || 0; });

        ec.setOption({
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                formatter: function(params) {
                    var out = '<b>' + params[0].name + '</b><br>';
                    params.forEach(function(p) {
                        out += p.marker + ' ' + p.seriesName + ': <b>';
                        out += p.seriesIndex === 0 ? peso(p.value) : p.value + ' nota(s)';
                        out += '</b><br>';
                    });
                    return out;
                }
            },
            legend: { data: ['Ingresos', 'Canceladas'], top: 0, right: 0, itemWidth: 10, textStyle: { fontSize: 9 } },
            grid:   { top: 28, left: 70, right: 55, bottom: 40 },
            xAxis:  { type: 'category', data: nombres,
                      axisLabel: { fontSize: 10, rotate: nombres.length > 4 ? 15 : 0 } },
            yAxis: [
                { type: 'value',
                  axisLabel: { formatter: function(v){ return peso(v); }, fontSize: 8 },
                  splitLine: { lineStyle: { color: '#eee' } } },
                { type: 'value', name: 'Canceladas', nameTextStyle: { fontSize: 8 }, minInterval: 1,
                  axisLabel: { fontSize: 8 }, splitLine: { show: false } }
            ],
            series: [
                { name: 'Ingresos', type: 'bar', yAxisIndex: 0, data: totales, barMaxWidth: 45,
                  itemStyle: { color: function(p){ return PAL[p.dataIndex % PAL.length]; } },
                  label: { show: true, position: 'top', fontSize: 9,
                           formatter: function(p){ return p.value > 0 ? peso(p.value) : ''; } } },
                { name: 'Canceladas', type: 'bar', yAxisIndex: 1, data: canceladas, barMaxWidth: 20,
                  itemStyle: { color: 'rgba(220,53,69,0.75)' },
                  label: { show: true, position: 'top', fontSize: 9,
                           formatter: function(p){ return p.value > 0 ? p.value : ''; } } }
            ]
        });
        window.addEventListener('resize', function(){ if(ecMap['chartVendedoresHoy']) ecMap['chartVendedoresHoy'].resize(); });
    }

    /* ══════════════════════════════════════════════════════════
       GRÁFICA HOY: Forma de pago del día (donut grande)
    ══════════════════════════════════════════════════════════ */
    function renderTipoPagoHoy(filas, fecha) {
        var legendEl = document.getElementById('legendTipoPagoHoy');
        var ec = initChart('chartTipoPagoHoy');
        if (!ec) return;

        if (!filas.length) {
            ec.clear();
            if (legendEl) legendEl.textContent = 'Sin pagos registrados hoy.';
            ec.setOption({ graphic: [{ type:'text', left:'center', top:'middle',
                style:{ text:'Sin pagos hoy', fill:'#aaa', fontSize:13 } }] });
            return;
        }

        var COLORS = ['#145388','#28a745','#fd7e14','#6f42c1','#17a2b8','#e83e8c','#20c997','#ffc107'];
        var datos  = filas.map(function(r,i){ return { value: parseFloat(r.total), name: r.tipo, itemStyle:{ color: COLORS[i % COLORS.length] } }; });
        var totalHoy = filas.reduce(function(s,r){ return s + parseFloat(r.total); }, 0);

        ec.setOption({
            tooltip: {
                trigger: 'item',
                formatter: function(p){
                    return '<b>' + p.name + '</b><br>' + peso(p.value) + ' (' + p.percent + '%)';
                }
            },
            legend: { show: false },
            series: [{
                type: 'pie', radius: ['52%','82%'],
                center: ['50%','52%'],
                data: datos,
                label: { show: true, fontSize: 10,
                         formatter: function(p){ return p.name + '\n' + p.percent + '%'; } },
                emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0,0,0,0.3)' } }
            }],
            graphic: [{
                type: 'text', left: 'center', top: '42%',
                style: { text: peso(totalHoy), fill: '#333', fontSize: 14, fontWeight: 'bold', textAlign: 'center' }
            }]
        });

        // Leyenda manual
        if (legendEl) {
            legendEl.innerHTML = filas.map(function(r, i){
                var pct = totalHoy > 0 ? (parseFloat(r.total) / totalHoy * 100).toFixed(1) : 0;
                return '<span style="display:inline-flex;align-items:center;margin:2px 8px">'
                     + '<span style="width:10px;height:10px;border-radius:50%;background:' + COLORS[i % COLORS.length] + ';display:inline-block;margin-right:4px"></span>'
                     + r.tipo + ' <b style="margin-left:4px">' + pct + '%</b></span>';
            }).join('');
        }

        window.addEventListener('resize', function(){ if(ecMap['chartTipoPagoHoy']) ecMap['chartTipoPagoHoy'].resize(); });
    }

})();
</script>
<?= $this->endSection() ?>
