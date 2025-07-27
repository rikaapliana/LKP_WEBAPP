<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'gelombang';
$baseURL = '../';

// Function untuk terbilang angka
function terbilang($angka) {
    $bilangan = array(
        1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima',
        6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan', 10 => 'sepuluh',
        11 => 'sebelas', 12 => 'dua belas'
    );
    return isset($bilangan[$angka]) ? $bilangan[$angka] : $angka;
}

// Proses hapus gelombang
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_gelombang = (int)$_GET['id'];
    
    try {
        // Cek apakah gelombang sedang digunakan
        $cekKelas = mysqli_query($conn, "SELECT COUNT(*) as total FROM kelas WHERE id_gelombang = $id_gelombang");
        $jumlahKelas = mysqli_fetch_assoc($cekKelas)['total'];
        
        $cekPengaturan = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengaturan_pendaftaran WHERE id_gelombang = $id_gelombang");
        $jumlahPengaturan = mysqli_fetch_assoc($cekPengaturan)['total'];
        
        $cekPendaftar = mysqli_query($conn, "SELECT COUNT(*) as total FROM pendaftar WHERE id_gelombang = $id_gelombang");
        $jumlahPendaftar = mysqli_fetch_assoc($cekPendaftar)['total'];
        
        if ($jumlahKelas > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus gelombang karena masih digunakan oleh $jumlahKelas kelas.";
        } elseif ($jumlahPendaftar > 0) {
            $_SESSION['error'] = "Tidak dapat menghapus gelombang karena sudah memiliki $jumlahPendaftar pendaftar.";
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

// Query utama - DIPERBAIKI: pisahkan perhitungan kelas dan siswa
$query = "SELECT g.*, 
                 COALESCE(kelas_count.jumlah_kelas, 0) as jumlah_kelas,
                 COALESCE(siswa_count.jumlah_siswa, 0) as jumlah_siswa,
                 p.status_pendaftaran,
                 p.kuota_maksimal,
                 COALESCE(pendaftar_count.jumlah_pendaftar, 0) as jumlah_pendaftar
          FROM gelombang g 
          LEFT JOIN (
              SELECT id_gelombang, COUNT(*) as jumlah_kelas 
              FROM kelas 
              GROUP BY id_gelombang
          ) kelas_count ON g.id_gelombang = kelas_count.id_gelombang
          LEFT JOIN (
              SELECT k.id_gelombang, COUNT(s.id_siswa) as jumlah_siswa
              FROM kelas k
              LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
              GROUP BY k.id_gelombang
          ) siswa_count ON g.id_gelombang = siswa_count.id_gelombang
          LEFT JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang
          LEFT JOIN (
              SELECT id_gelombang, COUNT(*) as jumlah_pendaftar
              FROM pendaftar
              GROUP BY id_gelombang
          ) pendaftar_count ON g.id_gelombang = pendaftar_count.id_gelombang
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

// Untuk dropdown filter
$tahunQuery = "SELECT DISTINCT tahun FROM gelombang ORDER BY tahun DESC";
$tahunResult = mysqli_query($conn, $tahunQuery);

function buildUrlWithFilters($page) {
    $params = $_GET;
    $params['page'] = $page;
    unset($params['action'], $params['id']);
    return '?' . http_build_query($params);
}

// Function to check if gelombang can be deleted
function canDelete($row) {
    return ($row['jumlah_kelas'] == 0 && $row['jumlah_pendaftar'] == 0 && 
            ($row['status_pendaftaran'] != 'dibuka' || $row['status_pendaftaran'] === null));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Data Gelombang - LKP Pradata Komputer</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
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
            <div class="d-flex align-items-center flex-grow-1">
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <div class="page-info">
                <h2 class="page-title mb-1">DATA GELOMBANG</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Data Pendaftaran</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Data Gelombang</li>
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
                <!-- UPDATED: Button Group dengan Cetak PDF -->
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
                          title="Cetak laporan data gelombang dalam format PDF">
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
                        <a class="dropdown-item sort-option" href="#" data-sort="tahun" data-order="desc">
                          <i class="bi bi-calendar-check me-2"></i>Tahun Terbaru
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="tahun" data-order="asc">
                          <i class="bi bi-calendar-x me-2"></i>Tahun Terlama
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
                      
                      <!-- Filter Tahun -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Tahun</label>
                        <select class="form-select form-select-sm" id="filterTahun">
                          <option value="">Semua Tahun</option>
                          <?php 
                          if ($tahunResult) {
                            mysqli_data_seek($tahunResult, 0);
                            while($tahun = mysqli_fetch_assoc($tahunResult)): ?>
                              <option value="<?= $tahun['tahun'] ?>">
                                <?= $tahun['tahun'] ?>
                              </option>
                            <?php endwhile;
                          } ?>
                        </select>
                      </div>
                      
                      <!-- Filter Status Gelombang -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Status Gelombang</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                          <option value="">Semua Status</option>
                          <option value="aktif">Aktif</option>
                          <option value="dibuka">Dibuka</option>
                          <option value="selesai">Selesai</option>
                        </select>
                      </div>
                      
                      <!-- Filter Status Formulir -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Status Formulir</label>
                        <select class="form-select form-select-sm" id="filterFormulir">
                          <option value="">Semua Status</option>
                          <option value="dibuka">Dibuka</option>
                          <option value="ditutup">Ditutup</option>
                          <option value="belum_diatur">Belum Diatur</option>
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
                      <span class="info-label">gelombang</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="gelombangTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Nama Gelombang</th>
                  <th>Tahun</th>
                  <th>Gelombang</th>
                  <th>Status</th>
                  <th>Kelas</th>
                  <th>Siswa</th>
                  <th>Formulir</th>
                  <th>Kuota</th>
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
                      <td class="text-nowrap align-middle">
                        <div class="fw-semibold"><?= htmlspecialchars($gelombang['nama_gelombang']) ?></div>
                      </td>
                      
                      <!-- Tahun -->
                      <td class="align-middle">
                        <span class="badge bg-light text-dark"><?= $gelombang['tahun'] ?></span>
                      </td>
                      
                      <!-- Gelombang Ke - UPDATED: Teks biasa dengan terbilang -->
                      <td class="align-middle">
                        <?= $gelombang['gelombang_ke'] ?> (<?= terbilang($gelombang['gelombang_ke']) ?>)
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
                      
                      <!-- Kelas - UPDATED: Tanpa badge -->
                      <td class="text-center align-middle">
                        <?php if ($gelombang['jumlah_kelas'] > 0): ?>
                          <span class="fw-medium"><?= $gelombang['jumlah_kelas'] ?> kelas</span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Siswa - UPDATED: Tanpa badge -->
                      <td class="text-center align-middle">
                        <?php if ($gelombang['jumlah_siswa'] > 0): ?>
                          <span class="fw-medium"><?= $gelombang['jumlah_siswa'] ?> siswa</span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Status Formulir - UPDATED: Simplified -->
                      <td class="text-center align-middle">
                        <?php if ($gelombang['status_pendaftaran'] === 'dibuka'): ?>
                          <span class="badge bg-success">Dibuka</span>
                        <?php elseif ($gelombang['status_pendaftaran'] === 'ditutup'): ?>
                          <span class="badge bg-secondary">Ditutup</span>
                        <?php else: ?>
                          <span class="badge bg-warning">Belum diatur</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Kuota -->
                      <td class="text-center align-middle">
                        <?php if ($gelombang['kuota_maksimal']): ?>
                          <span class="badge bg-info"><?= number_format($gelombang['kuota_maksimal']) ?> orang</span>
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
                             title="Edit Gelombang">
                            <i class="bi bi-pencil"></i>
                          </a>
                          
                          <a href="kelola_formulir.php?id=<?= $gelombang['id_gelombang'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Kelola Formulir Pendaftaran">
                            <i class="bi bi-gear"></i>
                          </a>
                          
                          <?php if (canDelete($gelombang)): ?>
                            <button type="button" 
                                    class="btn btn-action btn-delete btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalHapus<?= $gelombang['id_gelombang'] ?>"
                                    title="Hapus Gelombang">
                              <i class="bi bi-trash"></i>
                            </button>
                          <?php else: ?>
                            <button type="button" 
                                    class="btn btn-secondary btn-sm" 
                                    disabled
                                    title="Terkunci (sedang digunakan)">
                              <i class="bi bi-lock"></i>
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <?php if (canDelete($gelombang)): ?>
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
                    <td colspan="10" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-layers display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Gelombang</h5>
                        <p class="mb-3 text-muted">Mulai dengan membuat gelombang pelatihan pertama</p>
                        <a href="tambah.php" class="btn btn-tambah-soft">
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
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>
  
  <script>
  // Fungsi Cetak PDF
  function cetakLaporanPDF() {
    const button = document.getElementById('btnCetakPDF');
    const originalHTML = button.innerHTML;
    
    // Set loading state
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating PDF...';
    
    // Ambil filter yang sedang aktif dari dropdown
    const filterTahun = document.getElementById('filterTahun')?.value || '';
    const filterStatus = document.getElementById('filterStatus')?.value || '';
    const filterFormulir = document.getElementById('filterFormulir')?.value || '';
    const searchTerm = document.getElementById('searchInput')?.value || '';
    
    // Build URL parameter untuk cetak laporan
    const params = new URLSearchParams();
    
    // Tambahkan filter yang aktif
    if (filterTahun) params.append('tahun', filterTahun);
    if (filterStatus) params.append('status', filterStatus);
    if (filterFormulir) params.append('formulir', filterFormulir);
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
      
      // Show alert dengan link manual
      alert('Pop-up diblokir oleh browser. Silakan buka link berikut secara manual: ' + cetakURL);
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('gelombangTable');
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
        btnCetakPDF.title = 'Tidak ada data gelombang untuk dicetak';
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
          case 'nama':
            sortedRows = [...rows].sort((a, b) => {
              const aName = (a.cells[1]?.textContent || '').trim().toLowerCase();
              const bName = (b.cells[1]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aName.localeCompare(bName) : bName.localeCompare(aName);
            });
            break;
            
          case 'tahun':
            sortedRows = [...rows].sort((a, b) => {
              const aTahun = parseInt((a.cells[2]?.textContent || '').trim()) || 0;
              const bTahun = parseInt((b.cells[2]?.textContent || '').trim()) || 0;
              return order === 'asc' ? aTahun - bTahun : bTahun - aTahun;
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
    const filterTahun = document.getElementById('filterTahun');
    const filterStatus = document.getElementById('filterStatus');
    const filterFormulir = document.getElementById('filterFormulir');
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
      const tahunFilter = filterTahun?.value || '';
      const statusFilter = filterStatus?.value || '';
      const formulirFilter = filterFormulir?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (tahunFilter) activeFilters++;
      if (statusFilter) activeFilters++;
      if (formulirFilter) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach(row => {
        try {
          const namaGelombang = (row.cells[1]?.textContent || '').toLowerCase();
          const tahun = (row.cells[2]?.textContent || '').trim();
          
          // Status gelombang
          const statusElement = row.cells[4]?.querySelector('.badge');
          let status = '';
          if (statusElement) {
            const statusText = statusElement.textContent.trim().toLowerCase();
            if (statusText.includes('aktif')) status = 'aktif';
            else if (statusText.includes('dibuka')) status = 'dibuka';
            else if (statusText.includes('selesai')) status = 'selesai';
          }
          
          // Status formulir
          const formulirElement = row.cells[7]?.querySelector('.badge');
          let formulir = '';
          if (formulirElement) {
            const formulirText = formulirElement.textContent.trim().toLowerCase();
            if (formulirText.includes('dibuka')) formulir = 'dibuka';
            else if (formulirText.includes('ditutup')) formulir = 'ditutup';
            else if (formulirText.includes('belum diatur')) formulir = 'belum_diatur';
          }
          
          let showRow = true;
          
          if (searchTerm && !namaGelombang.includes(searchTerm) && !tahun.includes(searchTerm)) {
            showRow = false;
          }
          
          if (tahunFilter && tahun !== tahunFilter) showRow = false;
          if (statusFilter && status !== statusFilter) showRow = false;
          if (formulirFilter && formulir !== formulirFilter) showRow = false;
          
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
          btnCetakPDF.title = `Cetak laporan ${visibleCount} data gelombang`;
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
        if (filterTahun) filterTahun.value = '';
        if (filterStatus) filterStatus.value = '';
        if (filterFormulir) filterFormulir.value = '';
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
  </script>
</body>
</html>