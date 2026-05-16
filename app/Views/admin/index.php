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
            <small class="text-muted"><?= date('l, d \d\e F Y') ?> &nbsp;·&nbsp; Año <?= $anio ?></small>
        </div>
    </div>
    <div class="col-12"><div class="separator mt-3 mb-4"></div></div>
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
                <div class="kpi-sub"><?= date('F') ?></div>
            </div>
        </div>
    </div>

    <!-- Ingresos año -->
    <div class="col-6 col-sm-4 col-lg mb-3">
        <div class="card kpi-card h-100" style="border-left:4px solid #6f42c1">
            <div class="card-body py-3 px-3">
                <i class="iconsminds-bar-chart kpi-icon d-block mb-1" style="color:#6f42c1"></i>
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
     FILA 2 — Histórico anual (todos los años)
═══════════════════════════════════════════════════ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card chart-card">
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
</div>

<!-- ══════════════════════════════════════════════════
     FILA 3 — Gráfica ventas mensuales + tipo de pago
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- Ventas mensuales (barras) -->
    <div class="col-lg-8 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-1">Ingresos mensuales <?= $anio ?></h5>
                <p class="text-muted mb-3" style="font-size:.78rem">
                    <i class="simple-icon-info mr-1"></i>
                    Solo notas con estatus <strong>Pagada</strong> o <strong>Anticipo</strong>.
                </p>
                <div class="chart-container" style="height:240px">
                    <div id="chartMensual" style="width:100%;height:100%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tipo de pago (donut) -->
    <div class="col-lg-4 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Forma de pago <?= $anio ?></h5>
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
     FILA 3 — Top productos + Ventas por vendedor
═══════════════════════════════════════════════════ -->
<div class="row mb-4">

    <!-- Top 10 productos (barras horizontales) -->
    <div class="col-lg-7 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Top 10 productos — mayor cantidad de piezas <?= $anio ?></h5>
                <div class="chart-container" style="height:380px; position:relative">
                    <div id="chartTopProductos" style="width:100%;height:100%"></div>
                    <div id="loadingTop" class="text-center text-muted pt-5">
                        <span class="spinner-border spinner-border-sm" role="status"></span> Cargando…
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ventas por vendedor (barras) -->
    <div class="col-lg-5 mb-4">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Ingresos por vendedor <?= $anio ?></h5>
                <div class="chart-container" style="height:380px; position:relative">
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
     FILA 4 — Últimas notas del día
═══════════════════════════════════════════════════ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card chart-card">
            <div class="card-body">
                <h5 class="card-title mb-3">Notas del día — <?= date('d/m/Y') ?></h5>
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

    /* ── paleta de colores ── */
    var PAL = ['#145388','#28a745','#fd7e14','#6f42c1','#17a2b8',
               '#e83e8c','#20c997','#ffc107','#dc3545','#6c757d'];

    function peso(v) {
        return '$' + parseFloat(v||0).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    }

    /* ── DataTables en la tabla de stock ── */
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

    /* ── esperar a que ECharts esté listo ── */
    function esperarEcharts(intentos) {
        if (typeof echarts === 'undefined') {
            if (intentos > 30) return; // máx 3s
            setTimeout(function(){ esperarEcharts(intentos + 1); }, 100);
            return;
        }
        iniciarGraficas();
    }
    esperarEcharts(0);

    function iniciarGraficas() {

        /* ── 0. Histórico anual (datos embebidos en PHP) ── */
        var elAnual = document.getElementById('chartAnual');
        if (elAnual) {
            var aniosLabels  = <?= json_encode(array_map('strval', $aniosLabels)) ?>;
            var aniosTotales = <?= json_encode($aniosTotales) ?>;
            var aniosNotas   = <?= json_encode($aniosNotas) ?>;
            var aniosPagadas = <?= json_encode($aniosPagadas) ?>;
            var anioActual   = String(<?= $anio ?>);

            var ecAnual = echarts.init(elAnual);
            ecAnual.setOption({
                tooltip: {
                    trigger: 'axis', axisPointer: { type: 'shadow' },
                    formatter: function(p) {
                        var idx = p[0].dataIndex;
                        return '<b>' + p[0].name + '</b><br>'
                             + '💰 Ingresos: <b>' + peso(aniosTotales[idx]) + '</b><br>'
                             + '📋 Notas totales: <b>' + aniosNotas[idx] + '</b><br>'
                             + '✅ Notas pagadas: <b>' + aniosPagadas[idx] + '</b>';
                    }
                },
                legend: { data: ['Ingresos (pagadas)','Total notas'], bottom: 0, textStyle: { fontSize: 11 } },
                grid:   { top: 35, left: 70, right: 60, bottom: 35 },
                xAxis:  { type: 'category', data: aniosLabels, axisLabel: { fontSize: 11 } },
                yAxis: [
                    {
                        type: 'value', name: '$',
                        axisLabel: { formatter: function(v){ return peso(v); }, fontSize: 9 },
                        splitLine: { lineStyle: { color: '#eee' } }
                    },
                    {
                        type: 'value', name: 'Notas',
                        axisLabel: { fontSize: 9 },
                        splitLine: { show: false }
                    }
                ],
                series: [
                    {
                        name: 'Ingresos (pagadas)', type: 'bar', yAxisIndex: 0,
                        data: aniosTotales.map(function(v, i) {
                            return {
                                value: v,
                                itemStyle: { color: aniosLabels[i] === anioActual ? '#145388' : '#8aafc8' }
                            };
                        }),
                        barMaxWidth: 60,
                        label: {
                            show: true, position: 'top', fontSize: 10, fontWeight: 'bold',
                            formatter: function(p) {
                                return p.value > 0 ? peso(p.value) : '';
                            }
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
            window.addEventListener('resize', function(){ ecAnual.resize(); });
        }

        /* ── 1. Ingresos mensuales ── */
        var elMes = document.getElementById('chartMensual');
        if (elMes) {
            var meses   = <?= json_encode($mesesLabels) ?>;
            var ingresos = <?= json_encode($ventasMensuales) ?>;
            var notas    = <?= json_encode($notasMensuales) ?>;

            var ecMes = echarts.init(elMes);
            ecMes.setOption({
                tooltip : { trigger: 'axis', axisPointer: { type: 'shadow' },
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
                legend  : { data: ['Ingresos','Notas'], bottom: 0, textStyle:{ fontSize:11 } },
                grid    : { top:20, left:60, right:50, bottom:40 },
                xAxis   : { type:'category', data: meses, axisLabel:{ fontSize:10 } },
                yAxis   : [
                    { type:'value', name:'$', axisLabel:{ formatter: function(v){ return peso(v); }, fontSize:9 }, splitLine:{ lineStyle:{color:'#eee'} } },
                    { type:'value', name:'Notas', axisLabel:{ fontSize:9 }, splitLine:{ show:false } }
                ],
                series  : [
                    { name:'Ingresos', type:'bar',  yAxisIndex:0, data: ingresos,
                      itemStyle:{ color: PAL[0] }, barMaxWidth: 40 },
                    { name:'Notas',    type:'line', yAxisIndex:1, data: notas,
                      lineStyle:{ color: PAL[2] }, itemStyle:{ color: PAL[2] },
                      symbol:'circle', symbolSize:5, smooth: true }
                ]
            });
            window.addEventListener('resize', function(){ ecMes.resize(); });
        }

        /* ── 2–4: datos pesados vía AJAX ── */
        var dashUrl = window['BASE' + '_URL'] + 'admin/dashboard/datos';
        fetch(dashUrl, { credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                graficarTipoPago(d.tipoPago    || []);
                graficarTopProductos(d.topProductos  || []);
                graficarVendedor(d.ventasVendedor || []);
            })
            .catch(function(e){ console.error('dash-ajax', e); });
    }

    /* ── Forma de pago (donut) ── */
    function graficarTipoPago(filas) {
        var legEl = document.getElementById('legendTipoPago');
        var elDiv = document.getElementById('chartTipoPago');
        if (!elDiv) return;

        if (!filas.length) {
            if (legEl) legEl.innerHTML = '<em class="text-muted">Sin datos</em>';
            return;
        }

        var total = filas.reduce(function(s,r){ return s + parseFloat(r.total); }, 0);
        var datos = filas.map(function(r,i){
            return { value: parseFloat(r.total), name: r.tipo,
                     itemStyle:{ color: PAL[i % PAL.length] } };
        });

        /* leyenda manual */
        if (legEl) {
            legEl.innerHTML = filas.map(function(r,i){
                var pct = total > 0 ? (parseFloat(r.total)/total*100).toFixed(1) : '0.0';
                return '<span style="display:inline-flex;align-items:center;margin:2px 8px 2px 0">'
                     + '<span style="width:10px;height:10px;border-radius:50%;background:'
                     + PAL[i % PAL.length] + ';display:inline-block;margin-right:5px"></span>'
                     + r.tipo + ' <strong style="margin-left:3px">' + pct + '%</strong></span>';
            }).join('');
        }

        /* destruir canvas, reemplazar con div para ECharts */
        var parent = elDiv.parentNode;
        var div = document.createElement('div');
        div.id = 'chartTipoPagoEc';
        div.style.cssText = 'width:100%;height:200px';
        parent.replaceChild(div, elDiv);

        var ec = echarts.init(div);
        ec.setOption({
            tooltip : { trigger:'item', formatter: function(p){
                return p.name + '<br><b>' + peso(p.value) + '</b> (' + p.percent + '%)';
            }},
            legend  : { show: false },
            series  : [{ type:'pie', radius:['50%','75%'], data: datos,
                label : { show: false },
                emphasis: { label:{ show:true, fontSize:12, fontWeight:'bold' } }
            }]
        });
        window.addEventListener('resize', function(){ ec.resize(); });
    }

    /* ── Top 10 productos (barra horizontal) ── */
    function graficarTopProductos(filas) {
        var loadEl = document.getElementById('loadingTop');
        var elDiv  = document.getElementById('chartTopProductos');
        if (!elDiv) return;

        if (!filas.length) {
            if (loadEl) { loadEl.innerHTML='<em class="text-muted">Sin ventas registradas este año</em>'; loadEl.style.display='block'; }
            return;
        }
        if (loadEl) loadEl.style.display = 'none';

        var nombres = filas.map(function(r){ return r.nombre ? r.nombre.substring(0,28) : r.sku; });
        var piezas  = filas.map(function(r){ return parseInt(r.piezas); });
        var importes= filas.map(function(r){ return parseFloat(r.importe); });

        /* reemplazar canvas con div */
        var parent = elDiv.parentNode;
        var div = document.createElement('div');
        div.id = 'chartTopProductosEc';
        div.style.cssText = 'width:100%;height:300px';
        parent.replaceChild(div, elDiv);

        var ec = echarts.init(div);
        ec.setOption({
            tooltip : { trigger:'axis', axisPointer:{ type:'shadow' },
                formatter: function(p){
                    return p[0].name + '<br>'
                         + p[0].marker + ' Piezas: <b>' + p[0].value + '</b><br>'
                         + '💰 Importe: <b>' + peso(importes[p[0].dataIndex]) + '</b>';
                }
            },
            grid    : { top:10, left:168, right:20, bottom:30 },
            xAxis   : { type:'value', axisLabel:{ fontSize:9 }, splitLine:{ lineStyle:{color:'#eee'} } },
            yAxis   : { type:'category', data: nombres.slice().reverse(),
                        axisLabel:{ fontSize:9, width:155, overflow:'truncate' } },
            series  : [{ type:'bar', data: piezas.slice().reverse(), barMaxWidth:26,
                itemStyle:{ color: function(p){ return PAL[p.dataIndex % PAL.length]; } }
            }]
        });
        window.addEventListener('resize', function(){ ec.resize(); });
    }

    /* ── Ingresos por vendedor ── */
    function graficarVendedor(filas) {
        var loadEl = document.getElementById('loadingVend');
        var elDiv  = document.getElementById('chartVendedor');
        if (!elDiv) return;

        if (!filas.length) {
            if (loadEl) { loadEl.innerHTML='<em class="text-muted">Sin ventas registradas este año</em>'; loadEl.style.display='block'; }
            return;
        }
        if (loadEl) loadEl.style.display = 'none';

        var nombres = filas.map(function(r){ return r.vendedor; });
        var totales = filas.map(function(r){ return parseFloat(r.total); });

        /* reemplazar canvas con div */
        var parent = elDiv.parentNode;
        var div = document.createElement('div');
        div.id = 'chartVendedorEc';
        div.style.cssText = 'width:100%;height:300px';
        parent.replaceChild(div, elDiv);

        var ec = echarts.init(div);
        ec.setOption({
            tooltip : { trigger:'axis', axisPointer:{ type:'shadow' },
                formatter: function(p){ return p[0].name + '<br><b>' + peso(p[0].value) + '</b>'; }
            },
            grid    : { top:10, left:70, right:10, bottom:40 },
            xAxis   : { type:'category', data: nombres, axisLabel:{ fontSize:9, rotate: nombres.length > 4 ? 15 : 0 } },
            yAxis   : { type:'value', axisLabel:{ formatter: function(v){ return peso(v); }, fontSize:8 },
                        splitLine:{ lineStyle:{color:'#eee'} } },
            series  : [{ type:'bar', data: totales, barMaxWidth:40,
                itemStyle:{ color: function(p){ return PAL[p.dataIndex % PAL.length]; } }
            }]
        });
        window.addEventListener('resize', function(){ ec.resize(); });
    }

})();
</script>
<?= $this->endSection() ?>
