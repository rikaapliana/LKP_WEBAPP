<?php
session_start();
require_once '../../../includes/auth.php';
requireSiswaAuth(); // Hanya siswa yang bisa akses

include '../../../includes/db.php';
$activePage = 'jadwal'; 
$baseURL = '../';

// Ambil data siswa yang sedang login
$stmt = $conn->prepare("SELECT s.*, k.nama_kelas, g.nama_gelombang, i.nama as nama_instruktur 
                       FROM siswa s 
                       LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
                       LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                       LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
                       WHERE s.id_user = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$siswaData = $stmt->get_result()->fetch_assoc();

if (!$siswaData || !$siswaData['id_kelas']) {
    $_SESSION['error'] = "Data siswa atau kelas tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit();
}

// Filter parameters
$filterPeriode = isset($_GET['filter_periode']) ? $_GET['filter_periode'] : 'week';
$filterTanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';

// Build WHERE clause for filters
$whereConditions = ["j.id_kelas = ?"];
$params = [$siswaData['id_kelas']];

$today = date('Y-m-d');

if (!empty($filterTanggal)) {
    $whereConditions[] = "j.tanggal = ?";
    $params[] = $filterTanggal;
} else {
    switch($filterPeriode) {
        case 'today':
            $whereConditions[] = "j.tanggal = ?";
            $params[] = $today;
            break;
        case 'week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
            $params[] = $startOfWeek;
            $params[] = $endOfWeek;
            break;
        case 'month':
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
            $params[] = $startOfMonth;
            $params[] = $endOfMonth;
            break;
        case 'upcoming':
            $whereConditions[] = "j.tanggal >= ?";
            $params[] = $today;
            break;
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
$orderClause = "ORDER BY j.tanggal ASC, j.waktu_mulai ASC";

// Get jadwal data
$query = "SELECT j.*, 
          i.nama as nama_instruktur,
          CASE DAYNAME(j.tanggal)
            WHEN 'Monday' THEN 'Senin'
            WHEN 'Tuesday' THEN 'Selasa' 
            WHEN 'Wednesday' THEN 'Rabu'
            WHEN 'Thursday' THEN 'Kamis'
            WHEN 'Friday' THEN 'Jumat'
            WHEN 'Saturday' THEN 'Sabtu'
            WHEN 'Sunday' THEN 'Minggu'
          END as hari_indonesia
          FROM jadwal j 
          LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
          $whereClause
          $orderClause";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    $result = false;
}

// Statistik jadwal untuk siswa
$jadwalHariIniQuery = "SELECT COUNT(*) as total FROM jadwal WHERE id_kelas = ? AND tanggal = ?";
$jadwalHariIniStmt = $conn->prepare($jadwalHariIniQuery);
$jadwalHariIniStmt->bind_param("is", $siswaData['id_kelas'], $today);
$jadwalHariIniStmt->execute();
$jadwalHariIni = $jadwalHariIniStmt->get_result()->fetch_assoc()['total'];

$jadwalMingguIniQuery = "SELECT COUNT(*) as total FROM jadwal WHERE id_kelas = ? AND tanggal BETWEEN ? AND ?";
$jadwalMingguIniStmt = $conn->prepare($jadwalMingguIniQuery);
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$endOfWeek = date('Y-m-d', strtotime('sunday this week'));
$jadwalMingguIniStmt->bind_param("iss", $siswaData['id_kelas'], $startOfWeek, $endOfWeek);
$jadwalMingguIniStmt->execute();
$jadwalMingguIni = $jadwalMingguIniStmt->get_result()->fetch_assoc()['total'];

$jadwalAkanDatangQuery = "SELECT COUNT(*) as total FROM jadwal WHERE id_kelas = ? AND tanggal > ?";
$jadwalAkanDatangStmt = $conn->prepare($jadwalAkanDatangQuery);
$jadwalAkanDatangStmt->bind_param("is", $siswaData['id_kelas'], $today);
$jadwalAkanDatangStmt->execute();
$jadwalAkanDatang = $jadwalAkanDatangStmt->get_result()->fetch_assoc()['total'];

// Function to build URL with filters
function buildUrlWithFilters() {
    global $filterPeriode, $filterTanggal;
    
    $params = [];
    if (!empty($filterPeriode)) $params['filter_periode'] = $filterPeriode;
    if (!empty($filterTanggal)) $params['filter_tanggal'] = $filterTanggal;
    
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jadwal Kelas - Siswa</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/siswa.php'; ?>
    
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
                <h2 class="page-title mb-1">JADWAL KELAS</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Jadwal Kelas</li>
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

        <div class="row">
          <!-- Main Content -->
          <div class="col-lg-8">
            <!-- Jadwal Card -->
            <div class="card content-card">
              <div class="section-header">
                <div class="row align-items-center">
                  <div class="col-md-6">
                    <h5 class="mb-0 text-dark">
                      <i class="bi bi-calendar-event me-2"></i>Jadwal Kelas Saya
                    </h5>
                  </div>
                </div>
              </div>

              <!-- Filter Controls -->
              <div class="p-3 border-bottom">
                <form method="GET" id="filterForm">
                  <div class="row align-items-center">
                    <div class="col-md-6">
                      <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center">
                          <label for="filterPeriode" class="me-2 mb-0">
                            <small class="text-muted">Tampilkan:</small>
                          </label>
                          <select class="form-select form-select-sm" name="filter_periode" id="filterPeriode" style="width: auto;">
                            <option value="today" <?= ($filterPeriode == 'today') ? 'selected' : '' ?>>Hari Ini</option>
                            <option value="week" <?= ($filterPeriode == 'week') ? 'selected' : '' ?>>Minggu Ini</option>
                            <option value="month" <?= ($filterPeriode == 'month') ? 'selected' : '' ?>>Bulan Ini</option>
                            <option value="upcoming" <?= ($filterPeriode == 'upcoming') ? 'selected' : '' ?>>Yang Akan Datang</option>
                          </select>
                        </div>
                        
                        <div class="d-flex align-items-center">
                          <label for="filterTanggal" class="me-2 mb-0">
                            <small class="text-muted">Tanggal:</small>
                          </label>
                          <input type="date" name="filter_tanggal" id="filterTanggal" class="form-control form-control-sm" style="width: 150px;" value="<?= htmlspecialchars($filterTanggal) ?>" />
                        </div>
                      </div>
                     </div>
                  </div>
                </form>
              </div>

              <!-- Jadwal Table -->
              <div class="table-responsive">
                <table class="custom-table mb-0">
                  <thead>
                    <tr>
                      <th width="15%">Tanggal</th>
                      <th width="12%">Hari</th>
                      <th width="20%">Waktu</th>
                      <th width="25%">Instruktur</th>
                      <th width="15%">Status</th>
                      <th width="13%">Waktu Tersisa</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                      <?php while ($jadwal = mysqli_fetch_assoc($result)): ?>
                        <?php
                        $tanggalJadwal = strtotime($jadwal['tanggal']);
                        $today = strtotime(date('Y-m-d'));
                        $isToday = $tanggalJadwal == $today;
                        $isPast = $tanggalJadwal < $today;
                        $isUpcoming = $tanggalJadwal > $today;
                        
                        // Hitung waktu tersisa
                        $now = time();
                        $jadwalTime = strtotime($jadwal['tanggal'] . ' ' . $jadwal['waktu_mulai']);
                        $timeDiff = $jadwalTime - $now;
                        ?>
                        <tr class="<?= $isToday ? 'table-primary' : '' ?>">
                          <td class="align-middle">
                            <div class="fw-medium <?= $isToday ? 'text-primary' : ($isPast ? 'text-muted' : '') ?>">
                              <?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?>
                            </div>
                          </td>
                          
                          <td class="align-middle">
                            <span class="<?= $isToday ? 'text-primary fw-bold' : '' ?>">
                              <?= htmlspecialchars($jadwal['hari_indonesia']) ?>
                            </span>
                          </td>
                          
                          <td class="align-middle">
                            <div class="<?= $isToday ? 'text-primary fw-bold' : '' ?>">
                              <i class="bi bi-clock me-1"></i>
                              <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - 
                              <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?>
                            </div>
                          </td>
                          
                          <td class="align-middle">
                            <div class="d-flex align-items-center">
                              <i class="bi bi-person-circle me-2 text-muted"></i>
                              <span><?= htmlspecialchars($jadwal['nama_instruktur']) ?></span>
                            </div>
                          </td>
                          
                          <td class="align-middle">
                            <?php if($isToday): ?>
                              <span class="badge bg-primary px-2 py-1">
                                <i class="bi bi-clock me-1"></i>Hari Ini
                              </span>
                            <?php elseif($isPast): ?>
                              <span class="badge bg-success px-2 py-1">
                                <i class="bi bi-check-circle me-1"></i>Selesai
                              </span>
                            <?php else: ?>
                              <span class="badge bg-secondary px-2 py-1">
                                <i class="bi bi-calendar-check me-1"></i>Terjadwal
                              </span>
                            <?php endif; ?>
                          </td>
                          
                          <td class="align-middle">
                            <?php if($isPast): ?>
                              <small class="text-muted">-</small>
                            <?php elseif($isToday && $timeDiff > 0): ?>
                              <small class="text-primary fw-medium">
                                <?php
                                $hours = floor($timeDiff / 3600);
                                $minutes = floor(($timeDiff % 3600) / 60);
                                if($hours > 0) {
                                  echo $hours . "j " . $minutes . "m";
                                } else {
                                  echo $minutes . " menit";
                                }
                                ?>
                              </small>
                            <?php elseif($isToday && $timeDiff <= 0): ?>
                              <small class="text-warning fw-bold">Berlangsung</small>
                            <?php else: ?>
                              <small class="text-muted">
                                <?php
                                $daysDiff = ceil($timeDiff / (24 * 3600));
                                echo $daysDiff . " hari";
                                ?>
                              </small>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="6" class="text-center">
                          <div class="empty-state py-5">
                            <i class="bi bi-calendar-x display-4 text-muted mb-3 d-block"></i>
                            <h5>Tidak Ada Jadwal</h5>
                            <p class="mb-3 text-muted">
                              <?php if (!empty($filterTanggal) || $filterPeriode != 'week'): ?>
                                Tidak ada jadwal pada periode yang dipilih
                              <?php else: ?>
                                Belum ada jadwal kelas yang tersedia
                              <?php endif; ?>
                            </p>
                            <a href="?" class="btn btn-primary">
                              <i class="bi bi-arrow-clockwise me-2"></i>Lihat Semua Jadwal
                            </a>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Sidebar Info -->
          <div class="col-lg-4">
            

            <!-- Info Kelas -->
            <div class="card content-card">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Informasi Kelas
                </h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <small class="text-muted d-block">Nama Kelas</small>
                  <strong><?= htmlspecialchars($siswaData['nama_kelas']) ?></strong>
                </div>
                
                <?php if($siswaData['nama_gelombang']): ?>
                <div class="mb-3">
                  <small class="text-muted d-block">Gelombang</small>
                  <strong><?= htmlspecialchars($siswaData['nama_gelombang']) ?></strong>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                  <small class="text-muted d-block">Instruktur</small>
                  <strong><?= htmlspecialchars($siswaData['nama_instruktur']) ?></strong>
                </div>

                <hr>
                
                <div class="d-grid">
                  <a href="../dashboard.php" class="btn btn-kembali btn-sm">
                    Kembali ke Dashboard
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const filterPeriode = document.getElementById('filterPeriode');
    const filterTanggal = document.getElementById('filterTanggal');
    
    // Auto submit when filter changes
    if (filterPeriode) {
      filterPeriode.addEventListener('change', function() {
        form.submit();
      });
    }
    
    if (filterTanggal) {
      filterTanggal.addEventListener('change', function() {
        form.submit();
      });
    }
    
    // Update waktu tersisa setiap menit untuk jadwal hari ini
    setInterval(function() {
      const rows = document.querySelectorAll('tr.table-primary');
      rows.forEach(function(row) {
        // Logic untuk update countdown bisa ditambahkan di sini
        // Untuk sekarang, reload halaman setiap 5 menit jika ada jadwal hari ini
      });
    }, 300000); // 5 menit
  });
  </script>
</body>
</html>