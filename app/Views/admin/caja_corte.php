<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
#exportOverlayCj {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
#exportOverlayCj.active { display: flex; }
#exportOverlayCj .export-card {
    background: #fff;
    border-radius: 10px;
    padding: 2.2rem 3rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,.25);
    min-width: 240px;
}
#exportOverlayCj .exp-spinner {
    display: inline-block;
    width: 3.5rem; height: 3.5rem;
    border: 4px solid #d9e4f0;
    border-top-color: #145388;
    border-radius: 50%;
    animation: spinCj .8s linear infinite;
}
@keyframes spinCj { to { transform: rotate(360deg); } }
#exportOverlayCj .exp-title { margin-top: 1.1rem; margin-bottom: .2rem; font-weight: 700; color: #145388; font-size: 1rem; }
#exportOverlayCj .exp-sub   { color: #888; font-size: .82rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div id="exportOverlayCj" style="display:none;">
    <div class="export-card">
        <div class="exp-spinner"></div>
        <p class="exp-title">Generando reporte…</p>
        <p class="exp-sub">Por favor espera, esto puede tardar unos segundos.</p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <h1>Corte de Caja</h1>
        <div class="separator mb-5"></div>
    </div>

    <div class="col-12 mb-4 data-table-rows data-tables-hide-filter">

        <!-- Formulario de filtros (igual que corte2.php original) -->
        <div class="row">
            <form action="<?= base_url('admin/caja/corte') ?>" method="post" class="block-content w-100">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Fecha</label>
                            <input name="fecha" type="text"
                                   class="form-control datepicker"
                                   id="fecha"
                                   placeholder="dd/mm/yyyy"
                                   value="<?= esc($fecha ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Estatus</label>
                            <select name="estatus" class="form-control" id="estatus">
                                <option value="0">Todos</option>
                                <?php foreach ($listaEstatus as $s): ?>
                                    <option value="<?= $s['Id'] ?>"
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
                                <?php foreach ($listaTipoPago as $tp): ?>
                                    <option value="<?= $tp['id'] ?>"
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
                        <button type="button" class="btn btn-success" id="btnExportarCorte">
                            Exportar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de resultados (igual que corte2.php original) -->
        <table id="datatableProductos" class="table table-striped b-t b-light">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Referencia</th>
                    <th>Fecha</th>
                    <th>Nombre Cliente</th>
                    <th>Vendedor</th>
                    <th>Tipo de Pago &nbsp; / &nbsp; Monto</th>
                    <th>Estatus Nota</th>
                    <th>Verificado</th>
                </tr>
            </thead>
            <tbody id="busqueda">
                <?php if (!empty($notas)): ?>
                    <?php foreach ($notas as $n): ?>
                    <tr>
                        <td valign="middle"><?= esc($n['folio']) ?></td>
                        <td valign="middle"><?= esc($n['referencia']) ?></td>
                        <td valign="middle"><?= esc($n['fecha']) ?></td>
                        <td valign="middle"><?= esc($n['cliente']) ?></td>
                        <td valign="middle"><?= esc($n['vendedor']) ?></td>
                        <td><?= implode('<br>', array_map('esc', $n['pagos'])) ?></td>
                        <td valign="middle"><?= esc($n['status']) ?></td>
                        <td valign="middle"><?= esc($n['verificado']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No hay resultados para los filtros seleccionados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/js/vendor/bootstrap-datepicker.js') ?>"></script>
<script>
$(document).ready(function () {

    // Inicializar datepicker formato dd/mm/yyyy (igual que corte2.php original)
    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom auto'
    });

    // DataTable sin campo de búsqueda — destroy primero por si se navega via AJAX
    if ($.fn.DataTable.isDataTable('#datatableProductos')) {
        $('#datatableProductos').DataTable().destroy();
    }
    $('#datatableProductos').DataTable({
        searching: false,
        language: {
            url: '<?= base_url('assets/js/vendor/datatables.spanish.json') ?>'
        }
    });

    // Exportar corte con fetch (misma lógica que Reporte Diario)
    document.getElementById('btnExportarCorte').addEventListener('click', function () {
        var overlay = document.getElementById('exportOverlayCj');
        overlay.classList.add('active');

        var fecha    = document.getElementById('fecha').value;
        var estatus  = document.getElementById('estatus').value;
        var tipopago = document.getElementById('tipopago').value;
        var url = '<?= base_url('admin/caja/corte/exportar') ?>?fecha=' + encodeURIComponent(fecha)
                + '&estatus=' + estatus + '&tipopago=' + tipopago;

        fetch(url, { method: 'GET', credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('Error ' + r.status);
                return r.blob();
            })
            .then(function (blob) {
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'corte_caja.xls';
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    URL.revokeObjectURL(a.href);
                    document.body.removeChild(a);
                }, 1000);
                overlay.classList.remove('active');
            })
            .catch(function () { overlay.classList.remove('active'); });
    });

});
</script>
<?= $this->endSection() ?>
