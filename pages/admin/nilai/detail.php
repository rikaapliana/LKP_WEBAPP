<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'nilai'; 
$baseURL = '../';

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID nilai tidak valid!";
    header("Location: index.php");
    exit;
}

$id_nilai = (int)$_GET['id'];

// Ambil data nilai dengan join ke tabel siswa, kelas, gelombang, dan instruktur
$query = "SELECT n.*, 
          s.nama as nama_siswa, s.nik, s.email, s.no_hp, s.alamat_lengkap as alamat, s.tanggal_lahir, s.tempat_lahir, s.jenis_kelamin, s.pendidikan_terakhir,
          k.nama_kelas, k.kapasitas,
          g.nama_gelombang, g.tahun as tahun_gelombang, g.gelombang_ke,
          i.nama as nama_instruktur, i.nik as nik_instruktur
          FROM nilai n 
          LEFT JOIN siswa s ON n.id_siswa = s.id_siswa
          LEFT JOIN kelas k ON n.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
          WHERE n.id_nilai = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_nilai);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Data nilai tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$nilai = mysqli_fetch_assoc($result);

// Hitung jumlah siswa di kelas yang sama
$total_siswa_kelas = 0;
if ($nilai['id_kelas']) {
    $siswaQuery = "SELECT COUNT(*) as total FROM siswa WHERE id_kelas = ? AND status_aktif = 'aktif'";
    $siswaStmt = mysqli_prepare($conn, $siswaQuery);
    mysqli_stmt_bind_param($siswaStmt, "i", $nilai['id_kelas']);
    mysqli_stmt_execute($siswaStmt);
    $siswaResult = mysqli_stmt_get_result($siswaStmt);
    $siswaData = mysqli_fetch_assoc($siswaResult);
    $total_siswa_kelas = $siswaData['total'];
    mysqli_stmt_close($siswaStmt);
}

// Hitung rata-rata kelas untuk perbandingan
$rata_rata_kelas = 0;
if ($nilai['id_kelas']) {
    $avgQuery = "SELECT AVG(rata_rata) as avg_kelas FROM nilai WHERE id_kelas = ? AND rata_rata IS NOT NULL";
    $avgStmt = mysqli_prepare($conn, $avgQuery);
    mysqli_stmt_bind_param($avgStmt, "i", $nilai['id_kelas']);
    mysqli_stmt_execute($avgStmt);
    $avgResult = mysqli_stmt_get_result($avgStmt);
    $avgData = mysqli_fetch_assoc($avgResult);
    $rata_rata_kelas = $avgData['avg_kelas'] ?? 0;
    mysqli_stmt_close($avgStmt);
}

// Array nilai untuk chart
$nilai_chart = [
    'Word' => $nilai['nilai_word'] ?? 0,
    'Excel' => $nilai['nilai_excel'] ?? 0,
    'PowerPoint' => $nilai['nilai_ppt'] ?? 0,
    'Internet' => $nilai['nilai_internet'] ?? 0,
    'Softskill' => $nilai['nilai_pengembangan'] ?? 0
];

// Filter nilai yang ada (tidak null/kosong)
$nilai_terisi = array_filter($nilai_chart, function($val) {
    return $val !== null && $val !== 0;
});

// Kategori berdasarkan rata-rata keseluruhan
function getKategoriRataRata($rataRata) {
    if ($rataRata >= 85) return ['Sangat Baik', 'success'];
    if ($rataRata >= 75) return ['Baik', 'primary'];
    if ($rataRata >= 65) return ['Cukup', 'info'];
    if ($rataRata >= 60) return ['Kurang Baik', 'warning'];
    return ['Perlu Perbaikan', 'danger'];
}

// Fungsi untuk mendapatkan icon mata pelajaran
function getMataPelajaranIcon($mapel) {
    $icons = [
        'Word' => 'bi-file-word',
        'Excel' => 'bi-file-excel', 
        'PowerPoint' => 'bi-file-ppt',
        'Internet' => 'bi-globe',
        'Softskill' => 'bi-gear'
    ];
    return $icons[$mapel] ?? 'bi-journal-bookmark';
}

function getMataPelajaranColor($mapel) {
    $colors = [
        'Word' => 'primary',
        'Excel' => 'success', 
        'PowerPoint' => 'warning',
        'Internet' => 'info',
        'Softskill' => 'secondary'
    ];
    return $colors[$mapel] ?? 'secondary';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Nilai - <?= htmlspecialchars($nilai['nama_siswa']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
              <h2 class="page-title mb-1">DETAIL NILAI</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Akademik</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Nilai</a>
                  </li>
                  <li class="breadcrumb-item active" aria-current="page">Detail</li>
                </ol>
              </nav>
            </div>
          </div>
      
          <!-- Right: Date Info -->
          <div class="d-flex align-items-center">
            <div class="navbar-page-info d-none d-xl-block">
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
      <!-- Alert -->
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle me-2"></i>
          <?= $_SESSION['success'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <!-- Profile Header Card -->
      <div class="card content-card mb-4">
        <div class="card-body p-4">
          <div class="row align-items-start">
            <div class="col-auto">
              <div class="siswa-avatar">
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 100px; height: 100px; border: 4px solid #f8f9fa;">
                  <i class="bi bi-person-fill text-secondary" style="font-size: 3rem;"></i>
                </div>
              </div>
            </div>
            <div class="col">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h3 class="mb-1 fw-bold"><?= htmlspecialchars($nilai['nama_siswa']) ?></h3>
                  <p class="text-muted mb-2">NIK: <?= htmlspecialchars($nilai['nik']) ?></p>
                  <div class="d-flex gap-3 flex-wrap">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                      <i class="bi bi-building me-1"></i>
                      <?= htmlspecialchars($nilai['nama_kelas']) ?>
                    </span>
                    <?php if($nilai['rata_rata']): ?>
                      <?php 
                      list($kategori, $warna) = getKategoriRataRata($nilai['rata_rata']);
                      ?>
                      <span class="badge bg-<?= $warna ?> fs-6 px-3 py-2">
                        <i class="bi bi-trophy me-1"></i>
                        <?= number_format($nilai['rata_rata'], 1) ?> - <?= $kategori ?>
                      </span>
                    <?php else: ?>
                      <span class="badge bg-warning fs-6 px-3 py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Belum Ada Nilai
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-3">
                  <a href="index.php" class="btn btn-kembali px-4">
                    Kembali
                  </a>
                  <a href="edit.php?id=<?= $nilai['id_nilai'] ?>" class="btn btn-edit px-4">
                    <i class="bi bi-pencil me-1"></i>Edit Data
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
          <!-- Informasi Siswa -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-person-lines-fill me-2"></i>Informasi Siswa
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Nama Lengkap</label>
                    <div class="fw-medium"><?= htmlspecialchars($nilai['nama_siswa']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">NIK</label>
                    <div class="fw-medium"><?= htmlspecialchars($nilai['nik']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Email</label>
                    <div class="fw-medium"><?= htmlspecialchars($nilai['email'] ?? '-') ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">No. HP</label>
                    <div class="fw-medium"><?= htmlspecialchars($nilai['no_hp'] ?? '-') ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Tempat & Tanggal Lahir</label>
                    <div class="fw-medium">
                      <?php if($nilai['tempat_lahir'] && $nilai['tanggal_lahir']): ?>
                        <?= htmlspecialchars($nilai['tempat_lahir']) ?>, <?= date('d M Y', strtotime($nilai['tanggal_lahir'])) ?>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Jenis Kelamin</label>
                    <div class="fw-medium"><?= htmlspecialchars($nilai['jenis_kelamin'] ?? '-') ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Pendidikan Terakhir</label>
                    <div class="fw-medium"><?= htmlspecialchars($nilai['pendidikan_terakhir'] ?? '-') ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Kelas</label>
                    <div class="fw-medium">
                      <span class="badge bg-primary px-2 py-1">
                        <i class="bi bi-building me-1"></i>
                        <?= htmlspecialchars($nilai['nama_kelas']) ?>
                      </span>
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Alamat Lengkap</label>
                    <div class="fw-medium"><?= nl2br(htmlspecialchars($nilai['alamat'] ?? '-')) ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Detail Nilai per Mata Pelajaran -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-clipboard-data me-2"></i>Nilai per Mata Pelajaran
              </h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered table-hover">
                  <thead class="table-light">
                    <tr>
                      <th width="40%">Mata Pelajaran</th>
                      <th width="20%" class="text-center">Nilai</th>
                      <th width="40%" class="text-center">Progress</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($nilai_chart as $mapel => $nilai_mapel): ?>
                      <tr>
                        <td>
                          <i class="bi <?= getMataPelajaranIcon($mapel) ?> text-<?= getMataPelajaranColor($mapel) ?> me-2"></i>
                          <strong><?= $mapel ?></strong>
                        </td>
                        <td class="text-center">
                          <?php if($nilai_mapel && $nilai_mapel > 0): ?>
                            <span class="badge bg-<?= $nilai_mapel >= 80 ? 'success' : ($nilai_mapel >= 70 ? 'primary' : ($nilai_mapel >= 60 ? 'warning' : 'danger')) ?> px-3 py-2">
                              <?= $nilai_mapel ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if($nilai_mapel && $nilai_mapel > 0): ?>
                            <div class="progress" style="height: 10px;">
                              <div class="progress-bar bg-<?= $nilai_mapel >= 80 ? 'success' : ($nilai_mapel >= 70 ? 'primary' : ($nilai_mapel >= 60 ? 'warning' : 'danger')) ?>" 
                                   style="width: <?= $nilai_mapel ?>%"
                                   aria-valuemin="0" 
                                   aria-valuemax="100" 
                                   aria-valuenow="<?= $nilai_mapel ?>">
                              </div>
                            </div>
                            <small class="text-muted"><?= $nilai_mapel ?>%</small>
                          <?php else: ?>
                            <small class="text-muted">Belum dinilai</small>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Grafik Performa -->
          <?php if(!empty($nilai_terisi)): ?>
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-graph-up me-2"></i>Grafik Performa
              </h5>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-8">
                  <canvas id="nilaiChart" width="400" height="200"></canvas>
                </div>
                <div class="col-md-4">
                  <h6 class="fw-bold mb-3">Ringkasan</h6>
                  <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                      <span>Nilai Tertinggi</span>
                      <span class="badge bg-success rounded-pill"><?= max($nilai_terisi) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                      <span>Nilai Terendah</span>
                      <span class="badge bg-danger rounded-pill"><?= min($nilai_terisi) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                      <span>Rata-rata</span>
                      <span class="badge bg-primary rounded-pill"><?= number_format($nilai['rata_rata'], 1) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                      <span>Mata Pelajaran</span>
                      <span class="text-muted"><?= count($nilai_terisi) ?> dari 5</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
          <!-- Ringkasan Nilai -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-trophy me-2"></i>Ringkasan Nilai
              </h5>
            </div>
            <div class="card-body text-center">
              <?php if($nilai['rata_rata']): ?>
                <?php list($kategori, $warna) = getKategoriRataRata($nilai['rata_rata']); ?>
                <div class="mb-4">
                  <div class="bg-<?= $warna ?> bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                       style="width: 80px; height: 80px;">
                    <h2 class="fw-bold text-<?= $warna ?> mb-0"><?= number_format($nilai['rata_rata'], 1) ?></h2>
                  </div>
                  <h5 class="fw-bold text-<?= $warna ?>"><?= $kategori ?></h5>
                  <p class="text-muted mb-0">Rata-rata dari <?= count($nilai_terisi) ?> mata pelajaran</p>
                </div>
                
                <div class="row text-center">
                  <div class="col-6">
                    <div class="border-end">
                      <h6 class="fw-bold text-<?= $nilai['status_kelulusan'] == 'lulus' ? 'success' : 'danger' ?>">
                        <?= strtoupper($nilai['status_kelulusan']) ?>
                      </h6>
                      <small class="text-muted">
                        Status Kelulusan
                        <?php if(count($nilai_terisi) < 5): ?>
                          <br><span class="text-warning">(Hasil Sementara)</span>
                        <?php endif; ?>
                      </small>
                    </div>
                  </div>
                  <div class="col-6">
                    <h6 class="fw-bold text-primary"><?= count($nilai_terisi) ?>/5</h6>
                    <small class="text-muted">Mata Pelajaran Dinilai</small>
                  </div>
                </div>
                
                <!-- Info Tambahan -->
                <div class="mt-3 p-2 bg-light rounded">
                  <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Status kelulusan berdasarkan rata-rata keseluruhan (minimal 60 untuk lulus)
                    <?php if(count($nilai_terisi) < 5): ?>
                      <br><span class="text-warning">Status akan final setelah semua nilai diinput</span>
                    <?php endif; ?>
                  </small>
                </div>
              <?php else: ?>
                <div class="text-center p-3">
                  <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                       style="width: 80px; height: 80px;">
                    <i class="bi bi-exclamation-triangle text-warning fs-2"></i>
                  </div>
                  <h5 class="fw-bold text-muted">Belum Ada Nilai</h5>
                  <p class="text-muted mb-0">Nilai belum diinput</p>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Info Kelas -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-building me-2"></i>Info Kelas
              </h5>
            </div>
            <div class="card-body">
              <div class="text-center mb-4">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                     style="width: 60px; height: 60px;">
                  <i class="bi bi-building text-primary fs-4"></i>
                </div>
                <h6 class="fw-bold mb-1"><?= htmlspecialchars($nilai['nama_kelas']) ?></h6>
                <small class="text-muted">
                  <?= htmlspecialchars($nilai['nama_gelombang'] ?? '') ?>
                  <?php if($nilai['gelombang_ke']): ?>
                    (Gelombang <?= $nilai['gelombang_ke'] ?>)
                  <?php endif; ?>
                  <?php if($nilai['tahun_gelombang']): ?>
                    - <?= $nilai['tahun_gelombang'] ?>
                  <?php endif; ?>
                </small>
              </div>

              <div class="list-group list-group-flush">
                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <div>
                    <i class="bi bi-people text-primary me-2"></i>
                    <span class="text-dark">Total Siswa</span>
                  </div>
                  <span class="badge bg-primary rounded-pill"><?= $total_siswa_kelas ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <div>
                    <i class="bi bi-diagram-3 text-info me-2"></i>
                    <span class="text-dark">Kapasitas</span>
                  </div>
                  <span class="text-muted"><?= $nilai['kapasitas'] ?></span>
                </div>
                <?php if($rata_rata_kelas > 0): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                  <div>
                    <i class="bi bi-graph-up text-success me-2"></i>
                    <span class="text-dark">Rata-rata Kelas</span>
                  </div>
                  <span class="text-muted"><?= number_format($rata_rata_kelas, 1) ?></span>
                </div>
                <?php endif; ?>
              </div>

              <?php if($nilai['nama_instruktur']): ?>
              <hr class="my-3">
              <div class="text-center">
                <small class="text-muted d-block">Instruktur</small>
                <h6 class="fw-bold mb-0"><?= htmlspecialchars($nilai['nama_instruktur']) ?></h6>
                <?php if($nilai['nik_instruktur']): ?>
                <small class="text-muted">NIK: <?= htmlspecialchars($nilai['nik_instruktur']) ?></small>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Perbandingan -->
          <?php if($nilai['rata_rata'] && $rata_rata_kelas > 0): ?>
          <div class="card content-card">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-bar-chart me-2"></i>Perbandingan
              </h5>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <small>Nilai Siswa</small>
                  <small><?= number_format($nilai['rata_rata'], 1) ?></small>
                </div>
                <div class="progress mb-2" style="height: 8px;">
                  <div class="progress-bar bg-primary" style="width: <?= ($nilai['rata_rata']/100)*100 ?>%"></div>
                </div>
              </div>
              
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <small>Rata-rata Kelas</small>
                  <small><?= number_format($rata_rata_kelas, 1) ?></small>
                </div>
                <div class="progress mb-2" style="height: 8px;">
                  <div class="progress-bar bg-secondary" style="width: <?= ($rata_rata_kelas/100)*100 ?>%"></div>
                </div>
              </div>

              <div class="text-center mt-3">
                <?php 
                $selisih = $nilai['rata_rata'] - $rata_rata_kelas;
                $status_perbandingan = $selisih >= 0 ? 'di atas' : 'di bawah';
                $warna_perbandingan = $selisih >= 0 ? 'success' : 'warning';
                ?>
                <small class="text-<?= $warna_perbandingan ?>">
                  <i class="bi bi-<?= $selisih >= 0 ? 'arrow-up' : 'arrow-down' ?> me-1"></i>
                  <?= abs(number_format($selisih, 1)) ?> poin <?= $status_perbandingan ?> rata-rata kelas
                </small>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="../../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../../assets/js/scripts.js"></script>

<script>
// Chart.js untuk grafik nilai
<?php if(!empty($nilai_terisi)): ?>
document.addEventListener('DOMContentLoaded', function() {
  const ctx = document.getElementById('nilaiChart').getContext('2d');
  
  const nilaiData = {
    labels: <?= json_encode(array_keys($nilai_terisi)) ?>,
    datasets: [{
      label: 'Nilai',
      data: <?= json_encode(array_values($nilai_terisi)) ?>,
      backgroundColor: [
        'rgba(13, 110, 253, 0.8)',   // primary
        'rgba(25, 135, 84, 0.8)',    // success  
        'rgba(255, 193, 7, 0.8)',    // warning
        'rgba(13, 202, 240, 0.8)',   // info
        'rgba(108, 117, 125, 0.8)'   // secondary
      ],
      borderColor: [
        'rgb(13, 110, 253)',
        'rgb(25, 135, 84)', 
        'rgb(255, 193, 7)',
        'rgb(13, 202, 240)',
        'rgb(108, 117, 125)'
      ],
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false,
    }]
  };

  const nilaiChart = new Chart(ctx, {
    type: 'bar',
    data: nilaiData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.parsed.y + ' poin';
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: {
            callback: function(value) {
              return value + '%';
            }
          },
          grid: {
            color: 'rgba(0,0,0,0.1)'
          }
        },
        x: {
          grid: {
            display: false
          }
        }
      },
      animation: {
        duration: 1000,
        easing: 'easeInOutQuart'
      }
    }
  });
});
<?php endif; ?>

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Animasi progress bars
const progressBars = document.querySelectorAll('.progress-bar');
progressBars.forEach(function(bar) {
  const width = bar.style.width;
  bar.style.width = '0%';
  setTimeout(function() {
    bar.style.transition = 'width 1s ease-in-out';
    bar.style.width = width;
  }, 100);
});

// Smooth scroll untuk anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      target.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });
});
</script>
</body>
</html>