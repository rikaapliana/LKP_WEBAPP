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

// Get filter parameters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterJenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$filterGelombang = isset($_GET['gelombang']) ? $_GET['gelombang'] : '';

// Build WHERE clause for filters
$whereConditions = [];
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $whereConditions[] = "(pe.nama_evaluasi LIKE ? OR pe.deskripsi LIKE ?)";
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
    $types .= 'ss';
}

if (!empty($filterStatus)) {
    $whereConditions[] = "pe.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if (!empty($filterJenis)) {
    $whereConditions[] = "pe.jenis_evaluasi = ?";
    $params[] = $filterJenis;
    $types .= 's';
}

if (!empty($filterGelombang)) {
    $whereConditions[] = "pe.id_gelombang = ?";
    $params[] = $filterGelombang;
    $types .= 'i';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Count total filtered records
$countQuery = "SELECT COUNT(*) as total 
               FROM periode_evaluasi pe 
               LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang 
               LEFT JOIN admin a ON pe.dibuat_oleh = a.id_admin 
               $whereClause";

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

// Ambil data periode evaluasi dengan JOIN
$query = "SELECT pe.*, 
                 g.nama_gelombang, g.tahun, g.gelombang_ke,
                 a.nama as nama_admin,
                 (SELECT COUNT(*) FROM evaluasi e WHERE e.id_periode = pe.id_periode) as total_responden
          FROM periode_evaluasi pe 
          LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang 
          LEFT JOIN admin a ON pe.dibuat_oleh = a.id_admin 
          $whereClause
          ORDER BY 
            CASE pe.status 
              WHEN 'aktif' THEN 1 
              WHEN 'draft' THEN 2 
              WHEN 'selesai' THEN 3 
              ELSE 4 
            END ASC,
            pe.tanggal_buka DESC,
            pe.created_at DESC
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

// Statistik keseluruhan (tanpa filter)
$queryStats = "SELECT 
                COUNT(*) as total_periode,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN jenis_evaluasi = 'per_materi' THEN 1 ELSE 0 END) as per_materi,
                SUM(CASE WHEN jenis_evaluasi = 'akhir_kursus' THEN 1 ELSE 0 END) as akhir_kursus
               FROM periode_evaluasi";
$statsResult = mysqli_query($conn, $queryStats);
$stats = mysqli_fetch_assoc($statsResult);

// Data untuk dropdown filter
$gelombangQuery = "SELECT id_gelombang, nama_gelombang, tahun, gelombang_ke FROM gelombang ORDER BY tahun DESC, gelombang_ke DESC";
$gelombangResult = mysqli_query($conn, $gelombangQuery);

// Function to build URL with current filters
function buildFilterUrl($page = 1, $newFilters = []) {
    global $searchTerm, $filterStatus, $filterJenis, $filterGelombang;
    
    $params = [];
    
    $currentSearch = isset($newFilters['search']) ? $newFilters['search'] : $searchTerm;
    $currentStatus = isset($newFilters['status']) ? $newFilters['status'] : $filterStatus;
    $currentJenis = isset($newFilters['jenis']) ? $newFilters['jenis'] : $filterJenis;
    $currentGelombang = isset($newFilters['gelombang']) ? $newFilters['gelombang'] : $filterGelombang;
    
    if (!empty($currentSearch)) $params['search'] = $currentSearch;
    if (!empty($currentStatus)) $params['status'] = $currentStatus;
    if (!empty($currentJenis)) $params['jenis'] = $currentJenis;
    if (!empty($currentGelombang)) $params['gelombang'] = $currentGelombang;
    if ($page > 1) $params['page'] = $page;
    
    return '?' . http_build_query($params);
}

// Count active filters
$activeFilters = 0;
if (!empty($searchTerm)) $activeFilters++;
if (!empty($filterStatus)) $activeFilters++;
if (!empty($filterJenis)) $activeFilters++;
if (!empty($filterGelombang)) $activeFilters++;

// Helper function untuk status badge
function getStatusBadge($status) {
    switch($status) {
        case 'draft':
            return '<span class="badge bg-secondary">Draft</span>';
        case 'aktif':
            return '<span class="badge bg-success">Aktif</span>';
        case 'selesai':
            return '<span class="badge bg-primary"></i>Selesai</span>';
        default:
            return '<span class="badge bg-light text-dark">Unknown</span>';
    }
}

// Helper function untuk format tanggal
function formatTanggal($datetime) {
    return date('d M Y, H:i', strtotime($datetime));
}

// Helper function untuk format periode waktu
function formatPeriodeWaktu($tanggal_buka, $tanggal_tutup, $status) {
    $now = time();
    $buka = strtotime($tanggal_buka);
    $tutup = strtotime($tanggal_tutup);
    
    $result = '<div class="periode-waktu">';
    $result .= '<small class="text-muted d-block">Buka: ' . date('d M Y, H:i', $buka) . '</small>';
    $result .= '<small class="text-muted d-block">Tutup: ' . date('d M Y, H:i', $tutup) . '</small>';
    
    if ($status == 'aktif') {
        if ($now < $buka) {
            $result .= '<small class="text-warning"><i class="bi bi-clock me-1"></i>Belum dimulai</small>';
        } elseif ($now > $tutup) {
            $result .= '<small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Sudah berakhir</small>';
        } else {
            $result .= '<small class="text-success"><i class="bi bi-play-circle me-1"></i>Sedang berjalan</small>';
        }
    }
    
    $result .= '</div>';
    return $result;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Periode Evaluasi</title>
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
                <h2 class="page-title mb-1">PERIODE EVALUASI</h2>
                <nav aria-label="breadcrumb">  
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="#">Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Periode Evaluasi</li>
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


        <!-- Quick Stats -->
        <div class="row mb-4">
          <div class="col-md-6 mb-3">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-pie-chart me-2"></i>Distribusi Jenis Evaluasi
                </h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-6 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="text-truncate me-2 fw-medium text-dark">Per Materi</small>
                      <span class="badge bg-info text-white"><?= $stats['per_materi'] ?? 0 ?></span>
                    </div>
                    <div class="progress mt-1" style="height: 6px;">
                      <div class="progress-bar bg-info" style="width: <?= $stats['total_periode'] > 0 ? (($stats['per_materi'] ?? 0) / $stats['total_periode']) * 100 : 0 ?>%"></div>
                    </div>
                  </div>
                  <div class="col-6 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="text-truncate me-2 fw-medium text-dark">Akhir Kursus</small>
                      <span class="badge bg-success text-white"><?= $stats['akhir_kursus'] ?? 0 ?></span>
                    </div>
                    <div class="progress mt-1" style="height: 6px;">
                      <div class="progress-bar bg-success" style="width: <?= $stats['total_periode'] > 0 ? (($stats['akhir_kursus'] ?? 0) / $stats['total_periode']) * 100 : 0 ?>%"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6 mb-3">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-bar-chart me-2"></i>Status Periode
                </h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-4 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="text-truncate me-2 fw-medium text-dark">Draft</small>
                      <span class="badge bg-secondary text-white"><?= $stats['draft'] ?? 0 ?></span>
                    </div>
                    <div class="progress mt-1" style="height: 6px;">
                      <div class="progress-bar bg-secondary" style="width: <?= $stats['total_periode'] > 0 ? (($stats['draft'] ?? 0) / $stats['total_periode']) * 100 : 0 ?>%"></div>
                    </div>
                  </div>
                  <div class="col-4 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="text-truncate me-2 fw-medium text-dark">Aktif</small>
                      <span class="badge bg-success text-white"><?= $stats['aktif'] ?? 0 ?></span>
                    </div>
                    <div class="progress mt-1" style="height: 6px;">
                      <div class="progress-bar bg-success" style="width: <?= $stats['total_periode'] > 0 ? (($stats['aktif'] ?? 0) / $stats['total_periode']) * 100 : 0 ?>%"></div>
                    </div>
                  </div>
                  <div class="col-4 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="text-truncate me-2 fw-medium text-dark">Selesai</small>
                      <span class="badge bg-primary text-white"><?= $stats['selesai'] ?? 0 ?></span>
                    </div>
                    <div class="progress mt-1" style="height: 6px;">
                      <div class="progress-bar bg-primary" style="width: <?= $stats['total_periode'] > 0 ? (($stats['selesai'] ?? 0) / $stats['total_periode']) * 100 : 0 ?>%"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content Card -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-calendar-check me-2"></i>Daftar Periode Evaluasi
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <a href="tambah.php" class="btn btn-tambah-soft">
                  <i class="bi bi-plus-circle"></i>
                  Buat Periode Baru
                </a>
                <a href="../pertanyaan/index.php" class="btn btn-outline-primary ms-2">
                  <i class="bi bi-collection"></i>
                  Kelola Bank Soal
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
                    <!-- Search Box -->
                    <div class="d-flex align-items-center search-container">
                      <label for="searchInput" class="me-2 mb-0 search-label">
                        <small>Search:</small>
                      </label>
                      <input type="search" 
                             id="searchInput" 
                             name="search"
                             value="<?= htmlspecialchars($searchTerm) ?>"
                             class="form-control form-control-sm search-input" />
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
                      
                      <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 320px;">
                        <h6 class="mb-3 fw-bold">
                          <i class="bi bi-funnel me-2"></i>Filter Periode Evaluasi
                        </h6>
                        
                        <!-- Filter Status -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Status</label>
                          <select class="form-select form-select-sm" name="status" id="filterStatus">
                            <option value="">Semua Status</option>
                            <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="selesai" <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                          </select>
                        </div>
                        
                        <!-- Filter Jenis Evaluasi -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Jenis Evaluasi</label>
                          <select class="form-select form-select-sm" name="jenis" id="filterJenis">
                            <option value="">Semua Jenis</option>
                            <option value="per_materi" <?= $filterJenis === 'per_materi' ? 'selected' : '' ?>>Per Materi</option>
                            <option value="akhir_kursus" <?= $filterJenis === 'akhir_kursus' ? 'selected' : '' ?>>Akhir Kursus</option>
                          </select>
                        </div>
                        
                        <!-- Filter Gelombang -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Gelombang</label>
                          <select class="form-select form-select-sm" name="gelombang" id="filterGelombang">
                            <option value="">Semua Gelombang</option>
                            <?php if ($gelombangResult && mysqli_num_rows($gelombangResult) > 0): ?>
                              <?php while($gelombang = mysqli_fetch_assoc($gelombangResult)): ?>
                                <option value="<?= $gelombang['id_gelombang'] ?>" 
                                        <?= $filterGelombang == $gelombang['id_gelombang'] ? 'selected' : '' ?>>
                                  <?= htmlspecialchars($gelombang['nama_gelombang']) ?> (<?= $gelombang['tahun'] ?>)
                                </option>
                              <?php endwhile; ?>
                            <?php endif; ?>
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
                        <span class="info-label">periode</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
          
          <!-- Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="periodeTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Nama Evaluasi</th>
                  <th>Jenis</th>
                  <th>Gelombang</th>
                  <th>Periode Waktu</th>
                  <th>Status</th>
                  <th>Responden</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($periode = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <td class="align-middle text-nowrap">
                        <div class="periode-info">
                          <div class="fw-medium text-dark mb-1"><?= htmlspecialchars($periode['nama_evaluasi']) ?></div>
                          <?php if (!empty($periode['deskripsi'])): ?>
                            <small class="text-muted d-block"><?= htmlspecialchars(substr($periode['deskripsi'], 0, 60)) ?><?= strlen($periode['deskripsi']) > 60 ? '...' : '' ?></small>
                          <?php endif; ?>
                        </div>
                      </td>
                      
                      <td class="align-middle">
                        <?php if($periode['jenis_evaluasi'] == 'akhir_kursus'): ?>
                          <span class="badge bg-success">
                            <i class="bi bi-award me-1"></i>Akhir Kursus
                          </span>
                        <?php else: ?>
                          <span class="badge bg-info">
                            <i class="bi bi-book me-1"></i>Per Materi
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <td class="align-middle text-nowrap">
                        <div class="gelombang-info">
                          <div class="fw-medium text-dark"><?= htmlspecialchars($periode['nama_gelombang'] ?? 'N/A') ?></div>
                        </div>
                      </td>
                      
                      <td class="align-middle">
                        <?= formatPeriodeWaktu($periode['tanggal_buka'], $periode['tanggal_tutup'], $periode['status']) ?>
                      </td>
                      
                      <td class="align-middle">
                        <?= getStatusBadge($periode['status']) ?>
                      </td>
                      
                      <td class="text-center align-middle">
                        <span class="badge bg-primary text-white fs-6"><?= $periode['total_responden'] ?></span>
                      </td>
                      
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm">
                          <a href="detail.php?id=<?= $periode['id_periode'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          <?php if ($periode['status'] != 'selesai'): ?>
                            <a href="edit.php?id=<?= $periode['id_periode'] ?>" 
                               class="btn btn-action btn-edit btn-sm" 
                               title="Edit">
                              <i class="bi bi-pencil"></i>
                            </a>
                          <?php endif; ?>
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $periode['id_periode'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Hapus -->
                    <div class="modal fade" id="modalHapus<?= $periode['id_periode'] ?>" tabindex="-1">
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
                            <p>Anda yakin ingin menghapus periode evaluasi ini?</p>
                            <div class="alert alert-light border">
                              <div class="periode-preview">
                                <strong>Nama:</strong><br>
                                <em><?= htmlspecialchars($periode['nama_evaluasi']) ?></em>
                              </div>
                              <hr>
                              <small class="text-muted">
                                <strong>Jenis:</strong> <?= $periode['jenis_evaluasi'] == 'akhir_kursus' ? 'Akhir Kursus' : 'Per Materi' ?><br>
                                <strong>Status:</strong> <?= ucfirst($periode['status']) ?><br>
                                <strong>Responden:</strong> <?= $periode['total_responden'] ?> orang
                              </small>
                            </div>
                            <?php if ($periode['total_responden'] > 0): ?>
                              <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Peringatan:</strong> Periode ini sudah memiliki <?= $periode['total_responden'] ?> responden. 
                                Menghapus periode akan menghapus semua data evaluasi terkait.
                              </div>
                            <?php endif; ?>
                          </div>
                          
                          <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                              <i class="bi bi-x-lg"></i> Batal
                            </button>
                            <button type="button" class="btn btn-danger" 
                                    onclick="confirmDelete(<?= $periode['id_periode'] ?>)">
                              <i class="bi bi-trash"></i> Hapus
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-calendar-check display-4 text-muted mb-3 d-block"></i>
                        <?php if ($activeFilters > 0): ?>
                          <h5>Tidak Ada Hasil</h5>
                          <p class="mb-3 text-muted">Tidak ditemukan periode yang sesuai dengan filter yang dipilih</p>
                          <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="bi bi-arrow-clockwise me-2"></i>Reset Filter
                          </a>
                          <a href="tambah.php" class="btn-tambah-soft">
                            <i class="bi bi-plus-circle me-2"></i>Buat Periode Baru
                          </a>
                        <?php else: ?>
                          <h5>Belum Ada Periode Evaluasi</h5>
                          <p class="mb-3 text-muted">Mulai buat periode evaluasi untuk mengumpulkan feedback siswa</p>
                          <a href="tambah.php" class="btn btn-tambah-soft">
                            <i class="bi bi-plus-circle me-2"></i>Buat Periode Pertama
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
    // Auto-submit search with debounce
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
  .periode-info {
    max-width: 250px;
    word-wrap: break-word;
  }
  
  .periode-waktu {
    min-width: 150px;
  }
  
  .gelombang-info {
    min-width: 120px;
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
  
  .warning-icon {
    font-size: 2rem;
    text-align: center;
    margin-bottom: 1rem;
  }
  
  .periode-preview {
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
    
    .periode-info {
      max-width: 200px;
    }
    
    .periode-waktu {
      min-width: 130px;
    }
    
    .gelombang-info {
      min-width: 100px;
    }
  }
  </style>
</body>
</html>