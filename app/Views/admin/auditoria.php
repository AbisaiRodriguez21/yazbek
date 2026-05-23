<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<style>
/* ── Filtros ── */
.audit-filters {
    background: #fff;
    border: 1px solid #e3e8f0;
    border-radius: 10px;
    padding: 18px 20px 14px;
    margin-bottom: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.audit-filters label {
    font-size: .75rem;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 5px;
    display: block;
}
.audit-filters .form-control {
    font-size: .82rem;
    border-radius: 6px;
}

/* ── Tabla card ── */
.audit-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
    overflow: hidden;
}
.audit-card-header {
    background: linear-gradient(135deg, #145388 0%, #1a6bb5 100%);
    color: #fff;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.audit-card-header i {
    font-size: 1.1rem;
    opacity: .85;
}
.audit-card-header span {
    font-size: .92rem;
    font-weight: 600;
    letter-spacing: .02em;
}
.audit-card-header .badge-count {
    margin-left: auto;
    background: rgba(255,255,255,.2);
    color: #fff;
    font-size: .72rem;
    padding: 3px 10px;
    border-radius: 20px;
    font-weight: 600;
}

/* ── DataTable custom ── */
#tablaAuditoria_wrapper {
    padding: 16px 20px 20px;
}
#tablaAuditoria_wrapper .dataTables_length,
#tablaAuditoria_wrapper .dataTables_filter {
    margin-bottom: 14px;
}
#tablaAuditoria_wrapper .dataTables_length label,
#tablaAuditoria_wrapper .dataTables_filter label {
    font-size: .8rem;
    color: #6b7280;
}
#tablaAuditoria {
    border-collapse: separate !important;
    border-spacing: 0;
    width: 100% !important;
}
#tablaAuditoria thead tr th {
    background: #f1f5fb;
    color: #374151;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: 10px 12px;
    border-top: none;
    border-bottom: 2px solid #dde3f0;
    white-space: nowrap;
}
#tablaAuditoria tbody tr {
    transition: background .15s;
}
#tablaAuditoria tbody tr:hover td {
    background: #f0f6ff !important;
}
#tablaAuditoria tbody tr td {
    font-size: .82rem;
    color: #374151;
    padding: 10px 12px;
    border-top: 1px solid #f0f2f7;
    vertical-align: middle;
}
#tablaAuditoria tbody tr:nth-child(even) td {
    background: #fafbfd;
}
#tablaAuditoria_wrapper .dataTables_paginate .paginate_button.current,
#tablaAuditoria_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: #145388 !important;
    color: #fff !important;
    border-color: #145388 !important;
    border-radius: 6px;
}
#tablaAuditoria_wrapper .dataTables_paginate .paginate_button:hover {
    background: #e8f0fe !important;
    color: #145388 !important;
    border-color: #e8f0fe !important;
    border-radius: 6px;
}
/* columna # más chica y centrada */
#tablaAuditoria td:first-child,
#tablaAuditoria th:first-child {
    text-align: center;
    color: #9ca3af;
    font-size: .78rem;
}
</style>

<div class="page-title-container">
    <div class="page-title">
        <h1>Auditoría del Sistema</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Auditoría</li>
            </ol>
        </nav>
    </div>
</div>

<div class="separator mb-4"></div>

<!-- ── Filtros ── -->
<div class="audit-filters">
    <div class="row align-items-end">
        <div class="col-12 col-md mb-2 mb-md-0">
            <label>Acción</label>
            <select id="fAccion" class="form-control form-control-sm">
                <option value="">— Todas —</option>
                <?php foreach ($acciones as $a): ?>
                <option value="<?= esc($a) ?>"><?= esc($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md mb-2 mb-md-0">
            <label>Usuario</label>
            <select id="fUsuario" class="form-control form-control-sm">
                <option value="0">— Todos —</option>
                <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int)$u['Id'] ?>"><?= esc($u['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md mb-2 mb-md-0">
            <label>Desde</label>
            <input type="date" id="fDesde" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md mb-2 mb-md-0">
            <label>Hasta</label>
            <input type="date" id="fHasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-12 col-md-auto mb-2 mb-md-0">
            <label>&nbsp;</label>
            <div class="d-flex" style="gap:8px;">
                <button id="btnFiltrar" class="btn btn-primary btn-sm px-4">Filtrar</button>
                <button id="btnLimpiar" class="btn btn-outline-secondary btn-sm px-4">Limpiar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Tabla ── -->
<div class="audit-card">
    <div class="audit-card-header">
        <i class="simple-icon-list"></i>
        <span>Registro de Actividad</span>
        <span class="badge-count" id="totalRegistros">—</span>
    </div>
    <table id="tablaAuditoria" class="table mb-0" style="width:100%">
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Acción</th>
                <th>Registro</th>
                <th>Descripción</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
(function () {
    // Destruir instancia previa (navegación AJAX reutiliza el mismo ID)
    if (window.auditDT) {
        try { window.auditDT.destroy(); } catch (e) {}
        window.auditDT = null;
    }
    if ($.fn.DataTable.isDataTable('#tablaAuditoria')) {
        $('#tablaAuditoria').DataTable().destroy();
    }

    window.auditDT = $('#tablaAuditoria').DataTable({
        processing : true,
        serverSide : true,
        order      : [[0, 'desc']],
        pageLength : 25,
        lengthMenu : [25, 50, 100, 250],
        language   : { url: '<?= base_url('assets/js/vendor/datatables.spanish.json') ?>' },
        ajax: {
            url  : '<?= base_url('admin/auditoria/datatable') ?>',
            type : 'GET',
            data : function (d) {
                d.accion     = $('#fAccion').val();
                d.usuario_id = $('#fUsuario').val();
                d.desde      = $('#fDesde').val();
                d.hasta      = $('#fHasta').val();
            }
        },
        columns: [
            { data: 0, width: '55px'  },
            { data: 1, width: '148px' },
            { data: 2, width: '160px', orderable: false },
            { data: 3, width: '160px', orderable: false },
            { data: 4, width: '145px', orderable: false },
            { data: 5,                 orderable: false  }
        ],
        drawCallback: function (settings) {
            var total = settings.fnRecordsTotal();
            $('#totalRegistros').text(total.toLocaleString() + ' registros');
        }
    });

    $('#btnFiltrar').on('click', function () {
        window.auditDT.ajax.reload();
    });

    $('#btnLimpiar').on('click', function () {
        $('#fAccion').val('');
        $('#fUsuario').val('0');
        $('#fDesde').val('');
        $('#fHasta').val('<?= date('Y-m-d') ?>');
        window.auditDT.search('').ajax.reload();
    });
})();
</script>
<?= $this->endSection() ?>
