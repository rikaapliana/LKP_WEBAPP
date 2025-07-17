<?php
// File: includes/navbar.php - Untuk akses public

$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/lkp_webapp/index.php">
            <i class="bi bi-mortarboard-fill"></i>
            LKP Pradata Komputer
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="/lkp_webapp/index.php#home">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/lkp_webapp/index.php#features">Tentang</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'pendaftaran.php') ? 'active' : '' ?>" href="/lkp_webapp/pendaftaran.php">Pendaftaran</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/lkp_webapp/index.php#contact">Kontak</a>
                </li>
                
                <!-- Public Authentication Buttons -->
                <li class="nav-item">
                    <div class="d-flex gap-2">
                        <a class="btn btn-primary" href="/lkp_webapp/pages/auth/login.php">
                            Masuk
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>