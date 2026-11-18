<?php
/**
 * Database Auto Setup & Migration Script for PostgreSQL
 * Database: dompetDigitalDB
 * Password: Asgardian13
 */

require_once __DIR__ . '/config.php';

try {
    // 1. Table kelas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kelas (
            id SERIAL PRIMARY KEY,
            nama VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Table users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            nis VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL CHECK (role IN ('super_admin', 'admin', 'siswa')),
            kelas_id INTEGER REFERENCES kelas(id) ON DELETE SET NULL,
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 3. Table transactions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            kelas_id INTEGER REFERENCES kelas(id) ON DELETE CASCADE,
            type VARCHAR(10) NOT NULL CHECK (type IN ('in', 'out')),
            amount NUMERIC(15, 2) NOT NULL CHECK (amount > 0),
            payment_method VARCHAR(50) DEFAULT 'manual',
            status VARCHAR(20) DEFAULT 'success',
            description TEXT,
            created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_nis ON users(nis);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_kelas_id ON users(kelas_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_kelas_id ON transactions(kelas_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_user_id ON transactions(user_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_created_at ON transactions(created_at DESC);");

    // Cek apakah ada data users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = (int)$stmt->fetch()['count'];

    if ($count === 0) {
        $defaultPassword = password_hash('password', PASSWORD_DEFAULT);
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);

        // Seed Super Admin
        $stmtSuper = $pdo->prepare("INSERT INTO users (nis, name, password, role, kelas_id) VALUES ('superadmin', 'Super Administrator', ?, 'super_admin', NULL)");
        $stmtSuper->execute([$adminPassword]);

        // Seed Sample Classes
        $stmtK1 = $pdo->query("INSERT INTO kelas (nama) VALUES ('XII RPL 1') RETURNING id");
        $kelasId1 = $stmtK1->fetchColumn();

        $stmtK2 = $pdo->query("INSERT INTO kelas (nama) VALUES ('XII RPL 2') RETURNING id");
        $kelasId2 = $stmtK2->fetchColumn();

        // Seed Admin Kelas
        $stmtAdmin = $pdo->prepare("INSERT INTO users (nis, name, password, role, kelas_id) VALUES ('admin1', 'Bendahara XII RPL 1', ?, 'admin', ?)");
        $stmtAdmin->execute([$defaultPassword, $kelasId1]);

        // Seed Siswa
        $stmtS1 = $pdo->prepare("INSERT INTO users (nis, name, password, role, kelas_id) VALUES ('siswa1', 'Budi Santoso', ?, 'siswa', ?) RETURNING id");
        $stmtS1->execute([$defaultPassword, $kelasId1]);
        $s1Id = $stmtS1->fetchColumn();

        $stmtS2 = $pdo->prepare("INSERT INTO users (nis, name, password, role, kelas_id) VALUES ('siswa2', 'Siti Rahma', ?, 'siswa', ?) RETURNING id");
        $stmtS2->execute([$defaultPassword, $kelasId1]);
        $s2Id = $stmtS2->fetchColumn();

        // Seed Sample Transactions
        $pdo->exec("
            INSERT INTO transactions (user_id, kelas_id, type, amount, payment_method, status, description, created_at)
            VALUES 
            ($s1Id, $kelasId1, 'in', 50000, 'qris', 'success', 'Uang Kas Bulan Ini', NOW() - INTERVAL '2 days'),
            ($s2Id, $kelasId1, 'in', 50000, 'qris', 'success', 'Uang Kas Bulan Ini', NOW() - INTERVAL '1 day'),
            ($s1Id, $kelasId1, 'out', 20000, 'manual', 'success', 'Beli Spidol & Penghapus', NOW() - INTERVAL '3 hours')
        ");
    }

    sendSuccess('Inisialisasi database PostgreSQL dompetDigitalDB berhasil.');

} catch (Exception $e) {
    sendError('Inisialisasi database gagal: ' . $e->getMessage(), 500);
}
