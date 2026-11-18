<?php
require_once __DIR__ . '/config.php';
use Joi\Joi;

requireSuperAdmin();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $schema = Joi::object([
        'search' => Joi::string()->optional()->default(''),
    ]);
    $queryData = validateWithJoi($_GET, $schema);

    $where_clauses = ["1=1"];
    $params = [];

    if ($queryData['search'] !== '') {
        $where_clauses[] = "k.nama ILIKE ?";
        $params[] = "%" . $queryData['search'] . "%";
    }

    $where_sql = implode(' AND ', $where_clauses);

    $sql = "SELECT k.id, k.nama, k.created_at,
                (SELECT COUNT(*) FROM users WHERE kelas_id = k.id AND role = 'admin') as jumlah_admin,
                (SELECT COUNT(*) FROM users WHERE kelas_id = k.id AND role = 'siswa') as jumlah_siswa
            FROM kelas k
            WHERE $where_sql
            ORDER BY k.nama ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $kelas = $stmt->fetchAll();

    sendJson([
        'status' => 'success',
        'message' => 'Data kelas berhasil dimuat',
        'data' => $kelas
    ]);

} elseif ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $schema = Joi::object([
        'nama' => Joi::string()->required('Nama kelas wajib diisi')->min(2, 'Nama kelas minimal 2 karakter')->max(100, 'Nama kelas maksimal 100 karakter'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    $stmt = $pdo->prepare("INSERT INTO kelas (nama) VALUES (?) RETURNING id");
    $stmt->execute([$data['nama']]);
    $newId = $stmt->fetchColumn();

    sendSuccess('Kelas baru berhasil ditambahkan.', ['id' => $newId], 201);

} elseif ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $schema = Joi::object([
        'id' => Joi::integer()->required('ID kelas wajib disertakan')->positive('ID kelas tidak valid'),
        'nama' => Joi::string()->required('Nama kelas wajib diisi')->min(2, 'Nama kelas minimal 2 karakter')->max(100, 'Nama kelas maksimal 100 karakter'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    $stmt = $pdo->prepare("UPDATE kelas SET nama = ? WHERE id = ?");
    $stmt->execute([$data['nama'], $data['id']]);

    if ($stmt->rowCount() > 0) {
        sendSuccess('Nama kelas berhasil diperbarui.');
    } else {
        // Cek apakah memang kelas tidak ada atau nama sama persis
        $check = $pdo->prepare("SELECT id FROM kelas WHERE id = ?");
        $check->execute([$data['id']]);
        if (!$check->fetch()) {
            sendError('Kelas tidak ditemukan.', 404, [], 'NOT_FOUND');
        }
        sendSuccess('Tidak ada perubahan data pada kelas.');
    }

} elseif ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    $schema = Joi::object([
        'id' => Joi::integer()->required('ID kelas wajib disertakan')->positive('ID kelas tidak valid'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    // Cek apakah masih ada user di kelas ini
    $check = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE kelas_id = ?");
    $check->execute([$data['id']]);
    $result = $check->fetch();

    if ($result['total'] > 0) {
        sendError('Tidak dapat menghapus kelas yang masih memiliki ' . $result['total'] . ' admin/siswa. Pindahkan atau hapus user terlebih dahulu.', 400, [], 'CLASS_NOT_EMPTY');
    }

    $stmt = $pdo->prepare("DELETE FROM kelas WHERE id = ?");
    $stmt->execute([$data['id']]);

    if ($stmt->rowCount() > 0) {
        sendSuccess('Kelas berhasil dihapus.');
    } else {
        sendError('Kelas tidak ditemukan atau sudah dihapus.', 404, [], 'NOT_FOUND');
    }

} else {
    sendError('Aksi kelas tidak dikenali: ' . htmlspecialchars($action), 400, [], 'UNKNOWN_ACTION');
}
