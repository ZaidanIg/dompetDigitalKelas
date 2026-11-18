// assets/js/superadmin.js

const API_BASE = 'api';

let stateKelas = { search: '' };
let stateAdmin = { page: 1, limit: 10, search: '', kelas_id: '' };
let stateDetailTx = { page: 1, limit: 10, kelas_id: null, type: 'all' };
let stateDetailSiswa = { page: 1, limit: 10, kelas_id: null, search: '' };
let optionsKelas = []; // For select dropdowns

// Utilities
function saShowLoader() { document.getElementById('sa-loader').style.display = 'flex'; }
function saHideLoader() { document.getElementById('sa-loader').style.display = 'none'; }

function saShowToast(message, type = 'success') {
    const toast = document.getElementById('sa-toast');
    toast.className = 'sa-toast ' + type + ' show';
    toast.innerText = message;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

function saHandleAuthExpired() {
    saShowToast('Sesi Anda telah berakhir. Silakan login kembali.', 'error');
    setTimeout(() => {
        window.location.href = 'login.php';
    }, 1200);
}

function formatRp(angka) {
    return 'Rp ' + parseInt(angka || 0).toLocaleString('id-ID');
}

function showSAView(viewId) {
    document.querySelectorAll('.sa-view').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.sa-nav-item').forEach(el => el.classList.remove('active'));
    
    const target = document.getElementById('view-' + viewId);
    if (target) target.classList.add('active');
    
    // Set active nav
    const navItems = document.querySelectorAll('.sa-nav-item');
    navItems.forEach(nav => {
        const onclickAttr = nav.getAttribute('onclick') || '';
        if (onclickAttr.includes(viewId)) {
            nav.classList.add('active');
        }
    });

    // Refresh data based on view
    if (viewId === 'sa-dashboard') loadSADashboard();
    if (viewId === 'sa-kelas') loadKelasList();
    if (viewId === 'sa-admin') loadAdminList();
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

function renderSAPagination(paginationData, containerId, onClickFuncName) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    if (!paginationData || paginationData.total_pages <= 1) return;

    const { current_page, total_pages } = paginationData;
    const prevOnClick = current_page > 1 ? `onclick="${onClickFuncName}(${current_page - 1})"` : '';
    const nextOnClick = current_page < total_pages ? `onclick="${onClickFuncName}(${current_page + 1})"` : '';
    
    let html = `<button ${current_page === 1 ? 'disabled' : ''} ${prevOnClick}><i class="bi bi-chevron-left"></i></button>`;

    // Simple pagination (show max 5 pages)
    let startPage = Math.max(1, current_page - 2);
    let endPage = Math.min(total_pages, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="${current_page === i ? 'active' : ''}" onclick="${onClickFuncName}(${i})">${i}</button>`;
    }

    html += `<button ${current_page === total_pages ? 'disabled' : ''} ${nextOnClick}><i class="bi bi-chevron-right"></i></button>`;
    
    container.innerHTML = html;
}

async function saLogout() {
    saShowLoader();
    try {
        await fetch(`${API_BASE}/auth.php?action=logout`);
    } catch(e) {
        console.error(e);
    } finally {
        window.location.href = 'login.php'; 
        saHideLoader();
    }
}

async function checkSuperAdminAuth() {
    try {
        const res = await fetch(`${API_BASE}/auth.php?action=me`);
        
        if (!res.ok) {
            window.location.href = 'login.php';
            return;
        }

        const data = await res.json().catch(() => ({}));
        if (data.status === 'success' && data.user && data.user.role === 'super_admin') {
            await fetchKelasOptions();
            showSAView('sa-dashboard');
        } else {
            // Not super admin, redirect back to index
            window.location.href = 'index.html';
        }
    } catch (err) {
        window.location.href = 'login.php';
    }
}

// Helper: Fetch all classes for dropdowns
async function fetchKelasOptions() {
    try {
        const res = await fetch(`${API_BASE}/kelas.php?action=list`);
        if (res.status === 401) { saHandleAuthExpired(); return; }
        const data = await res.json().catch(() => ({}));
        if (data.status === 'success') {
            optionsKelas = data.data || [];
            populateKelasDropdowns();
        } else if (data.message) {
            saShowToast(data.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

function populateKelasDropdowns() {
    let optionsHtml = '<option value="">Semua Kelas</option>';
    let optionsRequiredHtml = '<option value="">Pilih Kelas...</option>';
    
    optionsKelas.forEach(k => {
        optionsHtml += `<option value="${k.id}">${k.nama}</option>`;
        optionsRequiredHtml += `<option value="${k.id}">${k.nama}</option>`;
    });

    const filterAdmin = document.getElementById('filterKelasAdmin');
    if (filterAdmin) filterAdmin.innerHTML = optionsHtml;
    
    const selectAdmin = document.getElementById('selectKelasForAdmin');
    if (selectAdmin) selectAdmin.innerHTML = optionsRequiredHtml;
    
    const globalSelectSiswa = document.getElementById('globalAddSiswaKelas');
    if (globalSelectSiswa) globalSelectSiswa.innerHTML = optionsRequiredHtml;
}

// Dashboard
async function loadSADashboard() {
    saShowLoader();
    try {
        const res = await fetch(`${API_BASE}/admin_management.php?action=dashboard_summary`);
        if (res.status === 401) { saHandleAuthExpired(); return; }
        const data = await res.json().catch(() => ({}));
        
        if (data.status === 'success') {
            document.getElementById('saGlobalSaldo').innerText = formatRp(data.global.total_saldo);
            document.getElementById('saGlobalKelas').innerText = data.global.total_kelas;
            document.getElementById('saGlobalAdmin').innerText = data.global.total_admin;
            document.getElementById('saGlobalSiswa').innerText = data.global.total_siswa;

            const list = document.getElementById('saDashboardKelasList');
            list.innerHTML = '';
            
            data.per_kelas.forEach(k => {
                list.innerHTML += `
                    <div class="sa-kelas-card" onclick="viewKelasDetail(${k.id}, '${k.nama}')">
                        <div class="kelas-info">
                            <h3>${k.nama}</h3>
                            <div class="kelas-meta">
                                <span><i class="bi bi-person-badge"></i> ${k.jumlah_admin}</span>
                                <span><i class="bi bi-people"></i> ${k.jumlah_siswa}</span>
                            </div>
                        </div>
                        <div class="kelas-saldo">
                            <div class="saldo-amount">${formatRp(k.saldo)}</div>
                            <div class="saldo-month">+${formatRp(k.pemasukan_bulan_ini)} bln ini</div>
                        </div>
                        <i class="bi bi-chevron-right kelas-arrow"></i>
                    </div>
                `;
            });
            
            if (data.per_kelas.length === 0) {
                list.innerHTML = '<div class="sa-empty"><i class="bi bi-folder-x"></i><p>Belum ada kelas</p></div>';
            }
        } else if (data.message) {
            saShowToast(data.message, 'error');
        }
    } catch(e) {
        saShowToast('Gagal terhubung ke server', 'error');
    } finally {
        saHideLoader();
    }
}

// Manajemen Kelas
async function loadKelasList() {
    try {
        const query = new URLSearchParams(stateKelas).toString();
        const res = await fetch(`${API_BASE}/kelas.php?action=list&${query}`);
        if (res.status === 401) { saHandleAuthExpired(); return; }
        const data = await res.json().catch(() => ({}));
        
        if (data.status === 'success') {
            const list = document.getElementById('saKelasList');
            list.innerHTML = '';
            
            data.data.forEach(k => {
                list.innerHTML += `
                    <div class="sa-list-item">
                        <div class="item-info">
                            <h4>${k.nama}</h4>
                            <p>${k.jumlah_admin} Admin · ${k.jumlah_siswa} Siswa</p>
                        </div>
                        <div class="item-actions">
                            <button class="btn-edit" onclick="openEditKelas(${k.id}, '${k.nama}')"><i class="bi bi-pencil"></i></button>
                            <button class="btn-del" onclick="deleteKelas(${k.id})"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                `;
            });
            
            if (data.data.length === 0) {
                list.innerHTML = '<div class="sa-empty"><i class="bi bi-folder-x"></i><p>Tidak ada kelas ditemukan</p></div>';
            }
        } else if (data.message) {
            saShowToast(data.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

function openAddKelasModal() {
    document.getElementById('formAddKelas').reset();
    document.getElementById('saModalAddKelas').classList.add('show');
}

document.getElementById('formAddKelas').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    saShowLoader();
    try {
        const res = await fetch(`${API_BASE}/kelas.php?action=create`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            saShowToast(data.message || 'Kelas berhasil ditambahkan', 'success');
            e.target.reset();
            document.getElementById('saModalAddKelas').classList.remove('show');
            loadKelasList();
            fetchKelasOptions(); // refresh dropdowns
        } else if (res.status === 401) {
            saHandleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal menambahkan kelas');
            saShowToast(errorMsg, 'error');
        }
    } catch(err) {
        saShowToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        saHideLoader();
    }
});

function openEditKelas(id, nama) {
    document.getElementById('editKelasId').value = id;
    document.getElementById('editKelasNama').value = nama;
    document.getElementById('saModalEditKelas').classList.add('show');
}

document.getElementById('formEditKelas').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('id', document.getElementById('editKelasId').value);
    fd.append('nama', document.getElementById('editKelasNama').value);
    
    saShowLoader();
    try {
        const res = await fetch(`${API_BASE}/kelas.php?action=update`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            saShowToast(data.message || 'Kelas berhasil diubah', 'success');
            document.getElementById('saModalEditKelas').classList.remove('show');
            loadKelasList();
            fetchKelasOptions();
        } else if (res.status === 401) {
            saHandleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal mengubah kelas');
            saShowToast(errorMsg, 'error');
        }
    } catch(err) {
        saShowToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        saHideLoader();
    }
});

async function deleteKelas(id) {
    if (!confirm('Yakin ingin menghapus kelas ini? (Hanya bisa dihapus jika tidak ada user)')) return;
    saShowLoader();
    try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch(`${API_BASE}/kelas.php?action=delete`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            saShowToast(data.message || 'Kelas berhasil dihapus', 'success');
            loadKelasList();
            fetchKelasOptions();
        } else if (res.status === 401) {
            saHandleAuthExpired();
        } else {
            saShowToast(data.message || 'Gagal menghapus kelas', 'error');
        }
    } catch(e) {
        saShowToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        saHideLoader();
    }
}

document.getElementById('searchKelas').addEventListener('input', (e) => {
    stateKelas.search = e.target.value;
    loadKelasList();
});

// Manajemen Admin
function setPageAdmin(page) {
    stateAdmin.page = page;
    loadAdminList();
}

async function loadAdminList() {
    try {
        const query = new URLSearchParams(stateAdmin).toString();
        const res = await fetch(`${API_BASE}/admin_management.php?action=list&${query}`);
        if (res.status === 401) { saHandleAuthExpired(); return; }
        const data = await res.json().catch(() => ({}));
        
        if (data.status === 'success') {
            const list = document.getElementById('saAdminList');
            list.innerHTML = '';
            
            data.data.forEach(a => {
                list.innerHTML += `
                    <div class="sa-list-item">
                        <div class="item-info">
                            <h4>${a.name} <span class="sa-badge">${a.kelas_nama || 'Tanpa Kelas'}</span></h4>
                            <p>NIS: ${a.nis}</p>
                        </div>
                        <div class="item-actions">
                            <button class="btn-key" onclick="openResetPass(${a.id})" title="Reset Password"><i class="bi bi-key"></i></button>
                            <button class="btn-del" onclick="deleteAdmin(${a.id})" title="Hapus"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                `;
            });
            
            if (data.data.length === 0) {
                list.innerHTML = '<div class="sa-empty"><i class="bi bi-person-x"></i><p>Tidak ada admin ditemukan</p></div>';
            }
            
            renderSAPagination(data.pagination, 'saAdminPagination', 'setPageAdmin');
        } else if (data.message) {
            saShowToast(data.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

function openAddAdminModal() {
    document.getElementById('formAddAdmin').reset();
    document.getElementById('saModalAddAdmin').classList.add('show');
}

document.getElementById('formAddAdmin').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    saShowLoader();
    try {
        const res = await fetch(`${API_BASE}/admin_management.php?action=create`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            saShowToast(data.message || 'Admin berhasil ditambahkan', 'success');
            e.target.reset();
            document.getElementById('saModalAddAdmin').classList.remove('show');
            loadAdminList();
        } else if (res.status === 401) {
            saHandleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal menambahkan admin');
            saShowToast(errorMsg, 'error');
        }
    } catch(err) {
        saShowToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        saHideLoader();
    }
});

async function deleteAdmin(id) {
    if (!confirm('Yakin ingin menghapus admin ini?')) return;
    saShowLoader();
    try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch(`${API_BASE}/admin_management.php?action=delete`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            saShowToast(data.message || 'Admin dihapus', 'success');
            loadAdminList();
        } else if (res.status === 401) {
            saHandleAuthExpired();
        } else {
            saShowToast(data.message || 'Gagal menghapus admin', 'error');
        }
    } catch(e) {
        saShowToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        saHideLoader();
    }
}

function openResetPass(id) {
    document.getElementById('resetAdminId').value = id;
    document.getElementById('resetAdminPass').value = '';
    document.getElementById('saModalResetPass').classList.add('show');
}

document.getElementById('formResetPass').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('id', document.getElementById('resetAdminId').value);
    fd.append('password', document.getElementById('resetAdminPass').value);
    
    saShowLoader();
    try {
        const res = await fetch(`${API_BASE}/admin_management.php?action=reset_password`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            saShowToast(data.message || 'Password admin berhasil diubah', 'success');
            document.getElementById('saModalResetPass').classList.remove('show');
        } else if (res.status === 401) {
            saHandleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal mengubah password');
            saShowToast(errorMsg, 'error');
        }
    } catch(err) {
        saShowToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        saHideLoader();
    }
});

document.getElementById('searchAdmin').addEventListener('input', (e) => {
    stateAdmin.search = e.target.value;
    stateAdmin.page = 1;
    loadAdminList();
});

document.getElementById('filterKelasAdmin').addEventListener('change', (e) => {
    stateAdmin.kelas_id = e.target.value;
    stateAdmin.page = 1;
    loadAdminList();
});

// Detail Kelas View
async function viewKelasDetail(kelas_id, kelas_nama) {
    document.getElementById('detailKelasNama').innerText = 'Detail: ' + kelas_nama;
    stateDetailTx.kelas_id = kelas_id;
    stateDetailSiswa.kelas_id = kelas_id;
    
    showSAView('sa-detail-kelas');
    switchDetailTab('tx');
    
    // Load balances
    try {
        const res = await fetch(`${API_BASE}/transactions.php?action=balance&kelas_id=${kelas_id}`);
        if (res.status === 401) { saHandleAuthExpired(); return; }
        const data = await res.json().catch(() => ({}));
        if (data.status === 'success') {
            document.getElementById('detailKelasSaldo').innerText = formatRp(data.balance);
            document.getElementById('detailKelasBulan').innerText = '+' + formatRp(data.month_total);
        } else if (data.message) {
            saShowToast(data.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

function switchDetailTab(tab) {
    document.getElementById('tabDetailTx').classList.remove('active');
    document.getElementById('tabDetailSiswa').classList.remove('active');
    document.getElementById('contentDetailTx').style.display = 'none';
    document.getElementById('contentDetailSiswa').style.display = 'none';
    
    if (tab === 'tx') {
        document.getElementById('tabDetailTx').classList.add('active');
        document.getElementById('contentDetailTx').style.display = 'block';
        loadDetailTx();
    } else {
        document.getElementById('tabDetailSiswa').classList.add('active');
        document.getElementById('contentDetailSiswa').style.display = 'block';
        loadDetailSiswa();
    }
}

function setPageDetailTx(page) {
    stateDetailTx.page = page;
    loadDetailTx();
}

async function loadDetailTx() {
    try {
        const query = new URLSearchParams(stateDetailTx).toString();
        const res = await fetch(`${API_BASE}/transactions.php?action=recent&${query}`);
        if (res.status === 401) { saHandleAuthExpired(); return; }
        const data = await res.json().catch(() => ({}));
        
        if (data.status === 'success') {
            const list = document.getElementById('saDetailTxList');
            list.innerHTML = '';
            
            data.data.forEach(t => {
                const isIncome = t.type === 'in';
                const icon = isIncome ? '+' : '-';
                const initial = t.user_name ? t.user_name.charAt(0).toUpperCase() : '?';
                
                list.innerHTML += `
                    <div class="sa-tx-item">
                        <div class="tx-left">
                            <div class="tx-avatar ${isIncome ? 'income' : 'expense'}">${isIncome ? initial : 'P'}</div>
                            <div class="tx-info">
                                <h5>${isIncome ? t.user_name : 'Pengeluaran'}</h5>
                                <p>${t.description || (isIncome ? 'Setor Kas' : 'Pengeluaran Kelas')}</p>
                            </div>
                        </div>
                        <div class="tx-right">
                            <div class="tx-amount ${isIncome ? 'income' : 'expense'}">${icon}${formatRp(t.amount)}</div>
                            <div class="tx-date">${new Date(t.created_at).toLocaleDateString('id-ID')}</div>
                        </div>
                    </div>
                `;
            });
            
            if (data.data.length === 0) {
                list.innerHTML = '<div class="sa-empty"><i class="bi bi-receipt"></i><p>Belum ada transaksi</p></div>';
            }
            
            renderSAPagination(data.pagination, 'saDetailTxPagination', 'setPageDetailTx');
        } else if (data.message) {
            saShowToast(data.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

document.getElementById('detailTxType').addEventListener('change', (e) => {
    stateDetailTx.type = e.target.value;
    stateDetailTx.page = 1;
    loadDetailTx();
});

function setPageDetailSiswa(page) {
    stateDetailSiswa.page = page;
    loadDetailSiswa();
}

async function loadDetailSiswa() {
    try {
        const query = new URLSearchParams(stateDetailSiswa).toString();
        const res = await fetch(`${API_BASE}/students.php?action=list&${query}`);
        if (res.status === 401) { saHandleAuthExpired(); return; }
        const data = await res.json().catch(() => ({}));
        
        if (data.status === 'success') {
            const list = document.getElementById('saDetailSiswaList');
            list.innerHTML = '';
            
            data.data.forEach(s => {
                list.innerHTML += `
                    <div class="sa-list-item">
                        <div class="item-info">
                            <h4>${s.name}</h4>
                            <p>NIS: ${s.nis}</p>
                        </div>
                    </div>
                `;
            });
            
            if (data.data.length === 0) {
                list.innerHTML = '<div class="sa-empty"><i class="bi bi-people"></i><p>Belum ada siswa di kelas ini</p></div>';
            }
            
            renderSAPagination(data.pagination, 'saDetailSiswaPagination', 'setPageDetailSiswa');
        } else if (data.message) {
            saShowToast(data.message, 'error');
        }
    } catch(e) {
        console.error(e);
    }
}

document.getElementById('detailSiswaSearch').addEventListener('input', (e) => {
    stateDetailSiswa.search = e.target.value;
    stateDetailSiswa.page = 1;
    loadDetailSiswa();
});

// Modal Tambah Siswa
function openAddSiswaModal() {
    if (!stateDetailSiswa.kelas_id) {
        saShowToast('Pilih kelas terlebih dahulu', 'error');
        return;
    }
    const headerText = document.getElementById('detailKelasNama').innerText;
    const className = headerText.replace('Detail: ', '');
    document.getElementById('addSiswaKelasLabel').innerText = className;
    
    document.getElementById('formAddSiswa').reset();
    document.getElementById('saModalAddSiswa').classList.add('show');
}

document.getElementById('formAddSiswa').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!stateDetailSiswa.kelas_id) {
        saShowToast('ID kelas tidak ditemukan. Silakan buka ulang kelas.', 'error');
        return;
    }
    const fd = new FormData(e.target);
    fd.append('kelas_id', stateDetailSiswa.kelas_id);
    
    saShowLoader();
    try {
        const res = await fetch(`${API_BASE}/students.php?action=create`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            saShowToast(data.message || 'Siswa berhasil ditambahkan', 'success');
            document.getElementById('saModalAddSiswa').classList.remove('show');
            loadDetailSiswa();
            
            fetch(`${API_BASE}/transactions.php?action=balance&kelas_id=${stateDetailSiswa.kelas_id}`)
                .then(r => r.json().catch(() => ({})))
                .then(d => { if(d.status === 'success') document.getElementById('detailKelasSaldo').innerText = formatRp(d.balance); });
                
        } else if (res.status === 401) {
            saHandleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal menambahkan siswa');
            saShowToast(errorMsg, 'error');
        }
    } catch(err) {
        saShowToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        saHideLoader();
    }
});

// Modal Global Tambah Siswa (Aksi Cepat)
function openGlobalAddSiswaModal() {
    if (optionsKelas.length === 0) {
        saShowToast('Harap buat kelas terlebih dahulu', 'error');
        return;
    }
    document.getElementById('formGlobalAddSiswa').reset();
    document.getElementById('saModalGlobalAddSiswa').classList.add('show');
}

document.getElementById('formGlobalAddSiswa').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    saShowLoader();
    try {
        const res = await fetch(`${API_BASE}/students.php?action=create`, { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            saShowToast(data.message || 'Siswa berhasil ditambahkan', 'success');
            document.getElementById('saModalGlobalAddSiswa').classList.remove('show');
            loadSADashboard();
        } else if (res.status === 401) {
            saHandleAuthExpired();
        } else {
            const errorMsg = data.message || (data.errors ? data.errors.join(', ') : 'Gagal menambahkan siswa');
            saShowToast(errorMsg, 'error');
        }
    } catch(err) {
        saShowToast('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
    } finally {
        saHideLoader();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkSuperAdminAuth();
});
