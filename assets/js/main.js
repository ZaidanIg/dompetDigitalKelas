// assets/js/app.js

const API_BASE = 'api';

let stateSiswaTxIn = { page: 1, limit: 10, search: '', filter_date: 'all', sort_by: 'created_at', order: 'DESC', type: 'in' };
let stateSiswaTxOut = { page: 1, limit: 10, search: '', filter_date: 'all', sort_by: 'created_at', order: 'DESC', type: 'out' };
let stateAdminTxIn = { page: 1, limit: 10, search: '', filter_date: 'all', sort_by: 'created_at', order: 'DESC', type: 'in' };
let stateAdminTxOut = { page: 1, limit: 10, search: '', filter_date: 'all', sort_by: 'created_at', order: 'DESC', type: 'out' };
let stateAdminSiswa = { page: 1, limit: 10, search: '', sort_by: 'name', order: 'ASC' };

// Utilities
function showLoader() { document.getElementById('loader').style.display = 'flex'; }
function hideLoader() { document.getElementById('loader').style.display = 'none'; }

function showToast(message, type = 'success') {
    const toastEl = document.getElementById('appToast');
    const toastMessage = document.getElementById('toastMessage');
    
    // reset classes
    toastEl.classList.remove('bg-success', 'bg-danger');
    
    if (type === 'success') {
        toastEl.classList.add('bg-success');
    } else {
        toastEl.classList.add('bg-danger');
    }
    
    toastMessage.innerText = message;
    
    const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
}

function handleAuthExpired() {
    showToast('Sesi Anda telah berakhir. Silakan login kembali.', 'error');
    setTimeout(() => {
        window.location.href = 'login.php';
    }, 1200);
}

function escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderPagination(paginationData, containerId, onClickFuncName) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    if (!paginationData || paginationData.total_pages <= 1) return;

    const { current_page, total_pages } = paginationData;
    const prevOnClick = current_page > 1 ? `onclick="${onClickFuncName}(${current_page - 1})"` : '';
    const nextOnClick = current_page < total_pages ? `onclick="${onClickFuncName}(${current_page + 1})"` : '';
    
    let html = `<li class="page-item ${current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" ${prevOnClick}>Prev</a>
    </li>`;

    for (let i = 1; i <= total_pages; i++) {
        html += `<li class="page-item ${current_page === i ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="${onClickFuncName}(${i})">${i}</a>
        </li>`;
    }

    html += `<li class="page-item ${current_page === total_pages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" ${nextOnClick}>Next</a>
    </li>`;
    
    container.innerHTML = html;
}

function formatRp(angka) {
    return 'Rp ' + parseInt(angka || 0).toLocaleString('id-ID');
}

function showView(viewId) {
    if (viewId === 'admin-dashboard') viewId = 'dashboard-admin';
    document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
    const target = document.getElementById('view-' + viewId);
    if (target) {
        target.classList.add('active');
    }

    // Refresh data based on view
    if (viewId === 'siswa-dashboard') loadSiswaDashboard();
    if (viewId === 'dashboard-admin') loadAdminDashboard();
}

function setAmount(amount) {
    document.getElementById('setorAmount').value = amount;
}

// Authentication
async function logout() {
    showLoader();
    try {
        await fetch(`${API_BASE}/auth.php?action=logout`);
    } catch (e) {
        console.error(e);
    } finally {
        window.location.href = 'login.php';
        hideLoader();
    }
}

async function checkAuth() {
    try {
        const res = await fetch(`${API_BASE}/auth.php?action=me`);
        
        if (!res.ok) {
            window.location.href = 'login.php';
            return;
        }

        const data = await res.json().catch(() => ({}));
        if (data.status === 'success' && data.user) {
            if (data.user.role === 'super_admin') {
                window.location.href = 'superadmin.html';
                return;
            } else if (data.user.role === 'admin') {
                const kelasLabel = document.getElementById('adminKelasNama');
                if (kelasLabel) kelasLabel.innerText = data.user.kelas_nama || 'Kelas';
                showView('dashboard-admin');
            } else {
                document.getElementById('siswaName').innerText = data.user.name;
                document.getElementById('siswaKelas').innerText = data.user.kelas_nama || '';
                showView('siswa-dashboard');
            }
        } else {
            window.location.href = 'login.php';
        }
    } catch (err) {
        window.location.href = 'login.php';
    }
}

// Siswa Functions
function setPageSiswaTxIn(page) {
    stateSiswaTxIn.page = page;
    loadSiswaDashboardIn();
}
function setPageSiswaTxOut(page) {
    stateSiswaTxOut.page = page;
    loadSiswaDashboardOut();
}

async function loadSiswaDashboard() {
    try {
        const resBal = await fetch(`${API_BASE}/transactions.php?action=balance`);
        if (resBal.status === 401) { handleAuthExpired(); return; }
        const dataBal = await resBal.json().catch(() => ({}));
        if (dataBal.status === 'success') {
            document.getElementById('siswaBalance').innerText = formatRp(dataBal.balance);
            document.getElementById('siswaMonthTotal').innerText = formatRp(dataBal.month_total);
        } else if (dataBal.message) {
            showToast(dataBal.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
    loadSiswaDashboardIn();
    loadSiswaDashboardOut();
}

async function loadSiswaDashboardIn() {
    try {
        const query = new URLSearchParams(stateSiswaTxIn).toString();
        const resRec = await fetch(`${API_BASE}/transactions.php?action=recent&${query}`);
        if (resRec.status === 401) { handleAuthExpired(); return; }
        const dataRec = await resRec.json().catch(() => ({}));
        if (dataRec.status === 'success') {
            const list = document.getElementById('siswaRecentInList');
            list.innerHTML = '';
            dataRec.data.forEach(item => {
                const initial = item.user_name ? item.user_name.charAt(0).toUpperCase() : '?';
                list.innerHTML += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle">${initial}</div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">${item.user_name}</h6>
                                <small class="text-muted">${item.description || 'Setor Kas'}</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <h6 class="mb-0 fw-bold text-success">+${formatRp(item.amount)}</h6>
                            <small class="text-muted" style="font-size:0.7rem;">${new Date(item.created_at).toLocaleDateString('id-ID')}</small>
                        </div>
                    </div>
                `;
            });
            if (dataRec.data.length === 0) list.innerHTML = '<div class="text-center p-3 text-muted">Belum ada transaksi pemasukan</div>';
            renderPagination(dataRec.pagination, 'siswaTxInPagination', 'setPageSiswaTxIn');
        } else if (dataRec.message) {
            showToast(dataRec.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

async function loadSiswaDashboardOut() {
    try {
        const query = new URLSearchParams(stateSiswaTxOut).toString();
        const resRec = await fetch(`${API_BASE}/transactions.php?action=recent&${query}`);
        if (resRec.status === 401) { handleAuthExpired(); return; }
        const dataRec = await resRec.json().catch(() => ({}));
        if (dataRec.status === 'success') {
            const list = document.getElementById('siswaRecentOutList');
            list.innerHTML = '';
            dataRec.data.forEach(item => {
                list.innerHTML += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle bg-danger text-white">P</div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Pengeluaran</h6>
                                <small class="text-muted">${item.description || 'Pengeluaran Kelas'}</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <h6 class="mb-0 fw-bold text-danger">-${formatRp(item.amount)}</h6>
                            <small class="text-muted" style="font-size:0.7rem;">${new Date(item.created_at).toLocaleDateString('id-ID')}</small>
                        </div>
                    </div>
                `;
            });
            if (dataRec.data.length === 0) list.innerHTML = '<div class="text-center p-3 text-muted">Belum ada transaksi pengeluaran</div>';
            renderPagination(dataRec.pagination, 'siswaTxOutPagination', 'setPageSiswaTxOut');
        } else if (dataRec.message) {
            showToast(dataRec.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

document.getElementById('setorForm').addEventListener('submit', (e) => {
    e.preventDefault();
    if (!e.target.checkValidity()) {
        showToast('Mohon masukkan nominal setoran dengan benar', 'error');
        return;
    }
    const amount = parseFloat(document.getElementById('setorAmount').value);
    if (amount > 0) {
        document.getElementById('qrisAmountText').innerText = formatRp(amount);
        document.getElementById('step-amount').classList.add('d-none');
        document.getElementById('step-qris').classList.remove('d-none');
    } else {
        showToast('Nominal setoran harus lebih besar dari 0', 'error');
    }
});

async function simulatePaymentSuccess() {
    const amount = document.getElementById('setorAmount').value;
    showLoader();
    const fd = new FormData();
    fd.append('amount', amount);
    
    try {
        const res = await fetch(`${API_BASE}/transactions.php?action=setor`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        
        if (res.ok) {
            showToast(data.message || 'Pembayaran Berhasil! Terima kasih.', 'success');
            document.getElementById('setorAmount').value = '';
            document.getElementById('step-qris').classList.add('d-none');
            document.getElementById('step-amount').classList.remove('d-none');
            showView('siswa-dashboard');
        } else if (res.status === 401) {
            handleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Pembayaran gagal');
            showToast(errorMsg, 'error');
        }
    } catch(e) {
        showToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        hideLoader();
    }
}

// Admin Functions
function setPageAdminTxIn(page) {
    stateAdminTxIn.page = page;
    loadAdminDashboardIn();
}
function setPageAdminTxOut(page) {
    stateAdminTxOut.page = page;
    loadAdminDashboardOut();
}

async function loadAdminDashboard() {
    try {
        const resBal = await fetch(`${API_BASE}/transactions.php?action=balance`);
        if (resBal.status === 401) { handleAuthExpired(); return; }
        const dataBal = await resBal.json().catch(() => ({}));
        if (dataBal.status === 'success') {
            document.getElementById('adminBalance').innerText = formatRp(dataBal.balance);
            document.getElementById('adminMonthTotal').innerText = formatRp(dataBal.month_total);
        } else if (dataBal.message) {
            showToast(dataBal.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
    loadAdminDashboardIn();
    loadAdminDashboardOut();
}

async function loadAdminDashboardIn() {
    try {
        const query = new URLSearchParams(stateAdminTxIn).toString();
        const resRec = await fetch(`${API_BASE}/transactions.php?action=recent&${query}`);
        if (resRec.status === 401) { handleAuthExpired(); return; }
        const dataRec = await resRec.json().catch(() => ({}));
        if (dataRec.status === 'success') {
            const list = document.getElementById('adminRecentInList');
            list.innerHTML = '';
            dataRec.data.forEach(item => {
                const initial = item.user_name ? item.user_name.charAt(0).toUpperCase() : '?';
                list.innerHTML += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle">${initial}</div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">${item.user_name}</h6>
                                <small class="text-muted">${item.description || 'Setor Kas'}</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <h6 class="mb-0 fw-bold text-success">
                                +${formatRp(item.amount)}
                            </h6>
                            <small class="text-muted" style="font-size:0.7rem;">${new Date(item.created_at).toLocaleDateString('id-ID')}</small>
                        </div>
                    </div>
                `;
            });
            if (dataRec.data.length === 0) list.innerHTML = '<div class="text-center p-3 text-muted">Belum ada transaksi</div>';
            renderPagination(dataRec.pagination, 'adminTxInPagination', 'setPageAdminTxIn');
        } else if (dataRec.message) {
            showToast(dataRec.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

async function loadAdminDashboardOut() {
    try {
        const query = new URLSearchParams(stateAdminTxOut).toString();
        const resRec = await fetch(`${API_BASE}/transactions.php?action=recent&${query}`);
        if (resRec.status === 401) { handleAuthExpired(); return; }
        const dataRec = await resRec.json().catch(() => ({}));
        if (dataRec.status === 'success') {
            const list = document.getElementById('adminRecentOutList');
            list.innerHTML = '';
            dataRec.data.forEach(item => {
                list.innerHTML += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle bg-danger text-white">P</div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Pengeluaran</h6>
                                <small class="text-muted">${item.description || 'Pengeluaran Kelas'}</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <h6 class="mb-0 fw-bold text-danger">
                                -${formatRp(item.amount)}
                            </h6>
                            <small class="text-muted" style="font-size:0.7rem;">${new Date(item.created_at).toLocaleDateString('id-ID')}</small>
                        </div>
                    </div>
                `;
            });
            if (dataRec.data.length === 0) list.innerHTML = '<div class="text-center p-3 text-muted">Belum ada transaksi</div>';
            renderPagination(dataRec.pagination, 'adminTxOutPagination', 'setPageAdminTxOut');
        } else if (dataRec.message) {
            showToast(dataRec.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

function setPageAdminSiswa(page) {
    stateAdminSiswa.page = page;
    loadSiswaList();
}

async function loadSiswaList() {
    try {
        const query = new URLSearchParams(stateAdminSiswa).toString();
        const res = await fetch(`${API_BASE}/students.php?action=list&${query}`);
        if (res.status === 401) { handleAuthExpired(); return; }
        const data = await res.json().catch(() => ({}));
        if (data.status === 'success') {
            const list = document.getElementById('siswaList');
            list.innerHTML = '';
            data.data.forEach(s => {
                list.innerHTML += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${s.name}</strong><br>
                            <small class="text-muted">NIS: ${s.nis}</small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="resetSiswaPassword(${s.id})" title="Ganti Password"><i class="bi bi-key"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSiswa(${s.id})" title="Hapus"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                `;
            });
            if (data.data.length === 0) list.innerHTML = '<div class="text-center p-3 text-muted">Belum ada siswa</div>';
            renderPagination(data.pagination, 'adminSiswaPagination', 'setPageAdminSiswa');
        } else if (data.message) {
            showToast(data.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

document.getElementById('addSiswaForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!e.target.checkValidity()) {
        showToast('Mohon lengkapi data siswa', 'error');
        return;
    }
    const fd = new FormData(e.target);
    showLoader();
    try {
        const res = await fetch(`${API_BASE}/students.php?action=create`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        
        if (res.ok) {
            showToast(data.message || 'Siswa berhasil ditambahkan', 'success');
            e.target.reset();
            const modalEl = document.getElementById('modalAddSiswa');
            if (modalEl) {
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
            }
            loadSiswaList();
        } else if (res.status === 401) {
            handleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal menambahkan siswa');
            showToast(errorMsg, 'error');
        }
    } catch(err) {
        showToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        hideLoader();
    }
});

async function deleteSiswa(id) {
    if (!confirm('Apakah anda yakin ingin menghapus siswa ini?')) return;
    showLoader();
    try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch(`${API_BASE}/students.php?action=delete`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        
        if (res.ok) {
            showToast(data.message || 'Siswa berhasil dihapus', 'success');
            loadSiswaList();
        } else if (res.status === 401) {
            handleAuthExpired();
        } else {
            showToast(data.message || 'Gagal menghapus siswa', 'error');
        }
    } catch(e) {
        showToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        hideLoader();
    }
}

function resetSiswaPassword(id) {
    document.getElementById('resetPasswordSiswaId').value = id;
    document.getElementById('resetPasswordInput').value = '';
    const modal = new bootstrap.Modal(document.getElementById('modalGantiPassword'));
    modal.show();
}

document.getElementById('formGantiPassword').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!e.target.checkValidity()) {
        showToast('Mohon masukkan password baru', 'error');
        return;
    }
    const id = document.getElementById('resetPasswordSiswaId').value;
    const newPassword = document.getElementById('resetPasswordInput').value;
    
    showLoader();
    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('password', newPassword);
        
        const res = await fetch(`${API_BASE}/students.php?action=reset_password`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        
        if (res.ok) {
            showToast(data.message || 'Password berhasil diubah', 'success');
            const modalEl = document.getElementById('modalGantiPassword');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        } else if (res.status === 401) {
            handleAuthExpired();
        } else {
            showToast(data.message || (data.errors ? data.errors.join(', ') : 'Gagal mengubah password'), 'error');
        }
    } catch(err) {
        showToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        hideLoader();
    }
});

document.getElementById('withdrawForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!e.target.checkValidity()) {
        showToast('Mohon isi Nominal dan Keterangan pengeluaran', 'error');
        return;
    }
    if (!confirm('Anda yakin ingin mencatat pengeluaran kas ini?')) return;
    
    const fd = new FormData(e.target);
    showLoader();
    try {
        const res = await fetch(`${API_BASE}/transactions.php?action=withdraw`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        
        if (res.ok) {
            showToast(data.message || 'Pengeluaran berhasil dicatat', 'success');
            e.target.reset();
            showView('dashboard-admin');
        } else if (res.status === 401) {
            handleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal mencatat pengeluaran');
            showToast(errorMsg, 'error');
        }
    } catch(err) {
        showToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        hideLoader();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();

    // Toggle logic for Tambah Siswa button
    const collapseAddSiswa = document.getElementById('collapseAddSiswa');
    const btnAddSiswaToggle = document.getElementById('btnAddSiswaToggle');
    if (collapseAddSiswa && btnAddSiswaToggle) {
        collapseAddSiswa.addEventListener('show.bs.collapse', () => {
            btnAddSiswaToggle.classList.add('d-none');
        });
        collapseAddSiswa.addEventListener('hide.bs.collapse', () => {
            btnAddSiswaToggle.classList.remove('d-none');
        });
    }

    // Event Listeners for Filters
    ['siswaTxSearch', 'siswaTxFilterDate', 'siswaTxSortBy', 'siswaTxOrder'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', (e) => {
            const key = id.replace('siswaTx', '').replace(/([A-Z])/g, '_$1').toLowerCase().substring(1);
            if (key === 'filter_date') { stateSiswaTxIn.filter_date = e.target.value; stateSiswaTxOut.filter_date = e.target.value; }
            else if (key === 'sort_by') { stateSiswaTxIn.sort_by = e.target.value; stateSiswaTxOut.sort_by = e.target.value; }
            else if (key === 'order') { stateSiswaTxIn.order = e.target.value; stateSiswaTxOut.order = e.target.value; }
            else if (key === 'search') { stateSiswaTxIn.search = e.target.value; stateSiswaTxOut.search = e.target.value; }
            
            stateSiswaTxIn.page = 1;
            stateSiswaTxOut.page = 1;
            loadSiswaDashboardIn();
            loadSiswaDashboardOut();
        });
        if (id === 'siswaTxSearch') {
            document.getElementById(id)?.addEventListener('input', (e) => {
                stateSiswaTxIn.search = e.target.value;
                stateSiswaTxOut.search = e.target.value;
                stateSiswaTxIn.page = 1;
                stateSiswaTxOut.page = 1;
                loadSiswaDashboardIn();
                loadSiswaDashboardOut();
            });
        }
    });

    ['adminTxSearch', 'adminTxFilterDate', 'adminTxSortBy', 'adminTxOrder'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', (e) => {
            const key = id.replace('adminTx', '').replace(/([A-Z])/g, '_$1').toLowerCase().substring(1);
            if (key === 'filter_date') { stateAdminTxIn.filter_date = e.target.value; stateAdminTxOut.filter_date = e.target.value; }
            else if (key === 'sort_by') { stateAdminTxIn.sort_by = e.target.value; stateAdminTxOut.sort_by = e.target.value; }
            else if (key === 'order') { stateAdminTxIn.order = e.target.value; stateAdminTxOut.order = e.target.value; }
            else if (key === 'search') { stateAdminTxIn.search = e.target.value; stateAdminTxOut.search = e.target.value; }
            
            stateAdminTxIn.page = 1;
            stateAdminTxOut.page = 1;
            loadAdminDashboardIn();
            loadAdminDashboardOut();
        });
        if (id === 'adminTxSearch') {
            document.getElementById(id)?.addEventListener('input', (e) => {
                stateAdminTxIn.search = e.target.value;
                stateAdminTxOut.search = e.target.value;
                stateAdminTxIn.page = 1;
                stateAdminTxOut.page = 1;
                loadAdminDashboardIn();
                loadAdminDashboardOut();
            });
        }
    });

    ['adminSiswaSearch', 'adminSiswaSortBy', 'adminSiswaOrder'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', (e) => {
            const key = id.replace('adminSiswa', '').replace(/([A-Z])/g, '_$1').toLowerCase().substring(1);
            if (key === 'sort_by') stateAdminSiswa.sort_by = e.target.value;
            else if (key === 'order') stateAdminSiswa.order = e.target.value;
            else if (key === 'search') stateAdminSiswa.search = e.target.value;
            stateAdminSiswa.page = 1;
            loadSiswaList();
        });
        if (id === 'adminSiswaSearch') {
            document.getElementById(id)?.addEventListener('input', (e) => {
                stateAdminSiswa.search = e.target.value;
                stateAdminSiswa.page = 1;
                loadSiswaList();
            });
        }
    });
});
