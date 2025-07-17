<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'nilai'; 
$baseURL = '../';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Count total records untuk pagination
$countQuery = "SELECT COUNT(*) as total FROM nilai n 
               LEFT JOIN siswa s ON n.id_siswa = s.id_siswa
               LEFT JOIN kelas k ON n.id_kelas = k.id_kelas
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Query dengan logika status kelulusan yang diperbaiki
$query = "SELECT n.*, s.nama as nama_siswa, s.nik, 
          k.nama_kelas, g.nama_gelombang,
          -- Hitung nilai yang sudah terisi
          CASE WHEN n.nilai_word IS NOT NULL AND n.nilai_word > 0 THEN 1 ELSE 0 END +
          CASE WHEN n.nilai_excel IS NOT NULL AND n.nilai_excel > 0 THEN 1 ELSE 0 END +
          CASE WHEN n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0 THEN 1 ELSE 0 END +
          CASE WHEN n.nilai_internet IS NOT NULL AND n.nilai_internet > 0 THEN 1 ELSE 0 END +
          CASE WHEN n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0 THEN 1 ELSE 0 END as nilai_terisi,
          -- Status kelulusan berdasarkan kelengkapan nilai
          CASE 
            WHEN (n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                 (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                 (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                 (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                 (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0) THEN
              CASE 
                WHEN n.rata_rata >= 60 THEN 'lulus'
                ELSE 'tidak lulus'
              END
            ELSE 'belum_lengkap'
          END as status_kelulusan_fix,
          -- Rata-rata sementara (dari nilai yang ada)
          CASE 
            WHEN (n.nilai_word IS NOT NULL AND n.nilai_word > 0) OR
                 (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) OR
                 (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) OR
                 (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) OR
                 (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0) THEN
              (COALESCE(n.nilai_word, 0) + COALESCE(n.nilai_excel, 0) + COALESCE(n.nilai_ppt, 0) + 
               COALESCE(n.nilai_internet, 0) + COALESCE(n.nilai_pengembangan, 0)) / 
              (CASE WHEN n.nilai_word IS NOT NULL AND n.nilai_word > 0 THEN 1 ELSE 0 END +
               CASE WHEN n.nilai_excel IS NOT NULL AND n.nilai_excel > 0 THEN 1 ELSE 0 END +
               CASE WHEN n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0 THEN 1 ELSE 0 END +
               CASE WHEN n.nilai_internet IS NOT NULL AND n.nilai_internet > 0 THEN 1 ELSE 0 END +
               CASE WHEN n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0 THEN 1 ELSE 0 END)
            ELSE NULL
          END as rata_rata_sementara,
          CASE 
            WHEN n.rata_rata >= 80 THEN 'Sangat Baik'
            WHEN n.rata_rata >= 70 THEN 'Baik'
            WHEN n.rata_rata >= 60 THEN 'Cukup'
            ELSE 'Kurang'
          END as kategori_nilai
          FROM nilai n 
          LEFT JOIN siswa s ON n.id_siswa = s.id_siswa
          LEFT JOIN kelas k ON n.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          ORDER BY n.id_nilai DESC
          LIMIT $recordsPerPage OFFSET $offset";
$result = mysqli_query($conn, $query);

// Statistik nilai dengan logika yang diperbaiki
$statsQuery = "SELECT 
                COUNT(*) as total_nilai,
                COUNT(CASE 
                  WHEN (n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                       (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                       (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                       (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                       (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0) AND
                       n.rata_rata >= 60 THEN 1 END) as lulus,
                COUNT(CASE 
                  WHEN (n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                       (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                       (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                       (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                       (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0) AND
                       n.rata_rata < 60 THEN 1 END) as tidak_lulus,
                COUNT(CASE 
                  WHEN NOT ((n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                            (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                            (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                            (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                            (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0)) THEN 1 END) as belum_lengkap,
                ROUND(AVG(CASE 
                  WHEN (n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                       (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                       (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                       (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                       (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0) 
                  THEN n.rata_rata END), 2) as rata_rata_keseluruhan
               FROM nilai n WHERE n.id_siswa IS NOT NULL";
$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Untuk dropdown kelas
$kelasQuery = "SELECT DISTINCT k.nama_kelas FROM kelas k ORDER BY k.nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Untuk dropdown gelombang
$gelombangQuery = "SELECT DISTINCT g.nama_gelombang FROM gelombang g ORDER BY g.nama_gelombang";
$gelombangResult = mysqli_query($conn, $gelombangQuery);

// Untuk dropdown status kelulusan yang diperbaiki
$statusOptions = ['lulus', 'tidak lulus', 'belum_lengkap'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Data Nilai</title>
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
                <h2 class="page-title mb-1">DATA NILAI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Data Akademik</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Data Nilai</li>
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
                  <i class="bi bi-pencil-square me-2"></i>Kelola Nilai Siswa
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <a href="tambah.php" class="btn btn-primary-formal">
                  <i class="bi bi-plus-circle"></i>
                  Tambah Nilai
                </a>
                <div class="btn-group ms-2">
                  <button type="button" class="btn btn-secondary-formal dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i>
                    Export Data
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item" href="export-pdf.php">
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
                          <i class="bi bi-sort-alpha-down me-2"></i>Nama Siswa A-Z
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="nama" data-order="desc">
                          <i class="bi bi-sort-alpha-up me-2"></i>Nama Siswa Z-A
                        </a>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="rata_rata" data-order="desc">
                          <i class="bi bi-sort-numeric-down me-2"></i>Nilai Tertinggi
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="rata_rata" data-order="asc">
                          <i class="bi bi-sort-numeric-up me-2"></i>Nilai Terendah
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="kelas" data-order="asc">
                          <i class="bi bi-building me-2"></i>Kelas
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
                      
                      <!-- Filter Kelas -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Kelas</label>
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

                      <!-- Filter Status Kelulusan -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Status Kelulusan</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                          <option value="">Semua Status</option>
                          <option value="lulus">Lulus</option>
                          <option value="tidak lulus">Tidak Lulus</option>
                          <option value="belum_lengkap">Belum Lengkap</option>
                        </select>
                      </div>
                                         
                      <!-- Filter Kategori Nilai -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Kategori Nilai</label>
                        <select class="form-select form-select-sm" id="filterKategori">
                          <option value="">Semua Kategori</option>
                          <option value="sangat_baik">Sangat Baik (80-100)</option>
                          <option value="baik">Baik (70-79)</option>
                          <option value="cukup">Cukup (60-69)</option>
                          <option value="kurang">Kurang (<60)</option>
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
            <table class="custom-table mb-0" id="nilaiTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Nama Siswa</th>
                  <th>Kelas</th>
                  <th class="text-center">Word</th>
                  <th class="text-center">Excel</th>
                  <th class="text-center">PPT</th>
                  <th class="text-center">Internet</th>
                  <th class="text-center">Softskill</th>
                  <th class="text-center">Progress</th>
                  <th class="text-center">Rata-rata</th>
                  <th class="text-center">Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($nilai = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <!-- Nama Siswa -->
                      <td class="align-middle text-nowrap">
                        <div class="fw-medium"><?= htmlspecialchars($nilai['nama_siswa']) ?></div>
                        <small class="text-muted">NIK: <?= htmlspecialchars($nilai['nik']) ?></small>
                      </td>
                      
                      <!-- Kelas -->
                      <td class="align-middle text-nowrap">
                        <?php if($nilai['nama_kelas']): ?>
                          <span class="badge bg-primary px-2 py-1">
                            <i class="bi bi-building me-1"></i>
                            <?= htmlspecialchars($nilai['nama_kelas']) ?>
                          </span>
                          <?php if($nilai['nama_gelombang']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($nilai['nama_gelombang']) ?></small>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-muted fst-italic">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Nilai Word -->
                      <td class="text-center align-middle">
                        <?php if($nilai['nilai_word'] && $nilai['nilai_word'] > 0): ?>
                          <span class="fw-medium"><?= $nilai['nilai_word'] ?></span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Nilai Excel -->
                      <td class="text-center align-middle">
                        <?php if($nilai['nilai_excel'] && $nilai['nilai_excel'] > 0): ?>
                          <span class="fw-medium"><?= $nilai['nilai_excel'] ?></span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Nilai PPT -->
                      <td class="text-center align-middle">
                        <?php if($nilai['nilai_ppt'] && $nilai['nilai_ppt'] > 0): ?>
                          <span class="fw-medium"><?= $nilai['nilai_ppt'] ?></span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Nilai Internet -->
                      <td class="text-center align-middle">
                        <?php if($nilai['nilai_internet'] && $nilai['nilai_internet'] > 0): ?>
                          <span class="fw-medium"><?= $nilai['nilai_internet'] ?></span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Nilai Pengembangan -->
                      <td class="text-center align-middle">
                        <?php if($nilai['nilai_pengembangan'] && $nilai['nilai_pengembangan'] > 0): ?>
                          <span class="fw-medium"><?= $nilai['nilai_pengembangan'] ?></span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Progress Nilai -->
                      <td class="text-center align-middle">
                        <div class="d-flex flex-column align-items-center">
                          <span class="badge bg-<?= $nilai['nilai_terisi'] == 5 ? 'success' : ($nilai['nilai_terisi'] >= 3 ? 'warning' : 'secondary') ?> mb-1">
                            <?= $nilai['nilai_terisi'] ?>/5
                          </span>
                          <div class="progress" style="width: 60px; height: 4px;">
                            <div class="progress-bar bg-<?= $nilai['nilai_terisi'] == 5 ? 'success' : ($nilai['nilai_terisi'] >= 3 ? 'warning' : 'secondary') ?>" 
                                 style="width: <?= ($nilai['nilai_terisi'] / 5) * 100 ?>%"></div>
                          </div>
                        </div>
                      </td>
                      
                      <!-- Rata-rata -->
                      <td class="text-center align-middle">
                        <?php if($nilai['rata_rata_sementara']): ?>
                          <?php
                          $rata = (float)$nilai['rata_rata_sementara'];
                          $badgeClass = 'bg-secondary';
                          if ($rata >= 80) $badgeClass = 'bg-success';
                          elseif ($rata >= 70) $badgeClass = 'bg-primary';
                          elseif ($rata >= 60) $badgeClass = 'bg-warning';
                          else $badgeClass = 'bg-danger';
                          ?>
                          <div class="d-flex flex-column align-items-center">
                            <span class="badge <?= $badgeClass ?> px-2 py-1 mb-1">
                              <?= number_format($rata, 1) ?>
                            </span>
                            <?php if($nilai['nilai_terisi'] < 5): ?>
                              <small class="text-dark fw-medium" style="font-size: 0.7em;">sementara</small>
                            <?php endif; ?>
                          </div>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Status Kelulusan -->
                      <td class="text-center align-middle">
                        <?php 
                        $status = $nilai['status_kelulusan_fix'];
                        if($status == 'lulus'): ?>
                          <span class="badge bg-success px-2 py-1">
                            <i class="bi bi-check-circle me-1"></i>
                            Lulus
                          </span>
                        <?php elseif($status == 'tidak lulus'): ?>
                          <span class="badge bg-danger px-2 py-1">
                            <i class="bi bi-x-circle me-1"></i>
                            Tidak Lulus
                          </span>
                        <?php else: ?>
                          <span class="badge bg-warning px-2 py-1">
                            <i class="bi bi-clock me-1"></i>
                            Belum Lengkap
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="detail.php?id=<?= $nilai['id_nilai'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="edit.php?id=<?= $nilai['id_nilai'] ?>" 
                             class="btn btn-action btn-edit btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Edit">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $nilai['id_nilai'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <div class="modal fade" id="modalHapus<?= $nilai['id_nilai'] ?>" tabindex="-1" aria-labelledby="modalHapusLabel<?= $nilai['id_nilai'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content border-0 shadow-lg">
                          
                          <!-- Modal Header -->
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <div class="warning-icon">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                              </div>
                              <h5 class="modal-title" id="modalHapusLabel<?= $nilai['id_nilai'] ?>">
                                Konfirmasi Hapus
                              </h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <!-- Modal Body -->
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus nilai siswa:</p>
                            
                            <div class="alert alert-light border">
                              <div class="row">
                                <div class="col-4 text-muted small">Nama:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($nilai['nama_siswa']) ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Kelas:</div>
                                <div class="col-8 fw-medium"><?= htmlspecialchars($nilai['nama_kelas'] ?? '-') ?></div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Progress:</div>
                                <div class="col-8 fw-medium"><?= $nilai['nilai_terisi'] ?>/5 nilai</div>
                              </div>
                              <div class="row">
                                <div class="col-4 text-muted small">Status:</div>
                                <div class="col-8 fw-medium">
                                  <?php 
                                  $status_text = '';
                                  switch($nilai['status_kelulusan_fix']) {
                                    case 'lulus': $status_text = 'Lulus'; break;
                                    case 'tidak lulus': $status_text = 'Tidak Lulus'; break;
                                    default: $status_text = 'Belum Lengkap'; break;
                                  }
                                  echo $status_text;
                                  ?>
                                </div>
                              </div>
                            </div>
                            
                            <div class="alert alert-warning">
                              <i class="bi bi-info-circle me-2"></i>
                              Data nilai akan dihapus permanen dan tidak dapat dipulihkan
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
                                        onclick="confirmDelete(<?= $nilai['id_nilai'] ?>, '<?= htmlspecialchars($nilai['nama_siswa']) ?>')">
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
                    <td colspan="12" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-clipboard-data display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Data Nilai</h5>
                        <p class="mb-3 text-muted">Mulai tambahkan nilai siswa untuk evaluasi pembelajaran</p>
                        <a href="tambah.php" class="btn btn-primary">
                          <i class="bi bi-plus-circle me-2"></i>Tambah Nilai Pertama
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
    const table = document.getElementById('nilaiTable');
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
              const aNama = (a.cells[1]?.textContent || '').trim().toLowerCase();
              const bNama = (b.cells[1]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aNama.localeCompare(bNama) : bNama.localeCompare(aNama);
            });
            break;
            
          case 'rata_rata':
            sortedRows = [...rows].sort((a, b) => {
              const aRata = parseFloat((a.cells[9]?.textContent || '0').trim()) || 0;
              const bRata = parseFloat((b.cells[9]?.textContent || '0').trim()) || 0;
              return order === 'asc' ? aRata - bRata : bRata - aRata;
            });
            break;
            
          case 'kelas':
            sortedRows = [...rows].sort((a, b) => {
              const aKelas = (a.cells[2]?.textContent || '').trim().toLowerCase();
              const bKelas = (b.cells[2]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aKelas.localeCompare(bKelas) : bKelas.localeCompare(aKelas);
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
    const filterKelas = document.getElementById('filterKelas');
    const filterGelombang = document.getElementById('filterGelombang');
    const filterStatus = document.getElementById('filterStatus');
    const filterKategori = document.getElementById('filterKategori');
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
      const kelasFilter = filterKelas?.value || '';
      const gelombangFilter = filterGelombang?.value || '';
      const statusFilter = filterStatus?.value || '';
      const kategoriFilter = filterKategori?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (kelasFilter) activeFilters++;
      if (gelombangFilter) activeFilters++;
      if (statusFilter) activeFilters++;
      if (kategoriFilter) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach(row => {
        try {
          const namaSiswa = (row.cells[1]?.textContent || '').toLowerCase();
          const kelas = (row.cells[2]?.textContent || '').trim();
          const gelombang = (row.cells[2]?.textContent || '').trim();
          const status = (row.cells[10]?.textContent || '').toLowerCase().trim();
          const rataRata = parseFloat((row.cells[9]?.textContent || '0').trim()) || 0;
          
          let showRow = true;
          
          // Filter search
          if (searchTerm && 
              !namaSiswa.includes(searchTerm) && 
              !kelas.toLowerCase().includes(searchTerm)) {
            showRow = false;
          }
          
          // Filter kelas
          if (kelasFilter && !kelas.includes(kelasFilter)) showRow = false;
          
          // Filter gelombang
          if (gelombangFilter && !gelombang.includes(gelombangFilter)) showRow = false;
          
          // Filter status - Updated untuk status baru
          if (statusFilter) {
            if (statusFilter === 'belum_lengkap' && !status.includes('belum lengkap')) showRow = false;
            else if (statusFilter !== 'belum_lengkap' && !status.includes(statusFilter)) showRow = false;
          }
          
          // Filter kategori nilai
          if (kategoriFilter) {
            let matchKategori = false;
            if (kategoriFilter === 'sangat_baik' && rataRata >= 80) matchKategori = true;
            if (kategoriFilter === 'baik' && rataRata >= 70 && rataRata < 80) matchKategori = true;
            if (kategoriFilter === 'cukup' && rataRata >= 60 && rataRata < 70) matchKategori = true;
            if (kategoriFilter === 'kurang' && rataRata < 60) matchKategori = true;
            
            if (!matchKategori) showRow = false;
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
        if (filterKelas) filterKelas.value = '';
        if (filterGelombang) filterGelombang.value = '';
        if (filterStatus) filterStatus.value = '';
        if (filterKategori) filterKategori.value = '';
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
  function confirmDelete(id, namaSiswa) {
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