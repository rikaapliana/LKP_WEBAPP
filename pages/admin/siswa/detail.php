<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'siswa'; 
$baseURL = '../';

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID siswa tidak valid!";
    header("Location: index.php");
    exit;
}

$id_siswa = (int)$_GET['id'];

// Ambil data siswa dengan join ke tabel user dan kelas
$query = "SELECT s.*, u.username, u.role, k.nama_kelas, g.nama_gelombang 
          FROM siswa s 
          LEFT JOIN user u ON s.id_user = u.id_user 
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          WHERE s.id_siswa = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_siswa);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Data siswa tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$siswa = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Siswa - <?= htmlspecialchars($siswa['nama']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>
<body>
<div class="d-flex">
  <?php include '../../../includes/sidebar/admin.php'; ?>

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
              <h2 class="page-title mb-1">DETAIL SISWA</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Akademik</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Siswa</a>
                  </li>
                  <li class="breadcrumb-item active" aria-current="page">Detail</li>
                </ol>
              </nav>
            </div>
          </div>
      
          <!-- Right: Date Info -->
          <div class="d-flex align-items-center">
            <div class="navbar-page-info d-none d-xl-block">
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
      <!-- Alert -->
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle me-2"></i>
          <?= $_SESSION['success'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <!-- Profile Header Card -->
      <div class="card content-card mb-4">
        <div class="card-body p-4">
          <div class="row align-items-start">
            <div class="col-auto">
              <div class="profile-photo">
                <?php if($siswa['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$siswa['pas_foto'])): ?>
                  <img src="../../../uploads/pas_foto/<?= $siswa['pas_foto'] ?>" 
                       alt="Foto <?= htmlspecialchars($siswa['nama']) ?>" 
                       class="rounded-circle" 
                       style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #f8f9fa;">
                <?php else: ?>
                  <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                       style="width: 100px; height: 100px; border: 4px solid #f8f9fa;">
                    <i class="bi bi-person-fill text-white" style="font-size: 3rem;"></i>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="col">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h3 class="mb-1 fw-bold"><?= htmlspecialchars($siswa['nama']) ?></h3>
                  <p class="text-muted mb-2">NIK: <?= htmlspecialchars($siswa['nik']) ?></p>
                  <div class="d-flex gap-3">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                      <i class="bi bi-person me-1"></i>
                      Siswa
                    </span>
                    <span class="badge bg-<?= $siswa['status_aktif'] == 'aktif' ? 'success' : 'danger' ?> fs-6 px-3 py-2">
                      <i class="bi bi-circle-fill me-1"></i>
                      <?= ucfirst($siswa['status_aktif']) ?>
                    </span>
                    <?php if($siswa['username']): ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-3">
                  <a href="index.php" class="btn btn-kembali px-4">
                    Kembali
                  </a>
                  <a href="edit.php?id=<?= $siswa['id_siswa'] ?>" class="btn btn-edit px-4">
                    <i class="bi bi-pencil me-1"></i>Edit Data
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Data Siswa -->
        <div class="col-lg-8">
          <!-- Data Pribadi -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-person-fill me-2"></i>Data Pribadi
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Nama Lengkap</label>
                    <div class="fw-medium"><?= htmlspecialchars($siswa['nama']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">NIK</label>
                    <div class="fw-medium"><?= htmlspecialchars($siswa['nik']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Tempat Lahir</label>
                    <div class="fw-medium"><?= htmlspecialchars($siswa['tempat_lahir']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Tanggal Lahir</label>
                    <div class="fw-medium"><?= date('d F Y', strtotime($siswa['tanggal_lahir'])) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Jenis Kelamin</label>
                    <div class="fw-medium"><?= htmlspecialchars($siswa['jenis_kelamin']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Pendidikan Terakhir</label>
                    <div class="fw-medium"><?= htmlspecialchars($siswa['pendidikan_terakhir']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Status Aktif</label>
                    <div class="fw-medium">
                      <span class="badge bg-<?= $siswa['status_aktif'] == 'aktif' ? 'success' : 'danger' ?> px-2 py-1">
                        <i class="bi bi-circle-fill me-1"></i>
                        <?= ucfirst($siswa['status_aktif']) ?>
                      </span>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Kelas</label>
                    <div class="fw-medium">
                      <?= $siswa['nama_kelas'] ? htmlspecialchars($siswa['nama_kelas']) : 'Belum ditentukan' ?>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Alamat Lengkap</label>
                    <div class="fw-medium"><?= htmlspecialchars($siswa['alamat_lengkap'] ?? '-') ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Data Kontak -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-telephone-fill me-2"></i>Informasi Kontak
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">No. Handphone</label>
                    <div class="fw-medium">
                      <a href="tel:<?= htmlspecialchars($siswa['no_hp']) ?>" class="text-decoration-none">
                        <i class="bi bi-phone me-1"></i><?= htmlspecialchars($siswa['no_hp']) ?>
                      </a>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Email</label>
                    <div class="fw-medium">
                      <?php if($siswa['email']): ?>
                        <a href="mailto:<?= htmlspecialchars($siswa['email']) ?>" class="text-decoration-none">
                          <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($siswa['email']) ?>
                        </a>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
          <!-- Info Siswa -->
          <div class="card content-card">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-info-circle me-2"></i>Info & Dokumen
              </h5>
            </div>
            <div class="card-body">
              <!-- Info Kelas -->
              <div class="mb-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-building me-2"></i>Kelas
                </h6>
                <?php if($siswa['nama_kelas']): ?>
                  <div class="text-center p-3">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="bi bi-building text-secondary fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($siswa['nama_kelas']) ?></h6>
                    <small class="text-muted">Gelombang: <?= htmlspecialchars($siswa['nama_gelombang'] ?? '-') ?></small>
                  </div>
                <?php else: ?>
                  <div class="text-center p-3">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="bi bi-building-x text-muted fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1 text-muted">Belum Ada Kelas</h6>
                    <small class="text-muted">Belum terdaftar di kelas</small>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Divider -->
              <hr class="my-4">
              
              <!-- Dokumen -->
              <div>
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-files me-2"></i>Dokumen
                </h6>
                <div class="list-group list-group-flush">
                  <!-- KTP -->
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                      <i class="bi bi-card-text text-danger me-2"></i>
                      <span class="text-dark">KTP</span>
                    </div>
                    <?php if($siswa['ktp'] && file_exists('../../../uploads/ktp/'.$siswa['ktp'])): ?>
                      <a href="../../../uploads/ktp/<?= $siswa['ktp'] ?>" 
                         target="_blank" 
                         class="btn btn-sm btn-outline-danger"
                         download="KTP_<?= htmlspecialchars($siswa['nama']) ?>.pdf">
                        <i class="bi bi-download"></i>
                      </a>
                    <?php else: ?>
                      <small class="text-muted">Belum upload</small>
                    <?php endif; ?>
                  </div>

                  <!-- Kartu Keluarga -->
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                      <i class="bi bi-people-fill text-info me-2"></i>
                      <span class="text-dark">Kartu Keluarga</span>
                    </div>
                    <?php if($siswa['kk'] && file_exists('../../../uploads/kk/'.$siswa['kk'])): ?>
                      <a href="../../../uploads/kk/<?= $siswa['kk'] ?>" 
                         target="_blank" 
                         class="btn btn-sm btn-outline-info"
                         download="KK_<?= htmlspecialchars($siswa['nama']) ?>.pdf">
                        <i class="bi bi-download"></i>
                      </a>
                    <?php else: ?>
                      <small class="text-muted">Belum upload</small>
                    <?php endif; ?>
                  </div>

                  <!-- Ijazah -->
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                    <div>
                      <i class="bi bi-award-fill text-success me-2"></i>
                      <span class="text-dark">Ijazah</span>
                    </div>
                    <?php if($siswa['ijazah'] && file_exists('../../../uploads/ijazah/'.$siswa['ijazah'])): ?>
                      <a href="../../../uploads/ijazah/<?= $siswa['ijazah'] ?>" 
                         target="_blank" 
                         class="btn btn-sm btn-outline-success"
                         download="Ijazah_<?= htmlspecialchars($siswa['nama']) ?>.pdf">
                        <i class="bi bi-download"></i>
                      </a>
                    <?php else: ?>
                      <small class="text-muted">Belum upload</small>
                    <?php endif; ?>
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