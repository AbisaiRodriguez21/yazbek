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
        'totalPiezas',
        'NombreCliente',
        'vendedor',
        'direccion',
        'telefono',
        'email',
    ];

    // ──────────────────────────────────────────────
    // Métodos de negocio
    // ──────────────────────────────────────────────

    /**
     * Notas del día de hoy con datos del cliente.
     */
    public function getDeHoy(): array
    {
        $hoy = date('Y-m-d');
        return $this->db->query(
            "SELECT n.*, c.nombre AS nombreCliente
             FROM notas_1 n
             LEFT JOIN clientes c ON n.idCliente = c.id
             WHERE n.fecha_inicial LIKE ?
             ORDER BY n.Id_Notas_1 DESC",
            ["{$hoy}%"]
        )->getResultArray();
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
     * Notas recientes de hoy con join a clientes.
     */
    public function getRecientesDeHoy(): array
    {
        $hoy = date('Y-m-d');
        return $this->db->query(
            "SELECT n.*, c.nombre
             FROM notas_1 n
             INNER JOIN clientes c ON n.idCliente = c.id
             WHERE n.fecha_inicial LIKE ?
             ORDER BY n.Id_Notas_1 DESC",
            ["{$hoy}%"]
        )->getResultArray();
    }

    /**
     * Obtiene una nota por folio.
     */
    public function getPorFolio(int $folio): array|null
    {
        return $this->where('folio', $folio)->first();
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

        if (! empty($folio)) {
            $sql    .= " WHERE n.folio LIKE ?";
            $params[] = "{$folio}%";
        }

        if ($statusPago !== null && $statusPago > 0) {
            $sql    .= empty($params) ? " WHERE" : " AND";
            $sql    .= " s.id = ?";
            $params[] = $statusPago;
        }

        $sql .= " ORDER BY n.Id_Notas_1 DESC";

        return $this->db->query($sql, $params)->getResultArray();
    }

    /**
     * Anticipo: notas con status = 4.
     */
    public function getAnticipos(): array
    {
        return $this->where('status', 4)->orderBy('Id_Notas_1', 'DESC')->findAll();
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
    public function siguienteFolio(): int
    {
        $result = $this->db->query("SELECT MAX(folio) AS max_folio FROM notas_1")->getRow();
        return ($result->max_folio ?? 0) + 1;
    }
}
