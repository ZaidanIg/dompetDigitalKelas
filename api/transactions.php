<?php
require_once __DIR__ . '/config.php';
use Joi\Joi;

requireAuth();

$action = $_GET['action'] ?? '';

if ($action === 'balance') {
    $params = [];
    $where = "status = 'success'";

    if (!isSuperAdmin()) {
        // Admin & Siswa: hanya kelas mereka
        $where .= " AND kelas_id = ?";
        $params[] = getKelasId();
    } elseif (isset($_GET['kelas_id']) && $_GET['kelas_id'] !== '') {
        // Super Admin: optional filter per kelas
        $where .= " AND kelas_id = ?";
        $params[] = (int)$_GET['kelas_id'];
    }

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) as total FROM transactions WHERE $where");
    $stmt->execute($params);
    $result = $stmt->fetch();
    $balance = (float)($result['total'] ?? 0);

    // Hitung total pemasukan bulan berjalan menggunakan fungsi PostgreSQL date_trunc
    $monthWhere = $where . " AND type = 'in' AND date_trunc('month', created_at) = date_trunc('month', CURRENT_DATE)";
    $stmtMonth = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as month_total FROM transactions WHERE $monthWhere");
    $stmtMonth->execute($params);
    $resMonth = $stmtMonth->fetch();
    $monthTotal = (float)($resMonth['month_total'] ?? 0);

    sendJson([
        'status' => 'success',
        'message' => 'Data saldo berhasil dimuat',
        'balance' => $balance,
        'month_total' => $monthTotal
    ]);

} elseif ($action === 'recent') {
    $schema = Joi::object([
        'page' => Joi::integer()->optional()->min(1)->default(1),
        'limit' => Joi::integer()->optional()->min(1)->max(100)->default(10),
        'search' => Joi::string()->optional()->default(''),
        'filter_date' => Joi::in(['all', '1w', '1m', '3m', '6m', '1y'], 'Filter waktu tidak valid')->default('all'),
        'sort_by' => Joi::in(['created_at', 'name', 'amount'], 'Kolom pengurutan tidak valid')->default('created_at'),
        'order' => Joi::in(['ASC', 'DESC', 'asc', 'desc'], 'Arah pengurutan tidak valid')->default('DESC'),
        'type' => Joi::in(['all', 'in', 'out'], 'Tipe transaksi tidak valid')->default('all'),
        'kelas_id' => Joi::string()->optional()->default(''),
    ]);
    $query = validateWithJoi($_GET, $schema);

    $page = $query['page'];
    $limit = $query['limit'];
    $offset = ($page - 1) * $limit;
    $search = $query['search'];
    $filter_date = $query['filter_date'];
    $order = strtoupper($query['order']);
    $type = $query['type'];

    $allowed_sorts = ['created_at' => 't.created_at', 'name' => 'u.name', 'amount' => 't.amount'];
    $sort_col = $allowed_sorts[$query['sort_by']] ?? 't.created_at';

    $where_clauses = ["t.status = 'success'"];
    $params = [];

    // Filter per kelas
    if (!isSuperAdmin()) {
        $where_clauses[] = "t.kelas_id = ?";
        $params[] = getKelasId();
    } elseif ($query['kelas_id'] !== '') {
        $where_clauses[] = "t.kelas_id = ?";
        $params[] = (int)$query['kelas_id'];
    }

    if ($search !== '') {
        $where_clauses[] = "(u.name ILIKE ? OR t.description ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($type === 'in' || $type === 'out') {
        $where_clauses[] = "t.type = ?";
        $params[] = $type;
    }

    // Filter tanggal PostgreSQL syntax
    if ($filter_date === '1w') {
        $where_clauses[] = "t.created_at >= NOW() - INTERVAL '1 week'";
    } elseif ($filter_date === '1m') {
        $where_clauses[] = "t.created_at >= NOW() - INTERVAL '1 month'";
    } elseif ($filter_date === '3m') {
        $where_clauses[] = "t.created_at >= NOW() - INTERVAL '3 months'";
    } elseif ($filter_date === '6m') {
        $where_clauses[] = "t.created_at >= NOW() - INTERVAL '6 months'";
    } elseif ($filter_date === '1y') {
        $where_clauses[] = "t.created_at >= NOW() - INTERVAL '1 year'";
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Total count
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE $where_sql");
    $stmtCount->execute($params);
    $total = (int)($stmtCount->fetch()['total'] ?? 0);

    // Data query
    $sql = "SELECT t.id, t.user_id, t.kelas_id, t.type, t.amount, t.payment_method, t.status, t.description, t.created_at,
                   COALESCE(u.name, 'Siswa (Telah Dihapus)') as user_name 
            FROM transactions t 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE $where_sql 
            ORDER BY $sort_col $order 
            LIMIT ? OFFSET ?";

    $queryParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $recent = $stmt->fetchAll();

    foreach ($recent as &$row) {
        $row['amount'] = (float)$row['amount'];
    }
    unset($row);

    $total_pages = $limit > 0 ? (int)ceil($total / $limit) : 1;

    sendJson([
        'status' => 'success',
        'message' => 'Daftar transaksi berhasil dimuat',
        'data' => $recent,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total,
            'limit' => $limit
        ]
    ]);

} elseif ($action === 'setor') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    if (($_SESSION['role'] ?? '') !== 'siswa') {
        sendError('Hanya siswa yang dapat melakukan setoran kas mandiri.', 403, [], 'FORBIDDEN');
    }

    $schema = Joi::object([
        'amount' => Joi::number()->required('Nominal setoran wajib diisi')->positive('Nominal setoran harus lebih besar dari 0')->min(500, 'Minimal setoran adalah Rp 500'),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    $kelasId = getKelasId();
    if (empty($kelasId)) {
        sendError('Akun siswa Anda belum terdaftar dalam kelas.', 400, [], 'NO_CLASS');
    }

    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, kelas_id, type, amount, payment_method, status, description) VALUES (?, ?, 'in', ?, 'qris', 'success', 'Setor Kas') RETURNING id");
    $stmt->execute([$_SESSION['user_id'], $kelasId, $data['amount']]);
    $newId = $stmt->fetchColumn();

    sendSuccess('Setoran kas berhasil dicatat.', ['id' => $newId], 201);

} elseif ($action === 'withdraw') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Metode request tidak valid. Harus POST.', 405, [], 'METHOD_NOT_ALLOWED');
    }

    requireAdmin();

    // Super admin tidak boleh withdraw langsung (hanya admin kelas)
    if (isSuperAdmin()) {
        sendError('Super Admin tidak dapat mencatat pengeluaran kas langsung. Fitur ini khusus Admin Kelas.', 403, [], 'FORBIDDEN_SUPER_ADMIN');
    }

    $schema = Joi::object([
        'amount' => Joi::number()->required('Nominal pengeluaran wajib diisi')->positive('Nominal pengeluaran harus lebih besar dari 0')->min(100, 'Minimal pengeluaran Rp 100'),
        'description' => Joi::string()->optional()->default('Pengeluaran Kelas')->min(3, 'Keterangan pengeluaran minimal 3 karakter')->max(255),
    ]);
    $data = validateWithJoi(getRequestData(), $schema);

    $kelasId = getKelasId();
    if (empty($kelasId)) {
        sendError('Admin belum terdaftar dalam kelas manapun.', 400, [], 'NO_CLASS');
    }

    // Cek saldo kelas saat ini agar tidak terjadi defisit/saldo minus
    $checkBalStmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) as balance FROM transactions WHERE status = 'success' AND kelas_id = ?");
    $checkBalStmt->execute([$kelasId]);
    $currentBalance = (float)($checkBalStmt->fetch()['balance'] ?? 0);

    if ($data['amount'] > $currentBalance) {
        $formattedBal = 'Rp ' . number_format($currentBalance, 0, ',', '.');
        $formattedReq = 'Rp ' . number_format($data['amount'], 0, ',', '.');
        sendError("Saldo kas kelas tidak mencukupi untuk pengeluaran {$formattedReq}. Saldo saat ini: {$formattedBal}.", 400, [], 'INSUFFICIENT_BALANCE');
    }

    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, kelas_id, type, amount, payment_method, status, description) VALUES (?, ?, 'out', ?, 'manual', 'success', ?) RETURNING id");
    $stmt->execute([$_SESSION['user_id'], $kelasId, $data['amount'], $data['description']]);
    $newId = $stmt->fetchColumn();

    sendSuccess('Pengeluaran kas berhasil dicatat.', ['id' => $newId], 201);

} else {
    sendError('Aksi transaksi tidak dikenali: ' . htmlspecialchars($action), 400, [], 'UNKNOWN_ACTION');
}
