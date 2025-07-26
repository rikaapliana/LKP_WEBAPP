<?php
session_start();  
require_once '../../../includes/auth.php';  
requireSiswaAuth();

include '../../../includes/db.php';
$activePage = 'nilai-saya'; 
$baseURL = '../';

$user_id = $_SESSION['user_id'];

// Ambil data siswa dengan nilai lengkap
$query = "SELECT s.*, k.nama_kelas, g.nama_gelombang, g.tahun, g.gelombang_ke,
                 i.nama as nama_instruktur, n.*,
                 -- Progress nilai
                 CASE WHEN n.nilai_word IS NOT NULL AND n.nilai_word > 0 THEN 1 ELSE 0 END +
                 CASE WHEN n.nilai_excel IS NOT NULL AND n.nilai_excel > 0 THEN 1 ELSE 0 END +
                 CASE WHEN n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0 THEN 1 ELSE 0 END +
                 CASE WHEN n.nilai_internet IS NOT NULL AND n.nilai_internet > 0 THEN 1 ELSE 0 END +
                 CASE WHEN n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0 THEN 1 ELSE 0 END as progress_nilai,
                 -- Status sertifikat
                 CASE WHEN n.rata_rata >= 60 AND 
                           (n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                           (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                           (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                           (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                           (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0)
                      THEN 'eligible' ELSE 'not_eligible' END as sertifikat_status,
                 -- Grade kategori
                 CASE 
                   WHEN n.rata_rata >= 80 THEN 'A'
                   WHEN n.rata_rata >= 70 THEN 'B'
                   WHEN n.rata_rata >= 60 THEN 'C'
                   ELSE 'D'
                 END as grade_keseluruhan
          FROM siswa s
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas  
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
          LEFT JOIN nilai n ON s.id_siswa = n.id_siswa
          WHERE s.id_user = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$siswaData = $result->fetch_assoc();

if (!$siswaData) {
    $_SESSION['error'] = "Data siswa tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit;
}

// Function untuk get grade individual
function getGrade($nilai) {
    if (!$nilai || $nilai <= 0) return '-';
    if ($nilai >= 80) return 'A';
    if ($nilai >= 70) return 'B';
    if ($nilai >= 60) return 'C';
    return 'D';
}

// Function untuk get badge class
function getBadgeClass($nilai) {
    if (!$nilai || $nilai <= 0) return 'bg-secondary';
    if ($nilai >= 80) return 'bg-success';
    if ($nilai >= 70) return 'bg-primary';
    if ($nilai >= 60) return 'bg-warning';
    return 'bg-danger';
}

// Function untuk get grade description
function getGradeDescription($nilai) {
    if (!$nilai || $nilai <= 0) return 'Belum Ada Nilai';
    if ($nilai >= 80) return 'Sangat Baik';
    if ($nilai >= 70) return 'Baik';
    if ($nilai >= 60) return 'Cukup';
    return 'Kurang';
}

// Array mata pelajaran
$mataPelajaran = [
    ['code' => 'word', 'name' => 'Microsoft Word', 'icon' => 'bi-file-word'],
    ['code' => 'excel', 'name' => 'Microsoft Excel', 'icon' => 'bi-file-excel'],
    ['code' => 'ppt', 'name' => 'Microsoft PowerPoint', 'icon' => 'bi-file-ppt'],
    ['code' => 'internet', 'name' => 'Internet & Email', 'icon' => 'bi-globe'],
    ['code' => 'pengembangan', 'name' => 'Pengembangan Softskill', 'icon' => 'bi-person-gear']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nilai Saya - <?= htmlspecialchars($siswaData['nama']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/siswa.php'; ?>

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
                <h2 class="page-title mb-1">NILAI SAYA</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Nilai Saya</li>
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

        <div class="row">
          <!-- Transkrip Nilai -->
          <div class="col-xl-8">
            <!-- Header Transkrip -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-award me-2"></i>Transkrip Nilai Akademik
                </h5>
                <br>
                <div class="ms-auto">
                  <?php if ($siswaData['sertifikat_status'] == 'eligible'): ?>
                    <button type="button" class="btn btn-unduh" onclick="cetakSertifikat()">
                      <i class="bi bi-download me-1"></i>Unduh Sertifikat
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn btn-download-soft" disabled title="Nilai belum lengkap atau belum lulus">
                      <i class="bi bi-award me-1"></i>Sertifikat Belum Tersedia
                    </button>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="card-body">
                <!-- Info Siswa -->
                <div class="row mb-4 p-3 bg-light rounded">
                  <div class="col-md-6">
                    <div class="mb-2">
                      <small class="text-muted">Nama Siswa:</small>
                      <div class="fw-bold"><?= htmlspecialchars($siswaData['nama']) ?></div>
                    </div>
                    <div class="mb-2">
                      <small class="text-muted">NIK:</small>
                      <div class="fw-medium"><?= htmlspecialchars($siswaData['nik']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-2">
                      <small class="text-muted">Kelas:</small>
                      <div class="fw-bold"><?= htmlspecialchars($siswaData['nama_kelas'] ?? 'Belum ada kelas') ?></div>
                    </div>
                    <div class="mb-2">
                      <small class="text-muted">Gelombang:</small>
                      <div class="fw-medium"><?= htmlspecialchars($siswaData['nama_gelombang'] ?? '-') ?></div>
                    </div>
                  </div>
                </div>

                <!-- Table Nilai -->
                <div class="table-responsive">
                  <table class="custom-table mb-0">
                    <thead>
                      <tr>
                        <th style="width: 50px;">No</th>
                        <th>Mata Pelajaran</th>
                        <th class="text-center" style="width: 100px;">Nilai</th>
                        <th class="text-center" style="width: 80px;">Grade</th>
                        <th class="text-center" style="width: 120px;">Keterangan</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php $no = 1; foreach ($mataPelajaran as $mp): ?>
                        <?php 
                        $fieldName = 'nilai_' . $mp['code'];
                        $nilai = $siswaData[$fieldName] ?? 0;
                        $grade = getGrade($nilai);
                        $badgeClass = getBadgeClass($nilai);
                        $description = getGradeDescription($nilai);
                        ?>
                        <tr>
                          <td class="text-center align-middle"><?= $no++ ?></td>
                          <td class="align-middle">
                            <div class="d-flex align-items-center">
                              <i class="<?= $mp['icon'] ?> text-primary me-2 fs-5"></i>
                              <span class="fw-medium"><?= $mp['name'] ?></span>
                            </div>
                          </td>
                          <td class="text-center align-middle">
                            <?php if ($nilai && $nilai > 0): ?>
                              <span class="badge <?= $badgeClass ?> px-3 py-2 fs-6">
                                <?= $nilai ?>
                              </span>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center align-middle">
                            <?php if ($nilai && $nilai > 0): ?>
                              <span class="fw-bold fs-5 text-dark"><?= $grade ?></span>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center align-middle">
                            <small class="<?= $nilai && $nilai > 0 ? '' : 'text-muted' ?>">
                              <?= $description ?>
                            </small>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                      <tr>
                        <td colspan="2" class="fw-bold text-end">RATA-RATA KESELURUHAN:</td>
                        <td class="text-center">
                          <?php if ($siswaData['rata_rata']): ?>
                            <?php 
                            $rataRata = (float)$siswaData['rata_rata'];
                            $badgeClass = getBadgeClass($rataRata);
                            ?>
                            <span class="badge <?= $badgeClass ?> px-3 py-2 fs-6">
                              <?= number_format($rataRata, 1) ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-center">
                          <?php if ($siswaData['rata_rata']): ?>
                            <span class="fw-bold fs-4 text-dark"><?= $siswaData['grade_keseluruhan'] ?></span>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-center">
                          <?php if ($siswaData['rata_rata']): ?>
                            <small><?= getGradeDescription($siswaData['rata_rata']) ?></small>
                          <?php else: ?>
                            <small class="text-muted">Belum Ada Nilai</small>
                          <?php endif; ?>
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Keterangan Grade -->
                <div class="mt-4 p-3 bg-light rounded">
                  <h6 class="fw-bold mb-2">Keterangan Grade:</h6>
                  <div class="row">
                    <div class="col-md-6">
                      <small><strong>A (80-100):</strong> Sangat Baik</small><br>
                      <small><strong>B (70-79):</strong> Baik</small>
                    </div>
                    <div class="col-md-6">
                      <small><strong>C (60-69):</strong> Cukup</small><br>
                      <small><strong>D (&lt;60):</strong> Kurang</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar Progress & Info -->
          <div class="col-xl-4">
            <!-- Status Kelulusan -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-trophy me-2"></i>Status Kelulusan
                </h5>
              </div>
              <div class="card-body text-center p-4">
                <?php if ($siswaData['sertifikat_status'] == 'eligible'): ?>
                  <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                       style="width: 80px; height: 80px;">
                    <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
                  </div>
                  <h5 class="fw-bold mb-2 text-success">LULUS</h5>
                  <p class="text-muted mb-3">Selamat! Anda telah menyelesaikan semua mata pelajaran dengan baik</p>
                  
                  <div class="alert alert-success border-0 mb-3">
                    <i class="bi bi-award me-2"></i>
                    <strong>Sertifikat siap dicetak!</strong>
                  </div>
                  
                <?php elseif ($siswaData['progress_nilai'] == 5 && $siswaData['rata_rata'] < 60): ?>
                  <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                       style="width: 80px; height: 80px;">
                    <i class="bi bi-x-circle text-danger" style="font-size: 2.5rem;"></i>
                  </div>
                  <h5 class="fw-bold mb-2 text-danger">TIDAK LULUS</h5>
                  <p class="text-muted mb-3">Rata-rata nilai kurang dari 60. Pelajari kembali materi dan konsultasi dengan instruktur</p>
                  
                  <div class="alert alert-warning border-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>Rata-rata minimal 60 untuk lulus</small>
                  </div>
                <?php else: ?>
                  <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                       style="width: 80px; height: 80px;">
                    <i class="bi bi-clock text-warning" style="font-size: 2.5rem;"></i>
                  </div>
                  <h5 class="fw-bold mb-2 text-warning">DALAM PROSES</h5>
                  <p class="text-muted mb-3">Masih ada nilai yang belum lengkap. Selesaikan semua mata pelajaran</p>
                  
                  <div class="alert alert-info border-0">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>Progress: <?= $siswaData['progress_nilai'] ?? 0 ?>/5 mata pelajaran</small>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Progress Chart -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-graph-up me-2"></i>Progress Nilai
                </h5>
              </div>
              <div class="card-body">
                <div class="text-center mb-3">
                  <canvas id="progressChart" width="200" height="200"></canvas>
                </div>
                
                <div class="progress-details">
                  <?php foreach ($mataPelajaran as $mp): ?>
                    <?php 
                    $fieldName = 'nilai_' . $mp['code'];
                    $nilai = $siswaData[$fieldName] ?? 0;
                    $hasValue = $nilai && $nilai > 0;
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <div class="d-flex align-items-center">
                        <i class="<?= $mp['icon'] ?> me-2 <?= $hasValue ? 'text-success' : 'text-muted' ?>"></i>
                        <small class="<?= $hasValue ? '' : 'text-muted' ?>"><?= $mp['name'] ?></small>
                      </div>
                      <div>
                        <?php if ($hasValue): ?>
                          <i class="bi bi-check-circle text-success"></i>
                        <?php else: ?>
                          <i class="bi bi-circle text-muted"></i>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
    // Progress Chart
    document.addEventListener('DOMContentLoaded', function() {
      const ctx = document.getElementById('progressChart').getContext('2d');
      const progressNilai = <?= $siswaData['progress_nilai'] ?? 0 ?>;
      const totalMataPelajaran = 5;
      
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['Selesai', 'Belum Selesai'],
          datasets: [{
            data: [progressNilai, totalMataPelajaran - progressNilai],
            backgroundColor: [
              progressNilai === totalMataPelajaran ? '#198754' : '#0d6efd',
              '#e9ecef'
            ],
            borderWidth: 0,
            cutout: '70%'
          }]
        },
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
                  return context.label + ': ' + context.parsed + ' mata pelajaran';
                }
              }
            }
          }
        },
        plugins: [{
          beforeDraw: function(chart) {
            const width = chart.width;
            const height = chart.height;
            const ctx = chart.ctx;
            
            ctx.restore();
            const fontSize = (height / 114).toFixed(2);
            ctx.font = fontSize + "em sans-serif";
            ctx.textBaseline = "middle";
            
            const text = progressNilai + "/" + totalMataPelajaran;
            const textX = Math.round((width - ctx.measureText(text).width) / 2);
            const textY = height / 2;
            
            ctx.fillStyle = '#495057';
            ctx.fillText(text, textX, textY - 10);
            
            ctx.font = (fontSize * 0.6) + "em sans-serif";
            const subText = "Mata Pelajaran";
            const subTextX = Math.round((width - ctx.measureText(subText).width) / 2);
            ctx.fillStyle = '#6c757d';
            ctx.fillText(subText, subTextX, textY + 15);
            
            ctx.save();
          }
        }]
      });
    });

    
function cetakSertifikat() {
  const eligible = '<?= $siswaData['sertifikat_status'] ?>';
  
  if (eligible !== 'eligible') {
    Swal.fire({
      title: 'Sertifikat Belum Tersedia',
      text: 'Anda belum memenuhi syarat untuk mencetak sertifikat.',
      icon: 'warning',
      confirmButtonText: 'OK',
      toast: true,
      position: 'top-end',
      timer: 3000
    });
    return;
  }

  // Langsung buka tanpa konfirmasi
  const pdfWindow = window.open('cetak_sertifikat.php', '_blank');
  
  if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed == 'undefined') {
    // Fallback jika popup diblokir
    window.location.href = 'cetak_sertifikat.php';
  }
}

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });
  </script>
</body>
</html>