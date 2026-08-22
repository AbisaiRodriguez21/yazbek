<?= $this->extend('layouts/main') ?>

<?= $this->section('page_css') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/select2.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/select2-bootstrap.min.css') ?>">
<style id="fact-styles">
/* ── Tarjetas de resumen ─────────────────────────────────── */
.fact-summary-card {
    border-radius: 8px;
    padding: 18px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    background: #fff;
    margin-bottom: 20px;
}
.fact-summary-card .fsc-icon {
    width: 48px; height: 48px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.fsc-facturado .fsc-icon { background: #e6f4ea; color: #1a7a3c; }
.fsc-pendiente .fsc-icon { background: #fff3e0; color: #e65100; }
.fact-summary-card .fsc-label { font-size: .78rem; color: #777; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.fact-summary-card .fsc-value { font-size: 1.45rem; font-weight: 700; color: #1a1a2e; line-height: 1.1; }
.fact-summary-card .fsc-count { font-size: .8rem; color: #999; margin-top: 2px; }

/* ── Filtros ─────────────────────────────────────────────── */
.fact-filters {
    background: #fff;
    border-radius: 8px;
    padding: 16px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
    margin-bottom: 20px;
}

/* ── Tabla ───────────────────────────────────────────────── */
.fact-table-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
    overflow: hidden;
}
.fact-table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px 10px;
    border-bottom: 1px solid #f0f0f0;
    flex-wrap: wrap;
    gap: 8px;
}
.fact-table-header h5 { margin: 0; font-size: 1rem; font-weight: 700; color: #1a1a2e; }
.fact-bulk-btns { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

/* Badges facturación */
.badge-facturado { background: #1a7a3c; color: #fff; font-size: .72rem; padding: 3px 7px; border-radius: 3px; display:inline-block; }
.badge-pendiente { background: #fd7e14; color: #fff; font-size: .72rem; padding: 3px 7px; border-radius: 3px; display:inline-block; }
.badge-nofact    { background: #6c757d; color: #fff; font-size: .72rem; padding: 3px 7px; border-radius: 3px; display:inline-block; }

/* Checkbox col */
#tablaFacturacion th:first-child,
#tablaFacturacion td:first-child { width: 36px; text-align: center; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.fact-summary-card{border-radius:8px;padding:18px 22px;display:flex;align-items:center;gap:16px;box-shadow:0 2px 10px rgba(0,0,0,.08);background:#fff;margin-bottom:20px}
.fact-summary-card .fsc-icon{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.35rem;flex-shrink:0}
.fsc-facturado .fsc-icon{background:#e6f4ea;color:#1a7a3c}.fsc-pendiente .fsc-icon{background:#fff3e0;color:#e65100}
.fact-summary-card .fsc-label{font-size:.78rem;color:#777;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.fact-summary-card .fsc-value{font-size:1.45rem;font-weight:700;color:#1a1a2e;line-height:1.1}
.fact-summary-card .fsc-count{font-size:.8rem;color:#999;margin-top:2px}
.fact-filters{background:#fff;border-radius:8px;padding:16px 20px;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:20px}
.fact-table-card{background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden}
.fact-table-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px 10px;border-bottom:1px solid #f0f0f0;flex-wrap:wrap;gap:8px}
.fact-table-header h5{margin:0;font-size:1rem;font-weight:700;color:#1a1a2e}
.fact-bulk-btns{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.badge-facturado{background:#1a7a3c;color:#fff;font-size:.72rem;padding:3px 7px;border-radius:3px;display:inline-block}
.badge-pendiente{background:#fd7e14;color:#fff;font-size:.72rem;padding:3px 7px;border-radius:3px;display:inline-block}
.badge-nofact{background:#6c757d;color:#fff;font-size:.72rem;padding:3px 7px;border-radius:3px;display:inline-block}
#tablaFacturacion th:first-child,#tablaFacturacion td:first-child{width:36px;text-align:center}

/* Vista previa de factura — mismo diseño que Consultar Folios */
.cfdi-preview { font-size: 8.5pt; color: #111; }
.cfdi-preview table { width: 100%; border-collapse: collapse; }
.cfdi-preview .r { text-align: right; }
.cfdi-preview .c { text-align: center; }
.cfdi-header { display: flex; border: 1px solid #888; margin-bottom: 6px; }
.cfdi-header-left { width: 65%; padding: 8px 10px; border-right: 1px solid #888; }
.cfdi-header-right { width: 35%; }
.cfdi-emp-name { font-size: 13pt; font-weight: bold; color: #1a3a5c; margin-bottom: 3px; }
.cfdi-emp-data { font-size: 8pt; line-height: 1.6; }
.cfdi-tipo-box { background: #1a3a5c; color: #fff; font-weight: bold; font-size: 8.5pt; text-align: center; padding: 5px 6px; }
.cfdi-tipo-tabla td { font-size: 8pt; padding: 3px 6px; border-bottom: 1px solid #ddd; }
.cfdi-tipo-tabla .lbl { color: #1a3a5c; font-weight: bold; width: 50%; }
.cfdi-sec-title { background: #222; color: #fff; font-weight: bold; padding: 4px 6px; font-size: 8.5pt; margin: 4px 0 2px; }
.cfdi-cliente-box { border: 1px solid #aaa; padding: 6px 8px; font-size: 8pt; margin-bottom: 6px; }
.cfdi-conc th { background: #222; color: #fff; padding: 4px; font-size: 7.5pt; text-align: center; border: 1px solid #555; }
.cfdi-conc td { border: 1px solid #ddd; padding: 3px 4px; font-size: 8pt; }
.cfdi-imp-row td { background: #f0f0f0; font-size: 7pt; color: #555; padding: 2px 6px; border-left: 1px solid #ddd; border-right: 1px solid #ddd; }
.cfdi-tot { width: 280px; margin-left: auto; margin-top: 6px; }
.cfdi-tot td { padding: 3px 6px; font-size: 8.5pt; }
.cfdi-tot-final td { background: #1a3a5c; color: #fff; font-weight: bold; font-size: 9.5pt; }
.cfdi-pending-note { font-size: 7.5pt; color: #888; margin-top: 8px; font-style: italic; }
.cfdi-logo { max-height: 45px; max-width: 150px; margin-bottom: 6px; }
.cfdi-regimen-bar { border: 1px solid #888; padding: 4px 8px; font-size: 8pt; margin-bottom: 6px; }
.cfdi-pend { color: #aaa; font-style: italic; }
.cfdi-sbox { background: #f7f7f7; border: 1px dashed #ccc; padding: 6px 8px; font-size: 7.5pt; color: #999; margin-top: 6px; text-align: center; }
</style>

<div class="page-title-container">
    <div class="page-title">
        <h1>Facturación</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('admin') ?>">Admin</a></li>
                <li class="breadcrumb-item active">Facturación</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= session()->getFlashdata('success') ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="separator mb-4"></div>

<!-- ══ Sección: Factura Global Consolidada por Cliente ══ -->
<div class="card mb-4" style="border:2px solid #3d85c8;border-radius:10px;overflow:hidden;">
    <div class="card-header d-flex align-items-center" style="background:#1a4a80;padding:14px 20px;">
        <i class="simple-icon-layers mr-2 text-white" style="font-size:1.2rem;"></i>
        <div>
            <span style="font-size:1.05rem;font-weight:700;color:#fff;">Factura Global Consolidada</span>
            <small class="d-block" style="color:#aecfef;font-size:.78rem;margin-top:1px;">
                Une <strong style="color:#fff;">todos los productos</strong> de los folios pendientes del cliente en <strong style="color:#fff;">un solo CFDI</strong> — no una factura por folio.
            </small>
        </div>
    </div>
    <div class="card-body" style="padding:16px 20px;">
        <div class="row align-items-end" style="row-gap:10px;">
            <div class="col-md-5">
                <label class="font-weight-bold mb-1" style="font-size:.95rem;">
                    Seleccionar cliente
                    <small class="text-muted font-weight-normal ml-1" style="font-size:.78rem;">— busca por nombre o RFC</small>
                </label>
                <select id="filtCliente" class="form-control" style="width:100%">
                    <option value="">— Escribe para buscar un cliente —</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small font-weight-bold mb-1">Desde</label>
                <input type="date" id="filtDesde" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="small font-weight-bold mb-1">Hasta</label>
                <input type="date" id="filtHasta" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex" style="gap:8px;">
                <button id="btnGenerarConsolidada" class="btn btn-primary flex-grow-1 d-none" onclick="factAbrirModalConsolidada()">
                    <i class="simple-icon-doc mr-1"></i> Generar Factura Consolidada
                </button>
                <button id="btnRestablecerCliente" class="btn btn-outline-secondary d-none" onclick="factRestablecerCliente()" title="Limpiar selección">
                    <i class="simple-icon-refresh"></i>
                </button>
            </div>
        </div>
        <div id="panelConsolidadaInfo" class="d-none mt-2 p-2" style="background:#f0f7ff;border-radius:6px;font-size:.85rem;color:#1a4a80;border-left:3px solid #3d85c8;">
        </div>
    </div>
</div>

<!-- inputs ocultos para compatibilidad con JS de filtros -->
<input type="hidden" id="filtFolio">
<input type="hidden" id="filtEstado">
<input type="hidden" id="btnFiltrar">

<!-- Tabla principal -->
<div class="fact-table-card">
    <div class="fact-table-header">
        <h5>
            <i class="simple-icon-doc mr-2"></i>Notas de Venta
            <span class="badge badge-secondary ml-1" id="totalFacturacion" style="font-size:.75rem">—</span>
        </h5>
        <div class="fact-bulk-btns">
            <span id="btnSelCount" style="display:none; font-size:.8rem; color:#555;"></span>
            <button id="btnFacturarSeleccionados" class="btn btn-sm btn-primary" style="display:none;" onclick="factAbrirModalSeleccionados()">
                <i class="simple-icon-doc mr-1"></i> Facturar Seleccionados (<span id="numSeleccionados">0</span>)
            </button>
        </div>
    </div>
    <div style="padding: 10px 20px 20px;">
        <table class="table responsive nowrap" id="tablaFacturacion" style="width:100%">
            <thead>
                <tr>
                    <th><input type="checkbox" id="chkFactAll" title="Seleccionar todos visibles"></th>
                    <th>Folio</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="text-right">Total</th>
                    <th>Factura</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- ══ Modal: Datos SAT (individual / seleccionados) ══════════ -->
<div class="modal fade" id="modalFacturacionSAT" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="simple-icon-doc mr-2"></i>Datos Fiscales SAT — <span id="mfsTitulo"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Captura o verifica los datos fiscales del receptor. Al facturar, se guardarán en el perfil del cliente.</p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>RFC Receptor <span class="text-danger">*</span></label>
                            <input type="text" id="mfsRfc" class="form-control text-uppercase" maxlength="13" placeholder="XAXX010101000">
                            <small class="text-muted">12 (moral) o 13 (física) caracteres</small>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Razón Social / Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="mfsRazonSocial" class="form-control text-uppercase" maxlength="200" placeholder="NOMBRE COMPLETO O RAZÓN SOCIAL">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>CP Fiscal <span class="text-danger">*</span></label>
                            <input type="text" id="mfsCP" class="form-control" maxlength="5" placeholder="72270">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Régimen Fiscal</label>
                            <select id="mfsRegimenFiscal" class="form-control">
                                <option value="616" selected>616 – Sin obligaciones fiscales</option>
                                <option value="601">601 – General de Ley Personas Morales</option>
                                <option value="612">612 – Personas Físicas con Actividades Empresariales</option>
                                <option value="621">621 – Incorporación Fiscal</option>
                                <option value="626">626 – RESICO</option>
                                <option value="605">605 – Sueldos y Salarios</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Uso del CFDI</label>
                            <select id="mfsUsoCFDI" class="form-control">
                                <option value="S01" selected>S01 – Sin efectos fiscales</option>
                                <option value="G01">G01 – Adquisición de mercancias</option>
                                <option value="G03">G03 – Gastos en general</option>
                                <option value="I01">I01 – Construcciones</option>
                                <option value="D01">D01 – Honorarios médicos y dentales</option>
                                <option value="CP01">CP01 – Pagos</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Forma de Pago</label>
                            <select id="mfsFormaPago" class="form-control">
                                <option value="01" selected>01 – Efectivo</option>
                                <option value="02">02 – Cheque nominativo</option>
                                <option value="03">03 – Transferencia electrónica</option>
                                <option value="04">04 – Tarjeta de crédito</option>
                                <option value="28">28 – Tarjeta de débito</option>
                                <option value="99">99 – Por definir</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Método de Pago</label>
                            <select id="mfsMetodoPago" class="form-control">
                                <option value="PUE" selected>PUE – Pago en una sola exhibición</option>
                                <option value="PPD">PPD – Pago en parcialidades o diferido</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea id="mfsObservaciones" class="form-control" rows="2"
                              placeholder="Notas o números de ticket para esta factura (opcional)"></textarea>
                </div>
                <!-- Barra de progreso masivo -->
                <div id="mfsProgreso" class="d-none mt-2">
                    <div class="progress" style="height:10px;border-radius:5px;">
                        <div id="mfsProgresoBar" class="progress-bar bg-primary" style="width:0%"></div>
                    </div>
                    <small id="mfsProgresoTxt" class="text-muted mt-1 d-block text-center"></small>
                </div>
                <div id="mfsResultados" class="mt-2 d-none">
                    <div id="mfsResultadoOk"  class="alert alert-success py-2 mb-1 d-none"></div>
                    <div id="mfsResultadoErr" class="alert alert-danger  py-2 mb-1 d-none"></div>
                </div>
                <!-- Alerta correo faltante -->
                <div id="mfsCorreoAlert" class="alert alert-warning d-none mt-2" style="border-left:4px solid #e6a817;">
                    <strong>⚠️ El cliente no tiene correo registrado.</strong>
                    <div class="mt-2 d-flex" style="gap:8px;align-items:center;">
                        <input type="hidden" id="mfsClienteId" value="">
                        <input type="email" id="mfsCorreoInput" class="form-control form-control-sm"
                               placeholder="correo@ejemplo.com" style="max-width:280px;">
                        <button type="button" class="btn btn-sm btn-warning" id="btnGuardarCorreo">
                            Guardar correo
                        </button>
                        <span id="mfsCorreoOk" class="text-success d-none" style="font-size:.85rem;">✔ Guardado</span>
                    </div>
                    <small class="text-muted d-block mt-1">El correo es necesario para enviar la factura al cliente.</small>
                </div>
                <div id="mfsError" class="alert alert-danger d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnMfsFacturar">
                    <i class="simple-icon-doc mr-1"></i> Facturar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ Modal: Vista Previa de Factura (paso 2, solo individual) ══ -->
<div class="modal fade" id="modalMfsFacturaPreview" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="simple-icon-eye mr-2"></i>Vista Previa de Factura — Folio <span id="mfsPvFolioNum"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="cfdi-preview" id="mfsPvContenido">
                    <div class="cfdi-header">
                        <div class="cfdi-header-left">
                            <img class="cfdi-logo" src="<?= base_url('assets/logos/logo_yazbek_01.png') ?>" alt="Logo" onerror="this.style.display='none'">
                            <div class="cfdi-emp-name" id="mfsPvEmpNombre"></div>
                            <div class="cfdi-emp-data">
                                <b>RFC:</b> <span id="mfsPvEmpRfc"></span><br>
                                <span id="mfsPvEmpDireccion"></span><br>
                                <span id="mfsPvEmpCiudad"></span>
                            </div>
                        </div>
                        <div class="cfdi-header-right">
                            <div class="cfdi-tipo-box">TIPO DE COMPROBANTE (I) FACTURA — VISTA PREVIA</div>
                            <table class="cfdi-tipo-tabla">
                                <tr><td class="lbl">Folio</td><td id="mfsPvFolioNum2"></td></tr>
                                <tr><td class="lbl">Folio Fiscal</td><td class="cfdi-pend">se asigna al timbrar</td></tr>
                                <tr><td class="lbl">No. Serie CSD Emisor</td><td class="cfdi-pend">se asigna al timbrar</td></tr>
                                <tr><td class="lbl">No. Serie CSD SAT</td><td class="cfdi-pend">se asigna al timbrar</td></tr>
                                <tr><td class="lbl">Fecha emisión</td><td id="mfsPvFecha"></td></tr>
                                <tr><td class="lbl">Fecha certificación</td><td class="cfdi-pend">se asigna al timbrar</td></tr>
                                <tr><td class="lbl">Lugar Expedición</td><td id="mfsPvLugarExp"></td></tr>
                                <tr><td class="lbl">Forma Pago</td><td id="mfsPvFormaPago"></td></tr>
                                <tr><td class="lbl">Moneda</td><td>MXN</td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="cfdi-sec-title">DATOS DEL CLIENTE</div>
                    <div class="cfdi-cliente-box">
                        <b id="mfsPvRazonSocial"></b><br>
                        <b>RFC:</b> <span id="mfsPvRfc"></span>
                        &nbsp;&nbsp; <b>CP Fiscal:</b> <span id="mfsPvCP"></span><br>
                        <b>Régimen fiscal del receptor:</b> <span id="mfsPvRegimenReceptor"></span><br>
                        <b>Uso CFDI:</b> <span id="mfsPvUsoCFDI"></span>
                    </div>

                    <div class="cfdi-regimen-bar">
                        <b>Régimen Fiscal del Emisor:</b> <span id="mfsPvRegimenEmisor"></span>
                    </div>

                    <div id="mfsPvObservacionesBox" class="cfdi-cliente-box d-none">
                        <b>Observaciones:</b> <span id="mfsPvObservaciones"></span>
                    </div>

                    <table class="cfdi-conc">
                        <thead>
                            <tr>
                                <th style="width:8%">CANTIDAD</th>
                                <th style="width:30%">DESCRIPCIÓN</th>
                                <th style="width:11%" class="r">P. UNITARIO</th>
                                <th style="width:10%" class="r">DESCUENTO</th>
                                <th style="width:11%" class="r">IMPORTE</th>
                                <th style="width:10%" class="r">IVA</th>
                                <th style="width:9%" class="r">IEPS</th>
                                <th style="width:9%" class="r">IMP.EST.</th>
                                <th style="width:11%" class="r">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody id="mfsPvConceptos"></tbody>
                    </table>

                    <table style="width:100%; margin-top:6px;">
                        <tr>
                            <td style="width:55%; vertical-align:top; font-size:7pt;" class="cfdi-pend">
                                RFC PAC: se asigna al timbrar<br>Exportación: 01
                            </td>
                            <td style="width:45%; vertical-align:top;">
                                <table class="cfdi-tot" style="margin:0 0 0 auto;">
                                    <tr><td>Subtotal</td><td class="r" id="mfsPvSubtotal"></td></tr>
                                    <tr id="mfsPvDescuentoRow" style="display:none;"><td>Descuento</td><td class="r" id="mfsPvDescuento"></td></tr>
                                    <tr><td>Total IVA</td><td class="r" id="mfsPvIva"></td></tr>
                                    <tr class="cfdi-tot-final"><td>TOTAL</td><td class="r" id="mfsPvTotal"></td></tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <div class="cfdi-sbox">Sellos digitales, folio fiscal (UUID) y código QR se generan al confirmar el timbrado.</div>
                    <div class="cfdi-pending-note">Este es un cálculo preliminar hecho con los datos capturados; no tiene validez fiscal hasta confirmar y timbrar.</div>
                </div>
                <div id="mfsPvError" class="alert alert-danger d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnMfsPvDescargar">
                    <i class="simple-icon-cloud-download mr-1"></i> Descargar Vista Previa
                </button>
                <button type="button" class="btn btn-secondary" id="btnMfsPvRegresar">Regresar</button>
                <button type="button" class="btn btn-success" id="btnMfsPvConfirmar">
                    <i class="simple-icon-check mr-1"></i> Confirmar y Facturar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ Modal: Factura Global ══════════════════════════════════ -->
<div class="modal fade" id="modalFacturaGlobal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="iconsminds-receipt-4 mr-2"></i>Factura Global — Público en General</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 mb-3">
                    <i class="simple-icon-info mr-1"></i>
                    Se generará <strong>una sola factura por folio</strong> usando los datos de <em>Público en General</em> para todos los seleccionados (o todos los pendientes con los filtros actuales).
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>RFC Receptor</label>
                            <input type="text" id="fgRfc" class="form-control text-uppercase" value="XAXX010101000" maxlength="13">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Razón Social</label>
                            <input type="text" id="fgRazonSocial" class="form-control text-uppercase" value="PUBLICO EN GENERAL" maxlength="200">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>CP Fiscal <span class="text-danger">*</span></label>
                            <input type="text" id="fgCP" class="form-control" maxlength="5" placeholder="00000">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Régimen Fiscal</label>
                            <select id="fgRegimenFiscal" class="form-control">
                                <option value="616" selected>616 – Sin obligaciones fiscales</option>
                                <option value="601">601 – General de Ley Personas Morales</option>
                                <option value="612">612 – Personas Físicas con Actividades Empresariales</option>
                                <option value="626">626 – RESICO</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Uso del CFDI</label>
                            <select id="fgUsoCFDI" class="form-control">
                                <option value="S01" selected>S01 – Sin efectos fiscales</option>
                                <option value="G03">G03 – Gastos en general</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Forma de Pago</label>
                            <select id="fgFormaPago" class="form-control">
                                <option value="01" selected>01 – Efectivo</option>
                                <option value="03">03 – Transferencia electrónica</option>
                                <option value="04">04 – Tarjeta de crédito</option>
                                <option value="28">28 – Tarjeta de débito</option>
                                <option value="99">99 – Por definir</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Método de Pago</label>
                            <select id="fgMetodoPago" class="form-control">
                                <option value="PUE" selected>PUE – Pago en una sola exhibición</option>
                                <option value="PPD">PPD – Parcialidades o diferido</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="fgProgreso" class="d-none mt-2">
                    <div class="progress" style="height:10px;border-radius:5px;">
                        <div id="fgProgresoBar" class="progress-bar bg-success" style="width:0%"></div>
                    </div>
                    <small id="fgProgresoTxt" class="text-muted mt-1 d-block text-center"></small>
                </div>
                <div id="fgResultados" class="mt-2 d-none">
                    <div id="fgResultadoOk"  class="alert alert-success py-2 mb-1 d-none"></div>
                    <div id="fgResultadoErr" class="alert alert-danger  py-2 mb-1 d-none"></div>
                </div>
                <div id="fgError" class="alert alert-danger d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnFgFacturar">
                    <i class="iconsminds-receipt-4 mr-1"></i> Generar Factura Global
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ Modal: Factura Consolidada (un solo CFDI para todos los folios del cliente) ══ -->
<div class="modal fade" id="modalConsolidada" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a4a80;">
                <h5 class="modal-title text-white"><i class="simple-icon-doc mr-2"></i>Factura Consolidada — <span id="mcTitulo"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="mcInfoFolios" class="alert alert-info py-2 mb-3"></div>
                <p class="text-muted small mb-3">Se generará <strong>un solo CFDI</strong> con todos los productos de los folios pendientes. Verifica los datos fiscales del receptor.</p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>RFC Receptor <span class="text-danger">*</span></label>
                            <input type="text" id="mcRfc" class="form-control text-uppercase" maxlength="13" placeholder="XAXX010101000">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Razón Social / Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="mcRazonSocial" class="form-control text-uppercase" maxlength="200">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>CP Fiscal <span class="text-danger">*</span></label>
                            <input type="text" id="mcCP" class="form-control" maxlength="5" placeholder="72270">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Régimen Fiscal</label>
                            <select id="mcRegimenFiscal" class="form-control">
                                <option value="616" selected>616 – Sin obligaciones fiscales</option>
                                <option value="601">601 – General de Ley Personas Morales</option>
                                <option value="612">612 – Personas Físicas con Actividades Empresariales</option>
                                <option value="621">621 – Incorporación Fiscal</option>
                                <option value="626">626 – RESICO</option>
                                <option value="605">605 – Sueldos y Salarios</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Uso del CFDI</label>
                            <select id="mcUsoCFDI" class="form-control">
                                <option value="S01" selected>S01 – Sin efectos fiscales</option>
                                <option value="G01">G01 – Adquisición de mercancias</option>
                                <option value="G03">G03 – Gastos en general</option>
                                <option value="I01">I01 – Construcciones</option>
                                <option value="CP01">CP01 – Pagos</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Forma de Pago</label>
                            <select id="mcFormaPago" class="form-control">
                                <option value="01" selected>01 – Efectivo</option>
                                <option value="02">02 – Cheque nominativo</option>
                                <option value="03">03 – Transferencia electrónica</option>
                                <option value="04">04 – Tarjeta de crédito</option>
                                <option value="28">28 – Tarjeta de débito</option>
                                <option value="99">99 – Por definir</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Método de Pago</label>
                            <select id="mcMetodoPago" class="form-control">
                                <option value="PUE" selected>PUE – Pago en una sola exhibición</option>
                                <option value="PPD">PPD – Parcialidades o diferido</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="mcProgreso" class="d-none mt-2">
                    <div class="progress" style="height:12px;border-radius:6px;">
                        <div id="mcProgresoBar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width:0%"></div>
                    </div>
                    <small id="mcProgresoTxt" class="text-muted mt-1 d-block text-center">Generando factura consolidada…</small>
                </div>
                <div id="mcResultado" class="mt-2 d-none"></div>
                <div id="mcError" class="alert alert-danger d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnMcFacturar">
                    <i class="simple-icon-doc mr-1"></i> Generar Factura Consolidada
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script src="<?= base_url('assets/js/vendor/select2.full.js') ?>"></script>
<script>
/* ── Select2 de cliente ───────────────────────────────────── */
(function initSelect2Cliente() {
    if (typeof $.fn.select2 === 'undefined') { setTimeout(initSelect2Cliente, 100); return; }
    $('#filtCliente').select2({
        theme            : 'bootstrap',
        placeholder      : '— Todos los clientes —',
        allowClear       : true,
        width            : '100%',
        minimumInputLength: 1,
        language: {
            inputTooShort : function () { return 'Escribe al menos 1 carácter...'; },
            searching     : function () { return 'Buscando...'; },
            noResults     : function () { return 'No se encontraron clientes.'; }
        },
        ajax: {
            url      : '<?= base_url(($factUrlPrefix ?? 'admin') . '/clientes/buscar') ?>',
            type     : 'POST',
            dataType : 'json',
            delay    : 300,
            data: function (params) {
                return { termino: params.term, '<?= csrf_token() ?>': '<?= csrf_hash() ?>' };
            },
            processResults: function (data) {
                return {
                    results: $.map(data, function (c) {
                        return { id: c.id, text: (c.RFC ? c.RFC + ' | ' : '') + c.nombre };
                    })
                };
            },
            cache: true
        }
    });
})();

/* ── Constantes globales ───────────────────────────────────── */
var FACT_BASE   = '<?= base_url(($factUrlPrefix ?? 'admin') . '/') ?>';
var FACT_CSRF_N = '<?= csrf_token() ?>';
var FACT_CSRF_H = '<?= csrf_hash() ?>';

/* Estado global */
var factModoMasivo  = false;
var factFolioIndiv  = null;
var factFoliosLista = [];
var _mfsDatosFiscales = null; // datos capturados en el paso 1, usados por el paso 2 (vista previa)
var factDt          = null;
var factClienteSeleccionadoId     = null;
var factClienteSeleccionadoNombre = '';

/*
 * Selección persistente entre páginas del DataTable.
 * Clave: folio (int) → Valor: { folio, cliente }
 */
var factSeleccion = {};       // { 1008040: { folio:1008040, cliente:'EDMUNDO...' } }
var factDeseleccionados = {}; // folios que el usuario desmarcó manualmente (no re-marcar)

/* ── DataTable ────────────────────────────────────────────── */
$(document).ready(function () {

    factDt = $('#tablaFacturacion').DataTable({
        processing : true,
        serverSide : true,
        ajax: {
            url  : FACT_BASE + 'facturacion/datatable',
            type : 'GET',
            data : function (d) {
                d.clienteId          = $('#filtCliente').val() || '';
                d.folio              = $('#filtFolio').val();
                d.desde              = $('#filtDesde').val();
                d.hasta              = $('#filtHasta').val();
                d.estado             = $('#filtEstado').val();
                d.ocultarFacturados  = factClienteSeleccionadoId ? '1' : '0';
                return d;
            }
        },
        columns: [
            {
                data      : null,
                orderable : false,
                searchable: false,
                render    : function (data, type, row) {
                    var uid = (row.uuid_fiscal || '').trim();
                    if (uid !== '') {
                        return '<i class="simple-icon-check text-success" title="Ya facturado"></i>';
                    }
                    var checked = factSeleccion[row.folio] ? ' checked' : '';
                    return '<input type="checkbox" class="chk-folio"'
                         + ' data-folio="' + row.folio + '"'
                         + ' data-cliente="' + row.cliente.replace(/"/g,'&quot;') + '"'
                         + ' data-clienteid="' + (row.idCliente || 0) + '"'
                         + checked + '>';
                }
            },
            { data: 'folio' },
            { data: 'fecha_inicial', render: function (d) { return d ? d.substr(0, 10) : ''; } },
            { data: 'cliente' },
            { data: 'vendedor' },
            {
                data     : 'total',
                className: 'text-right',
                render   : function (d) { return '$' + parseFloat(d || 0).toFixed(2); }
            },
            {
                data  : null,
                render: function (data, type, row) {
                    var uid = (row.uuid_fiscal || '').trim();
                    var sf  = parseInt(row.status_facturacion || 0, 10);
                    if (uid !== '') {
                        return '<span class="badge-facturado">Facturado ✓</span>'
                             + '<br><small class="text-muted" style="font-size:.65rem">' + uid.substr(0,20) + '…</small>';
                    }
                    if (sf === 1) return '<span class="badge-pendiente">Pend. SAT</span>';
                    return '<span class="badge-nofact">No Facturado</span>';
                }
            },
            {
                data      : null,
                orderable : false,
                searchable: false,
                render    : function (data, type, row) {
                    var uid = (row.uuid_fiscal || '').trim();
                    if (uid !== '') return '<span class="text-muted small">—</span>';
                    return '<button class="btn btn-sm btn-outline-primary py-0" style="font-size:.78rem" '
                         + 'onclick="factAbrirModalIndividual(' + row.folio + ')">'
                         + '<i class="simple-icon-doc mr-1"></i>Facturar</button>';
                }
            }
        ],
        order      : [[1, 'desc']],
        pageLength : 25,
        drawCallback: function (settings) {
            var total = settings.fnRecordsTotal();
            $('#totalFacturacion').text(total.toLocaleString());

            // Actualizar DOM: reflejar factSeleccion en los checkboxes visibles
            $('#tablaFacturacion .chk-folio').each(function () {
                var folio = parseInt($(this).data('folio'), 10);
                $(this).prop('checked', !!factSeleccion[folio]);
            });

            factActualizarSeleccion();
            $('#chkFactAll').prop('checked', false);
        },
        language: { url: '<?= base_url('assets/js/vendor/datatables.spanish.json') ?>' }
    });

    /* ── Filtrar al pulsar Buscar o Enter ── */
    $('#btnFiltrar').on('click', function () {
        factSeleccion = {};   // limpiar selección al buscar de nuevo
        factDt.ajax.reload(null, false);
        factCargarResumen();
    });
    $('#filtCliente,#filtFolio,#filtDesde,#filtHasta').on('keypress', function (e) {
        if (e.which === 13) $('#btnFiltrar').click();
    });

    /* ── Select-all visible ── */
    $('#chkFactAll').on('change', function () {
        var marcar = this.checked;
        $('#tablaFacturacion .chk-folio').each(function () {
            var folio      = parseInt($(this).data('folio'), 10);
            var cliente    = $(this).data('cliente') || '';
            var clienteId  = parseInt($(this).data('clienteid'), 10) || 0;
            if (marcar) {
                // Validar mismo cliente por ID
                var idActual = factObtenerClienteIdSeleccionado();
                if (idActual && idActual !== clienteId) {
                    // No agregar — cliente distinto
                    return;
                }
                factSeleccion[folio] = { folio: folio, cliente: cliente, clienteId: clienteId };
                $(this).prop('checked', true);
            } else {
                delete factSeleccion[folio];
                $(this).prop('checked', false);
            }
        });
        factActualizarSeleccion();
    });

    /* ── Checkbox individual con validación de cliente ── */
    $('#tablaFacturacion tbody').on('change', '.chk-folio', function () {
        var folio      = parseInt($(this).data('folio'), 10);
        var cliente    = $(this).data('cliente') || '';
        var clienteId  = parseInt($(this).data('clienteid'), 10) || 0;

        if (this.checked) {
            var idActual      = factObtenerClienteIdSeleccionado();
            var nombreActual  = factObtenerClienteSeleccionado();
            if (idActual && idActual !== clienteId) {
                $(this).prop('checked', false);
                alert('⚠️ No puedes mezclar clientes.\n\nYa tienes notas de "' + (nombreActual || idActual) + '" seleccionadas.\nSolo puedes facturar notas del mismo cliente a la vez.');
                return;
            }
            factSeleccion[folio] = { folio: folio, cliente: cliente, clienteId: clienteId };
            delete factDeseleccionados[folio]; // el usuario lo re-marcó → ya no está deseleccionado
        } else {
            delete factSeleccion[folio];
            if (factClienteSeleccionadoId) {
                factDeseleccionados[folio] = true; // marcado manualmente → no re-marcar al paginar
            }
        }
        factActualizarSeleccion();
    });

    /* ── Botón Facturar del modal SAT ── */
    document.getElementById('btnMfsFacturar').addEventListener('click', function () {
        var btn = this;
        var rfc = document.getElementById('mfsRfc').value.trim().toUpperCase();
        var rs  = document.getElementById('mfsRazonSocial').value.trim().toUpperCase();
        var cp  = document.getElementById('mfsCP').value.trim();
        var err = document.getElementById('mfsError');

        err.classList.add('d-none');
        if (!rfc) { err.textContent = 'El RFC es requerido.';           err.classList.remove('d-none'); return; }
        if (!rs)  { err.textContent = 'La Razón Social es requerida.';  err.classList.remove('d-none'); return; }
        if (!cp)  { err.textContent = 'El Código Postal es requerido.'; err.classList.remove('d-none'); return; }

        var datosSAT = {
            rfcReceptor          : rfc,
            razonSocialReceptor  : rs,
            cpReceptor           : cp,
            usoCFDI              : document.getElementById('mfsUsoCFDI').value,
            regimenFiscalReceptor: document.getElementById('mfsRegimenFiscal').value,
            formaPagoCFDI        : document.getElementById('mfsFormaPago').value,
            metodoPagoCFDI       : document.getElementById('mfsMetodoPago').value,
            observacionesFactura : document.getElementById('mfsObservaciones').value.trim()
        };

        btn.disabled = true;
        btn.textContent = 'Procesando…';

        if (!factModoMasivo) {
            // Individual → paso 2: pedir la vista previa antes de timbrar
            _mfsDatosFiscales = datosSAT;
            var fd = factMkFd(datosSAT);
            fetch(FACT_BASE + 'folio/' + factFolioIndiv + '/previsualizar-factura', {
                method: 'POST', body: fd, credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled  = false;
                btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
                if (!data.success) {
                    err.textContent = data.message || 'Error al calcular la vista previa.';
                    err.classList.remove('d-none');
                    return;
                }
                mfsMostrarPreviewFactura(data);
            })
            .catch(function () {
                btn.disabled  = false;
                btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
                err.textContent = 'Error de conexión.';
                err.classList.remove('d-none');
            });
        } else {
            // Masivo
            factProcesarMasivo(factFoliosLista, datosSAT, btn,
                'mfsProgreso', 'mfsProgresoBar', 'mfsProgresoTxt',
                'mfsResultados', 'mfsResultadoOk', 'mfsResultadoErr',
                function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';
                    factSeleccion = {};
                    setTimeout(function () {
                        $('#modalFacturacionSAT').modal('hide');
                        factDt.ajax.reload(null, false);
                        factCargarResumen();
                    }, 900);
                }
            );
        }
    });

    /* ── Vista previa (paso 2): regresar a editar datos fiscales ── */
    document.getElementById('btnMfsPvRegresar').addEventListener('click', function () {
        $('#modalMfsFacturaPreview').modal('hide');
        $('#modalFacturacionSAT').modal('show');
    });

    /* ── Vista previa (paso 2): confirmar y timbrar de verdad ── */
    document.getElementById('btnMfsPvConfirmar').addEventListener('click', function () {
        var btn = this;
        var err = document.getElementById('mfsPvError');
        err.classList.add('d-none');
        btn.disabled    = true;
        btn.textContent = 'Procesando…';

        var fd = factMkFd(_mfsDatosFiscales);
        fetch(FACT_BASE + 'folio/' + factFolioIndiv + '/facturar', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled  = false;
            btn.innerHTML = '<i class="simple-icon-check mr-1"></i> Confirmar y Facturar';
            if (data.success) {
                $('#modalMfsFacturaPreview').modal('hide');
                alert('✔ ' + (data.mensaje || 'Factura generada correctamente.'));
                factDt.ajax.reload(null, false);
                factCargarResumen();
            } else {
                err.textContent = data.mensaje || 'Error al generar la factura.';
                err.classList.remove('d-none');
            }
        })
        .catch(function () {
            btn.disabled  = false;
            btn.innerHTML = '<i class="simple-icon-check mr-1"></i> Confirmar y Facturar';
            err.textContent = 'Error de conexión.';
            err.classList.remove('d-none');
        });
    });

    /* ── Vista previa (paso 2): descargar como PDF (imprimir) ── */
    document.getElementById('btnMfsPvDescargar').addEventListener('click', function () {
        var contenido = document.getElementById('mfsPvContenido').innerHTML;
        var folioTxt  = document.getElementById('mfsPvFolioNum').textContent;
        var w = window.open('', '_blank');
        w.document.write(
            '<!DOCTYPE html><html><head><title>Vista previa factura ' + folioTxt + '</title>' +
            '<style>body{font-family:Arial,sans-serif;margin:20px;}' + mfsEstilosPreview() + '</style>' +
            '</head><body>' + contenido + '</body></html>'
        );
        w.document.close();
        w.onload = function () { w.focus(); w.print(); };
    });

    /* ── Botón Generar Factura Global ── */
    document.getElementById('btnFgFacturar').addEventListener('click', function () {
        var btn = this;
        var cp  = document.getElementById('fgCP').value.trim();
        var err = document.getElementById('fgError');
        err.classList.add('d-none');
        if (!cp) { err.textContent = 'El Código Postal es requerido.'; err.classList.remove('d-none'); return; }

        var datosSAT = {
            rfcReceptor          : document.getElementById('fgRfc').value.trim().toUpperCase(),
            razonSocialReceptor  : document.getElementById('fgRazonSocial').value.trim().toUpperCase(),
            cpReceptor           : cp,
            usoCFDI              : document.getElementById('fgUsoCFDI').value,
            regimenFiscalReceptor: document.getElementById('fgRegimenFiscal').value,
            formaPagoCFDI        : document.getElementById('fgFormaPago').value,
            metodoPagoCFDI       : document.getElementById('fgMetodoPago').value
        };

        btn.disabled = true;
        btn.textContent = 'Procesando…';

        if (factFoliosLista.length > 0) {
            factEjecutarGlobal(factFoliosLista, datosSAT, btn, err);
        } else {
            // Sin selección manual: pedir al backend los pendientes con filtros actuales
            var params = new URLSearchParams({
                clienteId: $('#filtCliente').val() || '',
                desde    : $('#filtDesde').val(),
                hasta    : $('#filtHasta').val()
            });
            fetch(FACT_BASE + 'facturacion/folios-pendientes?' + params.toString(), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.folios || d.folios.length === 0) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="iconsminds-receipt-4 mr-1"></i> Generar Factura Global';
                        err.textContent = 'No hay folios pendientes con los filtros actuales.';
                        err.classList.remove('d-none');
                        return;
                    }
                    factEjecutarGlobal(d.folios, datosSAT, btn, err);
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="iconsminds-receipt-4 mr-1"></i> Generar Factura Global';
                    err.textContent = 'Error al obtener folios pendientes.';
                    err.classList.remove('d-none');
                });
        }
    });

    /* ── Select2 cliente → mostrar/ocultar panel consolidado ── */
    $('#filtCliente').on('select2:select', function (e) {
        var data = e.params.data;
        factClienteSeleccionadoId     = data.id;
        factClienteSeleccionadoNombre = data.text;
        document.getElementById('mcTitulo').textContent = data.text;

        var infoEl = document.getElementById('panelConsolidadaInfo');
        infoEl.innerHTML = '<i class="simple-icon-user mr-1"></i><strong>' + data.text + '</strong>'
            + ' — Se unirán todos sus folios pendientes en <strong>un solo CFDI</strong>.';
        infoEl.classList.remove('d-none');

        document.getElementById('btnGenerarConsolidada').classList.remove('d-none');
        document.getElementById('btnRestablecerCliente').classList.remove('d-none');

        // Traer TODOS los folios pendientes del cliente, pre-cargar factSeleccion, luego recargar tabla
        factSeleccion       = {};
        factDeseleccionados = {};
        var pendParams = new URLSearchParams({
            clienteId: data.id,
            desde: $('#filtDesde').val() || '',
            hasta: $('#filtHasta').val() || ''
        });
        fetch(FACT_BASE + 'facturacion/folios-pendientes?' + pendParams.toString(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { folios: [] }; })
            .then(function (resp) {
                (resp.folios || []).forEach(function (f) {
                    factSeleccion[f] = { folio: f, cliente: data.text, clienteId: parseInt(data.id, 10) || 0 };
                });
                factDt.ajax.reload(null, false);
            });

        // Pre-cargar datos fiscales del cliente
        fetch(FACT_BASE + 'clientes/' + data.id + '/datos-fiscales-cliente', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return {}; })
            .then(function (d) {
                document.getElementById('mcRfc').value           = d.RFC           || '';
                document.getElementById('mcRazonSocial').value   = d.razonSocial   || d.nombre || '';
                document.getElementById('mcCP').value            = d.CP            || '';
                document.getElementById('mcRegimenFiscal').value = d.regimenFiscal || '616';
                document.getElementById('mcUsoCFDI').value       = 'S01';
                document.getElementById('mcFormaPago').value     = '01';
                document.getElementById('mcMetodoPago').value    = 'PUE';
            });
    });
    $('#filtCliente').on('select2:unselect select2:clear', function () {
        factRestablecerCliente();
    });

    /* ── Botón Generar Factura Consolidada ── */
    document.getElementById('btnMcFacturar').addEventListener('click', function () {
        var btn = this;
        var rfc = document.getElementById('mcRfc').value.trim().toUpperCase();
        var rs  = document.getElementById('mcRazonSocial').value.trim().toUpperCase();
        var cp  = document.getElementById('mcCP').value.trim();
        var err = document.getElementById('mcError');

        err.classList.add('d-none');
        if (!rfc) { err.textContent = 'El RFC es requerido.';           err.classList.remove('d-none'); return; }
        if (!rs)  { err.textContent = 'La Razón Social es requerida.';  err.classList.remove('d-none'); return; }
        if (!cp)  { err.textContent = 'El Código Postal es requerido.'; err.classList.remove('d-none'); return; }
        if (!factClienteSeleccionadoId) { err.textContent = 'Selecciona un cliente primero.'; err.classList.remove('d-none'); return; }

        // Usar exactamente los folios que el usuario dejó marcados en factSeleccion
        var foliosSeleccionados = Object.keys(factSeleccion).map(function (k) { return parseInt(k, 10); });
        if (foliosSeleccionados.length === 0) {
            err.textContent = 'No hay folios seleccionados. Marca al menos uno en la tabla.';
            err.classList.remove('d-none');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Procesando…';
        document.getElementById('mcProgreso').classList.remove('d-none');
        document.getElementById('mcProgresoBar').style.width = '30%';
        document.getElementById('mcResultado').classList.add('d-none');

        (function (folios) {
                document.getElementById('mcProgresoBar').style.width = '60%';
                document.getElementById('mcProgresoTxt').textContent = 'Timbrando ' + folios.length + ' folio(s)…';
                var d = { folios: folios };

                var datosSAT = {
                    rfcReceptor          : rfc,
                    razonSocialReceptor  : rs,
                    cpReceptor           : cp,
                    usoCFDI              : document.getElementById('mcUsoCFDI').value,
                    regimenFiscalReceptor: document.getElementById('mcRegimenFiscal').value,
                    formaPagoCFDI        : document.getElementById('mcFormaPago').value,
                    metodoPagoCFDI       : document.getElementById('mcMetodoPago').value
                };

                var fd = factMkFd(datosSAT);
                fd.append('folios', JSON.stringify(d.folios));

                fetch(FACT_BASE + 'facturacion/consolidada', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    document.getElementById('mcProgresoBar').style.width = '100%';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Generar Factura Consolidada';
                    if (res.success) {
                        var resEl = document.getElementById('mcResultado');
                        resEl.className = 'alert alert-success mt-2';
                        resEl.textContent = '✔ Factura consolidada generada. ' + res.folios + ' folio(s) timbrados. UUID: ' + res.uuid;
                        resEl.classList.remove('d-none');
                        setTimeout(function () {
                            $('#modalConsolidada').modal('hide');
                            factDt.ajax.reload(null, false);
                            factCargarResumen();
                        }, 1200);
                    } else {
                        document.getElementById('mcProgreso').classList.add('d-none');
                        err.textContent = res.message || 'Error al generar factura consolidada.';
                        err.classList.remove('d-none');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="simple-icon-doc mr-1"></i> Generar Factura Consolidada';
                    document.getElementById('mcProgreso').classList.add('d-none');
                    err.textContent = 'Error de conexión.';
                    err.classList.remove('d-none');
                });
        }(foliosSeleccionados));
    });

    // Cargar resumen al iniciar
    factCargarResumen();
});

/* ══════════════════════════════════════════════════════════════
   Funciones globales (llamadas desde onclick en celdas)
══════════════════════════════════════════════════════════════ */

function factAbrirModalIndividual(folio) {
    factModoMasivo  = false;
    factFolioIndiv  = folio;
    factFoliosLista = [folio];

    factResetModalSAT();
    document.getElementById('mfsTitulo').textContent = 'Folio #' + folio;
    document.getElementById('btnMfsFacturar').innerHTML = '<i class="simple-icon-doc mr-1"></i> Facturar';

    $('#modalFacturacionSAT').modal('show');

    fetch(FACT_BASE + 'folio/' + folio + '/datos-fiscales', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            document.getElementById('mfsRfc').value           = d.rfcReceptor           || '';
            document.getElementById('mfsRazonSocial').value   = d.razonSocialReceptor   || '';
            document.getElementById('mfsCP').value            = d.cpReceptor            || '';
            document.getElementById('mfsUsoCFDI').value       = d.usoCFDI               || 'S01';
            document.getElementById('mfsRegimenFiscal').value = d.regimenFiscalReceptor || '616';
            document.getElementById('mfsFormaPago').value     = d.formaPagoCFDI         || '01';
            document.getElementById('mfsMetodoPago').value    = 'PUE';
            factCheckCorreoModal(d.idCliente || 0, d.correoCliente || '');
        })
        .catch(function () {});
}

function factAbrirModalSeleccionados() {
    var folios = Object.keys(factSeleccion).map(function (k) { return parseInt(k, 10); });
    if (folios.length === 0) { alert('No has seleccionado ningún folio.'); return; }

    factModoMasivo  = true;
    factFolioIndiv  = null;
    factFoliosLista = folios;

    factResetModalSAT();
    document.getElementById('mfsTitulo').textContent = folios.length + ' folio(s) seleccionado(s)';
    document.getElementById('btnMfsFacturar').innerHTML =
        '<i class="simple-icon-doc mr-1"></i> Facturar ' + folios.length + ' folio(s)';

    // Pre-cargar datos fiscales del primer folio seleccionado
    fetch(FACT_BASE + 'folio/' + folios[0] + '/datos-fiscales', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            document.getElementById('mfsRfc').value           = d.rfcReceptor           || '';
            document.getElementById('mfsRazonSocial').value   = d.razonSocialReceptor   || '';
            document.getElementById('mfsCP').value            = d.cpReceptor            || '';
            document.getElementById('mfsUsoCFDI').value       = d.usoCFDI               || 'S01';
            document.getElementById('mfsRegimenFiscal').value = d.regimenFiscalReceptor || '616';
            document.getElementById('mfsFormaPago').value     = d.formaPagoCFDI         || '01';
            document.getElementById('mfsMetodoPago').value    = 'PUE';
            factCheckCorreoModal(d.idCliente || 0, d.correoCliente || '');
        })
        .catch(function () {});

    $('#modalFacturacionSAT').modal('show');
}

function factAbrirModalGlobal() {
    var folios = Object.keys(factSeleccion).map(function (k) { return parseInt(k, 10); });
    factFoliosLista = folios;

    document.getElementById('fgError').classList.add('d-none');
    document.getElementById('fgProgreso').classList.add('d-none');
    document.getElementById('fgResultados').classList.add('d-none');
    document.getElementById('fgResultadoOk').classList.add('d-none');
    document.getElementById('fgResultadoErr').classList.add('d-none');
    document.getElementById('fgProgresoBar').style.width = '0%';
    document.getElementById('btnFgFacturar').disabled = false;
    document.getElementById('btnFgFacturar').innerHTML = '<i class="iconsminds-receipt-4 mr-1"></i> Generar Factura Global';

    $('#modalFacturaGlobal').modal('show');
}

/* ── Helpers ──────────────────────────────────────────────── */

function factResetModalSAT() {
    ['mfsRfc','mfsRazonSocial','mfsCP'].forEach(function (id) {
        document.getElementById(id).value = '';
    });
    document.getElementById('mfsRegimenFiscal').value = '616';
    document.getElementById('mfsUsoCFDI').value       = 'S01';
    document.getElementById('mfsFormaPago').value     = '01';
    document.getElementById('mfsMetodoPago').value    = 'PUE';
    document.getElementById('mfsObservaciones').value = '';
    document.getElementById('mfsError').classList.add('d-none');
    document.getElementById('mfsProgreso').classList.add('d-none');
    document.getElementById('mfsResultados').classList.add('d-none');
    document.getElementById('mfsResultadoOk').classList.add('d-none');
    document.getElementById('mfsResultadoErr').classList.add('d-none');
    document.getElementById('mfsProgresoBar').style.width = '0%';
    document.getElementById('btnMfsFacturar').disabled = false;
    // Reset correo alert
    document.getElementById('mfsCorreoAlert').classList.add('d-none');
    document.getElementById('mfsCorreoInput').value    = '';
    document.getElementById('mfsCorreoInput').disabled = false;
    document.getElementById('mfsCorreoOk').classList.add('d-none');
    document.getElementById('btnGuardarCorreo').disabled = false;
    document.getElementById('mfsClienteId').value = '';
}

function factMkFd(extra) {
    var fd = new FormData();
    fd.append(FACT_CSRF_N, FACT_CSRF_H);
    if (extra) {
        Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
    }
    return fd;
}

/* ── Vista previa de factura (paso 2, individual) ───────────── */
function mfsMoneda(n) {
    return '$' + (Number(n) || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function mfsEsc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function mfsFechaLocal() {
    var d = new Date();
    function pad(n) { return String(n).padStart(2, '0'); }
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
        'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
}

function mfsMostrarPreviewFactura(data) {
    document.getElementById('mfsPvFolioNum').textContent  = '#' + data.folio;
    document.getElementById('mfsPvFolioNum2').textContent = data.folio;
    document.getElementById('mfsPvFecha').textContent     = mfsFechaLocal() + ' (aprox.)';
    document.getElementById('mfsPvLugarExp').textContent  = data.emisor.cp;

    document.getElementById('mfsPvEmpNombre').textContent    = data.emisor.razonSocial;
    document.getElementById('mfsPvEmpRfc').textContent       = data.emisor.rfc;
    document.getElementById('mfsPvEmpDireccion').textContent = data.emisor.direccion;
    document.getElementById('mfsPvEmpCiudad').textContent    = data.emisor.ciudad;
    document.getElementById('mfsPvRegimenEmisor').textContent = data.emisor.regimen + ' / ' + data.emisor.regimenTexto;

    var mfsObsBox = document.getElementById('mfsPvObservacionesBox');
    if (data.observaciones) {
        document.getElementById('mfsPvObservaciones').textContent = data.observaciones;
        mfsObsBox.classList.remove('d-none');
    } else {
        mfsObsBox.classList.add('d-none');
    }

    document.getElementById('mfsPvRfc').textContent            = data.datosFiscales.rfcReceptor;
    document.getElementById('mfsPvRazonSocial').textContent    = data.datosFiscales.razonSocialReceptor;
    document.getElementById('mfsPvCP').textContent             = data.datosFiscales.cpReceptor;
    document.getElementById('mfsPvRegimenReceptor').textContent = data.datosFiscales.regimenFiscalReceptor;
    document.getElementById('mfsPvUsoCFDI').textContent        = data.datosFiscales.usoCFDI;
    document.getElementById('mfsPvFormaPago').textContent      = data.datosFiscales.formaPagoTexto + ' / ' + data.datosFiscales.metodoPago;

    var tbody = document.getElementById('mfsPvConceptos');
    tbody.innerHTML = '';
    data.conceptos.forEach(function (c) {
        var descuento = Number(c.descuento) || 0;
        var baseIva   = (Number(c.importe) || 0) - descuento;
        var total     = baseIva + (Number(c.iva) || 0);
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="c">' + c.cantidad + '</td>' +
            '<td>' + mfsEsc(c.sku ? (c.sku + ' ' + c.descripcion) : c.descripcion) + '</td>' +
            '<td class="r">' + mfsMoneda(c.valorUnitario) + '</td>' +
            '<td class="r">' + mfsMoneda(descuento) + '</td>' +
            '<td class="r">' + mfsMoneda(baseIva) + '</td>' +
            '<td class="r">' + mfsMoneda(c.iva) + '</td>' +
            '<td class="r"></td>' +
            '<td class="r"></td>' +
            '<td class="r">' + mfsMoneda(total) + '</td>';
        tbody.appendChild(tr);

        var trImp = document.createElement('tr');
        trImp.className = 'cfdi-imp-row';
        trImp.innerHTML = '<td colspan="9">Impuesto: ' + mfsMoneda(baseIva) + ' x [002{IVA} Tasa 0.160000] = ' + mfsMoneda(c.iva) + '</td>';
        tbody.appendChild(trImp);
    });

    document.getElementById('mfsPvSubtotal').textContent = mfsMoneda(data.subtotal);
    var mfsDescuentoRow = document.getElementById('mfsPvDescuentoRow');
    if (mfsDescuentoRow) {
        if (Number(data.descuento) > 0) {
            mfsDescuentoRow.style.display = '';
            document.getElementById('mfsPvDescuento').textContent = '- ' + mfsMoneda(data.descuento);
        } else {
            mfsDescuentoRow.style.display = 'none';
        }
    }
    document.getElementById('mfsPvIva').textContent      = mfsMoneda(data.iva);
    document.getElementById('mfsPvTotal').textContent    = mfsMoneda(data.total);
    document.getElementById('mfsPvError').classList.add('d-none');

    $('#modalFacturacionSAT').modal('hide');
    $('#modalMfsFacturaPreview').modal('show');
}

/* CSS embebido para la ventana de impresión/descarga (no hereda los <style> de la página) */
function mfsEstilosPreview() {
    return '.cfdi-preview{font-size:9pt;color:#111}' +
        '.cfdi-preview table{width:100%;border-collapse:collapse}' +
        '.cfdi-preview .r{text-align:right}.cfdi-preview .c{text-align:center}' +
        '.cfdi-header{display:flex;border:1px solid #888;margin-bottom:6px}' +
        '.cfdi-header-left{width:65%;padding:8px 10px;border-right:1px solid #888}' +
        '.cfdi-header-right{width:35%}' +
        '.cfdi-emp-name{font-size:13pt;font-weight:bold;color:#1a3a5c;margin-bottom:3px}' +
        '.cfdi-emp-data{font-size:8pt;line-height:1.6}' +
        '.cfdi-tipo-box{background:#1a3a5c;color:#fff;font-weight:bold;font-size:8.5pt;text-align:center;padding:5px 6px}' +
        '.cfdi-tipo-tabla td{font-size:8pt;padding:3px 6px;border-bottom:1px solid #ddd}' +
        '.cfdi-tipo-tabla .lbl{color:#1a3a5c;font-weight:bold;width:50%}' +
        '.cfdi-sec-title{background:#222;color:#fff;font-weight:bold;padding:4px 6px;font-size:8.5pt;margin:4px 0 2px}' +
        '.cfdi-cliente-box{border:1px solid #aaa;padding:6px 8px;font-size:8pt;margin-bottom:6px}' +
        '.cfdi-conc th{background:#222;color:#fff;padding:4px;font-size:7.5pt;text-align:center;border:1px solid #555}' +
        '.cfdi-conc td{border:1px solid #ddd;padding:3px 4px;font-size:8pt}' +
        '.cfdi-imp-row td{background:#f0f0f0;font-size:7pt;color:#555;padding:2px 6px;border-left:1px solid #ddd;border-right:1px solid #ddd}' +
        '.cfdi-tot{width:280px;margin-left:auto;margin-top:6px}' +
        '.cfdi-tot td{padding:3px 6px;font-size:8.5pt}' +
        '.cfdi-tot-final td{background:#1a3a5c;color:#fff;font-weight:bold;font-size:9.5pt}' +
        '.cfdi-pending-note{font-size:7.5pt;color:#888;margin-top:8px;font-style:italic}' +
        '.cfdi-logo{max-height:45px;max-width:150px;margin-bottom:6px}' +
        '.cfdi-regimen-bar{border:1px solid #888;padding:4px 8px;font-size:8pt;margin-bottom:6px}' +
        '.cfdi-pend{color:#aaa;font-style:italic}' +
        '.cfdi-sbox{background:#f7f7f7;border:1px dashed #ccc;padding:6px 8px;font-size:7.5pt;color:#999;margin-top:6px;text-align:center}';
}

function factActualizarSeleccion() {
    var n = Object.keys(factSeleccion).length;
    if (n > 0) {
        document.getElementById('numSeleccionados').textContent = n;
        document.getElementById('btnSelCount').textContent = n + ' nota(s) seleccionada(s)';
        document.getElementById('btnSelCount').style.display = 'inline';
        document.getElementById('btnFacturarSeleccionados').style.display = 'inline-block';
    } else {
        document.getElementById('btnSelCount').style.display = 'none';
        document.getElementById('btnFacturarSeleccionados').style.display = 'none';
    }
}

function factAbrirModalConsolidada() {
    if (!factClienteSeleccionadoId) { alert('Selecciona primero un cliente.'); return; }
    // Reset modal
    document.getElementById('mcError').classList.add('d-none');
    document.getElementById('mcProgreso').classList.add('d-none');
    document.getElementById('mcResultado').classList.add('d-none');
    document.getElementById('mcProgresoBar').style.width = '0%';
    document.getElementById('btnMcFacturar').disabled = false;
    document.getElementById('btnMcFacturar').innerHTML = '<i class="simple-icon-doc mr-1"></i> Generar Factura Consolidada';
    var nFolios = Object.keys(factSeleccion).length;
    document.getElementById('mcInfoFolios').textContent =
        'Cliente: ' + factClienteSeleccionadoNombre + ' — Se facturarán ' + nFolios + ' folio(s) seleccionado(s) en un solo CFDI.';
    $('#modalConsolidada').modal('show');
}

/* ── Verificar correo en modal ─────────────────────────────── */
function factCheckCorreoModal(idCliente, correo) {
    var alertEl = document.getElementById('mfsCorreoAlert');
    var okEl    = document.getElementById('mfsCorreoOk');
    var inputEl = document.getElementById('mfsCorreoInput');
    document.getElementById('mfsClienteId').value = idCliente;
    okEl.classList.add('d-none');
    inputEl.value = '';
    if (!correo && idCliente > 0) {
        alertEl.classList.remove('d-none');
    } else {
        alertEl.classList.add('d-none');
    }
}

document.getElementById('btnGuardarCorreo').addEventListener('click', function () {
    var idCliente = parseInt(document.getElementById('mfsClienteId').value, 10);
    var mail      = document.getElementById('mfsCorreoInput').value.trim();
    var okEl      = document.getElementById('mfsCorreoOk');
    var btn       = this;
    if (!mail || !idCliente) return;

    btn.disabled = true;
    var fd = new FormData();
    fd.append(FACT_CSRF_N, FACT_CSRF_H);
    fd.append('mail', mail);
    fetch(FACT_BASE + 'clientes/' + idCliente + '/guardar-correo', {
        method: 'POST', body: fd, credentials: 'same-origin'
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        btn.disabled = false;
        if (res.success) {
            okEl.classList.remove('d-none');
            document.getElementById('mfsCorreoInput').disabled = true;
            btn.disabled = true;
        }
    })
    .catch(function () { btn.disabled = false; });
});

function factRestablecerCliente() {
    factClienteSeleccionadoId     = null;
    factClienteSeleccionadoNombre = '';
    $('#filtCliente').val(null).trigger('change');

    var infoEl = document.getElementById('panelConsolidadaInfo');
    if (infoEl) infoEl.classList.add('d-none');

    var btnGen = document.getElementById('btnGenerarConsolidada');
    if (btnGen) btnGen.classList.add('d-none');

    var btnRes = document.getElementById('btnRestablecerCliente');
    if (btnRes) btnRes.classList.add('d-none');

    // Compatibilidad: ocultar panel viejo si aún existe en DOM
    var panelViejo = document.getElementById('panelConsolidada');
    if (panelViejo) panelViejo.classList.add('d-none');

    factSeleccion       = {};
    factDeseleccionados = {};
    factActualizarSeleccion();
    factDt.ajax.reload(null, false);
}

/* Devuelve el nombre del cliente ya seleccionado (o null si no hay ninguno) */
function factObtenerClienteSeleccionado() {
    var keys = Object.keys(factSeleccion);
    if (keys.length === 0) return null;
    return factSeleccion[keys[0]].cliente;
}

/* Devuelve el ID numérico del cliente ya seleccionado (o null si no hay ninguno) */
function factObtenerClienteIdSeleccionado() {
    var keys = Object.keys(factSeleccion);
    if (keys.length === 0) return null;
    return factSeleccion[keys[0]].clienteId || null;
}

function factCargarResumen() {
    var params = new URLSearchParams({
        clienteId: $('#filtCliente').val() || '',
        folio    : $('#filtFolio').val(),
        desde    : $('#filtDesde').val(),
        hasta    : $('#filtHasta').val()
    });
    fetch(FACT_BASE + 'facturacion/resumen?' + params.toString(), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var fmt = function (n) {
                return '$' + parseFloat(n || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            };
            document.getElementById('resFacturadoMonto').textContent  = fmt(d.facturado_monto);
            document.getElementById('resFacturadoCount').textContent  = (d.facturado_count  || 0).toLocaleString() + ' notas';
            document.getElementById('resNoFacturadoMonto').textContent = fmt(d.no_facturado_monto);
            document.getElementById('resNoFacturadoCount').textContent = (d.no_facturado_count || 0).toLocaleString() + ' notas';
        })
        .catch(function () {});
}

function factEjecutarGlobal(folios, datosSAT, btn, errEl) {
    factProcesarMasivo(folios, datosSAT, btn,
        'fgProgreso', 'fgProgresoBar', 'fgProgresoTxt',
        'fgResultados', 'fgResultadoOk', 'fgResultadoErr',
        function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="iconsminds-receipt-4 mr-1"></i> Generar Factura Global';
            factSeleccion = {};
            setTimeout(function () {
                $('#modalFacturaGlobal').modal('hide');
                factDt.ajax.reload(null, false);
                factCargarResumen();
            }, 900);
        }
    );
}

function factProcesarMasivo(folios, datosSAT, btn,
    idProgreso, idBar, idTxt, idResultados, idOk, idErr, onDone)
{
    var total   = folios.length;
    var exitos  = 0;
    var errores = [];
    var idx     = 0;

    document.getElementById(idProgreso).classList.remove('d-none');
    document.getElementById(idResultados).classList.remove('d-none');

    function siguiente() {
        if (idx >= total) {
            document.getElementById(idBar).style.width = '100%';
            document.getElementById(idTxt).textContent =
                'Completado: ' + exitos + ' ok, ' + errores.length + ' con error.';
            if (exitos > 0) {
                document.getElementById(idOk).textContent =
                    '✔ ' + exitos + ' folio(s) facturado(s) correctamente.';
                document.getElementById(idOk).classList.remove('d-none');
            }
            if (errores.length > 0) {
                document.getElementById(idErr).textContent =
                    '✘ ' + errores.length + ' con error: ' + errores.join(', ');
                document.getElementById(idErr).classList.remove('d-none');
            }
            if (typeof onDone === 'function') onDone();
            return;
        }

        var folio = folios[idx];
        var pct   = Math.round((idx / total) * 100);
        document.getElementById(idBar).style.width  = pct + '%';
        document.getElementById(idTxt).textContent  =
            'Facturando ' + (idx + 1) + ' de ' + total + ' (Folio #' + folio + ')…';

        var fd = factMkFd(datosSAT);
        fetch(FACT_BASE + 'folio/' + folio + '/facturar', {
            method: 'POST', body: fd, credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) { exitos++; }
            else { errores.push('#' + folio + ' (' + (data.mensaje || 'error') + ')'); }
        })
        .catch(function () { errores.push('#' + folio + ' (conexión)'); })
        .finally(function () { idx++; siguiente(); });
    }

    siguiente();
}
</script>
<?= $this->endSection() ?>
