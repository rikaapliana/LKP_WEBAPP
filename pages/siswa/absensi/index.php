<?php
session_start();
require_once '../../../includes/auth.php';
requireSiswaAuth(); // Hanya siswa yang bisa akses

include '../../../includes/db.php';
$activePage = 'absensi'; 
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
$filterPeriode = isset($_GET['filter_periode']) ? $_GET['filter_periode'] : 'all';
$filterStatus = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// Build WHERE clause for filters
$whereConditions = ["a.id_siswa = ?"];
$params = [$siswaData['id_siswa']];

if (!empty($filterStatus)) {
    $whereConditions[] = "a.status = ?";
    $params[] = $filterStatus;
}

$today = date('Y-m-d');
switch($filterPeriode) {
    case 'month':
        $startOfMonth = date('Y-m-01');
        $endOfMonth = date('Y-m-t');
        $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
        $params[] = $startOfMonth;
        $params[] = $endOfMonth;
        break;
    case 'week':
        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
        $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
        $params[] = $startOfWeek;
        $params[] = $endOfWeek;
        break;
    case 'all':
    default:
        // Tidak ada filter periode
        break;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
$orderClause = "ORDER BY j.tanggal DESC, j.waktu_mulai DESC";

// Get absensi data dengan join ke jadwal dan instruktur
$query = "SELECT a.status, a.waktu_absen,
          j.tanggal, j.waktu_mulai, j.waktu_selesai,
          k.nama_kelas,
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
          FROM absensi_siswa a
          JOIN jadwal j ON a.id_jadwal = j.id_jadwal
          JOIN kelas k ON j.id_kelas = k.id_kelas
          JOIN instruktur i ON j.id_instruktur = i.id_instruktur
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

// Statistik absensi untuk siswa
$statsQuery = "SELECT 
    COUNT(*) as total_pertemuan,
    SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as total_hadir,
    SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) as total_izin,
    SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) as total_sakit,
    SUM(CASE WHEN a.status = 'tanpa keterangan' THEN 1 ELSE 0 END) as total_alpha
    FROM absensi_siswa a
    JOIN jadwal j ON a.id_jadwal = j.id_jadwal
    WHERE a.id_siswa = ?";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $siswaData['id_siswa']);
$statsStmt->execute();
$statsData = $statsStmt->get_result()->fetch_assoc();

// Hitung persentase kehadiran
$persentaseKehadiran = 0;
if ($statsData['total_pertemuan'] > 0) {
    $persentaseKehadiran = round(($statsData['total_hadir'] / $statsData['total_pertemuan']) * 100, 1);
}

// Function untuk badge status
function getStatusBadge($status) {
    switch($status) {
        case 'hadir':
            return '<span class="badge bg-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>Hadir</span>';
        case 'izin':
            return '<span class="badge bg-warning px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i>Izin</span>';
        case 'sakit':
            return '<span class="badge bg-info px-2 py-1"><i class="bi bi-heart-pulse me-1"></i>Sakit</span>';
        case 'tanpa keterangan':
            return '<span class="badge bg-danger px-2 py-1"><i class="bi bi-x-circle me-1"></i>Alpha</span>';
        default:
            return '<span class="badge bg-secondary px-2 py-1">-</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat Absensi - Siswa</title>
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
                <h2 class="page-title mb-1">RIWAYAT ABSENSI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Absensi Kelas</li>
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
            <!-- Absensi Card -->
            <div class="card content-card">
              <div class="section-header">
                <div class="row align-items-center">
                  <div class="col-md-6">
                    <h5 class="mb-0 text-dark">
                      <i class="bi bi-calendar-check me-2"></i>Riwayat Absensi Saya
                    </h5>
                  </div>
                </div>
              </div>

              <!-- Filter Controls -->
              <div class="p-3 border-bottom">
                <form method="GET" id="filterForm">
                  <div class="row align-items-center">
                    <div class="col-md-8">
                      <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center">
                          <label for="filterPeriode" class="me-2 mb-0">
                            <small class="text-muted">Periode:</small>
                          </label>
                          <select class="form-select form-select-sm" name="filter_periode" id="filterPeriode" style="width: auto;">
                            <option value="all" <?= ($filterPeriode == 'all') ? 'selected' : '' ?>>Semua</option>
                            <option value="month" <?= ($filterPeriode == 'month') ? 'selected' : '' ?>>Bulan Ini</option>
                            <option value="week" <?= ($filterPeriode == 'week') ? 'selected' : '' ?>>Minggu Ini</option>
                          </select>
                        </div>
                        
                        <div class="d-flex align-items-center">
                          <label for="filterStatus" class="me-2 mb-0">
                            <small class="text-muted">Status:</small>
                          </label>
                          <select class="form-select form-select-sm" name="filter_status" id="filterStatus" style="width: auto;">
                            <option value="" <?= empty($filterStatus) ? 'selected' : '' ?>>Semua Status</option>
                            <option value="hadir" <?= ($filterStatus == 'hadir') ? 'selected' : '' ?>>Hadir</option>
                            <option value="izin" <?= ($filterStatus == 'izin') ? 'selected' : '' ?>>Izin</option>
                            <option value="sakit" <?= ($filterStatus == 'sakit') ? 'selected' : '' ?>>Sakit</option>
                            <option value="tanpa keterangan" <?= ($filterStatus == 'tanpa keterangan') ? 'selected' : '' ?>>Alpha</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

              <!-- Absensi Table -->
              <div class="table-responsive">
                <table class="custom-table mb-0">
                  <thead>
                    <tr>
                      <th width="5%">No</th>
                      <th width="15%">Tanggal</th>
                      <th width="12%">Hari</th>
                      <th width="20%">Waktu</th>
                      <th width="25%">Instruktur</th>
                      <th width="15%">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                      <?php $no = 1; ?>
                      <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                        $tanggalJadwal = strtotime($row['tanggal']);
                        $today = strtotime(date('Y-m-d'));
                        $isToday = $tanggalJadwal == $today;
                        $isPast = $tanggalJadwal < $today;
                        ?>
                        <tr class="<?= $isToday ? 'table-primary' : '' ?>">
                          <td class="align-middle">
                            <span class="fw-medium text-muted"><?= $no++ ?></span>
                          </td>
                          
                          <td class="align-middle">
                            <div class="fw-medium <?= $isToday ? 'text-primary' : ($isPast ? 'text-muted' : '') ?>">
                              <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                            </div>
                          </td>
                          
                          <td class="align-middle">
                            <span class="<?= $isToday ? 'text-primary fw-bold' : '' ?>">
                              <?= htmlspecialchars($row['hari_indonesia']) ?>
                            </span>
                          </td>
                          
                          <td class="align-middle">
                            <div class="<?= $isToday ? 'text-primary fw-bold' : '' ?>">
                              <i class="bi bi-clock me-1"></i>
                              <?= date('H:i', strtotime($row['waktu_mulai'])) ?> - 
                              <?= date('H:i', strtotime($row['waktu_selesai'])) ?>
                            </div>
                          </td>
                          
                          <td class="align-middle">
                            <div class="d-flex align-items-center">
                              <i class="bi bi-person-circle me-2 text-muted"></i>
                              <span><?= htmlspecialchars($row['nama_instruktur']) ?></span>
                            </div>
                          </td>
                          
                          <td class="align-middle">
                            <?= getStatusBadge($row['status']) ?>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="6" class="text-center">
                          <div class="empty-state py-5">
                            <i class="bi bi-calendar-x display-4 text-muted mb-3 d-block"></i>
                            <h5>Tidak Ada Data Absensi</h5>
                            <p class="mb-3 text-muted">
                              <?php if (!empty($filterStatus) || $filterPeriode != 'all'): ?>
                                Tidak ada data absensi pada filter yang dipilih
                              <?php else: ?>
                                Belum ada riwayat absensi tersedia
                              <?php endif; ?>
                            </p>
                            <a href="?" class="btn btn-primary">
                              <i class="bi bi-arrow-clockwise me-2"></i>Lihat Semua Data
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
            <!-- Statistik Kehadiran -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-bar-chart me-2"></i>Statistik Kehadiran
                </h6>
              </div>
              <div class="card-body">
                <div class="text-center mb-3">
                  <div class="display-6 fw-bold text-primary"><?= $persentaseKehadiran ?>%</div>
                  <small class="text-muted">Tingkat Kehadiran</small>
                </div>
                
                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                      <i class="bi bi-check-circle text-success me-2"></i>
                      <span>Hadir</span>
                    </div>
                    <strong class="text-success"><?= $statsData['total_hadir'] ?></strong>
                  </div>
                  
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                      <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                      <span>Izin</span>
                    </div>
                    <strong class="text-warning"><?= $statsData['total_izin'] ?></strong>
                  </div>
                  
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center">
                      <i class="bi bi-heart-pulse text-info me-2"></i>
                      <span>Sakit</span>
                    </div>
                    <strong class="text-info"><?= $statsData['total_sakit'] ?></strong>
                  </div>
                  
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                      <i class="bi bi-x-circle text-danger me-2"></i>
                      <span>Alpha</span>
                    </div>
                    <strong class="text-danger"><?= $statsData['total_alpha'] ?></strong>
                  </div>
                </div>
                
                <hr>
                
                <div class="text-center">
                  <small class="text-muted">
                    Total Pertemuan: <strong><?= $statsData['total_pertemuan'] ?></strong>
                  </small>
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
    const filterStatus = document.getElementById('filterStatus');
    
    // Auto submit when filter changes
    if (filterPeriode) {
      filterPeriode.addEventListener('change', function() {
        form.submit();
      });
    }
    
    if (filterStatus) {
      filterStatus.addEventListener('change', function() {
        form.submit();
      });
    }
  });
  </script>
</body>
</html>