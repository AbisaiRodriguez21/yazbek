<?php

namespace App\Libraries;

/**
 * AuditService — Log de auditoría de acciones del sistema Yazbek
 *
 * Uso desde cualquier controlador:
 *   \App\Libraries\AuditService::log('NOTA_CANCELADA', 'notas_1', $folio, 'Nota cancelada por admin', $antes, $despues);
 *
 * Acciones estándar definidas como constantes para consistencia.
 */
class AuditService
{
    // ── Autenticación ─────────────────────────────────────────────
    const LOGIN_OK      = 'LOGIN_OK';
    const LOGIN_FAIL    = 'LOGIN_FAIL';
    const LOGOUT        = 'LOGOUT';

    // ── Ventas / Notas ────────────────────────────────────────────
    const VENTA_CREADA      = 'VENTA_CREADA';
    const VENTA_CERRADA     = 'VENTA_CERRADA';
    const VENTA_CANCELADA   = 'VENTA_CANCELADA';
    const VENTA_REACTIVADA  = 'VENTA_REACTIVADA';
    const VENTA_DUPLICADA   = 'VENTA_DUPLICADA';
    const VENTA_ELIMINADA   = 'VENTA_ELIMINADA';
    const VENTA_LIQUIDADA   = 'VENTA_LIQUIDADA';
    const VENTA_VERIFICADA  = 'VENTA_VERIFICADA';

    // ── Productos en nota ─────────────────────────────────────────
    const PRODUCTO_AGREGADO  = 'PRODUCTO_AGREGADO';
    const PRODUCTO_QUITADO   = 'PRODUCTO_QUITADO';

    // ── Pagos ─────────────────────────────────────────────────────
    const PAGO_REGISTRADO   = 'PAGO_REGISTRADO';
    const PAGO_CANCELADO    = 'PAGO_CANCELADO';
    const ANTICIPO_CREADO   = 'ANTICIPO_CREADO';

    // ── Inventario ────────────────────────────────────────────────
    const PRODUCTO_CREADO      = 'PRODUCTO_CREADO';
    const PRODUCTO_ACTUALIZADO = 'PRODUCTO_ACTUALIZADO';
    const PRODUCTO_ELIMINADO   = 'PRODUCTO_ELIMINADO';
    const STOCK_IMPORTADO      = 'STOCK_IMPORTADO';
    const PRECIOS_IMPORTADOS   = 'PRECIOS_IMPORTADOS';
    const PRODUCTOS_IMPORTADOS = 'PRODUCTOS_IMPORTADOS';

    // ── Clientes ──────────────────────────────────────────────────
    const CLIENTE_CREADO    = 'CLIENTE_CREADO';
    const CLIENTE_EDITADO   = 'CLIENTE_EDITADO';
    const CLIENTE_ELIMINADO = 'CLIENTE_ELIMINADO';
    const CLIENTE_RESTAURADO= 'CLIENTE_RESTAURADO';

    // ── Usuarios ──────────────────────────────────────────────────
    const USUARIO_CREADO     = 'USUARIO_CREADO';
    const USUARIO_EDITADO    = 'USUARIO_EDITADO';
    const USUARIO_ELIMINADO  = 'USUARIO_ELIMINADO';
    const USUARIO_RESTAURADO = 'USUARIO_RESTAURADO';
    const USUARIO_LIBERADO   = 'USUARIO_LIBERADO';
    const PASSWORD_CAMBIADO  = 'PASSWORD_CAMBIADO';

    // ── Mensajes / Avisos ─────────────────────────────────────────
    const MENSAJE_GUARDADO  = 'MENSAJE_GUARDADO';
    const MENSAJE_ELIMINADO = 'MENSAJE_ELIMINADO';

    /**
     * Registra una acción en audit_log.
     *
     * @param string      $accion      Constante de esta clase (ej: AuditService::VENTA_CANCELADA)
     * @param string      $tabla       Tabla principal afectada (ej: 'notas_1')
     * @param string|int|null $registroId  Identificador del registro (folio, id, sku…)
     * @param string      $descripcion Texto legible para humanos
     * @param array|null  $datosAntes  Estado anterior (para UPDATE/DELETE)
     * @param array|null  $datosDespues Estado nuevo (para INSERT/UPDATE)
     */
    public static function log(
        string     $accion,
        string     $tabla,
        mixed      $registroId,
        string     $descripcion,
        ?array     $datosAntes   = null,
        ?array     $datosDespues = null
    ): void {
        try {
            $session = session();
            $request = \Config\Services::request();

            $usuarioId     = (int)  ($session->get('user_id')     ?? 0);
            $usuarioNombre = (string)($session->get('user_nombre') ?? 'sistema');
            $rol           = (int)  ($session->get('user_acceso') ?? 0);
            $ip            = $request->getIPAddress();
            $ruta          = $request->getMethod() . ' ' . $request->getUri()->getPath();

            $db = \Config\Database::connect();
            $db->query(
                "INSERT INTO audit_log
                    (fecha, usuario_id, usuario_nombre, rol, accion, tabla, registro_id,
                     descripcion, datos_antes, datos_despues, ip, ruta)
                 VALUES
                    (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $usuarioId,
                    $usuarioNombre,
                    $rol,
                    $accion,
                    $tabla,
                    (string)($registroId ?? ''),
                    $descripcion,
                    $datosAntes   !== null ? json_encode($datosAntes,   JSON_UNESCAPED_UNICODE) : null,
                    $datosDespues !== null ? json_encode($datosDespues, JSON_UNESCAPED_UNICODE) : null,
                    $ip,
                    $ruta,
                ]
            );
        } catch (\Throwable $e) {
            // El log nunca debe romper la operación principal
            log_message('error', 'AuditService::log falló: ' . $e->getMessage());
        }
    }
}
