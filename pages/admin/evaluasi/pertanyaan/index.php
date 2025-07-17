<?php
session_start();  
require_once '../../../../includes/auth.php';  
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'evaluasi'; 
$baseURL = '../../';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Get filter parameters (search terpisah dari filter)
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterJenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$filterMateri = isset($_GET['materi']) ? $_GET['materi'] : '';
$filterTipe = isset($_GET['tipe']) ? $_GET['tipe'] : '';

// Build WHERE clause for filters (tanpa aspek)
$whereConditions = [];
$params = [];
$types = '';

// Search condition (terpisah)
if (!empty($searchTerm)) {
    $whereConditions[] = "(pertanyaan LIKE ? OR aspek_dinilai LIKE ?)";
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
    $types .= 'ss';
}

// Filter conditions (tanpa search)
if (!empty($filterJenis)) {
    $whereConditions[] = "jenis_evaluasi = ?";
    $params[] = $filterJenis;
    $types .= 's';
}

if (!empty($filterMateri)) {
    if ($filterMateri === 'null') {
        $whereConditions[] = "(materi_terkait IS NULL OR materi_terkait = '')";
    } else {
        $whereConditions[] = "materi_terkait = ?";
        $params[] = $filterMateri;
        $types .= 's';
    }
}

if (!empty($filterTipe)) {
    $whereConditions[] = "tipe_jawaban = ?";
    $params[] = $filterTipe;
    $types .= 's';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Count total filtered records
$countQuery = "SELECT COUNT(*) as total FROM pertanyaan_evaluasi $whereClause";
if (!empty($params)) {
    $countStmt = mysqli_prepare($conn, $countQuery);
    if (!empty($types)) {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
} else {
    $countResult = mysqli_query($conn, $countQuery);
}
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Ambil data pertanyaan dengan pagination dan filter
$query = "SELECT * FROM pertanyaan_evaluasi 
          $whereClause
          ORDER BY 
            jenis_evaluasi DESC,
            CASE 
              WHEN materi_terkait IS NULL OR materi_terkait = '' THEN 'ZZZZ' 
              ELSE materi_terkait 
            END ASC,
            question_order ASC,
            id_pertanyaan ASC
          LIMIT $recordsPerPage OFFSET $offset";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($types)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

// Hitung statistik
$queryStats = "SELECT 
                COUNT(*) as total_pertanyaan,
                SUM(CASE WHEN jenis_evaluasi = 'akhir_kursus' THEN 1 ELSE 0 END) as akhir_kursus,
                SUM(CASE WHEN jenis_evaluasi = 'per_materi' THEN 1 ELSE 0 END) as per_materi
               FROM pertanyaan_evaluasi";
$statsResult = mysqli_query($conn, $queryStats);
$stats = mysqli_fetch_assoc($statsResult);

// Statistik per aspek
$queryAspek = "SELECT 
                aspek_dinilai,
                COUNT(*) as jumlah
               FROM pertanyaan_evaluasi 
               GROUP BY aspek_dinilai 
               ORDER BY jumlah DESC";
$aspekResult = mysqli_query($conn, $queryAspek);

// Untuk dropdown filter (tanpa aspek)
$materiQuery = "SELECT DISTINCT materi_terkait FROM pertanyaan_evaluasi 
                WHERE materi_terkait IS NOT NULL AND materi_terkait != '' 
                ORDER BY materi_terkait";
$materiFilterResult = mysqli_query($conn, $materiQuery);

// Function to build URL with current filters (tanpa aspek)
function buildFilterUrl($page = 1, $newFilters = []) {
    global $searchTerm, $filterJenis, $filterMateri, $filterTipe;
    
    $params = [];
    
    $currentSearch = isset($newFilters['search']) ? $newFilters['search'] : $searchTerm;
    $currentJenis = isset($newFilters['jenis']) ? $newFilters['jenis'] : $filterJenis;
    $currentMateri = isset($newFilters['materi']) ? $newFilters['materi'] : $filterMateri;
    $currentTipe = isset($newFilters['tipe']) ? $newFilters['tipe'] : $filterTipe;
    
    if (!empty($currentSearch)) $params['search'] = $currentSearch;
    if (!empty($currentJenis)) $params['jenis'] = $currentJenis;
    if (!empty($currentMateri)) $params['materi'] = $currentMateri;
    if (!empty($currentTipe)) $params['tipe'] = $currentTipe;
    if ($page > 1) $params['page'] = $page;
    
    return '?' . http_build_query($params);
}

// Count active filters (tanpa search)
$activeFilters = 0;
if (!empty($filterJenis)) $activeFilters++;
if (!empty($filterMateri)) $activeFilters++;
if (!empty($filterTipe)) $activeFilters++;

// Helper function untuk decode pilihan jawaban
function getPilihanJawaban($pilihan_jawaban) {
    if (empty($pilihan_jawaban)) return [];
    $decoded = json_decode($pilihan_jawaban, true);
    return is_array($decoded) ? $decoded : [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bank Soal Evaluasi</title>
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
            <div class="d-flex align-items-center flex-grow-1">
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <div class="page-info">
                <h2 class="page-title mb-1">BANK SOAL EVALUASI</h2>
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
                    <li class="breadcrumb-item active" aria-current="page">Kelola Bank Soal</li>
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
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Total Bank Soal</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['total_pertanyaan'] ?? 0) ?></h3>
                    <small class="text-muted stats-subtitle">Pertanyaan tersedia</small>
                  </div>
                  <div class="stats-icon bg-primary-light stats-icon-mobile">
                    <i class="bi bi-collection text-primary"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Per Materi</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['per_materi'] ?? 0) ?></h3>
                    <small class="text-muted stats-subtitle">Evaluasi pembelajaran</small>
                  </div>
                  <div class="stats-icon bg-success-light stats-icon-mobile">
                    <i class="bi bi-book text-success"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Akhir Kursus</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['akhir_kursus'] ?? 0) ?></h3>
                    <small class="text-muted stats-subtitle">Evaluasi menyeluruh</small>
                  </div>
                  <div class="stats-icon bg-info-light stats-icon-mobile">
                    <i class="bi bi-award text-info"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Aspek Dinilai</h6>
                    <h3 class="mb-0 stats-number"><?= mysqli_num_rows($aspekResult) ?></h3>
                    <small class="text-muted stats-subtitle">Kategori evaluasi</small>
                  </div>
                  <div class="stats-icon bg-warning-light stats-icon-mobile">
                    <i class="bi bi-tags text-warning"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content Card -->
        <div class="card content-card">
        <!-- Header Section -->
                <div class="section-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0 text-dark">
                                <i class="bi bi-collection me-2"></i>Kelola Bank Soal
                            </h5>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <a href="../periode/index.php" class="btn btn-kembali">
                                Kembali
                            </a>
                            <a href="tambah.php" class="btn btn-primary-formal ms-2">
                                <i class="bi bi-plus-circle"></i>
                                Tambah Pertanyaan
                            </a>
                        </div>
                    </div>
                </div>

          <!-- Search/Filter Controls -->
          <div class="p-3 border-bottom">
            <form method="GET" id="filterForm">
              <div class="row align-items-center g-2">  
                <div class="col-12">
                  <div class="d-flex flex-wrap align-items-center gap-2 controls-container">
                    <!-- Search Box (Auto-search) -->
                    <div class="d-flex align-items-center search-container">
                      <label for="searchInput" class="me-2 mb-0 search-label">
                        <small>Search:</small>
                      </label>
                      <input type="search" 
                             id="searchInput" 
                             name="search"
                             value="<?= htmlspecialchars($searchTerm) ?>"
                             class="form-control form-control-sm search-input" 
                             />
                    </div>
                    
                    <!-- Filter Button -->
                    <div class="dropdown">
                      <button class="btn btn-light btn-icon position-relative control-btn" 
                              type="button" 
                              data-bs-toggle="dropdown">
                        <i class="bi bi-funnel"></i>
                        <?php if ($activeFilters > 0): ?>
                          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $activeFilters ?></span>
                        <?php endif; ?>
                      </button>
                      
                      <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 300px;">
                        <h6 class="mb-3 fw-bold">
                          <i class="bi bi-funnel me-2"></i>Filter Bank Soal
                        </h6>
                        
                        <!-- Filter Jenis Evaluasi -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Jenis Evaluasi</label>
                          <select class="form-select form-select-sm" name="jenis" id="filterJenis">
                            <option value="">Semua Jenis</option>
                            <option value="per_materi" <?= $filterJenis === 'per_materi' ? 'selected' : '' ?>>Per Materi</option>
                            <option value="akhir_kursus" <?= $filterJenis === 'akhir_kursus' ? 'selected' : '' ?>>Akhir Kursus</option>
                          </select>
                        </div>
                        
                        <!-- Filter Tipe Jawaban -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Tipe Jawaban</label>
                          <select class="form-select form-select-sm" name="tipe" id="filterTipe">
                            <option value="">Semua Tipe</option>
                            <option value="pilihan_ganda" <?= $filterTipe === 'pilihan_ganda' ? 'selected' : '' ?>>Pilihan Ganda</option>
                            <option value="skala" <?= $filterTipe === 'skala' ? 'selected' : '' ?>>Skala (1-5)</option>
                            <option value="isian" <?= $filterTipe === 'isian' ? 'selected' : '' ?>>Isian Bebas</option>
                          </select>
                        </div>
                        
                        <!-- Filter Materi -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Materi Terkait</label>
                          <select class="form-select form-select-sm" name="materi" id="filterMateri">
                            <option value="">Semua Materi</option>
                            <?php if ($materiFilterResult && mysqli_num_rows($materiFilterResult) > 0): ?>
                              <?php while($materi = mysqli_fetch_assoc($materiFilterResult)): ?>
                                <option value="<?= htmlspecialchars($materi['materi_terkait']) ?>" 
                                        <?= $filterMateri === $materi['materi_terkait'] ? 'selected' : '' ?>>
                                  <?= strtoupper(htmlspecialchars($materi['materi_terkait'])) ?>
                                </option>
                              <?php endwhile; ?>
                            <?php endif; ?>
                            <option value="null" <?= $filterMateri === 'null' ? 'selected' : '' ?>>Tanpa Materi</option>
                          </select>
                        </div>
                        
                        <hr class="my-3">
                        
                        <div class="row g-2">
                          <div class="col-6">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                              <i class="bi bi-check-lg me-1"></i>Terapkan
                            </button>
                          </div>
                          <div class="col-6">
                            <a href="index.php" class="btn btn-light btn-sm w-100">
                              <i class="bi bi-arrow-clockwise me-1"></i>Reset
                            </a>
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
                        <span class="info-count"><?= $totalRecords > 0 ? (($currentPage - 1) * $recordsPerPage) + 1 : 0 ?>-<?= min($currentPage * $recordsPerPage, $totalRecords) ?></span>
                        <span class="info-separator">dari</span>
                        <span class="info-total"><?= number_format($totalRecords) ?></span>
                        <span class="info-label">soal</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
          
          <!-- Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="pertanyaanTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Pertanyaan</th>
                  <th>Aspek Dinilai</th>
                  <th>Jenis Evaluasi</th>
                  <th>Tipe Jawaban</th>
                  <th>Materi Terkait</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  $currentJenis = '';
                  $currentMateri = '';
                  while ($pertanyaan = mysqli_fetch_assoc($result)): 
                    // Materi separator for per_materi
                    if ($currentJenis == 'per_materi' && $currentMateri != $pertanyaan['materi_terkait']) {
                      $currentMateri = $pertanyaan['materi_terkait'];
                      echo '<tr class="table-sub-header">';
                      echo '<td colspan="7" class="text-start bg-light-subtle py-1 ps-4">';
                      echo '<small class="text-muted">';
                      echo '<i class="bi bi-chevron-right me-1"></i>';
                      echo $currentMateri ? htmlspecialchars($currentMateri) : 'Tanpa Materi Terkait';
                      echo '</small>';
                      echo '</td>';
                      echo '</tr>';
                    }
                  ?>
                    <tr>
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <td class="align-middle">
                        <div class="pertanyaan-text">
                          <?= nl2br(htmlspecialchars($pertanyaan['pertanyaan'])) ?>
                        </div>
                        
                        <!-- Show pilihan jawaban untuk pilihan ganda -->
                        <?php if ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                          <?php $pilihan = getPilihanJawaban($pertanyaan['pilihan_jawaban']); ?>
                          <?php if (!empty($pilihan)): ?>
                            <div class="mt-2">
                              <small class="text-muted fw-bold">Pilihan Jawaban:</small>
                              <div class="pilihan-jawaban mt-1">
                                <?php foreach ($pilihan as $index => $option): ?>
                                  <div class="pilihan-item">
                                    <span class="pilihan-label"><?= chr(65 + $index) ?>.</span>
                                    <span class="pilihan-text"><?= htmlspecialchars($option) ?></span>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                            </div>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                      
                      <td class="align-middle">
                        <?php 
                        $badgeClass = 'bg-secondary';
                        $icon = 'bi-tag';
                        
                        // Color coding berdasarkan kategori aspek
                        if (in_array($pertanyaan['aspek_dinilai'], ['Kejelasan Materi', 'Kemudahan Pembelajaran', 'Kualitas Contoh/Latihan', 'Tingkat Kesulitan'])) {
                          $badgeClass = 'bg-info';
                          $icon = 'bi-book';
                        } elseif (in_array($pertanyaan['aspek_dinilai'], ['Fasilitas LKP', 'Kualitas Instruktur', 'Administrasi/Pelayanan', 'Lingkungan Belajar'])) {
                          $badgeClass = 'bg-success';
                          $icon = 'bi-building';
                        } elseif (in_array($pertanyaan['aspek_dinilai'], ['Kepuasan Keseluruhan', 'Pencapaian Tujuan', 'Rekomendasi ke Orang Lain', 'Nilai Investasi'])) {
                          $badgeClass = 'bg-warning text-dark';
                          $icon = 'bi-star';
                        }
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                          <i class="<?= $icon ?> me-1"></i><?= htmlspecialchars($pertanyaan['aspek_dinilai']) ?>
                        </span>
                      </td>
                      
                      <td class="align-middle">
                        <?php if($pertanyaan['jenis_evaluasi'] == 'akhir_kursus'): ?>
                          <span class="badge bg-success">
                            <i class="bi bi-award me-1"></i>Akhir Kursus
                          </span>
                        <?php else: ?>
                          <span class="badge bg-info">
                            <i class="bi bi-book me-1"></i>Per Materi
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <td class="align-middle">
                        <?php if($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                          <span class="badge bg-info">
                            <i class="bi bi-check2-square me-1"></i>Pilihan Ganda
                          </span>
                        <?php elseif($pertanyaan['tipe_jawaban'] == 'skala'): ?>
                          <span class="badge bg-warning text-dark">
                            <i class="bi bi-star me-1"></i>Skala 1-5
                          </span>
                        <?php else: ?>
                          <span class="badge bg-primary">
                            <i class="bi bi-pencil me-1"></i>Isian
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <td class="align-middle">
                        <?php if($pertanyaan['materi_terkait']): ?>
                          <span class="badge bg-light text-dark">
                            <?= strtoupper($pertanyaan['materi_terkait']) ?>
                          </span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm">
                          <a href="detail.php?id=<?= $pertanyaan['id_pertanyaan'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="edit.php?id=<?= $pertanyaan['id_pertanyaan'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             title="Edit">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $pertanyaan['id_pertanyaan'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Hapus -->
                    <div class="modal fade" id="modalHapus<?= $pertanyaan['id_pertanyaan'] ?>" tabindex="-1">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <div class="warning-icon">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                              </div>
                              <h5 class="modal-title">Konfirmasi Hapus</h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus pertanyaan ini?</p>
                            <div class="alert alert-light border">
                              <div class="pertanyaan-preview">
                                <strong>Pertanyaan:</strong><br>
                                <em><?= htmlspecialchars(substr($pertanyaan['pertanyaan'], 0, 100)) ?><?= strlen($pertanyaan['pertanyaan']) > 100 ? '...' : '' ?></em>
                              </div>
                              <hr>
                              <small class="text-muted">
                                <strong>Aspek:</strong> <?= htmlspecialchars($pertanyaan['aspek_dinilai']) ?><br>
                                <strong>Jenis:</strong> <?= $pertanyaan['jenis_evaluasi'] == 'akhir_kursus' ? 'Akhir Kursus' : 'Per Materi' ?>
                              </small>
                            </div>
                          </div>
                          
                          <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                              <i class="bi bi-x-lg"></i> Batal
                            </button>
                            <button type="button" class="btn btn-danger" 
                                    onclick="confirmDelete(<?= $pertanyaan['id_pertanyaan'] ?>)">
                              <i class="bi bi-trash"></i> Hapus
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-collection display-4 text-muted mb-3 d-block"></i>
                        <?php if ($activeFilters > 0): ?>
                          <h5>Tidak Ada Hasil</h5>
                          <p class="mb-3 text-muted">Tidak ditemukan data yang sesuai dengan filter yang dipilih</p>
                          <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="bi bi-arrow-clockwise me-2"></i>Reset Filter
                          </a>
                          <a href="tambah.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Tambah Pertanyaan
                          </a>
                        <?php else: ?>
                          <h5>Belum Ada Bank Soal</h5>
                          <p class="mb-3 text-muted">Mulai tambahkan pertanyaan untuk evaluasi siswa</p>
                          <a href="tambah.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Tambah Pertanyaan Pertama
                          </a>
                        <?php endif; ?>
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
                  <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= buildFilterUrl(max(1, $currentPage - 1)) ?>">
                      <i class="bi bi-chevron-left"></i>
                    </a>
                  </li>
                  
                  <?php
                  $startPage = max(1, $currentPage - 2);
                  $endPage = min($totalPages, $currentPage + 2);
                  
                  // Show first page if we're not starting from it
                  if ($startPage > 1): ?>
                    <li class="page-item">
                      <a class="page-link" href="<?= buildFilterUrl(1) ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                      <li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildFilterUrl($i) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  
                  <?php 
                  // Show last page if we're not ending with it
                  if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                      <li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>
                    <?php endif; ?>
                    <li class="page-item">
                      <a class="page-link" href="<?= buildFilterUrl($totalPages) ?>"><?= $totalPages ?></a>
                    </li>
                  <?php endif; ?>
                  
                  <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= buildFilterUrl(min($totalPages, $currentPage + 1)) ?>">
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

  <script src="../../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit search with debounce (back to original behavior)
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    let searchTimeout;
    
    if (searchInput && filterForm) {
      searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
          filterForm.submit();
        }, 500); // 500ms debounce
      });
    }
    
    // Prevent dropdown close on click inside
    document.querySelector('.dropdown-menu.p-3')?.addEventListener('click', function(e) {
      e.stopPropagation();
    });
    
    // Show active filters
    const activeFilters = <?= $activeFilters ?>;
    if (activeFilters > 0) {
      console.log('Active filters:', activeFilters);
    }
  });

  // Fungsi konfirmasi hapus
  function confirmDelete(id) {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalHapus' + id));
    if (modal) modal.hide();
    
    setTimeout(() => {
      window.location.href = `hapus.php?id=${id}&confirm=delete`;
    }, 300);
  }
  </script>

  <style>
  .table-section-header {
    background-color: #f8f9fa !important;
    font-weight: 600;
  }
  
  .table-sub-header {
    background-color: #f8f9fa50 !important;
  }
  
  .table-section-header td {
    border-top: 2px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
  }
  
  .table-sub-header td {
    border-bottom: 1px solid #e9ecef;
  }
  
  .pertanyaan-text {
    max-width: 300px;
    word-wrap: break-word;
    line-height: 1.4;
  }
  
  .pilihan-jawaban {
    background-color: #f8f9fa;
    padding: 0.5rem;
    border-radius: 0.375rem;
    border-left: 3px solid #0d6efd;
  }
  
  .pilihan-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
  }
  
  .pilihan-label {
    font-weight: 600;
    margin-right: 0.5rem;
    color: #0d6efd;
    min-width: 20px;
  }
  
  .pilihan-text {
    flex-grow: 1;
    line-height: 1.3;
  }
  
  .stats-card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: all 0.15s ease-in-out;
  }
  
  .stats-card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transform: translateY(-1px);
  }
  
  .progress {
    background-color: #e9ecef;
    border-radius: 3px;
  }
  
  .progress-bar {
    border-radius: 3px;
  }
  
  .badge {
    font-size: 0.75em;
  }
  
  .btn-group-sm .btn {
    padding: 0.25rem 0.4rem;
  }
  
  .empty-state {
    opacity: 0.7;
  }
  
  .empty-state:hover {
    opacity: 1;
  }
  
  .bg-primary-light {
    background-color: rgba(13, 110, 253, 0.1) !important;
  }
  
  .bg-success-light {
    background-color: rgba(25, 135, 84, 0.1) !important;
  }
  
  .bg-info-light {
    background-color: rgba(13, 202, 240, 0.1) !important;
  }
  
  .bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1) !important;
  }
  
  .bg-light-subtle {
    background-color: rgba(248, 249, 250, 0.5) !important;
  }
  
  .warning-icon {
    font-size: 2rem;
    text-align: center;
    margin-bottom: 1rem;
  }
  
  .pertanyaan-preview {
    background-color: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.375rem;
    margin-bottom: 0.5rem;
  }
  
  .search-container {
    min-width: 200px;
  }
  
  .search-input {
    min-width: 150px;
  }
  
  .controls-container {
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  
  .result-info {
    white-space: nowrap;
  }
  
  .info-badge {
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
  
  .pagination-info {
    flex-grow: 1;
  }
  
  @media (max-width: 768px) {
    .controls-container {
      justify-content: center;
    }
    
    .result-info {
      order: -1;
      flex-basis: 100%;
      justify-content: center;
      margin-bottom: 0.5rem;
    }
    
    .search-container {
      flex-grow: 1;
      max-width: 200px;
    }
  }
  </style>
</body>
</html>