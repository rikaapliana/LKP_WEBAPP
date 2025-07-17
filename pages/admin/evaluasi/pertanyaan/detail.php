<?php
session_start();  
require_once '../../../../includes/auth.php';  
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'evaluasi'; 
$baseURL = '../../';

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID pertanyaan tidak valid!";
    header("Location: index.php");
    exit;
}

$id_pertanyaan = (int)$_GET['id'];

// Ambil data pertanyaan evaluasi
$query = "SELECT * FROM pertanyaan_evaluasi WHERE id_pertanyaan = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_pertanyaan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Data pertanyaan tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$pertanyaan = mysqli_fetch_assoc($result);

// Decode pilihan jawaban jika ada
$pilihan_jawaban = [];
if ($pertanyaan['pilihan_jawaban']) {
    $pilihan_jawaban = json_decode($pertanyaan['pilihan_jawaban'], true);
    if (!is_array($pilihan_jawaban)) {
        $pilihan_jawaban = [];
    }
}

// Statistik penggunaan pertanyaan (berdasarkan struktur database yang ada)
$statsQuery = "SELECT 
                (SELECT COUNT(*) FROM periode_evaluasi WHERE status IN ('aktif', 'selesai')) as total_periode,
                (SELECT COUNT(*) FROM jawaban_evaluasi WHERE id_pertanyaan = ?) as total_jawaban,
                (SELECT COUNT(DISTINCT id_siswa) FROM jawaban_evaluasi WHERE id_pertanyaan = ?) as siswa_menjawab";

$statsStmt = mysqli_prepare($conn, $statsQuery);
mysqli_stmt_bind_param($statsStmt, "ii", $id_pertanyaan, $id_pertanyaan);
mysqli_stmt_execute($statsStmt);
$statsResult = mysqli_stmt_get_result($statsStmt);
$stats = mysqli_fetch_assoc($statsResult);

// Data aspek evaluasi untuk informasi
$aspekPerMateri = [
    'Kejelasan Materi', 'Kemudahan Pembelajaran', 'Kualitas Contoh/Latihan',
    'Tingkat Kesulitan', 'Manfaat Praktis', 'Durasi Pembelajaran',
    'Kesesuaian dengan Kebutuhan', 'Kelengkapan Pembahasan'
];

$aspekAkhirKursus = [
    'Fasilitas LKP', 'Kualitas Instruktur', 'Administrasi/Pelayanan', 
    'Lingkungan Belajar', 'Pencapaian Tujuan', 'Kepuasan Keseluruhan',
    'Nilai Investasi', 'Rekomendasi ke Orang Lain'
];

// Cek apakah aspek adalah custom atau predefined
$aspekArray = $pertanyaan['jenis_evaluasi'] === 'per_materi' ? $aspekPerMateri : $aspekAkhirKursus;
$isCustomAspek = !in_array($pertanyaan['aspek_dinilai'], $aspekArray);

// Fungsi untuk mendapatkan icon berdasarkan tipe jawaban
function getTipeJawabanIcon($tipe) {
    switch ($tipe) {
        case 'pilihan_ganda':
            return 'bi-check2-square';
        case 'skala':
            return 'bi-star';
        case 'isian':
            return 'bi-pencil';
        default:
            return 'bi-question-circle';
    }
}

// Fungsi untuk mendapatkan label tipe jawaban
function getTipeJawabanLabel($tipe) {
    switch ($tipe) {
        case 'pilihan_ganda':
            return 'Pilihan Ganda (A-D)';
        case 'skala':
            return 'Skala 1-5';
        case 'isian':
            return 'Isian Bebas';
        default:
            return 'Tidak Diketahui';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Pertanyaan - ID <?= $id_pertanyaan ?></title>
  <link rel="icon" type="image/png" href="../../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../../assets/css/styles.css" />
</head>
<body>
<div class="d-flex">
  <?php include '../../../../includes/sidebar/admin.php'; ?>

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
              <h2 class="page-title mb-1">DETAIL PERTANYAAN EVALUASI</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Evaluasi</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="../periode/index.php">Periode Evaluasi</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Kelola Bank Soal</a>
                  </li>
                  <li class="breadcrumb-item active" aria-current="page">Detail #<?= $id_pertanyaan ?></li>
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
              <div class="question-icon">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 100px; height: 100px; border: 4px solid #f8f9fa;">
                  <i class="bi bi-chat-quote text-white" style="font-size: 3rem;"></i>
                </div>
              </div>
            </div>
            <div class="col">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h3 class="mb-1 fw-bold">Pertanyaan Evaluasi #<?= $id_pertanyaan ?></h3>
                  <p class="text-muted mb-2">Aspek: <?= htmlspecialchars($pertanyaan['aspek_dinilai']) ?></p>
                  <div class="d-flex gap-3 flex-wrap">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                      <i class="bi bi-<?= $pertanyaan['jenis_evaluasi'] == 'per_materi' ? 'book' : 'award' ?> me-1"></i>
                      <?= $pertanyaan['jenis_evaluasi'] == 'per_materi' ? 'Per Materi' : 'Akhir Kursus' ?>
                    </span>
                    <span class="badge bg-<?= $pertanyaan['tipe_jawaban'] == 'pilihan_ganda' ? 'info' : ($pertanyaan['tipe_jawaban'] == 'skala' ? 'warning' : 'secondary') ?> fs-6 px-3 py-2">
                      <i class="bi bi-<?= getTipeJawabanIcon($pertanyaan['tipe_jawaban']) ?> me-1"></i>
                      <?= getTipeJawabanLabel($pertanyaan['tipe_jawaban']) ?>
                    </span>
                    <?php if ($pertanyaan['materi_terkait']): ?>
                      <span class="badge bg-info fs-6 px-3 py-2">
                        <i class="bi bi-laptop me-1"></i>
                        <?= strtoupper($pertanyaan['materi_terkait']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-3">
                  <a href="index.php" class="btn btn-kembali px-4">
                    Kembali
                  </a>
                  <a href="edit.php?id=<?= $pertanyaan['id_pertanyaan'] ?>" class="btn btn-edit px-4">
                    <i class="bi bi-pencil me-1"></i>Edit Pertanyaan
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Data Pertanyaan -->
        <div class="col-lg-8">
          <!-- Konten Pertanyaan -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-chat-quote me-2"></i>Konten Pertanyaan
              </h5>
            </div>
            <div class="card-body">
              <div class="question-content">
                <div class="question-text p-4 bg-light rounded border-start border-primary border-4">
                  <div class="d-flex align-items-start">
                    <div class="question-quote me-3">
                      <i class="bi bi-quote text-primary" style="font-size: 2.5rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                      <p class="mb-0 fs-5 lh-base" style="font-style: italic;">
                        <?= nl2br(htmlspecialchars($pertanyaan['pertanyaan'])) ?>
                      </p>
                    </div>
                  </div>
                </div>
                
                <!-- Pilihan Jawaban untuk Pilihan Ganda -->
                <?php if ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda' && !empty($pilihan_jawaban)): ?>
                  <div class="mt-4">
                    <h6 class="fw-bold mb-3">
                      <i class="bi bi-check2-square text-info me-2"></i>
                      Pilihan Jawaban
                    </h6>
                    <div class="pilihan-jawaban-container">
                      <?php foreach ($pilihan_jawaban as $index => $pilihan): ?>
                        <div class="pilihan-jawaban-item p-3 mb-2 border rounded">
                          <div class="d-flex align-items-center">
                            <div class="pilihan-label me-3">
                              <span class="badge bg-info rounded-circle d-inline-flex align-items-center justify-content-center" 
                                    style="width: 30px; height: 30px; font-size: 0.9rem; font-weight: 600;">
                                <?= chr(65 + $index) ?>
                              </span>
                            </div>
                            <div class="pilihan-text flex-grow-1">
                              <span class="fw-medium"><?= htmlspecialchars($pilihan) ?></span>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
                
                <div class="question-meta mt-3">
                  <div class="row g-3">
                    <div class="col-auto">
                      <small class="text-muted">
                        <i class="bi bi-type me-1"></i>
                        <?= strlen($pertanyaan['pertanyaan']) ?> karakter
                      </small>
                    </div>
                    <div class="col-auto">
                      <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>
                        Dibuat untuk <?= $pertanyaan['jenis_evaluasi'] == 'per_materi' ? 'evaluasi materi' : 'evaluasi akhir' ?>
                      </small>
                    </div>
                    <?php if ($pertanyaan['materi_terkait']): ?>
                    <div class="col-auto">
                      <small class="text-muted">
                        <i class="bi bi-laptop me-1"></i>
                        Khusus materi <?= ucfirst($pertanyaan['materi_terkait']) ?>
                      </small>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
          <!-- Statistik Penggunaan -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-graph-up me-2"></i>Statistik Penggunaan
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4 text-center">
                <div class="col-4">
                  <div class="stat-item">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                      <i class="bi bi-calendar-check text-primary fs-5"></i>
                    </div>
                    <h4 class="fw-bold mb-1"><?= number_format($stats['total_periode']) ?></h4>
                    <small class="text-muted">Periode Aktif</small>
                  </div>
                </div>
                <div class="col-4">
                  <div class="stat-item">
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                      <i class="bi bi-chat-text text-success fs-5"></i>
                    </div>
                    <h4 class="fw-bold mb-1"><?= number_format($stats['total_jawaban']) ?></h4>
                    <small class="text-muted">Total Jawaban</small>
                  </div>
                </div>
                <div class="col-4">
                  <div class="stat-item">
                    <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                      <i class="bi bi-people text-info fs-5"></i>
                    </div>
                    <h4 class="fw-bold mb-1"><?= number_format($stats['siswa_menjawab']) ?></h4>
                    <small class="text-muted">Siswa Menjawab</small>
                  </div>
                </div>
              </div>
              
              <?php if ($stats['total_jawaban'] > 0): ?>
                <hr class="my-4">
                <div class="text-center">
                  <div class="bg-light rounded p-3">
                    <h6 class="fw-bold mb-2">Status Pertanyaan</h6>
                    <span class="badge bg-success fs-6 px-3 py-2">
                      <i class="bi bi-check-circle me-1"></i>
                      Aktif Digunakan
                    </span>
                    <p class="small text-muted mt-2 mb-0">
                      Pertanyaan ini sudah mendapat <?= $stats['total_jawaban'] ?> respons dari <?= $stats['siswa_menjawab'] ?> siswa
                    </p>
                  </div>
                </div>
              <?php else: ?>
                <hr class="my-4">
                <div class="text-center">
                  <div class="bg-light rounded p-3">
                    <h6 class="fw-bold mb-2">Status Pertanyaan</h6>
                    <span class="badge bg-secondary fs-6 px-3 py-2">
                      <i class="bi bi-clock me-1"></i>
                      Belum Digunakan
                    </span>
                    <p class="small text-muted mt-2 mb-0">
                      Pertanyaan siap digunakan dalam evaluasi
                    </p>
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
</div>

<!-- Scripts -->
<script src="../../../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../../../assets/js/scripts.js"></script>

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>

<style>
.question-content {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.question-text {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  transition: all 0.3s ease;
}

.question-text:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.question-quote {
  opacity: 0.7;
}

.question-meta {
  border-top: 1px solid #e9ecef;
  padding-top: 1rem;
}

.pilihan-jawaban-container {
  background-color: #f8f9fa;
  border-radius: 0.5rem;
  padding: 1rem;
  border: 1px solid #e9ecef;
}

.pilihan-jawaban-item {
  background-color: #ffffff;
  border: 1px solid #e9ecef !important;
  transition: all 0.2s ease;
}

.pilihan-jawaban-item:hover {
  border-color: #0d6efd !important;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.pilihan-jawaban-item:last-child {
  margin-bottom: 0 !important;
}

.pilihan-label .badge {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.pilihan-text {
  line-height: 1.4;
}

.stat-item {
  transition: transform 0.2s ease;
}

.stat-item:hover {
  transform: translateY(-2px);
}

.info-item {
  transition: all 0.2s ease;
}

.info-item:hover {
  background-color: #f8f9fa;
  border-radius: 0.375rem;
  padding: 0.5rem;
  margin: -0.5rem;
}

.list-group-item {
  transition: all 0.2s ease;
}

.list-group-item:hover {
  background-color: #f8f9fa;
  border-radius: 0.375rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .question-icon {
    text-align: center;
    margin-bottom: 1rem;
  }
  
  .pilihan-jawaban-item {
    padding: 0.75rem !important;
  }
  
  .pilihan-label .badge {
    width: 25px;
    height: 25px;
    font-size: 0.8rem;
  }
  
  .stat-item h4 {
    font-size: 1.5rem;
  }
}

/* Print styles */
@media print {
  .btn, .top-navbar, .sidebar {
    display: none !important;
  }
  
  .main-content {
    margin-left: 0 !important;
  }
  
  .question-text {
    background: #f8f9fa !important;
    -webkit-print-color-adjust: exact;
  }
  
  .pilihan-jawaban-container {
    border: 1px solid #000 !important;
  }
}
</style>
</body>
</html>