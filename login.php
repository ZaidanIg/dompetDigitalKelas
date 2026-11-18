<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Sistem Kas Kelas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20260928">
</head>
<body>

<div id="loader">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
    <div id="appToast" class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<div id="app-container">
    <section id="view-login" class="view-section active p-4 min-vh-100 d-flex flex-column justify-content-center">
        <div class="text-center mb-5">
            <h1 class="fw-bold text-primary mb-2" style="font-size: 2.5rem;">Kas Kelas</h1>
            <p class="text-muted">Kelola kas kelas dengan mudah dan transparan</p>
        </div>
        
        <div class="card glass border-0 rounded-4 p-4 shadow-sm">
            <form id="loginForm" novalidate>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold mb-1">NIS / Username</label>
                    <input type="text" class="form-control form-control-lg bg-light border-0" name="nis" required placeholder="Masukkan NIS">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold mb-1">Password</label>
                    <input type="password" class="form-control form-control-lg bg-light border-0" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary-custom w-100 btn-lg">Masuk</button>
            </form>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const API_BASE = 'api';
    
    function showLoader() { document.getElementById('loader').style.display = 'flex'; }
    function hideLoader() { document.getElementById('loader').style.display = 'none'; }
    
    function showToast(message, type = 'success') {
        const toastEl = document.getElementById('appToast');
        const toastBody = document.getElementById('toastMessage');
        toastEl.className = `toast align-items-center text-white border-0 ${type === 'success' ? 'bg-success' : 'bg-danger'}`;
        toastBody.innerText = message;
        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
    }

    // Cek apakah sudah login
    async function checkAuth() {
        try {
            const res = await fetch(`${API_BASE}/auth.php?action=me`);
            if (res.ok) {
                const data = await res.json().catch(() => ({}));
                if (data.status === 'success' && data.user) {
                    if (data.user.role === 'super_admin') {
                        window.location.href = 'superadmin.html';
                    } else {
                        window.location.href = 'index.html';
                    }
                }
            }
        } catch(e) { }
    }

    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const nisInput = e.target.querySelector('input[name="nis"]');
        const passInput = e.target.querySelector('input[name="password"]');

        if (!nisInput.value.trim() || !passInput.value) {
            showToast('Mohon isi NIS dan Password dengan lengkap', 'error');
            return;
        }

        const formData = new FormData(e.target);
        showLoader();
        try {
            const res = await fetch(`${API_BASE}/auth.php?action=login`, { method: 'POST', body: formData });
            const data = await res.json().catch(() => ({}));
            
            if (res.ok) {
                showToast(data.message || 'Login berhasil', 'success');
                setTimeout(() => {
                    if (data.role === 'super_admin') {
                        window.location.href = 'superadmin.html';
                    } else {
                        window.location.href = 'index.html';
                    }
                }, 500);
            } else {
                const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal login');
                showToast(errorMsg, 'error');
            }
        } catch(err) {
            showToast('Gagal terhubung ke server. Periksa koneksi atau pastikan server berjalan.', 'error');
        } finally {
            hideLoader();
        }
    });

    document.addEventListener('DOMContentLoaded', checkAuth);
</script>
</body>
</html>
