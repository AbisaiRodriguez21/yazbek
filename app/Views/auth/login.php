<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yazbek - Iniciar Sesión</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap.min.css') ?>">

    <!-- Dore CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/main.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/dore.dark.bluenavy.min.css') ?>">

    <!-- Icon fonts -->
    <link rel="stylesheet" href="<?= base_url('assets/font/simple-line-icons/css/simple-line-icons.css') ?>">

    <style>
        body {
            background: linear-gradient(135deg, #1a2a4a 0%, #0f1623 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }

        .login-card {
            background: #1e2d47;
            border-radius: 8px;
            padding: 3rem 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo img {
            height: 60px;
            margin-bottom: 1rem;
        }

        .login-logo h1 {
            font-size: 1.8rem;
            color: #fff;
            font-weight: 600;
            margin: 0;
        }

        .login-form {
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            color: #aaa;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-group input {
            background: #2d3e52;
            border: 1px solid #3d4e62;
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            background: #354555;
            border-color: #5a7a9e;
            outline: none;
            color: #fff;
        }

        .form-group input::placeholder {
            color: #888;
        }

        .login-button {
            background: linear-gradient(90deg, #5a7a9e 0%, #4a6a8e 100%);
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .login-button:hover {
            background: linear-gradient(90deg, #6a8aae 0%, #5a7a9e 100%);
            transform: translateY(-2px);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .alert {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 4px;
            border: 1px solid #d4a574;
            background: #3a2f25;
            color: #f4b896;
        }

        .alert-danger {
            border-color: #c75757;
            background: #3a2525;
            color: #f08080;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <h1>Yazbek</h1>
                <p style="color: #888; margin: 0; font-size: 0.9rem;">Sistema de Gestión</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?= esc($error) ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="<?= base_url('login') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" placeholder="tu@email.com" required
                           value="<?= old('email') ?>">
                </div>

                <div class="form-group">
                    <label for="pass">Contraseña</label>
                    <input type="password" id="pass" name="pass" placeholder="Contraseña" required>
                </div>

                <button type="submit" class="login-button">Iniciar Sesión</button>
            </form>
        </div>
    </div>

    <script src="<?= base_url('assets/js/vendor/jquery-3.3.1.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/vendor/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
