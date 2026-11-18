<?php
require_once __DIR__ . '/config.php';
use Joi\Joi;

requireSuperAdmin();

$action = $_GET['action'] ?? '';

if ($action === 'dashboard_summary') {
    // Ringkasan per kelas: saldo, jumlah admin, jumlah siswa, pemasukan bulan ini
    $sql = "SELECT k.id, k.nama,
                (SELECT COUNT(*) FROM users WHERE kelas_id = k.id AND role = 'admin') as jumlah_admin,
                (SELECT COUNT(*) FROM users WHERE kelas_id = k.id AND role = 'siswa') as jumlah_siswa,
                (SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) 
                 FROM transactions WHERE kelas_id = k.id AND status = 'success') as saldo,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions 
                 WHERE kelas_id = k.id AND status = 'success' AND type = 'in' 
                 AND date_trunc('month', created_at) = date_trunc('month', CURRENT_DATE)) as pemasukan_bulan_ini
            FROM kelas k
            ORDER BY k.nama ASC";
    $stmt = $pdo->query($sql);
    $kelas_data = $stmt->fetchAll();

    // Pastikan nilai numerik bertipe float/int
    foreach ($kelas_data as &$k) {
        $k['saldo'] = (float)$k['saldo'];
        $k['pemasukan_bulan_ini'] = (float)$k['pemasukan_bulan_ini'];
        $k['jumlah_admin'] = (int)$k['jumlah_admin'];
        $k['jumlah_siswa'] = (int)$k['jumlah_siswa'];
    }
    unset($k);

    // Global stats
    $totalKelas = count($kelas_data);

    $stmtAdmin = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
    $totalAdmin = (int)$stmtAdmin->fetch()['total'];

    $stmtSiswa = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'");
    $totalSiswa = (int)$stmtSiswa->fetch()['total'];

    $stmtSaldo = $pdo->query("SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) as total FROM transactions WHERE status = 'success'");
    $totalSaldo = (float)$stmtSaldo->fetch()['total'];

    sendJson([
        'status' => 'success',
        'message' => 'Ringkasan dashboard super admin berhasil dimuat',
        'global' => [
            'total_kelas' => $totalKelas,
            'total_admin' => $totalAdmin,
            'total_siswa' => $totalSiswa,
            'total_saldo' => $totalSaldo
        ],
        'per_kelas' => $kelas_data
    ]);

} elseif ($action === 'list') {
    $schema = Joi::object([
        'page' => Joi::integer()->optional()->min(1)->default(1),
        'limit' => Joi::integer()->optional()->min(1)->max(100)->default(10),
        'search' => Joi::string()->optional()->default(''),
        'kelas_id' => Joi::string()->optional()->default(''),
    ]);
    $query = validateWithJoi($_GET, $schema);

    $page = $query['page'];
    $limit = $query['limit'];
    $offset = ($page - 1) * $limit;
    $search = $query['search'];
    $kelas_filter = $query['kelas_id'];

    $where_clauses = ["u.role = 'admin'"];
    $params = [];

    if ($search !== '') {
        $where_clauses[] = "(u.nis ILIKE ? OR u.name ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($kelas_filter !== '') {
        $where_clauses[] = "u.kelas_id = ?";
        $params[] = (int)$kelas_filter;
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Total count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN kelas k ON u.kelas_id = k.id WHERE $where_sql");
    $stmtCount->execute($params);
    $total = (int)($stmtCount->fetch()['total'] ?? 0);

    // Data
    $sql = "SELECT u.id, u.nis, u.name, u.kelas_id, k.nama as kelas_nama, u.created_at
            FROM users u 
            LEFT JOIN kelas k ON u.kelas_id = k.id 
            WHERE $where_sql 
            ORDER BY u.name ASC 
            LIMIT ? OFFSET ?";
    
    $queryParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $admins = $stmt->fetchAll();

    $total_pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

    sendJson([
        'status' => 'success',
        'message' => 'Daftar admin berhasil dimuat',
        'data' => $admins,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total,
            'limit' => $limit
        ]
    ]);

} elseif ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $schema = Joi::object([
        'nis' => Joi::string()->required('NIS wajib diisi')->min(2, 'NIS minimal 2 karakter')->max(50),
        'name' => Joi::string()->required('Nama lengkap admin wajib diisi')->min(2, 'Nama minimal 2 karakter')->max(100),
        'password' => Joi::string()->optional()->default('password')->min(4, 'Password minimal 4 karakter'),
        'kelas_id' => Joi::integer()->required('Kelas wajib dipilih')->positive('Pilihan kelas tidak valid'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    // Cek keberadaan kelas
    $checkKelas = $pdo->prepare("SELECT id FROM kelas WHERE id = ?");
    $checkKelas->execute([$data['kelas_id']]);
    if (!$checkKelas->fetch()) {
        sendError('Kelas yang dipilih tidak ditemukan.', 404, [], 'CLASS_NOT_FOUND');
    }

    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (role, nis, name, password, kelas_id) VALUES ('admin', ?, ?, ?, ?) RETURNING id");
    $stmt->execute([$data['nis'], $data['name'], $passwordHash, $data['kelas_id']]);
    $newId = $stmt->fetchColumn();

    sendSuccess('Admin kelas berhasil ditambahkan.', ['id' => $newId], 201);

} elseif ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $schema = Joi::object([
        'id' => Joi::integer()->required('ID admin wajib disertakan')->positive('ID admin tidak valid'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$data['id']]);

    if ($stmt->rowCount() > 0) {
        sendSuccess('Admin kelas berhasil dihapus.');
    } else {
        sendError('Admin tidak ditemukan atau bukan role admin.', 404, [], 'NOT_FOUND');
    }

} elseif ($action === 'reset_password') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $schema = Joi::object([
        'id' => Joi::integer()->required('ID admin wajib disertakan')->positive('ID admin tidak valid'),
        'password' => Joi::string()->required('Password baru wajib diisi')->min(4, 'Password baru minimal 4 karakter'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'admin'");
    $stmt->execute([$passwordHash, $data['id']]);

    if ($stmt->rowCount() > 0) {
        sendSuccess('Password admin berhasil diubah.');
    } else {
        sendError('Admin tidak ditemukan.', 404, [], 'NOT_FOUND');
    }

} else {
    sendError('Aksi admin management tidak dikenali: ' . htmlspecialchars($action), 400, [], 'UNKNOWN_ACTION');
}
