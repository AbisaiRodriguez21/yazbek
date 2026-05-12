<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yazbek - Sistema de Gestión</title>

    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap.rtl.only.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/main.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/dore.light.bluenavy.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/font/iconsmind-s/css/iconsminds.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/font/simple-line-icons/css/simple-line-icons.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/component-custom-switch.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/perfect-scrollbar.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/dataTables.bootstrap4.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/datatables.responsive.bootstrap4.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/select2.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/select2-bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-float-label.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-datepicker3.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/glide.core.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/nouislider.min.css') ?>">

    <style>
    @media print {
        .app-menu, .navbar, .page-footer, .btn, .breadcrumb, .page-title-container { display: none !important; }
        main { margin: 0 !important; padding: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
    }
    </style>

    <?= $this->renderSection('page_css') ?>
</head>
<body id="app-container" class="menu-default show-spinner">

    <!-- ========== NAVBAR ========== -->
    <nav class="navbar fixed-top">
        <div class="d-flex align-items-center navbar-left">
            <a href="#" class="menu-button d-none d-md-block">
                <svg class="main" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 9 17">
                    <rect x="0.48" y="0.5" width="7" height="1" />
                    <rect x="0.48" y="7.5" width="7" height="1" />
                    <rect x="0.48" y="15.5" width="7" height="1" />
                </svg>
                <svg class="sub" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 17">
                    <rect x="1.56" y="0.5" width="16" height="1" />
                    <rect x="1.56" y="7.5" width="16" height="1" />
                    <rect x="1.56" y="15.5" width="16" height="1" />
                </svg>
            </a>
            <a href="#" class="menu-button-mobile d-xs-block d-sm-block d-md-none">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26 17">
                    <rect x="0.5" y="0.5" width="25" height="1" />
                    <rect x="0.5" y="7.5" width="25" height="1" />
                    <rect x="0.5" y="15.5" width="25" height="1" />
                </svg>
            </a>

            <?php $acceso = (int)($usuario['acceso'] ?? 0); ?>
            <?php if ($acceso === 1): ?>
            <div class="search" data-search-path="<?= base_url('mostrador/consulta?f=') ?>">
                <input placeholder="Buscar folio...">
                <span class="search-icon">
                    <i class="simple-icon-magnifier"></i>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <a class="navbar-logo" href="<?= base_url('/') ?>">
            <span class="logo d-none d-xs-block"></span>
            <span class="logo-mobile d-block d-xs-none"></span>
        </a>

        <div class="navbar-right">
            <div class="header-icons d-inline-block align-middle">
                <div class="d-none d-md-inline-block align-text-bottom mr-3">
                    <div class="custom-switch custom-switch-primary-inverse custom-switch-small pl-1">
                        <input class="custom-switch-input" id="switchDark" type="checkbox" checked>
                        <label class="custom-switch-btn" for="switchDark"></label>
                    </div>
                </div>
                <button class="header-icon btn btn-empty d-none d-sm-inline-block" type="button" id="fullScreenButton">
                    <i class="simple-icon-size-fullscreen"></i>
                    <i class="simple-icon-size-actual"></i>
                </button>
            </div>
            <div class="user d-inline-block">
                <button class="btn btn-empty p-0" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="name"><?= esc($usuario['nombre'] ?? 'Usuario') ?></span>
                    <span><img alt="Profile" src="<?= base_url('assets/img/profiles/l-1.jpg') ?>" /></span>
                </button>
                <div class="dropdown-menu dropdown-menu-right mt-3">
                    <a class="dropdown-item" href="<?= base_url('logout') ?>">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ========== SIDEBAR / MENU ========== -->
    <?php $acceso = (int)($usuario['acceso'] ?? 0); ?>

    <?php if ($acceso === 1): ?>
    <!-- ADMIN — dual sidebar (main + sub) -->
    <?php
        // setSilent(true) evita HTTPException cuando la URL tiene menos segmentos de los pedidos
        $uri  = service('uri')->setSilent();
        $seg1 = (string) $uri->getSegment(1); // "admin", "reportes", "mostrador"
        $seg2 = (string) $uri->getSegment(2); // "inventario", "caja", "venta", etc.
        $seg3 = (string) $uri->getSegment(3); // "corte", "productos", etc.

        // Ítem activo del main-menu
        $menuActivo = 'dashboards';
        if ($seg2 === 'inventario') $menuActivo = 'productos';
        elseif (in_array($seg2, ['caja', 'reportediario'])) $menuActivo = 'contabilidad';
        elseif (($seg1 === 'admin' && $seg2 === 'venta') || ($seg1 === 'mostrador' && in_array($seg2, ['venta','mayoreo','consulta'])) || $seg1 === 'reportes') $menuActivo = 'ventas';
        elseif (in_array($seg2, ['usuarios', 'mensajes', 'importar', 'exportar', 'clientes']) || ($seg1 === 'mostrador' && $seg2 === 'clientes')) $menuActivo = 'admon';

        // Ítem activo del sub-menu
        $subActivo = '';
        if ($seg2 === 'caja' && $seg3 === 'corte') $subActivo = 'cortecaja';
        elseif ($seg2 === 'caja' && $seg3 !== 'corte') $subActivo = 'verificarcaja';
        elseif ($seg2 === 'reportediario') $subActivo = 'reportediario';
        elseif ($seg1 === 'mostrador' && $seg2 === 'venta') $subActivo = 'venta';
        elseif ($seg1 === 'mostrador' && $seg2 === 'mayoreo') $subActivo = 'ventamayoreo';
        elseif ($seg1 === 'mostrador' && $seg2 === 'consulta') $subActivo = 'consultafolios';
        elseif ($seg2 === 'mensajes') $subActivo = 'mensajes';
        elseif ($seg2 === 'usuarios') $subActivo = 'usuarios';
        elseif ($seg2 === 'clientes' || ($seg1 === 'mostrador' && $seg2 === 'clientes')) $subActivo = 'clientes';
        elseif ($seg2 === 'importar') $subActivo = 'importar';
    ?>
    <div class="menu">
        <div class="main-menu">
            <div class="scroll">
                <ul class="list-unstyled">
                    <li class="<?= $menuActivo === 'dashboards' ? 'active' : '' ?>" data-menu="dashboards">
                        <a href="<?= base_url('admin') ?>">
                            <i class="iconsminds-shop-4"></i>
                            <span>Dashboards</span>
                        </a>
                    </li>
                    <li class="<?= $menuActivo === 'productos' ? 'active' : '' ?>" data-menu="productos">
                        <a href="<?= base_url('admin/inventario') ?>">
                            <i class="iconsminds-digital-drawing"></i>
                            <span>Productos</span>
                        </a>
                    </li>
                    <li class="<?= $menuActivo === 'contabilidad' ? 'active' : '' ?>" data-menu="contabilidad">
                        <a href="#contabilidad">
                            <i class="iconsminds-air-balloon-1"></i>
                            <span>Contabilidad</span>
                        </a>
                    </li>
                    <li class="<?= $menuActivo === 'ventas' ? 'active' : '' ?>" data-menu="ventas">
                        <a href="#ventas">
                            <i class="iconsminds-pantone"></i>
                            <span>Ventas</span>
                        </a>
                    </li>
                    <li class="<?= $menuActivo === 'admon' ? 'active' : '' ?>" data-menu="admon">
                        <a href="#admon">
                            <i class="iconsminds-library"></i>
                            <span>Admon</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="sub-menu">
            <div class="scroll">
                <!-- Contabilidad — igual al original: 3 ítems -->
                <ul class="list-unstyled" data-link="contabilidad">
                    <li class="<?= $subActivo === 'cortecaja'    ? 'active' : '' ?>" data-submenu="cortecaja">
                        <a href="<?= base_url('admin/caja/corte') ?>">
                            <i class="iconsminds-cash-register-2"></i>
                            <span class="d-inline-block">Corte de caja</span>
                        </a>
                    </li>
                    <li class="<?= $subActivo === 'verificarcaja' ? 'active' : '' ?>" data-submenu="verificarcaja">
                        <a href="<?= base_url('admin/caja') ?>">
                            <i class="simple-icon-check"></i>
                            <span class="d-inline-block">Verificar Caja</span>
                        </a>
                    </li>
                    <li class="<?= $subActivo === 'reportediario' ? 'active' : '' ?>" data-submenu="reportediario">
                        <a href="<?= base_url('admin/reportediario') ?>">
                            <i class="simple-icon-calculator"></i>
                            <span class="d-inline-block">Reporte diario</span>
                        </a>
                    </li>
                </ul>

                <!-- Ventas -->
                <ul class="list-unstyled" data-link="ventas">
                    <li class="<?= $subActivo === 'venta' ? 'active' : '' ?>" data-submenu="venta">
                        <a href="<?= base_url('mostrador/venta') ?>">
                            <i class="simple-icon-picture"></i>
                            <span class="d-inline-block">Venta</span>
                        </a>
                    </li>
                    <li class="<?= $subActivo === 'ventamayoreo' ? 'active' : '' ?>" data-submenu="ventamayoreo">
                        <a href="<?= base_url('mostrador/mayoreo') ?>">
                            <i class="simple-icon-picture"></i>
                            <span class="d-inline-block">Venta Mayoreo</span>
                        </a>
                    </li>
                    <li class="<?= $subActivo === 'consultafolios' ? 'active' : '' ?>" data-submenu="consultafolios">
                        <a href="<?= base_url('mostrador/consulta') ?>">
                            <i class="simple-icon-check"></i>
                            <span class="d-inline-block">Consultar folios</span>
                        </a>
                    </li>
                </ul>

                <!-- Admon -->
                <ul class="list-unstyled" data-link="admon">
                    <li class="<?= $subActivo === 'mensajes'  ? 'active' : '' ?>" data-submenu="mensajes">
                        <a href="<?= base_url('admin/mensajes') ?>">
                            <i class="simple-icon-picture"></i>
                            <span class="d-inline-block">Mensajes</span>
                        </a>
                    </li>
                    <li class="<?= $subActivo === 'usuarios'  ? 'active' : '' ?>" data-submenu="usuarios">
                        <a href="<?= base_url('admin/usuarios') ?>">
                            <i class="simple-icon-check"></i>
                            <span class="d-inline-block">Usuarios</span>
                        </a>
                    </li>
                    <li class="<?= $subActivo === 'clientes' ? 'active' : '' ?>" data-submenu="clientes">
                        <a href="<?= base_url('admin/clientes') ?>">
                            <i class="iconsminds-digital-drawing"></i>
                            <span class="d-inline-block">Clientes</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <?php elseif ($acceso === 2): ?>
    <!-- CAJA — single sidebar -->
    <?php
        $cajUri  = service('uri')->setSilent();
        $cajSeg2 = (string) $cajUri->getSegment(2);
        $cajSeg3 = (string) $cajUri->getSegment(3);
        // active helpers
        $cajActivo = function(string ...$segs) use ($cajSeg2, $cajSeg3): string {
            foreach ($segs as $s) {
                if ($cajSeg2 === $s) return 'active';
            }
            return '';
        };
        $cajDash = ($cajSeg2 === '' || $cajSeg2 === null) ? 'active' : '';
        $cajCobrar = in_array($cajSeg2, ['cobrar','folio','venta','pago']) ? 'active' : '';
        $cajCorte  = in_array($cajSeg2, ['corte']) ? 'active' : '';
    ?>
    <div class="menu">
        <div class="main-menu">
            <div class="scroll">
                <ul class="list-unstyled">
                    <li class="<?= $cajDash ?>">
                        <a href="<?= base_url('caja') ?>">
                            <i class="iconsminds-shop-4"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="<?= $cajSeg2 === 'clientes' ? 'active' : '' ?>">
                        <a href="<?= base_url('caja/clientes') ?>">
                            <i class="iconsminds-digital-drawing"></i>
                            <span>Clientes</span>
                        </a>
                    </li>
                    <li class="<?= $cajCobrar ?>">
                        <a href="<?= base_url('caja/cobrar') ?>">
                            <i class="iconsminds-cash-register-2"></i>
                            <span>Verificar Caja</span>
                        </a>
                    </li>
                    <li class="<?= $cajSeg2 === 'consulta' ? 'active' : '' ?>">
                        <a href="<?= base_url('caja/consulta') ?>">
                            <i class="iconsminds-pantone"></i>
                            <span>Consultar Folios</span>
                        </a>
                    </li>
                    <li class="<?= $cajCorte ?>">
                        <a href="<?= base_url('caja/corte') ?>">
                            <i class="iconsminds-receipt-4"></i>
                            <span>Corte de Caja</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <?php elseif ($acceso === 3 || $acceso === 4): ?>
    <!-- MOSTRADOR / GVENTAS — single sidebar -->
    <?php
        $mosUri  = service('uri')->setSilent();
        $mosSeg2 = (string) $mosUri->getSegment(2);
    ?>
    <div class="menu">
        <div class="main-menu">
            <div class="scroll">
                <ul class="list-unstyled">
                    <li class="<?= ($mosSeg2 === '' || $mosSeg2 === null) ? 'active' : '' ?>">
                        <a href="<?= base_url('mostrador') ?>">
                            <i class="iconsminds-shop-4"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="<?= $mosSeg2 === 'clientes' ? 'active' : '' ?>">
                        <a href="<?= base_url('mostrador/clientes') ?>">
                            <i class="iconsminds-digital-drawing"></i>
                            <span>Clientes</span>
                        </a>
                    </li>
                    <li class="<?= $mosSeg2 === 'venta' ? 'active' : '' ?>">
                        <a href="<?= base_url('mostrador/venta') ?>">
                            <i class="iconsminds-air-balloon-1"></i>
                            <span>Venta</span>
                        </a>
                    </li>
                    <li class="<?= $mosSeg2 === 'mayoreo' ? 'active' : '' ?>">
                        <a href="<?= base_url('mostrador/mayoreo') ?>">
                            <i class="iconsminds-air-balloon-1"></i>
                            <span>Venta Mayoreo</span>
                        </a>
                    </li>
                    <li class="<?= $mosSeg2 === 'consulta' ? 'active' : '' ?>">
                        <a href="<?= base_url('mostrador/consulta') ?>">
                            <i class="iconsminds-pantone"></i>
                            <span>Consultar Folios</span>
                        </a>
                    </li>
                    <li class="<?= $mosSeg2 === 'inventario' ? 'active' : '' ?>">
                        <a href="<?= base_url('mostrador/inventario') ?>">
                            <i class="iconsminds-three-arrow-fork"></i>
                            <span>Inventario</span>
                        </a>
                    </li>
                    <li class="<?= $mosSeg2 === 'anticipos' ? 'active' : '' ?>">
                        <a href="<?= base_url('mostrador/anticipos') ?>">
                            <i class="iconsminds-dollar"></i>
                            <span>Anticipos</span>
                        </a>
                    </li>
                    <li class="<?= $mosSeg2 === 'metas' ? 'active' : '' ?>">
                        <a href="<?= base_url('mostrador/metas') ?>">
                            <i class="iconsminds-bullseye"></i>
                            <span>Metas</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========== MAIN CONTENT ========== -->
    <main>
        <div class="container-fluid">
            <?= $this->renderSection('content') ?>
        </div>
    </main>

    <footer class="page-footer">
        <div class="footer-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <span class="text-muted d-block text-center text-sm-left d-sm-inline-block">
                            Yazbek &copy; <?= date('Y') ?> — Sistema de Gestión
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="<?= base_url('assets/js/vendor/jquery-3.3.1.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/vendor/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/vendor/perfect-scrollbar.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/vendor/mousetrap.min.js') ?>"></script>
    <script>
        var BASE_URL = '<?= base_url() ?>';
        if (typeof Storage !== 'undefined') {
            var saved = localStorage.getItem('dore-theme-color');
            if (!saved || saved.indexOf('dark') !== -1) {
                localStorage.setItem('dore-theme-color', 'dore.light.bluenavy.min.css');
            }
        }
    </script>
    <script src="<?= base_url('assets/js/vendor/datatables.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/dore.script.js') ?>"></script>
    <script src="<?= base_url('assets/js/scripts.js') ?>"></script>

    <?= $this->renderSection('page_scripts') ?>

    <script>
    /* ── AJAX Navigation ── */
    (function () {
        var BASE = BASE_URL; // definido en el layout

        // URLs que NO deben interceptarse (descargas, logout, POST-only)
        var skipPatterns = ['/exportar', '/reportediario/dia', '/logout',
                            '/importar/subir', '/caja/ajax', '/ajax'];

        function shouldSkip(url) {
            if (!url || url.charAt(0) === '#') return true;
            // URL absolutas de otro dominio
            if (url.indexOf('http') === 0 && url.indexOf(BASE) !== 0) return true;
            for (var i = 0; i < skipPatterns.length; i++) {
                if (url.indexOf(skipPatterns[i]) !== -1) return true;
            }
            return false;
        }

        function getSegments(url) {
            var path = url.split('?')[0].replace(/https?:\/\/[^\/]+/, '').replace(/\/index\.php/, '');
            var parts = path.split('/').filter(Boolean);
            return { seg1: parts[0]||'', seg2: parts[1]||'', seg3: parts[2]||'' };
        }

        function updateSidebar(url) {
            var s = getSegments(url);
            var seg1 = s.seg1, seg2 = s.seg2, seg3 = s.seg3;

            // menuActivo
            var menu = 'dashboards';
            if (seg2 === 'inventario') menu = 'productos';
            else if (seg2 === 'caja' || seg2 === 'reportediario') menu = 'contabilidad';
            else if ((seg1 === 'admin' && seg2 === 'venta') || (seg1 === 'mostrador' && ['venta','mayoreo','consulta'].indexOf(seg2) !== -1) || seg1 === 'reportes') menu = 'ventas';
            else if (['usuarios','mensajes','importar','exportar','clientes'].indexOf(seg2) !== -1 || (seg1 === 'mostrador' && seg2 === 'clientes')) menu = 'admon';

            // subActivo
            var sub = '';
            if (seg2 === 'caja' && seg3 === 'corte') sub = 'cortecaja';
            else if (seg2 === 'caja') sub = 'verificarcaja';
            else if (seg2 === 'reportediario') sub = 'reportediario';
            else if (seg1 === 'mostrador' && seg2 === 'venta') sub = 'venta';
            else if (seg1 === 'mostrador' && seg2 === 'mayoreo') sub = 'ventamayoreo';
            else if (seg1 === 'mostrador' && seg2 === 'consulta') sub = 'consultafolios';
            else if (seg2 === 'mensajes') sub = 'mensajes';
            else if (seg2 === 'usuarios') sub = 'usuarios';
            else if (seg2 === 'clientes' || (seg1 === 'mostrador' && seg2 === 'clientes')) sub = 'clientes';
            else if (seg2 === 'importar') sub = 'importar';

            // Actualizar main-menu active
            document.querySelectorAll('.main-menu li[data-menu]').forEach(function(li) {
                li.classList.toggle('active', li.dataset.menu === menu);
            });

            // Mostrar el sub-panel correcto (Dore usa JS para esto)
            var withSub = ['contabilidad','ventas','admon'];
            if (withSub.indexOf(menu) !== -1) {
                var trigger = document.querySelector('.main-menu a[href="#' + menu + '"]');
                if (trigger && !trigger.closest('li').classList.contains('active')) {
                    trigger.click();
                }
            }

            // Actualizar sub-menu active
            document.querySelectorAll('.sub-menu li[data-submenu]').forEach(function(li) {
                li.classList.toggle('active', li.dataset.submenu === sub);
            });
        }

        function reinitDataTables() {
            if (!$.fn || !$.fn.DataTable) return;
            // Solo reinit tablas gestionadas por dore (.data-table-standard).
            // Las tablas con ID propio (ej. #datatableProductos) las inicializa
            // el page_scripts de cada vista — no tocarlas aquí para evitar doble init.
            document.querySelectorAll('.data-table-standard').forEach(function(el) {
                if (!$.fn.DataTable.isDataTable(el)) {
                    $(el).DataTable({ responsive: true, pageLength: 10 });
                }
            });
        }

        function execPageScripts(doc) {
            var layoutSrc = ['jquery','bootstrap.bundle','perfect-scrollbar','mousetrap',
                             'dore.script','scripts.js','datatables.min','glide.min'];

            var externalPromises = [];
            var inlineCodes = [];

            doc.querySelectorAll('script').forEach(function(script) {
                if (script.src) {
                    var isLayout = layoutSrc.some(function(k){ return script.src.indexOf(k) !== -1; });
                    if (isLayout) return;
                    // Si ya está cargado, no volver a cargarlo
                    if (document.querySelector('script[src="'+script.src+'"]')) return;
                    var p = new Promise(function(resolve) {
                        var s = document.createElement('script');
                        s.src = script.src;
                        s.onload = resolve;
                        s.onerror = resolve; // resolver igualmente para no bloquear
                        document.body.appendChild(s);
                    });
                    externalPromises.push(p);
                } else {
                    var code = script.textContent.trim();
                    if (!code) return;
                    if (code.indexOf('BASE_URL') !== -1) return;
                    if (code.indexOf('dore-theme-color') !== -1) return;
                    inlineCodes.push(code);
                }
            });

            // Ejecutar inline scripts una vez que los externos estén listos
            // Retornar la Promise para que loadPage pueda encadenar reinitDataTables
            return Promise.all(externalPromises).then(function() {
                inlineCodes.forEach(function(code) {
                    var s = document.createElement('script');
                    s.textContent = code;
                    document.body.appendChild(s);
                });
            });
        }

        var navigating = false;

        function loadPage(url) {
            if (navigating) return;
            navigating = true;

            var main = document.querySelector('main .container-fluid');
            if (main) main.style.opacity = '0.4';

            fetch(url, { credentials: 'same-origin' })
                .then(function(r) {
                    if (!r.ok) throw new Error(r.status);
                    return r.text();
                })
                .then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');

                    var newMain = doc.querySelector('main .container-fluid');
                    if (!newMain) {
                        // Si no encontró el contenedor principal (ej. página de login tras expirar sesión)
                        // hacer recarga completa
                        window.location.href = url;
                        return;
                    }
                    main.innerHTML = newMain.innerHTML;
                    main.style.opacity = '1';

                    // Actualizar título de la pestaña
                    if (doc.title) document.title = doc.title;

                    updateSidebar(url);
                    history.pushState({ url: url }, doc.title || '', url);

                    // execPageScripts retorna Promise — reinitDataTables corre después
                    execPageScripts(doc).then(function() {
                        reinitDataTables();
                    });

                    navigating = false;
                })
                .catch(function() {
                    window.location.href = url;
                    navigating = false;
                });
        }

        // Interceptar clicks en sidebar Y en el contenido de la página
        document.addEventListener('click', function(e) {
            var a = e.target.closest('a');
            if (!a) return;
            var href = a.getAttribute('href');
            if (!href || shouldSkip(href)) return;

            // Saltar links con target="_blank" o download
            if (a.target === '_blank' || a.hasAttribute('download')) return;

            // Saltar links que abren modales de Bootstrap (#modal-id)
            if (href.charAt(0) === '#') return;

            // Verificar que sea un link interno (mismo dominio)
            var absUrl;
            if (href.indexOf('http') === 0) {
                if (href.indexOf(BASE) !== 0) return; // dominio externo
                absUrl = href;
            } else {
                absUrl = BASE + href.replace(/^\//, '');
            }

            e.preventDefault();
            loadPage(absUrl);
        });

        // Back / Forward del navegador
        window.addEventListener('popstate', function() {
            loadPage(window.location.href);
        });

    })();
    </script>
</body>
</html>
