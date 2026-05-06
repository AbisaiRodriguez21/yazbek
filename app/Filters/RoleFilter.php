<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RoleFilter
 *
 * Verifica que el usuario autenticado tenga el nivel de acceso requerido.
 *
 * Niveles de acceso:
 *   1 = Administrador
 *   2 = Caja
 *   3 = Mostrador
 *   4 = Gerente de Ventas
 *
 * Se usa en Routes.php como: 'filter' => 'role:1'  o  'filter' => 'role:3,4'
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // Primero verificar que haya sesión activa
        if (! $session->has('user_id')) {
            return redirect()->to('/login')->with('error', 'Debes iniciar sesión para acceder.');
        }

        // Si se especificaron roles requeridos, verificar
        if (! empty($arguments)) {
            $userAcceso = (int) $session->get('user_acceso');

            // Los argumentos pueden venir como ['3,4'] o ['3', '4']
            $rolesPermitidos = [];
            foreach ($arguments as $arg) {
                foreach (explode(',', $arg) as $rol) {
                    $rolesPermitidos[] = (int) trim($rol);
                }
            }

            if (! in_array($userAcceso, $rolesPermitidos)) {
                // Redirigir al dashboard del rol que corresponde
                return redirect()->to($this->dashboardPorRol($userAcceso))
                                 ->with('error', 'No tienes permiso para acceder a esa sección.');
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada
    }

    /**
     * Retorna la URL del dashboard según el nivel de acceso del usuario.
     */
    private function dashboardPorRol(int $acceso): string
    {
        return match ($acceso) {
            1       => '/admin',
            2       => '/caja',
            3, 4    => '/mostrador',
            default => '/login',
        };
    }
}
