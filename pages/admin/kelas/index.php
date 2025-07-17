<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'kelas'; 
$baseURL = '../';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure minimum page is 1
$offset = ($currentPage - 1) * $recordsPerPage;

// Count total records untuk pagination
$countQuery = "SELECT COUNT(*) as total FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Ambil data kelas dengan join ke tabel gelombang dan instruktur dengan pagination
$query = "SELECT k.*, g.nama_gelombang, g.status as status_gelombang, i.nama as nama_instruktur,
          COUNT(s.id_siswa) as jumlah_siswa
          FROM kelas k 
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
          LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
          GROUP BY k.id_kelas, k.nama_kelas, k.kapasitas, k.id_gelombang, k.id_instruktur, g.nama_gelombang, g.status, i.nama
          ORDER BY k.id_kelas DESC
          LIMIT $recordsPerPage OFFSET $offset";
$result = mysqli_query($conn, $query);

// Hitung kelas aktif
$queryAktif = "SELECT COUNT(*) as total FROM kelas k 
               JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               WHERE g.status = 'aktif'";
$resultAktif = mysqli_query($conn, $queryAktif);
$kelasAktif = mysqli_fetch_assoc($resultAktif)['total'];

// Hitung kelas penuh
$queryPenuh = "SELECT COUNT(*) as total FROM (
                SELECT k.id_kelas 
                FROM kelas k 
                LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
                WHERE k.kapasitas > 0
                GROUP BY k.id_kelas, k.kapasitas
                HAVING COUNT(s.id_siswa) >= k.kapasitas
               ) as kelas_penuh";
$resultPenuh = mysqli_query($conn, $queryPenuh);
$kelasPenuh = mysqli_fetch_assoc($resultPenuh)['total'];

// Untuk dropdown gelombang
$gelombangQuery = "SELECT DISTINCT g.nama_gelombang FROM gelombang g ORDER BY g.nama_gelombang";
$gelombangResult = mysqli_query($conn, $gelombangQuery);

// Untuk dropdown instruktur  
$instrukturQuery = "SELECT DISTINCT i.nama FROM instruktur i ORDER BY i.nama";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

// Untuk dropdown status gelombang
$statusOptions = ['aktif', 'selesai', 'dibuka'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Data Kelas</title>
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
                <h2 class="page-title mb-1">DATA KELAS</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Data Master</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Data Kelas</li>
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
                  <i class="bi bi-pencil-square me-2"></i>Kelola Data Kelas
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <a href="tambah.php" class="btn btn-primary-formal">
                  <i class="bi bi-plus-circle"></i>
                  Tambah Data
                </a>
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
                          <i class="bi bi-sort-alpha-down me-2"></i>Nama Kelas A-Z
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="nama" data-order="desc">
                          <i class="bi bi-sort-alpha-up me-2"></i>Nama Kelas Z-A
                        </a>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="gelombang" data-order="asc">
                          <i class="bi bi-calendar-range me-2"></i>Gelombang
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="kapasitas" data-order="desc">
                          <i class="bi bi-people me-2"></i>Kapasitas
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="siswa" data-order="desc">
                          <i class="bi bi-person-check me-2"></i>Jumlah Siswa
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
                                <?= htmlspecialchars($gelombang['nama_gelombang']) ?>
                              </option>
                            <?php endwhile;
                          } ?>
                        </select>
                      </div>
                      
                      <!-- Filter Instruktur -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Instruktur</label>
                        <select class="form-select form-select-sm" id="filterInstruktur">
                          <option value="">Semua Instruktur</option>
                          <?php 
                          if ($instrukturResult) {
                            mysqli_data_seek($instrukturResult, 0);
                            while($instruktur = mysqli_fetch_assoc($instrukturResult)): ?>
                              <option value="<?= htmlspecialchars($instruktur['nama']) ?>">
                                <?= htmlspecialchars($instruktur['nama']) ?>
                              </option>
                            <?php endwhile;
                          } ?>
                        </select>
                      </div>
                                         
                      <!-- Filter Kapasitas -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Kapasitas</label>
                        <select class="form-select form-select-sm" id="filterKapasitas">
                          <option value="">Semua Kapasitas</option>
                          <option value="penuh">Kelas Penuh</option>
                          <option value="tersedia">Masih Tersedia</option>
                          <option value="kosong">Belum Ada Siswa</option>
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
            <table class="custom-table mb-0" id="kelasTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Nama Kelas</th>
                  <th>Gelombang</th>
                  <th>Kapasitas</th>
                  <th>Instruktur</th>
                  <th>Jumlah Siswa</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <!-- TBODY YANG SUDAH DIPERBAIKI -->
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1; // Start numbering from correct position
                  while ($kelas = mysqli_fetch_assoc($result)): 
                  ?>
                    <?php
                    $jumlahSiswa = (int)$kelas['jumlah_siswa'];
                    $kapasitas = (int)$kelas['kapasitas'];
                    $isKelassPenuh = $kapasitas > 0 && $jumlahSiswa >= $kapasitas;
                    $persentase = $kapasitas > 0 ? round(($jumlahSiswa / $kapasitas) * 100, 1) : 0;
                    ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <!-- Nama Kelas -->
                      <td class="align-middle text-nowrap">
                        <div class="fw-medium"><?= htmlspecialchars($kelas['nama_kelas']) ?></div>
                      </td>
                      
                      <!-- Gelombang - DIPERBAIKI -->
                      <td class="align-middle">
                        <div class="fw-medium"><?= htmlspecialchars($kelas['nama_gelombang'] ?? 'Belum Ditentukan') ?></div>
                      </td>
                      
                      <!-- Kapasitas -->
                      <td class="align-middle text-nowrap">
                        <span class="fw-medium"><?= $kapasitas ?></span>
                        <?php if($kapasitas > 0): ?>
                        <small class="text-muted">Siswa</small>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Instruktur -->
                      <td class="align-middle">
                        <?php if($kelas['nama_instruktur']): ?>
                          <span><?= htmlspecialchars($kelas['nama_instruktur']) ?></span>
                        <?php else: ?>
                          <span class="text-muted fst-italic">
                            <i class="bi bi-dash-circle me-1"></i>
                            Belum ditentukan
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Jumlah Siswa - DIPERBAIKI -->
                      <td class="text-center align-middle">
                        <div class="siswa-info">
                          <div class="siswa-count <?= $isKelassPenuh ? 'siswa-penuh' : 'siswa-normal' ?>">
                            <span class="count-angka"><?= $jumlahSiswa ?></span>
                            <span class="count-separator">/</span>
                            <span class="count-total"><?= $kapasitas ?></span>
                          </div>
                          
                          <?php if($kapasitas > 0): ?>
                            <div class="progress-container">
                              <div class="progress-bar-custom">
                                <div class="progress-fill <?= $isKelassPenuh ? 'progress-warning' : 'progress-primary' ?>" 
                                     style="width: <?= min($persentase, 100) ?>%"></div>
                              </div>
                            </div>
                          <?php else: ?>
                            <small class="no-data">-</small>
                          <?php endif; ?>
                        </div>
                      </td>
                      
                      
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="detail.php?id=<?= $kelas['id_kelas'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="edit.php?id=<?= $kelas['id_kelas'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Edit">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $kelas['id_kelas'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <div class="modal fade" id="modalHapus<?= $kelas['id_kelas'] ?>" tabindex="-1" aria-labelledby="modalHapusLabel<?= $kelas['id_kelas'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content border-0 shadow-lg">
                          
                          <!-- Modal Header -->
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <div class="warning-icon">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                              </div>
                              <h5 class="modal-title" id="modalHapusLabel<?= $kelas['id_kelas'] ?>">
                                Konfirmasi Hapus
                              </h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <!-- Modal Body -->
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus kelas:</p>
                            
                            <div class="alert alert-light border">
                              <div class="row">
                                <div class="col-4 text-muted small">Nama Kelas:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($kelas['nama_kelas']) ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Gelombang:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($kelas['nama_gelombang'] ?? '-') ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Instruktur:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($kelas['nama_instruktur'] ?? 'Belum ditentukan') ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Siswa:</div>
                                <div class="col-8 fw-medium"><?= $jumlahSiswa ?> dari <?= $kapasitas ?> siswa</div>
                              </div>
                            </div>
                            
                            <div class="alert alert-warning">
                              <i class="bi bi-info-circle me-2"></i>
                              <?php if($jumlahSiswa > 0): ?>
                                Data kelas dan <?= $jumlahSiswa ?> siswa terkait akan terpengaruh
                              <?php else: ?>
                                Data kelas akan dihapus permanen
                              <?php endif; ?>
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
                                        onclick="confirmDelete(<?= $kelas['id_kelas'] ?>, '<?= htmlspecialchars($kelas['nama_kelas']) ?>')">
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
                    <td colspan="7" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-building display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Data Kelas</h5>
                        <p class="mb-3 text-muted">Mulai tambahkan data kelas untuk mengatur program pelatihan</p>
                        <a href="tambah.php" class="btn btn-primary">
                          <i class="bi bi-plus-circle me-2"></i>Tambah Kelas Pertama
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
    const table = document.getElementById('kelasTable');
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
              const aName = (a.cells[1]?.textContent || '').trim().toLowerCase();
              const bName = (b.cells[1]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aName.localeCompare(bName) : bName.localeCompare(aName);
            });
            break;
            
          case 'gelombang':
            sortedRows = [...rows].sort((a, b) => {
              const aGelombang = (a.cells[2]?.textContent || '').trim().toLowerCase();
              const bGelombang = (b.cells[2]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aGelombang.localeCompare(bGelombang) : bGelombang.localeCompare(aGelombang);
            });
            break;
            
          case 'kapasitas':
            sortedRows = [...rows].sort((a, b) => {
              const aKapasitas = parseInt((a.cells[3]?.textContent || '').trim()) || 0;
              const bKapasitas = parseInt((b.cells[3]?.textContent || '').trim()) || 0;
              return order === 'asc' ? aKapasitas - bKapasitas : bKapasitas - aKapasitas;
            });
            break;
            
          case 'siswa':
            sortedRows = [...rows].sort((a, b) => {
              const aSiswa = parseInt((a.cells[5]?.querySelector('span')?.textContent || '').trim()) || 0;
              const bSiswa = parseInt((b.cells[5]?.querySelector('span')?.textContent || '').trim()) || 0;
              return order === 'asc' ? aSiswa - bSiswa : bSiswa - aSiswa;
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
    const filterGelombang = document.getElementById('filterGelombang');
    const filterInstruktur = document.getElementById('filterInstruktur');
    const filterStatus = document.getElementById('filterStatus');
    const filterKapasitas = document.getElementById('filterKapasitas');
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
      const gelombangFilter = filterGelombang?.value || '';
      const instrukturFilter = filterInstruktur?.value || '';
      const statusFilter = filterStatus?.value || '';
      const kapasitasFilter = filterKapasitas?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (gelombangFilter) activeFilters++;
      if (instrukturFilter) activeFilters++;
      if (statusFilter) activeFilters++;
      if (kapasitasFilter) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach(row => {
        try {
          const namaKelas = (row.cells[1]?.textContent || '').toLowerCase();
          
          const gelombangElement = row.cells[2];
          const gelombang = gelombangElement ? gelombangElement.textContent.trim() : '';
          
          const instruktur = (row.cells[4]?.textContent || '').trim();
          
          const kapasitas = parseInt((row.cells[3]?.textContent || '').trim()) || 0;
          const jumlahSiswa = parseInt((row.cells[5]?.querySelector('span')?.textContent || '').trim()) || 0;
          
          let showRow = true;
          
          // Filter search
          if (searchTerm && 
              !namaKelas.includes(searchTerm) && 
              !gelombang.toLowerCase().includes(searchTerm) &&
              !instruktur.toLowerCase().includes(searchTerm)) {
            showRow = false;
          }
          
          // Filter gelombang
          if (gelombangFilter && gelombang !== gelombangFilter) showRow = false;
          
          // Filter instruktur
          if (instrukturFilter && instruktur !== instrukturFilter) showRow = false;
          
          // Filter kapasitas
          if (kapasitasFilter) {
            if (kapasitasFilter === 'penuh' && (kapasitas === 0 || jumlahSiswa < kapasitas)) showRow = false;
            if (kapasitasFilter === 'tersedia' && (kapasitas === 0 || jumlahSiswa >= kapasitas)) showRow = false;
            if (kapasitasFilter === 'kosong' && jumlahSiswa > 0) showRow = false;
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
        if (filterGelombang) filterGelombang.value = '';
        if (filterInstruktur) filterInstruktur.value = '';
        if (filterStatus) filterStatus.value = '';
        if (filterKapasitas) filterKapasitas.value = '';
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
  function confirmDelete(id, namaKelas) {
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