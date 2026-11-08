<?php

declare(strict_types=1);

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

final class AuthController
{
    private Google2FA $google2fa;
    private PDO $pdo;

    // Inject PDO agar bisa koneksi ke database untuk tabel users
    public function __construct(PDO $pdo)
    {
        $this->google2fa = new Google2FA();
        $this->pdo = $pdo;
    }

    // 1. Proses Register Akun Baru (Username & Password)
    public function register(): void
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');

        if ($username === '' || $password === '') {
            fail('Username dan password wajib diisi.', 400);
        }

        // Cek apakah username sudah digunakan
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        if ($stmt->fetch()) {
            fail('Username sudah terdaftar.', 400);
        }

        // Simpan ke database dengan password hashing yang aman
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $insert = $this->pdo->prepare('INSERT INTO users (username, password) VALUES (:username, :password)');
        $insert->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
        ]);

        respond(['success' => true, 'message' => 'Registrasi berhasil.'], 201);
    }

    // 2. Proses Login awal dengan Username & Password
    public function loginCredentials(): void
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            fail('Username atau password salah.', 401);
        }

        // Jika berhasil, berikan sinyal sukses untuk lanjut ke halaman scan barcode 2FA
        respond([
            'success' => true,
            'message' => 'Login kredensial berhasil. Lanjutkan verifikasi Google Authenticator.',
        ]);
    }

    // 3. Setup QR Code Google Authenticator (Dipanggil SETELAH login kredensial sukses)
    public function setup(): void
    {
        $secret = Config::google2faSecret();
        $account = Config::google2faAccount();
        $issuer = Config::google2faIssuer();
        $otpAuthUrl = $this->google2fa->getQRCodeUrl($issuer, $account, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(256), new SvgImageBackEnd()));

        respond([
            'issuer' => $issuer,
            'account' => $account,
            'secret' => $secret,
            'otp_auth_url' => $otpAuthUrl,
            'qr_code' => $writer->writeString($otpAuthUrl),
        ]);
    }

    // 4. Verifikasi Kode OTP 6-Digit Google Authenticator
    public function login(): void
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $code = is_array($body) ? (string) ($body['code'] ?? '') : '';
        if (!preg_match('/^\d{6}$/', $code)) {
            fail('A valid 6-digit authentication code is required.');
        }

        $valid = $this->google2fa->verifyKey(Config::google2faSecret(), $code, 1);
        if (!$valid) {
            fail('Invalid authentication code.', 401);
        }

        respond([
            'authenticated' => true,
            'message' => 'Authentication successful.',
        ]);
    }
}