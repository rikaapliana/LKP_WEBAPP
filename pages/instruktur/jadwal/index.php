<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth(); // Hanya instruktur yang bisa akses

include '../../../includes/db.php';
$activePage = 'jadwal'; 
$baseURL = '../';

// Ambil ID instruktur yang sedang login
$stmt = $conn->prepare("SELECT id_instruktur FROM instruktur WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$instrukturData = $stmt->get_result()->fetch_assoc();

if (!$instrukturData) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit();
}

$id_instruktur = $instrukturData['id_instruktur'];

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Get filter parameters from URL
$filterKelas = isset($_GET['filter_kelas']) ? $_GET['filter_kelas'] : '';
$filterPeriode = isset($_GET['filter_periode']) ? $_GET['filter_periode'] : '';
$filterTanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for filters
$whereConditions = ["k.id_instruktur = ?"];
$params = [$id_instruktur];

if (!empty($searchTerm)) {
    $whereConditions[] = "(k.nama_kelas LIKE ?)";
    $params[] = "%$searchTerm%";
}

if (!empty($filterKelas)) {
    $whereConditions[] = "k.id_kelas = ?";
    $params[] = $filterKelas;
}

if (!empty($filterTanggal)) {
    $whereConditions[] = "j.tanggal = ?";
    $params[] = $filterTanggal;
}

if (!empty($filterPeriode)) {
    $today = date('Y-m-d');
    switch($filterPeriode) {
        case 'today':
            $whereConditions[] = "j.tanggal = ?";
            $params[] = $today;
            break;
        case 'week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
            $params[] = $startOfWeek;
            $params[] = $endOfWeek;
            break;
        case 'month':
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
            $params[] = $startOfMonth;
            $params[] = $endOfMonth;
            break;
        case 'past':
            $whereConditions[] = "j.tanggal < ?";
            $params[] = $today;
            break;
        case 'upcoming':
            $whereConditions[] = "j.tanggal > ?";
            $params[] = $today;
            break;
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
$orderClause = "ORDER BY j.tanggal ASC, j.waktu_mulai ASC";

// Count total records with filters
$countQuery = "SELECT COUNT(*) as total FROM jadwal j 
               LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang  
               $whereClause";

$stmt = mysqli_prepare($conn, $countQuery);
if ($stmt) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $countResult = mysqli_stmt_get_result($stmt);
    $totalRecords = mysqli_fetch_assoc($countResult)['total'];
    mysqli_stmt_close($stmt);
} else {
    $totalRecords = 0;
}

$totalPages = ceil($totalRecords / $recordsPerPage);

// Get filtered data with pagination
$query = "SELECT j.*, 
          k.nama_kelas, 
          g.nama_gelombang,
          COUNT(DISTINCT s.id_siswa) as jumlah_siswa,
          CASE DAYNAME(j.tanggal)
            WHEN 'Monday' THEN 'Senin'
            WHEN 'Tuesday' THEN 'Selasa' 
            WHEN 'Wednesday' THEN 'Rabu'
            WHEN 'Thursday' THEN 'Kamis'
            WHEN 'Friday' THEN 'Jumat'
            WHEN 'Saturday' THEN 'Sabtu'
            WHEN 'Sunday' THEN 'Minggu'
          END as hari_indonesia
          FROM jadwal j 
          LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang  
          LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
          $whereClause
          GROUP BY j.id_jadwal
          $orderClause
          LIMIT $recordsPerPage OFFSET $offset";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    $result = false;
}

// Untuk dropdown kelas - hanya kelas yang diampu instruktur ini
$kelasQuery = "SELECT k.*, g.nama_gelombang 
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               WHERE k.id_instruktur = ?
               ORDER BY k.nama_kelas";
$kelasStmt = $conn->prepare($kelasQuery);
$kelasStmt->bind_param("i", $id_instruktur);
$kelasStmt->execute();
$kelasResult = $kelasStmt->get_result();

// Statistik untuk instruktur
$today = date('Y-m-d');

// Jadwal hari ini
$jadwalHariIniQuery = "SELECT COUNT(*) as total FROM jadwal j 
                      JOIN kelas k ON j.id_kelas = k.id_kelas
                      WHERE k.id_instruktur = ? AND j.tanggal = ?";
$jadwalHariIniStmt = $conn->prepare($jadwalHariIniQuery);
$jadwalHariIniStmt->bind_param("is", $id_instruktur, $today);
$jadwalHariIniStmt->execute();
$jadwalHariIni = $jadwalHariIniStmt->get_result()->fetch_assoc()['total'];

// Jadwal minggu ini
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$endOfWeek = date('Y-m-d', strtotime('sunday this week'));
$jadwalMingguIniQuery = "SELECT COUNT(*) as total FROM jadwal j 
                        JOIN kelas k ON j.id_kelas = k.id_kelas
                        WHERE k.id_instruktur = ? AND j.tanggal BETWEEN ? AND ?";
$jadwalMingguIniStmt = $conn->prepare($jadwalMingguIniQuery);
$jadwalMingguIniStmt->bind_param("iss", $id_instruktur, $startOfWeek, $endOfWeek);
$jadwalMingguIniStmt->execute();
$jadwalMingguIni = $jadwalMingguIniStmt->get_result()->fetch_assoc()['total'];

// Total jadwal aktif (yang akan datang)
$jadwalAktifQuery = "SELECT COUNT(*) as total FROM jadwal j 
                    JOIN kelas k ON j.id_kelas = k.id_kelas
                    WHERE k.id_instruktur = ? AND j.tanggal >= ?";
$jadwalAktifStmt = $conn->prepare($jadwalAktifQuery);
$jadwalAktifStmt->bind_param("is", $id_instruktur, $today);
$jadwalAktifStmt->execute();
$jadwalAktif = $jadwalAktifStmt->get_result()->fetch_assoc()['total'];

// Function to build URL with current filters
function buildUrlWithFilters($page = null) {
    global $filterKelas, $filterPeriode, $filterTanggal, $searchTerm;
    
    $params = [];
    if ($page) $params['page'] = $page;
    if (!empty($searchTerm)) $params['search'] = $searchTerm;
    if (!empty($filterKelas)) $params['filter_kelas'] = $filterKelas;
    if (!empty($filterPeriode)) $params['filter_periode'] = $filterPeriode;
    if (!empty($filterTanggal)) $params['filter_tanggal'] = $filterTanggal;
    
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jadwal Mengajar - Instruktur</title>
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
                <h2 class="page-title mb-1">JADWAL MENGAJAR</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Jadwal Mengajar</li>
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

        <!-- Main Content Card -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-calendar-event me-2"></i>Jadwal Mengajar Saya
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <div class="d-flex button-group-header justify-content-md-end gap-2">
                  <button type="button" 
                          class="btn btn-cetak-soft" 
                          onclick="cetakLaporanPDF()" 
                          id="btnCetakPDF"
                          title="Cetak jadwal mengajar">
                    <i class="bi bi-printer me-2"></i>Cetak Jadwal
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Search/Filter Controls -->
          <div class="p-3 border-bottom">
            <form method="GET" id="filterForm">
              <input type="hidden" name="page" value="1" id="pageInput">
              <div class="row align-items-center">  
                <div class="col-12">
                  <div class="d-flex flex-wrap align-items-center gap-2 controls-container">
                    <!-- Search Box -->
                    <div class="d-flex align-items-center search-container">
                      <label for="searchInput" class="me-2 mb-0 search-label">
                        <small>Search:</small>
                      </label>
                      <input type="search" name="search" id="searchInput" class="form-control form-control-sm search-input" value="<?= htmlspecialchars($searchTerm) ?>"/>
                    </div>
                    
                    <!-- Date Range Filter -->
                    <div class="d-flex align-items-center">
                      <label for="filterTanggal" class="me-2 mb-0 search-label">
                        <small>Tanggal:</small>
                      </label>
                      <input type="date" name="filter_tanggal" id="filterTanggal" class="form-control form-control-sm" style="width: 150px;" value="<?= htmlspecialchars($filterTanggal) ?>" />
                    </div>
                    
                    <!-- Filter Button -->
                    <div class="dropdown">
                      <button class="btn btn-light btn-icon position-relative control-btn" 
                              type="button" 
                              data-bs-toggle="dropdown" 
                              aria-expanded="false"
                              id="filterButton"
                              title="Filter">
                        <i class="bi bi-funnel"></i>
                        <?php 
                        $activeFilters = 0;
                        if (!empty($filterKelas)) $activeFilters++;
                        if (!empty($filterPeriode)) $activeFilters++;
                        if (!empty($filterTanggal)) $activeFilters++;
                        ?>
                        <?php if ($activeFilters > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                          <?= $activeFilters ?>
                        </span>
                        <?php endif; ?>
                      </button>
                      
                      <!-- Filter Dropdown -->
                      <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 320px;">
                        <h6 class="mb-3 fw-bold">
                          <i class="bi bi-funnel me-2"></i>Filter Jadwal
                        </h6>
                        
                        <!-- Filter Kelas -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Kelas</label>
                          <select class="form-select form-select-sm" name="filter_kelas">
                            <option value="">Semua Kelas</option>
                            <?php 
                            if ($kelasResult) {
                              mysqli_data_seek($kelasResult, 0);
                              while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                                <option value="<?= $kelas['id_kelas'] ?>" <?= ($filterKelas == $kelas['id_kelas']) ? 'selected' : '' ?>>
                                  <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                  <?php if($kelas['nama_gelombang']): ?>
                                    (<?= htmlspecialchars($kelas['nama_gelombang']) ?>)
                                  <?php endif; ?>
                                </option>
                              <?php endwhile;
                            } ?>
                          </select>
                        </div>
                        
                        <!-- Filter Periode -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Periode</label>
                          <select class="form-select form-select-sm" name="filter_periode">
                            <option value="">Semua Periode</option>
                            <option value="today" <?= ($filterPeriode == 'today') ? 'selected' : '' ?>>Hari Ini</option>
                            <option value="week" <?= ($filterPeriode == 'week') ? 'selected' : '' ?>>Minggu Ini</option>
                            <option value="month" <?= ($filterPeriode == 'month') ? 'selected' : '' ?>>Bulan Ini</option>
                            <option value="past" <?= ($filterPeriode == 'past') ? 'selected' : '' ?>>Yang Sudah Lewat</option>
                            <option value="upcoming" <?= ($filterPeriode == 'upcoming') ? 'selected' : '' ?>>Yang Akan Datang</option>
                          </select>
                        </div>
                        
                        <hr class="my-3">
                        
                        <!-- Filter Buttons -->
                        <div class="row g-2">
                          <div class="col-6">
                            <button class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center" 
                                    type="submit"
                                    style="height: 36px;">
                              <i class="bi bi-check-lg me-1"></i>
                              <span>Terapkan</span>
                            </button>
                          </div>
                          <div class="col-6">
                            <a href="?" class="btn btn-light btn-sm w-100 d-flex align-items-center justify-content-center" 
                               style="height: 36px;">
                              <i class="bi bi-arrow-clockwise me-1"></i>
                              <span>Reset</span>
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
                      <span class="info-count"><?= (($currentPage - 1) * $recordsPerPage) + 1 ?>-<?= min($currentPage * $recordsPerPage, $totalRecords) ?></span>
                      <span class="info-separator">dari</span>
                      <span class="info-total"><?= number_format($totalRecords) ?></span>
                      <span class="info-label">jadwal</span>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
          
          <!-- Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="jadwalTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Tanggal</th>
                  <th>Hari</th>
                  <th>Waktu</th>
                  <th>Kelas</th>
                  <th>Siswa</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($jadwal = mysqli_fetch_assoc($result)): 
                  ?>
                    <?php
                    $tanggalJadwal = strtotime($jadwal['tanggal']);
                    $today = strtotime(date('Y-m-d'));
                    $isToday = $tanggalJadwal == $today;
                    $isPast = $tanggalJadwal < $today;
                    $isUpcoming = $tanggalJadwal > $today;
                    ?>
                    <tr>
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <td class="align-middle">
                        <span class="<?= $isToday ? 'text-primary fw-bold' : ($isPast ? 'text-muted' : '') ?>">
                          <?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?>
                        </span>
                      </td>
                      
                      <td class="align-middle">
                        <span class="<?= $isToday ? 'text-primary fw-bold' : '' ?>">
                          <?= htmlspecialchars($jadwal['hari_indonesia']) ?>
                        </span>
                      </td>
                      
                      <td class="align-middle text-start">
                        <span class="<?= $isToday ? 'text-primary fw-bold' : '' ?>">
                          <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - 
                          <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?>
                        </span>
                      </td>
                      
                      <td class="align-middle">
                        <div class="fw-medium"><?= htmlspecialchars($jadwal['nama_kelas']) ?></div>
                        <?php if($jadwal['nama_gelombang']): ?>
                          <small class="text-muted"><?= htmlspecialchars($jadwal['nama_gelombang']) ?></small>
                        <?php endif; ?>
                      </td>
                      
                      <td class="text-center align-middle">
                        <span class="badge bg-info px-2 py-1">
                          <i class="bi bi-people me-1"></i>
                          <?= $jadwal['jumlah_siswa'] ?> siswa
                        </span>
                      </td>
                      
                      <td class="align-middle">
                        <?php if($isToday): ?>
                          <span class="badge bg-primary">
                            <i class="bi bi-clock me-1"></i>Hari Ini
                          </span>
                        <?php elseif($isPast): ?>
                          <span class="badge bg-success">
                            <i class="bi bi-check-circle me-1"></i>Selesai
                          </span>
                        <?php else: ?>
                          <span class="badge bg-secondary">
                            <i class="bi bi-calendar-check me-1"></i>Terjadwal
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="../siswa/index.php?kelas=<?= $jadwal['id_kelas'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Lihat Siswa">
                            <i class="bi bi-people"></i>
                          </a>
                          <a href="../kelas/index.php" 
                             class="btn btn-action btn-primary btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail Kelas">
                            <i class="bi bi-building"></i>
                          </a>
                          <?php if($isToday || $isPast): ?>
                          <a href="../absensi/index.php?jadwal=<?= $jadwal['id_jadwal'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Kelola Absensi">
                            <i class="bi bi-clipboard-check"></i>
                          </a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-calendar-x display-4 text-muted mb-3 d-block"></i>
                        <h5>
                          <?php if ($activeFilters > 0): ?>
                            Tidak Ada Jadwal yang Sesuai Filter
                          <?php else: ?>
                            Belum Ada Jadwal Mengajar
                          <?php endif; ?>
                        </h5>
                        <p class="mb-3 text-muted">
                          <?php if ($activeFilters > 0): ?>
                            Coba ubah kriteria filter atau reset filter untuk melihat semua jadwal Anda
                          <?php else: ?>
                            Saat ini belum ada jadwal mengajar yang ditugaskan untuk Anda. Hubungi admin untuk informasi lebih lanjut.
                          <?php endif; ?>
                        </p>
                        <div class="btn-group">
                          <?php if ($activeFilters > 0): ?>
                            <a href="?" class="btn btn-secondary">
                              <i class="bi bi-arrow-clockwise me-2"></i>Reset Filter
                            </a>
                          <?php endif; ?>
                          <a href="../kelas/index.php" class="btn btn-primary">
                            <i class="bi bi-building me-2"></i>Lihat Kelas Diampu
                          </a>
                        </div>
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
                  
                  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildUrlWithFilters($i) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  
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

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.getElementById('searchInput');
    const filterTanggal = document.getElementById('filterTanggal');
    
    let searchTimeout;
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          document.getElementById('pageInput').value = 1;
          form.submit();
        }, 500);
      });
    }
    
    if (filterTanggal) {
      filterTanggal.addEventListener('change', function() {
        document.getElementById('pageInput').value = 1;
        form.submit();
      });
    }
    
    const filterDropdown = document.querySelector('.dropdown-menu.p-3');
    if (filterDropdown) {
      filterDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }
    
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }
    
    updateCetakButtonState();
  });

  function cetakLaporanPDF() {
    const btnCetak = document.getElementById('btnCetakPDF');
    
    btnCetak.disabled = true;
    btnCetak.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Memproses...';
    
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    
    let url = 'cetak_laporan.php?';
    const params = [];
    
    if (formData.get('search')) params.push('search=' + encodeURIComponent(formData.get('search')));
    if (formData.get('filter_kelas')) params.push('filter_kelas=' + encodeURIComponent(formData.get('filter_kelas')));
    if (formData.get('filter_periode')) params.push('filter_periode=' + encodeURIComponent(formData.get('filter_periode')));
    if (formData.get('filter_tanggal')) params.push('filter_tanggal=' + encodeURIComponent(formData.get('filter_tanggal')));
    
    url += params.join('&');
    
    const pdfWindow = window.open(url, '_blank');
    
    setTimeout(() => {
      btnCetak.disabled = false;
      btnCetak.innerHTML = '<i class="bi bi-printer me-2"></i>Cetak Jadwal';
      
      if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed == 'undefined') {
        alert('Popup diblokir! Silakan izinkan popup untuk mengunduh laporan PDF.');
      }
    }, 2000);
  }

  function updateCetakButtonState() {
    const btnCetak = document.getElementById('btnCetakPDF');
    const tableRows = document.querySelectorAll('#jadwalTable tbody tr');
    const emptyState = document.querySelector('#jadwalTable tbody .empty-state');
    const hasData = tableRows.length > 0 && !emptyState;
    
    if (!hasData) {
      btnCetak.disabled = true;
      btnCetak.title = 'Tidak ada jadwal untuk dicetak';
    } else {
      btnCetak.disabled = false;
      const visibleRows = Array.from(tableRows).filter(row => !row.querySelector('.empty-state'));
      btnCetak.title = `Cetak ${visibleRows.length} jadwal mengajar`;
    }
  }
  </script>
</body>
</html>