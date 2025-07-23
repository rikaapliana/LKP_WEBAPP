<?php
session_start();  
require_once '../../../includes/auth.php';  
requireinstrukturAuth();

include '../../../includes/db.php';
$activePage = 'profil'; 
$baseURL = '../';

$user_id = $_SESSION['user_id'];

// Ambil data instruktur berdasarkan user_id
$query = "SELECT a.id_instruktur, a.id_user, a.nik, a.nama, a.jenis_kelamin, 
                 a.angkatan, a.status_aktif, a.email, COALESCE(a.pas_foto, '') as pas_foto, 
                 u.username, u.created_at
          FROM instruktur a 
          JOIN user u ON a.id_user = u.id_user 
          WHERE a.id_user = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$instruktur_data = $result->fetch_assoc();

if (!$instruktur_data) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit;
}

// Ambil statistik absensi instruktur
$id_instruktur = $instruktur_data['id_instruktur'];

// Total absensi
$queryTotalAbsensi = "SELECT COUNT(*) as total FROM absensi_instruktur WHERE id_instruktur = ?";
$stmt = $conn->prepare($queryTotalAbsensi);
$stmt->bind_param("i", $id_instruktur);
$stmt->execute();
$totalAbsensi = $stmt->get_result()->fetch_assoc()['total'];

// Absensi hadir
$queryHadir = "SELECT COUNT(*) as hadir FROM absensi_instruktur WHERE id_instruktur = ? AND status = 'hadir'";
$stmt = $conn->prepare($queryHadir);
$stmt->bind_param("i", $id_instruktur);
$stmt->execute();
$totalHadir = $stmt->get_result()->fetch_assoc()['hadir'];

// Absensi bulan ini
$queryBulanIni = "SELECT COUNT(*) as bulan_ini FROM absensi_instruktur 
                  WHERE id_instruktur = ? AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())";
$stmt = $conn->prepare($queryBulanIni);
$stmt->bind_param("i", $id_instruktur);
$stmt->execute();
$absensiBulanIni = $stmt->get_result()->fetch_assoc()['bulan_ini'];

// Persentase kehadiran
$persentaseKehadiran = $totalAbsensi > 0 ? round(($totalHadir / $totalAbsensi) * 100, 1) : 0;

// Absensi terakhir
$queryTerakhir = "SELECT tanggal, waktu, status FROM absensi_instruktur 
                  WHERE id_instruktur = ? ORDER BY tanggal DESC, waktu DESC LIMIT 1";
$stmt = $conn->prepare($queryTerakhir);
$stmt->bind_param("i", $id_instruktur);
$stmt->execute();
$absensiTerakhir = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Instruktur - <?= htmlspecialchars($instruktur_data['nama']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/instruktur.php'; ?>

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
                <h2 class="page-title mb-1">PROFIL INSTRUKTUR</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Profil</li>
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
        </div>

        <!-- Profile Header Card -->
        <div class="card content-card mb-4">
          <div class="card-body p-4">
            <div class="row align-items-start">
              <div class="col-auto">
                <div class="profile-photo">
                  <?php if(!empty($instruktur_data['pas_foto']) && file_exists('../../../uploads/profile_pictures/'.$instruktur_data['pas_foto'])): ?>
                    <img src="../../../uploads/profile_pictures/<?= $instruktur_data['pas_foto'] ?>" 
                         alt="Foto <?= htmlspecialchars($instruktur_data['nama']) ?>" 
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
                    <h3 class="mb-1 fw-bold"><?= htmlspecialchars($instruktur_data['nama']) ?></h3>
                    <p class="text-muted mb-2">Username: <?= htmlspecialchars($instruktur_data['username']) ?></p>
                    <div class="d-flex gap-3 mb-2">
                      <span class="badge bg-primary fs-6 px-3 py-2">
                        <i class="bi bi-person-gear me-1"></i>
                        Instruktur
                      </span>
                      <span class="badge bg-<?= $instruktur_data['status_aktif'] == 'aktif' ? 'success' : 'secondary' ?> fs-6 px-3 py-2">
                        <i class="bi bi-circle-fill me-1"></i>
                        <?= ucfirst($instruktur_data['status_aktif']) ?>
                      </span>
                    </div>
                    <?php if($absensiTerakhir): ?>
                    <div class="text-muted small">
                      <i class="bi bi-clock me-1"></i>
                      Absensi terakhir: <?= date('d/m/Y H:i', strtotime($absensiTerakhir['tanggal'] . ' ' . $absensiTerakhir['waktu'])) ?>
                      <span class="badge bg-<?= $absensiTerakhir['status'] == 'hadir' ? 'success' : 'warning' ?> ms-2">
                        <?= ucfirst($absensiTerakhir['status']) ?>
                      </span>
                    </div>
                    <?php endif; ?>
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
                      <div class="fw-medium"><?= htmlspecialchars($instruktur_data['nik'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Nama Lengkap</label>
                      <div class="fw-medium"><?= htmlspecialchars($instruktur_data['nama']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Username</label>
                      <div class="fw-medium"><?= htmlspecialchars($instruktur_data['username']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Email</label>
                      <div class="fw-medium"><?= htmlspecialchars($instruktur_data['email'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Jenis Kelamin</label>
                      <div class="fw-medium">
                        <i class="bi bi-<?= $instruktur_data['jenis_kelamin'] == 'Laki-Laki' ? 'person' : 'person-dress' ?> me-1"></i>
                        <?= htmlspecialchars($instruktur_data['jenis_kelamin'] ?? '-') ?>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Angkatan</label>
                      <div class="fw-medium"><?= htmlspecialchars($instruktur_data['angkatan'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Status</label>
                      <div class="fw-medium">
                        <span class="badge bg-<?= $instruktur_data['status_aktif'] == 'aktif' ? 'success' : 'secondary' ?> px-2 py-1">
                          <i class="bi bi-<?= $instruktur_data['status_aktif'] == 'aktif' ? 'check-circle' : 'x-circle' ?> me-1"></i>
                          <?= ucfirst($instruktur_data['status_aktif'] ?? 'nonaktif') ?>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Bergabung Sejak</label>
                      <div class="fw-medium"><?= date('d F Y', strtotime($instruktur_data['created_at'])) ?></div>
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
                  <h5 class="fw-bold mb-2 text-success">Akun Instruktur</h5>
                  <p class="text-muted mb-3">Memiliki akses ke fitur instruktur</p>
                  
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