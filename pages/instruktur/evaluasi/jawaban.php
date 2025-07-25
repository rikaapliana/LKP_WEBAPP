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

// Validasi parameter
if (!isset($_GET['id_evaluasi']) || !is_numeric($_GET['id_evaluasi'])) {
    $_SESSION['error'] = "ID evaluasi tidak valid.";
    header("Location: index.php");
    exit;
}

$id_evaluasi = (int)$_GET['id_evaluasi'];

// Ambil data evaluasi dengan validasi akses instruktur (query yang disederhanakan)
$evaluasiQuery = "SELECT 
                    e.*,
                    s.id_siswa,
                    s.nama as nama_siswa,
                    s.nik,
                    s.pas_foto,
                    s.id_kelas,
                    pe.id_periode,
                    pe.nama_evaluasi,
                    pe.jenis_evaluasi,
                    pe.materi_terkait,
                    g.nama_gelombang,
                    g.tahun
                  FROM evaluasi e
                  JOIN siswa s ON e.id_siswa = s.id_siswa
                  JOIN periode_evaluasi pe ON e.id_periode = pe.id_periode
                  LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang
                  WHERE e.id_evaluasi = ?";

$stmt = mysqli_prepare($conn, $evaluasiQuery);
mysqli_stmt_bind_param($stmt, "i", $id_evaluasi);
mysqli_stmt_execute($stmt);
$evaluasiResult = mysqli_stmt_get_result($stmt);

if (!$evaluasiResult || mysqli_num_rows($evaluasiResult) == 0) {
    $_SESSION['error'] = "Data evaluasi tidak ditemukan.";
    header("Location: index.php");
    exit;
}

$evaluasi = mysqli_fetch_assoc($evaluasiResult);

// Validasi apakah siswa dari kelas yang diampu instruktur
$kelasValidasiQuery = "SELECT k.nama_kelas, k.id_instruktur 
                       FROM kelas k 
                       WHERE k.id_kelas = ? AND k.id_instruktur = ?";
$kelasStmt = mysqli_prepare($conn, $kelasValidasiQuery);
mysqli_stmt_bind_param($kelasStmt, "ii", $evaluasi['id_kelas'], $id_instruktur);
mysqli_stmt_execute($kelasStmt);
$kelasResult = mysqli_stmt_get_result($kelasStmt);

if (!$kelasResult || mysqli_num_rows($kelasResult) == 0) {
    $_SESSION['error'] = "Data evaluasi tidak ditemukan atau bukan siswa dari kelas yang Anda ampu.";
    header("Location: index.php");
    exit;
}

$kelasData = mysqli_fetch_assoc($kelasResult);
$evaluasi['nama_kelas'] = $kelasData['nama_kelas'];

// Ambil semua jawaban evaluasi dengan join ke pertanyaan (urutkan berdasarkan nomor urut)
$jawabanQuery = "SELECT 
                   je.*,
                   p.pertanyaan,
                   p.aspek_dinilai,
                   p.tipe_jawaban,
                   p.pilihan_jawaban
                 FROM jawaban_evaluasi je
                 JOIN pertanyaan_evaluasi p ON je.id_pertanyaan = p.id_pertanyaan
                 WHERE je.id_evaluasi = ?
                 ORDER BY je.id_jawaban ASC";

$jawabanStmt = mysqli_prepare($conn, $jawabanQuery);
mysqli_stmt_bind_param($jawabanStmt, "i", $id_evaluasi);
mysqli_stmt_execute($jawabanStmt);
$jawabanResult = mysqli_stmt_get_result($jawabanStmt);

// Ambil semua jawaban dalam array
$allJawaban = [];
$stats_tipe = [
    'pilihan_ganda' => 0,
    'skala' => 0,
    'isian' => 0
];

while ($jawaban = mysqli_fetch_assoc($jawabanResult)) {
    $allJawaban[] = $jawaban;
    $stats_tipe[$jawaban['tipe_jawaban']]++;
}

// Hitung statistik rating
$totalJawaban = count($allJawaban);
$jawabanSkala = 0;
$totalSkala = 0;

foreach ($allJawaban as $jawaban) {
    if ($jawaban['tipe_jawaban'] == 'skala') {
        $jawabanSkala++;
        $totalSkala += (int)$jawaban['jawaban'];
    }
}

$rataRataSkala = $jawabanSkala > 0 ? round($totalSkala / $jawabanSkala, 1) : 0;

// Label materi
$materi_labels = [
    'word' => 'Microsoft Word',
    'excel' => 'Microsoft Excel', 
    'ppt' => 'Microsoft PowerPoint',
    'internet' => 'Internet & Email'
];

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';
    
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_nama = $bulan[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return "$hari $bulan_nama $tahun, $jam";
}

// Helper function untuk mendapatkan icon tipe jawaban
function getTipeJawabanIcon($tipe) {
    switch ($tipe) {
        case 'pilihan_ganda':
            return 'bi-check2-square';
        case 'skala':
            return 'bi-star';
        case 'isian':
            return 'bi-pencil';
        default:
            return 'bi-question-circle';
    }
}

// Helper function untuk mendapatkan label tipe jawaban
function getTipeJawabanLabel($tipe) {
    switch ($tipe) {
        case 'pilihan_ganda':
            return 'Pilihan Ganda';
        case 'skala':
            return 'Rating 1-5';
        case 'isian':
            return 'Isian Bebas';
        default:
            return 'Tidak Diketahui';
    }
}

// Function untuk decode pilihan jawaban
function getPilihanJawaban($pilihan_jawaban) {
    if (empty($pilihan_jawaban)) return [];
    $decoded = json_decode($pilihan_jawaban, true);
    return is_array($decoded) ? $decoded : [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jawaban Evaluasi - <?= htmlspecialchars($evaluasi['nama_siswa']) ?></title>
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
                <h2 class="page-title mb-1">JAWABAN EVALUASI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Hasil Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Jawaban</li>
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

        <!-- Header Student Info -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-auto">
                <?php if($evaluasi['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$evaluasi['pas_foto'])): ?>
                  <img src="../../../uploads/pas_foto/<?= $evaluasi['pas_foto'] ?>" 
                       alt="Foto Siswa" 
                       class="rounded-circle border" 
                       style="width: 80px; height: 80px; object-fit: cover;">
                <?php else: ?>
                  <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white" 
                       style="width: 80px; height: 80px;">
                    <i class="bi bi-person-fill fs-2"></i>
                  </div>
                <?php endif; ?>
              </div>
              <div class="col">
                <h4 class="mb-1 text-dark"><?= htmlspecialchars($evaluasi['nama_siswa']) ?></h4>
                <div class="text-muted mb-2">
                  NIK: <?= htmlspecialchars($evaluasi['nik']) ?> • 
                  Kelas <?= htmlspecialchars($evaluasi['nama_kelas']) ?>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <?php if($evaluasi['jenis_evaluasi'] == 'per_materi'): ?>
                    <?php if($evaluasi['materi_terkait']): ?>
                      <span class="text-muted"><?= $materi_labels[$evaluasi['materi_terkait']] ?? ucfirst($evaluasi['materi_terkait']) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge bg-success">Akhir Kursus</span>
                  <?php endif; ?>
                  <span class="text-muted">
                    <i class="bi bi-clock me-1"></i>
                    <?= formatTanggalIndonesia($evaluasi['tanggal_evaluasi']) ?>
                  </span>
                </div>
              </div>
              <div class="col-auto">
                <a href="index.php?kelas=<?= urlencode($evaluasi['nama_kelas']) ?>&periode=<?= $evaluasi['id_periode'] ?>" class="btn btn-kembali">
                 Kembali
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Daftar Jawaban -->
        <?php if (!empty($allJawaban)): ?>
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-chat-quote me-2"></i>Daftar Jawaban
              </h5>
              <small class="text-muted"><?= $totalJawaban ?> pertanyaan dijawab</small>
            </div>
            <div class="card-body">
              <?php foreach ($allJawaban as $index => $jawaban): ?>
                <div class="question-item <?= $index < count($allJawaban) - 1 ? 'mb-4 pb-4 border-bottom' : '' ?>">
                  <!-- Pertanyaan -->
                  <div class="mb-3">
                    <div class="d-flex align-items-start">
                      <div class="question-number me-3">
                        <span class="badge bg-light text-dark border fw-bold" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                          <?= $index + 1 ?>
                        </span>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-2 text-dark">
                          <?= nl2br(htmlspecialchars($jawaban['pertanyaan'])) ?>
                        </h6>
                        <div class="d-flex align-items-center gap-3">
                          <small class="text-muted">
                            <i class="bi bi-<?= getTipeJawabanIcon($jawaban['tipe_jawaban']) ?> me-1"></i>
                            <?= getTipeJawabanLabel($jawaban['tipe_jawaban']) ?>
                          </small>
                          <small class="text-muted">
                            <i class="bi bi-tag me-1"></i>
                            <?= htmlspecialchars($jawaban['aspek_dinilai']) ?>
                          </small>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Jawaban -->
                  <div class="answer-section ms-5">
                    <?php if ($jawaban['tipe_jawaban'] == 'pilihan_ganda'): ?>
                      <!-- Pilihan Ganda -->
                      <?php 
                      $pilihan = getPilihanJawaban($jawaban['pilihan_jawaban']);
                      $jawabanIndex = (int)$jawaban['jawaban'];
                      $jawabanText = isset($pilihan[$jawabanIndex]) ? $pilihan[$jawabanIndex] : 'Jawaban tidak valid';
                      $jawabanLabel = chr(65 + $jawabanIndex); // A, B, C, D
                      ?>
                      <div class="multiple-choice-answer">
                        <div class="fw-bold text-dark mb-3">Jawaban:</div>
                        <div class="answer-choice p-3 bg-light rounded border-start border-info border-4">
                          <div class="d-flex align-items-center">
                            <div class="choice-label me-3">
                              <span class="badge bg-info rounded-circle d-inline-flex align-items-center justify-content-center" 
                                    style="width: 35px; height: 35px; font-size: 1rem; font-weight: 600;">
                                <?= $jawabanLabel ?>
                              </span>
                            </div>
                            <div class="choice-text">
                              <div class="fw-medium text-dark"><?= htmlspecialchars($jawabanText) ?></div>
                            </div>
                          </div>
                        </div>
                      </div>

                    <?php elseif ($jawaban['tipe_jawaban'] == 'skala'): ?>
                      <!-- Rating Skala -->
                      <div class="scale-answer p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                          <span class="fw-bold text-dark me-3">Jawaban:</span>
                          <div class="rating-display">
                            <?php 
                            $rating = (int)$jawaban['jawaban'];
                            for($i = 1; $i <= 5; $i++): 
                            ?>
                              <i class="bi bi-star<?= $i <= $rating ? '-fill text-warning' : ' text-muted' ?> me-1" style="font-size: 1.25rem;"></i>
                            <?php endfor; ?>
                            <span class="ms-2 fs-5 fw-bold text-dark"><?= $rating ?>/5</span>
                          </div>
                        </div>
                        <div class="mt-2">
                          <small class="text-muted">
                            <?php 
                            $ratingLabels = [
                              1 => 'Sangat Buruk',
                              2 => 'Buruk', 
                              3 => 'Cukup',
                              4 => 'Baik',
                              5 => 'Sangat Baik'
                            ];
                            echo $ratingLabels[$rating] ?? 'Tidak valid';
                            ?>
                          </small>
                        </div>
                      </div>

                    <?php else: ?>
                      <!-- Isian Bebas -->
                      <div class="text-answer">
                        <div class="fw-bold text-dark mb-2">Jawaban:</div>
                        <div class="answer-box p-3 bg-light rounded border-start border-primary border-4">
                          <div class="text-dark" style="line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($jawaban['jawaban']) ?></div>
                        </div>
                        <div class="mt-2">
                          <small class="text-muted">
                            <i class="bi bi-type me-1"></i>
                            <?= strlen($jawaban['jawaban']) ?> karakter
                          </small>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
              <i class="bi bi-chat-x display-1 text-muted mb-3"></i>
              <h4>Belum Ada Jawaban</h4>
              <p class="text-muted">Siswa belum memberikan jawaban untuk evaluasi ini</p>
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
    // Initialize tooltips
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }

    // Add hover effects for question items
    document.querySelectorAll('.question-item').forEach(item => {
      item.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#fafbfc';
        this.style.borderRadius = '8px';
        this.style.padding = '1rem';
        this.style.margin = '-1rem';
        this.style.transition = 'all 0.3s ease';
      });
      
      item.addEventListener('mouseleave', function() {
        this.style.backgroundColor = '';
        this.style.borderRadius = '';
        this.style.padding = '';
        this.style.margin = '';
      });
    });

    // Print functionality
    document.addEventListener('keydown', function(e) {
      if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        window.print();
      }
    });
  });
  </script>
</body>
</html>