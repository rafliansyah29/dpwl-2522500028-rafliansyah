<?php

class Auth extends Controller
{
    // --- METHOD LOGIN ---
    public function login(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // 1. Jika diakses via GET, tampilkan form login
        if ($method === 'GET') {
            $this->view('auth/login');
            return;
        }

        // 2. Jika bukan POST, tolak request
        if ($method !== 'POST') {
            http_response_code(405);
            header('Allow: GET, POST');
            exit('Method Not Allowed');
        }

        // --- PROSES LOGIN (REQUEST POST) ---
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validasi input
        if ($username === '') {
            $this->view('auth/login', [
                'error' => 'Username wajib diisi.'
            ]);
            return;
        }

        if ($password === '') {
            $this->view('auth/login', [
                'error' => 'Password wajib diisi.'
            ]);
            return;
        }

        // Memanggil Admin_model
        require_once APPPATH . 'models/Admin_model.php';

        $model = new Admin_model();
        $admin = $model->findByUsername($username);

        // Verifikasi akun, status, dan password (menggunakan teks biasa)
        if (
            !$admin ||
            $admin['status_akun'] !== 'aktif' ||
            $password !== $admin['password']
        ) {
            $this->view('auth/login', [
                'error' => 'Username atau password tidak sesuai.'
            ]);
            return;
        }

        // Simpan ke session jika berhasil login
        session_regenerate_id(true);

        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_status']   = $admin['status_akun'];

        header('Location: ' . site_url('admin'));
        exit;
    }

    // --- METHOD LOGOUT ---
    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: ' . site_url('auth/login'));
        exit;
    }
}