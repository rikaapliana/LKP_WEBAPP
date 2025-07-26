<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'hasil-evaluasi'; 
$baseURL = '../';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Count total records untuk pagination
$countQuery = "SELECT COUNT(DISTINCT pe.id_periode) as total 
               FROM periode_evaluasi pe 
               LEFT JOIN evaluasi e ON pe.id_periode = e.id_periode
               WHERE pe.status IN ('aktif', 'selesai')";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Query utama untuk mengambil data periode evaluasi dengan statistik
$query = "SELECT 
            pe.id_periode,
            pe.nama_evaluasi,
            pe.jenis_evaluasi,
            pe.materi_terkait,
            pe.tanggal_buka,
            pe.tanggal_tutup,
            pe.status,
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
          WHERE pe.status IN ('aktif', 'selesai')
          GROUP BY pe.id_periode
          ORDER BY pe.tanggal_buka DESC
          LIMIT $recordsPerPage OFFSET $offset";
$result = mysqli_query($conn, $query);

// Untuk dropdown filter
$gelombangQuery = "SELECT DISTINCT g.id_gelombang, g.nama_gelombang, g.tahun 
                   FROM gelombang g 
                   JOIN periode_evaluasi pe ON g.id_gelombang = pe.id_gelombang 
                   WHERE pe.status IN ('aktif', 'selesai')
                   ORDER BY g.tahun DESC, g.nama_gelombang";
$gelombangResult = mysqli_query($conn, $gelombangQuery);

// Helper function untuk pagination
function buildUrlWithFilters($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hasil Evaluasi</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
  <style>
  /* Custom styling untuk tombol aksi hasil evaluasi */
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

  .btn-action.btn-info {
    color: #0dcaf0;
    background-color: #f0fcff;
  }

  .btn-action.btn-info:hover {
    background-color: #0dcaf0;
    color: white;
    border-color: #0dcaf0;
  }

  .btn-action.btn-secondary {
    color: #6c757d;
    background-color: #f8f9fa;
  }

  .btn-action.btn-secondary:hover {
    background-color: #6c757d;
    color: white;
    border-color: #6c757d;
  }

  /* Responsive untuk mobile */
  @media (max-width: 768px) {
    .btn-group .btn-action {
      min-width: 28px;
      height: 28px;
      font-size: 0.75rem;
    }
    
    .btn-group .btn-action i {
      font-size: 0.75rem;
    }
  }

  /* Progress bar styling yang lebih baik */
  .progress-info {
    min-width: 120px;
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
                <h2 class="page-title mb-1">HASIL EVALUASI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Hasil Evaluasi</li>
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
              <div class="col-md-8">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-clipboard-data me-2"></i>Data Hasil Evaluasi
                </h5>
              </div>
              <div class="col-md-4 text-md-end">
                <!-- Quick Action Buttons -->
                <div class="d-flex align-items-centre justify-content-end gap-0" role="group">
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="../evaluasi/periode/index.php">
                        <i class="bi bi-calendar-plus me-2"></i>
                        Kelola Periode
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <a class="dropdown-item" href="../evaluasi/pertanyaan/index.php">
                        <i class="bi bi-question-circle me-2"></i>
                        Kelola Pertanyaan
                      </a>
                    </li>
                  </ul>
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
                    <input type="search" id="searchInput" class="form-control form-control-sm search-input"  />
                  </div>
                  
                  <!-- Sort Button -->
                  <div class="dropdown">
                    <button class="btn btn-light btn-icon position-relative control-btn" 
                            type="button" 
                            data-bs-toggle="dropdown" 
                            data-bs-display="static"
                            aria-expanded="false"
                            title="Sort">
                      <i class="bi bi-arrow-down-up"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 200px;">
                      <li><h6 class="dropdown-header">Sort by</h6></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="nama" data-order="asc">
                          <i class="bi bi-sort-alpha-down me-2"></i>Nama A-Z
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="nama" data-order="desc">
                          <i class="bi bi-sort-alpha-up me-2"></i>Nama Z-A
                        </a>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="tanggal" data-order="desc">
                          <i class="bi bi-calendar-check me-2"></i>Terbaru
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="tanggal" data-order="asc">
                          <i class="bi bi-calendar-x me-2"></i>Terlama
                        </a>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="status" data-order="asc">
                          <i class="bi bi-check-square me-2"></i>Status
                        </a>
                      </li>
                    </ul>
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
                      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="filterBadge">
                        0
                      </span>
                    </button>
                    
                    <!-- Filter Dropdown -->
                    <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 300px;">
                      <h6 class="mb-3 fw-bold">
                        <i class="bi bi-funnel me-2"></i>Filter Data
                      </h6>
                      
                      <!-- Filter Status -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Status Periode</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                          <option value="">Semua Status</option>
                          <option value="aktif">Aktif</option>
                          <option value="selesai">Selesai</option>
                        </select>
                      </div>
                      
                      <!-- Filter Jenis -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Jenis Evaluasi</label>
                        <select class="form-select form-select-sm" id="filterJenis">
                          <option value="">Semua Jenis</option>
                          <option value="per_materi">Per Materi</option>
                          <option value="akhir_kursus">Akhir Kursus</option>
                        </select>
                      </div>
                      
                      <!-- Filter Gelombang -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Gelombang</label>
                        <select class="form-select form-select-sm" id="filterGelombang">
                          <option value="">Semua Gelombang</option>
                          <?php 
                          if ($gelombangResult) {
                            mysqli_data_seek($gelombangResult, 0);
                            while($gelombang = mysqli_fetch_assoc($gelombangResult)): ?>
                              <option value="<?= htmlspecialchars($gelombang['nama_gelombang']) ?>">
                                <?= htmlspecialchars($gelombang['nama_gelombang']) ?> (<?= $gelombang['tahun'] ?>)
                              </option>
                            <?php endwhile;
                          } ?>
                        </select>
                      </div>
                      
                      <!-- Filter Materi -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Materi Terkait</label>
                        <select class="form-select form-select-sm" id="filterMateri">
                          <option value="">Semua Materi</option>
                          <option value="word">Microsoft Word</option>
                          <option value="excel">Microsoft Excel</option>
                          <option value="ppt">Microsoft PowerPoint</option>
                          <option value="internet">Internet & Email</option>
                        </select>
                      </div>
                      
                      <hr class="my-3">
                      
                      <!-- Filter Buttons -->
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
                      <span class="info-label">data</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="evaluasiTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Periode Evaluasi</th>
                  <th>Jenis & Materi</th>
                  <th>Gelombang</th>
                  <th>Tanggal Periode</th>
                  <th>Total Siswa</th>
                  <th>Progress</th>
                  <th>Completion Rate</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($periode = mysqli_fetch_assoc($result)): 
                    $belum_mengerjakan = $periode['total_siswa_aktif'] - $periode['total_mengerjakan'];
                    $completion_rate = $periode['total_siswa_aktif'] > 0 ? 
                                      round(($periode['selesai'] / $periode['total_siswa_aktif']) * 100, 1) : 0;
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <!-- Periode Evaluasi -->
                      <td class="align-middle">
                        <div class="fw-medium"><?= htmlspecialchars($periode['nama_evaluasi']) ?></div>
                        <small class="text-muted">ID: <?= $periode['id_periode'] ?></small>
                      </td>
                      
                      <!-- Jenis & Materi -->
                      <td class="align-middle">
                        <div class="d-flex flex-column">
                          <?php if($periode['jenis_evaluasi'] == 'per_materi'): ?>
                            <span class="badge bg-info-subtle text-info mb-1">Per Materi</span>
                            <?php 
                            $materi_label = [
                              'word' => 'Microsoft Word',
                              'excel' => 'Microsoft Excel', 
                              'ppt' => 'Microsoft PowerPoint',
                              'internet' => 'Internet & Email'
                            ];
                            ?>
                            <small class="text-muted">
                              <?= $materi_label[$periode['materi_terkait']] ?? ucfirst($periode['materi_terkait']) ?>
                            </small>
                          <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary">Akhir Kursus</span>
                            <small class="text-muted">Evaluasi komprehensif</small>
                          <?php endif; ?>
                        </div>
                      </td>
                      
                      <!-- Gelombang -->
                      <td class="align-middle">
                        <div class="fw-medium"><?= htmlspecialchars($periode['nama_gelombang']) ?></div>
                        <small class="text-muted">Tahun <?= $periode['tahun'] ?></small>
                      </td>
                      
                      <!-- Tanggal Periode -->
                      <td class="align-middle">
                        <div class="d-flex flex-column">
                          <small class="text-success">
                            <i class="bi bi-calendar-plus me-1"></i>
                            <?= date('d/m/Y H:i', strtotime($periode['tanggal_buka'])) ?>
                          </small>
                          <small class="text-danger">
                            <i class="bi bi-calendar-x me-1"></i>
                            <?= date('d/m/Y H:i', strtotime($periode['tanggal_tutup'])) ?>
                          </small>
                        </div>
                      </td>
                      
                      <!-- Total Siswa -->
                      <td class="text-center align-middle">
                        <div class="fw-medium"><?= number_format($periode['total_siswa_aktif']) ?></div>
                        <small class="text-muted">siswa aktif</small>
                      </td>
                      
                      <!-- Progress -->
                      <td class="align-middle">
                        <div class="progress-info">
                          <div class="d-flex justify-content-between mb-1">
                            <small class="text-success">Selesai: <?= $periode['selesai'] ?></small>
                          </div>
                          <div class="d-flex justify-content-between">
                            <small class="text-muted">Belum: <?= $belum_mengerjakan ?></small>
                          </div>
                        </div>
                      </td>
                      
                      <!-- Completion Rate -->
                      <td class="align-middle">
                        <div class="d-flex align-items-center">
                          <div class="progress flex-grow-1 me-2" style="height: 8px;">
                            <div class="progress-bar bg-success" 
                                 role="progressbar" 
                                 style="width: <?= $completion_rate ?>%"
                                 aria-valuenow="<?= $completion_rate ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                          </div>
                          <small class="fw-medium"><?= $completion_rate ?>%</small>
                        </div>
                      </td>
                      
                      <!-- Status -->
                      <td class="text-center align-middle">
                        <?php if($periode['status'] == 'aktif'): ?>
                          <span class="badge badge-active">Aktif</span>
                        <?php else: ?>
                          <span class="badge badge-inactive">Selesai</span>
                        <?php endif; ?>
                      </td>

                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <!-- Detail Button -->
                          <a href="detail.php?id_periode=<?= $periode['id_periode'] ?>" 
                             class="btn btn-action btn-view" 
                             data-bs-toggle="tooltip" 
                             title="Lihat Hasil">
                            <i class="bi bi-eye me-1"></i>Lihat
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="10" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-clipboard-x display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Hasil Evaluasi</h5>
                        <p class="mb-3 text-muted">Hasil evaluasi akan muncul setelah siswa mengerjakan evaluasi</p>
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

  <!-- Scripts - Offline -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('evaluasiTable');
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('.empty-state'));
    const filterButton = document.getElementById('filterButton');
    const filterBadge = document.getElementById('filterBadge');
    
    const originalOrder = [...rows];
    let activeFilters = 0;

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

    // Sort functionality
    function initializeSortOptions() {
      document.querySelectorAll('.sort-option').forEach(item => {
        item.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          document.querySelectorAll('.sort-option').forEach(opt => {
            opt.classList.remove('active');
            opt.style.backgroundColor = '';
            opt.style.color = '';
          });
          
          this.classList.add('active');
          this.style.backgroundColor = '#0d6efd';
          this.style.color = 'white';
          
          const sortType = this.dataset.sort;
          const sortOrder = this.dataset.order;
          
          sortTable(sortType, sortOrder);
          
          setTimeout(() => {
            const dropdownToggle = this.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
            if (dropdownToggle) {
              const dropdown = bootstrap.Dropdown.getInstance(dropdownToggle);
              if (dropdown) dropdown.hide();
            }
          }, 150);
        });
      });
    }
    
    function sortTable(type, order) {
      let sortedRows;
      
      try {
        switch(type) {
          case 'nama':
            sortedRows = [...rows].sort((a, b) => {
              const aName = (a.cells[1]?.querySelector('.fw-medium')?.textContent || '').trim().toLowerCase();
              const bName = (b.cells[1]?.querySelector('.fw-medium')?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aName.localeCompare(bName) : bName.localeCompare(aName);
            });
            break;
            
          case 'tanggal':
            sortedRows = [...rows].sort((a, b) => {
              const parseDate = (dateStr) => {
                try {
                  const parts = dateStr.split(' ');
                  if (parts.length >= 2) {
                    const datePart = parts[0].split('/');
                    if (datePart.length === 3) {
                      return new Date(datePart[2], datePart[1] - 1, datePart[0]);
                    }
                  }
                  return new Date(0);
                } catch (e) {
                  return new Date(0);
                }
              };
              
              const aDateStr = (a.cells[4]?.querySelector('small.text-success')?.textContent || '').replace(/.*(\d{2}\/\d{2}\/\d{4}).*/, '$1');
              const bDateStr = (b.cells[4]?.querySelector('small.text-success')?.textContent || '').replace(/.*(\d{2}\/\d{2}\/\d{4}).*/, '$1');
              const aDate = parseDate(aDateStr);
              const bDate = parseDate(bDateStr);
              return order === 'asc' ? aDate - bDate : bDate - aDate;
            });
            break;
            
          case 'status':
            sortedRows = [...rows].sort((a, b) => {
              const aStatus = (a.cells[8]?.querySelector('.badge')?.textContent || '').trim().toLowerCase();
              const bStatus = (b.cells[8]?.querySelector('.badge')?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aStatus.localeCompare(bStatus) : bStatus.localeCompare(aStatus);
            });
            break;
            
          default:
            sortedRows = [...originalOrder];
        }
        
        const fragment = document.createDocumentFragment();
        sortedRows.forEach(row => fragment.appendChild(row));
        tbody.appendChild(fragment);
        
        updateRowNumbers();
        
      } catch (error) {
        console.error('Sort error:', error);
        const fragment = document.createDocumentFragment();
        originalOrder.forEach(row => fragment.appendChild(row));
        tbody.appendChild(fragment);
        updateRowNumbers();
      }
    }

    // Filter functionality
    const searchInput = document.getElementById('searchInput');
    const filterStatus = document.getElementById('filterStatus');
    const filterJenis = document.getElementById('filterJenis');
    const filterGelombang = document.getElementById('filterGelombang');
    const filterMateri = document.getElementById('filterMateri');
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
      const statusFilter = (filterStatus?.value || '').toLowerCase();
      const jenisFilter = filterJenis?.value || '';
      const gelombangFilter = filterGelombang?.value || '';
      const materiFilter = filterMateri?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (statusFilter) activeFilters++;
      if (jenisFilter) activeFilters++;
      if (gelombangFilter) activeFilters++;
      if (materiFilter) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach(row => {
        try {
          const nama = (row.cells[1]?.querySelector('.fw-medium')?.textContent || '').toLowerCase();
          const gelombang = (row.cells[3]?.querySelector('.fw-medium')?.textContent || '').toLowerCase();
          
          const statusElement = row.cells[8]?.querySelector('.badge');
          let status = '';
          if (statusElement) {
            const statusText = statusElement.textContent.trim().toLowerCase();
            status = statusText.includes('aktif') ? 'aktif' : 'selesai';
          }
          
          const jenisElement = row.cells[2]?.querySelector('.badge');
          let jenis = '';
          if (jenisElement) {
            const jenisText = jenisElement.textContent.trim().toLowerCase();
            jenis = jenisText.includes('per materi') ? 'per_materi' : 'akhir_kursus';
          }
          
          const materiElement = row.cells[2]?.querySelector('small');
          let materi = '';
          if (materiElement) {
            const materiText = materiElement.textContent.trim().toLowerCase();
            if (materiText.includes('word')) materi = 'word';
            else if (materiText.includes('excel')) materi = 'excel';
            else if (materiText.includes('powerpoint')) materi = 'ppt';
            else if (materiText.includes('internet')) materi = 'internet';
          }
          
          let showRow = true;
          
          if (searchTerm && 
              !nama.includes(searchTerm) && 
              !gelombang.includes(searchTerm)) {
            showRow = false;
          }
          
          if (statusFilter && status !== statusFilter) showRow = false;
          if (jenisFilter && jenis !== jenisFilter) showRow = false;
          if (gelombangFilter && !gelombang.includes(gelombangFilter.toLowerCase())) showRow = false;
          if (materiFilter && materi !== materiFilter) showRow = false;
          
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
        if (filterJenis) filterJenis.value = '';
        if (filterGelombang) filterGelombang.value = '';
        if (filterMateri) filterMateri.value = '';
        applyFilters();
      });
    }
    
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
    initializeSortOptions();
    
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
    
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }
    
    window.addEventListener('resize', forceDropdownPositioning);
    window.addEventListener('scroll', forceDropdownPositioning);
  });
  </script>
</body>
</html>