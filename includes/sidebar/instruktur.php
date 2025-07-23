<?php
$activePage = $activePage ?? '';
$baseURL = $baseURL ?? './'; // default: jika tidak di-set, gunakan './'
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
            <a class="nav-link <?= ($activePage == 'dashboard') ? 'active' : '' ?>" href="<?= $baseURL ?>dashboard.php">
                <i class="bi bi-house-door-fill me-2"></i> Dashboard
            </a>
        </li>

        <!-- KELAS SAYA Category -->
        <li class="nav-item mb-1 mt-3">
            <div class="menu-category">
                <small class="menu-category-label">
                    KELAS SAYA
                </small>
            </div>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'kelas-diampu') ? 'active' : '' ?>" href="<?= $baseURL ?>kelas/index.php">
                <i class="bi bi-building me-2"></i> Kelas Diampu
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'siswa-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>siswa/index.php">
                <i class="bi bi-people me-2"></i> Siswa Saya
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'jadwal-mengajar') ? 'active' : '' ?>" href="<?= $baseURL ?>jadwal/index.php">
                <i class="bi bi-calendar-event me-2"></i> Jadwal Mengajar
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'materi-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>materi/index.php">
                <i class="bi bi-journal-text me-2"></i> Materi Kelas
            </a>
        </li>

        <!-- PENILAIAN & ABSENSI Category -->
        <li class="nav-item mb-1 mt-3">
            <div class="menu-category">
                <small class="menu-category-label">
                    ABSENSI & PENILAIAN
                </small>
            </div>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'absensi-instruktur') ? 'active' : '' ?>" href="<?= $baseURL ?>absensi_instruktur/index.php">
                <i class="bi bi-person-check me-2"></i> Absensi Saya
            </a>
        </li>

         <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'absensi') ? 'active' : '' ?>" href="<?= $baseURL ?>absensi/index.php">
                <i class="bi bi-clipboard-check me-2"></i> Absensi Kelas
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'kelola-nilai') ? 'active' : '' ?>" href="<?= $baseURL ?>nilai/index.php">
                <i class="bi bi-pencil-square me-2"></i> Kelola Nilai
            </a>
        </li>

        <!-- EVALUASI Category -->
        <li class="nav-item mb-1 mt-3">
            <div class="menu-category">
                <small class="menu-category-label">
                    EVALUASI
                </small>
            </div>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'hasil-evaluasi') ? 'active' : '' ?>" href="<?= $baseURL ?>evaluasi/index.php">
                <i class="bi bi-graph-up me-2"></i> Hasil Evaluasi
            </a>
        </li>

        <!-- LAPORAN Category -->
        <li class="nav-item mb-1 mt-3">
            <div class="menu-category">
                <small class="menu-category-label">
                    LAPORAN
                </small>
            </div>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'laporan-kelas') ? 'active' : '' ?>" href="<?= $baseURL ?>laporan/kelas.php">
                <i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan Kelas
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'laporan-nilai') ? 'active' : '' ?>" href="<?= $baseURL ?>laporan/nilai.php">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i> Laporan Nilai
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'laporan-absensi') ? 'active' : '' ?>" href="<?= $baseURL ?>laporan/absensi.php">
                <i class="bi bi-file-earmark-check me-2"></i> Laporan Absensi
            </a>
        </li>

        <!-- AKUN Category -->
        <li class="nav-item mb-1 mt-3">
            <div class="menu-category">
                <small class="menu-category-label">
                    AKUN
                </small>
            </div>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'profil') ? 'active' : '' ?>" href="<?= $baseURL ?>profil/index.php">
                <i class="bi bi-person-fill me-2"></i> Profil Saya
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link text-danger" href="<?= $baseURL ?>../auth/logout.php">
                <i class="bi bi-box-arrow-left me-2"></i> Keluar
            </a>
        </li>
    </ul>
</div>