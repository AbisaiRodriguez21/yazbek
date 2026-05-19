<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Consultar Folios<?php if (($tipo ?? '') === 'mayoreo'): ?> <small class="text-muted">— Mayoreo</small><?php endif; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Consulta</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= session()->getFlashdata('success') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= session()->getFlashdata('error') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="separator mb-5"></div>
<div class="row">
    <div class="col-12 mb-4">
        <table class="table responsive nowrap" id="tablaAdminConsulta" style="width:100%">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Tipo Pago</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Modal Nuevo Pago Anticipo (Admin) -->
<div class="modal fade" id="modalAdminNuevoPago" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Pago — Folio <span id="anpFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block">Total nota</small>
                        <strong id="anpTotal">$0.00</strong>
                    </div>
                </div>
                <div class="form-group">
                    <label>Método de pago <span class="text-danger">*</span></label>
                    <select id="anpTipoPago" class="form-control">
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
                        <input type="number" id="anpMonto" class="form-control" min="0.01" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div id="anpError" class="alert alert-danger d-none"></div>
                <div id="anpSuccess" class="alert alert-success d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnAdminGuardarPago">
                    <i class="iconsminds-money-2 mr-1"></i> Registrar Pago
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ver Folio -->
<div class="modal fade" id="modalVerFolio" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Folio</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="modalVerFolioBody">
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
/* DataTables JS ya cargado en el layout — no importar de nuevo */
var STATUS_LABELS = {
    1: '<span class="badge badge-primary">Abierta</span>',
    2: '<span class="badge badge-info">En proceso</span>',
    3: '<span class="badge badge-danger">Cancelada</span>',
    4: '<span class="badge badge-warning">Anticipo</span>',
    5: '<span class="badge badge-success">Pagada</span>'
};

$(document).ready(function() {
    var tipoFiltro = '<?= esc($tipo ?? '') ?>';

    if ($.fn.DataTable.isDataTable('#tablaAdminConsulta')) {
        $('#tablaAdminConsulta').DataTable().destroy();
    }

    $('#tablaAdminConsulta').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= base_url('admin/consulta/datatable') ?>',
            type: 'GET',
            data: function(d) {
                if (tipoFiltro) d.tipo = tipoFiltro;
                return d;
            }
        },
        columns: [
            { data: 'folio' },
            { data: 'fecha_inicial', render: function(data) {
                return data ? data.substr(0, 10) : '';
            }},
            { data: 'cliente' },
            { data: 'vendedor' },
            { data: 'tipopago' },
            { data: 'total', render: function(data) {
                return '$' + parseFloat(data || 0).toFixed(2);
            }, className: 'text-right'},
            { data: 'idstatus', render: function(data) {
                var sid = parseInt(data, 10);
                return STATUS_LABELS[sid] || '<span class="badge badge-secondary">Desconocido</span>';
            }},
            { data: null, render: function(data, type, row) {
                return accionesNota(row);
            }, orderable: false, searchable: false}
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            url: '<?= base_url('assets/js/vendor/datatables.spanish.json') ?>'
        }
    });
});

function accionesNota(n) {
    var btns = '';
    var idstatus  = parseInt(n.idstatus, 10);
    var referencia = parseInt(n.referencia || 0, 10);
    var esPadre   = referencia === 0;
    var BASE_PAGO = '<?= base_url('admin/venta/') ?>';

    if ((idstatus === 1 || idstatus === 4) && esPadre) {
        // Solo folios padre Abiertos/Anticipo: ir a pantalla de pago
        btns += '<a href="' + BASE_PAGO + n.folio + '/confirmar" class="btn btn-xs btn-outline-primary mr-1">Ver / Pagar</a>';
    } else if (idstatus !== 5) {
        btns += '<a href="#" class="btn btn-xs btn-outline-primary mr-1"'
              + ' onclick="adminVerFolio(' + n.folio + '); return false;">Ver</a>';
    }
    // Liquidar: solo en folios padre con status Anticipo
    if (idstatus === 4 && esPadre) {
        btns += '<a href="#" class="btn btn-xs btn-success mr-1"'
              + ' onclick="adminLiquidarAnticipo(' + n.folio + '); return false;">Liquidar</a>';
    }
    if (idstatus !== 5) {
        btns += '<a href="#" class="btn btn-xs btn-outline-danger"'
              + ' onclick="adminCancelarFolio(' + n.folio + '); return false;">Cancelar</a>';
    }
    // Pagada + padre: mostrar Verificar y Ver Ticket
    if (idstatus === 5 && esPadre) {
        btns += '<a href="#" class="btn btn-xs btn-outline-secondary mr-1"'
              + ' onclick="adminVerFolio(' + n.folio + '); return false;">Verificar</a>';
        btns += '<a href="#" class="btn btn-xs btn-outline-dark">Ver Ticket</a>';
    }

    if (!btns) btns = '<span class="text-muted">—</span>';
    return btns;
}

function fn_muestra_modal() {
    var folio = document.getElementById('folio_input') ? document.getElementById('folio_input').value : 0;
    if (!folio) return;
    if (!confirm('¿Marcar el folio ' + folio + ' como Pago Verificado?')) return;
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';
    var fd = new FormData();
    fd.append(csrfName, csrfHash);
    fd.append('folio', folio);
    fetch('<?= base_url('admin/caja/verificar') ?>', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function(r) { return r.text(); })
    .then(function(resp) {
        if (resp === 'bien') {
            $('#modalVerFolio').modal('hide');
            $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
        } else {
            alert('No se pudo verificar el pago.');
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function fn_modal_calcelar_nota() {
    var folio = document.getElementById('folio_input') ? document.getElementById('folio_input').value : 0;
    if (!folio) return;
    if (!confirm('¿Cancelar el folio ' + folio + '? Esta acción restaura el inventario.')) return;
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';
    var fd = new FormData();
    fd.append(csrfName, csrfHash);
    fd.append('folio', folio);
    fetch('<?= base_url('admin/caja/cancelar') ?>', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function(r) { return r.text(); })
    .then(function(resp) {
        $('#modalVerFolio').modal('hide');
        $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
    })
    .catch(function() { alert('Error de conexión.'); });
}

function adminVerFolio(folio) {
    $('#modalVerFolioBody').html('<p class="text-center"><i>Cargando...</i></p>');
    $('#modalVerFolio').modal('show');
    $.post('<?= base_url('admin/caja/ajax') ?>', { folio: folio }, function(html) {
        $('#modalVerFolioBody').html(html);
    }).fail(function() {
        $('#modalVerFolioBody').html('<p class="text-danger">Error al cargar el folio.</p>');
    });
}

var anpFolioActivo = null;

function adminNuevoPago(folio, total) {
    anpFolioActivo = folio;
    document.getElementById('anpFolioNum').textContent = '#' + folio;
    document.getElementById('anpTotal').textContent    = '$' + parseFloat(total).toFixed(2);
    document.getElementById('anpTipoPago').value = '';
    document.getElementById('anpMonto').value    = '';
    document.getElementById('anpError').classList.add('d-none');
    document.getElementById('anpSuccess').classList.add('d-none');
    document.getElementById('btnAdminGuardarPago').disabled = false;
    document.getElementById('btnAdminGuardarPago').innerHTML = '<i class="iconsminds-money-2 mr-1"></i> Registrar Pago';
    $('#modalAdminNuevoPago').modal('show');
}

document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = '<?= csrf_token() ?>';
    var csrfHash  = '<?= csrf_hash() ?>';

    document.getElementById('btnAdminGuardarPago').addEventListener('click', function () {
        var btn      = this;
        var tipoPago = document.getElementById('anpTipoPago').value;
        var monto    = parseFloat(document.getElementById('anpMonto').value);
        var errEl    = document.getElementById('anpError');
        var sucEl    = document.getElementById('anpSuccess');

        errEl.classList.add('d-none');
        sucEl.classList.add('d-none');

        if (!tipoPago) {
            errEl.textContent = 'Selecciona un método de pago.';
            errEl.classList.remove('d-none'); return;
        }
        if (!monto || monto <= 0) {
            errEl.textContent = 'Ingresa un monto válido mayor a $0.';
            errEl.classList.remove('d-none'); return;
        }

        btn.disabled = true;
        btn.textContent = 'Guardando...';

        var fd = new FormData();
        fd.append(csrfToken, csrfHash);
        fd.append('folio',    anpFolioActivo);
        fd.append('tipoPago', tipoPago);
        fd.append('monto',    monto);

        fetch('<?= base_url('mostrador/anticipo/nuevo-pago') ?>', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="iconsminds-money-2 mr-1"></i> Registrar Pago';
            if (!data.ok) {
                errEl.textContent = data.error || 'Error al registrar el pago.';
                errEl.classList.remove('d-none'); return;
            }
            var msg = 'Pago registrado. Folio hijo: #' + data.folioHijo
                    + ' | Pagado: $' + parseFloat(data.totalPagado).toFixed(2)
                    + ' de $' + parseFloat(data.totalNota).toFixed(2);
            if (data.liquidado) msg += ' — ¡Nota liquidada!';
            sucEl.textContent = msg;
            sucEl.classList.remove('d-none');
            btn.disabled = true;
            setTimeout(function() {
                $('#modalAdminNuevoPago').modal('hide');
                $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
            }, 1800);
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="iconsminds-money-2 mr-1"></i> Registrar Pago';
            errEl.textContent = 'Error de conexión.';
            errEl.classList.remove('d-none');
        });
    });
});

function adminLiquidarAnticipo(folio) {
    if (!confirm('¿Liquidar el anticipo ' + folio + ' y todos sus pagos? Esta acción marcará la nota como Pagada.')) return;
    var fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    fetch('<?= base_url('admin/folio/') ?>' + folio + '/liquidar', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
        } else {
            alert(data.error || 'No se pudo liquidar.');
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function adminCancelarFolio(folio) {
    if (!confirm('¿Cancelar el folio ' + folio + '?')) return;
    $.post('<?= base_url('admin/caja/cancelar') ?>', { folio: folio }, function(resp) {
        if (resp === '1') {
            $('#tablaAdminConsulta').DataTable().ajax.reload(null, false);
        } else {
            alert('No se pudo cancelar el folio ' + folio + '.');
        }
    }).fail(function() {
        alert('Error de comunicación al cancelar.');
    });
}
</script>
<?= $this->endSection() ?>
                                                                                                                                                                                                                                                                                                                                                                                                       