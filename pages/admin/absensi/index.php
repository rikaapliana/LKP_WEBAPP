<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'absensi'; 
$baseURL = '../';

// Ambil parameter tipe absensi (default: siswa)
$tipeAbsensi = isset($_GET['tipe']) ? $_GET['tipe'] : 'siswa';
if (!in_array($tipeAbsensi, ['siswa', 'instruktur'])) {
    $tipeAbsensi = 'siswa';
}

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Filter parameters
$filterTanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query berdasarkan tipe absensi
if ($tipeAbsensi == 'siswa') {
    // Query untuk absensi siswa
    $whereConditions = [];
    $params = [];
    $types = "";
    
    if (!empty($filterTanggal)) {
        $whereConditions[] = "DATE(a.waktu_absen) = ?";
        $params[] = $filterTanggal;
        $types .= "s";
    }
    
    if (!empty($filterKelas)) {
        $whereConditions[] = "k.nama_kelas = ?";
        $params[] = $filterKelas;
        $types .= "s";
    }
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(s.nama LIKE ? OR s.nik LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params = array_merge($params, [$searchParam, $searchParam]);
        $types .= "ss";
    }
    
    // Count query untuk pagination
    $countQuery = "SELECT COUNT(*) as total FROM absensi_siswa a
                   JOIN siswa s ON a.id_siswa = s.id_siswa
                   LEFT JOIN jadwal j ON a.id_jadwal = j.id_jadwal
                   LEFT JOIN kelas k ON j.id_kelas = k.id_kelas";
    
    if (!empty($whereConditions)) {
        $countQuery .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Main query
    $query = "SELECT a.id_absen, a.status, a.waktu_absen,
              s.nama as nama_siswa, s.nik,
              k.nama_kelas,
              j.tanggal as tanggal_jadwal, j.waktu_mulai, j.waktu_selesai
              FROM absensi_siswa a
              JOIN siswa s ON a.id_siswa = s.id_siswa
              LEFT JOIN jadwal j ON a.id_jadwal = j.id_jadwal
              LEFT JOIN kelas k ON j.id_kelas = k.id_kelas";
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $query .= " ORDER BY a.waktu_absen DESC LIMIT $recordsPerPage OFFSET $offset";
    
} else {
    // Query untuk absensi instruktur
    $whereConditions = [];
    $params = [];
    $types = "";
    
    if (!empty($filterTanggal)) {
        $whereConditions[] = "DATE(a.tanggal) = ?";
        $params[] = $filterTanggal;
        $types .= "s";
    }
    
    if (!empty($filterKelas)) {
        $whereConditions[] = "k.nama_kelas = ?";
        $params[] = $filterKelas;
        $types .= "s";
    }
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(i.nama LIKE ? OR i.nik LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params = array_merge($params, [$searchParam, $searchParam]);
        $types .= "ss";
    }
    
    // Count query untuk pagination
    $countQuery = "SELECT COUNT(*) as total FROM absensi_instruktur a
                   JOIN instruktur i ON a.id_instruktur = i.id_instruktur
                   LEFT JOIN jadwal j ON a.id_jadwal = j.id_jadwal
                   LEFT JOIN kelas k ON j.id_kelas = k.id_kelas";
    
    if (!empty($whereConditions)) {
        $countQuery .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Main query
    $query = "SELECT a.id_absen, a.status, a.tanggal, a.waktu, a.keterangan,
              i.nama as nama_instruktur, i.nik,
              k.nama_kelas,
              j.tanggal as tanggal_jadwal, j.waktu_mulai, j.waktu_selesai
              FROM absensi_instruktur a
              JOIN instruktur i ON a.id_instruktur = i.id_instruktur
              LEFT JOIN jadwal j ON a.id_jadwal = j.id_jadwal
              LEFT JOIN kelas k ON j.id_kelas = k.id_kelas";
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $query .= " ORDER BY a.tanggal DESC, a.waktu DESC LIMIT $recordsPerPage OFFSET $offset";
}

// Execute queries
try {
    // Count total records
    if (!empty($params)) {
        $countStmt = mysqli_prepare($conn, $countQuery);
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        $totalRecords = mysqli_fetch_assoc($countResult)['total'];
        mysqli_stmt_close($countStmt);
    } else {
        $countResult = mysqli_query($conn, $countQuery);
        $totalRecords = mysqli_fetch_assoc($countResult)['total'];
    }
    
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Main query
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
    }
    
} catch (Exception $e) {
    $error = "Terjadi kesalahan database: " . $e->getMessage();
}

// Ambil data untuk dropdown filter
$kelasQuery = "SELECT DISTINCT nama_kelas FROM kelas ORDER BY nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Function untuk build URL dengan filter
function buildUrlWithFilters($page, $tipe) {
    $params = $_GET;
    $params['page'] = $page;
    $params['tipe'] = $tipe;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Data Absensi</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
  <!-- SweetAlert2 for better alerts -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <style>
    /* Style untuk tab cards */
    .absensi-card {
      cursor: pointer;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }
    
    .absensi-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .absensi-card.active {
      border-color: #0d6efd;
      box-shadow: 0 4px 20px rgba(13, 110, 253, 0.3);
    }
    
    .absensi-card .card-body {
      padding: 1.5rem;
    }
    
    .absensi-icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
      margin-bottom: 1rem;
    }
    
    .icon-siswa {
      background: linear-gradient(135deg, #28a745, #20c997);
    }
    
    .icon-instruktur {
      background: linear-gradient(135deg, #007bff, #6610f2);
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .absensi-card .row {
        text-align: center;
      }
      
      .absensi-icon {
        margin: 0 auto 1rem auto;
      }
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
            <!-- Left: Hamburger + Page Info -->
            <div class="d-flex align-items-center flex-grow-1">
              <!-- Sidebar Toggle Button -->
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <!-- Page Title & Breadcrumb -->
              <div class="page-info">
                <h2 class="page-title mb-1">DATA ABSENSI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Data Akademik</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Data Absensi</li>
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
        <?php if (isset($_SESSION['error']) || isset($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $_SESSION['error'] ?? $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Tab Cards untuk Pilih Tipe Absensi -->
        <div class="row mb-4">
          <div class="col-md-6 mb-3">
            <div class="card absensi-card <?= $tipeAbsensi == 'siswa' ? 'active' : '' ?>" 
                 onclick="switchAbsensi('siswa')">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-auto">
                    <div class="absensi-icon icon-siswa">
                      <i class="bi bi-people-fill"></i>
                    </div>
                  </div>
                  <div class="col">
                    <h5 class="card-title mb-1">Absensi Siswa</h5>
                    <p class="card-text text-muted mb-2">Kelola data kehadiran siswa</p>
                    <small class="text-success fw-bold">
                      <i class="bi bi-check-circle me-1"></i>
                      <?= $tipeAbsensi == 'siswa' ? 'Sedang aktif' : 'Klik untuk beralih' ?>
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6 mb-3">
            <div class="card absensi-card <?= $tipeAbsensi == 'instruktur' ? 'active' : '' ?>" 
                 onclick="switchAbsensi('instruktur')">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-auto">
                    <div class="absensi-icon icon-instruktur">
                      <i class="bi bi-person-workspace"></i>
                    </div>
                  </div>
                  <div class="col">
                    <h5 class="card-title mb-1">Absensi Instruktur</h5>
                    <p class="card-text text-muted mb-2">Kelola data kehadiran instruktur</p>
                    <small class="text-primary fw-bold">
                      <i class="bi bi-check-circle me-1"></i>
                      <?= $tipeAbsensi == 'instruktur' ? 'Sedang aktif' : 'Klik untuk beralih' ?>
                    </small>
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
                  <i class="bi bi-clipboard-check me-2"></i>
                  Data Absensi <?= ucfirst($tipeAbsensi) ?>
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <!-- Button Cetak PDF -->
                <button type="button" 
                        class="btn btn-cetak-soft" 
                        onclick="cetakLaporanPDF()" 
                        id="btnCetakPDF"
                        title="Cetak laporan absensi <?= $tipeAbsensi ?> dalam format PDF">
                  <i class="bi bi-printer me-2"></i>Cetak Data
                </button>
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
                    <input type="search" id="searchInput" class="form-control form-control-sm search-input" 
                           value="<?= htmlspecialchars($searchTerm) ?>" 
                     />
                  </div>
                  
                  <!-- Filter Tanggal -->
                  <div class="d-flex align-items-center">
                    <label for="filterTanggal" class="me-2 mb-0 search-label">
                      <small>Tanggal:</small>
                    </label>
                    <input type="date" id="filterTanggal" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($filterTanggal) ?>" style="width: 150px;" />
                  </div>
                  
                  <!-- Filter Kelas -->
                  <div class="d-flex align-items-center">
                    <label for="filterKelas" class="me-2 mb-0 search-label">
                      <small>Kelas:</small>
                    </label>
                    <select id="filterKelas" class="form-select form-select-sm" style="width: 120px;">
                      <option value="">Semua</option>
                      <?php if ($kelasResult && mysqli_num_rows($kelasResult) > 0): ?>
                        <?php while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                          <option value="<?= htmlspecialchars($kelas['nama_kelas']) ?>" 
                                  <?= $filterKelas == $kelas['nama_kelas'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kelas['nama_kelas']) ?>
                          </option>
                        <?php endwhile; ?>
                      <?php endif; ?>
                    </select>
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
            <table class="custom-table mb-0" id="absensiTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th><?= $tipeAbsensi == 'siswa' ? 'Nama Siswa' : 'Nama Instruktur' ?></th>
                  <th>NIK</th>
                  <th>Kelas</th>
                  <th>Tanggal</th>
                  <th>Waktu</th>
                  <th>Status</th>
                  <?php if ($tipeAbsensi == 'instruktur'): ?>
                  <th>Keterangan</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (isset($result) && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($row = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <!-- Nama -->
                      <td class="align-middle">
                        <div class="fw-medium">
                          <?= htmlspecialchars($tipeAbsensi == 'siswa' ? $row['nama_siswa'] : $row['nama_instruktur']) ?>
                        </div>
                      </td>
                      
                      <!-- NIK -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($row['nik'] ?? '-') ?></small>
                      </td>
                      
                      <!-- Kelas -->
                      <td class="align-middle">
                        <?= htmlspecialchars($row['nama_kelas'] ?? '-') ?>
                      </td>
                      
                      <!-- Tanggal -->
                      <td class="align-middle">
                        <?php 
                        $tanggal = $tipeAbsensi == 'siswa' ? $row['waktu_absen'] : $row['tanggal'];
                        echo date('d/m/Y', strtotime($tanggal));
                        ?>
                      </td>
                      
                      <!-- Waktu -->
                      <td class="align-middle">
                        <?php 
                        if ($tipeAbsensi == 'siswa') {
                            echo date('H:i', strtotime($row['waktu_absen']));
                        } else {
                            echo $row['waktu'] ? date('H:i', strtotime($row['waktu'])) : '-';
                        }
                        ?>
                      </td>
                      
                      <!-- Status -->
                      <td class="align-middle">
                        <?php 
                        $status = $row['status'];
                        $badgeClass = '';
                        switch($status) {
                            case 'hadir': $badgeClass = 'bg-success'; break;
                            case 'izin': $badgeClass = 'bg-warning'; break;
                            case 'sakit': $badgeClass = 'bg-info'; break;
                            case 'tanpa keterangan': $badgeClass = 'bg-danger'; break;
                            default: $badgeClass = 'bg-secondary';
                        }
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                          <?= ucfirst(htmlspecialchars($status)) ?>
                        </span>
                      </td>
                      
                      <!-- Keterangan (hanya untuk instruktur) -->
                      <?php if ($tipeAbsensi == 'instruktur'): ?>
                      <td class="align-middle">
                        <small><?= htmlspecialchars($row['keterangan'] ?? '-') ?></small>
                      </td>
                      <?php endif; ?>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="<?= $tipeAbsensi == 'instruktur' ? '8' : '7' ?>" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-clipboard-x display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Data Absensi</h5>
                        <p class="mb-3 text-muted">
                          Belum ada data absensi <?= $tipeAbsensi ?> yang sesuai dengan filter yang diterapkan
                        </p>
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
                    <a class="page-link" href="<?= ($currentPage > 1) ? buildUrlWithFilters($currentPage - 1, $tipeAbsensi) : '#' ?>">
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
                      <a class="page-link" href="<?= buildUrlWithFilters(1, $tipeAbsensi) ?>">1</a>
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
                      <a class="page-link" href="<?= buildUrlWithFilters($i, $tipeAbsensi) ?>"><?= $i ?></a>
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
                      <a class="page-link" href="<?= buildUrlWithFilters($totalPages, $tipeAbsensi) ?>"><?= $totalPages ?></a>
                    </li>
                  <?php endif; ?>
                  
                  <!-- Next Button -->
                  <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($currentPage < $totalPages) ? buildUrlWithFilters($currentPage + 1, $tipeAbsensi) : '#' ?>">
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
  // Debounce function untuk search
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // Function untuk apply filters
  function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const tanggal = document.getElementById('filterTanggal').value;
    const kelas = document.getElementById('filterKelas').value;
    const tipe = '<?= $tipeAbsensi ?>';
    
    const params = new URLSearchParams();
    params.set('tipe', tipe);
    
    if (search) params.set('search', search);
    if (tanggal) params.set('tanggal', tanggal);
    if (kelas) params.set('kelas', kelas);
    
    window.location.href = '?' + params.toString();
  }

  // Function untuk switch tipe absensi
  function switchAbsensi(tipe) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('tipe', tipe);
    currentUrl.searchParams.delete('page'); // Reset ke halaman 1
    window.location.href = currentUrl.toString();
  }

  // Function untuk cetak PDF
  function cetakLaporanPDF() {
    const button = document.getElementById('btnCetakPDF');
    const originalHTML = button.innerHTML;
    
    // Set loading state
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating PDF...';
    
    // Build URL parameter untuk cetak laporan
    const params = new URLSearchParams();
    params.set('tipe', '<?= $tipeAbsensi ?>');
    
    // Tambahkan filter yang aktif
    const search = '<?= htmlspecialchars($searchTerm) ?>';
    const tanggal = '<?= htmlspecialchars($filterTanggal) ?>';
    const kelas = '<?= htmlspecialchars($filterKelas) ?>';
    
    if (search) params.set('search', search);
    if (tanggal) params.set('tanggal', tanggal);
    if (kelas) params.set('kelas', kelas);
    
    // Build URL untuk cetak laporan
    const cetakURL = 'cetak_laporan.php?' + params.toString();
    
    // Buka PDF di tab baru
    const newWindow = window.open(cetakURL, '_blank');
    
    // Reset button state setelah delay
    setTimeout(() => {
      button.disabled = false;
      button.innerHTML = originalHTML;
    }, 2000);
    
    // Handle jika popup diblokir
    if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
      button.disabled = false;
      button.innerHTML = originalHTML;
      
      // Show alert dengan SweetAlert2
      Swal.fire({
        title: 'Pop-up Diblokir!',
        html: `Browser memblokir pop-up. Klik tombol di bawah untuk membuka PDF secara manual:<br><br>
               <a href="${cetakURL}" target="_blank" class="btn btn-danger">
               <i class="bi bi-file-earmark-pdf"></i> Buka PDF Manual</a>`,
        icon: 'warning',
        showConfirmButton: false,
        showCloseButton: true,
        allowOutsideClick: true
      });
    }
  }

  // Debounced search function
  const debouncedSearch = debounce(applyFilters, 500);

  document.addEventListener('DOMContentLoaded', function() {
    // Event listener untuk search input dengan debounce
    document.getElementById('searchInput').addEventListener('input', debouncedSearch);

    // Auto-apply filter when date changes
    document.getElementById('filterTanggal').addEventListener('change', applyFilters);

    // Auto-apply filter when kelas changes
    document.getElementById('filterKelas').addEventListener('change', applyFilters);

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
  </script>
</body>
</html>