<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'jadwal'; 
$baseURL = '../';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure minimum page is 1
$offset = ($currentPage - 1) * $recordsPerPage;

// Get filter parameters from URL
$filterKelas = isset($_GET['filter_kelas']) ? $_GET['filter_kelas'] : '';
$filterInstruktur = isset($_GET['filter_instruktur']) ? $_GET['filter_instruktur'] : '';
$filterHari = isset($_GET['filter_hari']) ? $_GET['filter_hari'] : '';
$filterPeriode = isset($_GET['filter_periode']) ? $_GET['filter_periode'] : '';
$filterTanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for filters
$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "(k.nama_kelas LIKE ? OR i.nama LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if (!empty($filterKelas)) {
    $whereConditions[] = "k.nama_kelas = ?";
    $params[] = $filterKelas;
}

if (!empty($filterInstruktur)) {
    $whereConditions[] = "i.nama = ?";
    $params[] = $filterInstruktur;
}

if (!empty($filterTanggal)) {
    $whereConditions[] = "j.tanggal = ?";
    $params[] = $filterTanggal;
}

if (!empty($filterHari)) {
    $hariMap = [
        'Senin' => 'Monday',
        'Selasa' => 'Tuesday', 
        'Rabu' => 'Wednesday',
        'Kamis' => 'Thursday',
        'Jumat' => 'Friday',
        'Sabtu' => 'Saturday',
        'Minggu' => 'Sunday'
    ];
    if (isset($hariMap[$filterHari])) {
        $whereConditions[] = "DAYNAME(j.tanggal) = ?";
        $params[] = $hariMap[$filterHari];
    }
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

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Default order: Tanggal terbaru dulu, waktu mulai ascending untuk hari yang sama
$orderClause = "ORDER BY j.tanggal DESC, j.waktu_mulai ASC";

// Count total records with filters
$countQuery = "SELECT COUNT(*) as total FROM jadwal j 
               LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang  
               LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
               $whereClause";

if (!empty($params)) {
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
} else {
    $countResult = mysqli_query($conn, $countQuery);
    $totalRecords = mysqli_fetch_assoc($countResult)['total'];
}

$totalPages = ceil($totalRecords / $recordsPerPage);

// Get filtered data with pagination
$query = "SELECT j.*, 
          k.nama_kelas, 
          g.nama_gelombang,
          i.nama as nama_instruktur,
          DAYNAME(j.tanggal) as hari_nama,
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
          LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
          $whereClause
          $orderClause
          LIMIT $recordsPerPage OFFSET $offset";

if (!empty($params)) {
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
} else {
    $result = mysqli_query($conn, $query);
}

// Untuk dropdown kelas
$kelasQuery = "SELECT k.*, g.nama_gelombang 
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               ORDER BY k.nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Untuk dropdown instruktur  
$instrukturQuery = "SELECT * FROM instruktur ORDER BY nama";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

// Untuk dropdown hari
$hariOptions = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

// Function to build URL with current filters
function buildUrlWithFilters($page = null) {
    global $filterKelas, $filterInstruktur, $filterHari, $filterPeriode, $filterTanggal, $searchTerm;
    
    $params = [];
    if ($page) $params['page'] = $page;
    if (!empty($searchTerm)) $params['search'] = $searchTerm;
    if (!empty($filterKelas)) $params['filter_kelas'] = $filterKelas;
    if (!empty($filterInstruktur)) $params['filter_instruktur'] = $filterInstruktur;
    if (!empty($filterHari)) $params['filter_hari'] = $filterHari;
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
  <title>Manajemen Data Jadwal</title>
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
                <h2 class="page-title mb-1">DATA JADWAL</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Data Akademik</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Data Jadwal</li>
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

        <!-- Main Content Card -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-pencil-square me-2"></i>Kelola Data Jadwal
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <div class="btn-group me-2">
                  <button type="button" class="btn btn-tambah-soft dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-plus-circle me-1"></i>
                    Tambah Data
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item" href="tambah.php">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>
                        Tambah Manual
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="generate.php">
                        <i class="bi bi-calendar-range text-success me-2"></i>
                        Generate Otomatis
                      </a>
                    </li>
                  </ul>
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
                        if (!empty($filterInstruktur)) $activeFilters++;
                        if (!empty($filterHari)) $activeFilters++;
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
                          <i class="bi bi-funnel me-2"></i>Filter Data
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
                                <option value="<?= htmlspecialchars($kelas['nama_kelas']) ?>" <?= ($filterKelas == $kelas['nama_kelas']) ? 'selected' : '' ?>>
                                  <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                  <?php if($kelas['nama_gelombang']): ?>
                                    (<?= htmlspecialchars($kelas['nama_gelombang']) ?>)
                                  <?php endif; ?>
                                </option>
                              <?php endwhile;
                            } ?>
                          </select>
                        </div>
                        
                        <!-- Filter Instruktur -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Instruktur</label>
                          <select class="form-select form-select-sm" name="filter_instruktur">
                            <option value="">Semua Instruktur</option>
                            <?php 
                            if ($instrukturResult) {
                              mysqli_data_seek($instrukturResult, 0);
                              while($instruktur = mysqli_fetch_assoc($instrukturResult)): ?>
                                <option value="<?= htmlspecialchars($instruktur['nama']) ?>" <?= ($filterInstruktur == $instruktur['nama']) ? 'selected' : '' ?>>
                                  <?= htmlspecialchars($instruktur['nama']) ?>
                                </option>
                              <?php endwhile;
                            } ?>
                          </select>
                        </div>
                        
                        <!-- Filter Hari -->
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Hari</label>
                          <select class="form-select form-select-sm" name="filter_hari">
                            <option value="">Semua Hari</option>
                            <?php foreach($hariOptions as $hari): ?>
                              <option value="<?= $hari ?>" <?= ($filterHari == $hari) ? 'selected' : '' ?>><?= $hari ?></option>
                            <?php endforeach; ?>
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
                      <span class="info-label">data</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
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
                  <th>Instruktur</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1; // Start numbering from correct position
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
                      <!-- No -->
                      <td class="text-center align-middle" style="text-align: center !important;"><?= $no++ ?></td>
                      
                      <!-- Tanggal -->
                      <td class="align-middle">
                        <span class="<?= $isToday ? 'text-primary' : ($isPast ? 'text-muted' : '') ?>">
                          <?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?>
                        </span>
                      </td>
                      
                      <!-- Hari -->
                      <td class="align-middle">
                        <span class="<?= $isToday ? 'text-primary' : '' ?>">
                          <?= htmlspecialchars($jadwal['hari_indonesia']) ?>
                        </span>
                      </td>
                      
                      <!-- Waktu -->
                      <td class="align-middle text-start">
                        <span>
                          <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - 
                          <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?>
                        </span>
                      </td>
                      
                      <!-- Kelas -->
                      <td class="align-middle">
                        <div class="fw-medium"><?= htmlspecialchars($jadwal['nama_kelas']) ?></div>
                        <?php if($jadwal['nama_gelombang']): ?>
                          <small class="text-muted"><?= htmlspecialchars($jadwal['nama_gelombang']) ?></small>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Instruktur -->
                      <td class="align-middle">
                        <span><?= htmlspecialchars($jadwal['nama_instruktur'] ?? 'Belum ditentukan') ?></span>
                      </td>
                      
                      <!-- Status -->
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
                      
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="detail.php?id=<?= $jadwal['id_jadwal'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="edit.php?id=<?= $jadwal['id_jadwal'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Edit">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $jadwal['id_jadwal'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <div class="modal fade" id="modalHapus<?= $jadwal['id_jadwal'] ?>" tabindex="-1" aria-labelledby="modalHapusLabel<?= $jadwal['id_jadwal'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content border-0 shadow-lg">
                          
                          <!-- Modal Header -->
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <div class="warning-icon">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                              </div>
                              <h5 class="modal-title" id="modalHapusLabel<?= $jadwal['id_jadwal'] ?>">
                                Konfirmasi Hapus
                              </h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <!-- Modal Body -->
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus jadwal:</p>
                            
                            <div class="alert alert-light border">
                              <div class="row">
                                <div class="col-4 text-muted small">Tanggal:</div>
                                <div class="col-8 fw-medium"><?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Waktu:</div>
                                <div class="col-8 fw-medium">
                                  <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - 
                                  <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?>
                                </div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Kelas:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($jadwal['nama_kelas']) ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Instruktur:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($jadwal['nama_instruktur'] ?? '-') ?></div>
                              </div>
                            </div>
                            
                            <div class="alert alert-warning">
                              <i class="bi bi-info-circle me-2"></i>
                              Data absensi terkait juga akan ikut terhapus
                            </div>
                          </div>
                          
                          <!-- Modal Footer -->
                          <div class="modal-footer border-0">
                            <div class="row g-2 w-100">
                              <div class="col-6">
                                <button type="button" 
                                        class="btn btn-secondary w-100" 
                                        data-bs-dismiss="modal">
                                  <i class="bi bi-x-lg"></i>
                                  Batal
                                </button>
                              </div>
                              <div class="col-6">
                                <button type="button" 
                                        class="btn btn-danger w-100" 
                                        onclick="confirmDelete(<?= $jadwal['id_jadwal'] ?>, '<?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?>')">
                                  <i class="bi bi-trash"></i>
                                  Hapus
                                </button>
                              </div>
                            </div>
                          </div>
                          
                        </div>
                      </div>
                    </div>
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
                            Belum Ada Jadwal
                          <?php endif; ?>
                        </h5>
                        <p class="mb-3 text-muted">
                          <?php if ($activeFilters > 0): ?>
                            Coba ubah kriteria filter atau reset filter untuk melihat semua jadwal
                          <?php else: ?>
                            Mulai buat jadwal pelatihan untuk mengatur kegiatan belajar mengajar
                          <?php endif; ?>
                        </p>
                        <div class="btn-group">
                          <?php if ($activeFilters > 0): ?>
                            <a href="?" class="btn btn-secondary">
                              <i class="bi bi-arrow-clockwise me-2"></i>Reset Filter
                            </a>
                          <?php endif; ?>
                          <a href="tambah.php" class="btn btn-tambah-soft">
                            <i class="bi bi-calendar-plus me-2"></i>Tambah Jadwal Manual
                          </a>
                          <a href="generate.php" class="btn btn-success">
                            <i class="bi bi-calendar-range me-2"></i>Generate Otomatis
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
                  <!-- Previous Button -->
                  <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($currentPage > 1) ? buildUrlWithFilters($currentPage - 1) : '#' ?>">
                      <i class="bi bi-chevron-left"></i>
                    </a>
                  </li>
                  
                  <?php
                  // Calculate pagination range
                  $startPage = max(1, $currentPage - 2);
                  $endPage = min($totalPages, $currentPage + 2);
                  
                  // Adjust range if we're near the beginning or end
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

  <!-- Scripts - Offline -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.getElementById('searchInput');
    const filterTanggal = document.getElementById('filterTanggal');
    
    // Auto submit on search input with debounce
    let searchTimeout;
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          document.getElementById('pageInput').value = 1; // Reset to page 1
          form.submit();
        }, 500);
      });
    }
    
    // Auto submit on date filter change
    if (filterTanggal) {
      filterTanggal.addEventListener('change', function() {
        document.getElementById('pageInput').value = 1; // Reset to page 1
        form.submit();
      });
    }
    
    // Prevent dropdown from closing when clicking inside filter dropdown
    const filterDropdown = document.querySelector('.dropdown-menu.p-3');
    if (filterDropdown) {
      filterDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }
    
    // Initialize tooltips
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }
  });

  // Fungsi konfirmasi hapus
  function confirmDelete(id, tanggal) {
    // Tutup modal Bootstrap terlebih dahulu
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalHapus' + id));
    if (modal) {
      modal.hide();
    }
    
    // Tampilkan loading pada tombol
    const deleteBtn = document.querySelector(`#modalHapus${id} .btn-danger`);
    if (deleteBtn) {
      deleteBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Memproses...';
      deleteBtn.disabled = true;
    }
    
    // Tunggu modal tertutup, lalu redirect
    setTimeout(() => {
      window.location.href = `hapus.php?id=${id}&confirm=delete`;
    }, 1000);
  }
  </script>
</body>
</html>