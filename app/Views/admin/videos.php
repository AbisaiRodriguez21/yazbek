<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-title-container">
    <div class="page-title d-flex justify-content-between w-100">
        <div>
            <h1>Videos</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                    <li class="breadcrumb-item active">Videos</li>
                </ol>
            </nav>
        </div>
        <div class="pt-2">
            <button class="btn btn-primary" data-toggle="modal" data-target="#modalSubirVideo">
                <i class="iconsminds-add"></i> Subir Video
            </button>
        </div>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>

<div class="row">
    <?php if (!empty($videos)): ?>
        <?php foreach ($videos as $v): ?>
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?= esc($v['titulo'] ?? 'Video') ?></h5>
                    <?php if (!empty($v['url'])): ?>
                    <div class="embed-responsive embed-responsive-16by9">
                        <video controls class="w-100">
                            <source src="<?= esc($v['url']) ?>">
                        </video>
                    </div>
                    <?php endif; ?>
                    <p class="text-muted mt-2 mb-0"><?= date('d/m/Y', strtotime($v['created_at'] ?? 'now')) ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="iconsminds-video icon-dual icon-lg mb-3"></i>
                <p>No hay videos registrados aún.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal subir video -->
<div class="modal fade" id="modalSubirVideo" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= base_url('admin/videos/subir') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Subir Video</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Título</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Archivo de video</label>
                        <input type="file" name="video" class="form-control-file" accept="video/*">
                    </div>
                    <div class="form-group">
                        <label>O URL del video</label>
                        <input type="url" name="url" class="form-control" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
