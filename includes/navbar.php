<?php
// File: includes/navbar.php - Untuk akses public

$current_page = basename($_SERVER['PHP_SELF']);
$base_url = "/lkp_webapp"; // Sesuaikan jika nama folder Anda berbeda
?>

<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= $base_url ?>/index.php">
            <i class="bi bi-mortarboard-fill"></i>
            LKP Pradata Komputer
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="<?= $base_url ?>/index.php#home">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>/index.php#kurikulum">Program</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'pendaftaran.php') ? 'active' : '' ?>" href="<?= $base_url ?>/pendaftaran.php">Pendaftaran</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'bantuan.php') ? 'active' : '' ?>" href="<?= $base_url ?>/bantuan.php">Bantuan</a>
                </li>
                
                <li class="nav-item">
                    <div class="d-flex gap-2">
                        <a class="btn btn-primary" href="<?= $base_url ?>/pages/auth/login.php">
                            Masuk
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>