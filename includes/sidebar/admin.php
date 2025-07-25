<?php
$activePage = $activePage ?? '';
$baseURL = $baseURL ?? './';

// Halaman yang masuk dalam grup Data Master
$dataMasterPages = ['instruktur', 'kelas', 'materi'];

// Halaman yang masuk dalam grup Data Akademik  
$dataAkademikPages = ['pendaftar', 'siswa', 'jadwal', 'nilai'];

// Halaman yang masuk dalam grup Laporan (digabung)
$laporanPages = ['laporan-instruktur', 'laporan-kelas', 'laporan-materi', 'laporan-pendaftar', 'laporan-siswa', 'laporan-jadwal', 'laporan-nilai', 'laporan-hasil-evaluasi', 'laporan-rekap'];

// Fungsi pengecekan dengan proteksi duplikat
if (!function_exists('isGroupSubPage')) {
    function isGroupSubPage($activePage, $groupPages) {
        return in_array($activePage, $groupPages);
    }
}

// Fungsi khusus untuk evaluasi dengan proteksi duplikat
if (!function_exists('isEvaluasiGroupActive')) {
    function isEvaluasiGroupActive($activePage) {
        $evaluasiPages = ['evaluasi', 'hasil-evaluasi', 'analisis-evaluasi'];
        return in_array($activePage, $evaluasiPages);
    }
}

// Tentukan apakah setiap grup menu aktif berdasarkan halaman aktif
$isDataMasterActive = isGroupSubPage($activePage, $dataMasterPages);
$isDataAkademikActive = isGroupSubPage($activePage, $dataAkademikPages);
$isManajemenEvaluasiActive = isEvaluasiGroupActive($activePage);
$isLaporanActive = isGroupSubPage($activePage, $laporanPages);
?>

<div class="sidebar p-3">
    <!-- Sidebar Header -->
    <div class="sidebar-header text-center mb-2 p-2 rounded" style="background: linear-gradient(135deg, #667eea 0%,rgb(170, 133, 207) 100%); box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div class="logo-container mb-1">
            <img src="<?= $baseURL ?>../../assets/img/favicon.png" 
                 alt="Logo LKP" 
                 style="width: 45px; height: 45px; object-fit: contain; border-radius: 8px; background: rgba(255,255,255,0.1); padding: 5px;" 
                 class="logo-img">
        </div>
        <div class="fw-bold text-white" style="font-size: 10px; line-height: 2; letter-spacing: 0.5px;">
            LKP PRADATA KOMPUTER<br>
        </div>
        <div class="text-white" style="font-size: 8px; line-height: 1.1; letter-spacing: 0.5px;">
            Kabupaten Tabalong<br>
        </div>
    </div>

    <ul class="nav flex-column">
        <!-- MAIN MENU Category -->
        <li class="nav-item mb-1">
            <div class="menu-category">
                <small class="menu-category-label">
                    MAIN MENU
                </small>
            </div>
        </li>

        <li class="nav-item">
            <a id="link-dashboard" class="nav-link <?= ($activePage == 'dashboard') ? 'active' : '' ?>" href="<?= $baseURL ?>dashboard.php">
                <i class="bi bi-house-door-fill me-2"></i> Dashboard
            </a>
        </li>

        <!-- DATA MANAGEMENT Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    DATA MANAGEMENT
                </small>
            </div>
        </li>

        <!-- Data Master Toggle -->
        <li class="nav-item">
            <a id="toggle-datamaster" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                <i class="submenu-caret <?= $isDataMasterActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isDataMasterActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'instruktur') ? 'active' : '' ?>" href="<?= $baseURL ?>instruktur/index.php">
                        Data Instruktur
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'kelas') ? 'active' : '' ?>" href="<?= $baseURL ?>kelas/index.php">
                        Data Kelas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'materi') ? 'active' : '' ?>" href="<?= $baseURL ?>materi/index.php">
                        Data Materi
                    </a>
                </li>
            </ul>
        </li>

        <!-- Data Akademik Toggle -->
        <li class="nav-item">
            <a id="toggle-dataakademik" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-book-fill me-2"></i> Data Akademik</span>
                <i class="submenu-caret <?= $isDataAkademikActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isDataAkademikActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'pendaftar') ? 'active' : '' ?>" href="<?= $baseURL ?>pendaftar/index.php">
                        Data Pendaftar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'siswa') ? 'active' : '' ?>" href="<?= $baseURL ?>siswa/index.php">
                        Data Siswa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'jadwal') ? 'active' : '' ?>" href="<?= $baseURL ?>jadwal/index.php">
                        Data Jadwal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'nilai') ? 'active' : '' ?>" href="<?= $baseURL ?>nilai/index.php">
                        Data Nilai
                    </a>
                </li>
            </ul>
        </li>

        <!-- EVALUASI & FEEDBACK Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    EVALUASI & FEEDBACK
                </small>
            </div>
        </li>

        <!-- Manajemen Evaluasi Toggle -->
        <li class="nav-item">
            <a id="toggle-manajemenevaluasi" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-clipboard-check-fill me-2"></i> Evaluasi</span>
                <i class="submenu-caret <?= $isManajemenEvaluasiActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isManajemenEvaluasiActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>evaluasi/periode/index.php">
                        <i class="bi bi-question-circle me-1"></i> Kelola Pertanyaan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'hasil-evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>hasil-evaluasi/index.php">
                        <i class="bi bi-list-check me-1"></i> Hasil Evaluasi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'analisis-evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>analisis-evaluasi/index.php">
                        <i class="bi bi-graph-up me-1"></i> Analisis & Grafik
                    </a>
                </li>
            </ul>
        </li>

        <!-- LAPORAN Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    LAPORAN
                </small>
            </div>
        </li>

        <!-- Laporan Toggle (Gabungan) -->
        <li class="nav-item">
            <a id="toggle-laporan" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan</span>
                <i class="submenu-caret <?= $isLaporanActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isLaporanActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'laporan-instruktur') ? 'active' : '' ?>" href="<?= $baseURL ?>instruktur/cetak_laporan.php">
                       Laporan Instruktur
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'laporan-kelas') ? 'active' : '' ?>" href="<?= $baseURL ?>kelas/cetak_laporan.php">
                        Laporan Kelas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'laporan-pendaftar') ? 'active' : '' ?>" href="<?= $baseURL ?>pendaftar/cetak_laporan.php">
                        Laporan Pendaftar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'laporan-siswa') ? 'active' : '' ?>" href="<?= $baseURL ?>siswa/cetak_laporan.php">
                        Laporan Siswa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'laporan-jadwal') ? 'active' : '' ?>" href="<?= $baseURL ?>jadwal/cetak_laporan.php">
                        Laporan Jadwal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'laporan-nilai') ? 'active' : '' ?>" href="<?= $baseURL ?>nilai/cetak_laporan.php">
                        Laporan Nilai
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'laporan-hasil-evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>analisis-evaluasi/index.php">
                        Laporan Hasil Evaluasi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'laporan-rekap') ? 'active' : '' ?>" href="<?= $baseURL ?>rekap-data/cetak_laporan.php">
                        Laporan Rekap Data
                    </a>
                </li>
            </ul>
        </li>

        <!-- AKUN Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    AKUN
                </small>
            </div>
        </li>

        <!-- Profil (menu biasa) -->
        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'profil') ? 'active' : '' ?>" href="<?= $baseURL ?>profil/index.php">
                <i class="bi bi-person-fill me-2"></i> Profil
            </a>
        </li>

        <!-- Keluar (menu biasa) -->
        <li class="nav-item">
            <a class="nav-link text-danger" href="<?= $baseURL ?>../auth/logout.php">
                <i class="bi bi-box-arrow-left me-2"></i> Keluar
            </a>
        </li>
    </ul>
</div>