# dompetDigitalKelas

Sistem Manajemen Keuangan dan Kas Kelas berbasis Web dengan PostgreSQL dan PHP.

## Fitur Utama
- **Autentikasi Multi-Role**: Super Admin, Admin Kelas (Bendahara), dan Siswa.
- **Validasi Data Terpusat**: Menggunakan schema validation JOI.
- **Manajemen Kas & Transaksi**: Pemasukan (Setor Kas QRIS mandiri oleh siswa) dan Pengeluaran (dicatat oleh admin kelas dengan proteksi saldo minus).
- **Manajemen Kelas & Pengguna**: Super Admin dapat mengelola seluruh kelas dan admin; Admin kelas mengelola siswa di kelasnya.
- **Database PostgreSQL**: Dukungan penuh database PostgreSQL 16+ dengan integritas relasional (`ON DELETE SET NULL` pada riwayat transaksi).
- **Siap Deployment VPS**: Dilengkapi script otomatisasi Nginx + PHP 8.3-FPM + Let's Encrypt SSL, Docker Compose, serta konfigurasi Free Domain DuckDNS (`dompetkelas.duckdns.org`).

## Tech Stack
- **Backend**: PHP 8.x, PDO PostgreSQL (`pdo_pgsql`), JOI Schema Validator
- **Database**: PostgreSQL (`dompetDigitalDB`)
- **Frontend**: Vanilla HTML5, Bootstrap 5, Bootstrap Icons, JavaScript (ES6+)
- **Web Server / Deployment**: Nginx, Docker & Docker Compose, Let's Encrypt Certbot

## Quick Start (Lokal)
1. Pastikan PostgreSQL berjalan dan database `dompetDigitalDB` telah dibuat.
2. Salin template konfigurasi:
   ```bash
   cp .env.example .env
   ```
3. Jalankan inisialisasi tabel dan data akun awal:
   ```bash
   php api/init_db.php
   ```
4. Jalankan server lokal:
   ```bash
   php -S localhost:8000
   ```
5. Akses di browser: `http://localhost:8000/login.php`

## Deployment di VPS (Domain: dompetkelas.duckdns.org)
1. Clone repositori ke direktori server:
   ```bash
   git clone https://github.com/ZaidanIg/dompetDigitalKelas.git /var/www/dompetDigital
   cd /var/www/dompetDigital
   ```
2. Jalankan script deployment otomatis:
   ```bash
   chmod +x deploy-vps.sh
   sudo bash deploy-vps.sh
   ```
3. Akses via HTTPS: `https://dompetkelas.duckdns.org`

## Kredensial Default
- **Super Admin**: NIS `superadmin` | Password `admin123`
- **Admin Kelas**: NIS `admin1` | Password `password`
- **Siswa**: NIS `siswa1` / `siswa2` | Password `password`
