<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth(); // Hanya instruktur yang bisa akses

include '../../../includes/db.php';
$activePage = 'materi'; 
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

// Get filter parameter dari URL
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// Count total records untuk pagination - hanya materi dari kelas yang diampu
$countQuery = "SELECT COUNT(*) as total FROM materi m 
               LEFT JOIN kelas k ON m.id_kelas = k.id_kelas
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               WHERE k.id_instruktur = ?";
$countParams = [$id_instruktur];

if (!empty($filterKelas)) {
    $countQuery .= " AND k.id_kelas = ?";
    $countParams[] = $filterKelas;
}

$countStmt = $conn->prepare($countQuery);
$types = str_repeat('i', count($countParams));
$countStmt->bind_param($types, ...$countParams);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Ambil data materi dengan join - hanya dari kelas yang diampu instruktur ini
$query = "SELECT m.*, k.nama_kelas, g.nama_gelombang,
          CASE 
            WHEN m.file_materi IS NOT NULL AND m.file_materi != '' THEN 'Ada File'
            ELSE 'Tidak Ada File'
          END as status_file
          FROM materi m 
          LEFT JOIN kelas k ON m.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          WHERE k.id_instruktur = ?";

$params = [$id_instruktur];

if (!empty($filterKelas)) {
    $query .= " AND k.id_kelas = ?";
    $params[] = $filterKelas;
}

$query .= " ORDER BY m.id_materi DESC LIMIT $recordsPerPage OFFSET $offset";

$stmt = $conn->prepare($query);
$types = str_repeat('i', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Statistik materi untuk instruktur ini
$statsQuery = "SELECT 
                COUNT(*) as total_materi,
                COUNT(CASE WHEN m.file_materi IS NOT NULL AND m.file_materi != '' THEN 1 END) as materi_dengan_file,
                COUNT(CASE WHEN m.file_materi IS NULL OR m.file_materi = '' THEN 1 END) as materi_tanpa_file
               FROM materi m
               JOIN kelas k ON m.id_kelas = k.id_kelas
               WHERE k.id_instruktur = ?";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $id_instruktur);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

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

// Function untuk build URL dengan filter
function buildUrlWithFilters($page = null) {
    global $filterKelas;
    
    $params = [];
    if ($page) $params['page'] = $page;
    if (!empty($filterKelas)) $params['kelas'] = $filterKelas;
    
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Materi - Instruktur</title>
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
                <h2 class="page-title mb-1">MATERI KELAS</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Materi Kelas</li>
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
                  <i class="bi bi-journal-text me-2"></i>Kelola Materi Kelas
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <div class="d-flex button-group-header justify-content-md-end gap-2">
                  <a href="tambah.php<?= !empty($filterKelas) ? '?kelas=' . $filterKelas : '' ?>" class="btn btn-tambah-soft">
                    <i class="bi bi-plus-circle me-1"></i>
                    Tambah Materi
                  </a>
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
                      <input type="search" id="searchInput" class="form-control form-control-sm search-input" />
                    </div>
                    
                    <!-- Filter Kelas -->
                    <div class="d-flex align-items-center">
                      <label for="filterKelas" class="me-2 mb-0 search-label">
                        <small>Kelas:</small>
                      </label>
                      <select name="kelas" id="filterKelas" class="form-select form-select-sm" style="width: 200px;" onchange="document.getElementById('pageInput').value = 1; document.getElementById('filterForm').submit();">
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
                          <a class="dropdown-item sort-option" href="#" data-sort="judul" data-order="asc">
                            <i class="bi bi-sort-alpha-down me-2"></i>Judul A-Z
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item sort-option" href="#" data-sort="judul" data-order="desc">
                            <i class="bi bi-sort-alpha-up me-2"></i>Judul Z-A
                          </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <a class="dropdown-item sort-option" href="#" data-sort="kelas" data-order="asc">
                            <i class="bi bi-building me-2"></i>Kelas
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item sort-option" href="#" data-sort="file" data-order="desc">
                            <i class="bi bi-file-earmark me-2"></i>Ada File
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item sort-option" href="#" data-sort="terbaru" data-order="desc">
                            <i class="bi bi-clock me-2"></i>Terbaru
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
                        <span class="info-label">materi</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>
          
          <!-- Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="materiTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Judul Materi</th>
                  <th>Kelas</th>
                  <th>Gelombang</th>
                  <th>File</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($materi = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <!-- Judul Materi -->
                      <td class="align-middle">
                        <div class="fw-medium"><?= htmlspecialchars($materi['judul']) ?></div>
                        <?php if($materi['deskripsi']): ?>
                          <small class="text-muted"><?= htmlspecialchars(substr($materi['deskripsi'], 0, 100)) ?><?= strlen($materi['deskripsi']) > 100 ? '...' : '' ?></small>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Kelas -->
                      <td class="align-middle">
                        <?php if($materi['nama_kelas']): ?>
                          <span class="badge bg-primary px-2 py-1">
                            <i class="bi bi-building me-1"></i>
                            <?= htmlspecialchars($materi['nama_kelas']) ?>
                          </span>
                        <?php else: ?>
                          <span class="text-muted fst-italic">
                            <i class="bi bi-dash-circle me-1"></i>
                            Belum ditentukan
                          </span>
                        <?php endif; ?>
                      </td>

                      <!-- Gelombang -->
                      <td class="align-middle">
                        <?php if($materi['nama_gelombang']): ?>
                          <span>
                            <?= htmlspecialchars($materi['nama_gelombang']) ?>
                          </span>
                        <?php else: ?>
                          <span class="text-muted fst-italic">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- File -->
                      <td class="text-center align-middle">
                        <?php if($materi['file_materi'] && $materi['file_materi'] != ''): ?>
                          <a href="../../../uploads/materi/<?= htmlspecialchars($materi['file_materi']) ?>" 
                             target="_blank" 
                             class="btn btn-sm btn-success"
                             title="Unduh file materi">
                            <i class="bi bi-download me-1"></i>
                            Unduh
                          </a>
                        <?php else: ?>
                          <span class="text-muted fst-italic">
                            <i class="bi bi-dash-circle me-1"></i>
                            Tidak ada file
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="detail.php?id=<?= $materi['id_materi'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="edit.php?id=<?= $materi['id_materi'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Edit">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $materi['id_materi'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <div class="modal fade" id="modalHapus<?= $materi['id_materi'] ?>" tabindex="-1" aria-labelledby="modalHapusLabel<?= $materi['id_materi'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content border-0 shadow-lg">
                          
                          <!-- Modal Header -->
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <div class="warning-icon">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                              </div>
                              <h5 class="modal-title" id="modalHapusLabel<?= $materi['id_materi'] ?>">
                                Konfirmasi Hapus
                              </h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <!-- Modal Body -->
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus materi:</p>
                            
                            <div class="alert alert-light border">
                              <div class="row">
                                <div class="col-4 text-muted small">Judul:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($materi['judul']) ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Kelas:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($materi['nama_kelas'] ?? '-') ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">File:</div>
                                <div class="col-8 fw-medium"><?= $materi['file_materi'] ? 'Ada file terlampir' : 'Tidak ada file' ?></div>
                              </div>
                            </div>
                            
                            <div class="alert alert-warning">
                              <i class="bi bi-info-circle me-2"></i>
                              <?php if($materi['file_materi']): ?>
                                Data materi dan file terlampir akan dihapus permanen
                              <?php else: ?>
                                Data materi akan dihapus permanen
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
                                        onclick="confirmDelete(<?= $materi['id_materi'] ?>, '<?= htmlspecialchars($materi['judul']) ?>')">
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
                    <td colspan="6" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-journal-bookmark display-4 text-muted mb-3 d-block"></i>
                        <h5>
                          <?php if (!empty($filterKelas)): ?>
                            Belum Ada Materi untuk Kelas Ini
                          <?php else: ?>
                            Belum Ada Materi
                          <?php endif; ?>
                        </h5>
                        <p class="mb-3 text-muted">
                          <?php if (!empty($filterKelas)): ?>
                            Mulai tambahkan materi pembelajaran untuk kelas yang dipilih
                          <?php else: ?>
                            Mulai tambahkan materi pembelajaran untuk kelas yang Anda ampu
                          <?php endif; ?>
                        </p>
                        <div class="btn-group">
                          <?php if (!empty($filterKelas)): ?>
                            <a href="?" class="btn btn-secondary">
                              <i class="bi bi-arrow-left me-2"></i>Lihat Semua Materi
                            </a>
                          <?php endif; ?>
                          <a href="tambah.php<?= !empty($filterKelas) ? '?kelas=' . $filterKelas : '' ?>" class="btn btn-tambah-soft">
                            <i class="bi bi-plus-circle me-2"></i>Tambah Materi
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
    const table = document.getElementById('materiTable');
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
          const judul = (row.cells[1]?.textContent || '').toLowerCase();
          const kelas = (row.cells[2]?.textContent || '').toLowerCase();
          const gelombang = (row.cells[3]?.textContent || '').toLowerCase();
          
          const showRow = !searchTerm || 
                         judul.includes(searchTerm) || 
                         kelas.includes(searchTerm) ||
                         gelombang.includes(searchTerm);
          
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
          case 'judul':
            sortedRows = [...rows].sort((a, b) => {
              const aJudul = (a.cells[1]?.textContent || '').trim().toLowerCase();
              const bJudul = (b.cells[1]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aJudul.localeCompare(bJudul) : bJudul.localeCompare(aJudul);
            });
            break;
            
          case 'kelas':
            sortedRows = [...rows].sort((a, b) => {
              const aKelas = (a.cells[2]?.textContent || '').trim().toLowerCase();
              const bKelas = (b.cells[2]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aKelas.localeCompare(bKelas) : bKelas.localeCompare(aKelas);
            });
            break;
            
          case 'file':
            sortedRows = [...rows].sort((a, b) => {
              const aFile = (a.cells[4]?.textContent || '').includes('Unduh') ? 1 : 0;
              const bFile = (b.cells[4]?.textContent || '').includes('Unduh') ? 1 : 0;
              return order === 'asc' ? aFile - bFile : bFile - aFile;
            });
            break;
            
          case 'terbaru':
            sortedRows = [...originalOrder];
            if (order === 'asc') {
              sortedRows.reverse();
            }
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

    // Initialize sort options
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
    
    if (formData.get('kelas')) params.push('kelas=' + encodeURIComponent(formData.get('kelas')));
    
    url += params.join('&');
    
    const pdfWindow = window.open(url, '_blank');
    
    setTimeout(() => {
      btnCetak.disabled = false;
      btnCetak.innerHTML = '<i class="bi bi-printer me-2"></i>Cetak Laporan';
      
      if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed == 'undefined') {
        alert('Popup diblokir! Silakan izinkan popup untuk mengunduh laporan PDF.');
      }
    }, 2000);
  }

  function updateCetakButtonState() {
    const btnCetak = document.getElementById('btnCetakPDF');
    const tableRows = document.querySelectorAll('#materiTable tbody tr');
    const emptyState = document.querySelector('#materiTable tbody .empty-state');
    const hasData = tableRows.length > 0 && !emptyState;
    
    if (!hasData) {
      btnCetak.disabled = true;
      btnCetak.title = 'Tidak ada materi untuk dicetak';
    } else {
      btnCetak.disabled = false;
      const visibleRows = Array.from(tableRows).filter(row => !row.querySelector('.empty-state'));
      btnCetak.title = `Cetak ${visibleRows.length} data materi`;
    }
  }

  // Fungsi konfirmasi hapus
  function confirmDelete(id, judulMateri) {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalHapus' + id));
    if (modal) {
      modal.hide();
    }
    
    const deleteBtn = document.querySelector(`#modalHapus${id} .btn-danger`);
    if (deleteBtn) {
      deleteBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Memproses...';
      deleteBtn.disabled = true;
    }
    
    setTimeout(() => {
      window.location.href = `hapus.php?id=${id}&confirm=delete`;
    }, 1000);
  }
  </script>
</body>
</html>