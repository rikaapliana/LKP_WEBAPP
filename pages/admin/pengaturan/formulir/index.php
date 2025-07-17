<?php
session_start();
require_once '../../../../includes/auth.php';
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'pengaturan';
$baseURL = '../../';

// Proses update status formulir
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id_gelombang = (int)$_POST['id_gelombang'];
    $status_pendaftaran = $_POST['status_pendaftaran'];
    $kuota_maksimal = (int)$_POST['kuota_maksimal'];
    $tanggal_buka = $_POST['tanggal_buka'] ?: NULL;
    $tanggal_tutup = $_POST['tanggal_tutup'] ?: NULL;
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    try {
        // Cek apakah sudah ada pengaturan untuk gelombang ini
        $checkQuery = "SELECT id_pengaturan FROM pengaturan_pendaftaran WHERE id_gelombang = $id_gelombang";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            // Update existing
            $updateQuery = "UPDATE pengaturan_pendaftaran SET 
                           status_pendaftaran = '$status_pendaftaran',
                           kuota_maksimal = $kuota_maksimal,
                           tanggal_buka = " . ($tanggal_buka ? "'$tanggal_buka'" : "NULL") . ",
                           tanggal_tutup = " . ($tanggal_tutup ? "'$tanggal_tutup'" : "NULL") . ",
                           keterangan = '$keterangan',
                           updated_at = NOW()
                           WHERE id_gelombang = $id_gelombang";
        } else {
            // Insert new
            $updateQuery = "INSERT INTO pengaturan_pendaftaran 
                           (id_gelombang, status_pendaftaran, kuota_maksimal, tanggal_buka, tanggal_tutup, keterangan, created_at, updated_at) 
                           VALUES ($id_gelombang, '$status_pendaftaran', $kuota_maksimal, " . 
                           ($tanggal_buka ? "'$tanggal_buka'" : "NULL") . ", " . 
                           ($tanggal_tutup ? "'$tanggal_tutup'" : "NULL") . ", " . 
                           "'$keterangan', NOW(), NOW())";
        }
        
        if (mysqli_query($conn, $updateQuery)) {
            $_SESSION['success'] = "Pengaturan formulir pendaftaran berhasil diperbarui!";
        } else {
            $_SESSION['error'] = "Gagal memperbarui pengaturan: " . mysqli_error($conn);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: index.php');
    exit();
}

// Pagination
$recordsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$whereClause = '';
if ($statusFilter) {
    $whereClause = "WHERE p.status_pendaftaran = '$statusFilter'";
}

// Count total records
$countQuery = "SELECT COUNT(*) as total FROM gelombang g 
               LEFT JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang 
               $whereClause";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Ambil data gelombang dengan info formulir dan counting yang diperbaiki
$query = "SELECT g.*, 
                 p.status_pendaftaran,
                 p.kuota_maksimal,
                 p.tanggal_buka,
                 p.tanggal_tutup,
                 p.keterangan,
                 CASE 
                   WHEN g.status = 'aktif' THEN (
                     SELECT COUNT(*) FROM kelas k 
                     INNER JOIN siswa s ON k.id_kelas = s.id_kelas 
                     WHERE k.id_gelombang = g.id_gelombang AND s.status_aktif = 'aktif'
                   )
                   WHEN g.status = 'dibuka' THEN (
                     SELECT COUNT(*) FROM pendaftar pd 
                     WHERE pd.id_gelombang = g.id_gelombang 
                     AND pd.status_pendaftaran != 'Belum di Verifikasi'
                   )
                   ELSE 0
                 END as jumlah_pendaftar_siswa
          FROM gelombang g 
          LEFT JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang
          $whereClause
          ORDER BY g.tahun DESC, g.gelombang_ke DESC
          LIMIT $recordsPerPage OFFSET $offset";
$result = mysqli_query($conn, $query);

// Statistik formulir
$statsQuery = "SELECT 
    COUNT(DISTINCT g.id_gelombang) as total_gelombang,
    COUNT(CASE WHEN p.status_pendaftaran = 'dibuka' THEN 1 END) as formulir_aktif,
    COUNT(CASE WHEN p.status_pendaftaran = 'ditutup' THEN 1 END) as formulir_tutup,
    COALESCE(SUM(p.kuota_maksimal), 0) as total_kuota
FROM gelombang g 
LEFT JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang";
$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

function buildUrlWithFilters($page) {
    $params = $_GET;
    $params['page'] = $page;
    unset($params['action']);
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Formulir Pendaftaran - LKP Pradata Komputer</title>
  <link rel="icon" type="image/png" href="../../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../../assets/css/styles.css" />
  
  <!-- Custom CSS -->
  <style>
    .controls-container {
      flex-wrap: wrap;
      gap: 10px;
    }
    .search-container {
      min-width: 200px;
      flex: 1;
    }
    .search-input {
      min-width: 150px;
    }
    .search-label {
      white-space: nowrap;
      font-weight: 500;
    }
    @media (max-width: 768px) {
      .table-responsive {
        font-size: 0.875rem;
      }
      .custom-table th,
      .custom-table td {
        padding: 0.5rem 0.25rem;
      }
      .btn-group-xs .btn {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
      }
      .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.4rem;
      }
      .controls-container {
        flex-direction: column;
        gap: 15px;
      }
      .search-container {
        min-width: 100%;
      }
      .info-badge {
        font-size: 0.875rem;
      }
      .stats-card-mobile .stats-card-content {
        flex-direction: column;
        text-align: center;
      }
      .stats-icon-mobile {
        margin-bottom: 10px;
      }
    }
    .search-highlight {
      background-color: #fff3cd;
      padding: 0.1rem 0.2rem;
      border-radius: 0.25rem;
    }
    .empty-state {
      color: #6c757d;
    }
    .empty-state .display-4 {
      font-size: 3rem;
      opacity: 0.5;
    }
    .spinner-border-sm {
      width: 1rem;
      height: 1rem;
    }
    .btn:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }
    .tooltip {
      font-size: 0.875rem;
    }
    @media (max-width: 576px) {
      .modal-dialog {
        margin: 0.5rem;
      }
      .modal-content {
        border-radius: 0.5rem;
      }
      .modal-header {
        padding: 1rem;
      }
      .modal-body {
        padding: 1rem;
      }
      .modal-footer {
        padding: 1rem;
        flex-direction: column;
      }
      .modal-footer .btn {
        width: 100%;
        margin-bottom: 0.5rem;
      }
      .modal-footer .btn:last-child {
        margin-bottom: 0;
      }
    }
    @media print {
      .no-print {
        display: none !important;
      }
      .table {
        font-size: 0.8rem;
      }
      .page-break {
        page-break-after: always;
      }
    }
    .form-control:focus,
    .form-select:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    .table-responsive::-webkit-scrollbar {
      height: 8px;
    }
    .table-responsive::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    .table-responsive::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }
    .table-responsive::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }
  </style>
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
                <h2 class="page-title mb-1">KELOLA FORMULIR PENDAFTARAN</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Formulir Pendaftaran</li>
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

        </div>

        <!-- Main Content Card -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-clipboard-data me-2"></i>Pengaturan Formulir Pendaftaran
                </h5>
              </div>
              <div class="col-md-4 text-md-end">
                <a href="../gelombang/tambah.php" class="btn btn-primary-formal btn-sm">
                  <i class="bi bi-plus-circle"></i>
                  Tambah Data
                </a>
              </div>
            </div>
          </div>

          <!-- Search and Filter Controls -->
          <div class="p-3 border-bottom">
            <div class="row align-items-center">  
              <div class="col-md-8">
                <div class="d-flex flex-wrap align-items-center gap-2 controls-container">
                  <!-- Search Box -->
                  <div class="d-flex align-items-center search-container">
                    <label for="searchInput" class="me-2 mb-0 search-label">
                      <small>Search:</small>
                    </label>
                    <input type="search" id="searchInput" class="form-control form-control-sm search-input" />
                  </div>
                  
                  <!-- Status Filter -->
                  <div class="d-flex align-items-center">
                    <label for="statusFilter" class="me-2 mb-0 search-label">
                      <small>Status:</small>
                    </label>
                    <select id="statusFilter" class="form-select form-select-sm" onchange="filterByStatus()">
                      <option value="">Semua Status</option>
                      <option value="dibuka" <?= $statusFilter === 'dibuka' ? 'selected' : '' ?>>Dibuka</option>
                      <option value="ditutup" <?= $statusFilter === 'ditutup' ? 'selected' : '' ?>>Ditutup</option>
                    </select>
                  </div>
                </div>
              </div>
              
              <div class="col-md-4">
                <!-- Result Info -->
                <div class="result-info d-flex align-items-center justify-content-md-end" 
                     data-original="<div class='info-badge'><span class='info-count'><?= (($currentPage - 1) * $recordsPerPage) + 1 ?>-<?= min($currentPage * $recordsPerPage, $totalRecords) ?></span><span class='info-separator'>dari</span><span class='info-total'><?= number_format($totalRecords) ?></span><span class='info-label'>gelombang</span></div>">
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
          
          <!-- Table -->
          <div class="table-responsive">
            <table class="custom-table mb-0" id="formulirTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Gelombang</th>
                  <th>Status Formulir</th>
                  <th>Kuota</th>
                  <th>Pendaftar/Siswa</th>
                  <th>Periode</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($row = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      <td class="align-middle">
                        <div class="fw-semibold"><?= htmlspecialchars($row['nama_gelombang']) ?></div>
                      </td>
                      <td class="align-middle">
                        <?php if ($row['status_pendaftaran'] === 'dibuka'): ?>
                          <span class="badge bg-success px-2 py-1">
                            <i class="bi bi-door-open me-1"></i>Dibuka
                          </span>
                        <?php elseif ($row['status_pendaftaran'] === 'ditutup'): ?>
                          <span class="badge bg-secondary px-2 py-1">
                            <i class="bi bi-door-closed me-1"></i>Ditutup
                          </span>
                        <?php else: ?>
                          <span class="badge bg-warning px-2 py-1">
                            <i class="bi bi-gear me-1"></i>Belum Diatur
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class=" align-middle">
                        <?php if ($row['kuota_maksimal']): ?>
                          <span class="badge bg-info"><?= number_format($row['kuota_maksimal']) ?> orang</span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center align-middle">
                        <?php if ($row['jumlah_pendaftar_siswa'] > 0): ?>
                          <span class="badge bg-primary"><?= $row['jumlah_pendaftar_siswa'] ?> <?= ($row['status'] === 'aktif') ? 'siswa' : 'pendaftar' ?></span>
                        <?php else: ?>
                          <span class="text-muted">Belum ada</span>
                        <?php endif; ?>
                      </td>
                      <td class="align-middle">
                        <?php if ($row['tanggal_buka'] || $row['tanggal_tutup']): ?>
                          <small class="text-muted">
                            <?php if ($row['tanggal_buka']): ?>
                              <i class="bi bi-calendar-event me-1"></i><?= date('d/m/Y', strtotime($row['tanggal_buka'])) ?>
                            <?php endif; ?>
                            <?php if ($row['tanggal_buka'] && $row['tanggal_tutup']): ?>
                              <br>
                            <?php endif; ?>
                            <?php if ($row['tanggal_tutup']): ?>
                              <i class="bi bi-calendar-x me-1"></i><?= date('d/m/Y', strtotime($row['tanggal_tutup'])) ?>
                            <?php endif; ?>
                          </small>
                        <?php else: ?>
                          <span class="text-muted">Tanpa batas</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="detail.php?id=<?= $row['id_gelombang'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail & Pengaturan">
                            <i class="bi bi-gear"></i>
                          </a>
                          <?php if ($row['status_pendaftaran'] === 'dibuka'): ?>
                            <a href="../../../../pendaftaran.php" 
                               class="btn btn-action btn-success btn-sm" 
                               target="_blank"
                               data-bs-toggle="tooltip" 
                               title="Lihat Formulir Publik">
                              <i class="bi bi-eye"></i>
                            </a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Quick Edit -->
                    <div class="modal fade" id="modalQuickEdit<?= $row['id_gelombang'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                          <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title">
                              <i class="bi bi-lightning me-2"></i>
                              Edit Cepat Formulir
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                          </div>
                          <form method="POST">
                            <div class="modal-body">
                              <input type="hidden" name="action" value="update_status">
                              <input type="hidden" name="id_gelombang" value="<?= $row['id_gelombang'] ?>">
                              <div class="mb-3">
                                <label class="form-label">Gelombang</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($row['nama_gelombang']) ?>" readonly>
                              </div>
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="mb-3">
                                    <label class="form-label">Status Formulir</label>
                                    <select name="status_pendaftaran" class="form-select" required>
                                      <option value="dibuka" <?= $row['status_pendaftaran'] === 'dibuka' ? 'selected' : '' ?>>Dibuka</option>
                                      <option value="ditutup" <?= $row['status_pendaftaran'] === 'ditutup' ? 'selected' : '' ?>>Ditutup</option>
                                    </select>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="mb-3">
                                    <label class="form-label">Kuota Maksimal</label>
                                    <input type="number" name="kuota_maksimal" class="form-control" 
                                           value="<?= $row['kuota_maksimal'] ?: 50 ?>" min="1" max="1000">
                                  </div>
                                </div>
                              </div>
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="mb-3">
                                    <label class="form-label">Tanggal Buka</label>
                                    <input type="date" name="tanggal_buka" class="form-control" 
                                           value="<?= $row['tanggal_buka'] ? date('Y-m-d', strtotime($row['tanggal_buka'])) : '' ?>">
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="mb-3">
                                    <label class="form-label">Tanggal Tutup</label>
                                    <input type="date" name="tanggal_tutup" class="form-control" 
                                           value="<?= $row['tanggal_tutup'] ? date('Y-m-d', strtotime($row['tanggal_tutup'])) : '' ?>">
                                  </div>
                                </div>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Keterangan</label>
                                <textarea name="keterangan" class="form-control" rows="2" 
                                          placeholder="Keterangan tambahan..."><?= htmlspecialchars($row['keterangan'] ?? '') ?></textarea>
                              </div>
                            </div>
                            <div class="modal-footer border-0">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                              <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-clipboard-data display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Gelombang</h5>
                        <p class="mb-3 text-muted">Buat gelombang terlebih dahulu untuk mengatur formulir pendaftaran</p>
                        <a href="../gelombang/tambah.php" class="btn btn-primary">
                          <i class="bi bi-plus-circle me-2"></i>Buat Gelombang
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
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        initializeSearch();
        initializeAlerts();
        initializeResponsiveTable();
    });

    function filterByStatus() {
        const statusFilter = document.getElementById('statusFilter').value;
        const currentUrl = new URL(window.location.href);
        if (statusFilter) {
            currentUrl.searchParams.set('status', statusFilter);
        } else {
            currentUrl.searchParams.delete('status');
        }
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    }

    function initializeSearch() {
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('formulirTable');
        const tbody = table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        
        if (!searchInput || !table) return;
        
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
        
        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleRows = 0;
            
            rows.forEach(row => {
                if (row.querySelector('.empty-state')) {
                    return;
                }
                
                const cells = row.querySelectorAll('td');
                let rowText = '';
                
                for (let i = 0; i < cells.length - 1; i++) {
                    rowText += cells[i].textContent.toLowerCase() + ' ';
                }
                
                if (searchTerm === '' || rowText.includes(searchTerm)) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateResultInfo(visibleRows);
            showEmptyState(visibleRows === 0 && searchTerm !== '');
        }
        
        searchInput.addEventListener('input', debounce(performSearch, 300));
        
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchInput.value = '';
                performSearch();
            }
        });
    }

    function updateResultInfo(visibleRows) {
        const resultInfo = document.querySelector('.result-info');
        if (!resultInfo) return;
        
        const searchTerm = document.getElementById('searchInput').value;
        
        if (searchTerm.trim() !== '') {
            resultInfo.innerHTML = `
                <div class="info-badge">
                    <span class="info-count">${visibleRows}</span>
                    <span class="info-label">hasil ditemukan</span>
                </div>
            `;
        } else {
            const originalInfo = resultInfo.getAttribute('data-original');
            if (originalInfo) {
                resultInfo.innerHTML = originalInfo;
            }
        }
    }

    function showEmptyState(show) {
        const tbody = document.querySelector('#formulirTable tbody');
        let emptySearchRow = tbody.querySelector('.empty-search-state');
        
        if (show && !emptySearchRow) {
            emptySearchRow = document.createElement('tr');
            emptySearchRow.className = 'empty-search-state';
            emptySearchRow.innerHTML = `
                <td colspan="7" class="text-center">
                    <div class="empty-state py-4">
                        <i class="bi bi-search display-4 text-muted mb-3 d-block"></i>
                        <h6>Tidak Ada Hasil</h6>
                        <p class="mb-0 text-muted">Tidak ditemukan gelombang yang sesuai dengan pencarian</p>
                    </div>
                </td>
            `;
            tbody.appendChild(emptySearchRow);
        } else if (!show && emptySearchRow) {
            emptySearchRow.remove();
        }
    }

    function initializeAlerts() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('alert-success')) {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
        });
    }

    function initializeResponsiveTable() {
        const table = document.getElementById('formulirTable');
        if (!table) return;
        
        function handleResize() {
            const isMobile = window.innerWidth < 768;
            
            if (isMobile) {
                table.classList.add('table-mobile');
                const btnGroups = table.querySelectorAll('.btn-group');
                btnGroups.forEach(group => {
                    group.classList.remove('btn-group-sm');
                    group.classList.add('btn-group-xs');
                });
            } else {
                table.classList.remove('table-mobile');
                const btnGroups = table.querySelectorAll('.btn-group');
                btnGroups.forEach(group => {
                    group.classList.add('btn-group-sm');
                    group.classList.remove('btn-group-xs');
                });
            }
        }
        
        handleResize();
        window.addEventListener('resize', debounce(handleResize, 100));
    }

    document.addEventListener('submit', function(e) {
        if (e.target.closest('.modal')) {
            const modal = e.target.closest('.modal');
            const submitBtn = modal.querySelector('button[type="submit"]');
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
                
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Simpan Perubahan';
                }, 3000);
            }
        }
    });

    document.addEventListener('hidden.bs.modal', function(e) {
        const modal = e.target;
        const submitBtn = modal.querySelector('button[type="submit"]');
        
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Simpan Perubahan';
        }
    });

    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href*="confirm=delete"]');
        if (link) {
            e.preventDefault();
            
            if (confirm('Apakah Anda yakin ingin menghapus data ini?')) {
                window.location.href = link.href;
            }
        }
    });

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
  </script>
</body>
</html>