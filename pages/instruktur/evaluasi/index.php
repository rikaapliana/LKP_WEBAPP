<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth();

include '../../../includes/db.php';
$activePage = 'hasil-evaluasi';
$baseURL = '../';

// Ambil ID instruktur yang sedang login
$stmt = $conn->prepare("SELECT id_instruktur, nama FROM instruktur WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$instrukturData = $stmt->get_result()->fetch_assoc();

if (!$instrukturData) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit();
}

$id_instruktur = $instrukturData['id_instruktur'];

// Ambil daftar kelas yang diampu instruktur
$kelasQuery = "SELECT k.id_kelas, k.nama_kelas, g.nama_gelombang, g.tahun,
               COUNT(DISTINCT s.id_siswa) as total_siswa
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
               WHERE k.id_instruktur = ? 
               GROUP BY k.id_kelas
               ORDER BY k.nama_kelas";
$kelasStmt = $conn->prepare($kelasQuery);
$kelasStmt->bind_param("i", $id_instruktur);
$kelasStmt->execute();
$kelasResult = $kelasStmt->get_result();

// Filter parameters
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$selected_periode = isset($_GET['periode']) ? (int)$_GET['periode'] : 0;

// Step 1: Jika kelas dipilih, ambil periode evaluasi untuk kelas tersebut
$periodeList = [];
if ($selected_kelas) {
    $periodeQuery = "SELECT 
                        pe.id_periode,
                        pe.nama_evaluasi,
                        pe.jenis_evaluasi,
                        pe.materi_terkait,
                        pe.tanggal_buka,
                        pe.tanggal_tutup,
                        pe.status,
                        DATE_FORMAT(pe.tanggal_buka, '%d %b %Y') as tgl_buka_format
                     FROM periode_evaluasi pe
                     LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang
                     JOIN kelas k ON g.id_gelombang = k.id_gelombang
                     WHERE k.id_instruktur = ? AND k.nama_kelas = ?
                     ORDER BY pe.tanggal_buka DESC";
    
    $periodeStmt = $conn->prepare($periodeQuery);
    $periodeStmt->bind_param("is", $id_instruktur, $selected_kelas);
    $periodeStmt->execute();
    $periodeResult = $periodeStmt->get_result();
    
    while ($periode = $periodeResult->fetch_assoc()) {
        $periodeList[] = $periode;
    }
    
    // Jika tidak ada filter periode, ambil periode terbaru sebagai default
    if (!$selected_periode && !empty($periodeList)) {
        $selected_periode = $periodeList[0]['id_periode'];
    }
}

// Step 2: Jika periode dipilih, ambil data evaluasi dan siswa
$evaluasiData = null;
$siswaData = [];
$stats = ['total_siswa' => 0, 'sudah_selesai' => 0, 'belum_mulai' => 0];

if ($selected_kelas && $selected_periode) {
    // Ambil data periode evaluasi yang dipilih
    foreach ($periodeList as $periode) {
        if ($periode['id_periode'] == $selected_periode) {
            $evaluasiData = $periode;
            break;
        }
    }
    
    if ($evaluasiData) {
        // Hitung total pertanyaan
        $total_pertanyaan = 0;
        $pertanyaanQuery = "SELECT pertanyaan_terpilih FROM periode_evaluasi WHERE id_periode = ?";
        $pertanyaanStmt = $conn->prepare($pertanyaanQuery);
        $pertanyaanStmt->bind_param("i", $selected_periode);
        $pertanyaanStmt->execute();
        $pertanyaanResult = $pertanyaanStmt->get_result()->fetch_assoc();
        
        if ($pertanyaanResult['pertanyaan_terpilih']) {
            $pertanyaan_ids = json_decode($pertanyaanResult['pertanyaan_terpilih'], true);
            if (is_array($pertanyaan_ids)) {
                $total_pertanyaan = count($pertanyaan_ids);
            }
        }
        
        // Ambil data siswa untuk periode ini
        $siswaQuery = "SELECT 
                        s.id_siswa,
                        s.nama,
                        s.nik,
                        s.pas_foto,
                        e.id_evaluasi,
                        e.status_evaluasi,
                        e.tanggal_evaluasi,
                        COUNT(je.id_jawaban) as jumlah_jawaban
                       FROM siswa s
                       JOIN kelas k ON s.id_kelas = k.id_kelas
                       LEFT JOIN evaluasi e ON s.id_siswa = e.id_siswa AND e.id_periode = ?
                       LEFT JOIN jawaban_evaluasi je ON e.id_evaluasi = je.id_evaluasi
                       WHERE k.id_instruktur = ? AND k.nama_kelas = ? AND s.status_aktif = 'aktif'
                       GROUP BY s.id_siswa
                       ORDER BY s.nama ASC";
        
        $siswaStmt = $conn->prepare($siswaQuery);
        $siswaStmt->bind_param("iis", $selected_periode, $id_instruktur, $selected_kelas);
        $siswaStmt->execute();
        $siswaResult = $siswaStmt->get_result();
        
        while ($siswa = $siswaResult->fetch_assoc()) {
            $siswa['total_pertanyaan'] = $total_pertanyaan;
            $siswaData[] = $siswa;
            
            // Update statistik
            $stats['total_siswa']++;
            if ($siswa['status_evaluasi'] == 'selesai') {
                $stats['sudah_selesai']++;
            } else {
                $stats['belum_mulai']++;
            }
        }
    }
}

// Label materi
$materi_labels = [
    'word' => 'Microsoft Word',
    'excel' => 'Microsoft Excel', 
    'ppt' => 'Microsoft PowerPoint',
    'internet' => 'Internet & Email'
];

// Fungsi format tanggal
function formatTanggal($tanggal) {
    if (!$tanggal) return '-';
    return date('d M Y, H:i', strtotime($tanggal));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hasil Evaluasi - LKP Pradata Komputer</title>
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
                <h2 class="page-title mb-1">HASIL EVALUASI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Hasil Evaluasi</li>
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

        <!-- Step 1: Pilih Kelas -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-person-check text-primary me-2"></i>
                Pilih Kelas yang Anda Ampu
              </h5>
            </div>
          </div>
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-md-6">
                <label class="form-label fw-bold">Kelas:</label>
                <select class="form-select" id="selectKelas">
                  <option value="">-- Pilih Kelas --</option>
                  <?php 
                  mysqli_data_seek($kelasResult, 0);
                  while ($kelas = $kelasResult->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($kelas['nama_kelas']) ?>" 
                            <?= $selected_kelas == $kelas['nama_kelas'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($kelas['nama_kelas']) ?> 
                      (<?= $kelas['total_siswa'] ?> siswa)
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2: Pilih Periode (jika kelas sudah dipilih) -->
        <?php if ($selected_kelas && !empty($periodeList)): ?>
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-calendar-event text-info me-2"></i>
                Pilih Periode Evaluasi untuk Kelas <?= htmlspecialchars($selected_kelas) ?>
              </h5>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <?php foreach ($periodeList as $index => $periode): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                  <div class="card h-100 <?= $periode['id_periode'] == $selected_periode ? 'border-primary' : 'border-light' ?>" 
                       style="cursor: pointer;" 
                       onclick="selectPeriode(<?= $periode['id_periode'] ?>)">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($periode['nama_evaluasi']) ?></h6>
                        <span class="badge <?= $periode['status'] == 'aktif' ? 'bg-success' : 'bg-secondary' ?>">
                          <?= ucfirst($periode['status']) ?>
                        </span>
                      </div>
                      
                      <div class="mb-2">
                        <?php if($periode['jenis_evaluasi'] == 'per_materi'): ?>
                          <span class="badge bg-info me-1">Per Materi</span>
                          <?php if($periode['materi_terkait']): ?>
                            <small class="text-muted">
                              <?= $materi_labels[$periode['materi_terkait']] ?? ucfirst($periode['materi_terkait']) ?>
                            </small>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="badge bg-primary">Akhir Kursus</span>
                        <?php endif; ?>
                      </div>
                      
                      <small class="text-muted">
                        <i class="bi bi-calendar me-1"></i>
                        <?= $periode['tgl_buka_format'] ?>
                      </small>
                      
                      <?php if($periode['id_periode'] == $selected_periode): ?>
                        <div class="mt-2">
                          <i class="bi bi-check-circle text-success me-1"></i>
                          <small class="text-success fw-bold">Terpilih</small>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Step 3: Hasil Evaluasi (jika periode sudah dipilih) -->
        <?php if ($selected_kelas && $selected_periode && $evaluasiData && !empty($siswaData)): ?>
        
        <!-- Daftar Siswa -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-people me-2"></i>Daftar Siswa & Status Evaluasi
                </h5>
                <small class="text-muted">
                  Kelas <?= htmlspecialchars($selected_kelas) ?> • 
                  Periode: <?= formatTanggal($evaluasiData['tanggal_buka']) ?>
                </small>
              </div>
            </div>
          </div>
       

          <!-- Search Controls -->
          <div class="p-3 border-bottom">
            <div class="row align-items-center">  
              <div class="col-12">
                <div class="d-flex flex-wrap align-items-center gap-2 controls-container">
                  <!-- Search Box -->
                  <div class="d-flex align-items-center search-container">
                    <label for="searchInput" class="me-2 mb-0 search-label">
                      <small>Search:</small>
                    </label>
                    <input type="search" id="searchInput" class="form-control form-control-sm search-input">
                  </div>
                  
                 <!-- Result Info -->
                  <div class="ms-auto result-info d-flex align-items-center">
                    <label class="me-2 mb-0 search-label">
                      <small>Show:</small>
                    </label>
                    <div class="info-badge">
                      <span class="info-count"><?= count($siswaData) ?></span>
                      <span class="info-separator">dari</span>
                      <span class="info-total"><?= count($siswaData) ?></span>
                      <span class="info-label">siswa</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Table -->
          <div class="table-responsive">
            <table class="custom-table mb-0" id="siswaTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>Foto</th>
                  <th>Data Siswa</th>
                  <th>Status Evaluasi</th>
                  <th>Progress Jawaban</th>
                  <th>Tanggal Selesai</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($siswaData)): ?>
                  <?php 
                  $no = 1;
                  foreach ($siswaData as $siswa): 
                    // Hitung progress jawaban
                    $progress_pct = $siswa['total_pertanyaan'] > 0 ? 
                                   round(($siswa['jumlah_jawaban'] / $siswa['total_pertanyaan']) * 100) : 0;
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle"><?= $no++ ?></td>
                      
                      <!-- Foto -->
                      <td class="text-center align-middle">
                        <?php if($siswa['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$siswa['pas_foto'])): ?>
                          <img src="../../../uploads/pas_foto/<?= $siswa['pas_foto'] ?>" 
                               alt="Foto" 
                               class="rounded-circle" 
                               style="width: 50px; height: 50px; object-fit: cover; border: 2px solid #e9ecef;" 
                               title="<?= htmlspecialchars($siswa['nama']) ?>">
                        <?php else: ?>
                          <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white" 
                               style="width: 50px; height: 50px;">
                            <i class="bi bi-person-fill fs-5"></i>
                          </div>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Data Siswa -->
                      <td class="align-middle">
                        <div class="fw-medium text-dark"><?= htmlspecialchars($siswa['nama']) ?></div>
                        <small class="text-muted d-block">NIK: <?= htmlspecialchars($siswa['nik']) ?></small>
                      </td>
                      
                      <!-- Status Evaluasi -->
                      <td class="text-center align-middle">
                        <?php if($siswa['status_evaluasi'] == 'selesai'): ?>
                          <span class="badge badge-active">
                            <i class="bi bi-check-circle me-1"></i>Selesai
                          </span>
                        <?php else: ?>
                          <span class="badge badge-inactive">
                            <i class="bi bi-x-circle me-1"></i>Belum Mulai
                          </span>
                        <?php endif; ?>
                      </td>

                      <!-- Progress Jawaban -->
                      <td class="align-middle">
                        <div class="d-flex align-items-center">
                          <div class="progress me-2" style="width: 80px; height: 8px;">
                            <div class="progress-bar bg-<?= $progress_pct == 100 ? 'success' : ($progress_pct > 0 ? 'info' : 'secondary') ?>" 
                                 style="width: <?= $progress_pct ?>%"></div>
                          </div>
                          <small class="text-muted">
                            <?= $siswa['jumlah_jawaban'] ?>/<?= $siswa['total_pertanyaan'] ?>
                          </small>
                        </div>
                        <small class="text-muted"><?= $progress_pct ?>% selesai</small>
                      </td>
                      
                      <!-- Tanggal Selesai -->
                      <td class="align-middle">
                        <?php if($siswa['tanggal_evaluasi']): ?>
                          <small class="text-muted">
                            <?= formatTanggal($siswa['tanggal_evaluasi']) ?>
                          </small>
                        <?php else: ?>
                          <small class="text-muted">-</small>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <?php if($siswa['id_evaluasi']): ?>
                          <a href="jawaban.php?id_evaluasi=<?= $siswa['id_evaluasi'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Lihat Jawaban Lengkap">
                            <i class="bi bi-eye me-1"></i>Lihat
                          </a>
                        <?php else: ?>
                          <span class="text-muted">
                            <small>Belum ada jawaban</small>
                          </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-people display-4 text-muted mb-3 d-block"></i>
                        <h5>Tidak Ada Siswa</h5>
                        <p class="mb-3 text-muted">Belum ada siswa terdaftar di kelas ini</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Empty States -->
        <?php elseif ($selected_kelas && empty($periodeList)): ?>
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-5">
            <i class="bi bi-calendar-x display-1 text-muted mb-3"></i>
            <h4>Belum Ada Evaluasi</h4>
            <p class="text-muted">
              Kelas <strong><?= htmlspecialchars($selected_kelas) ?></strong> belum memiliki periode evaluasi.<br>
              Silakan pilih kelas lain atau tunggu admin membuat evaluasi.
            </p>
          </div>
        </div>

        <?php elseif (!$selected_kelas): ?>
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-5">
            <i class="bi bi-arrow-up display-3 text-primary mb-3"></i>
            <h5>Mulai dengan Memilih Kelas</h5>
            <p class="text-muted">
              Silakan pilih kelas dari dropdown di atas untuk melihat hasil evaluasi siswa.
            </p>
          </div>
        </div>

        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Definisi semua variabel yang diperlukan
    const table = document.getElementById('siswaTable');
    const tbody = table ? table.querySelector('tbody') : null;
    const rows = tbody ? Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('.empty-state')) : [];
    const filterButton = document.getElementById('filterButton');
    const filterBadge = document.getElementById('filterBadge');
    const searchInput = document.getElementById('searchInput');
    const filterStatus = document.getElementById('filterStatus');
    const applyFilterBtn = document.getElementById('applyFilter');
    const resetFilterBtn = document.getElementById('resetFilter');
    const infoCount = document.querySelector('.info-count');
    const infoTotal = document.querySelector('.info-total');
    
    let activeFilters = 0;
    let searchTimeout;

    // Function untuk pilih periode
    window.selectPeriode = function(periodeId) {
      const params = new URLSearchParams(window.location.search);
      params.set('periode', periodeId);
      window.location.href = window.location.pathname + '?' + params.toString();
    };

    // Event listener untuk pilih kelas
    const selectKelas = document.getElementById('selectKelas');
    if (selectKelas) {
      selectKelas.addEventListener('change', function() {
        if (this.value) {
          window.location.href = 'index.php?kelas=' + encodeURIComponent(this.value);
        } else {
          window.location.href = 'index.php';
        }
      });
    }

    // Hanya jalankan filter jika ada data siswa
    if (rows.length > 0) {
      
      // Real-time search
      if (searchInput) {
        searchInput.addEventListener('input', function(e) {
          e.stopPropagation();
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            applyFilters();
          }, 300);
        });
      }
      
      // Function untuk apply filters
      function applyFilters() {
        const searchTerm = (searchInput?.value || '').toLowerCase().trim();
        const statusFilter = filterStatus?.value || '';
        
        let visibleCount = 0;
        activeFilters = 0;
        
        // Hitung active filters
        if (searchTerm) activeFilters++;
        if (statusFilter) activeFilters++;
        
        updateFilterBadge();
        
        // Filter setiap row
        rows.forEach(row => {
          try {
            // Get data dari cells
            const namaCell = row.cells[2]; // kolom Data Siswa
            const nama = namaCell ? namaCell.textContent.toLowerCase() : '';
            
            // Get status dari badge
            const statusCell = row.cells[3]; // kolom Status Evaluasi
            const statusBadge = statusCell ? statusCell.querySelector('.badge') : null;
            let rowStatus = '';
            
            if (statusBadge) {
              const badgeText = statusBadge.textContent.toLowerCase();
              if (badgeText.includes('selesai')) {
                rowStatus = 'selesai';
              } else if (badgeText.includes('belum')) {
                rowStatus = 'belum';
              }
            }
            
            let showRow = true;
            
            // Apply search filter
            if (searchTerm && !nama.includes(searchTerm)) {
              showRow = false;
            }
            
            // Apply status filter
            if (statusFilter && rowStatus !== statusFilter) {
              showRow = false;
            }
            
            // Show/hide row
            row.style.display = showRow ? '' : 'none';
            if (showRow) visibleCount++;
            
          } catch (error) {
            console.error('Filter error for row:', error);
            row.style.display = '';
            visibleCount++;
          }
        });
        
        // Update info count
        if (infoCount) infoCount.textContent = visibleCount;
        
        // Update row numbers
        updateRowNumbers();
      }
      
      // Function untuk update filter badge
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
      
      // Function untuk update row numbers
      function updateRowNumbers() {
        let counter = 1;
        rows.forEach(row => {
          if (row.style.display !== 'none') {
            const noCell = row.cells[0];
            if (noCell) noCell.textContent = counter++;
          }
        });
      }

      // Event listeners untuk tombol filter
      if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          applyFilters();
          
          // Close dropdown setelah apply
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
          
          // Reset form values
          if (searchInput) searchInput.value = '';
          if (filterStatus) filterStatus.value = '';
          
          // Apply filters (akan reset tampilan)
          applyFilters();
          
          // Close dropdown setelah reset
          setTimeout(() => {
            const dropdown = bootstrap.Dropdown.getInstance(filterButton);
            if (dropdown) dropdown.hide();
          }, 100);
        });
      }
      
      // Prevent dropdown dari closing ketika click di dalam
      const filterDropdown = document.querySelector('.dropdown-menu.p-3');
      if (filterDropdown) {
        filterDropdown.addEventListener('click', function(e) {
          e.stopPropagation();
        });
      }

      // Add hover effects untuk table rows
      rows.forEach(row => {
        row.addEventListener('mouseenter', function() {
          this.style.backgroundColor = '#f8f9fa';
        });
        
        row.addEventListener('mouseleave', function() {
          this.style.backgroundColor = '';
        });
      });
    }

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
  </script>

  <style>
  /* Controls styling */
  .search-container {
    min-width: 200px;
  }

  .search-input {
    min-width: 150px;
  }

  .search-label {
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 500;
  }

  .controls-container {
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .control-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  

  .result-info .info-count {
    font-weight: 600;
    color: #495057;
  }

  .result-info .info-separator {
    margin: 0 0.25rem;
    color: #6c757d;
  }

  .result-info .info-total {
    font-weight: 600;
    color: #495057;
  }

  .result-info .info-label {
    color: #6c757d;
  }

  /* Badge styles */
  .badge.badge-active {
    background-color: #d1edff;
    color: #0c63e4;
    border: 1px solid #b6d7ff;
  }

  .badge.badge-inactive {
    background-color: #f8d7da;
    color: #842029;
    border: 1px solid #f5c6cb;
  }

  /* Table styling */
  .custom-table td {
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
  }

  .custom-table th {
    font-weight: 600;
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
  }

  /* Empty state styling */
  .empty-state {
    text-align: center;
    padding: 3rem 1rem;
  }

  .empty-state i {
    opacity: 0.5;
  }

  .empty-state h5 {
    color: #6c757d;
    margin-bottom: 1rem;
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .controls-container {
      flex-direction: column;
      align-items: stretch;
    }
    
    .result-info {
      justify-content: center;
    }
    
    .search-container {
      min-width: auto;
    }
    
    .search-input {
      min-width: auto;
    }
  }
  </style>
</body>
</html>