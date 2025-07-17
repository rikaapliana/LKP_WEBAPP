<?php
$activePage = $activePage ?? '';
$baseURL = $baseURL ?? './'; // default: jika tidak di-set, gunakan './'

// Halaman yang masuk dalam grup Kelas Saya
$kelasSayaPages = ['kelas-diampu', 'siswa-saya', 'jadwal-mengajar', 'materi-saya'];

// Halaman yang masuk dalam grup Penilaian
$penilaianPages = ['input-nilai', 'rekap-nilai', 'absensi-kelas'];

// Halaman yang masuk dalam grup Evaluasi
$evaluasiPages = ['hasil-evaluasi', 'feedback-siswa'];

// Fungsi pengecekan apakah halaman aktif merupakan bagian dari grup tertentu
function isGroupSubPage($activePage, $groupPages) {
    return in_array($activePage, $groupPages);
}

// Tentukan apakah setiap grup menu aktif berdasarkan halaman aktif
$isKelasSayaActive = isGroupSubPage($activePage, $kelasSayaPages);
$isPenilaianActive = isGroupSubPage($activePage, $penilaianPages);
$isEvaluasiActive = isGroupSubPage($activePage, $evaluasiPages);
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

        <!-- KELAS SAYA Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    KELAS SAYA
                </small>
            </div>
        </li>

        <!-- Kelas Saya Toggle -->
        <li class="nav-item">
            <a id="toggle-kelassaya" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-building-fill me-2"></i> Manajemen Kelas</span>
                <i class="submenu-caret <?= $isKelasSayaActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isKelasSayaActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'kelas-diampu') ? 'active' : '' ?>" href="<?= $baseURL ?>kelas/index.php">
                        <i class="bi bi-building me-2"></i> Kelas Diampu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'siswa-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>siswa/index.php">
                        <i class="bi bi-people me-2"></i> Siswa Saya
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'jadwal-mengajar') ? 'active' : '' ?>" href="<?= $baseURL ?>jadwal/index.php">
                        <i class="bi bi-calendar-event me-2"></i> Jadwal Mengajar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'materi-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>materi/index.php">
                        <i class="bi bi-journal-text me-2"></i> Materi Kelas
                    </a>
                </li>
            </ul>
        </li>

        <!-- PENILAIAN & ABSENSI Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    PENILAIAN & ABSENSI
                </small>
            </div>
        </li>

        <!-- Penilaian Toggle -->
        <li class="nav-item">
            <a id="toggle-penilaian" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-trophy-fill me-2"></i> Penilaian</span>
                <i class="submenu-caret <?= $isPenilaianActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isPenilaianActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'input-nilai') ? 'active' : '' ?>" href="<?= $baseURL ?>nilai/input.php">
                        <i class="bi bi-pencil-square me-2"></i> Input Nilai
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'rekap-nilai') ? 'active' : '' ?>" href="<?= $baseURL ?>nilai/rekap.php">
                        <i class="bi bi-bar-chart me-2"></i> Rekap Nilai
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'absensi-kelas') ? 'active' : '' ?>" href="<?= $baseURL ?>absensi/index.php">
                        <i class="bi bi-clipboard-check me-2"></i> Absensi Kelas
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

        <!-- Evaluasi Toggle -->
        <li class="nav-item">
            <a id="toggle-evaluasi" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-star-fill me-2"></i> Evaluasi</span>
                <i class="submenu-caret <?= $isEvaluasiActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isEvaluasiActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'hasil-evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>evaluasi/hasil.php">
                        <i class="bi bi-graph-up me-2"></i> Hasil Evaluasi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'feedback-siswa') ? 'active' : '' ?>" href="<?= $baseURL ?>evaluasi/feedback.php">
                        <i class="bi bi-chat-square-dots me-2"></i> Feedback Siswa
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

        <!-- Laporan Kelas (menu biasa) -->
        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'laporan-kelas') ? 'active' : '' ?>" href="<?= $baseURL ?>laporan/kelas.php">
                <i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Kelas
            </a>
        </li>

        <!-- Laporan Nilai (menu biasa) -->
        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'laporan-nilai') ? 'active' : '' ?>" href="<?= $baseURL ?>laporan/nilai.php">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i> Laporan Nilai
            </a>
        </li>

        <!-- Laporan Absensi (menu biasa) -->
        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'laporan-absensi') ? 'active' : '' ?>" href="<?= $baseURL ?>laporan/absensi.php">
                <i class="bi bi-file-earmark-check me-2"></i> Laporan Absensi
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
                <i class="bi bi-person-fill me-2"></i> Profil Saya
            </a>
        </li>

        <!-- Ganti Password (menu biasa) -->
        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'ganti-password') ? 'active' : '' ?>" href="<?= $baseURL ?>password/index.php">
                <i class="bi bi-shield-lock-fill me-2"></i> Ganti Password
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