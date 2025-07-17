<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'instruktur'; 
$baseURL = '../';

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID instruktur tidak valid!";
    header("Location: index.php");
    exit;
}

$id_instruktur = (int)$_GET['id'];

// Ambil data instruktur dengan join ke tabel user dan kelas yang diampu
$query = "SELECT i.*, u.username, u.role,
          GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') as kelas_diampu,
          GROUP_CONCAT(DISTINCT CONCAT(k.nama_kelas, '|', COALESCE(g.nama_gelombang, ''), '|', COALESCE(g.tahun, '')) ORDER BY k.nama_kelas SEPARATOR '||') as kelas_detail
          FROM instruktur i 
          LEFT JOIN user u ON i.id_user = u.id_user 
          LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          WHERE i.id_instruktur = ?
          GROUP BY i.id_instruktur";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_instruktur);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$instruktur = mysqli_fetch_assoc($result);

// Parse kelas detail untuk tampilan yang lebih baik
$kelas_list = [];
if ($instruktur['kelas_detail']) {
    $kelas_items = explode('||', $instruktur['kelas_detail']);
    foreach ($kelas_items as $item) {
        $parts = explode('|', $item);
        if (count($parts) >= 3) {
            $kelas_list[] = [
                'nama_kelas' => $parts[0],
                'nama_gelombang' => $parts[1],
                'tahun' => $parts[2]
            ];
        }
    }
}

// Hitung statistik instruktur
$total_kelas = count($kelas_list);

// Hitung total siswa yang diajar (jika ada kelas)
$total_siswa = 0;
if ($total_kelas > 0) {
    $siswaQuery = "SELECT COUNT(DISTINCT s.id_siswa) as total
                   FROM siswa s 
                   JOIN kelas k ON s.id_kelas = k.id_kelas 
                   WHERE k.id_instruktur = ?";
    $siswaStmt = mysqli_prepare($conn, $siswaQuery);
    mysqli_stmt_bind_param($siswaStmt, "i", $id_instruktur);
    mysqli_stmt_execute($siswaStmt);
    $siswaResult = mysqli_stmt_get_result($siswaStmt);
    $siswaData = mysqli_fetch_assoc($siswaResult);
    $total_siswa = $siswaData['total'];
    mysqli_stmt_close($siswaStmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Instruktur - <?= htmlspecialchars($instruktur['nama']) ?></title>
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
              <h2 class="page-title mb-1">DETAIL INSTRUKTUR</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Master</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Instruktur</a>
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
                <?php if($instruktur['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$instruktur['pas_foto'])): ?>
                  <img src="../../../uploads/pas_foto/<?= $instruktur['pas_foto'] ?>" 
                       alt="Foto <?= htmlspecialchars($instruktur['nama']) ?>" 
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
                  <h3 class="mb-1 fw-bold"><?= htmlspecialchars($instruktur['nama']) ?></h3>
                  <p class="text-muted mb-2">NIK: <?= htmlspecialchars($instruktur['nik'] ?? '-') ?></p>
                  <div class="d-flex gap-3">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                      <i class="bi bi-person-workspace me-1"></i>
                      Instruktur
                    </span>
                    <span class="badge bg-<?= ($instruktur['status_aktif'] ?? 'aktif') == 'aktif' ? 'success' : 'danger' ?> fs-6 px-3 py-2">
                      <i class="bi bi-circle-fill me-1"></i>
                      <?= ucfirst($instruktur['status_aktif'] ?? 'Aktif') ?>
                    </span>
                    <?php if($instruktur['username']): ?>
                      <span class="badge bg-info fs-6 px-3 py-2">
                        <i class="bi bi-person-circle me-1"></i>
                        Punya Akun
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-3">
                  <a href="index.php" class="btn btn-kembali px-4">
                    Kembali
                  </a>
                  <a href="edit.php?id=<?= $instruktur['id_instruktur'] ?>" class="btn btn-edit px-4">
                    <i class="bi bi-pencil me-1"></i>Edit Data
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Data Instruktur -->
        <div class="col-lg-8">
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-person-fill me-2"></i>Data Instruktur
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Nama Lengkap</label>
                    <div class="fw-medium"><?= htmlspecialchars($instruktur['nama']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">NIK</label>
                    <div class="fw-medium"><?= htmlspecialchars($instruktur['nik'] ?? '-') ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Jenis Kelamin</label>
                    <div class="fw-medium"><?= htmlspecialchars($instruktur['jenis_kelamin']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Angkatan</label>
                    <div class="fw-medium"><?= htmlspecialchars($instruktur['angkatan'] ?? '-') ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Status Aktif</label>
                    <div class="fw-medium">
                      <span class="badge bg-<?= ($instruktur['status_aktif'] ?? 'aktif') == 'aktif' ? 'success' : 'danger' ?> px-2 py-1">
                        <i class="bi bi-circle-fill me-1"></i>
                        <?= ucfirst($instruktur['status_aktif'] ?? 'Aktif') ?>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Kelas yang Diampu -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-building me-2"></i>Kelas yang Diampu (<?= $total_kelas ?> kelas)
              </h5>
            </div>
            <div class="card-body">
              <?php if(!empty($kelas_list)): ?>
                <div class="row g-3">
                  <?php foreach($kelas_list as $kelas): ?>
                    <div class="col-md-6">
                      <div class="card border border-primary border-opacity-25 h-100">
                        <div class="card-body p-3">
                          <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                              <i class="bi bi-building text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                              <h6 class="mb-1 fw-bold"><?= htmlspecialchars($kelas['nama_kelas']) ?></h6>
                              <?php if($kelas['nama_gelombang']): ?>
                                <small class="text-muted">
                                  <?= htmlspecialchars($kelas['nama_gelombang']) ?>
                                  <?= $kelas['tahun'] ? ' (' . htmlspecialchars($kelas['tahun']) . ')' : '' ?>
                                </small>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-center p-4 text-muted">
                  <i class="bi bi-building display-6 mb-3 d-block opacity-50"></i>
                  <h6 class="text-muted">Belum Mengampu Kelas</h6>
                  <small>Instruktur ini belum ditugaskan mengampu kelas apapun</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
          <!-- Info Sidebar -->
          <div class="card content-card">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-info-circle me-2"></i>Statistik
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-3 text-center">
                <div class="col-6">
                  <div class="p-3 bg-light rounded">
                    <i class="bi bi-building text-secondary fs-2 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold text-dark"><?= $total_kelas ?></h4>
                    <small class="text-muted">Kelas Diampu</small>
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-3 bg-light rounded">
                    <i class="bi bi-people text-secondary fs-2 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold text-dark"><?= $total_siswa ?></h4>
                    <small class="text-muted">Total Siswa</small>
                  </div>
                </div>
              </div>

              <!-- Divider -->
              <hr class="my-4">

              <!-- Status Akun -->
              <div>
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-shield-check me-2"></i>Status Akun
                </h6>
                <?php if($instruktur['username']): ?>
                  <div class="text-center p-3">
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="bi bi-person-check text-success fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1 text-success">Akun Aktif</h6>
                    <small class="text-muted">Username: <strong><?= htmlspecialchars($instruktur['username']) ?></strong></small>
                    <br>
                    <small class="text-muted">Role: <?= htmlspecialchars($instruktur['role']) ?></small>
                  </div>
                <?php else: ?>
                  <div class="text-center p-3">
                    <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="bi bi-person-x text-warning fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1 text-warning">Belum Punya Akun</h6>
                    <small class="text-muted">Instruktur belum memiliki akun login</small>
                  </div>
                <?php endif; ?>
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