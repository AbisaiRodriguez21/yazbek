<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
#exportOverlayCjD {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
#exportOverlayCjD.active { display: flex; }
#exportOverlayCjD .export-card {
    background: #fff;
    border-radius: 10px;
    padding: 2.2rem 3rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,.25);
    min-width: 240px;
}
#exportOverlayCjD .exp-spinner {
    display: inline-block;
    width: 3.5rem; height: 3.5rem;
    border: 4px solid #d9e4f0;
    border-top-color: #145388;
    border-radius: 50%;
    animation: spinCjD .8s linear infinite;
}
@keyframes spinCjD { to { transform: rotate(360deg); } }
#exportOverlayCjD .exp-title { margin-top: 1.1rem; margin-bottom: .2rem; font-weight: 700; color: #145388; font-size: 1rem; }
#exportOverlayCjD .exp-sub   { color: #888; font-size: .82rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div id="exportOverlayCjD">
    <div class="export-card">
        <div class="exp-spinner"></div>
        <p class="exp-title">Generando reporte…</p>
        <p class="exp-sub">Por favor espera, esto puede tardar unos segundos.</p>
    </div>
</div>

<div class="page-title-container">
    <div class="page-title">
        <h1>Reporte Diario</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Caja</a></li>
                <li class="breadcrumb-item active">Reporte Diario</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-xl-7 col-lg-9 col-12">

        <!-- Exportar por rango -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center">
                <i class="iconsminds-file-csv mr-2" style="font-size:1.3rem;"></i>
                <span class="font-weight-bold">Exportar por rango de fechas</span>
            </div>
            <div class="card-body">
                <form action="<?= base_url('caja/reportediario') ?>" method="post" id="formReporteCaja">
                    <?= csrf_field() ?>

                    <!-- Fecha inicio -->
                    <div class="mb-4">
                        <p class="text-muted mb-2 font-weight-bold small text-uppercase">
                            <i class="simple-icon-calendar mr-1"></i> Fecha inicio
                        </p>
                        <div class="row align-items-center">
                            <div class="col-md-5 mb-2 mb-md-0">
                                <input type="date" name="fecha1" class="form-control"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-7">
                                <div class="row">
                                    <div class="col-4">
                                        <label class="text-muted small mb-1">Hora</label>
                                        <select name="horas" class="form-control">
                                            <?php for ($i = 0; $i <= 23; $i++): ?>
                                            <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>"><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-muted small mb-1">Min</label>
                                        <select name="minutos" class="form-control">
                                            <?php for ($i = 0; $i <= 59; $i++): ?>
                                            <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>"><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-muted small mb-1">Seg</label>
                                        <select name="segundos" class="form-control">
                                            <?php for ($i = 0; $i <= 59; $i++): ?>
                                            <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>"><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top mb-4"></div>

                    <!-- Fecha fin -->
                    <div class="mb-4">
                        <p class="text-muted mb-2 font-weight-bold small text-uppercase">
                            <i class="simple-icon-calendar mr-1"></i> Fecha fin
                        </p>
                        <div class="row align-items-center">
                            <div class="col-md-5 mb-2 mb-md-0">
                                <input type="date" name="fecha2" class="form-control"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-7">
                                <div class="row">
                                    <div class="col-4">
                                        <label class="text-muted small mb-1">Hora</label>
                                        <select name="horas2" class="form-control">
                                            <?php for ($i = 0; $i <= 23; $i++): ?>
                                            <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>" <?= $i === 23 ? 'selected' : '' ?>><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-muted small mb-1">Min</label>
                                        <select name="minutos2" class="form-control">
                                            <?php for ($i = 0; $i <= 59; $i++): ?>
                                            <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>" <?= $i === 59 ? 'selected' : '' ?>><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-muted small mb-1">Seg</label>
                                        <select name="segundos2" class="form-control">
                                            <?php for ($i = 0; $i <= 59; $i++): ?>
                                            <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>" <?= $i === 59 ? 'selected' : '' ?>><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-lg" id="btnExportarCaja">
                            <i class="iconsminds-download-1 mr-1"></i> Exportar reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Exportar solo hoy -->
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <p class="font-weight-bold mb-1">Exportar reporte de hoy</p>
                    <p class="text-muted small mb-0">
                        Descarga directamente el xlsx del día <strong><?= date('d/m/Y') ?></strong>
                    </p>
                </div>
                <a href="#" class="btn btn-outline-primary" id="btnHoyCaja">
                    <i class="iconsminds-download-1 mr-1"></i> Descargar hoy
                </a>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
(function () {
    var overlay = document.getElementById('exportOverlayCjD');

    function showOverlay() { overlay.classList.add('active'); }
    function hideOverlay() { overlay.classList.remove('active'); }

    function triggerDownload(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a   = document.createElement('a');
        a.href     = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }, 1000);
    }

    var form = document.getElementById('formReporteCaja');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            showOverlay();
            fetch(form.action, {
                method     : 'POST',
                body       : new FormData(form),
                credentials: 'same-origin'
            })
            .then(function (r) {
                if (!r.ok) throw new Error('Error ' + r.status);
                return r.blob();
            })
            .then(function (blob) {
                triggerDownload(blob, 'reporte_diario.xlsx');
                hideOverlay();
            })
            .catch(function () { hideOverlay(); });
        });
    }

    var btnHoy = document.getElementById('btnHoyCaja');
    if (btnHoy) {
        btnHoy.addEventListener('click', function (e) {
            e.preventDefault();
            showOverlay();
            fetch('<?= base_url('caja/reportediario/dia') ?>', {
                method     : 'GET',
                credentials: 'same-origin'
            })
            .then(function (r) {
                if (!r.ok) throw new Error('Error ' + r.status);
                return r.blob();
            })
            .then(function (blob) {
                triggerDownload(blob, 'reporte_diario_hoy.xlsx');
                hideOverlay();
            })
            .catch(function () { hideOverlay(); });
        });
    }
})();
</script>
<?= $this->endSection() ?>
