<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'hasil-evaluasi'; 
$baseURL = '../';

// Validasi parameter
if (!isset($_GET['id_periode']) || !is_numeric($_GET['id_periode'])) {
    $_SESSION['error'] = "ID periode evaluasi tidak valid.";
    header("Location: index.php");
    exit;
}

$id_periode = (int)$_GET['id_periode'];

// Ambil data periode evaluasi
$periodeQuery = "SELECT 
                    pe.*,
                    g.nama_gelombang,
                    g.tahun,
                    COUNT(DISTINCT e.id_evaluasi) as total_mengerjakan,
                    COUNT(DISTINCT CASE WHEN e.status_evaluasi = 'selesai' THEN e.id_evaluasi END) as selesai,
                    (SELECT COUNT(DISTINCT s.id_siswa) 
                     FROM siswa s 
                     JOIN kelas k ON s.id_kelas = k.id_kelas 
                     WHERE k.id_gelombang = pe.id_gelombang AND s.status_aktif = 'aktif') as total_siswa_aktif
                 FROM periode_evaluasi pe
                 LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang
                 LEFT JOIN evaluasi e ON pe.id_periode = e.id_periode
                 WHERE pe.id_periode = $id_periode
                 GROUP BY pe.id_periode";

$periodeResult = mysqli_query($conn, $periodeQuery);

if (!$periodeResult || mysqli_num_rows($periodeResult) == 0) {
    $_SESSION['error'] = "Periode evaluasi tidak ditemukan.";
    header("Location: index.php");
    exit;
}

$periode = mysqli_fetch_assoc($periodeResult);
$belum_mengerjakan = $periode['total_siswa_aktif'] - $periode['total_mengerjakan'];

// Ambil pertanyaan yang terpilih untuk periode ini
$pertanyaan_terpilih = [];
if ($periode['pertanyaan_terpilih']) {
    $pertanyaan_terpilih = json_decode($periode['pertanyaan_terpilih'], true);
    if (!is_array($pertanyaan_terpilih)) {
        $pertanyaan_terpilih = [];
    }
}

// Hitung statistik per tipe jawaban
$stats_tipe = [
    'pilihan_ganda' => 0,
    'skala' => 0,
    'isian' => 0
];

if (!empty($pertanyaan_terpilih)) {
    $pertanyaan_ids = implode(',', array_map('intval', $pertanyaan_terpilih));
    $tipeQuery = "SELECT tipe_jawaban, COUNT(*) as jumlah 
                  FROM pertanyaan_evaluasi 
                  WHERE id_pertanyaan IN ($pertanyaan_ids) 
                  GROUP BY tipe_jawaban";
    $tipeResult = mysqli_query($conn, $tipeQuery);
    while ($row = mysqli_fetch_assoc($tipeResult)) {
        $stats_tipe[$row['tipe_jawaban']] = $row['jumlah'];
    }
}

// Pagination settings
$recordsPerPage = 30;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Count total records untuk pagination
$countQuery = "SELECT COUNT(DISTINCT s.id_siswa) as total 
               FROM siswa s 
               JOIN kelas k ON s.id_kelas = k.id_kelas 
               WHERE k.id_gelombang = {$periode['id_gelombang']} AND s.status_aktif = 'aktif'";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Query untuk mengambil data siswa dengan status evaluasi
$siswaQuery = "SELECT 
                s.id_siswa,
                s.nama,
                s.nik,
                s.email,
                s.no_hp,
                s.pas_foto,
                k.nama_kelas,
                e.id_evaluasi,
                e.status_evaluasi,
                e.tanggal_evaluasi,
                COUNT(je.id_jawaban) as jumlah_jawaban,
                (SELECT COUNT(*) FROM pertanyaan_evaluasi pe_count 
                 WHERE pe_count.id_pertanyaan IN (" . (empty($pertanyaan_terpilih) ? '0' : implode(',', array_map('intval', $pertanyaan_terpilih))) . ")) as total_pertanyaan
               FROM siswa s
               JOIN kelas k ON s.id_kelas = k.id_kelas
               LEFT JOIN evaluasi e ON s.id_siswa = e.id_siswa AND e.id_periode = $id_periode
               LEFT JOIN jawaban_evaluasi je ON e.id_evaluasi = je.id_evaluasi
               WHERE k.id_gelombang = {$periode['id_gelombang']} 
               AND s.status_aktif = 'aktif'
               GROUP BY s.id_siswa
               ORDER BY 
                CASE 
                  WHEN e.status_evaluasi = 'selesai' THEN 1
                  ELSE 2
                END,
                e.tanggal_evaluasi DESC,
                s.nama ASC
               LIMIT $recordsPerPage OFFSET $offset";

$siswaResult = mysqli_query($conn, $siswaQuery);

// Helper function untuk pagination
function buildUrlWithFilters($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';
    
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_nama = $bulan[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return "$hari $bulan_nama $tahun, $jam";
}

// Label materi
$materi_labels = [
    'word' => 'Microsoft Word',
    'excel' => 'Microsoft Excel', 
    'ppt' => 'Microsoft PowerPoint',
    'internet' => 'Internet & Email'
];

// Function untuk mendapatkan icon tipe jawaban
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

// Function untuk mendapatkan warna badge tipe jawaban
function getTipeJawabanColor($tipe) {
    switch ($tipe) {
        case 'pilihan_ganda':
            return 'info';
        case 'skala':
            return 'warning';
        case 'isian':
            return 'secondary';
        default:
            return 'light';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Hasil Evaluasi - <?= htmlspecialchars($periode['nama_evaluasi']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
  <style>
  /* Custom Progress Circle */
  .progress-circle {
    position: relative;
    border-radius: 50%;
    background: conic-gradient(#28a745 <?= $periode['total_siswa_aktif'] > 0 ? round(($periode['selesai'] / $periode['total_siswa_aktif']) * 100, 1) * 3.6 : 0 ?>deg, #e9ecef 0deg);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .progress-circle-inner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    background: white;
    border-radius: 50%;
    width: 80%;
    height: 80%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  
  /* Alert styling */
  .alert-sm {
    padding: 1rem 5rem;
    font-size: 0.875rem;
  }
  
  
  /* Info item styling */
  .info-item strong {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.75rem;
  }
  
  /* Button styling yang konsisten */
  .btn-action {
    border: 1px solid #dee2e6;
    transition: all 0.2s ease;
    min-width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  .btn-action.btn-view {
    color: #0d6efd;
    background-color: #f8f9ff;
  }

  .btn-action.btn-view:hover {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
  }
  
  /* Responsive adjustments */
  @media (max-width: 768px) {
    .btn-group {
      flex-direction: column;
      gap: 0.5rem;
    }
    
    .btn-group .btn {
      width: 100%;
    }
    
    .progress-circle {
      width: 80px !important;
      height: 80px !important;
    }
    
    .progress-circle-inner .fs-3 {
      font-size: 1.2rem !important;
    }
  }
  
  /* Table enhancements */
  .custom-table td {
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
  }
  
  .custom-table th {
    font-weight: 600;
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
  }
  
  /* Badge styling */
  .badge.badge-active {
    background-color: #d1edff;
    color: #0c63e4;
    border: 1px solid #b6d7ff;
  }
  
  .badge.badge-inactive {
    background-color: #f8d7da;
    color: #842029;
    border: 1px solid #f5c6cb;
  }
  
  /* Search and filter controls */
  .controls-container {
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  
  .search-container {
    min-width: 200px;
  }
  
  .search-input {
    min-width: 150px;
  }
  
  .control-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  /* Section header styling */
  .section-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.25rem;
    border-radius: 0.5rem 0.5rem 0 0;
  }
  
  .section-header h5 {
    margin-bottom: 0;
    font-weight: 600;
  }
  
  /* Photo styling */
  .rounded-circle {
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
  }
  
  .rounded-circle:hover {
    border-color: #0d6efd;
    transform: scale(1.05);
  }
  
  /* Empty state styling */
  .empty-state {
    text-align: center;
    padding: 3rem 1rem;
  }
  
  .empty-state i {
    opacity: 0.5;
  }
  
  .empty-state h5 {
    color: #6c757d;
    margin-bottom: 1rem;
  }
  
  /* Pagination styling */
  .pagination-sm .page-link {
    border-radius: 0.375rem;
    margin: 0 2px;
  }
  
  .pagination-sm .page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
  }

  /* Statistik tipe jawaban */
  .tipe-stats {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
  }

  .tipe-stat-item {
    text-align: center;
    padding: 0.75rem;
    background: white;
    border-radius: 0.375rem;
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
  }

  .tipe-stat-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  }

  .tipe-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-size: 1.2rem;
  }

  .result-info .info-badge {
    background-color: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    border: 1px solid #dee2e6;
    font-size: 0.875rem;
  }

  .info-count {
    font-weight: 600;
    color: #495057;
  }

  .info-separator {
    margin: 0 0.25rem;
    color: #6c757d;
  }

  .info-total {
    font-weight: 600;
    color: #495057;
  }

  .info-label {
    color: #6c757d;
  }
  </style>
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/admin.php'; ?>

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
                <h2 class="page-title mb-1">DETAIL HASIL EVALUASI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Evaluasi & Feedback</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Hasil Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Detail</li>
                  </ol>
                </nav>
              </div>
            </div>
            
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

        <!-- Periode Info Card -->
        <div class="card content-card mb-4">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-clipboard-data me-2"></i>
                  <?= htmlspecialchars($periode['nama_evaluasi']) ?>
                </h5>
                <small class="text-muted">
                  <?= htmlspecialchars($periode['nama_gelombang']) ?> (<?= $periode['tahun'] ?>) • 
                  <?= $periode['selesai'] ?>/<?= $periode['total_siswa_aktif'] ?> siswa selesai
                </small>
              </div>
              <div class="col-md-4 text-md-end">
                <div class="d-flex align-items-center justify-content-end gap-1" role="group">
                  <a href="index.php" class="btn btn-secondary-formal btn-sm">
                    Kembali
                  </a>
                  <a href="ringkasan.php?id_periode=<?= $id_periode ?>" 
                     class="btn btn-primary-formal btn-sm">
                    <i class="bi bi-bar-chart me-1"></i>
                    Ringkasan
                  </a>                                
                </div>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="info-item mb-3">
                  <strong class="text-muted">Jenis Evaluasi:</strong>
                  <?php if($periode['jenis_evaluasi'] == 'per_materi'): ?>
                    <span class="badge bg-info-subtle text-info ms-2">Per Materi</span>
                  <?php else: ?>
                    <span class="badge bg-primary-subtle text-primary ms-2">Akhir Kursus</span>
                  <?php endif; ?>
                </div>
                
                <?php if($periode['materi_terkait']): ?>
                <div class="info-item mb-3">
                  <strong class="text-muted">Materi Terkait:</strong>
                  <span class="ms-2 text-dark"><?= $materi_labels[$periode['materi_terkait']] ?? ucfirst($periode['materi_terkait']) ?></span>
                </div>
                <?php endif; ?>
              </div>
              
              <div class="col-md-6">
                <div class="info-item mb-3">
                  <strong class="text-muted">Periode Evaluasi:</strong>
                  <div class="ms-2">
                    <small class="text-success d-block">
                      <i class="bi bi-calendar-plus me-1"></i>
                      Buka: <?= formatTanggalIndonesia($periode['tanggal_buka']) ?>
                    </small>
                    <small class="text-danger d-block">
                      <i class="bi bi-calendar-x me-1"></i>
                      Tutup: <?= formatTanggalIndonesia($periode['tanggal_tutup']) ?>
                    </small>
                  </div>
                </div>
                
                <div class="info-item">
                  <strong class="text-muted">Status:</strong>
                  <?php if($periode['status'] == 'aktif'): ?>
                    <span class="badge badge-active ms-2">Aktif</span>
                  <?php else: ?>
                    <span class="badge badge-inactive ms-2">Selesai</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistik Pertanyaan & Progress -->
        <div class="row mb-4">
          <!-- Statistik Tipe Pertanyaan -->
          <div class="col-md-8">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-pie-chart me-2"></i>Komposisi Pertanyaan Evaluasi
                </h5>
                <small class="text-muted">Distribusi tipe pertanyaan dalam evaluasi ini</small>
              </div>
<div class="card-body">
  <div class="row g-3">
    <div class="col-md-4">
      <div class="tipe-stat-item">
        <div class="tipe-icon bg-info-subtle">
          <i class="bi bi-check2-square text-info"></i>
        </div>
        <div class="fw-bold fs-4 text-info"><?= $stats_tipe['pilihan_ganda'] ?></div>
        <small class="text-muted">Pilihan Ganda</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="tipe-stat-item">
        <div class="tipe-icon bg-warning-subtle">
          <i class="bi bi-star-fill text-warning"></i>
        </div>
        <div class="fw-bold fs-4 text-warning"><?= $stats_tipe['skala'] ?></div>
        <small class="text-muted">Skala 1-5</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="tipe-stat-item">
        <div class="tipe-icon bg-secondary-subtle">
          <i class="bi bi-pencil-fill text-secondary"></i>
        </div>
        <div class="fw-bold fs-4 text-secondary"><?= $stats_tipe['isian'] ?></div>
        <small class="text-muted">Isian Bebas</small>
      </div>
    </div>
  </div>
  <hr class="my-3">
  <div class="text-center">
    <span class="badge bg-primary fs-6 px-3 py-2">
      <i class="bi bi-collection me-1"></i>
      Total <?= array_sum($stats_tipe) ?> Pertanyaan
    </span>
  </div>
</div>
            </div>
          </div>

          <!-- Progress Card -->
          <div class="col-md-4">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-bar-chart me-2"></i>Progress Evaluasi
                </h5>
              </div>
              <div class="card-body text-center">
                <?php 
                $completion_rate = $periode['total_siswa_aktif'] > 0 ? 
                                  round(($periode['selesai'] / $periode['total_siswa_aktif']) * 100, 1) : 0;
                ?>
                <div class="progress-circle mx-auto mb-3" style="width: 120px; height: 120px;">
                  <div class="progress-circle-inner">
                    <div class="fw-bold fs-3 text-dark"><?= $completion_rate ?>%</div>
                    <small class="text-muted">Complete</small>
                  </div>
                </div>
                
                <div class="row g-2 text-center">
                  <div class="col-4">
                    <div class="fw-bold text-success"><?= $periode['selesai'] ?></div>
                    <small class="text-muted d-block">Selesai</small>
                  </div>
                  <div class="col-4">
                    <div class="fw-bold text-muted"><?= $belum_mengerjakan ?></div>
                    <small class="text-muted d-block">Belum</small>
                  </div>
                  <div class="col-4">
                    <div class="fw-bold text-primary"><?= $periode['total_siswa_aktif'] ?></div>
                    <small class="text-muted d-block">Total</small>
                  </div>
                </div>

                <?php if($belum_mengerjakan > 0): ?>
                <div class="alert alert-warning mt-3 mb-0">
                  <i class="bi bi-exclamation-triangle me-1"></i>
                  <small><strong><?= $belum_mengerjakan ?> siswa</strong> belum mengerjakan</small>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content Card -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-people me-2"></i>Daftar Siswa & Status Evaluasi
                </h5>
                <small class="text-muted">Partisipasi siswa dalam periode evaluasi ini</small>
              </div>
              <div class="col-md-4 text-md-end">
                <div class="btn-group btn-group-sm" role="group">
                  
                </div>
              </div>
            </div>
          </div>

          <!-- Search/Filter Controls -->
          <div class="p-3 border-bottom">
            <div class="row align-items-center">  
              <div class="col-12">
                <div class="d-flex flex-wrap align-items-center gap-2 controls-container">
                  <!-- Search Box -->
                  <div class="d-flex align-items-center search-container">
                    <label for="searchInput" class="me-2 mb-0 search-label">
                      <small>Search:</small>
                    </label>
                    <input type="search" id="searchInput" class="form-control form-control-sm search-input" />
                  </div>
                  
                  <!-- Filter Status -->
                  <div class="dropdown">
                    <button class="btn btn-light btn-icon position-relative control-btn" 
                            type="button" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false"
                            id="filterButton"
                            title="Filter">
                      <i class="bi bi-funnel"></i>
                      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="filterBadge">
                        0
                      </span>
                    </button>
                    
                    <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 250px;">
                      <h6 class="mb-3 fw-bold">
                        <i class="bi bi-funnel me-2"></i>Filter Data
                      </h6>
                      
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Status Evaluasi</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                          <option value="">Semua Status</option>
                          <option value="selesai">Sudah Selesai</option>
                          <option value="belum">Belum Mulai</option>
                        </select>
                      </div>
                      
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Kelas</label>
                        <select class="form-select form-select-sm" id="filterKelas">
                          <option value="">Semua Kelas</option>
                        </select>
                      </div>
                      
                      <hr class="my-3">
                      
                      <div class="row g-2">
                        <div class="col-6">
                          <button class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center" 
                                  id="applyFilter" 
                                  type="button"
                                  style="height: 36px;">
                            <i class="bi bi-check-lg me-1"></i>
                            <span>Terapkan</span>
                          </button>
                        </div>
                        <div class="col-6">
                          <button class="btn btn-light btn-sm w-100 d-flex align-items-center justify-content-center" 
                                  id="resetFilter" 
                                  type="button"
                                  style="height: 36px;">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            <span>Reset</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                 <!-- Result Info -->
                  <div class="ms-auto result-info d-flex align-items-center">
                    <label class="me-2 mb-0 search-label">
                      <small>Show:</small>
                    </label>
                    <div class="info-badge">
                      <span class="info-count"><?= (($currentPage - 1) * $recordsPerPage) + 1 ?>-<?= min($currentPage * $recordsPerPage, $totalRecords) ?></span>
                      <span class="info-separator">dari</span>
                      <span class="info-total"><?= number_format($totalRecords) ?></span>
                      <span class="info-label">siswa</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="siswaTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Foto</th>
                  <th>Data Siswa</th>
                  <th>Kelas</th>
                  <th>Status Evaluasi</th>
                  <th>Progress Jawaban</th>
                  <th>Tanggal Selesai</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($siswaResult) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($siswa = mysqli_fetch_assoc($siswaResult)): 
                    // Hitung progress jawaban
                    $progress_pct = $siswa['total_pertanyaan'] > 0 ? 
                                   round(($siswa['jumlah_jawaban'] / $siswa['total_pertanyaan']) * 100) : 0;
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <!-- Foto -->
                      <td class="text-center align-middle">
                        <?php if($siswa['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$siswa['pas_foto'])): ?>
                          <img src="../../../uploads/pas_foto/<?= $siswa['pas_foto'] ?>" 
                               alt="Foto" 
                               class="rounded-circle" 
                               style="width: 50px; height: 50px; object-fit: cover; border: 2px solid #e9ecef;" 
                               title="<?= htmlspecialchars($siswa['nama']) ?>">
                        <?php else: ?>
                          <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white" 
                               style="width: 50px; height: 50px;">
                            <i class="bi bi-person-fill fs-5"></i>
                          </div>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Data Siswa -->
                      <td class="align-middle">
                        <div class="fw-medium text-dark"><?= htmlspecialchars($siswa['nama']) ?></div>
                        <small class="text-muted d-block">NIK: <?= htmlspecialchars($siswa['nik']) ?></small>
                        <small class="text-muted d-block">
                          <i class="bi bi-envelope me-1"></i>
                          <?= htmlspecialchars($siswa['email'] ?? 'Tidak ada email') ?>
                        </small>
                        <small class="text-muted d-block">
                          <i class="bi bi-telephone me-1"></i>
                          <?= htmlspecialchars($siswa['no_hp']) ?>
                        </small>
                      </td>
                      
                      <!-- Kelas -->
                      <td class="align-middle">
                        <span class="fw-medium text-dark"><?= htmlspecialchars($siswa['nama_kelas']) ?></span>
                      </td>
                      
                      <!-- Status Evaluasi -->
                      <td class="text-center align-middle">
                        <?php if($siswa['status_evaluasi'] == 'selesai'): ?>
                          <span class="badge badge-active">
                            <i class="bi bi-check-circle me-1"></i>Selesai
                          </span>
                        <?php else: ?>
                          <span class="badge badge-inactive">
                            <i class="bi bi-x-circle me-1"></i>Belum Mulai
                          </span>
                        <?php endif; ?>
                      </td>

                      <!-- Progress Jawaban -->
                      <td class="align-middle">
                        <div class="d-flex align-items-center">
                          <div class="progress me-2" style="width: 60px; height: 8px;">
                            <div class="progress-bar bg-<?= $progress_pct == 100 ? 'success' : ($progress_pct > 0 ? 'info' : 'secondary') ?>" 
                                 style="width: <?= $progress_pct ?>%"></div>
                          </div>
                          <small class="text-muted">
                            <?= $siswa['jumlah_jawaban'] ?>/<?= $siswa['total_pertanyaan'] ?>
                          </small>
                        </div>
                        <small class="text-muted"><?= $progress_pct ?>% selesai</small>
                      </td>
                      
                      <!-- Tanggal Selesai -->
                      <td class="align-middle">
                        <?php if($siswa['tanggal_evaluasi']): ?>
                          <small class="text-muted">
                            <?= formatTanggalIndonesia($siswa['tanggal_evaluasi']) ?>
                          </small>
                        <?php else: ?>
                          <small class="text-muted">-</small>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Aksi -->
                      <td class="align-middle">
                        <?php if($siswa['id_evaluasi']): ?>
                          <a href="view.php?id_evaluasi=<?= $siswa['id_evaluasi'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Lihat Detail Jawaban">
                            <i class="bi bi-eye me-1"></i>Detail
                          </a>
                        <?php else: ?>
                          <span class="text-muted">
                            <small>Belum ada jawaban</small>
                          </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-people display-4 text-muted mb-3 d-block"></i>
                        <h5>Tidak Ada Siswa</h5>
                        <p class="mb-3 text-muted">Belum ada siswa terdaftar di gelombang ini</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

         <!-- Pagination -->
          <?php if ($totalPages > 1): ?>
          <div class="card-footer">
            <div class="d-flex justify-content-end align-items-center">
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                  <!-- Previous Button -->
                  <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($currentPage > 1) ? buildUrlWithFilters($currentPage - 1) : '#' ?>">
                      <i class="bi bi-chevron-left"></i>
                    </a>
                  </li>
                  
                  <?php
                  $startPage = max(1, $currentPage - 2);
                  $endPage = min($totalPages, $currentPage + 2);
                  
                  if ($endPage - $startPage < 4) {
                    if ($startPage == 1) {
                      $endPage = min($totalPages, $startPage + 4);
                    } else {
                      $startPage = max(1, $endPage - 4);
                    }
                  }
                  ?>
                  
                  <!-- First page if not in range -->
                  <?php if ($startPage > 1): ?>
                    <li class="page-item">
                      <a class="page-link" href="<?= buildUrlWithFilters(1) ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                      <li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <!-- Page numbers -->
                  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildUrlWithFilters($i) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  
                  <!-- Last page if not in range -->
                  <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                      <li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>
                    <?php endif; ?>
                    <li class="page-item">
                      <a class="page-link" href="<?= buildUrlWithFilters($totalPages) ?>"><?= $totalPages ?></a>
                    </li>
                  <?php endif; ?>
                  
                  <!-- Next Button -->
                  <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($currentPage < $totalPages) ? buildUrlWithFilters($currentPage + 1) : '#' ?>">
                      <i class="bi bi-chevron-right"></i>
                    </a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('siswaTable');
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('.empty-state'));
    const filterButton = document.getElementById('filterButton');
    const filterBadge = document.getElementById('filterBadge');
    
    let activeFilters = 0;
    
    // Populate kelas filter
    const kelasFilter = document.getElementById('filterKelas');
    const uniqueKelas = new Set();
    rows.forEach(row => {
      const kelas = row.cells[3]?.textContent?.trim();
      if (kelas) uniqueKelas.add(kelas);
    });
    
    uniqueKelas.forEach(kelas => {
      const option = document.createElement('option');
      option.value = kelas;
      option.textContent = kelas;
      kelasFilter.appendChild(option);
    });
    
    // Force dropdown positioning
    function forceDropdownPositioning() {
      document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.style.setProperty('position', 'absolute', 'important');
        menu.style.setProperty('top', '100%', 'important');
        menu.style.setProperty('bottom', 'auto', 'important');
        menu.style.setProperty('transform', 'none', 'important');
        menu.style.setProperty('z-index', '1055', 'important');
        menu.style.setProperty('margin-top', '2px', 'important');
        
        if (menu.classList.contains('dropdown-menu-end')) {
          menu.style.setProperty('right', '0', 'important');
          menu.style.setProperty('left', 'auto', 'important');
        }
      });
    }
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const filterStatus = document.getElementById('filterStatus');
    const applyFilterBtn = document.getElementById('applyFilter');
    const resetFilterBtn = document.getElementById('resetFilter');
    
    let searchTimeout;
    if (searchInput) {
      searchInput.addEventListener('input', function(e) {
        e.stopPropagation();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          applyFilters();
        }, 300);
      });
    }
    
    function applyFilters() {
      const searchTerm = (searchInput?.value || '').toLowerCase().trim();
      const statusFilter = filterStatus?.value || '';
      const kelasFilterValue = kelasFilter?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (statusFilter) activeFilters++;
      if (kelasFilterValue) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach(row => {
        try {
          // Get data from cells
          const nama = (row.cells[2]?.querySelector('.fw-medium')?.textContent || '').toLowerCase();
          const nik = (row.cells[2]?.textContent || '').toLowerCase();
          const email = (row.cells[2]?.textContent || '').toLowerCase();
          const kelas = (row.cells[3]?.textContent || '').trim();
          
          // Get status
          const statusElement = row.cells[4]?.querySelector('.badge');
          let status = '';
          if (statusElement) {
            const statusText = statusElement.textContent.trim().toLowerCase();
            if (statusText.includes('selesai')) status = 'selesai';
            else status = 'belum';
          }
          
          let showRow = true;
          
          // Apply search filter
          if (searchTerm && 
              !nama.includes(searchTerm) && 
              !nik.includes(searchTerm) && 
              !email.includes(searchTerm)) {
            showRow = false;
          }
          
          // Apply status filter
          if (statusFilter && status !== statusFilter) {
            showRow = false;
          }
          
          // Apply kelas filter
          if (kelasFilterValue && kelas !== kelasFilterValue) {
            showRow = false;
          }
          
          row.style.display = showRow ? '' : 'none';
          if (showRow) visibleCount++;
          
        } catch (error) {
          console.error('Filter error for row:', error);
          row.style.display = '';
          visibleCount++;
        }
      });
      
      updateRowNumbers();
    }
    
    function updateFilterBadge() {
      if (!filterBadge || !filterButton) return;
      
      if (activeFilters > 0) {
        filterBadge.textContent = activeFilters;
        filterBadge.classList.remove('d-none');
        filterButton.classList.add('btn-primary');
        filterButton.classList.remove('btn-light');
      } else {
        filterBadge.classList.add('d-none');
        filterButton.classList.remove('btn-primary');
        filterButton.classList.add('btn-light');
      }
    }
    
    function updateRowNumbers() {
      let counter = <?= ($currentPage - 1) * $recordsPerPage + 1 ?>;
      rows.forEach(row => {
        if (row.style.display !== 'none') {
          row.cells[0].textContent = counter++;
        }
      });
    }

    // Event listeners
    if (applyFilterBtn) {
      applyFilterBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        applyFilters();
        
        // Close dropdown
        setTimeout(() => {
          const dropdown = bootstrap.Dropdown.getInstance(filterButton);
          if (dropdown) dropdown.hide();
        }, 100);
      });
    }
    
    if (resetFilterBtn) {
      resetFilterBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (searchInput) searchInput.value = '';
        if (filterStatus) filterStatus.value = '';
        if (kelasFilter) kelasFilter.value = '';
        applyFilters();
      });
    }
    
    // Prevent dropdown from closing when clicking inside
    const filterDropdown = document.querySelector('.dropdown-menu.p-3');
    if (filterDropdown) {
      filterDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }

    // Dropdown event handlers
    document.addEventListener('show.bs.dropdown', function (e) {
      forceDropdownPositioning();
    });
    
    document.addEventListener('shown.bs.dropdown', function (e) {
      const dropdown = e.target.nextElementSibling;
      if (dropdown && dropdown.classList.contains('dropdown-menu')) {
        dropdown.style.setProperty('position', 'absolute', 'important');
        dropdown.style.setProperty('top', '100%', 'important');
        dropdown.style.setProperty('bottom', 'auto', 'important');
        dropdown.style.setProperty('transform', 'none', 'important');
        dropdown.style.setProperty('z-index', '1055', 'important');
        dropdown.style.setProperty('margin-top', '2px', 'important');
        
        if (dropdown.classList.contains('dropdown-menu-end')) {
          dropdown.style.setProperty('right', '0', 'important');
          dropdown.style.setProperty('left', 'auto', 'important');
        }
      }
    });

    // Initialize everything
    forceDropdownPositioning();
    
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'childList' || mutation.type === 'attributes') {
          forceDropdownPositioning();
        }
      });
    });
    
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'style']
    });

    // Initialize tooltips
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }
    
    // Add hover effects for better UX
    rows.forEach(row => {
      row.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f8f9fa';
      });
      
      row.addEventListener('mouseleave', function() {
        this.style.backgroundColor = '';
      });
    });
    
    window.addEventListener('resize', forceDropdownPositioning);
    window.addEventListener('scroll', forceDropdownPositioning);
  });
  </script>
</body>
</html>