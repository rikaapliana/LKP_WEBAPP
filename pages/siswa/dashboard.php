<?php
session_start();  

// Set timezone Indonesia untuk Kalimantan Selatan (WITA)
date_default_timezone_set('Asia/Makassar'); // UTC+8 untuk Kalimantan Selatan

require_once '../../includes/auth.php';
requireSiswaAuth(); // Hanya siswa dan admin yang bisa akses

require_once '../../includes/db.php';

$role = $_SESSION['role'] ?? 'siswa'; 
$activePage = 'dashboard'; 
$baseURL = './';
include '../../includes/db.php'; 

// Ambil data siswa yang login (dengan semua relasi dan status sertifikat)
$stmt = $conn->prepare("
    SELECT s.*, k.nama_kelas, k.id_kelas, g.nama_gelombang, g.tahun, g.gelombang_ke,
           i.nama as nama_instruktur, n.*,
           -- Status nilai lengkap (5 mata pelajaran)
           CASE WHEN n.nilai_word IS NOT NULL AND n.nilai_word > 0 THEN 1 ELSE 0 END +
           CASE WHEN n.nilai_excel IS NOT NULL AND n.nilai_excel > 0 THEN 1 ELSE 0 END +
           CASE WHEN n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0 THEN 1 ELSE 0 END +
           CASE WHEN n.nilai_internet IS NOT NULL AND n.nilai_internet > 0 THEN 1 ELSE 0 END +
           CASE WHEN n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0 THEN 1 ELSE 0 END as progress_nilai,
           -- Status sertifikat
           CASE WHEN n.rata_rata >= 60 AND 
                     (n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                     (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                     (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                     (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                     (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0)
                THEN 'eligible' ELSE 'not_eligible' END as sertifikat_status
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas  
    LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
    LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
    LEFT JOIN nilai n ON s.id_siswa = n.id_siswa
    WHERE s.id_user = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$siswaData = $stmt->get_result()->fetch_assoc();

// Ambil jadwal hari ini dan mendatang untuk siswa
$jadwalStmt = $conn->prepare("
    SELECT j.*, k.nama_kelas, DATE(j.tanggal) as tgl_jadwal
    FROM jadwal j
    JOIN kelas k ON j.id_kelas = k.id_kelas
    WHERE k.id_kelas = ? AND j.tanggal >= CURDATE()
    ORDER BY j.tanggal ASC, j.waktu_mulai ASC
    LIMIT 5
");
$jadwalStmt->bind_param("i", $siswaData['id_kelas']);
$jadwalStmt->execute();
$jadwalResult = $jadwalStmt->get_result();

// Hitung jadwal hari ini
$jadwalHariIniStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM jadwal j
    WHERE j.id_kelas = ? AND DATE(j.tanggal) = CURDATE()
");
$jadwalHariIniStmt->bind_param("i", $siswaData['id_kelas']);
$jadwalHariIniStmt->execute();
$jadwalHariIni = $jadwalHariIniStmt->get_result()->fetch_assoc()['total'];

// Ambil materi terbaru
$materiStmt = $conn->prepare("
    SELECT m.*, i.nama as nama_instruktur
    FROM materi m
    LEFT JOIN instruktur i ON m.id_instruktur = i.id_instruktur
    WHERE m.id_kelas = ?
    ORDER BY m.id_materi DESC
    LIMIT 3
");
$materiStmt->bind_param("i", $siswaData['id_kelas']);
$materiStmt->execute();
$materiResult = $materiStmt->get_result();

// Hitung total materi
$totalMateriStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM materi m
    WHERE m.id_kelas = ?
");
$totalMateriStmt->bind_param("i", $siswaData['id_kelas']);
$totalMateriStmt->execute();
$totalMateri = $totalMateriStmt->get_result()->fetch_assoc()['total'];

// Ambil absensi kehadiran bulan ini (FIXED QUERY)
$absensiStmt = $conn->prepare("
    SELECT 
        COUNT(j.id_jadwal) as total_jadwal,
        SUM(CASE WHEN ab.status = 'hadir' THEN 1 ELSE 0 END) as total_hadir,
        COUNT(ab.id_absen) as total_absen_tercatat
    FROM jadwal j
    LEFT JOIN absensi_siswa ab ON j.id_jadwal = ab.id_jadwal AND ab.id_siswa = ?
    WHERE j.id_kelas = ? 
    AND MONTH(j.tanggal) = MONTH(CURDATE()) 
    AND YEAR(j.tanggal) = YEAR(CURDATE())
    AND j.tanggal <= CURDATE()
");
$absensiStmt->bind_param("ii", $siswaData['id_siswa'], $siswaData['id_kelas']);
$absensiStmt->execute();
$absensiData = $absensiStmt->get_result()->fetch_assoc();

// Hitung persentase kehadiran (FIXED LOGIC)
$persentaseKehadiran = 0;
if ($absensiData['total_jadwal'] > 0) {
    $persentaseKehadiran = round(($absensiData['total_hadir'] / $absensiData['total_jadwal']) * 100);
}

// Format tanggal dan waktu Indonesia
$hariIni = date('l, d F Y');
$jamSekarang = date('H:i');

// Translate hari ke bahasa Indonesia
$hariInggris = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$hariIndonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hariIni = str_replace($hariInggris, $hariIndonesia, $hariIni);

// Translate bulan ke bahasa Indonesia
$bulanInggris = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$bulanIndonesia = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$hariIni = str_replace($bulanInggris, $bulanIndonesia, $hariIni);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Siswa - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../assets/css/fonts.css" />
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
                                    <?php if ($jadwalHariIni > 0 || $siswaData['sertifikat_status'] == 'eligible'): ?>
                                    <span class="notification-badge"><?= $jadwalHariIni + ($siswaData['sertifikat_status'] == 'eligible' ? 1 : 0) ?></span>
                                    <?php endif; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li class="dropdown-header">Notifikasi</li>
                                    <?php if ($jadwalHariIni > 0): ?>
                                    <li>
                                        <a class="dropdown-item" href="jadwal/">
                                            <i class="bi bi-calendar-check text-info me-2"></i>
                                            <strong><?= $jadwalHariIni ?></strong> jadwal kelas hari ini
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($siswaData['sertifikat_status'] == 'eligible'): ?>
                                    <li>
                                        <a class="dropdown-item" href="nilai/">
                                            <i class="bi bi-award text-success me-2"></i>
                                            Sertifikat siap dicetak!
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($jadwalHariIni == 0 && $siswaData['sertifikat_status'] != 'eligible'): ?>
                                    <li>
                                        <span class="dropdown-item text-muted">Tidak ada notifikasi</span>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            
                            <!-- User info dengan foto profil -->
                            <div class="navbar-user-info">
                                <div class="navbar-user-avatar">
                                    <?php if(!empty($siswaData['pas_foto']) && file_exists('../../uploads/profile_pictures/'.$siswaData['pas_foto'])): ?>
                                        <img src="../../uploads/profile_pictures/<?= $siswaData['pas_foto'] ?>" 
                                             alt="Foto Profil" 
                                             class="rounded-circle" 
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <?= strtoupper(substr($siswaData['nama'] ?? 'S', 0, 1)) ?>
                                    <?php endif; ?>
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
                                        <li><a class="dropdown-item" href="profil/"><i class="bi bi-person me-2"></i>Profil</a></li>
                                        <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
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
                            <p class="mb-0 opacity-90">
                                Anda sedang login sebagai <strong>Siswa</strong><br>
                                <small>LKP Pradata Komputer Kabupaten Tabalong</small>
                            </p>
                        </div>

                        <!-- Quick Stats in Welcome -->
                        <div class="row mt-3">
                            <div class="col-auto">
                                <small class="opacity-75">
                                    <?= $hariIni ?>
                                </small>
                            </div>
                            <div class="col-auto">
                                <small class="opacity-75">
                                    <?= $jamSekarang ?> WITA 
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="bi bi-person-workspace fs-1 opacity-75"></i>
                    </div>
                </div>

                <!-- Statistik Cards -->
                <div class="row g-3 g-md-4 mb-4">
                    <!-- Kelas Saya -->
                    <div class="col-6 col-lg-3">
                        <div class="card stats-card">
                            <div class="card-body text-center p-3">
                                <div class="stats-icon stats-primary text-white mb-2">
                                    <i class="bi bi-building-fill"></i>
                                </div>
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($siswaData['nama_kelas'] ?? 'Belum ada') ?></h6>
                                <p class="text-muted mb-0 small">Kelas Saya</p>
                                <small class="text-info">
                                    <i class="bi bi-people"></i> 
                                    <?= htmlspecialchars($siswaData['nama_gelombang'] ?? 'Gelombang') ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Nilai -->
                    <div class="col-6 col-lg-3">
                        <a href="nilai/" class="text-decoration-none">
                            <div class="card stats-card stats-card-clickable">
                                <div class="card-body text-center p-3">
                                    <div class="stats-icon stats-success text-white mb-2">
                                        <i class="bi bi-graph-up"></i>
                                    </div>
                                    <h4 class="fw-bold mb-1"><?= $siswaData['progress_nilai'] ?? 0 ?>/5</h4>
                                    <p class="text-muted mb-0 small">Progress Nilai</p>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle"></i> Mata pelajaran
                                    </small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Status Sertifikat -->
                    <div class="col-6 col-lg-3">
                        <a href="nilai/" class="text-decoration-none">
                            <div class="card stats-card stats-card-clickable">
                                <div class="card-body text-center p-3">
                                    <div class="stats-icon stats-warning text-white mb-2">
                                        <i class="bi bi-award-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1">
                                        <?php if(isset($siswaData['sertifikat_status']) && $siswaData['sertifikat_status'] == 'eligible'): ?>
                                            SIAP
                                        <?php else: ?>
                                            BELUM SIAP
                                        <?php endif; ?>
                                    </h6>
                                    <p class="text-muted mb-0 small">Sertifikat</p>
                                    <small class="text-warning">
                                        <?php if(isset($siswaData['sertifikat_status']) && $siswaData['sertifikat_status'] == 'eligible'): ?>
                                            <i class="bi bi-check-circle-fill"></i> Bisa dicetak
                                        <?php else: ?>
                                            <i class="bi bi-clock-fill"></i> Lengkapi nilai
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Kehadiran Bulan Ini -->
                    <div class="col-6 col-lg-3">
                        <a href="absensi/" class="text-decoration-none">
                            <div class="card stats-card stats-card-clickable">
                                <div class="card-body text-center p-3">
                                    <div class="stats-icon stats-danger text-white mb-2">
                                        <i class="bi bi-person-check-fill"></i>
                                    </div>
                                    <h4 class="fw-bold mb-1"><?= $persentaseKehadiran ?>%</h4>
                                    <p class="text-muted mb-0 small">Kehadiran</p>
                                    <small class="text-danger">
                                        <i class="bi bi-calendar"></i> Dari Pertemuan
                                    </small>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Quick Actions Row -->
                <div class="row g-3 g-md-4">
                    <!-- Jadwal Terdekat -->
                    <?php if ($jadwalResult->num_rows > 0): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-dark">
                                        <i class="bi bi-calendar-event text-primary me-2"></i>
                                        Jadwal Terdekat
                                    </h5>
                                    <a href="jadwal/" class="btn btn-outline-primary btn-sm">
                                        Lihat Semua
                                    </a>
                                </div>
                            </div>
                            <div class="card-body py-2">
                                <?php while ($jadwal = $jadwalResult->fetch_assoc()): ?>
                                    <?php
                                    $isToday = $jadwal['tgl_jadwal'] == date('Y-m-d');
                                    $isTomorrow = $jadwal['tgl_jadwal'] == date('Y-m-d', strtotime('+1 day'));
                                    
                                    if ($isToday) {
                                        $dateLabel = 'Hari Ini';
                                        $badgeClass = 'bg-success';
                                    } elseif ($isTomorrow) {
                                        $dateLabel = 'Besok';
                                        $badgeClass = 'bg-warning';
                                    } else {
                                        $dateLabel = date('d/m/Y', strtotime($jadwal['tanggal']));
                                        $badgeClass = 'bg-info';
                                    }
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($jadwal['nama_kelas']) ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= $jadwal['waktu_mulai'] ?> - <?= $jadwal['waktu_selesai'] ?>
                                            </small>
                                        </div>
                                        <span class="badge <?= $badgeClass ?> px-2 py-1">
                                            <?= $dateLabel ?>
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Materi Terbaru -->
                    <?php if ($materiResult->num_rows > 0): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-dark">
                                        <i class="bi bi-journal-text text-warning me-2"></i>
                                        Materi Terbaru
                                    </h5>
                                    <a href="materi/" class="btn btn-outline-warning btn-sm">
                                        Lihat Semua (<?= $totalMateri ?>)
                                    </a>
                                </div>
                            </div>
                            <div class="card-body py-2">
                                <?php while ($materi = $materiResult->fetch_assoc()): ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($materi['judul']) ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-person me-1"></i>
                                                <?= htmlspecialchars($materi['nama_instruktur']) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
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