<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

/**
 * AuthController
 *
 * Maneja el login y logout del sistema.
 *
 * Migrado desde:
 *   AppNissi/Yazbek/mostrador/entrar.php
 *
 * Niveles de acceso (campo `acceso` en tabla usuarios):
 *   1 = Administrador  → redirige a /admin
 *   2 = Caja           → redirige a /caja
 *   3 = Mostrador      → redirige a /mostrador
 *   4 = G. Ventas      → redirige a /mostrador
 */
class AuthController extends BaseController
{
    protected UsuarioModel $usuarioModel;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->usuarioModel = new UsuarioModel();
    }

    // ──────────────────────────────────────────────────────────────
    // GET /login  —  Muestra la pantalla de login
    // ──────────────────────────────────────────────────────────────
    public function loginPage(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        // Si ya hay sesión activa, redirigir al módulo correspondiente
        $session = session();
        if ($session->has('user_id')) {
            return redirect()->to($this->dashboardPorRol((int) $session->get('user_acceso')));
        }

        return view('auth/login', [
            'error'   => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /login  —  Procesa el formulario de login
    // Migrado desde: mostrador/entrar.php (bloque if isset $_POST['email'])
    // ──────────────────────────────────────────────────────────────
    public function login(): \CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'email' => 'required|valid_email',
            'pass'  => 'required|min_length[1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/login')
                             ->with('error', 'Correo o contraseña inválidos.')
                             ->withInput();
        }

        $mail = $this->request->getPost('email');
        $pass = $this->request->getPost('pass');

        $usuario = $this->usuarioModel->verificarLogin($mail, $pass);

        if (! $usuario) {
            return redirect()->to('/login')
                             ->with('error', 'Correo o contraseña incorrectos.');
        }

        // Crear sesión limpia — regenerar ID para evitar session fixation
        $session = session();
        $session->regenerate(true); // true = borra la sesión anterior
        $session->set([
            'user_id'     => $usuario['Id'],
            'user_email'  => $usuario['mail'],
            'user_nombre' => $usuario['nombre'],
            'user_acceso' => $usuario['acceso'],
            'logged_in'   => true,
        ]);

        // Redirigir según nivel de acceso
        return redirect()->to($this->dashboardPorRol((int) $usuario['acceso']));
    }

    // ──────────────────────────────────────────────────────────────
    // GET /logout  —  Cierra la sesión
    // Migrado desde: bloques doLogout en cada módulo
    // ──────────────────────────────────────────────────────────────
    public function logout(): \CodeIgniter\HTTP\RedirectResponse
    {
        // Destruir la sesión completamente y regenerar un ID limpio
        session()->destroy();

        // Redirigir sin flashdata para evitar problemas con sesión nueva
        return redirect()->to('/login');
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: URL del dashboard según rol
    // ──────────────────────────────────────────────────────────────
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
