<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth(); // Hanya instruktur yang bisa akses

include '../../../includes/db.php';
$activePage = 'materi'; 
$baseURL = '../';

// Ambil ID instruktur yang sedang login
$stmt = $conn->prepare("SELECT id_instruktur, nama FROM instruktur WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$instrukturData = $stmt->get_result()->fetch_assoc();

if (!$instrukturData) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit();
}

$id_instruktur = $instrukturData['id_instruktur'];
$nama_instruktur = $instrukturData['nama'];

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID materi tidak valid!";
    header("Location: index.php");
    exit;
}

$id_materi = (int)$_GET['id'];

// Ambil data materi - hanya yang milik kelas yang diampu instruktur ini
$query = "SELECT m.*, 
          k.nama_kelas, k.kapasitas,
          g.nama_gelombang, g.tahun as tahun_gelombang
          FROM materi m 
          LEFT JOIN kelas k ON m.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          WHERE m.id_materi = ? AND k.id_instruktur = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $id_materi, $id_instruktur);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Data materi tidak ditemukan atau bukan milik kelas yang Anda ampu!";
    header("Location: index.php");
    exit;
}

$materi = mysqli_fetch_assoc($result);

// Hitung jumlah siswa di kelas (jika ada kelas)
$total_siswa = 0;
if ($materi['id_kelas']) {
    $siswaQuery = "SELECT COUNT(*) as total FROM siswa WHERE id_kelas = ? AND status_aktif = 'aktif'";
    $siswaStmt = mysqli_prepare($conn, $siswaQuery);
    mysqli_stmt_bind_param($siswaStmt, "i", $materi['id_kelas']);
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
  <title>Detail Materi - <?= htmlspecialchars($materi['judul']) ?></title>
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
          <div class="d-flex align-items-center flex-grow-1">
            <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
              <i class="bi bi-list fs-4"></i>
            </button>

            <div class="page-info">
              <h2 class="page-title mb-1">DETAIL MATERI</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Materi Kelas</a>
                  </li>
                  <li class="breadcrumb-item active" aria-current="page">Detail</li>
                </ol>
              </nav>
            </div>
          </div>
      
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
              <div class="materi-icon">
                <?php
                // Tentukan icon berdasarkan file yang ada
                $iconClass = 'bi-journal-bookmark';
                $iconColor = 'text-secondary';
                
                if (!empty($materi['file_materi'])) {
                    $fileExt = strtolower(pathinfo($materi['file_materi'], PATHINFO_EXTENSION));
                    if ($fileExt == 'pdf') {
                        $iconClass = 'bi-file-pdf';
                        $iconColor = 'text-danger';
                    } elseif (in_array($fileExt, ['doc', 'docx'])) {
                        $iconClass = 'bi-file-word';
                        $iconColor = 'text-primary';
                    } elseif (in_array($fileExt, ['ppt', 'pptx'])) {
                        $iconClass = 'bi-file-ppt';
                        $iconColor = 'text-warning';
                    } elseif (in_array($fileExt, ['xls', 'xlsx'])) {
                        $iconClass = 'bi-file-excel';
                        $iconColor = 'text-success';
                    }
                }
                ?>
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 100px; height: 100px; border: 4px solid #f8f9fa;">
                  <i class="bi <?= $iconClass ?> <?= $iconColor ?>" style="font-size: 3rem;"></i>
                </div>
              </div>
            </div>
            <div class="col">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h3 class="mb-1 fw-bold"><?= htmlspecialchars($materi['judul']) ?></h3>
                  <p class="text-muted mb-2">ID Materi: <?= $materi['id_materi'] ?></p>
                  <div class="d-flex gap-3">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                      <i class="bi bi-journal-bookmark me-1"></i>
                      Materi
                    </span>
                    <?php if($materi['file_materi']): ?>
                      <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="bi bi-file-earmark-check me-1"></i>
                        Ada File
                      </span>
                    <?php else: ?>
                      <span class="badge bg-warning fs-6 px-3 py-2">
                        <i class="bi bi-file-earmark-x me-1"></i>
                        Tidak Ada File
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-3">
                  <a href="index.php<?= !empty($materi['id_kelas']) ? '?kelas=' . $materi['id_kelas'] : '' ?>" class="btn btn-kembali px-4">
                    Kembali
                  </a>
                  <a href="edit.php?id=<?= $materi['id_materi'] ?>" class="btn btn-edit px-4">
                    <i class="bi bi-pencil me-1"></i>Edit Data
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Data Materi -->
        <div class="col-lg-8">
          <!-- Informasi Materi -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-journal-text me-2"></i>Informasi Materi
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Judul Materi</label>
                    <div class="fw-medium"><?= htmlspecialchars($materi['judul']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">ID Materi</label>
                    <div class="fw-medium"><?= $materi['id_materi'] ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Kelas</label>
                    <div class="fw-medium">
                      <?php if($materi['nama_kelas']): ?>
                        <span class="badge bg-primary px-2 py-1">
                          <i class="bi bi-building me-1"></i>
                          <?= htmlspecialchars($materi['nama_kelas']) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">Materi Umum</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Gelombang</label>
                    <div class="fw-medium">
                      <?php if($materi['nama_gelombang']): ?>
                        <span class="badge bg-secondary px-2 py-1">
                          <i class="bi bi-collection me-1"></i>
                          <?= htmlspecialchars($materi['nama_gelombang']) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Instruktur</label>
                    <div class="fw-medium"><?= htmlspecialchars($nama_instruktur) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Status File</label>
                    <div class="fw-medium">
                      <?php if($materi['file_materi']): ?>
                        <span class="badge bg-success px-2 py-1">
                          <i class="bi bi-file-earmark-check me-1"></i>
                          Ada File
                        </span>
                      <?php else: ?>
                        <span class="badge bg-warning px-2 py-1">
                          <i class="bi bi-file-earmark-x me-1"></i>
                          Tidak Ada File
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Deskripsi</label>
                    <div class="fw-medium"><?= nl2br(htmlspecialchars($materi['deskripsi'] ?? '-')) ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Info Kelas & Siswa -->
          <?php if($materi['id_kelas']): ?>
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-people me-2"></i>Informasi Kelas
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Nama Kelas</label>
                    <div class="fw-medium"><?= htmlspecialchars($materi['nama_kelas']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Kapasitas Kelas</label>
                    <div class="fw-medium"><?= $materi['kapasitas'] ?> siswa</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Jumlah Siswa Aktif</label>
                    <div class="fw-medium">
                      <span class="badge bg-info px-2 py-1">
                        <i class="bi bi-people me-1"></i>
                        <?= $total_siswa ?> siswa
                      </span>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Tahun Gelombang</label>
                    <div class="fw-medium"><?= $materi['tahun_gelombang'] ?? '-' ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
          <!-- Info Materi -->
          <div class="card content-card">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-info-circle me-2"></i>Info & File
              </h5>
            </div>
            <div class="card-body">
              <!-- Info Instruktur -->
              <div class="mb-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-person-badge me-2"></i>Instruktur
                </h6>
                <div class="text-center p-3">
                  <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-person-fill text-secondary fs-4"></i>
                  </div>
                  <h6 class="fw-bold mb-1"><?= htmlspecialchars($nama_instruktur) ?></h6>
                  <small class="text-muted">Instruktur Pengampu</small>
                </div>
              </div>

              <!-- Divider -->
              <hr class="my-4">
              
              <!-- File Materi -->
              <div>
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-file-earmark me-2"></i>File Materi
                </h6>
                <div class="list-group list-group-flush">
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                    <div>
                      <?php if($materi['file_materi']): ?>
                        <?php
                        $fileExt = strtolower(pathinfo($materi['file_materi'], PATHINFO_EXTENSION));
                        $iconClass = 'bi-file-earmark';
                        $iconColor = 'text-secondary';
                        
                        if ($fileExt == 'pdf') {
                            $iconClass = 'bi-file-pdf';
                            $iconColor = 'text-danger';
                        } elseif (in_array($fileExt, ['doc', 'docx'])) {
                            $iconClass = 'bi-file-word';
                            $iconColor = 'text-primary';
                        } elseif (in_array($fileExt, ['ppt', 'pptx'])) {
                            $iconClass = 'bi-file-ppt';
                            $iconColor = 'text-warning';
                        } elseif (in_array($fileExt, ['xls', 'xlsx'])) {
                            $iconClass = 'bi-file-excel';
                            $iconColor = 'text-success';
                        }
                        ?>
                        <i class="bi <?= $iconClass ?> <?= $iconColor ?> me-2"></i>
                        <span class="text-dark">File Materi</span>
                        <br><small class="text-muted"><?= htmlspecialchars(basename($materi['file_materi'])) ?></small>
                      <?php else: ?>
                        <i class="bi bi-file-earmark-x text-muted me-2"></i>
                        <span class="text-muted">Tidak ada file</span>
                      <?php endif; ?>
                    </div>
                    <?php if($materi['file_materi'] && file_exists('../../../uploads/materi/'.$materi['file_materi'])): ?>
                      <a href="../../../uploads/materi/<?= $materi['file_materi'] ?>" 
                         target="_blank" 
                         class="btn btn-sm btn-outline-primary"
                         download="Materi_<?= htmlspecialchars($materi['judul']) ?>">
                        <i class="bi bi-download"></i>
                      </a>
                    <?php else: ?>
                      <small class="text-muted">-</small>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <?php if($materi['id_kelas']): ?>
              <!-- Divider -->
              <hr class="my-4">
              
              <!-- Statistik Kelas -->
              <div>
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-graph-up me-2"></i>Statistik Kelas
                </h6>
                <div class="list-group list-group-flush">
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                      <i class="bi bi-people text-primary me-2"></i>
                      <span class="text-dark">Total Siswa</span>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= $total_siswa ?></span>
                  </div>
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                      <i class="bi bi-diagram-3 text-info me-2"></i>
                      <span class="text-dark">Kapasitas</span>
                    </div>
                    <span class="text-muted"><?= $materi['kapasitas'] ?></span>
                  </div>
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                    <div>
                      <i class="bi bi-percent text-success me-2"></i>
                      <span class="text-dark">Pengisian</span>
                    </div>
                    <span class="text-muted">
                      <?= $materi['kapasitas'] > 0 ? round(($total_siswa / $materi['kapasitas']) * 100, 1) : 0 ?>%
                    </span>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

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