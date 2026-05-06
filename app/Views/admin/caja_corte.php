<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

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
                        <a href="<?= base_url('admin/reportediario/exportar-corte?fecha=' . urlencode($fecha ?? '') . '&estatus=' . (int)($estatus ?? 0) . '&tipopago=' . (int)($tipopago ?? 0)) ?>"
                           class="btn btn-success">
                            Exportar
                        </a>
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

});
</script>
<?= $this->endSection() ?>
