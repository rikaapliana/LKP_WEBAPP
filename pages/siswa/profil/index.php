<?php
session_start();  
require_once '../../../includes/auth.php';  
requireSiswaAuth();

include '../../../includes/db.php';
$activePage = 'profil-saya'; 
$baseURL = '../';

$user_id = $_SESSION['user_id'];

// Ambil data siswa berdasarkan user_id dengan semua relasi
$query = "SELECT s.*, k.nama_kelas, g.nama_gelombang, g.tahun, g.gelombang_ke,
                 i.nama as nama_instruktur, n.*,
                 u.username, u.created_at,
                 -- Progress nilai
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
          LEFT JOIN user u ON s.id_user = u.id_user 
          WHERE s.id_user = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$siswa_data = $result->fetch_assoc();

if (!$siswa_data) {
    $_SESSION['error'] = "Data siswa tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit;
}

// Ambil statistik kehadiran siswa
$id_siswa = $siswa_data['id_siswa'];

// Total absensi yang sudah tercatat
$queryTotalAbsensi = "SELECT COUNT(*) as total FROM absensi_siswa WHERE id_siswa = ?";
$stmt = $conn->prepare($queryTotalAbsensi);
$stmt->bind_param("i", $id_siswa);
$stmt->execute();
$totalAbsensi = $stmt->get_result()->fetch_assoc()['total'];

// Absensi hadir
$queryHadir = "SELECT COUNT(*) as hadir FROM absensi_siswa WHERE id_siswa = ? AND status = 'hadir'";
$stmt = $conn->prepare($queryHadir);
$stmt->bind_param("i", $id_siswa);
$stmt->execute();
$totalHadir = $stmt->get_result()->fetch_assoc()['hadir'];

// Persentase kehadiran
$persentaseKehadiran = $totalAbsensi > 0 ? round(($totalHadir / $totalAbsensi) * 100, 1) : 0;

// Format tanggal lahir Indonesia
$tanggalLahir = '';
if ($siswa_data['tanggal_lahir']) {
    $tanggalLahir = date('d F Y', strtotime($siswa_data['tanggal_lahir']));
    // Translate bulan ke Indonesia
    $bulanInggris = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $bulanIndonesia = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $tanggalLahir = str_replace($bulanInggris, $bulanIndonesia, $tanggalLahir);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Siswa - <?= htmlspecialchars($siswa_data['nama']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/siswa.php'; ?>

    <div class="flex-fill main-content">
      <!-- TOP NAVBAR -->
      <nav class="top-navbar">
        <div class="container-fluid px-3 px-md-4">
          <div class="d-flex align-items-center">
            <!-- Left: Hamburger + Page Info -->
            <div class="d-flex align-items-center flex-grow-1">
              <!-- Sidebar Toggle Button -->
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <!-- Page Title & Breadcrumb -->
              <div class="page-info">
                <h2 class="page-title mb-1">PROFIL SAYA</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Profil Saya</li>
                  </ol>
                </nav>
              </div>
            </div>
            
            <!-- Right: Optional Info -->
            <div class="d-flex align-items-center">
              <div class="navbar-page-info d-none d-md-block">
                <small class="text-muted">
                  <i class="bi bi-calendar3 me-1"></i>
                  <?= date('d M Y') ?>
                </small>
              </div>
            </div>
          </div>
        </div>
      </nav>

      <div class="container-fluid mt-4">
        <!-- Alert Success -->
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Alert Error -->
        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Profile Header Card -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body p-4">
            <div class="row align-items-start">
              <div class="col-auto">
                <div class="profile-photo">
                  <?php if(!empty($siswa_data['pas_foto']) && file_exists('../../../uploads/profile_pictures/'.$siswa_data['pas_foto'])): ?>
                    <img src="../../../uploads/profile_pictures/<?= $siswa_data['pas_foto'] ?>" 
                         alt="Foto <?= htmlspecialchars($siswa_data['nama']) ?>" 
                         class="rounded-circle" 
                         style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #f8f9fa;">
                  <?php else: ?>
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 120px; height: 120px; border: 4px solid #f8f9fa;">
                      <i class="bi bi-person-fill text-white" style="font-size: 4rem;"></i>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h3 class="mb-1 fw-bold"><?= htmlspecialchars($siswa_data['nama']) ?></h3>
                    <p class="text-muted mb-2">Username: <?= htmlspecialchars($siswa_data['username']) ?></p>
                    <p class="text-muted mb-3">
                      <i class="bi bi-building me-1"></i>
                      <?= htmlspecialchars($siswa_data['nama_kelas'] ?? 'Belum ada kelas') ?> - 
                      <?= htmlspecialchars($siswa_data['nama_gelombang'] ?? 'Gelombang') ?>
                    </p>
                    <div class="d-flex gap-3">
                      <span class="badge bg-primary fs-6 px-3 py-2">
                        <i class="bi bi-person-graduation me-1"></i>
                        Siswa
                      </span>
                      <span class="badge bg-<?= $siswa_data['status_aktif'] == 'aktif' ? 'success' : 'success' ?> fs-6 px-3 py-2">
                        <i class="bi bi-circle-fill me-1"></i>
                        <?= ucfirst($siswa_data['status_aktif'] ?? 'aktif') ?>
                      </span>
                    </div>
                  </div>
                   <div class="d-flex gap-3">
                    <a href="../dashboard.php" class="btn btn-kembali px-4">
                      Kembali
                    </a>
                    <a href="edit.php" class="btn btn-edit px-4">
                      <i class="bi bi-pencil me-1"></i>Edit Profil
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
         <!-- Data Profil -->
<div class="col-lg-8">
  <div class="card content-card mb-4">
    <div class="section-header">
      <h5 class="mb-0 text-dark">
        <i class="bi bi-person-fill me-2"></i>Informasi Personal
      </h5>
    </div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-6">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">NIK</label>
            <div class="fw-medium"><?= htmlspecialchars($siswa_data['nik'] ?? '-') ?></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">Nama Lengkap</label>
            <div class="fw-medium"><?= htmlspecialchars($siswa_data['nama']) ?></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">Tempat, Tanggal Lahir</label>
            <div class="fw-medium">
              <?= htmlspecialchars($siswa_data['tempat_lahir'] ?? '-') ?><?= $tanggalLahir ? ', ' . $tanggalLahir : '' ?>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">Jenis Kelamin</label>
            <div class="fw-medium">
              <i class="bi bi-<?= $siswa_data['jenis_kelamin'] == 'Laki-Laki' ? 'person' : 'person-dress' ?> me-1"></i>
              <?= htmlspecialchars($siswa_data['jenis_kelamin'] ?? '-') ?>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">Pendidikan Terakhir</label>
            <div class="fw-medium">
              <i class="bi bi-mortarboard me-1"></i>
              <?= htmlspecialchars($siswa_data['pendidikan_terakhir'] ?? '-') ?>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">Username</label>
            <div class="fw-medium"><?= htmlspecialchars($siswa_data['username']) ?></div>
          </div>
        </div>
        <div class="col-12">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">Alamat Lengkap</label>
            <div class="fw-medium"><?= htmlspecialchars($siswa_data['alamat_lengkap'] ?? '-') ?></div>
          </div>
        </div>
        <!-- Ditambahkan dari Informasi Kontak -->
        <div class="col-md-6">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">No. HP</label>
            <div class="fw-medium">
              <i class="bi bi-phone me-1"></i>
              <?= htmlspecialchars($siswa_data['no_hp'] ?? '-') ?>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="info-item">
            <label class="form-label small text-muted mb-1">Email</label>
            <div class="fw-medium">
              <i class="bi bi-envelope me-1"></i>
              <?= htmlspecialchars($siswa_data['email'] ?? '-') ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


          <!-- Sidebar Info -->
          <div class="col-lg-4">
            <!-- Status Akun -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Status Akun
                </h5>
              </div>
              <div class="card-body">
                <div class="text-center p-3">
                  <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                       style="width: 80px; height: 80px;">
                    <i class="bi bi-person-check text-success" style="font-size: 2.5rem;"></i>
                  </div>
                  <h5 class="fw-bold mb-2 text-success">Akun Siswa</h5>
                  <p class="text-muted mb-3">Terdaftar sejak <?= date('d M Y', strtotime($siswa_data['created_at'])) ?></p>
                  
                  <div class="row text-center">
                    <div class="col-6">
                      <div class="p-2 bg-light rounded">
                        <i class="bi bi-key text-secondary fs-4 mb-1 d-block"></i>
                        <small class="text-muted fw-medium">Access</small>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="p-2 bg-light rounded">
                        <i class="bi bi-shield-lock text-secondary fs-4 mb-1 d-block"></i>
                        <small class="text-muted fw-medium">Secure</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });
  </script>
</body>
</html>