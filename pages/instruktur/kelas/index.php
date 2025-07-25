<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth(); // Hanya instruktur yang bisa akses

include '../../../includes/db.php';
$activePage = 'kelas-diampu'; 
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

// Count total records untuk pagination - hanya kelas yang diampu instruktur ini
$countQuery = "SELECT COUNT(*) as total FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               WHERE k.id_instruktur = ?";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("i", $id_instruktur);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Query kelas yang diampu instruktur ini dengan pagination - DIPERBAIKI
$query = "SELECT k.*, g.nama_gelombang, g.status as status_gelombang,
          COUNT(DISTINCT s.id_siswa) as jumlah_siswa,
          COUNT(DISTINCT CASE WHEN s.status_aktif = 'aktif' THEN s.id_siswa END) as siswa_aktif,
          COUNT(DISTINCT m.id_materi) as jumlah_materi
          FROM kelas k 
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
          LEFT JOIN materi m ON k.id_kelas = m.id_kelas
          WHERE k.id_instruktur = ?
          GROUP BY k.id_kelas, k.nama_kelas, k.kapasitas, k.id_gelombang, g.nama_gelombang, g.status
          ORDER BY k.id_kelas DESC
          LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $id_instruktur, $recordsPerPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Statistik untuk instruktur
$totalKelas = $totalRecords;

// Hitung total siswa aktif dari semua kelas yang diampu
$totalSiswaQuery = "SELECT COUNT(DISTINCT s.id_siswa) as total
                   FROM siswa s 
                   JOIN kelas k ON s.id_kelas = k.id_kelas
                   WHERE k.id_instruktur = ? AND s.status_aktif = 'aktif'";
$totalSiswaStmt = $conn->prepare($totalSiswaQuery);
$totalSiswaStmt->bind_param("i", $id_instruktur);
$totalSiswaStmt->execute();
$totalSiswa = $totalSiswaStmt->get_result()->fetch_assoc()['total'];

// Hitung total materi yang dibuat
$totalMateriQuery = "SELECT COUNT(*) as total
                    FROM materi m 
                    JOIN kelas k ON m.id_kelas = k.id_kelas
                    WHERE k.id_instruktur = ?";
$totalMateriStmt = $conn->prepare($totalMateriQuery);
$totalMateriStmt->bind_param("i", $id_instruktur);
$totalMateriStmt->execute();
$totalMateri = $totalMateriStmt->get_result()->fetch_assoc()['total'];

// Function untuk build URL dengan filter
function buildUrlWithFilters($page) {
    $params = [];
    if ($page > 1) $params['page'] = $page;
    return 'index.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Kelas - Instruktur</title>
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
            <!-- Left: Hamburger + Page Info -->
            <div class="d-flex align-items-center flex-grow-1">
              <!-- Sidebar Toggle Button -->
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <!-- Page Title & Breadcrumb -->
              <div class="page-info">
                <h2 class="page-title mb-1">KELAS DIAMPU</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Kelas Diampu</li>
                  </ol>
                </nav>
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
                  <i class="bi bi-building me-2"></i>Daftar Kelas Yang Diampu
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
                        <a class="dropdown-item sort-option" href="#" data-sort="siswa" data-order="desc">
                          <i class="bi bi-person-check me-2"></i>Jumlah Siswa
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="materi" data-order="desc">
                          <i class="bi bi-journal-text me-2"></i>Jumlah Materi
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
                      <span class="info-label">kelas</span>
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
                  <th>Jumlah Siswa</th>
                  <th>Materi</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($kelas = mysqli_fetch_assoc($result)): 
                  ?>
                    <?php
                    $jumlahSiswa = (int)$kelas['siswa_aktif'];
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
                      
                      <!-- Gelombang -->
                      <td class="align-middle">
                        <div class="fw-medium"><?= htmlspecialchars($kelas['nama_gelombang'] ?? 'Belum Ditentukan') ?></div>
                        <?php if($kelas['status_gelombang']): ?>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Kapasitas -->
                      <td class="align-middle text-nowrap">
                        <span class="fw-medium"><?= $kapasitas ?></span>
                        <?php if($kapasitas > 0): ?>
                        <small class="text-muted">Siswa</small>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Jumlah Siswa -->
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
                      
                      <!-- Jumlah Materi -->
                      <td class="text-center align-middle">
                        <span class="badge bg-info px-2 py-1">
                          <i class="bi bi-journal-text me-1"></i>
                          <?= $kelas['jumlah_materi'] ?>
                        </span>
                      </td>
                      
                      <!-- Status -->
                      <td class="text-center align-middle">
                        <?php if($kelas['status_gelombang'] == 'aktif'): ?>
                          <span class="badge bg-success px-2 py-1">
                            Aktif
                          </span>
                        <?php elseif($kelas['status_gelombang'] == 'selesai'): ?>
                          <span class="badge bg-secondary px-2 py-1">
                            Selesai
                          </span>
                        <?php else: ?>
                          <span class="badge bg-warning px-2 py-1">
                            Menunggu
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Aksi -->
                      <td class="align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="../siswa/index.php?kelas=<?= $kelas['id_kelas'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Lihat Siswa">
                            <i class="bi bi-people"></i>
                          </a>
                          <a href="../materi/index.php?kelas=<?= $kelas['id_kelas'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Kelola Materi">
                            <i class="bi bi-journal-text"></i>
                          </a>
                          <a href="../jadwal/index.php?kelas=<?= $kelas['id_kelas'] ?>" 
                             class="btn btn-action btn-primary btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Lihat Jadwal">
                            <i class="bi bi-calendar-event"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-building display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Kelas Yang Diampu</h5>
                        <p class="mb-3 text-muted">Saat ini Anda belum diamanahi kelas untuk diajar. Hubungi admin untuk penugasan kelas.</p>
                        <a href="../dashboard.php" class="btn btn-primary">
                          <i class="bi bi-house me-2"></i>Kembali ke Dashboard
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

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('kelasTable');
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
          const namaKelas = (row.cells[1]?.textContent || '').toLowerCase();
          const gelombang = (row.cells[2]?.textContent || '').toLowerCase();
          
          const showRow = !searchTerm || 
                         namaKelas.includes(searchTerm) || 
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
          case 'nama':
            sortedRows = [...rows].sort((a, b) => {
              const aName = (a.cells[1]?.textContent || '').trim().toLowerCase();
              const bName = (b.cells[1]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aName.localeCompare(bName) : bName.localeCompare(aName);
            });
            break;
            
          case 'siswa':
            sortedRows = [...rows].sort((a, b) => {
              const aSiswa = parseInt((a.cells[4]?.querySelector('.count-angka')?.textContent || '').trim()) || 0;
              const bSiswa = parseInt((b.cells[4]?.querySelector('.count-angka')?.textContent || '').trim()) || 0;
              return order === 'asc' ? aSiswa - bSiswa : bSiswa - aSiswa;
            });
            break;
            
          case 'materi':
            sortedRows = [...rows].sort((a, b) => {
              const aMateri = parseInt((a.cells[5]?.textContent || '').trim()) || 0;
              const bMateri = parseInt((b.cells[5]?.textContent || '').trim()) || 0;
              return order === 'asc' ? aMateri - bMateri : bMateri - aMateri;
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