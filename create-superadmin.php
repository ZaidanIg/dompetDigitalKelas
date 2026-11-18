<?php
/**
 * CLI Helper: Membuat atau Mengupdate Akun Super Admin
 * Jalankan di terminal: php create-superadmin.php
 */

if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui terminal / CLI.\n");
}

require_once __DIR__ . '/api/config.php';

echo "=============================================\n";
echo "       PEMBUATAN AKUN SUPER ADMIN VPS        \n";
echo "=============================================\n";

echo "Masukkan NIS / Username Superadmin (default: superadmin): ";
$nis = trim(fgets(STDIN));
if ($nis === '') {
    $nis = 'superadmin';
}

echo "Masukkan Nama Lengkap (default: Super Administrator): ";
$name = trim(fgets(STDIN));
if ($name === '') {
    $name = 'Super Administrator';
}

echo "Masukkan Password baru: ";
$pass = trim(fgets(STDIN));
if (strlen($pass) < 4) {
    die("\n❌ Error: Password minimal harus 4 karakter!\n");
}

$passwordHash = password_hash($pass, PASSWORD_DEFAULT);

try {
    // Insert jika belum ada, atau update password jika NIS sudah ada
    $stmt = $pdo->prepare("
        INSERT INTO users (nis, name, password, role, kelas_id)
        VALUES (?, ?, ?, 'super_admin', NULL)
        ON CONFLICT (nis)
        DO UPDATE SET 
            name = EXCLUDED.name, 
            password = EXCLUDED.password, 
            role = 'super_admin';
    ");
    $stmt->execute([$nis, $name, $passwordHash]);

    echo "\n=============================================\n";
    echo "✅ SUKSES! Akun Super Admin berhasil disimpan:\n";
    echo "   - NIS / Username : $nis\n";
    echo "   - Nama Lengkap   : $name\n";
    echo "   - Role           : super_admin\n";
    echo "=============================================\n";
    echo "Silakan login di: https://dompetkelas.duckdns.org/login.php\n";
} catch (Exception $e) {
    echo "\n❌ Gagal menyimpan akun: " . $e->getMessage() . "\n";
}
