<?php
// File: pages/instruktur/dashboard.php

require_once '../../includes/auth.php';
requireInstrukturAuth(); // Hanya instruktur dan admin yang bisa akses

require_once '../../includes/db.php';

// Ambil data instruktur yang login
$stmt = $conn->prepare("
    SELECT i.*, COUNT(DISTINCT k.id_kelas) as total_kelas_diampu
    FROM instruktur i
    LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur
    WHERE i.id_user = ?
    GROUP BY i.id_instruktur
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$instrukturData = $stmt->get_result()->fetch_assoc();

// Ambil kelas yang diampu instruktur
$kelasStmt = $conn->prepare("
    SELECT k.*, g.nama_gelombang, g.status as status_gelombang,
           COUNT(DISTINCT s.id_siswa) as jumlah_siswa
    FROM kelas k
    LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
    LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
    WHERE k.id_instruktur = ?
    GROUP BY k.id_kelas
    ORDER BY k.id_kelas DESC
");
$kelasStmt->bind_param("i", $instrukturData['id_instruktur']);
$kelasStmt->execute();
$kelasResult = $kelasStmt->get_result();

// Ambil jadwal hari ini dan mendatang
$jadwalStmt = $conn->prepare("
    SELECT j.*, k.nama_kelas, DATE(j.tanggal) as tgl_jadwal
    FROM jadwal j
    JOIN kelas k ON j.id_kelas = k.id_kelas
    WHERE j.id_instruktur = ? AND j.tanggal >= CURDATE()
    ORDER BY j.tanggal ASC, j.waktu_mulai ASC
    LIMIT 5
");
$jadwalStmt->bind_param("i", $instrukturData['id_instruktur']);
$jadwalStmt->execute();
$jadwalResult = $jadwalStmt->get_result();

// Statistik
$totalSiswa = 0;
$totalMateri = 0;
$jadwalHariIni = 0;

// Hitung total siswa dari semua kelas yang diampu
$totalSiswaStmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id_siswa) as total
    FROM siswa s
    JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE k.id_instruktur = ? AND s.status_aktif = 'aktif'
");
$totalSiswaStmt->bind_param("i", $instrukturData['id_instruktur']);
$totalSiswaStmt->execute();
$totalSiswa = $totalSiswaStmt->get_result()->fetch_assoc()['total'];

// Hitung total materi yang dibuat
$totalMateriStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM materi m
    JOIN kelas k ON m.id_kelas = k.id_kelas
    WHERE k.id_instruktur = ?
");
$totalMateriStmt->bind_param("i", $instrukturData['id_instruktur']);
$totalMateriStmt->execute();
$totalMateri = $totalMateriStmt->get_result()->fetch_assoc()['total'];

// Hitung jadwal hari ini
$jadwalHariIniStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM jadwal j
    WHERE j.id_instruktur = ? AND DATE(j.tanggal) = CURDATE()
");
$jadwalHariIniStmt->bind_param("i", $instrukturData['id_instruktur']);
$jadwalHariIniStmt->execute();
$jadwalHariIni = $jadwalHariIniStmt->get_result()->fetch_assoc()['total'];

// Ambil absensi hari ini
$absensiHariIniStmt = $conn->prepare("
    SELECT j.id_jadwal, j.tanggal, j.waktu_mulai, j.waktu_selesai, k.nama_kelas,
           COUNT(ab.id_absen) as total_absen,
           SUM(CASE WHEN ab.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
           COUNT(s.id_siswa) as total_siswa_kelas
    FROM jadwal j
    JOIN kelas k ON j.id_kelas = k.id_kelas
    LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
    LEFT JOIN absensi_siswa ab ON ab.id_jadwal = j.id_jadwal
    WHERE j.id_instruktur = ? AND DATE(j.tanggal) = CURDATE()
    GROUP BY j.id_jadwal
");
$absensiHariIniStmt->bind_param("i", $instrukturData['id_instruktur']);
$absensiHariIniStmt->execute();
$absensiHariIniResult = $absensiHariIniStmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Instruktur - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon.png"/>
    
    <!-- Offline Bootstrap CSS -->
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css" />
    
    <!-- Offline Bootstrap Icons -->
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css" />
    
    <!-- Offline Poppins Font -->
    <link rel="stylesheet" href="../../assets/css/fonts.css" />
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="../../assets/css/styles.css" />
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../../includes/sidebar/instruktur.php'; ?>

        <!-- Main Content -->
        <div class="flex-fill dashboard-bg">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <div class="container-fluid px-3 px-md-4">
                    <div class="d-flex align-items-center">
                        <!-- Left section - Hamburger Menu (hanya tampil di mobile) -->
                        <div class="d-flex align-items-center">
                            <button class="btn sidebar-toggle me-3" type="button" id="sidebarToggle">
                                <i class="bi bi-list fs-5"></i>
                            </button>
                        </div>
                        
                        <!-- Spacer untuk push content ke kanan -->
                        <div class="flex-grow-1"></div>
                        
                        <!-- Right section -->
                        <div class="d-flex align-items-center">
                            <!-- Notifications -->
                            <div class="navbar-notifications me-2">
                                <button class="btn" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-bell fs-5"></i>
                                    <?php if ($jadwalHariIni > 0): ?>
                                    <span class="notification-badge"><?= $jadwalHariIni ?></span>
                                    <?php endif; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li class="dropdown-header">Notifikasi</li>
                                    <?php if ($jadwalHariIni > 0): ?>
                                    <li>
                                        <a class="dropdown-item" href="#jadwal">
                                            <i class="bi bi-calendar-check text-info me-2"></i>
                                            <strong><?= $jadwalHariIni ?></strong> jadwal mengajar hari ini
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($totalSiswa > 0): ?>
                                    <li>
                                        <a class="dropdown-item" href="#siswa">
                                            <i class="bi bi-people text-success me-2"></i>
                                            <strong><?= $totalSiswa ?></strong> siswa aktif
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($jadwalHariIni == 0): ?>
                                    <li>
                                        <span class="dropdown-item text-muted">Tidak ada jadwal hari ini</span>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            
                            <!-- User info -->
                            <div class="navbar-user-info">
                                <div class="navbar-user-avatar">
                                    <?= strtoupper(substr($instrukturData['nama'] ?? 'I', 0, 1)) ?>
                                </div>
                                <div class="navbar-user-details">
                                    <span class="navbar-user-name"><?= htmlspecialchars($instrukturData['nama'] ?? $_SESSION['username']) ?></span>
                                    <span class="navbar-user-role">Instruktur</span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-link text-dark p-0" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profil</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Pengaturan</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
            
            <div class="p-3 p-md-4">
                <!-- Welcome Section -->
                <div class="welcome-card corporate">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-2 font-roboto">Selamat Datang, <?= htmlspecialchars($instrukturData['nama'] ?? $_SESSION['username']) ?></h2>
                            <p class="mb-0 opacity-90">Anda sedang login sebagai <strong>Instruktur</strong><br>
                            <strong>LKP Pradata Komputer Kabupaten Tabalong</strong></p>
                        </div>
                        <div class="col-md-4 text-end d-none d-md-block">
                            <i class="bi bi-person-workspace fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>

                <!-- Statistik Cards -->
                <div class="row g-3 g-md-4 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-4">
                                <div class="stats-icon stats-primary text-white">
                                    <i class="bi bi-building-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1"><?= $instrukturData['total_kelas_diampu'] ?? 0 ?></h3>
                                <p class="text-muted mb-0">Kelas Diampu</p>
                                <small class="text-info"><i class="bi bi-book"></i> Total kelas</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-4">
                                <div class="stats-icon stats-success text-white">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1"><?= $totalSiswa ?></h3>
                                <p class="text-muted mb-0">Total Siswa</p>
                                <small class="text-success"><i class="bi bi-check-circle"></i> Aktif</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-4">
                                <div class="stats-icon stats-warning text-white">
                                    <i class="bi bi-journal-text"></i>
                                </div>
                                <h3 class="fw-bold mb-1"><?= $totalMateri ?></h3>
                                <p class="text-muted mb-0">Materi Dibuat</p>
                                <small class="text-warning"><i class="bi bi-file-text"></i> Total materi</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-4">
                                <div class="stats-icon stats-danger text-white">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1"><?= $jadwalHariIni ?></h3>
                                <p class="text-muted mb-0">Jadwal Hari Ini</p>
                                <small class="<?= $jadwalHariIni > 0 ? 'text-info' : 'text-muted' ?>">
                                    <i class="bi bi-<?= $jadwalHariIni > 0 ? 'clock' : 'calendar-x' ?>"></i> 
                                    <?= $jadwalHariIni > 0 ? 'Ada jadwal' : 'Tidak ada jadwal' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

               
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>
    
    <!-- Scripts - Offline -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/scripts.js"></script>
</body>
</html>