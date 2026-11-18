<?php
require_once __DIR__ . '/config.php';
use Joi\Joi;

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $loginSchema = Joi::object([
        'nis' => Joi::string()->required('NIS wajib diisi')->min(1, 'NIS tidak boleh kosong'),
        'password' => Joi::string()->required('Password wajib diisi')->min(1, 'Password tidak boleh kosong'),
    ]);

    $data = validateWithJoi(getRequestData(), $loginSchema);

    $stmt = $pdo->prepare("
        SELECT u.id, u.nis, u.name, u.password, u.role, u.kelas_id, k.nama as kelas_nama
        FROM users u
        LEFT JOIN kelas k ON u.kelas_id = k.id
        WHERE u.nis = ?
        LIMIT 1
    ");

    $stmt->execute([$data['nis']]);
    $user = $stmt->fetch();

    if ($user && password_verify($data['password'], $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['kelas_id'] = $user['kelas_id'];
        $_SESSION['kelas_nama'] = $user['kelas_nama'] ?? '';

        $redirect = ($user['role'] === 'super_admin') ? 'superadmin.html' : 'index.html';

        sendJson([
            'status' => 'success',
            'message' => 'Login berhasil. Selamat datang kembali, ' . htmlspecialchars($user['name']),
            'role' => $user['role'],
            'redirect' => $redirect,
            'user' => [
                'id' => $user['id'],
                'nis' => $user['nis'],
                'name' => $user['name'],
                'role' => $user['role'],
                'kelas_id' => $user['kelas_id'],
                'kelas_nama' => $user['kelas_nama'] ?? ''
            ]
        ]);
    }

    sendError('NIS atau Password yang Anda masukkan tidak sesuai.', 401, [], 'INVALID_CREDENTIALS');

} elseif ($action === 'logout') {
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

    sendSuccess('Anda telah berhasil keluar (logout).');

} elseif ($action === 'me') {
    if (isLoggedIn()) {
        sendJson([
            'status' => 'success',
            'user' => [
                'id' => $_SESSION['user_id'],
                'role' => $_SESSION['role'],
                'name' => $_SESSION['name'],
                'kelas_id' => $_SESSION['kelas_id'] ?? null,
                'kelas_nama' => $_SESSION['kelas_nama'] ?? ''
            ]
        ]);
    }

    sendError('Anda belum masuk ke sistem. Silakan login.', 401, [], 'NOT_LOGGED_IN');

} else {
    sendError('Aksi auth tidak dikenali: ' . htmlspecialchars($action), 400, [], 'UNKNOWN_ACTION');
}
