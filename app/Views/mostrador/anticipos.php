<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Anticipos</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('mostrador') ?>">Mostrador</a></li>
                <li class="breadcrumb-item active">Anticipos</li>
            </ol>
        </nav>
    </div>
</div>

<div class="separator mb-5"></div>
<div class="row">
    <div class="col-12 mb-4">
        <table id="tablaAnticipos" class="table responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Pagado</th>
                    <th class="text-right">Restante</th>
                    <th>Status</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($anticipos as $a): ?>
                <tr>
                    <td><?= (int)$a['folio'] ?></td>
                    <td><?= date('d/m/Y', strtotime($a['fecha_inicial'])) ?></td>
                    <td><?= esc($a['cliente'] ?? '') ?></td>
                    <td><?= esc($a['vendedor'] ?? '') ?></td>
                    <td class="text-right">$<?= number_format($a['total'] ?? 0, 2) ?></td>
                    <td class="text-right">$<?= number_format($a['pagado'] ?? 0, 2) ?></td>
                    <td class="text-right">$<?= number_format(max(0, ($a['total'] ?? 0) - ($a['pagado'] ?? 0)), 2) ?></td>
                    <td><span class="badge badge-warning">Anticipo</span></td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary mr-1"
                                onclick="verDetalle(<?= (int)$a['folio'] ?>)">
                            <i class="simple-icon-eye"></i> Ver
                        </button>
                        <button type="button" class="btn btn-sm btn-success"
                                onclick="abrirNuevoPago(<?= (int)$a['folio'] ?>, <?= (float)($a['total'] ?? 0) ?>, <?= (float)($a['pagado'] ?? 0) ?>)">
                            <i class="iconsminds-money-2"></i> Nuevo Pago
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($anticipos)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No hay anticipos pendientes.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Ver Detalle de Anticipo -->
<div class="modal fade" id="modalVerDetalle" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Folio <span id="detalleFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="detalleBody">
                <p class="text-center text-muted"><i>Cargando...</i></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nuevo Pago de Anticipo -->
<div class="modal fade" id="modalNuevoPago" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Pago — Folio <span id="npFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block">Total nota</small>
                        <strong id="npTotal">$0.00</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Restante por pagar</small>
                        <strong class="text-danger" id="npRestante">$0.00</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label>Método de pago <span class="text-danger">*</span></label>
                    <select id="npTipoPago" class="form-control">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($tiposPago as $tp): ?>
                        <option value="<?= (int)$tp['id'] ?>"><?= esc($tp['tipo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Monto del pago <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                        <input type="number" id="npMonto" class="form-control" min="0.01" step="0.01" placeholder="0.00">
                    </div>
                </div>

                <div id="npError" class="alert alert-danger d-none"></div>
                <div id="npSuccess" class="alert alert-success d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarPago">
                    <i class="iconsminds-money-2 mr-1"></i> Registrar Pago
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
(function () {
    var csrfToken = '<?= csrf_token() ?>';
    var csrfHash  = '<?= csrf_hash() ?>';
    var npFolioActivo = null;

    /* ── DataTable ── */
    $('#tablaAnticipos').DataTable({
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            search:       'Buscar:',
            lengthMenu:   'Mostrar _MENU_ registros',
            info:         'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty:    'Mostrando 0 a 0 de 0 registros',
            zeroRecords:  'No hay anticipos pendientes',
            paginate: { first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último' }
        }
    });

    /* ── Ver Detalle ── */
    window.verDetalle = function(folio) {
        document.getElementById('detalleFolioNum').textContent = '#' + folio;
        document.getElementById('detalleBody').innerHTML = '<p class="text-center text-muted"><i>Cargando...</i></p>';
        $('#modalVerDetalle').modal('show');

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);

        fetch('<?= base_url('mostrador/anticipos/folio/') ?>' + folio, {
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                document.getElementById('detalleBody').innerHTML = '<p class="text-danger">Error al cargar el detalle.</p>';
                return;
            }
            var html = '';

            /* Resumen */
            html += '<div class="row mb-3">'
                  + '<div class="col-4"><small class="text-muted d-block">Total nota</small><strong>$' + parseFloat(data.total||0).toFixed(2) + '</strong></div>'
                  + '<div class="col-4"><small class="text-muted d-block">Pagado</small><strong class="text-success">$' + parseFloat(data.pagado||0).toFixed(2) + '</strong></div>'
                  + '<div class="col-4"><small class="text-muted d-block">Restante</small><strong class="text-danger">$' + parseFloat(data.restante||0).toFixed(2) + '</strong></div>'
                  + '</div>';

            /* Pagos registrados */
            if (data.pagos && data.pagos.length > 0) {
                html += '<h6 class="font-weight-bold mb-2">Pagos registrados</h6>'
                      + '<table class="table table-sm table-bordered mb-0"><thead><tr>'
                      + '<th>Folio hijo</th><th>Fecha</th><th>Método</th><th class="text-right">Monto</th>'
                      + '</tr></thead><tbody>';
                data.pagos.forEach(function(p) {
                    html += '<tr>'
                          + '<td>' + p.folio + '</td>'
                          + '<td>' + (p.fecha_inicial || '').substr(0, 10) + '</td>'
                          + '<td>' + (p.tipoPago || '—') + '</td>'
                          + '<td class="text-right">$' + parseFloat(p.monto||0).toFixed(2) + '</td>'
                          + '</tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p class="text-muted">Aún no hay pagos registrados.</p>';
            }

            document.getElementById('detalleBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('detalleBody').innerHTML = '<p class="text-danger">Error de conexión.</p>';
        });
    };

    /* ── Nuevo Pago ── */
    window.abrirNuevoPago = function(folio, total, pagado) {
        npFolioActivo = folio;
        var restante = Math.max(0, total - pagado);
        document.getElementById('npFolioNum').textContent = '#' + folio;
        document.getElementById('npTotal').textContent    = '$' + parseFloat(total).toFixed(2);
        document.getElementById('npRestante').textContent = '$' + parseFloat(restante).toFixed(2);
        document.getElementById('npTipoPago').value = '';
        document.getElementById('npMonto').value    = '';
        document.getElementById('npError').classList.add('d-none');
        document.getElementById('npSuccess').classList.add('d-none');
        document.getElementById('btnGuardarPago').disabled = false;
        $('#modalNuevoPago').modal('show');
    };

    document.getElementById('btnGuardarPago').addEventListener('click', function () {
        var btn       = this;
        var tipoPago  = document.getElementById('npTipoPago').value;
        var monto     = parseFloat(document.getElementById('npMonto').value);
        var errEl     = document.getElementById('npError');
        var sucEl     = document.getElementById('npSuccess');

        errEl.classList.add('d-none');
        sucEl.classList.add('d-none');

        if (!tipoPago) {
            errEl.textContent = 'Selecciona un método de pago.';
            errEl.classList.remove('d-none');
            return;
        }
        if (!monto || monto <= 0) {
            errEl.textContent = 'Ingresa un monto válido mayor a $0.';
            errEl.classList.remove('d-none');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Guardando...';

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);
        fd.append('folio',     npFolioActivo);
        fd.append('tipoPago',  tipoPago);
        fd.append('monto',     monto);

        fetch('<?= base_url('mostrador/anticipo/nuevo-pago') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="iconsminds-money-2 mr-1"></i> Registrar Pago';

            if (!data.ok) {
                errEl.textContent = data.error || 'Error al registrar el pago.';
                errEl.classList.remove('d-none');
                return;
            }

            var msg = 'Pago registrado. Folio hijo: #' + data.folioHijo
                    + ' | Total pagado: $' + parseFloat(data.totalPagado).toFixed(2)
                    + ' de $' + parseFloat(data.totalNota).toFixed(2) + '.';

            if (data.liquidado) {
                msg += ' ¡Nota liquidada completamente!';
            }

            sucEl.textContent = msg;
            sucEl.classList.remove('d-none');
            document.getElementById('btnGuardarPago').disabled = true;

            /* Recargar la página tras 1.5 s para reflejar nuevos montos */
            setTimeout(function() {
                window.location.reload();
            }, 1800);
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="iconsminds-money-2 mr-1"></i> Registrar Pago';
            errEl.textContent = 'Error de conexión. Intenta de nuevo.';
            errEl.classList.remove('d-none');
        });
    });
})();
</script>
<?= $this->endSection() ?>
