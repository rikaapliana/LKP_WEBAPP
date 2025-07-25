<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth(); // Hanya instruktur yang bisa akses

include '../../../includes/db.php';
$activePage = 'siswa-saya'; 
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

// Ambil filter kelas dari URL (jika ada)
$filterKelas = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Base query condition
$baseCondition = "k.id_instruktur = ?";
$params = [$id_instruktur];
$paramTypes = "i";

if ($filterKelas > 0) {
    $baseCondition .= " AND k.id_kelas = ?";
    $params[] = $filterKelas;
    $paramTypes .= "i";
}

// Count total records untuk pagination
$countQuery = "SELECT COUNT(*) as total FROM siswa s 
               LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               WHERE " . $baseCondition;
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param($paramTypes, ...$params);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Query siswa dari kelas yang diampu instruktur - disesuaikan dengan struktur admin
$query = "SELECT s.*, k.nama_kelas, g.nama_gelombang, g.status as status_gelombang,
          u.username, u.role
          FROM siswa s 
          LEFT JOIN user u ON s.id_user = u.id_user
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          WHERE " . $baseCondition . "
          ORDER BY s.id_siswa DESC
          LIMIT ? OFFSET ?";

$params[] = $recordsPerPage;
$params[] = $offset;
$paramTypes .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Ambil daftar kelas yang diampu untuk filter dropdown
$kelasQuery = "SELECT id_kelas, nama_kelas FROM kelas WHERE id_instruktur = ? ORDER BY nama_kelas";
$kelasStmt = $conn->prepare($kelasQuery);
$kelasStmt->bind_param("i", $id_instruktur);
$kelasStmt->execute();
$kelasResult = $kelasStmt->get_result();

// Handle update status siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $siswa_id = (int)$_POST['siswa_id'];
    $new_status = $_POST['status'];
    
    // Validasi status
    if (!in_array($new_status, ['aktif', 'nonaktif'])) {
        $_SESSION['error'] = "Status tidak valid!";
    } else {
        // Cek apakah siswa ada di kelas yang diampu instruktur ini
        $checkQuery = "SELECT s.nama FROM siswa s 
                      JOIN kelas k ON s.id_kelas = k.id_kelas
                      WHERE s.id_siswa = ? AND k.id_instruktur = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ii", $siswa_id, $id_instruktur);
        $checkStmt->execute();
        $siswaCheck = $checkStmt->get_result()->fetch_assoc();
        
        if ($siswaCheck) {
            // Update status siswa
            $updateQuery = "UPDATE siswa SET status_aktif = ? WHERE id_siswa = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $new_status, $siswa_id);
            
            if ($updateStmt->execute()) {
                $_SESSION['success'] = "Status siswa " . htmlspecialchars($siswaCheck['nama']) . " berhasil diubah menjadi " . ucfirst($new_status);
            } else {
                $_SESSION['error'] = "Gagal mengubah status siswa!";
            }
        } else {
            $_SESSION['error'] = "Siswa tidak ditemukan atau bukan siswa Anda!";
        }
    }
    
    // Redirect untuk menghindari resubmit
    $redirectUrl = "index.php";
    if ($filterKelas > 0) $redirectUrl .= "?kelas=" . $filterKelas;
    if ($currentPage > 1) {
        $redirectUrl .= ($filterKelas > 0 ? "&" : "?") . "page=" . $currentPage;
    }
    header("Location: " . $redirectUrl);
    exit();
}

// Function untuk build URL dengan filter
function buildUrlWithFilters($page) {
    global $filterKelas;
    $params = [];
    if ($filterKelas > 0) $params['kelas'] = $filterKelas;
    if ($page > 1) $params['page'] = $page;
    return 'index.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Siswa Saya - Instruktur</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
  <!-- SweetAlert2 for notifications -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/instruktur.php'; ?>

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
                <h2 class="page-title mb-1">SISWA SAYA</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Siswa Saya</li>
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
        <!-- Main Content Card -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-people me-2"></i>Daftar Siswa Yang Diampu
                  <?php if ($filterKelas > 0): ?>
                    <small class="text-muted">(Filtered by Class)</small>
                  <?php endif; ?>
                </h5>
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
                  
                  <!-- Filter Kelas -->
                  <div class="d-flex align-items-center">
                    <label for="filterKelas" class="me-2 mb-0 search-label">
                      <small>Kelas:</small>
                    </label>
                    <select id="filterKelasDropdown" class="form-select form-select-sm" style="min-width: 120px;" onchange="applyKelasFilter()">
                      <option value="">Semua Kelas</option>
                      <?php 
                      mysqli_data_seek($kelasResult, 0);
                      while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                        <option value="<?= $kelas['id_kelas'] ?>" <?= ($filterKelas == $kelas['id_kelas']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($kelas['nama_kelas']) ?>
                        </option>
                      <?php endwhile; ?>
                    </select>
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
                        <a class="dropdown-item sort-option" href="#" data-sort="kelas" data-order="asc">
                          <i class="bi bi-building me-2"></i>Kelas
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="status" data-order="asc">
                          <i class="bi bi-person-check me-2"></i>Status
                        </a>
                      </li>
                    </ul>
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
          
          <!-- Table - Struktur sama dengan admin -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="siswaTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>NIK</th>
                  <th>Nama</th>
                  <th>Tempat Lahir</th>
                  <th>Tanggal Lahir</th>
                  <th>Jenis Kelamin</th>
                  <th>Pendidikan</th>
                  <th>Telepon</th>
                  <th>Email</th>
                  <th>Alamat</th>
                  <th>Kelas</th>
                  <th>Foto</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($siswa = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle" style="text-align: center !important;"><?= $no++ ?></td>
                      
                      <!-- NIK -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($siswa['nik']) ?></small>
                      </td>
                      
                      <!-- Nama -->
                      <td class="align-middle text-nowrap">
                        <div class="fw-medium"><?= htmlspecialchars($siswa['nama']) ?></div>
                      </td>
                      
                      <!-- Tempat Lahir -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($siswa['tempat_lahir'] ?? '-') ?></small>
                      </td>
                      
                      <!-- Tanggal Lahir -->
                      <td class="align-middle">
                        <small><?= $siswa['tanggal_lahir'] ? date('d/m/Y', strtotime($siswa['tanggal_lahir'])) : '-' ?></small>
                      </td>
                      
                      <!-- Jenis Kelamin -->
                      <td class="align-middle">
                        <?php if($siswa['jenis_kelamin'] == 'Laki-Laki'): ?>
                          <span>Laki-Laki</span>
                        <?php else: ?>
                          <span>Perempuan</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Pendidikan -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($siswa['pendidikan_terakhir'] ?? '-') ?></small>
                      </td>
                      
                      <!-- Telepon -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($siswa['no_hp'] ?? '-') ?></small>
                      </td>
                      
                      <!-- Email -->
                      <td class="align-middle">
                        <small class="text-muted text-truncate d-inline-block" 
                               style="max-width: 150px;" 
                               title="<?= htmlspecialchars($siswa['email'] ?? '-') ?>">
                          <?= htmlspecialchars($siswa['email'] ?? '-') ?>
                        </small>
                      </td>
                      
                      <!-- Alamat -->
                      <td class="align-middle">
                        <small class="text-muted text-truncate d-inline-block" 
                               style="max-width: 200px;" 
                               title="<?= htmlspecialchars($siswa['alamat_lengkap'] ?? '-') ?>">
                          <?= htmlspecialchars($siswa['alamat_lengkap'] ?? '-') ?>
                        </small>
                      </td>
                      
                      <!-- Kelas -->
                      <td class="align-middle text-nowrap">
                        <?php if($siswa['nama_kelas']): ?>
                          <div class="fw-medium small"><?= htmlspecialchars($siswa['nama_kelas']) ?></div>
                          <small class="text-muted">(<?= htmlspecialchars($siswa['nama_gelombang'] ?? '') ?>)</small>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Foto -->
                      <td class="text-center align-middle" style="text-align: center !important;">
                        <?php if($siswa['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$siswa['pas_foto'])): ?>
                          <img src="../../../uploads/pas_foto/<?= $siswa['pas_foto'] ?>" 
                               alt="Foto" 
                               class="rounded photo-preview" 
                               style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #e9ecef; margin: 0 auto; display: block;" 
                               title="<?= htmlspecialchars($siswa['nama']) ?>">
                        <?php else: ?>
                          <div class="photo-preview-placeholder" 
                               style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 6px; color: #6c757d; margin: 0 auto;">
                            <i class="bi bi-person-fill"></i>
                          </div>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Status - Dengan dropdown untuk update -->
                      <td class="text-center align-middle" style="text-align: center !important;">
                        <form method="post" style="display: inline;" onchange="updateSiswaStatus(this)">
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="siswa_id" value="<?= $siswa['id_siswa'] ?>">
                          <select name="status" class="form-select form-select-sm <?= $siswa['status_aktif'] == 'aktif' ? 'status-active' : 'status-inactive' ?>" style="min-width: 80px;">
                            <option value="aktif" <?= ($siswa['status_aktif'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($siswa['status_aktif'] == 'nonaktif') ? 'selected' : '' ?>>Non Aktif</option>
                          </select>
                        </form>
                      </td>
                      
                      <!-- Aksi - Hanya view detail dan lihat nilai -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="detail.php?id=<?= $siswa['id_siswa'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="../nilai/index.php?siswa=<?= $siswa['id_siswa'] ?>" 
                             class="btn btn-action btn-primary btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Lihat Nilai">
                            <i class="bi bi-clipboard-data"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="14" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-people display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Siswa</h5>
                        <?php if ($filterKelas > 0): ?>
                          <p class="mb-3 text-muted">Tidak ada siswa di kelas yang dipilih</p>
                          <button class="btn btn-primary" onclick="clearKelasFilter()">
                            <i class="bi bi-funnel me-2"></i>Tampilkan Semua Siswa
                          </button>
                        <?php else: ?>
                          <p class="mb-3 text-muted">Belum ada siswa di kelas yang Anda ampu</p>
                          <a href="../kelas/index.php" class="btn btn-primary">
                            <i class="bi bi-building me-2"></i>Lihat Kelas Saya
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

  <style>
    .form-select.status-active {
      background-color: #d1edff;
      color: #0066cc;
      border-color: #0066cc;
      font-weight: 600;
    }
    
    .form-select.status-inactive {
      background-color: #fff3cd;
      color: #856404;
      border-color: #856404;
      font-weight: 600;
    }
    
    .form-select:focus {
      box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
    
    .photo-preview {
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .photo-preview:hover {
      transform: scale(1.1);
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
  </style>

  <script>
  // Update status siswa dengan SweetAlert2
  function updateSiswaStatus(form) {
    const siswaId = form.querySelector('input[name="siswa_id"]').value;
    const newStatus = form.querySelector('select[name="status"]').value;
    const selectElement = form.querySelector('select[name="status"]');
    const oldStatus = selectElement.selectedIndex === 0 ? 'nonaktif' : 'aktif';
    
    // Konfirmasi dengan SweetAlert2
    Swal.fire({
      title: 'Konfirmasi Perubahan Status',
      text: `Apakah Anda yakin ingin mengubah status siswa menjadi "${newStatus.toUpperCase()}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Ubah Status!',
      cancelButtonText: 'Batal',
      reverseButtons: true
    }).then((result) => {
      if (result.isConfirmed) {
        // Update visual immediately
        selectElement.className = selectElement.className.replace(/status-(active|inactive)/, '');
        selectElement.classList.add(newStatus === 'aktif' ? 'status-active' : 'status-inactive');
        
        // Show loading
        Swal.fire({
          title: 'Mengupdate Status...',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
        
        // Submit form
        form.submit();
      } else {
        // Reset select ke nilai sebelumnya
        selectElement.value = oldStatus;
      }
    });
  }

  // Filter kelas
  function applyKelasFilter() {
    const kelasId = document.getElementById('filterKelasDropdown').value;
    const currentUrl = new URL(window.location);
    
    // Remove existing parameters
    currentUrl.searchParams.delete('kelas');
    currentUrl.searchParams.delete('page');
    
    // Add new filter if selected
    if (kelasId) {
      currentUrl.searchParams.set('kelas', kelasId);
    }
    
    window.location.href = currentUrl.toString();
  }

  // Clear kelas filter
  function clearKelasFilter() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.delete('kelas');
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl.toString();
  }

  document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('siswaTable');
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('.empty-state'));
    const originalOrder = [...rows];

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    if (searchInput) {
      searchInput.addEventListener('input', function(e) {
        e.stopPropagation();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          applySearch();
        }, 300);
      });
    }
    
    function applySearch() {
      const searchTerm = (searchInput?.value || '').toLowerCase().trim();
      let visibleCount = 0;
      
      rows.forEach(row => {
        try {
          const nama = (row.cells[2]?.textContent || '').toLowerCase();
          const nik = (row.cells[1]?.textContent || '').toLowerCase();
          const tempat = (row.cells[3]?.textContent || '').toLowerCase();
          const email = (row.cells[8]?.textContent || '').toLowerCase();
          const telepon = (row.cells[7]?.textContent || '').toLowerCase();
          const kelas = (row.cells[10]?.textContent || '').toLowerCase();
          
          const showRow = !searchTerm || 
                         nama.includes(searchTerm) || 
                         nik.includes(searchTerm) ||
                         tempat.includes(searchTerm) ||
                         email.includes(searchTerm) ||
                         telepon.includes(searchTerm) ||
                         kelas.includes(searchTerm);
          
          row.style.display = showRow ? '' : 'none';
          if (showRow) visibleCount++;
          
        } catch (error) {
          console.error('Search error for row:', error);
          row.style.display = '';
          visibleCount++;
        }
      });
      
      updateRowNumbers();
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
              const aNama = (a.cells[2]?.textContent || '').trim().toLowerCase();
              const bNama = (b.cells[2]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aNama.localeCompare(bNama) : bNama.localeCompare(aNama);
            });
            break;
            
          case 'nik':
            sortedRows = [...rows].sort((a, b) => {
              const aNik = (a.cells[1]?.textContent || '').trim();
              const bNik = (b.cells[1]?.textContent || '').trim();
              return order === 'asc' ? aNik.localeCompare(bNik) : bNik.localeCompare(aNik);
            });
            break;
            
          case 'kelas':
            sortedRows = [...rows].sort((a, b) => {
              const aKelas = (a.cells[10]?.textContent || '').trim().toLowerCase();
              const bKelas = (b.cells[10]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aKelas.localeCompare(bKelas) : bKelas.localeCompare(aKelas);
            });
            break;
            
          case 'status':
            sortedRows = [...rows].sort((a, b) => {
              const aStatus = (a.cells[12]?.querySelector('select')?.value || '').trim().toLowerCase();
              const bStatus = (b.cells[12]?.querySelector('select')?.value || '').trim().toLowerCase();
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
    
    function updateRowNumbers() {
      let counter = <?= ($currentPage - 1) * $recordsPerPage + 1 ?>;
      rows.forEach(row => {
        if (row.style.display !== 'none') {
          row.cells[0].textContent = counter++;
        }
      });
    }

    // Tampilkan notifikasi sukses jika ada session success
    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({
      icon: 'success',
      title: 'Berhasil!',
      text: '<?= $_SESSION['success'] ?>',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
    <?php unset($_SESSION['success']); endif; ?>

    // Tampilkan notifikasi error jika ada session error  
    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({
      icon: 'error',
      title: 'Oops...',
      text: '<?= $_SESSION['error'] ?>',
      confirmButtonText: 'OK'
    });
    <?php unset($_SESSION['error']); endif; ?>

    // Initialize everything
    initializeSortOptions();
    
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

  // Fungsi cetak laporan PDF
  function cetakLaporanPDF() {
    const btnCetak = document.getElementById('btnCetakPDF');
    
    // Disable button dan ubah text
    btnCetak.disabled = true;
    btnCetak.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Memproses...';
    
    // Ambil filter yang aktif
    const searchTerm = document.getElementById('searchInput')?.value || '';
    const kelasFilter = document.getElementById('filterKelasDropdown')?.value || '';
    
    // Build URL dengan parameter
    let url = 'cetak_laporan.php';
    const params = [];
    
    if (searchTerm) params.push('search=' + encodeURIComponent(searchTerm));
    if (kelasFilter) params.push('kelas=' + encodeURIComponent(kelasFilter));
    
    if (params.length > 0) {
      url += '?' + params.join('&');
    }
    
    // Buka PDF di tab baru
    const pdfWindow = window.open(url, '_blank');
    
    // Reset button setelah delay
    setTimeout(() => {
      btnCetak.disabled = false;
      btnCetak.innerHTML = '<i class="bi bi-printer me-2"></i>Cetak Laporan';
      
      // Jika window tidak terbuka (popup blocker), beri notifikasi
      if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed == 'undefined') {
        alert('Popup diblokir! Silakan izinkan popup untuk mengunduh laporan PDF.');
      }
    }, 2000);
  }
  </script>
</body>
</html>