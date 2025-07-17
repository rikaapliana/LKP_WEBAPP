<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

$activePage = 'instruktur'; 
$baseURL = '../';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure minimum page is 1
$offset = ($currentPage - 1) * $recordsPerPage;

// Count total records untuk pagination
$countQuery = "SELECT COUNT(DISTINCT i.id_instruktur) as total FROM instruktur i 
               LEFT JOIN user u ON i.id_user = u.id_user 
               LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Ambil data instruktur dengan join ke tabel user dan kelas yang diampu dengan pagination
$query = "SELECT i.*, u.username, u.role,
          GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') as kelas_diampu
          FROM instruktur i 
          LEFT JOIN user u ON i.id_user = u.id_user 
          LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur
          GROUP BY i.id_instruktur, i.nik, i.nama, i.jenis_kelamin, i.angkatan, i.pas_foto, u.username, u.role
          ORDER BY i.id_instruktur DESC
          LIMIT $recordsPerPage OFFSET $offset";
$result = mysqli_query($conn, $query);

// Untuk dropdown angkatan
$angkatanQuery = "SELECT DISTINCT angkatan FROM instruktur WHERE angkatan IS NOT NULL AND angkatan != '' ORDER BY angkatan";
$angkatanResult = mysqli_query($conn, $angkatanQuery);

// Untuk dropdown kelas
$kelasQuery = "SELECT DISTINCT nama_kelas FROM kelas ORDER BY nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Data Instruktur</title>
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
                <h2 class="page-title mb-1">DATA INSTRUKTUR</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Data Master</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Data Instruktur</li>
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
                  <i class="bi bi-pencil-square me-2"></i>Kelola Data Instruktur
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <a href="tambah.php" class="btn btn-primary-formal">
                  <i class="bi bi-plus-circle"></i>
                  Tambah Instruktur
                </a>
                <div class="btn-group ms-2">
                  <button type="button" class="btn btn-secondary-formal dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i>
                    Export Data
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item" href="export-pdf.php" target="_blank">
                        <i class="bi bi-file-pdf text-danger me-2"></i>
                        Export ke PDF
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="export-excel.php">
                        <i class="bi bi-file-excel text-success me-2"></i>
                        Export ke Excel
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
                    <input type="search" id="searchInput" class="form-control form-control-sm search-input" />
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
                        <a class="dropdown-item sort-option" href="#" data-sort="nik" data-order="asc">
                          <i class="bi bi-credit-card me-2"></i>NIK
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="angkatan" data-order="asc">
                          <i class="bi bi-calendar-range me-2"></i>Angkatan
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
                      
                      <!-- Filter Jenis Kelamin -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Jenis Kelamin</label>
                        <select class="form-select form-select-sm" id="filterJK">
                          <option value="">Semua</option>
                          <option value="Laki-Laki">Laki-Laki</option>
                          <option value="Perempuan">Perempuan</option>
                        </select>
                      </div>
                      
                      <!-- Filter Angkatan -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Angkatan</label>
                        <select class="form-select form-select-sm" id="filterAngkatan">
                          <option value="">Semua Angkatan</option>
                          <?php 
                          if ($angkatanResult) {
                            mysqli_data_seek($angkatanResult, 0);
                            while($angkatan = mysqli_fetch_assoc($angkatanResult)): ?>
                              <option value="<?= htmlspecialchars($angkatan['angkatan']) ?>">
                                <?= htmlspecialchars($angkatan['angkatan']) ?>
                              </option>
                            <?php endwhile;
                          } ?>
                        </select>
                      </div>
                      
                      <!-- Filter Kelas -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Kelas yang Diampu</label>
                        <select class="form-select form-select-sm" id="filterKelas">
                          <option value="">Semua Kelas</option>
                          <?php 
                          if ($kelasResult) {
                            mysqli_data_seek($kelasResult, 0);
                            while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                              <option value="<?= htmlspecialchars($kelas['nama_kelas']) ?>">
                                <?= htmlspecialchars($kelas['nama_kelas']) ?>
                              </option>
                            <?php endwhile;
                          } ?>
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
            <table class="custom-table mb-0" id="instrukturTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>NIK</th>
                  <th>Nama</th>
                  <th>Jenis Kelamin</th>
                  <th>Angkatan</th>
                  <th>Kelas Diampu</th>
                  <th>Foto</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1; // Start numbering from correct position
                  while ($instruktur = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle" style="text-align: center !important;"><?= $no++ ?></td>
                      
                      <!-- NIK -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($instruktur['nik'] ?? '-') ?></small>
                      </td>
                      
                      <!-- Nama -->
                      <td class="align-middle text-nowrap">
                        <div class="fw-medium"><?= htmlspecialchars($instruktur['nama']) ?></div>
                      </td>
                      
                      <!-- Jenis Kelamin -->
                      <td class="align-middle">
                        <?php if($instruktur['jenis_kelamin'] == 'Laki-Laki'): ?>
                          <span>Laki-Laki</span>
                        <?php else: ?>
                          <span>Perempuan</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Angkatan -->
                      <td class="align-middle">
                        <span><?= htmlspecialchars($instruktur['angkatan'] ?? '-') ?></span>
                      </td>
                      
                      <!-- Kelas yang Diampu -->
                      <td class="align-middle">
                        <?php if($instruktur['kelas_diampu'] && trim($instruktur['kelas_diampu']) != ''): ?>
                          <span>
                            <?= htmlspecialchars($instruktur['kelas_diampu']) ?>
                          </span>
                        <?php else: ?>
                          <span class="text-muted fst-italic">
                            <i class="bi bi-dash-circle me-1"></i>
                            Belum ada kelas
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Foto -->
                      <td class="text-center align-middle" style="text-align: center !important;">
                        <?php if($instruktur['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$instruktur['pas_foto'])): ?>
                          <img src="../../../uploads/pas_foto/<?= $instruktur['pas_foto'] ?>" 
                               alt="Foto" 
                               class="rounded photo-preview" 
                               style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #e9ecef; margin: 0 auto; display: block;" 
                               title="<?= htmlspecialchars($instruktur['nama']) ?>">
                        <?php else: ?>
                          <div class="photo-preview-placeholder" 
                               style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 6px; color: #6c757d; margin: 0 auto;">
                            <i class="bi bi-person-fill"></i>
                          </div>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="detail.php?id=<?= $instruktur['id_instruktur'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="edit.php?id=<?= $instruktur['id_instruktur'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Edit">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $instruktur['id_instruktur'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <div class="modal fade" id="modalHapus<?= $instruktur['id_instruktur'] ?>" tabindex="-1" aria-labelledby="modalHapusLabel<?= $instruktur['id_instruktur'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content border-0 shadow-lg">
                          
                          <!-- Modal Header -->
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <div class="warning-icon">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                              </div>
                              <h5 class="modal-title" id="modalHapusLabel<?= $instruktur['id_instruktur'] ?>">
                                Konfirmasi Hapus
                              </h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <!-- Modal Body -->
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus data instruktur:</p>
                            
                            <div class="student-preview">
                              <!-- Foto Instruktur -->
                              <?php if($instruktur['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$instruktur['pas_foto'])): ?>
                                <img src="../../../uploads/pas_foto/<?= $instruktur['pas_foto'] ?>" 
                                     alt="Foto Instruktur" 
                                     class="rounded-circle">
                              <?php else: ?>
                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center">
                                  <i class="bi bi-person-fill text-white fs-4"></i>
                                </div>
                              <?php endif; ?>
                              
                              <!-- Info Instruktur -->
                              <div class="text-center">
                                <div class="fw-bold">
                                  <?= htmlspecialchars($instruktur['nama']) ?>
                                </div>
                                <div class="text-muted">
                                  NIK: <?= htmlspecialchars($instruktur['nik'] ?? '-') ?>
                                </div>
                              </div>
                            </div>
                            
                            <div class="alert alert-warning">
                              <i class="bi bi-info-circle me-2"></i>
                              Data dan semua file terkait akan dihapus permanen
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
                                        onclick="confirmDelete(<?= $instruktur['id_instruktur'] ?>, '<?= htmlspecialchars($instruktur['nama']) ?>')">
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
                        <i class="bi bi-person-workspace display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Data Instruktur</h5>
                        <p class="mb-3 text-muted">Mulai tambahkan data instruktur untuk mengelola tenaga pengajar</p>
                        <a href="tambah.php" class="btn btn-primary">
                          <i class="bi bi-plus-circle me-2"></i>Tambah Instruktur Pertama
                        </a>
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
    const table = document.getElementById('instrukturTable');
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
              const aName = (a.cells[2]?.textContent || '').trim().toLowerCase();
              const bName = (b.cells[2]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aName.localeCompare(bName) : bName.localeCompare(aName);
            });
            break;
            
          case 'nik':
            sortedRows = [...rows].sort((a, b) => {
              const aNik = (a.cells[1]?.textContent || '').trim();
              const bNik = (b.cells[1]?.textContent || '').trim();
              return order === 'asc' ? aNik.localeCompare(bNik) : bNik.localeCompare(aNik);
            });
            break;
            
          case 'angkatan':
            sortedRows = [...rows].sort((a, b) => {
              const aAngkatan = (a.cells[4]?.textContent || '').trim();
              const bAngkatan = (b.cells[4]?.textContent || '').trim();
              return order === 'asc' ? aAngkatan.localeCompare(bAngkatan) : bAngkatan.localeCompare(aAngkatan);
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
    const filterJK = document.getElementById('filterJK');
    const filterAngkatan = document.getElementById('filterAngkatan');
    const filterKelas = document.getElementById('filterKelas');
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
      const jkFilter = filterJK?.value || '';
      const angkatanFilter = filterAngkatan?.value || '';
      const kelasFilter = filterKelas?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (jkFilter) activeFilters++;
      if (angkatanFilter) activeFilters++;
      if (kelasFilter) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach(row => {
        try {
          const nama = (row.cells[2]?.textContent || '').toLowerCase();
          const nik = (row.cells[1]?.textContent || '').toLowerCase();
          
          // Jenis Kelamin (kolom 3 - text langsung, bukan badge)
          const jk = (row.cells[3]?.textContent || '').trim();
          
          // Angkatan (kolom 4)
          const angkatan = (row.cells[4]?.textContent || '').trim();
          
          // Kelas yang diampu (kolom 5)
          const kelas = (row.cells[5]?.textContent || '').toLowerCase();
          
          let showRow = true;
          
          // Filter search
          if (searchTerm && 
              !nama.includes(searchTerm) && 
              !nik.includes(searchTerm)) {
            showRow = false;
          }
          
          // Filter jenis kelamin
          if (jkFilter && jk !== jkFilter) showRow = false;
          
          // Filter angkatan
          if (angkatanFilter && angkatan !== angkatanFilter) showRow = false;
          
          // Filter kelas
          if (kelasFilter && !kelas.includes(kelasFilter.toLowerCase())) showRow = false;
          
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
        if (filterJK) filterJK.value = '';
        if (filterAngkatan) filterAngkatan.value = '';
        if (filterKelas) filterKelas.value = '';
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

  // Fungsi konfirmasi hapus
  function confirmDelete(id, nama) {
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