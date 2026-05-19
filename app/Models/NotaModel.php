<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * NotaModel — cabecera de ventas (tabla notas_1)
 *
 * Campos principales:
 *   Id_Notas_1, fecha_inicial, idCliente, idVendedor, folio,
 *   precioMayoreo, referencia, factura, descuento, status,
 *   verificado, sumaImportes, tipoPago, cargoTarjeta,
 *   subTotal, subTotal2, iva, total, tipoImpresion,
 *   totalPiezas, NombreCliente, vendedor, direccion,
 *   telefono, email
 *
 * Status:
 *   1 = Abierta / activa
 *   2 = En proceso de pago
 *   3 = Cancelada
 *   4 = Anticipo
 *   5 = Completada / pagada
 */
class NotaModel extends Model
{
    protected $table         = 'notas_1';
    protected $primaryKey    = 'Id_Notas_1';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;     // fecha_inicial es TIMESTAMP con DEFAULT

    protected $allowedFields = [
        'idCliente',
        'idVendedor',
        'folio',
        'precioMayoreo',
        'referencia',
        'factura',
        'descuento',
        'status',
        'verificado',
        'sumaImportes',
        'tipoPago',
        'cargoTarjeta',
        'subTotal',
        'subTotal2',
        'iva',
        'total',
        'tipoImpresion',
        'cargoPorImpresion',
        'totalPiezas',
    ];

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Notas del día de hoy con datos del cliente.
     */
/**
     * Notas del día de hoy con datos del cliente (Filtrado por vendedor).
     */
    public function getDeHoy(): array
    {
        $hoy = date('Y-m-d');
        $session = session();
        
        $sql = "SELECT n.*, c.nombre AS nombreCliente
                FROM notas_1 n
                LEFT JOIN clientes c ON n.idCliente = c.id
                WHERE n.fecha_inicial LIKE ?";
        
        $params = ["{$hoy}%"];

        // SI NO ES NIVEL 1 (Admin), SOLO VE SUS PROPIOS FOLIOS
        if ($session->has('user_acceso') && (int) $session->get('user_acceso') !== 1) {
            $sql .= " AND n.idVendedor = ?";
            $params[] = (int) $session->get('user_id');
        }

        $sql .= " ORDER BY n.Id_Notas_1 DESC";

        return $this->db->query($sql, $params)->getResultArray();
    }
    /**
     * Cuenta notas de hoy por status.
     */
    public function contarPorStatus(int $status): int
    {
        $hoy = date('Y-m-d');
        return $this->where('status', $status)
                    ->like('fecha_inicial', $hoy, 'after')
                    ->countAllResults();
    }


/**
     * Notas recientes de hoy con join a clientes (Filtrado por vendedor).
     */
    public function getRecientesDeHoy(): array
    {
        $hoy = date('Y-m-d');
        $session = session();

        $sql = "SELECT n.*, c.nombre
                FROM notas_1 n
                INNER JOIN clientes c ON n.idCliente = c.id
                WHERE n.fecha_inicial LIKE ?";
        
        $params = ["{$hoy}%"];

        // SI NO ES NIVEL 1, SOLO VE SUS PROPIOS FOLIOS
        if ($session->has('user_acceso') && (int) $session->get('user_acceso') !== 1) {
            $sql .= " AND n.idVendedor = ?";
            $params[] = (int) $session->get('user_id');
        }

        $sql .= " ORDER BY n.Id_Notas_1 DESC";

        return $this->db->query($sql, $params)->getResultArray();
    }
    /**
     * Obtiene una nota por folio.
     */
    public function getPorFolio(int $folio): array|null
    {
        return $this->db->query(
            "SELECT n.*, COALESCE(c.nombre, '—') AS cliente
             FROM notas_1 n
             LEFT JOIN clientes c ON c.id = n.idCliente
             WHERE n.folio = ? LIMIT 1",
            [$folio]
        )->getRowArray();
    }

    /**
     * Obtiene notas con join a status, filtradas por folio y/o status de pago.
     * Equivale a la lógica de llamadasAjax.php tipo 1 y 2.
     */
    public function buscarConFiltros(?string $folio = null, ?int $statusPago = null): array
    {
        $sql = "SELECT n.Id_Notas_1, n.fecha_inicial, n.NombreCliente, n.direccion,
                       n.telefono, n.email, n.vendedor, n.folio, n.factura, n.descuento,
                       s.nombre AS status, n.sumaImportes, n.subTotal, n.tipoPago,
                       n.cargoTarjeta, n.subTotal2, n.iva, n.total, n.status AS idstatus
                FROM notas_1 n
                LEFT JOIN status s ON n.status = s.id";

        $params = [];
        $whereClauses = []; 

        //Filtro por vendedor logueado si no es nivel 1
        $session = session();
        if ($session->has('user_acceso') && (int) $session->get('user_acceso') !== 1) {
            $whereClauses[] = "n.idVendedor = ?";
            $params[]       = (int) $session->get('user_id');
        }

        if (! empty($folio)) {
            $whereClauses[] = "n.folio LIKE ?";
            $params[] = "{$folio}%";
        }

        if ($statusPago !== null && $statusPago > 0) {
            $whereClauses[] = "s.id = ?";
            $params[] = $statusPago;
        }

        // Si hay condiciones, armamos el WHERE
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $sql .= " ORDER BY n.Id_Notas_1 DESC";

        return $this->db->query($sql, $params)->getResultArray();
    }

    /**
     * Anticipo: notas con status = 4.
     */
    public function getAnticipos(): array
    {
        $db = \Config\Database::connect();
        return $db->query("
            SELECT
                n.folio,
                n.fecha_inicial,
                COALESCE(c.nombre, n.NombreCliente, '—') AS cliente,
                COALESCE(n.vendedor, '—')                  AS vendedor,
                n.total,
                COALESCE((
                    SELECT SUM(m2.monto)
                    FROM montosnotas m2
                    INNER JOIN notas_1 nh ON nh.Id_Notas_1 = m2.idNotas
                    WHERE nh.folio = n.folio OR nh.referencia = n.folio
                ), 0) AS pagado
            FROM notas_1 n
            LEFT JOIN clientes c ON c.id = n.idCliente
            WHERE n.status = 4
              AND (n.referencia IS NULL OR n.referencia = 0)
            ORDER BY n.Id_Notas_1 DESC
        ")->getResultArray();
    }

    /**
     * Notas abiertas del vendedor actual (para mostrador).
     */
    public function getAbiertasPorVendedor(string $vendedor): array
    {
        return $this->where('vendedor', $vendedor)
                    ->whereIn('status', [1, 2, 4])
                    ->orderBy('Id_Notas_1', 'DESC')
                    ->findAll();
    }

    /**
     * Cambia el status de una nota.
     */
    public function cambiarStatus(int $id, int $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    /**
     * Marca una nota como pagada.
     */
    public function marcarPagada(int $id): bool
    {
        return $this->update($id, [
            'status'    => 5,
            'verificado' => 'Pagado',
        ]);
    }

    /**
     * Genera el siguiente número de folio disponible.
     */
    /**
     * Genera el siguiente folio de forma atómica usando transacción + bloqueo.
     * Previene que dos cajas simultáneas obtengan el mismo folio.
     */
    public function siguienteFolio(): int
    {
        $db = $this->db;
        $db->transStart();
        // FOR UPDATE bloquea la fila hasta que se haga el INSERT,
        // impidiendo que otra conexión lea el mismo MAX durante la transacción
        $result = $db->query("SELECT MAX(folio) AS max_folio FROM notas_1 FOR UPDATE")->getRow();
        $folio  = ($result->max_folio ?? 1000000) + 1;
        $db->transComplete();
        return $folio;
    }

    /**
     * Crea un folio hijo de pago para un anticipo existente.
     * El hijo tiene referencia = folio padre, sin productos, solo registra el pago.
     */
    public function crearFolioPagoAnticipo(int $foliopadre, int $idCliente, int $idVendedor, float $monto, int $idTipoPago, float $cargo = 0, int $esAnticipo = 1): int
    {
        $db    = \Config\Database::connect();
        $folio = $this->siguienteFolio();
        $fecha = date('Y-m-d H:i:s');

        // status 4 = anticipo (pago parcial), 5 = pagada (pago final)
        $statusHijo = $esAnticipo ? 4 : 5;

        $db->query(
            "INSERT INTO notas_1
             (folio, fecha_inicial, idCliente, idVendedor, referencia,
              sumaImportes, subTotal, subTotal2, iva, total, totalPiezas,
              status, descuento, cargoTarjeta, factura, tipoImpresion)
             VALUES (?, ?, ?, ?, ?, 0, ?, 0, 0, ?, 0, ?, 0, ?, 0, 1)",
            [$folio, $fecha, $idCliente, $idVendedor, $foliopadre, $monto, $monto, $statusHijo, $cargo]
        );

        $idNota = $db->insertID();

        $db->query(
            "INSERT INTO montosnotas (idNotas, idTipoPago, monto, cargos, anticipo, montoEfectivoIva, fecha)
             VALUES (?, ?, ?, ?, ?, 0, ?)",
            [$idNota, $idTipoPago, $monto, $cargo, $esAnticipo, $fecha]
        );

        return $folio;
    }

    /**
     * Liquida el folio padre y todos sus hijos de anticipo (status → 5).
     */
    public function liquidarAnticipo(int $foliopadre): void
    {
        $db = \Config\Database::connect();
        $db->query(
            "UPDATE notas_1 SET status = 5 WHERE folio = ? OR referencia = ?",
            [$foliopadre, $foliopadre]
        );
    }

    /**
     * Devuelve el total pagado (suma de montosnotas) del folio padre + todos sus hijos.
     */
    public function getTotalPagadoAnticipo(int $foliopadre): float
    {
        $db = \Config\Database::connect();
        $row = $db->query(
            "SELECT COALESCE(SUM(mn.monto), 0) AS pagado
             FROM montosnotas mn
             INNER JOIN notas_1 n ON n.Id_Notas_1 = mn.idNotas
             WHERE n.folio = ? OR n.referencia = ?",
            [$foliopadre, $foliopadre]
        )->getRowArray();
        return (float)($row['pagado'] ?? 0);
    }

    /**
     * Devuelve los pagos hijos de un folio anticipo.
     */
    public function getPagosHijos(int $foliopadre): array
    {
        $db = \Config\Database::connect();
        return $db->query(
            "SELECT n.folio, n.fecha_inicial, n.total,
                    COALESCE(tp.descripcion, '—') AS tipoPago,
                    mn.anticipo
             FROM notas_1 n
             LEFT JOIN montosnotas mn ON mn.idNotas = n.Id_Notas_1
             LEFT JOIN tipopago tp   ON tp.id = mn.idTipoPago
             WHERE n.referencia = ?
             ORDER BY n.folio ASC",
            [$foliopadre]
        )->getResultArray();
    }
}
