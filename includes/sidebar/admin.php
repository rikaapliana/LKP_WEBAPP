<?php
$activePage = $activePage ?? '';
$baseURL = $baseURL ?? './';

// Halaman yang masuk dalam grup Data Master - urutan diperbaiki
$dataMasterPages = ['pengguna', 'instruktur', 'kelas', 'materi'];

// Halaman yang masuk dalam grup Data Pendaftaran - BARU!
$dataPendaftaranPages = ['pendaftar', 'gelombang'];

// Halaman yang masuk dalam grup Data Akademik - tanpa pendaftar
$dataAkademikPages = ['siswa', 'jadwal', 'absensi', 'nilai'];

// Halaman yang masuk dalam grup Laporan - tambah 'laporan' untuk dashboard
$laporanPages = ['laporan', 'laporan-instruktur', 'laporan-kelas', 'laporan-pengguna', 'laporan-pendaftar', 'laporan-siswa', 'laporan-jadwal', 'laporan-nilai', 'laporan-absensi', 'laporan-hasil-evaluasi', 'laporan-rekap'];

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
$isDataPendaftaranActive = isGroupSubPage($activePage, $dataPendaftaranPages);
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
        <!-- MENU UTAMA Category -->
        <li class="nav-item mb-1">
            <div class="menu-category">
                <small class="menu-category-label">
                    MENU UTAMA
                </small>
            </div>
        </li>

        <li class="nav-item">
            <a id="link-dashboard" class="nav-link <?= ($activePage == 'dashboard') ? 'active' : '' ?>" href="<?= $baseURL ?>dashboard.php">
                <i class="bi bi-house-door-fill me-2"></i> Dashboard
            </a>
        </li>

        <!-- MANAJEMEN DATA Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    MANAJEMEN DATA
                </small>
            </div>
        </li>

        <!-- Data Master Toggle - tanpa icon di submenu -->
        <li class="nav-item">
            <a id="toggle-datamaster" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-folder-fill me-2"></i> Data Master</span>
                <i class="submenu-caret <?= $isDataMasterActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isDataMasterActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'pengguna') ? 'active' : '' ?>" href="<?= $baseURL ?>pengguna/index.php">
                        Data Pengguna
                    </a>
                </li>
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

        <!-- Data Pendaftaran Toggle - BARU! -->
        <li class="nav-item">
            <a id="toggle-datapendaftaran" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-person-plus-fill me-2"></i> Data Pendaftaran</span>
                <i class="submenu-caret <?= $isDataPendaftaranActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isDataPendaftaranActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'pendaftar') ? 'active' : '' ?>" href="<?= $baseURL ?>pendaftar/index.php">
                        Data Pendaftar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'gelombang') ? 'active' : '' ?>" href="<?= $baseURL ?>pengaturan/gelombang/index.php">
                        Data Gelombang
                    </a>
                </li>
            </ul>
        </li>

        <!-- Data Akademik Toggle - tanpa pendaftar, tanpa icon di submenu -->
        <li class="nav-item">
            <a id="toggle-dataakademik" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-mortarboard-fill me-2"></i> Data Akademik</span>
                <i class="submenu-caret <?= $isDataAkademikActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isDataAkademikActive ? 'show' : '' ?>">
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
                    <a class="nav-link submenu-link <?= ($activePage == 'absensi') ? 'active' : '' ?>" href="<?= $baseURL ?>absensi/index.php">
                        Data Absensi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'nilai') ? 'active' : '' ?>" href="<?= $baseURL ?>nilai/index.php">
                        Data Nilai
                    </a>
                </li>
            </ul>
        </li>

        <!-- EVALUASI & UMPAN BALIK Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    MANAJEMEN EVALUASI
                </small>
            </div>
        </li>

        <!-- Manajemen Evaluasi Toggle -->
        <li class="nav-item">
            <a id="toggle-manajemenevaluasi" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-clipboard-check-fill me-2"></i> Kelola Evaluasi</span>
                <i class="submenu-caret <?= $isManajemenEvaluasiActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isManajemenEvaluasiActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>evaluasi/periode/index.php">
                        Periode Evaluasi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'hasil-evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>hasil-evaluasi/index.php">
                        Hasil Evaluasi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'analisis-evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>analisis-evaluasi/index.php">
                        Analisis & Grafik
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

        <!-- Menu Laporan - SEDERHANA -->
        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'laporan') ? 'active' : '' ?>" href="<?= $baseURL ?>laporan/">
                <i class="bi bi-file-earmark-bar-graph me-2"></i> Pusat Laporan
            </a>
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