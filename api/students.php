<?php
require_once __DIR__ . '/config.php';
use Joi\Joi;

requireAdmin();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $schema = Joi::object([
        'page' => Joi::integer()->optional()->min(1)->default(1),
        'limit' => Joi::integer()->optional()->min(1)->max(100)->default(10),
        'search' => Joi::string()->optional()->default(''),
        'sort_by' => Joi::in(['name', 'nis'], 'Sort by hanya boleh "name" atau "nis"')->default('name'),
        'order' => Joi::in(['ASC', 'DESC', 'asc', 'desc'], 'Order hanya boleh "ASC" atau "DESC"')->default('ASC'),
        'kelas_id' => Joi::string()->optional()->default(''),
    ]);
    $query = validateWithJoi($_GET, $schema);

    $page = $query['page'];
    $limit = $query['limit'];
    $offset = ($page - 1) * $limit;
    $search = $query['search'];
    $order = strtoupper($query['order']);
    $allowed_sorts = ['nis' => 'u.nis', 'name' => 'u.name'];
    $sort_col = $allowed_sorts[$query['sort_by']] ?? 'u.name';

    $where_clauses = ["u.role = 'siswa'"];
    $params = [];

    // Filter per kelas
    if (!isSuperAdmin()) {
        $where_clauses[] = "u.kelas_id = ?";
        $params[] = getKelasId();
    } elseif ($query['kelas_id'] !== '') {
        $where_clauses[] = "u.kelas_id = ?";
        $params[] = (int)$query['kelas_id'];
    }

    if ($search !== '') {
        $where_clauses[] = "(u.nis ILIKE ? OR u.name ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Total count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN kelas k ON u.kelas_id = k.id WHERE $where_sql");
    $stmtCount->execute($params);
    $total = (int)($stmtCount->fetch()['total'] ?? 0);

    // Data query
    $sql = "SELECT u.id, u.nis, u.name, u.role, u.kelas_id, k.nama as kelas_nama, u.created_at 
            FROM users u 
            LEFT JOIN kelas k ON u.kelas_id = k.id 
            WHERE $where_sql 
            ORDER BY $sort_col $order 
            LIMIT ? OFFSET ?";
    
    $queryParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $students = $stmt->fetchAll();

    $total_pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

    sendJson([
        'status' => 'success',
        'message' => 'Daftar siswa berhasil dimuat',
        'data' => $students,
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
        'name' => Joi::string()->required('Nama siswa wajib diisi')->min(2, 'Nama minimal 2 karakter')->max(100),
        'password' => Joi::string()->optional()->default('password')->min(4, 'Password minimal 4 karakter'),
        'kelas_id' => Joi::integer()->optional()->positive('Pilihan kelas tidak valid'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    // Penentuan kelas_id
    if (isSuperAdmin()) {
        if (empty($data['kelas_id'])) {
            sendError('Super Admin wajib memilih kelas untuk siswa baru.', 400, [], 'CLASS_REQUIRED');
        }
        $kelas_id = $data['kelas_id'];
    } else {
        $kelas_id = getKelasId();
        if (empty($kelas_id)) {
            sendError('Akun admin Anda belum terhubung dengan kelas manapun.', 400, [], 'NO_CLASS_ASSIGNED');
        }
    }

    // Verifikasi kelas ada di database
    $checkKelas = $pdo->prepare("SELECT id FROM kelas WHERE id = ?");
    $checkKelas->execute([$kelas_id]);
    if (!$checkKelas->fetch()) {
        sendError('Kelas yang dipilih tidak ditemukan.', 404, [], 'CLASS_NOT_FOUND');
    }

    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (role, nis, name, password, kelas_id) VALUES ('siswa', ?, ?, ?, ?) RETURNING id");
    $stmt->execute([$data['nis'], $data['name'], $passwordHash, $kelas_id]);
    $newId = $stmt->fetchColumn();

    sendSuccess('Siswa baru berhasil ditambahkan.', ['id' => $newId], 201);

} elseif ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $schema = Joi::object([
        'id' => Joi::integer()->required('ID siswa wajib disertakan')->positive('ID siswa tidak valid'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    // Validasi: admin hanya bisa hapus siswa di kelasnya
    if (!isSuperAdmin()) {
        $check = $pdo->prepare("SELECT kelas_id FROM users WHERE id = ? AND role = 'siswa'");
        $check->execute([$data['id']]);
        $target = $check->fetch();
        if (!$target || (int)$target['kelas_id'] !== getKelasId()) {
            sendError('Anda tidak memiliki izin untuk menghapus siswa dari kelas lain.', 403, [], 'FORBIDDEN');
        }
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'siswa'");
    $stmt->execute([$data['id']]);

    if ($stmt->rowCount() > 0) {
        sendSuccess('Siswa berhasil dihapus dari sistem.');
    } else {
        sendError('Siswa tidak ditemukan atau sudah dihapus.', 404, [], 'NOT_FOUND');
    }

} elseif ($action === 'reset_password') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $schema = Joi::object([
        'id' => Joi::integer()->required('ID siswa wajib disertakan')->positive('ID siswa tidak valid'),
        'password' => Joi::string()->required('Password baru wajib diisi')->min(4, 'Password minimal 4 karakter'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    // Validasi: admin hanya bisa reset password siswa di kelasnya
    if (!isSuperAdmin()) {
        $check = $pdo->prepare("SELECT kelas_id FROM users WHERE id = ? AND role = 'siswa'");
        $check->execute([$data['id']]);
        $target = $check->fetch();
        if (!$target || (int)$target['kelas_id'] !== getKelasId()) {
            sendError('Anda tidak memiliki izin mengubah password siswa ini.', 403, [], 'FORBIDDEN');
        }
    }

    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'siswa'");
    $stmt->execute([$passwordHash, $data['id']]);

    if ($stmt->rowCount() > 0) {
        sendSuccess('Password siswa berhasil diubah.');
    } else {
        sendError('Siswa tidak ditemukan.', 404, [], 'NOT_FOUND');
    }

} else {
    sendError('Aksi students tidak dikenali: ' . htmlspecialchars($action), 400, [], 'UNKNOWN_ACTION');
}
