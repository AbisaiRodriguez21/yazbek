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
                            <label>Fecha</label>
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
                        <a href="<?= base_url('caja/corte/exportar')
                            . '?fecha='    . urlencode($fecha    ?? '')
                            . '&estatus='  . (int)($estatus  ?? 0)
                            . '&tipopago=' . (int)($tipopago ?? 0) ?>"
                           class="btn btn-success">
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
                    <td><?= implode('<br>', array_map('esc', $n['pagos'])) ?></td>
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
    var $fp = $('#inputFecha');
    if ($fp.data('datepicker')) { $fp.datepicker('destroy'); }
    $fp.datepicker({
        format:         'dd/mm/yyyy',
        language:       'es',
        autoclose:      true,
        todayHighlight: true,
        orientation:    'bottom auto',
        weekStart:      1
    });

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

});
</script>
<?= $this->endSection() ?>
