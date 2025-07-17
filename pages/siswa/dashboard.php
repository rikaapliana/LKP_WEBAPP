<?php
// File: pages/siswa/dashboard.php

require_once '../../includes/auth.php';
requireSiswaAuth(); // Hanya siswa dan admin yang bisa akses

require_once '../../includes/db.php';

// Ambil data siswa yang login
$stmt = $conn->prepare("
    SELECT s.*, k.nama_kelas, g.nama_gelombang, i.nama as nama_instruktur
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
    LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
    WHERE s.id_user = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$siswaData = $stmt->get_result()->fetch_assoc();

// Ambil data nilai siswa
$nilaiStmt = $conn->prepare("
    SELECT n.*, k.nama_kelas 
    FROM nilai n
    JOIN kelas k ON n.id_kelas = k.id_kelas
    WHERE n.id_siswa = ?
");
$nilaiStmt->bind_param("i", $siswaData['id_siswa']);
$nilaiStmt->execute();
$nilaiData = $nilaiStmt->get_result()->fetch_assoc();

// Ambil jadwal kelas siswa
$jadwalStmt = $conn->prepare("
    SELECT j.*, i.nama as nama_instruktur, k.nama_kelas
    FROM jadwal j
    JOIN instruktur i ON j.id_instruktur = i.id_instruktur
    JOIN kelas k ON j.id_kelas = k.id_kelas
    WHERE j.id_kelas = ? AND j.tanggal >= CURDATE()
    ORDER BY j.tanggal ASC, j.waktu_mulai ASC
    LIMIT 5
");
if ($siswaData['id_kelas']) {
    $jadwalStmt->bind_param("i", $siswaData['id_kelas']);
    $jadwalStmt->execute();
    $jadwalResult = $jadwalStmt->get_result();
}

// Data untuk statistik
$totalMateri = 0;
if ($siswaData['id_kelas']) {
    $materiStmt = $conn->prepare("SELECT COUNT(*) as total FROM materi WHERE id_kelas = ?");
    $materiStmt->bind_param("i", $siswaData['id_kelas']);
    $materiStmt->execute();
    $totalMateri = $materiStmt->get_result()->fetch_assoc()['total'];
}

// Statistik absensi siswa
$absensiStmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
        SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
        COUNT(*) as total_absensi
    FROM absensi_siswa 
    WHERE id_siswa = ?
");
$absensiStmt->bind_param("i", $siswaData['id_siswa']);
$absensiStmt->execute();
$absensiData = $absensiStmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Siswa - LKP Pradata Komputer</title>
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
        <?php include '../../includes/sidebar/siswa.php'; ?>

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
                                    <?php if ($jadwalResult && $jadwalResult->num_rows > 0): ?>
                                    <span class="notification-badge"><?= $jadwalResult->num_rows ?></span>
                                    <?php endif; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li class="dropdown-header">Notifikasi</li>
                                    <?php if (isset($jadwalResult) && $jadwalResult->num_rows > 0): ?>
                                    <li>
                                        <a class="dropdown-item" href="#jadwal">
                                            <i class="bi bi-calendar-check text-info me-2"></i>
                                            <strong><?= $jadwalResult->num_rows ?></strong> jadwal kelas mendatang
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($nilaiData): ?>
                                    <li>
                                        <a class="dropdown-item" href="#nilai">
                                            <i class="bi bi-trophy text-warning me-2"></i>
                                            Nilai kursus tersedia
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (!isset($jadwalResult) || $jadwalResult->num_rows == 0): ?>
                                    <li>
                                        <span class="dropdown-item text-muted">Tidak ada notifikasi baru</span>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            
                            <!-- User info -->
                            <div class="navbar-user-info">
                                <div class="navbar-user-avatar">
                                    <?= strtoupper(substr($siswaData['nama'] ?? 'S', 0, 1)) ?>
                                </div>
                                <div class="navbar-user-details">
                                    <span class="navbar-user-name"><?= htmlspecialchars($siswaData['nama'] ?? $_SESSION['username']) ?></span>
                                    <span class="navbar-user-role">Siswa</span>
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
                            <h2 class="mb-2 font-roboto">Selamat Datang, <?= htmlspecialchars($siswaData['nama'] ?? $_SESSION['username']) ?></h2>
                            <p class="mb-0 opacity-90">Anda sedang login sebagai <strong>Siswa</strong><br>
                            <strong>LKP Pradata Komputer Kabupaten Tabalong</strong></p>
                        </div>
                        <div class="col-md-4 text-end d-none d-md-block">
                            <i class="bi bi-mortarboard fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>

                <!-- Statistik Cards -->
                <div class="row g-3 g-md-4 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-4">
                                <div class="stats-icon stats-primary text-white">
                                    <i class="bi bi-person-check-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1"><?= ucfirst($siswaData['status_aktif'] ?? 'Tidak Diketahui') ?></h3>
                                <p class="text-muted mb-0">Status Siswa</p>
                                <small class="<?= ($siswaData['status_aktif'] ?? '') == 'aktif' ? 'text-success' : 'text-warning' ?>">
                                    <i class="bi bi-<?= ($siswaData['status_aktif'] ?? '') == 'aktif' ? 'check-circle' : 'exclamation-circle' ?>"></i> 
                                    <?= ($siswaData['status_aktif'] ?? '') == 'aktif' ? 'Aktif' : 'Perlu Aktivasi' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-4">
                                <div class="stats-icon stats-success text-white">
                                    <i class="bi bi-book-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1"><?= $totalMateri ?></h3>
                                <p class="text-muted mb-0">Total Materi</p>
                                <small class="text-info"><i class="bi bi-journal-text"></i> Tersedia</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-4">
                                <div class="stats-icon stats-warning text-white">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1"><?= $absensiData['hadir'] ?? 0 ?></h3>
                                <p class="text-muted mb-0">Kehadiran</p>
                                <small class="text-success"><i class="bi bi-check-circle"></i> Dari <?= $absensiData['total_absensi'] ?? 0 ?> pertemuan</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-4">
                                <div class="stats-icon stats-danger text-white">
                                    <i class="bi bi-trophy-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1"><?= $nilaiData ? number_format($nilaiData['rata_rata'], 1) : '0' ?></h3>
                                <p class="text-muted mb-0">Rata-rata Nilai</p>
                                <small class="<?= ($nilaiData['status_kelulusan'] ?? '') == 'lulus' ? 'text-success' : 'text-warning' ?>">
                                    <i class="bi bi-<?= ($nilaiData['status_kelulusan'] ?? '') == 'lulus' ? 'check-circle' : 'clock' ?>"></i> 
                                    <?= ucfirst($nilaiData['status_kelulusan'] ?? 'Belum Ada') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Cards Tambahan -->
                <div class="row g-3 g-md-4 mb-4">
                    <div class="col-md-6">
                        <div class="card info-card-primary pattern">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-1"><?= htmlspecialchars($siswaData['nama_kelas'] ?? 'Belum Terdaftar') ?></h4>
                                        <p class="mb-0">Kelas Saat Ini</p>
                                        <?php if ($siswaData['nama_gelombang']): ?>
                                        <small class="opacity-75">
                                            <i class="bi bi-calendar"></i> <?= htmlspecialchars($siswaData['nama_gelombang']) ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ms-3">
                                        <i class="bi bi-building-fill-check fs-1 opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card info-card-warning pattern">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-1"><?= htmlspecialchars($siswaData['nama_instruktur'] ?? 'Belum Ditentukan') ?></h4>
                                        <p class="mb-0">Instruktur Kelas</p>
                                        <?php if ($siswaData['nama_instruktur']): ?>
                                        <small class="opacity-75">
                                            <i class="bi bi-person-workspace"></i> Pembimbing Anda
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ms-3">
                                        <i class="bi bi-person-badge-fill fs-1 opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Cards Row -->
                <div class="row">
                    <!-- Nilai -->
                    <?php if ($nilaiData): ?>
                    <div class="col-lg-8 mb-4">
                        <div class="card content-card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-semibold"><i class="bi bi-trophy me-2"></i>Nilai Kursus</h5>
                                    <span class="badge bg-<?= ($nilaiData['status_kelulusan'] ?? '') == 'lulus' ? 'success' : 'warning' ?> badge-status">
                                        <?= ucfirst($nilaiData['status_kelulusan'] ?? 'Belum Ada') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Mata Pelajaran</th>
                                                <th>Nilai</th>
                                                <th>Progress</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="fw-semibold"><i class="bi bi-file-word text-primary me-2"></i>Microsoft Word</td>
                                                <td><span class="badge bg-primary"><?= $nilaiData['nilai_word'] ?? 0 ?></span></td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-primary" style="width: <?= ($nilaiData['nilai_word'] ?? 0) ?>%"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= ($nilaiData['nilai_word'] ?? 0) >= 70 ? 'success' : 'warning' ?>">
                                                        <?= ($nilaiData['nilai_word'] ?? 0) >= 70 ? 'Lulus' : 'Perlu Perbaikan' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-semibold"><i class="bi bi-file-excel text-success me-2"></i>Microsoft Excel</td>
                                                <td><span class="badge bg-success"><?= $nilaiData['nilai_excel'] ?? 0 ?></span></td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-success" style="width: <?= ($nilaiData['nilai_excel'] ?? 0) ?>%"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= ($nilaiData['nilai_excel'] ?? 0) >= 70 ? 'success' : 'warning' ?>">
                                                        <?= ($nilaiData['nilai_excel'] ?? 0) >= 70 ? 'Lulus' : 'Perlu Perbaikan' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-semibold"><i class="bi bi-file-slides text-warning me-2"></i>Microsoft PowerPoint</td>
                                                <td><span class="badge bg-warning"><?= $nilaiData['nilai_ppt'] ?? 0 ?></span></td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-warning" style="width: <?= ($nilaiData['nilai_ppt'] ?? 0) ?>%"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= ($nilaiData['nilai_ppt'] ?? 0) >= 70 ? 'success' : 'warning' ?>">
                                                        <?= ($nilaiData['nilai_ppt'] ?? 0) >= 70 ? 'Lulus' : 'Perlu Perbaikan' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-semibold"><i class="bi bi-globe text-info me-2"></i>Internet</td>
                                                <td><span class="badge bg-info"><?= $nilaiData['nilai_internet'] ?? 0 ?></span></td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-info" style="width: <?= ($nilaiData['nilai_internet'] ?? 0) ?>%"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= ($nilaiData['nilai_internet'] ?? 0) >= 70 ? 'success' : 'warning' ?>">
                                                        <?= ($nilaiData['nilai_internet'] ?? 0) >= 70 ? 'Lulus' : 'Perlu Perbaikan' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="h4 mb-0 text-primary"><?= number_format($nilaiData['rata_rata'] ?? 0, 1) ?></div>
                                            <div class="small text-muted">Rata-rata</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="h4 mb-0 <?= ($nilaiData['status_kelulusan'] ?? '') == 'lulus' ? 'text-success' : 'text-warning' ?>">
                                                <?= ucfirst($nilaiData['status_kelulusan'] ?? 'Belum Ada') ?>
                                            </div>
                                            <div class="small text-muted">Status Kelulusan</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Jadwal & Profil -->
                    <div class="col-lg-<?= $nilaiData ? '4' : '12' ?> mb-4">
                        <!-- Jadwal -->
                        <div class="card content-card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2"></i>Jadwal Mendatang</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($jadwalResult) && $jadwalResult->num_rows > 0): ?>
                                    <?php while ($jadwal = $jadwalResult->fetch_assoc()): ?>
                                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bi bi-calendar-check"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold"><?= htmlspecialchars($jadwal['nama_kelas']) ?></div>
                                            <div class="small text-muted">
                                                <?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?>
                                            </div>
                                            <span class="badge bg-info badge-status">
                                                <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
                                        <p class="text-muted">Belum ada jadwal tersedia</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    
    <!-- Scripts - Offline -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/scripts.js"></script>
</body>
</html>
