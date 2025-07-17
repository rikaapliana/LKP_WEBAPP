<?php
$activePage = $activePage ?? '';
$baseURL = $baseURL ?? './'; // default: jika tidak di-set, gunakan './'

// Halaman yang masuk dalam grup Akademik Saya
$akademikSayaPages = ['nilai-saya', 'jadwal-saya', 'materi-saya', 'absensi-saya'];

// Halaman yang masuk dalam grup Evaluasi & Feedback
$evaluasiPages = ['evaluasi-instruktur', 'feedback-kelas'];

// Fungsi pengecekan apakah halaman aktif merupakan bagian dari grup tertentu
function isGroupSubPage($activePage, $groupPages) {
    return in_array($activePage, $groupPages);
}

// Tentukan apakah setiap grup menu aktif berdasarkan halaman aktif
$isAkademikSayaActive = isGroupSubPage($activePage, $akademikSayaPages);
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

        <!-- AKADEMIK SAYA Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    AKADEMIK SAYA
                </small>
            </div>
        </li>

        <!-- Akademik Saya Toggle -->
        <li class="nav-item">
            <a id="toggle-akademiksaya" class="nav-link d-flex justify-content-between align-items-center toggle-submenu" href="javascript:void(0);">
                <span><i class="bi bi-book-fill me-2"></i> Data Akademik</span>
                <i class="submenu-caret <?= $isAkademikSayaActive ? 'rotate' : '' ?>">&gt;</i>
            </a>

            <ul class="nav flex-column submenu <?= $isAkademikSayaActive ? 'show' : '' ?>">
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'nilai-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>nilai/index.php">
                        <i class="bi bi-trophy me-2"></i> Nilai Saya
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'jadwal-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>jadwal/index.php">
                        <i class="bi bi-calendar-event me-2"></i> Jadwal Kelas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'materi-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>materi/index.php">
                        <i class="bi bi-journal-text me-2"></i> Materi Kelas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'absensi-saya') ? 'active' : '' ?>" href="<?= $baseURL ?>absensi/index.php">
                        <i class="bi bi-calendar-check me-2"></i> Absensi Saya
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
                    <a class="nav-link submenu-link <?= ($activePage == 'evaluasi-instruktur') ? 'active' : '' ?>" href="<?= $baseURL ?>evaluasi/instruktur.php">
                        <i class="bi bi-person-badge me-2"></i> Evaluasi Instruktur
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link submenu-link <?= ($activePage == 'feedback-kelas') ? 'active' : '' ?>" href="<?= $baseURL ?>evaluasi/kelas.php">
                        <i class="bi bi-chat-square-text me-2"></i> Feedback Kelas
                    </a>
                </li>
            </ul>
        </li>

        <!-- INFORMASI Category -->
        <li class="nav-item mb-1 mt-2">
            <div class="menu-category">
                <small class="menu-category-label">
                    INFORMASI
                </small>
            </div>
        </li>

        <!-- Pengumuman (menu biasa) -->
        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'pengumuman') ? 'active' : '' ?>" href="<?= $baseURL ?>pengumuman/index.php">
                <i class="bi bi-megaphone-fill me-2"></i> Pengumuman
            </a>
        </li>

        <!-- Kontak (menu biasa) -->
        <li class="nav-item">
            <a class="nav-link <?= ($activePage == 'kontak') ? 'active' : '' ?>" href="<?= $baseURL ?>kontak/index.php">
                <i class="bi bi-telephone-fill me-2"></i> Kontak LKP
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