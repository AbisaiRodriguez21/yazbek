<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-datepicker3.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
<style>
    @media print { .no-print { display: none !important; } }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1>Corte de Caja</h1>
        <nav class="breadcrumb-container d-none d-sm-block d-lg-inline-block" aria-label="breadcrumb">
            <ol class="breadcrumb pt-0">
                <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Caja</a></li>
                <li class="breadcrumb-item active">Corte</li>
            </ol>
        </nav>
        <div class="separator mb-5"></div>
    </div>

    <div class="col-12 mb-4 data-table-rows data-tables-hide-filter">

        <!-- Filtros — misma estructura que admin/caja_corte.php -->
        <div class="row mb-3 no-print">
            <form action="<?= base_url('caja/corte') ?>" method="post" class="block-content w-100">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Fecha Desde</label>
                            <input name="fecha" type="text"
                                   class="form-control"
                                   id="inputFecha"
                                   placeholder="dd/mm/yyyy"
                                   value="<?= esc($fecha ?? '') ?>"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Fecha Hasta <small class="text-muted">(opcional)</small></label>
                            <input name="fecha_hasta" type="text"
                                   class="form-control"
                                   id="inputFechaHasta"
                                   placeholder="dd/mm/yyyy"
                                   value="<?= esc($fechaHasta ?? '') ?>"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Estatus</label>
                            <select name="estatus" class="form-control" id="estatus">
                                <option value="0">Todos</option>
                                <?php foreach ($statusList as $s): ?>
                                    <option value="<?= (int)$s['Id'] ?>"
                                        <?= (int)($estatus ?? 0) === (int)$s['Id'] ? 'selected' : '' ?>>
                                        <?= esc($s['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Tipo Pago</label>
                            <select name="tipopago" class="form-control" id="tipopago">
                                <option value="0">Todos</option>
                                <?php foreach ($tipoPagoList as $tp): ?>
                                    <option value="<?= (int)$tp['id'] ?>"
                                        <?= (int)($tipopago ?? 0) === (int)$tp['id'] ? 'selected' : '' ?>>
                                        <?= esc($tp['descripcion']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label>&nbsp;</label><br>
                        <button type="submit" class="btn btn-success">Buscar</button>
                    </div>
                    <div class="col-md-2">
                        <label>&nbsp;</label><br>
                        <a id="btnExportarCorte"
                           href="<?= base_url('caja/corte/exportar')
                            . '?fecha='       . urlencode($fecha      ?? '')
                            . '&fecha_hasta=' . urlencode($fechaHasta ?? '')
                            . '&estatus='     . (int)($estatus  ?? 0)
                            . '&tipopago='    . (int)($tipopago ?? 0) ?>"
                           class="btn btn-success"
                           data-download data-filename="corte_caja.xlsx"
                           data-download-title="Generando corte de caja…"
                           data-download-sub="Estamos preparando el archivo, esto puede tardar unos segundos.">
                            Exportar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de resultados — mismas columnas que corte2.php -->
        <table id="datatableCorte" class="table table-striped b-t b-light">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Referencia</th>
                    <th>Fecha</th>
                    <th>Nombre Cliente</th>
                    <th>Vendedor</th>
                    <th>Tipo de Pago &nbsp;/&nbsp; Monto</th>
                    <th>Estatus Nota</th>
                    <th>Verificado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notas as $n): ?>
                <tr>
                    <td valign="middle"><?= esc($n['folio']) ?></td>
                    <td valign="middle"><?= esc($n['referencia'] ?? '') ?></td>
                    <td valign="middle"><?= esc($n['fecha'] ?? '') ?></td>
                    <td valign="middle"><?= esc($n['cliente'] ?? '') ?></td>
                    <td valign="middle"><?= esc($n['vendedor'] ?? '') ?></td>
                    <td>
                        <?php foreach ($n['pagos'] as $p): ?>
                            <div class="mb-1">
                                <span><?= esc($p['texto']) ?></span>
                                <?php if (!empty($p['editable']) && $p['mn_id']): ?>
                                    <div class="d-flex align-items-center mt-1" style="gap:4px;">
                                        <input type="text"
                                               class="form-control form-control-sm input-ref-pago"
                                               style="max-width:170px;"
                                               placeholder="Referencia bancaria"
                                               value="<?= esc($p['referencia']) ?>"
                                               data-mn-id="<?= (int)$p['mn_id'] ?>">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-success btn-guardar-ref"
                                                title="Guardar">
                                            <i class="simple-icon-check"></i>
                                        </button>
                                        <span class="ref-ok text-success d-none" style="font-size:.8rem;">✓</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td valign="middle"><?= esc($n['status'] ?? '') ?></td>
                    <td valign="middle"><?= esc($n['verificado'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/js/vendor/bootstrap-datepicker.js') ?>"></script>
<script>
$(document).ready(function () {

    // ── Locale español (debe definirse ANTES de init para evitar corrupción de fecha)
    if ($.fn.datepicker && $.fn.datepicker.dates) {
        $.fn.datepicker.dates['es'] = {
            days:        ["Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado"],
            daysShort:   ["Dom","Lun","Mar","Mié","Jue","Vie","Sáb"],
            daysMin:     ["Do","Lu","Ma","Mi","Ju","Vi","Sa"],
            months:      ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"],
            monthsShort: ["Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic"],
            today:       "Hoy",
            clear:       "Borrar",
            titleFormat: "MM yyyy"
        };
    }

    // ── Datepicker — inicializa solo por ID (evita doble-init de dore.script.js)
    var dpOpts = {
        format:         'dd/mm/yyyy',
        language:       'es',
        autoclose:      true,
        todayHighlight: true,
        orientation:    'bottom auto',
        weekStart:      1
    };
    var $fp = $('#inputFecha');
    if ($fp.data('datepicker')) { $fp.datepicker('destroy'); }
    $fp.datepicker(dpOpts);

    var $fph = $('#inputFechaHasta');
    if ($fph.data('datepicker')) { $fph.datepicker('destroy'); }
    $fph.datepicker(dpOpts);

    // ── DataTable sin búsqueda (igual que corte2.php: searching: false)
    if ($.fn.DataTable.isDataTable('#datatableCorte')) {
        $('#datatableCorte').DataTable().destroy();
    }
    $('#datatableCorte').DataTable({
        searching: false,
        language: {
            url:        '<?= base_url('assets/js/vendor/datatables.spanish.json') ?>',
            emptyTable: 'No hay resultados para los filtros seleccionados.'
        }
    });

    // ── Guardar referencia de pago (Transferencia/Depósito/Cargo con tarjeta)
    $(document).on('click', '.btn-guardar-ref', function () {
        var $btn  = $(this);
        var $wrap = $btn.closest('.d-flex');
        var $input = $wrap.find('.input-ref-pago');
        var $ok    = $wrap.find('.ref-ok');
        var mnId   = $input.data('mn-id');
        var ref    = $input.val().trim();

        $btn.prop('disabled', true);

        $.post('<?= base_url('caja/monto/referencia') ?>', {
            <?= csrf_token() ?>: '<?= csrf_hash() ?>',
            mn_id:      mnId,
            referencia: ref
        })
        .done(function (res) {
            if (res.ok) {
                $ok.removeClass('d-none');
                setTimeout(function () { $ok.addClass('d-none'); }, 2000);
            }
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    // También guardar al presionar Enter en el input
    $(document).on('keypress', '.input-ref-pago', function (e) {
        if (e.which === 13) {
            $(this).closest('.d-flex').find('.btn-guardar-ref').trigger('click');
        }
    });

});
</script>
<?= $this->endSection() ?>
