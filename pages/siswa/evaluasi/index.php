<?php
session_start();
require_once '../../../includes/auth.php';
requireSiswaAuth(); // Hanya siswa yang bisa akses

include '../../../includes/db.php';
$activePage = 'evaluasi'; 
$baseURL = '../';

// Ambil data siswa yang sedang login
$stmt = $conn->prepare("SELECT s.*, k.nama_kelas, g.nama_gelombang, g.id_gelombang, i.nama as nama_instruktur 
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

// Ambil evaluasi yang tersedia untuk gelombang siswa
$evaluasiTersediaQuery = "SELECT pe.*, 
                         CASE 
                           WHEN NOW() < pe.tanggal_buka THEN 'belum_buka'
                           WHEN NOW() > pe.tanggal_tutup THEN 'sudah_tutup'
                           WHEN pe.status = 'aktif' THEN 'bisa_dikerjakan'
                           ELSE 'tidak_aktif'
                         END as status_akses
                         FROM periode_evaluasi pe 
                         WHERE pe.id_gelombang = ? 
                         AND pe.status IN ('aktif', 'selesai')
                         ORDER BY pe.tanggal_buka DESC";

$evaluasiTersediaStmt = $conn->prepare($evaluasiTersediaQuery);
$evaluasiTersediaStmt->bind_param("i", $siswaData['id_gelombang']);
$evaluasiTersediaStmt->execute();
$evaluasiTersediaResult = $evaluasiTersediaStmt->get_result();

// Ambil history evaluasi yang sudah dikerjakan siswa
$historyQuery = "SELECT e.*, pe.nama_evaluasi, pe.jenis_evaluasi, pe.materi_terkait, pe.tanggal_tutup
                FROM evaluasi e
                LEFT JOIN periode_evaluasi pe ON e.id_periode = pe.id_periode
                WHERE e.id_siswa = ?
                ORDER BY e.tanggal_evaluasi DESC";

$historyStmt = $conn->prepare($historyQuery);
$historyStmt->bind_param("i", $siswaData['id_siswa']);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();

// Statistik evaluasi
$totalEvaluasiTersedia = mysqli_num_rows($evaluasiTersediaResult);
$totalEvaluasiSelesai = mysqli_num_rows($historyResult);

// Reset pointer result
mysqli_data_seek($evaluasiTersediaResult, 0);
mysqli_data_seek($historyResult, 0);

// Function untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulanNama = $bulan[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return $hari . ' ' . $bulanNama . ' ' . $tahun . ' pukul ' . $jam;
}

// Function untuk mendapatkan badge status
function getStatusBadge($status_akses) {
    switch($status_akses) {
        case 'bisa_dikerjakan':
            return '<span class="badge bg-success"><i class="bi bi-play-circle me-1"></i>Tersedia</span>';
        case 'belum_buka':
            return '<span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Belum Buka</span>';
        case 'sudah_tutup':
            return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Sudah Tutup</span>';
        case 'tidak_aktif':
            return '<span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>Tidak Aktif</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Function untuk mendapatkan icon jenis evaluasi
function getJenisIcon($jenis) {
    switch($jenis) {
        case 'per_materi':
            return 'bi-book';
        case 'akhir_kursus':
            return 'bi-trophy';
        default:
            return 'bi-clipboard-check';
    }
}

// Function untuk nama materi
function getNamaMateri($materi) {
    switch($materi) {
        case 'word': return 'Microsoft Word';
        case 'excel': return 'Microsoft Excel';
        case 'ppt': return 'Microsoft PowerPoint';
        case 'internet': return 'Internet & Email';
        default: return ucfirst($materi);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Evaluasi Pembelajaran - Siswa</title>
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
                <h2 class="page-title mb-1">EVALUASI PEMBELAJARAN</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Evaluasi Pembelajaran</li>
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
            <!-- Evaluasi Tersedia -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-clipboard-check me-2"></i>Evaluasi Tersedia
                </h5>
              </div>
              
              <div class="card-body">
                <?php if (mysqli_num_rows($evaluasiTersediaResult) > 0): ?>
                  <div class="row">
                    <?php while ($evaluasi = mysqli_fetch_assoc($evaluasiTersediaResult)): ?>
                      <div class="col-md-6 mb-3">
                        <div class="card border">
                          <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                              <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                  <i class="<?= getJenisIcon($evaluasi['jenis_evaluasi']) ?> text-primary fs-5"></i>
                                  <h6 class="mb-0 fw-medium"><?= htmlspecialchars($evaluasi['nama_evaluasi']) ?></h6>
                                </div>
                                
                                <div class="mb-2">
                                  <small class="text-muted">Jenis:</small>
                                  <span class="badge bg-info px-2 py-1 small">
                                    <?= $evaluasi['jenis_evaluasi'] == 'per_materi' ? 'Per Materi' : 'Akhir Kursus' ?>
                                  </span>
                                  
                                  <?php if($evaluasi['materi_terkait']): ?>
                                    <span class="badge bg-secondary px-2 py-1 small ms-1">
                                      <?= getNamaMateri($evaluasi['materi_terkait']) ?>
                                    </span>
                                  <?php endif; ?>
                                </div>
                                
                                <?php if($evaluasi['deskripsi']): ?>
                                  <p class="text-muted small mb-2"><?= htmlspecialchars($evaluasi['deskripsi']) ?></p>
                                <?php endif; ?>
                                
                                <div class="small text-muted mb-2">
                                  <div><i class="bi bi-calendar-event me-1"></i>
                                    <strong>Buka:</strong> <?= formatTanggalIndonesia($evaluasi['tanggal_buka']) ?>
                                  </div>
                                  <div><i class="bi bi-calendar-x me-1"></i>
                                    <strong>Tutup:</strong> <?= formatTanggalIndonesia($evaluasi['tanggal_tutup']) ?>
                                  </div>
                                </div>
                                
                                <div class="mb-3">
                                  <?= getStatusBadge($evaluasi['status_akses']) ?>
                                </div>
                              </div>
                            </div>
                            
                            <div class="d-grid">
                              <?php if($evaluasi['status_akses'] == 'bisa_dikerjakan'): ?>
                                <!-- Cek apakah sudah pernah dikerjakan -->
                                <?php
                                $cekSudahKerjaQuery = "SELECT id_evaluasi FROM evaluasi WHERE id_siswa = ? AND id_periode = ?";
                                $cekStmt = $conn->prepare($cekSudahKerjaQuery);
                                $cekStmt->bind_param("ii", $siswaData['id_siswa'], $evaluasi['id_periode']);
                                $cekStmt->execute();
                                $sudahDikerjakan = $cekStmt->get_result()->num_rows > 0;
                                ?>
                                
                                <?php if($sudahDikerjakan): ?>
                                  <span class="btn btn-success btn-sm disabled">
                                    <i class="bi bi-check-circle me-1"></i>Sudah Dikerjakan
                                  </span>
                                <?php else: ?>
                                  <a href="form.php?id=<?= $evaluasi['id_periode'] ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-play-circle me-1"></i>Mulai Evaluasi
                                  </a>
                                <?php endif; ?>
                              <?php else: ?>
                                <span class="btn btn-secondary btn-sm disabled">
                                  <i class="bi bi-lock me-1"></i>Tidak Tersedia
                                </span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endwhile; ?>
                  </div>
                <?php else: ?>
                  <div class="text-center py-4">
                    <i class="bi bi-clipboard-x display-4 text-muted mb-3 d-block"></i>
                    <h5>Belum Ada Evaluasi Tersedia</h5>
                    <p class="text-muted mb-3">
                      Belum ada evaluasi yang dibuka untuk gelombang Anda saat ini.
                    </p>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- History Evaluasi -->
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-clock-history me-2"></i>Riwayat Evaluasi
                </h5>
              </div>
              
              <div class="card-body">
                <?php if (mysqli_num_rows($historyResult) > 0): ?>
                  <div class="table-responsive">
                    <table class="custom-table mb-0">
                      <thead>
                        <tr>
                          <th>Nama Evaluasi</th>
                          <th>Jenis</th>
                          <th>Tanggal Dikerjakan</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php while ($history = mysqli_fetch_assoc($historyResult)): ?>
                          <tr>
                            <td class="align-middle">
                              <div class="fw-medium"><?= htmlspecialchars($history['nama_evaluasi']) ?></div>
                              <?php if($history['materi_terkait']): ?>
                                <small class="text-muted"><?= getNamaMateri($history['materi_terkait']) ?></small>
                              <?php endif; ?>
                            </td>
                            
                            <td class="align-middle">
                              <span class="badge bg-info px-2 py-1">
                                <i class="<?= getJenisIcon($history['jenis_evaluasi']) ?> me-1"></i>
                                <?= $history['jenis_evaluasi'] == 'per_materi' ? 'Per Materi' : 'Akhir Kursus' ?>
                              </span>
                            </td>
                            
                            <td class="align-middle">
                              <small class="text-muted">
                                <i class="bi bi-calendar me-1"></i>
                                <?= date('d/m/Y H:i', strtotime($history['tanggal_evaluasi'])) ?>
                              </small>
                            </td>
                            
                            <td class="align-middle">
                              <?php if($history['status_evaluasi'] == 'selesai'): ?>
                                <span class="badge bg-success px-2 py-1">
                                  <i class="bi bi-check-circle me-1"></i>Selesai
                                </span>
                              <?php else: ?>
                                <span class="badge bg-warning px-2 py-1">
                                  <i class="bi bi-hourglass-split me-1"></i>Dalam Proses
                                </span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="text-center py-4">
                    <i class="bi bi-inbox display-4 text-muted mb-3 d-block"></i>
                    <h5>Belum Ada Riwayat Evaluasi</h5>
                    <p class="text-muted">
                      Anda belum pernah mengerjakan evaluasi apapun.
                    </p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Sidebar Info -->
          <div class="col-lg-4">
            <!-- Tips Evaluasi -->
            <div class="card content-card">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-lightbulb me-2"></i>Tips Evaluasi
                </h6>
              </div>
              <div class="card-body">
                <div class="small text-muted">
                  <div class="mb-2">
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Pastikan koneksi internet stabil
                  </div>
                  <div class="mb-2">
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Evaluasi hanya bisa dikerjakan sekali
                  </div>
                  <div class="mb-2">
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Perhatikan batas waktu yang diberikan
                  </div>
                  <div class="mb-2">
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Jawab semua pertanyaan dengan jujur
                  </div>
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
    // Auto refresh untuk update status evaluasi setiap 5 menit
    setInterval(function() {
      // Bisa tambahkan AJAX untuk update status tanpa reload penuh
    }, 300000);
    
    // Konfirmasi sebelum mulai evaluasi
    const startButtons = document.querySelectorAll('a[href*="form.php"]');
    startButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        const evaluasiName = this.closest('.card-body').querySelector('h6').textContent;
        
        if (!confirm(`Apakah Anda yakin ingin memulai evaluasi "${evaluasiName}"?\n\nEvaluasi hanya bisa dikerjakan sekali dan tidak dapat diulang.`)) {
          e.preventDefault();
        }
      });
    });
  });
  </script>
</body>
</html>