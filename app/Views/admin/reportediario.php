<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<style>
#exportOverlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
#exportOverlay.active {
    display: flex;
}
#exportOverlay .export-card {
    background: #fff;
    border-radius: 10px;
    padding: 2.2rem 3rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, .25);
    min-width: 240px;
}
#exportOverlay .exp-spinner {
    display: inline-block;
    width: 3.5rem;
    height: 3.5rem;
    border: 4px solid #d9e4f0;
    border-top-color: #145388;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
#exportOverlay .exp-title {
    margin-top: 1.1rem;
    margin-bottom: .2rem;
    font-weight: 700;
    color: #145388;
    font-size: 1rem;
}
#exportOverlay .exp-sub {
    color: #888;
    font-size: .82rem;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title">
        <h1>Reporte Diario</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
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
                <form action="<?= base_url('admin/reportediario') ?>" method="post" id="formReporte"
                      data-download data-filename="reporte_diario.xlsx"
                      data-download-title="Generando reporte…"
                      data-download-sub="Estamos preparando tu archivo, esto puede tardar unos segundos.">
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
                        <button type="submit" class="btn btn-primary btn-lg" id="btnExportar">
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
                        Descarga directamente el XLS del día <strong><?= date('d/m/Y') ?></strong>
                    </p>
                </div>
                <a href="<?= base_url('admin/reportediario/dia') ?>" class="btn btn-outline-primary" id="btnHoy"
                   data-download data-filename="reporte_diario_hoy.xlsx"
                   data-download-title="Generando reporte…"
                   data-download-sub="Estamos preparando el archivo del día, un momento por favor.">
                    <i class="iconsminds-download-1 mr-1"></i> Descargar hoy
                </a>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<!-- La descarga la maneja el indicador global (data-download) definido en el layout. -->
<?= $this->endSection() ?>
