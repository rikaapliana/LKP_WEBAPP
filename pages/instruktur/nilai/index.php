<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth(); // Hanya instruktur yang bisa akses

include '../../../includes/db.php';
$activePage = 'kelola-nilai'; 
$baseURL = '../';

// Set timezone Makassar (WITA)
date_default_timezone_set('Asia/Makassar');

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
$nama_instruktur = $instrukturData['nama'];

// Get active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'input';

// Handle AJAX untuk save nilai
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_nilai') {
    header('Content-Type: application/json');
    
    try {
        $id_siswa = (int)$_POST['id_siswa'];
        $id_kelas = (int)$_POST['id_kelas'];
        $nilai_word = $_POST['nilai_word'] ? (int)$_POST['nilai_word'] : null;
        $nilai_excel = $_POST['nilai_excel'] ? (int)$_POST['nilai_excel'] : null;
        $nilai_ppt = $_POST['nilai_ppt'] ? (int)$_POST['nilai_ppt'] : null;
        $nilai_internet = $_POST['nilai_internet'] ? (int)$_POST['nilai_internet'] : null;
        $nilai_pengembangan = $_POST['nilai_pengembangan'] ? (int)$_POST['nilai_pengembangan'] : null;
        
        // Validasi kelas harus milik instruktur
        $checkKelas = $conn->prepare("SELECT id_kelas FROM kelas WHERE id_kelas = ? AND id_instruktur = ?");
        $checkKelas->bind_param("ii", $id_kelas, $id_instruktur);
        $checkKelas->execute();
        if ($checkKelas->get_result()->num_rows == 0) {
            throw new Exception("Kelas tidak valid atau bukan kelas yang Anda ampu!");
        }
        
        // Hitung rata-rata dari nilai yang ada
        $nilaiArray = array_filter([$nilai_word, $nilai_excel, $nilai_ppt, $nilai_internet, $nilai_pengembangan], function($v) { return $v !== null; });
        $rata_rata = count($nilaiArray) > 0 ? array_sum($nilaiArray) / count($nilaiArray) : null;
        
        // Tentukan status kelulusan
        $status_kelulusan = null;
        if (count($nilaiArray) == 5) { // Semua nilai sudah terisi
            $status_kelulusan = $rata_rata >= 60 ? 'lulus' : 'tidak lulus';
        }
        
        // Cek apakah sudah ada record
        $checkNilai = $conn->prepare("SELECT id_nilai FROM nilai WHERE id_siswa = ? AND id_kelas = ?");
        $checkNilai->bind_param("ii", $id_siswa, $id_kelas);
        $checkNilai->execute();
        $existingNilai = $checkNilai->get_result()->fetch_assoc();
        
        if ($existingNilai) {
            // Update existing record
            $updateQuery = "UPDATE nilai SET 
                           nilai_word = ?, nilai_excel = ?, nilai_ppt = ?, 
                           nilai_internet = ?, nilai_pengembangan = ?, 
                           rata_rata = ?, status_kelulusan = ?
                           WHERE id_nilai = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("iiiiddsi", $nilai_word, $nilai_excel, $nilai_ppt, $nilai_internet, $nilai_pengembangan, $rata_rata, $status_kelulusan, $existingNilai['id_nilai']);
        } else {
            // Insert new record
            $insertQuery = "INSERT INTO nilai (id_siswa, id_kelas, nilai_word, nilai_excel, nilai_ppt, nilai_internet, nilai_pengembangan, rata_rata, status_kelulusan) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iiiiiidds", $id_siswa, $id_kelas, $nilai_word, $nilai_excel, $nilai_ppt, $nilai_internet, $nilai_pengembangan, $rata_rata, $status_kelulusan);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'rata_rata' => $rata_rata, 'status' => $status_kelulusan]);
        } else {
            throw new Exception("Gagal menyimpan nilai: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Pagination settings untuk tab rekap
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Filter untuk tab rekap
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// Query untuk kelas yang diampu instruktur
$kelasQuery = "SELECT k.*, g.nama_gelombang 
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               WHERE k.id_instruktur = ?
               ORDER BY k.nama_kelas";
$kelasStmt = $conn->prepare($kelasQuery);
$kelasStmt->bind_param("i", $id_instruktur);
$kelasStmt->execute();
$kelasResult = $kelasStmt->get_result();

// Query untuk siswa di kelas yang diampu (untuk tab input)
$siswaInputQuery = "SELECT s.*, k.nama_kelas, g.nama_gelombang,
                   n.nilai_word, n.nilai_excel, n.nilai_ppt, n.nilai_internet, n.nilai_pengembangan,
                   n.rata_rata, n.status_kelulusan
                   FROM siswa s
                   JOIN kelas k ON s.id_kelas = k.id_kelas
                   LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                   LEFT JOIN nilai n ON s.id_siswa = n.id_siswa AND s.id_kelas = n.id_kelas
                   WHERE k.id_instruktur = ? AND s.status_aktif = 'aktif'";

if (!empty($filterKelas)) {
    $siswaInputQuery .= " AND k.id_kelas = ?";
}

$siswaInputQuery .= " ORDER BY k.nama_kelas, s.nama";

$siswaInputStmt = $conn->prepare($siswaInputQuery);
if (!empty($filterKelas)) {
    $siswaInputStmt->bind_param("ii", $id_instruktur, $filterKelas);
} else {
    $siswaInputStmt->bind_param("i", $id_instruktur);
}
$siswaInputStmt->execute();
$siswaInputResult = $siswaInputStmt->get_result();

// Count total records untuk pagination rekap
$countQuery = "SELECT COUNT(*) as total FROM nilai n 
               JOIN siswa s ON n.id_siswa = s.id_siswa
               JOIN kelas k ON n.id_kelas = k.id_kelas
               WHERE k.id_instruktur = ? AND s.status_aktif = 'aktif'";
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

// Query untuk tab rekap
$rekapQuery = "SELECT n.*, s.nama as nama_siswa, s.nik, 
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
          END as status_kelulusan_fix
          FROM nilai n 
          JOIN siswa s ON n.id_siswa = s.id_siswa
          JOIN kelas k ON n.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          WHERE k.id_instruktur = ? AND s.status_aktif = 'aktif'";

$rekapParams = [$id_instruktur];

if (!empty($filterKelas)) {
    $rekapQuery .= " AND k.id_kelas = ?";
    $rekapParams[] = $filterKelas;
}

$rekapQuery .= " ORDER BY n.id_nilai DESC LIMIT $recordsPerPage OFFSET $offset";

$rekapStmt = $conn->prepare($rekapQuery);
$types = str_repeat('i', count($rekapParams));
$rekapStmt->bind_param($types, ...$rekapParams);
$rekapStmt->execute();
$rekapResult = $rekapStmt->get_result();

// Statistik nilai untuk instruktur ini
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
               FROM nilai n 
               JOIN siswa s ON n.id_siswa = s.id_siswa
               JOIN kelas k ON n.id_kelas = k.id_kelas
               WHERE k.id_instruktur = ? AND s.status_aktif = 'aktif'";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $id_instruktur);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// Function untuk build URL dengan filter
function buildUrlWithFilters($page = null) {
    global $filterKelas, $activeTab;
    
    $params = [];
    if ($page) $params['page'] = $page;
    if (!empty($filterKelas)) $params['kelas'] = $filterKelas;
    if ($activeTab) $params['tab'] = $activeTab;
    
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Nilai - Instruktur</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
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
            <div class="d-flex align-items-center flex-grow-1">
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <div class="page-info">
                <h2 class="page-title mb-1">KELOLA NILAI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Kelola Nilai</li>
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
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Alert Error -->
        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
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
                  <i class="bi bi-clipboard-data me-2"></i>Kelola Nilai Siswa
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <?php if ($activeTab == 'rekap'): ?>
                <button type="button" 
                        class="btn btn-cetak-soft" 
                        onclick="cetakLaporanPDF()" 
                        id="btnCetakPDF"
                        title="Cetak laporan nilai">
                  <i class="bi bi-printer me-2"></i>Cetak Laporan
                </button>
                <?php endif; ?>
              </div>
            </div>
          </div>

       <div class="p-3 border-bottom">
            <div class="d-flex gap-2">
              <a class="btn <?= $activeTab == 'input' ? 'btn-primary' : 'btn-outline-primary' ?>" 
                 href="?tab=input<?= !empty($filterKelas) ? '&kelas=' . $filterKelas : '' ?>">
                <i class="bi bi-pencil-square me-2"></i>Input Nilai
              </a>
              <a class="btn <?= $activeTab == 'rekap' ? 'btn-primary' : 'btn-outline-primary' ?>" 
                 href="?tab=rekap<?= !empty($filterKelas) ? '&kelas=' . $filterKelas : '' ?>">
                <i class="bi bi-table me-2"></i>Rekap Nilai
              </a>
            </div>
          </div>

          <!-- Filter Kelas -->
          <div class="p-3 border-bottom">
            <form method="GET" id="filterForm">
              <input type="hidden" name="tab" value="<?= $activeTab ?>">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                  <label for="filterKelas" class="mb-0 text-muted small fw-medium">Filter Kelas:</label>
                  <select name="kelas" id="filterKelas" class="form-select form-select-sm" style="width: auto; min-width: 200px;" onchange="document.getElementById('filterForm').submit();">
                    <option value="">Semua Kelas</option>
                    <?php 
                    mysqli_data_seek($kelasResult, 0);
                    while($kelas = $kelasResult->fetch_assoc()): ?>
                      <option value="<?= $kelas['id_kelas'] ?>" <?= ($filterKelas == $kelas['id_kelas']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kelas['nama_kelas']) ?><?php if($kelas['nama_gelombang']): ?> (<?= htmlspecialchars($kelas['nama_gelombang']) ?>)<?php endif; ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
                
                <?php if ($activeTab == 'input'): ?>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-success btn-sm" onclick="saveAllNilai()">
                    <i class="bi bi-check-all me-1"></i>Simpan Semua
                  </button>
                </div>
                <?php endif; ?>
              </div>
            </form>
          </div>

          <!-- Tab Content -->
          <div class="tab-content">
            <!-- Tab Input Nilai -->
            <?php if ($activeTab == 'input'): ?>
            <div class="tab-pane fade show active">
              <div class="table-responsive">
                <table class="custom-table mb-0" id="inputTable">
                  <thead class="sticky-top">
                    <tr>
                      <th rowspan="2" class="align-middle" style="min-width: 180px;">Nama Siswa</th>
                      <th rowspan="2" class="align-middle" style="min-width: 110px;">Kelas</th>
                      <th colspan="5" class="text-center">Nilai Komponen</th>
                      <th rowspan="2" class="align-middle text-center">Rata-rata</th>
                      <th rowspan="2" class="align-middle text-center">Status</th>
                      <th rowspan="2" class="align-middle text-center">Aksi</th>
                    </tr>
                    <tr>
                      <th class="text-center" style="width: 80px;">Word</th>
                      <th class="text-center" style="width: 80px;">Excel</th>
                      <th class="text-center" style="width: 80px;">PPT</th>
                      <th class="text-center" style="width: 80px;">Internet</th>
                      <th class="text-center" style="width: 80px;">Softskill</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($siswaInputResult->num_rows > 0): ?>
                      <?php while ($siswa = $siswaInputResult->fetch_assoc()): ?>
                        <tr data-siswa="<?= $siswa['id_siswa'] ?>" data-kelas="<?= $siswa['id_kelas'] ?>">
                          <!-- Nama Siswa -->
                          <td class="align-middle">
                            <div class="fw-medium"><?= htmlspecialchars($siswa['nama']) ?></div>
                            <small class="text-muted">NIK: <?= htmlspecialchars($siswa['nik']) ?></small>
                          </td>
                          
                          <!-- Kelas -->
                          <td class="align-middle">
                            <span class="badge bg-primary px-2 py-1">
                              <?= htmlspecialchars($siswa['nama_kelas']) ?>
                            </span>
                          </td>
                          
                          <!-- Input Nilai -->
                          <td class="text-center" style="width: 80px;">
                            <input type="number" class="form-control form-control-sm text-center nilai-input" 
                                   name="nilai_word" min="0" max="100" 
                                   value="<?= $siswa['nilai_word'] ?: '' ?>" 
                                   data-komponen="word" style="width: 65px;">
                          </td>
                          <td class="text-center" style="width: 80px;">
                            <input type="number" class="form-control form-control-sm text-center nilai-input" 
                                   name="nilai_excel" min="0" max="100" 
                                   value="<?= $siswa['nilai_excel'] ?: '' ?>" 
                                   data-komponen="excel" style="width: 65px;">
                          </td>
                          <td class="text-center" style="width: 80px;">
                            <input type="number" class="form-control form-control-sm text-center nilai-input" 
                                   name="nilai_ppt" min="0" max="100" 
                                   value="<?= $siswa['nilai_ppt'] ?: '' ?>" 
                                   data-komponen="ppt" style="width: 65px;">
                          </td>
                          <td class="text-center" style="width: 80px;">
                            <input type="number" class="form-control form-control-sm text-center nilai-input" 
                                   name="nilai_internet" min="0" max="100" 
                                   value="<?= $siswa['nilai_internet'] ?: '' ?>" 
                                   data-komponen="internet" style="width: 65px;">
                          </td>
                          <td class="text-center" style="width: 80px;">
                            <input type="number" class="form-control form-control-sm text-center nilai-input" 
                                   name="nilai_pengembangan" min="0" max="100" 
                                   value="<?= $siswa['nilai_pengembangan'] ?: '' ?>" 
                                   data-komponen="pengembangan" style="width: 65px;">
                          </td>
                          
                          <!-- Rata-rata -->
                          <td class="text-center align-middle">
                            <span class="rata-rata-display">
                              <?php if($siswa['rata_rata']): ?>
                                <?php
                                $rata = (float)$siswa['rata_rata'];
                                $badgeClass = 'bg-secondary';
                                if ($rata >= 80) $badgeClass = 'bg-success';
                                elseif ($rata >= 70) $badgeClass = 'bg-primary';
                                elseif ($rata >= 60) $badgeClass = 'bg-warning';
                                else $badgeClass = 'bg-danger';
                                ?>
                                <span class="badge <?= $badgeClass ?> px-2 py-1">
                                  <?= number_format($rata, 1) ?>
                                </span>
                              <?php else: ?>
                                <span class="text-muted">-</span>
                              <?php endif; ?>
                            </span>
                          </td>
                          
                          <!-- Status -->
                          <td class="text-center align-middle">
                            <span class="status-display">
                              <?php 
                              $status = $siswa['status_kelulusan'];
                              if($status == 'lulus'): ?>
                                <span class="badge bg-success px-2 py-1">Lulus</span>
                              <?php elseif($status == 'tidak lulus'): ?>
                                <span class="badge bg-danger px-2 py-1">Tidak Lulus</span>
                              <?php else: ?>
                                <span class="badge bg-warning px-2 py-1">Belum Lengkap</span>
                              <?php endif; ?>
                            </span>
                          </td>
                          
                          <!-- Aksi -->
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-sm btn-success save-row" 
                                    onclick="saveNilaiSiswa(<?= $siswa['id_siswa'] ?>, <?= $siswa['id_kelas'] ?>)">
                              <i class="bi bi-check"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="10" class="text-center">
                          <div class="empty-state py-5">
                            <i class="bi bi-people display-4 text-muted mb-3 d-block"></i>
                            <h5>Belum Ada Siswa</h5>
                            <p class="mb-3 text-muted">
                              <?php if (!empty($filterKelas)): ?>
                                Tidak ada siswa aktif di kelas yang dipilih
                              <?php else: ?>
                                Belum ada siswa aktif di kelas yang Anda ampu
                              <?php endif; ?>
                            </p>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php endif; ?>

            <!-- Tab Rekap Nilai -->
            <?php if ($activeTab == 'rekap'): ?>
            <div class="tab-pane fade show active">
              <!-- Search/Filter Controls untuk Rekap -->
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
                          <span class="info-label">data</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Table Rekap -->
              <div class="table-responsive">
                <table class="custom-table mb-0" id="rekapTable">
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
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($rekapResult && $rekapResult->num_rows > 0): ?>
                      <?php 
                      $no = ($currentPage - 1) * $recordsPerPage + 1;
                      while ($nilai = $rekapResult->fetch_assoc()): 
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
                            <span class="badge bg-primary px-2 py-1">
                              <i class="bi bi-building me-1"></i>
                              <?= htmlspecialchars($nilai['nama_kelas']) ?>
                            </span>
                            <?php if($nilai['nama_gelombang']): ?>
                              <br><small class="text-muted"><?= htmlspecialchars($nilai['nama_gelombang']) ?></small>
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
                            <?php if($nilai['rata_rata']): ?>
                              <?php
                              $rata = (float)$nilai['rata_rata'];
                              $badgeClass = 'bg-secondary';
                              if ($rata >= 80) $badgeClass = 'bg-success';
                              elseif ($rata >= 70) $badgeClass = 'bg-primary';
                              elseif ($rata >= 60) $badgeClass = 'bg-warning';
                              else $badgeClass = 'bg-danger';
                              ?>
                              <span class="badge <?= $badgeClass ?> px-2 py-1">
                                <?= number_format($rata, 1) ?>
                              </span>
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
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="12" class="text-center">
                          <div class="empty-state py-5">
                            <i class="bi bi-clipboard-data display-4 text-muted mb-3 d-block"></i>
                            <h5>Belum Ada Data Nilai</h5>
                            <p class="mb-3 text-muted">
                              <?php if (!empty($filterKelas)): ?>
                                Belum ada nilai untuk kelas yang dipilih
                              <?php else: ?>
                                Mulai input nilai siswa di tab "Input Nilai"
                              <?php endif; ?>
                            </p>
                            <a href="?tab=input<?= !empty($filterKelas) ? '&kelas=' . $filterKelas : '' ?>" class="btn btn-primary">
                              <i class="bi bi-pencil-square me-2"></i>Mulai Input Nilai
                            </a>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Pagination untuk Rekap -->
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
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  // Fungsi untuk menyimpan nilai siswa individual
  function saveNilaiSiswa(idSiswa, idKelas) {
    const row = document.querySelector(`tr[data-siswa="${idSiswa}"]`);
    const inputs = row.querySelectorAll('.nilai-input');
    const saveBtn = row.querySelector('.save-row');
    
    // Disable button dan show loading
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    
    const formData = new FormData();
    formData.append('action', 'save_nilai');
    formData.append('id_siswa', idSiswa);
    formData.append('id_kelas', idKelas);
    
    inputs.forEach(input => {
      const komponen = input.dataset.komponen;
      const value = input.value.trim();
      formData.append(`nilai_${komponen}`, value);
    });
    
    fetch('', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update rata-rata display
        const rataDisplay = row.querySelector('.rata-rata-display');
        const statusDisplay = row.querySelector('.status-display');
        
        if (data.rata_rata) {
          let badgeClass = 'bg-secondary';
          if (data.rata_rata >= 80) badgeClass = 'bg-success';
          else if (data.rata_rata >= 70) badgeClass = 'bg-primary';
          else if (data.rata_rata >= 60) badgeClass = 'bg-warning';
          else badgeClass = 'bg-danger';
          
          rataDisplay.innerHTML = `<span class="badge ${badgeClass} px-2 py-1">${parseFloat(data.rata_rata).toFixed(1)}</span>`;
        } else {
          rataDisplay.innerHTML = '<span class="text-muted">-</span>';
        }
        
        // Update status display
        if (data.status == 'lulus') {
          statusDisplay.innerHTML = '<span class="badge bg-success px-2 py-1">Lulus</span>';
        } else if (data.status == 'tidak lulus') {
          statusDisplay.innerHTML = '<span class="badge bg-danger px-2 py-1">Tidak Lulus</span>';
        } else {
          statusDisplay.innerHTML = '<span class="badge bg-warning px-2 py-1">Belum Lengkap</span>';
        }
        
        // Mark inputs as saved
        inputs.forEach(input => {
          input.classList.remove('is-invalid');
          input.classList.add('is-valid');
        });
        
        Swal.fire({
          title: 'Berhasil!',
          text: 'Nilai berhasil disimpan',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        });
        
        // Reset valid class after delay
        setTimeout(() => {
          inputs.forEach(input => {
            input.classList.remove('is-valid');
          });
        }, 3000);
        
      } else {
        Swal.fire({
          title: 'Error!',
          text: data.message || 'Gagal menyimpan nilai',
          icon: 'error'
        });
        
        inputs.forEach(input => {
          input.classList.add('is-invalid');
        });
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Swal.fire({
        title: 'Error!',
        text: 'Terjadi kesalahan koneksi',
        icon: 'error'
      });
    })
    .finally(() => {
      // Reset button
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="bi bi-check"></i>';
    });
  }
  
  // Fungsi untuk menyimpan semua perubahan
  function saveAllNilai() {
    const rows = document.querySelectorAll('#inputTable tbody tr[data-siswa]');
    let totalRows = rows.length;
    let savedRows = 0;
    let hasError = false;
    
    if (totalRows === 0) {
      Swal.fire({
        title: 'Tidak Ada Data',
        text: 'Tidak ada siswa untuk disimpan',
        icon: 'info'
      });
      return;
    }
    
    Swal.fire({
      title: 'Menyimpan Data...',
      text: `Menyimpan nilai untuk ${totalRows} siswa`,
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
    
    rows.forEach((row, index) => {
      const idSiswa = row.dataset.siswa;
      const idKelas = row.dataset.kelas;
      const inputs = row.querySelectorAll('.nilai-input');
      
      const formData = new FormData();
      formData.append('action', 'save_nilai');
      formData.append('id_siswa', idSiswa);
      formData.append('id_kelas', idKelas);
      
      inputs.forEach(input => {
        const komponen = input.dataset.komponen;
        const value = input.value.trim();
        formData.append(`nilai_${komponen}`, value);
      });
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        savedRows++;
        
        if (data.success) {
          // Update displays (sama seperti saveNilaiSiswa)
          const rataDisplay = row.querySelector('.rata-rata-display');
          const statusDisplay = row.querySelector('.status-display');
          
          if (data.rata_rata) {
            let badgeClass = 'bg-secondary';
            if (data.rata_rata >= 80) badgeClass = 'bg-success';
            else if (data.rata_rata >= 70) badgeClass = 'bg-primary';
            else if (data.rata_rata >= 60) badgeClass = 'bg-warning';
            else badgeClass = 'bg-danger';
            
            rataDisplay.innerHTML = `<span class="badge ${badgeClass} px-2 py-1">${parseFloat(data.rata_rata).toFixed(1)}</span>`;
          }
          
          if (data.status == 'lulus') {
            statusDisplay.innerHTML = '<span class="badge bg-success px-2 py-1">Lulus</span>';
          } else if (data.status == 'tidak lulus') {
            statusDisplay.innerHTML = '<span class="badge bg-danger px-2 py-1">Tidak Lulus</span>';
          } else {
            statusDisplay.innerHTML = '<span class="badge bg-warning px-2 py-1">Belum Lengkap</span>';
          }
        } else {
          hasError = true;
        }
        
        // Check if all done
        if (savedRows === totalRows) {
          Swal.close();
          
          if (hasError) {
            Swal.fire({
              title: 'Sebagian Berhasil',
              text: 'Beberapa nilai berhasil disimpan, tapi ada yang gagal',
              icon: 'warning'
            });
          } else {
            Swal.fire({
              title: 'Semua Berhasil!',
              text: `${totalRows} nilai siswa berhasil disimpan`,
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
          }
        }
      })
      .catch(error => {
        savedRows++;
        hasError = true;
        console.error('Error:', error);
        
        if (savedRows === totalRows) {
          Swal.close();
          Swal.fire({
            title: 'Ada Kesalahan',
            text: 'Terjadi kesalahan saat menyimpan beberapa nilai',
            icon: 'error'
          });
        }
      });
    });
  }
  
  // Fungsi cetak laporan (TANPA LOADING LAMA)
  function cetakLaporanPDF() {
    const btnCetak = document.getElementById('btnCetakPDF');
    
    // Validasi kelas terpilih
    const filterKelas = '<?= $filterKelas ?>';
    if (!filterKelas) {
      Swal.fire({
        title: 'Pilih Kelas!',
        text: 'Silakan pilih kelas terlebih dahulu untuk mencetak laporan',
        icon: 'warning'
      });
      return;
    }
    
    // Disable button sementara
    btnCetak.disabled = true;
    btnCetak.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Membuka PDF...';
    
    // Build URL
    const url = `cetak_laporan.php?kelas=${encodeURIComponent(filterKelas)}`;
    
    // Langsung buka window tanpa delay lama
    const pdfWindow = window.open(url, '_blank');
    
    // Reset button dengan delay minimal
    setTimeout(() => {
      btnCetak.disabled = false;
      btnCetak.innerHTML = '<i class="bi bi-printer me-2"></i>Cetak Laporan';
      
      // Check popup blocker
      if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed == 'undefined') {
        Swal.fire({
          title: 'Pop-up Diblokir!',
          text: 'Browser memblokir pop-up. Silakan izinkan popup untuk mengunduh laporan PDF.',
          icon: 'warning',
          confirmButtonText: 'OK'
        });
      }
    }, 500);
  }
  
  // Search functionality untuk tab rekap
  document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('rekapTable')) {
      const searchInput = document.getElementById('searchInput');
      const table = document.getElementById('rekapTable');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('.empty-state'));
      
      let searchTimeout;
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            const searchTerm = this.value.toLowerCase().trim();
            
            rows.forEach(row => {
              const namaSiswa = (row.cells[1]?.textContent || '').toLowerCase();
              const kelas = (row.cells[2]?.textContent || '').toLowerCase();
              
              const showRow = !searchTerm || 
                             namaSiswa.includes(searchTerm) || 
                             kelas.includes(searchTerm);
              
              row.style.display = showRow ? '' : 'none';
            });
            
            // Update row numbers
            let counter = <?= ($currentPage - 1) * $recordsPerPage + 1 ?>;
            rows.forEach(row => {
              if (row.style.display !== 'none') {
                row.cells[0].textContent = counter++;
              }
            });
          }, 300);
        });
      }
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
  
  // Validasi input nilai
  document.addEventListener('DOMContentLoaded', function() {
    const nilaiInputs = document.querySelectorAll('.nilai-input');
    
    nilaiInputs.forEach(input => {
      input.addEventListener('input', function() {
        let value = parseInt(this.value);
        
        // Validasi range 0-100
        if (value < 0) {
          this.value = 0;
        } else if (value > 100) {
          this.value = 100;
        }
        
        // Mark as changed
        this.classList.remove('is-valid', 'is-invalid');
        this.style.borderColor = '#ffc107'; // warning color
      });
      
      input.addEventListener('blur', function() {
        this.style.borderColor = '';
      });
    });
  });
  </script>
</body>
</html>