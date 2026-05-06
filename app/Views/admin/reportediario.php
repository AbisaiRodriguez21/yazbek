<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1>Reporte Diario</h1>
        <div class="separator mb-5"></div>
    </div>

    <div class="col-lg-6 col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Exportar Lista del día</h5>

                <form action="<?= base_url('admin/reportediario') ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- Fecha inicial -->
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha inicio</label>
                        <div class="col-sm-4">
                            <input type="date" name="fecha1" class="form-control"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-sm-2">
                            <select name="horas" class="form-control">
                                <?php for ($i = 0; $i <= 23; $i++): ?>
                                <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>"><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <select name="minutos" class="form-control">
                                <?php for ($i = 0; $i <= 59; $i++): ?>
                                <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>"><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <select name="segundos" class="form-control">
                                <?php for ($i = 0; $i <= 59; $i++): ?>
                                <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>"><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fecha final -->
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Fecha fin</label>
                        <div class="col-sm-4">
                            <input type="date" name="fecha2" class="form-control"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-sm-2">
                            <select name="horas2" class="form-control">
                                <?php for ($i = 0; $i <= 23; $i++): ?>
                                <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>" <?= $i === 23 ? 'selected' : '' ?>><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <select name="minutos2" class="form-control">
                                <?php for ($i = 0; $i <= 59; $i++): ?>
                                <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>" <?= $i === 59 ? 'selected' : '' ?>><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <select name="segundos2" class="form-control">
                                <?php for ($i = 0; $i <= 59; $i++): ?>
                                <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>" <?= $i === 59 ? 'selected' : '' ?>><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mt-3">
                        <div class="col-sm-3">
                            <button type="submit" class="btn btn-warning">
                                Crear reporte
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
