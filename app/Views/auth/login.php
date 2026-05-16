<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Yazbek — Sistema de Gestión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <link rel="stylesheet" href="<?= base_url('assets/font/iconsmind-s/css/iconsminds.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/font/simple-line-icons/css/simple-line-icons.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-float-label.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/dore.light.bluenavy.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/main.css') ?>">

    <style>
        /* Fondo global — corrige ruta relativa del CSS del tema */
        .fixed-background {
            background-image: url('<?= base_url('assets/img/login/balloon-lg.jpg') ?>') !important;
        }
        /* Lado izquierdo: foto modelo Yazbek */
        .auth-card .image-side {
            background-image: url('<?= base_url('assets/img/login/balloon.jpg') ?>') !important;
            background-position: center top !important;
            background-size: cover !important;
        }
        /* Lado derecho: blanco (igual al original) */
        .auth-card .form-side {
            background: #fff !important;
        }
    </style>
</head>

<body class="background no-footer">
    <div class="fixed-background"></div>

    <main>
        <div class="container">
            <div class="row h-100">
                <div class="col-12 col-md-10 mx-auto my-auto">
                    <div class="card auth-card">

                        <!-- Lado izquierdo: imagen + texto -->
                        <div class="position-relative image-side">
                            <p class="text-white h2">Yazbek</p>
                            <p class="white mb-0">
                                Bienvenido. Llena tus datos para acceder al sistema.
                            </p>
                            <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible mt-3" role="alert">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <?= esc($error) ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Lado derecho: formulario -->
                        <div class="form-side">
                            <a href="<?= base_url('/') ?>">
                                <span class="logo-single"></span>
                            </a>
                            <h6 class="mb-4">Login</h6>

                            <form action="<?= base_url('login') ?>" method="POST">
                                <?= csrf_field() ?>

                                <label class="form-group has-float-label mb-4">
                                    <input class="form-control"
                                           type="email"
                                           id="email"
                                           name="email"
                                           value="<?= old('email') ?>"
                                           required
                                           autocomplete="email" />
                                    <span>E-mail</span>
                                </label>

                                <label class="form-group has-float-label mb-4">
                                    <input class="form-control"
                                           type="password"
                                           id="pass"
                                           name="pass"
                                           required
                                           autocomplete="current-password" />
                                    <span>Password</span>
                                </label>

                                <div class="d-flex justify-content-between align-items-center">
                                    <button class="btn btn-primary btn-lg btn-shadow" type="submit">
                                        LOGIN
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?= base_url('assets/js/vendor/jquery-3.3.1.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/vendor/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/dore.script.js') ?>"></script>
</body>
</html>
