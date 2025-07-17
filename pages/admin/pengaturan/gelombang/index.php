<?php
session_start();
require_once '../../../../includes/auth.php';
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'pengaturan';
$baseURL = '../../';

// Proses hapus gelombang
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_gelombang = (int)$_GET['id'];
    
    try {
        // Cek apakah gelombang sedang digunakan
        $cekKelas = mysqli_query($conn, "SELECT COUNT(*) as total FROM kelas WHERE id_gelombang = $id_gelombang");
        $jumlahKelas = mysqli_fetch_assoc($cekKelas)['total'];
        
        $cekPengaturan = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengaturan_pendaftaran WHERE id_gelombang = $id_gelombang");
        $jumlahPengaturan = mysqli_fetch_assoc($cekPengaturan)['total'];
        
        if ($jumlahKelas > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus gelombang karena masih digunakan oleh $jumlahKelas kelas.";
        } elseif ($jumlahPengaturan > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus gelombang karena sudah memiliki pengaturan pendaftaran.";
        } else {
            $deleteQuery = "DELETE FROM gelombang WHERE id_gelombang = $id_gelombang";
            if (mysqli_query($conn, $deleteQuery)) {
                $_SESSION['success'] = "Gelombang berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus gelombang: " . mysqli_error($conn);
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: index.php');
    exit();
}

// Pagination settings
$recordsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Count total records
$countQuery = "SELECT COUNT(*) as total FROM gelombang";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Ambil data gelombang dengan info tambahan
$query = "SELECT g.*, 
                 COUNT(k.id_kelas) as jumlah_kelas,
                 COUNT(CASE WHEN k.id_kelas IS NOT NULL THEN s.id_siswa END) as jumlah_siswa,
                 p.status_pendaftaran
          FROM gelombang g 
          LEFT JOIN kelas k ON g.id_gelombang = k.id_gelombang
          LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
          LEFT JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang
          GROUP BY g.id_gelombang
          ORDER BY g.tahun DESC, g.gelombang_ke DESC
          LIMIT $recordsPerPage OFFSET $offset";
$result = mysqli_query($conn, $query);

// Statistik gelombang
$statsQuery = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'aktif' THEN 1 END) as aktif,
    COUNT(CASE WHEN status = 'dibuka' THEN 1 END) as dibuka,
    COUNT(CASE WHEN status = 'selesai' THEN 1 END) as selesai
FROM gelombang";
$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

function buildUrlWithFilters($page) {
    $params = $_GET;
    $params['page'] = $page;
    unset($params['action'], $params['id']);
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Gelombang - LKP Pradata Komputer</title>
  <link rel="icon" type="image/png" href="../../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../../includes/sidebar/admin.php'; ?>

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
                <h2 class="page-title mb-1">KELOLA GELOMBANG</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="../index.php">Pengaturan</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Kelola Gelombang</li>
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
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

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
                    <h6 class="mb-1 stats-title">Total Gelombang</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['total']) ?></h3>
                    <small class="text-muted stats-subtitle">Keseluruhan</small>
                  </div>
                  <div class="stats-icon bg-primary-light stats-icon-mobile">
                    <i class="bi bi-layers text-primary"></i>
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
                    <h6 class="mb-1 stats-title">Gelombang Aktif</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['aktif']) ?></h3>
                    <small class="text-muted stats-subtitle">Sedang berjalan</small>
                  </div>
                  <div class="stats-icon bg-success-light stats-icon-mobile">
                    <i class="bi bi-play-circle text-success"></i>
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
                    <h6 class="mb-1 stats-title">Pendaftaran Terbuka</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['dibuka']) ?></h3>
                    <small class="text-muted stats-subtitle">Formulir aktif</small>
                  </div>
                  <div class="stats-icon bg-warning-light stats-icon-mobile">
                    <i class="bi bi-door-open text-warning"></i>
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
                    <h6 class="mb-1 stats-title">Gelombang Selesai</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['selesai']) ?></h3>
                    <small class="text-muted stats-subtitle">Sudah selesai</small>
                  </div>
                  <div class="stats-icon bg-secondary-light stats-icon-mobile">
                    <i class="bi bi-check-circle text-secondary"></i>
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
                  <i class="bi bi-layers me-2"></i>Daftar Gelombang Pelatihan
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <a href="tambah.php" class="btn btn-primary-formal">
                  <i class="bi bi-plus-circle"></i>
                  Tambah Gelombang
                </a>
              </div>
            </div>
          </div>

          <!-- Search Controls (Simplified) -->
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
                  
                  <!-- Result Info -->
                  <div class="ms-auto result-info d-flex align-items-center">
                    <label class="me-2 mb-0 search-label">
                      <small>Show:</small>
                    </label>
                    <div class="info-badge">
                      <span class="info-count"><?= (($currentPage - 1) * $recordsPerPage) + 1 ?>-<?= min($currentPage * $recordsPerPage, $totalRecords) ?></span>
                      <span class="info-separator">dari</span>
                      <span class="info-total"><?= number_format($totalRecords) ?></span>
                      <span class="info-label">gelombang</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Table -->
          <div class="table-responsive">
            <table class="custom-table mb-0" id="gelombangTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Nama Gelombang</th>
                  <th>Tahun</th>
                  <th>Gelombang Ke</th>
                  <th>Status</th>
                  <th>Kelas</th>
                  <th>Siswa</th>
                  <th>Formulir</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($gelombang = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <!-- Nama Gelombang -->
                      <td class="align-middle">
                        <div class="fw-semibold"><?= htmlspecialchars($gelombang['nama_gelombang']) ?></div>
                      </td>
                      
                      <!-- Tahun -->
                      <td class="align-middle">
                        <span class="badge bg-light text-dark"><?= $gelombang['tahun'] ?></span>
                      </td>
                      
                      <!-- Gelombang Ke -->
                      <td class="text-center align-middle">
                        <span class="badge bg-info"><?= $gelombang['gelombang_ke'] ?></span>
                      </td>
                      
                      <!-- Status -->
                      <td class="text-center align-middle">
                        <?php 
                        $statusClass = 'secondary';
                        $statusText = 'Draft';
                        $statusIcon = 'pause-circle';
                        
                        switch($gelombang['status']) {
                          case 'aktif':
                            $statusClass = 'success';
                            $statusText = 'Aktif';
                            $statusIcon = 'play-circle';
                            break;
                          case 'dibuka':
                            $statusClass = 'primary';
                            $statusText = 'Dibuka';
                            $statusIcon = 'door-open';
                            break;
                          case 'selesai':
                            $statusClass = 'secondary';
                            $statusText = 'Selesai';
                            $statusIcon = 'check-circle';
                            break;
                        }
                        ?>
                        <span class="badge bg-<?= $statusClass ?> px-2 py-1">
                          <i class="bi bi-<?= $statusIcon ?> me-1"></i><?= $statusText ?>
                        </span>
                      </td>
                      
                      <!-- Kelas -->
                      <td class="text-center align-middle">
                        <?php if ($gelombang['jumlah_kelas'] > 0): ?>
                          <span class="badge bg-primary"><?= $gelombang['jumlah_kelas'] ?> kelas</span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Siswa -->
                      <td class="text-center align-middle">
                        <?php if ($gelombang['jumlah_siswa'] > 0): ?>
                          <span class="badge bg-success"><?= $gelombang['jumlah_siswa'] ?> siswa</span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Status Formulir -->
                      <td class="text-center align-middle">
                        <?php if ($gelombang['status_pendaftaran'] === 'dibuka'): ?>
                          <span class="badge bg-success">
                            <i class="bi bi-door-open me-1"></i>Terbuka
                          </span>
                        <?php elseif ($gelombang['status_pendaftaran'] === 'ditutup'): ?>
                          <span class="badge bg-secondary">
                            <i class="bi bi-door-closed me-1"></i>Ditutup
                          </span>
                        <?php else: ?>
                          <span class="text-muted">Belum diatur</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="edit.php?id=<?= $gelombang['id_gelombang'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Edit">
                            <i class="bi bi-pencil"></i>
                          </a>
                          
                          <a href="../formulir/detail.php?id=<?= $gelombang['id_gelombang'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Pengaturan Formulir">
                            <i class="bi bi-gear"></i>
                          </a>
                          
                          <?php if ($gelombang['jumlah_kelas'] == 0 && $gelombang['status_pendaftaran'] != 'dibuka'): ?>
                            <button type="button" 
                                    class="btn btn-action btn-delete btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalHapus<?= $gelombang['id_gelombang'] ?>"
                                    title="Hapus">
                              <i class="bi bi-trash"></i>
                            </button>
                          <?php else: ?>
                            <button type="button" 
                                    class="btn btn-secondary btn-sm" 
                                    disabled
                                    title="Tidak dapat dihapus (sedang digunakan)">
                              <i class="bi bi-lock"></i>
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <?php if ($gelombang['jumlah_kelas'] == 0 && $gelombang['status_pendaftaran'] != 'dibuka'): ?>
                    <div class="modal fade" id="modalHapus<?= $gelombang['id_gelombang'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content border-0 shadow-lg">
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <h5 class="modal-title">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Konfirmasi Hapus
                              </h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus gelombang:</p>
                            <div class="text-center p-3 bg-light rounded">
                              <div class="fw-bold"><?= htmlspecialchars($gelombang['nama_gelombang']) ?></div>
                              <div class="text-muted">Tahun <?= $gelombang['tahun'] ?></div>
                            </div>
                          </div>
                          
                          <div class="modal-footer border-0">
                            <div class="row g-2 w-100">
                              <div class="col-6">
                                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">
                                  Batal
                                </button>
                              </div>
                              <div class="col-6">
                                <a href="?action=hapus&id=<?= $gelombang['id_gelombang'] ?>" 
                                   class="btn btn-danger w-100">
                                  Hapus
                                </a>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="9" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-layers display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Gelombang</h5>
                        <p class="mb-3 text-muted">Mulai dengan membuat gelombang pelatihan pertama</p>
                        <a href="tambah.php" class="btn btn-primary">
                          <i class="bi bi-plus-circle me-2"></i>Buat Gelombang Pertama
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
                      <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildUrlWithFilters($i) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  
                  <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                      <li class="page-item disabled"><span class="page-link">...</span></li>
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

  <!-- Scripts -->
  <script src="../../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../../assets/js/scripts.js"></script>
  
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('gelombangTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('.empty-state'));
    
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        rows.forEach(row => {
          const namaGelombang = (row.cells[1]?.textContent || '').toLowerCase();
          const tahun = (row.cells[2]?.textContent || '').toLowerCase();
          
          const showRow = !searchTerm || 
                         namaGelombang.includes(searchTerm) || 
                         tahun.includes(searchTerm);
          
          row.style.display = showRow ? '' : 'none';
        });
        
        // Update row numbers
        let counter = 1;
        rows.forEach(row => {
          if (row.style.display !== 'none') {
            row.cells[0].textContent = counter++;
          }
        });
      });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
  </script>
</body>
</html>