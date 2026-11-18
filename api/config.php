<?php
session_start();

// Aktifkan reporting error penuh secara internal, tapi jangan tampilkan HTML ke output
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/joi.php';
use Joi\Joi;
use Joi\ValidationError;

/**
 * Standard Error & Exception Handlers
 * Memastikan backend SELALU mengembalikan respon JSON terstruktur di segala kondisi error.
 */
set_exception_handler(function(Throwable $e) {
    $statusCode = 500;
    $message = 'Terjadi kesalahan internal pada server';
    $errors = [];
    $code = 'INTERNAL_SERVER_ERROR';

    if ($e instanceof ValidationError) {
        $statusCode = 400;
        $message = $e->getMessage();
        $errors = $e->getErrors();
        $code = 'VALIDATION_ERROR';
    } elseif ($e instanceof PDOException) {
        $sqlState = $e->getCode();
        if ($sqlState == '23505') { // Unique violation PostgreSQL
            $statusCode = 409;
            $code = 'DUPLICATE_ENTRY';
            if (str_contains($e->getMessage(), 'users_nis_key')) {
                $message = 'NIS sudah terdaftar. Silakan gunakan NIS lain.';
            } elseif (str_contains($e->getMessage(), 'kelas_nama_key')) {
                $message = 'Nama kelas sudah digunakan. Silakan pilih nama lain.';
            } else {
                $message = 'Data sudah ada di sistem dan tidak boleh duplikat.';
            }
        } elseif ($sqlState == '23503') { // Foreign key constraint violation
            $statusCode = 400;
            $code = 'FOREIGN_KEY_VIOLATION';
            $message = 'Data ini terhubung dengan data lain sehingga tidak dapat dihapus atau diubah langsung.';
        } elseif ($sqlState == '08006' || str_contains($e->getMessage(), 'connection')) {
            $statusCode = 500;
            $code = 'DB_CONNECTION_ERROR';
            $message = 'Tidak dapat terhubung ke database PostgreSQL.';
        } else {
            $statusCode = 500;
            $code = 'DATABASE_ERROR';
            $message = 'Kesalahan database: ' . $e->getMessage();
        }
    } else {
        $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        $message = $e->getMessage() ?: 'Terjadi kesalahan pada sistem';
    }

    http_response_code($statusCode);
    $response = [
        'status' => 'error',
        'message' => $message,
        'code' => $code
    ];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
});

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Load .env jika tersedia di root project
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// Konfigurasi Database PostgreSQL (Support Environment Variable & Fallback Lokal)
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'dompetDigitalDB';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'Asgardian13');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'code' => 'DB_CONNECTION_FAILED',
        'message' => 'Koneksi ke database PostgreSQL gagal: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Helpers Response
 */
function sendJson(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSuccess(string $message = 'Berhasil', array $extra = [], int $statusCode = 200): void {
    sendJson(array_merge(['status' => 'success', 'message' => $message], $extra), $statusCode);
}

function sendError(string $message, int $statusCode = 400, array $errors = [], string $code = 'ERROR'): void {
    $res = ['status' => 'error', 'message' => $message, 'code' => $code];
    if (!empty($errors)) {
        $res['errors'] = $errors;
    }
    sendJson($res, $statusCode);
}

/**
 * Helper untuk mengambil input (baik Form-Data, X-WWW-Form-Urlencoded, maupun JSON)
 */
function getRequestData(): array {
    $data = $_POST;
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
    }
    return $data;
}

/**
 * Helper validasi request menggunakan Joi
 */
function validateWithJoi(array $data, \Joi\ObjectSchema $schema): array {
    return Joi::validate($data, $schema);
}

/**
 * Session & Auth Helpers
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        sendError('Sesi Anda belum login atau telah berakhir. Silakan login kembali.', 401, [], 'UNAUTHORIZED');
    }
}

function requireAdmin(): void {
    requireAuth();
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'admin' && $role !== 'super_admin') {
        sendError('Akses ditolak: Hanya Admin atau Super Admin yang diizinkan.', 403, [], 'FORBIDDEN');
    }
}

function requireSuperAdmin(): void {
    requireAuth();
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        sendError('Akses ditolak: Hanya Super Admin yang diizinkan mengakses fitur ini.', 403, [], 'FORBIDDEN_SUPER_ADMIN');
    }
}

function getKelasId(): ?int {
    return isset($_SESSION['kelas_id']) ? (int)$_SESSION['kelas_id'] : null;
}

function isSuperAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'super_admin';
}
