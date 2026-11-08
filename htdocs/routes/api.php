<?php

declare(strict_types=1);

function registerApiRoutes(ProductController $controller, AuthController $authController): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
    $segments = $path === '' ? [] : explode('/', $path);

    // ==========================================
    // RUTE AUTHENTICATION & USERS
    // ==========================================

    // POST /register - Pendaftaran akun baru (Username & Password)
    if ($segments === ['register'] && $method === 'POST') {
        $authController->register();
        return;
    }

    // POST /login-credentials - Login awal menggunakan Username & Password
    if ($segments === ['login-credentials'] && $method === 'POST') {
        $authController->loginCredentials();
        return;
    }

    // GET /auth/setup - Detail konfigurasi TOTP dan QR code Google Authenticator (Muncul setelah login credentials)
    if ($segments === ['auth', 'setup'] && $method === 'GET') {
        $authController->setup();
        return;
    }

    // POST /auth/verify - Verifikasi kode 6-digit Google Authenticator sebelum masuk beranda
    if ($segments === ['auth', 'verify'] && $method === 'POST') {
        $authController->login();
        return;
    }

    // (Opsional kompatibilitas lama) GET /login - detail QR code lama
    if ($segments === ['login'] && $method === 'GET') {
        $authController->setup();
        return;
    }
    // (Opsional kompatibilitas lama) POST /login - verifikasi kode 6 digit lama
    if ($segments === ['login'] && $method === 'POST') {
        $authController->login();
        return;
    }
    if ($segments === ['login']) {
        fail('Method not allowed.', 405);
    }

    // GET / - halaman login.
    if ($segments === [] && $method === 'GET') {
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../views/LoginView.php';
        return;
    }
    
    // GET /health - health check service.
    if ($segments === ['health']) {
        respond(['status' => 'ok', 'service' => 'products-api']);
    }

    // ==========================================
    // RUTE PRODUCTS (CRUD & KATALOG)
    // ==========================================
    if (($segments[0] ?? '') !== 'products') {
        fail('Route not found.', 404);
    }

    // GET /products/categories - daftar kategori dengan slug dan URL.
    if ($method === 'GET' && ($segments[1] ?? '') === 'categories') {
        $controller->categories();
    }
    // GET /products/category-list - daftar nilai kategori.
    if ($method === 'GET' && ($segments[1] ?? '') === 'category-list') {
        $controller->categoryList();
    }
    // GET /products/category/{category} - produk berdasarkan kategori.
    if ($method === 'GET' && ($segments[1] ?? '') === 'category' && isset($segments[2])) {
        $_GET['category'] = $segments[2];
        $controller->index();
    }
    // GET /products/{id} - detail satu produk.
    if ($method === 'GET' && isset($segments[1]) && ctype_digit($segments[1])) {
        $controller->show((int) $segments[1]);
    }
    // GET /products - daftar produk dengan filter, sorting, dan pagination.
    if ($method === 'GET' && count($segments) === 1) {
        $controller->index();
    }
    // POST /products atau POST /products/add - tambah produk.
    if ($method === 'POST' && (($segments[1] ?? '') === 'add' || !isset($segments[1]))) {
        $controller->store();
    }
    // PUT/PATCH /products/{id} - ubah produk.
    if (in_array($method, ['PUT', 'PATCH'], true) && isset($segments[1]) && ctype_digit($segments[1])) {
        $controller->update((int) $segments[1]);
    }
    // DELETE /products/{id} - hapus produk.
    if ($method === 'DELETE' && isset($segments[1]) && ctype_digit($segments[1])) {
        $controller->destroy((int) $segments[1]);
    }

    fail('Route not found.', 404);
}