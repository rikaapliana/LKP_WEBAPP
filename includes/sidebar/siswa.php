<?php
$activePage = $activePage ?? '';
$baseURL = $baseURL ?? './';
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

        <!-- AKADEMIK Category -->
        <li class="nav-item mb-1 mt-3">
            <div class="menu-category">
                <small class="menu-category-label">
                    AKADEMIK
                </small>
            </div>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'jadwal-kelas') ? 'active' : '' ?>" href="<?= $baseURL ?>jadwal/index.php">
                <i class="bi bi-calendar-event me-2"></i> Jadwal Kelas
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'materi-kelas') ? 'active' : '' ?>" href="<?= $baseURL ?>materi/index.php">
                <i class="bi bi-journal-text me-2"></i> Materi Kelas
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'nilai-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>nilai/index.php">
                <i class="bi bi-award me-2"></i> Nilai Saya
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'absensi-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>absensi/index.php">
                <i class="bi bi-person-check me-2"></i> Absensi Saya
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
            <a class="nav-link <?= ($activePage == 'evaluasi-pembelajaran') ? 'active' : '' ?>" href="<?= $baseURL ?>evaluasi/index.php">
                <i class="bi bi-clipboard-check-fill me-2"></i> Evaluasi & Feedback
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
            <a class="nav-link <?= ($activePage == 'profil-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>profil/index.php">
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