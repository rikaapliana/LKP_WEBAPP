<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'pengguna'; 
$baseURL = '../';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Ensure minimum page is 1
$offset = ($currentPage - 1) * $recordsPerPage;

// Count total records untuk pagination
$countQuery = "SELECT COUNT(DISTINCT u.id_user) as total FROM user u";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Ambil data user dengan join ke tabel admin, instruktur, dan siswa dengan pagination
$query = "SELECT u.id_user, u.username, u.role, u.created_at,
          CASE 
            WHEN u.role = 'admin' THEN a.nama
            WHEN u.role = 'instruktur' THEN i.nama
            WHEN u.role = 'siswa' THEN s.nama
            ELSE NULL
          END as nama_lengkap,
          CASE 
            WHEN u.role = 'admin' THEN a.email
            WHEN u.role = 'instruktur' THEN i.email
            WHEN u.role = 'siswa' THEN s.email
            ELSE NULL
          END as email,
          CASE 
            WHEN u.role = 'instruktur' THEN i.status_aktif
            WHEN u.role = 'siswa' THEN s.status_aktif
            ELSE 'aktif'
          END as status_aktif
          FROM user u 
          LEFT JOIN admin a ON u.id_user = a.id_user AND u.role = 'admin'
          LEFT JOIN instruktur i ON u.id_user = i.id_user AND u.role = 'instruktur'
          LEFT JOIN siswa s ON u.id_user = s.id_user AND u.role = 'siswa'
          ORDER BY u.created_at DESC
          LIMIT $recordsPerPage OFFSET $offset";
$result = mysqli_query($conn, $query);

// Hitung statistik user
$queryAdmin = "SELECT COUNT(*) as total FROM user WHERE role = 'admin'";
$resultAdmin = mysqli_query($conn, $queryAdmin);
$totalAdmin = mysqli_fetch_assoc($resultAdmin)['total'];

$queryInstruktur = "SELECT COUNT(*) as total FROM user WHERE role = 'instruktur'";
$resultInstruktur = mysqli_query($conn, $queryInstruktur);
$totalInstruktur = mysqli_fetch_assoc($resultInstruktur)['total'];

$querySiswa = "SELECT COUNT(*) as total FROM user WHERE role = 'siswa'";
$resultSiswa = mysqli_query($conn, $querySiswa);
$totalSiswa = mysqli_fetch_assoc($resultSiswa)['total'];

// Function untuk build URL dengan filter (untuk pagination)
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
  <title>Manajemen Data Pengguna</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
  <!-- SweetAlert2 for better alerts -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <style>
    /* Style untuk button cetak */
    .btn-cetak-pdf {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      border: none;
      color: white;
      transition: all 0.3s ease;
      box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
    }
    
    .btn-cetak-pdf:hover {
      background: linear-gradient(135deg, #c82333 0%, #b21e2f 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
      color: white;
    }
    
    .btn-cetak-pdf:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }
    
    .btn-cetak-pdf .fa-spinner {
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Responsive button group */
    .button-group-header {
      gap: 8px;
    }
    
    @media (max-width: 768px) {
      .button-group-header {
        flex-direction: column;
        width: 100%;
      }
      
      .button-group-header .btn {
        width: 100%;
        margin-bottom: 5px;
      }
    }

    /* Role badge styles */
    .role-badge {
      font-size: 0.75rem;
      padding: 0.25rem 0.5rem;
      border-radius: 0.375rem;
      font-weight: 500;
    }
    
    .role-admin {
      background-color: #ee1a6bff;
      color: white;
    }
    
    .role-instruktur {
      background-color: #0d6efd;
      color: white;
    }
    
    .role-siswa {
      background-color: #23d180ff;
      color: white;
    }

    /* Status badge styles */
    .status-badge {
      font-size: 0.75rem;
      padding: 0.25rem 0.5rem;
      border-radius: 0.375rem;
      font-weight: 500;
    }
    
    .status-aktif {
      background-color: #d1e7dd;
      color: #0f5132;
      border: 1px solid #badbcc;
    }
    
    .status-nonaktif {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c2c7;
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
                <h2 class="page-title mb-1">DATA PENGGUNA</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Data Master</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Data Pengguna</li>
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Total Pengguna</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($totalRecords) ?></h3>
                    <small class="text-muted stats-subtitle">Keseluruhan pengguna</small>
                  </div>
                  <div class="stats-icon bg-primary-light stats-icon-mobile">
                    <i class="bi bi-people text-primary"></i>
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
                    <h6 class="mb-1 stats-title">Admin</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($totalAdmin) ?></h3>
                    <small class="text-muted stats-subtitle">Administrator</small>
                  </div>
                  <div class="stats-icon bg-danger-light stats-icon-mobile">
                    <i class="bi bi-shield-check text-danger"></i>
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
                    <h6 class="mb-1 stats-title">Instruktur</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($totalInstruktur) ?></h3>
                    <small class="text-muted stats-subtitle">Tenaga pengajar</small>
                  </div>
                  <div class="stats-icon bg-info-light stats-icon-mobile">
                    <i class="bi bi-person-workspace text-info"></i>
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
                    <h6 class="mb-1 stats-title">Siswa</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($totalSiswa) ?></h3>
                    <small class="text-muted stats-subtitle">Peserta kursus</small>
                  </div>
                  <div class="stats-icon bg-success-light stats-icon-mobile">
                    <i class="bi bi-mortarboard text-success"></i>
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
                  <i class="bi bi-people me-2"></i>Kelola Data Pengguna
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <!-- Button Group dengan Cetak PDF -->
                <div class="d-flex button-group-header justify-content-md-end">                 
                  <!-- Button Tambah Data -->
                  <a href="tambah.php" class="btn btn-tambah-soft">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Data
                  </a>
                  <!-- Button Cetak PDF -->
                  <button type="button" 
                          class="btn btn-cetak-soft" 
                          onclick="cetakLaporanPDF()" 
                          id="btnCetakPDF"
                          title="Cetak laporan data pengguna dalam format PDF">
                    <i class="bi bi-printer me-2"></i>Cetak Data
                  </button>
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
                        <a class="dropdown-item sort-option" href="#" data-sort="username" data-order="asc">
                          <i class="bi bi-sort-alpha-down me-2"></i>Username A-Z
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="username" data-order="desc">
                          <i class="bi bi-sort-alpha-up me-2"></i>Username Z-A
                        </a>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="role" data-order="asc">
                          <i class="bi bi-person-gear me-2"></i>Role
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="created_at" data-order="desc">
                          <i class="bi bi-calendar-plus me-2"></i>Terbaru
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
                    <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 300px;" id="filterDropdown">
                      <h6 class="mb-3 fw-bold">
                        <i class="bi bi-funnel me-2"></i>Filter Data
                      </h6>
                      
                      <!-- Filter Role -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Role</label>
                        <select class="form-select form-select-sm" id="filterRole">
                          <option value="">Semua Role</option>
                          <option value="admin">Admin</option>
                          <option value="instruktur">Instruktur</option>
                          <option value="siswa">Siswa</option>
                        </select>
                      </div>
                      
                      <!-- Filter Status -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Status</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                          <option value="">Semua Status</option>
                          <option value="aktif">Aktif</option>
                          <option value="nonaktif">Non-aktif</option>
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
            <table class="custom-table mb-0" id="penggunaTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Nama Lengkap</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Terdaftar</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1; // Start numbering from correct position
                  while ($user = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle" style="text-align: center !important;"><?= $no++ ?></td>
                      
                      <!-- Username -->
                      <td class="align-middle">
                        <div class="fw-medium"><?= htmlspecialchars($user['username']) ?></div>
                      </td>
                      
                      <!-- Role -->
                      <td class="align-middle">
                        <span class="role-badge role-<?= htmlspecialchars($user['role']) ?>">
                          <?= ucfirst(htmlspecialchars($user['role'])) ?>
                        </span>
                      </td>
                      
                      <!-- Nama Lengkap -->
                      <td class="align-middle">
                        <?php if($user['nama_lengkap']): ?>
                          <div><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                        <?php else: ?>
                          <span class="text-muted fst-italic">
                            <i class="bi bi-dash-circle me-1"></i>
                            Belum diatur
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Email -->
                      <td class="align-middle">
                        <?php if($user['email']): ?>
                          <small><?= htmlspecialchars($user['email']) ?></small>
                        <?php else: ?>
                          <span class="text-muted fst-italic">
                            <i class="bi bi-dash-circle me-1"></i>
                            Belum diatur
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Status -->
                      <td class="align-middle">
                        <span class="status-badge status-<?= htmlspecialchars($user['status_aktif']) ?>">
                          <?= ucfirst(htmlspecialchars($user['status_aktif'])) ?>
                        </span>
                      </td>
                      
                      <!-- Created At -->
                      <td class="align-middle">
                        <small><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></small>
                      </td>
                      
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="edit.php?id=<?= $user['id_user'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Edit">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $user['id_user'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <div class="modal fade" id="modalHapus<?= $user['id_user'] ?>" tabindex="-1" aria-labelledby="modalHapusLabel<?= $user['id_user'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content border-0 shadow-lg">
                          
                          <!-- Modal Header -->
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <div class="warning-icon">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                              </div>
                              <h5 class="modal-title" id="modalHapusLabel<?= $user['id_user'] ?>">
                                Konfirmasi Hapus
                              </h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <!-- Modal Body -->
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus data pengguna:</p>
                            
                            <div class="student-preview">
                              <!-- Icon User -->
                              <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-person-fill text-white fs-4"></i>
                              </div>
                              
                              <!-- Info User -->
                              <div class="text-center">
                                <div class="fw-bold">
                                  <?= htmlspecialchars($user['username']) ?>
                                </div>
                                <div class="text-muted">
                                  Role: <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                </div>
                              </div>
                            </div>
                            
                            <div class="alert alert-warning">
                              <i class="bi bi-info-circle me-2"></i>
                              Data pengguna dan semua data terkait akan dihapus permanen
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
                                        onclick="confirmDelete(<?= $user['id_user'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
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
                        <i class="bi bi-people display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Data Pengguna</h5>
                        <p class="mb-3 text-muted">Mulai tambahkan data pengguna untuk mengelola akses sistem</p>
                        <a href="tambah.php" class="btn btn-tambah-soft">
                          <i class="bi bi-plus-circle me-2"></i>Tambah Pengguna Pertama
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

        </div>
      </div>
    </div>
  </div>

  <!-- Scripts - Offline -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  // Fungsi Cetak PDF - BARU
  function cetakLaporanPDF() {
    const button = document.getElementById('btnCetakPDF');
    const originalHTML = button.innerHTML;
    
    // Set loading state
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating PDF...';
    
    // Ambil filter yang sedang aktif dari dropdown
    const filterRole = document.getElementById('filterRole')?.value || '';
    const filterStatus = document.getElementById('filterStatus')?.value || '';
    const searchTerm = document.getElementById('searchInput')?.value || '';
    
    // Build URL parameter untuk cetak laporan
    const params = new URLSearchParams();
    
    // Tambahkan filter yang aktif
    if (filterRole) params.append('role', filterRole);
    if (filterStatus) params.append('status', filterStatus);
    if (searchTerm) params.append('search', searchTerm);
    
    // Build URL untuk cetak laporan
    let cetakURL = 'cetak_laporan.php';
    if (params.toString()) {
      cetakURL += '?' + params.toString();
    }
    
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
      
      // Show alert dengan link manual menggunakan SweetAlert2
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

  document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('penggunaTable');
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('.empty-state'));
    const filterButton = document.getElementById('filterButton');
    const filterBadge = document.getElementById('filterBadge');
    
    const originalOrder = [...rows];
    let activeFilters = 0;

    // Cek apakah ada data untuk enable/disable button cetak
    const btnCetakPDF = document.getElementById('btnCetakPDF');
    if (btnCetakPDF) {
      const hasData = rows.length > 0;
      if (!hasData) {
        btnCetakPDF.disabled = true;
        btnCetakPDF.innerHTML = '<i class="bi bi-printer me-2"></i>Tidak Ada Data';
        btnCetakPDF.title = 'Tidak ada data pengguna untuk dicetak';
      }
    }

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
          case 'username':
            sortedRows = [...rows].sort((a, b) => {
              const aUsername = (a.cells[1]?.textContent || '').trim().toLowerCase();
              const bUsername = (b.cells[1]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aUsername.localeCompare(bUsername) : bUsername.localeCompare(aUsername);
            });
            break;
            
          case 'role':
            sortedRows = [...rows].sort((a, b) => {
              const aRole = (a.cells[2]?.textContent || '').trim().toLowerCase();
              const bRole = (b.cells[2]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aRole.localeCompare(bRole) : bRole.localeCompare(aRole);
            });
            break;
            
          case 'created_at':
            sortedRows = [...rows].sort((a, b) => {
              const aDate = (a.cells[6]?.textContent || '').trim();
              const bDate = (b.cells[6]?.textContent || '').trim();
              return order === 'desc' ? bDate.localeCompare(aDate) : aDate.localeCompare(bDate);
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
    const filterRole = document.getElementById('filterRole');
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
      const roleFilter = filterRole?.value || '';
      const statusFilter = filterStatus?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (roleFilter) activeFilters++;
      if (statusFilter) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach(row => {
        try {
          const username = (row.cells[1]?.textContent || '').toLowerCase();
          const namaLengkap = (row.cells[3]?.textContent || '').toLowerCase();
          const email = (row.cells[4]?.textContent || '').toLowerCase();
          
          // Role (kolom 2 - ambil dari badge)
          const role = (row.cells[2]?.textContent || '').trim().toLowerCase();
          
          // Status (kolom 5 - ambil dari badge)
          const status = (row.cells[5]?.textContent || '').trim().toLowerCase();
          
          let showRow = true;
          
          // Filter search
          if (searchTerm && 
              !username.includes(searchTerm) && 
              !namaLengkap.includes(searchTerm) &&
              !email.includes(searchTerm)) {
            showRow = false;
          }
          
          // Filter role
          if (roleFilter && role !== roleFilter) showRow = false;
          
          // Filter status
          if (statusFilter && status !== statusFilter) showRow = false;
          
          row.style.display = showRow ? '' : 'none';
          if (showRow) visibleCount++;
          
        } catch (error) {
          console.error('Filter error for row:', error);
          row.style.display = '';
          visibleCount++;
        }
      });
      
      updateRowNumbers();
      
      // Update button cetak berdasarkan hasil filter
      if (btnCetakPDF && rows.length > 0) {
        if (visibleCount > 0) {
          btnCetakPDF.disabled = false;
          btnCetakPDF.innerHTML = '<i class="bi bi-printer me-2"></i>Cetak Data';
          btnCetakPDF.title = `Cetak laporan ${visibleCount} data pengguna`;
        } else {
          btnCetakPDF.disabled = true;
          btnCetakPDF.innerHTML = '<i class="bi bi-printer me-2"></i>Tidak Ada Data';
          btnCetakPDF.title = 'Tidak ada data yang sesuai filter';
        }
      }
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
        if (filterRole) filterRole.value = '';
        if (filterStatus) filterStatus.value = '';
        applyFilters();
      });
    }
    
    const filterDropdown = document.getElementById('filterDropdown');
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
  function confirmDelete(id, username) {
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