<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1>Hola</h1>
        <nav class="breadcrumb-container d-none d-sm-block d-lg-inline-block" aria-label="breadcrumb">
            <ol class="breadcrumb pt-0">
                <li class="breadcrumb-item"><a href="<?= base_url('caja') ?>">Home</a></li>
                <li class="breadcrumb-item active"><?= esc($mensaje['fecha'] ?? '') ?></li>
            </ol>
        </nav>

        <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= session()->getFlashdata('error') ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= session()->getFlashdata('success') ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($banner['texto'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="iconsminds-speaker mr-2"></i> <?= esc($banner['texto']) ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php endif; ?>

        <div class="separator mb-5"></div>

        <?php if (!empty($mensaje)): ?>
        <div class="col-12 mb-4">
            <div class="card active">
                <?php if (!empty($mensaje['imagen'])): ?>
                <div class="position-relative">
                    <img class="card-img-top"
                         src="<?= base_url($mensaje['imagen']) ?>"
                         alt="Banner"
                         style="max-height:480px; object-fit:cover; width:100%;">
                </div>
                <?php endif; ?>
                <div class="card-body">
                    <div class="row">
                        <div class="col-10">
                            <?php if (!empty($mensaje['t_mensaje'])): ?>
                            <p class="list-item-heading mb-4 pt-1"><?= esc($mensaje['t_mensaje']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($mensaje['texto'])): ?>
                            <footer>
                                <p class="text-muted text-small mb-0 font-weight-light">
                                    <?= esc($mensaje['texto']) ?>
                                </p>
                            </footer>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?= $this->endSection() ?>
