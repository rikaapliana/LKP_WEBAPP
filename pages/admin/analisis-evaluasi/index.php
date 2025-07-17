<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'analisis-evaluasi'; 
$baseURL = '../';

// Get all active evaluation periods
$periodeQuery = "SELECT 
                    pe.*,
                    g.nama_gelombang,
                    g.tahun,
                    COUNT(DISTINCT e.id_evaluasi) as total_evaluasi,
                    COUNT(DISTINCT CASE WHEN e.status_evaluasi = 'selesai' THEN e.id_evaluasi END) as evaluasi_selesai
                 FROM periode_evaluasi pe
                 LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang
                 LEFT JOIN evaluasi e ON pe.id_periode = e.id_periode
                 WHERE pe.status IN ('aktif', 'selesai')
                 GROUP BY pe.id_periode
                 ORDER BY pe.created_at DESC";

$periodeResult = mysqli_query($conn, $periodeQuery);
$periodeList = [];
while ($periode = mysqli_fetch_assoc($periodeResult)) {
    $periodeList[] = $periode;
}

// Default selected periode (latest)
$selectedPeriode = isset($_GET['periode']) ? (int)$_GET['periode'] : ($periodeList[0]['id_periode'] ?? 0);

if ($selectedPeriode > 0) {
    // Get detailed data for selected periode
    $detailQuery = "SELECT 
                        pe.*,
                        g.nama_gelombang,
                        g.tahun,
                        COUNT(DISTINCT e.id_evaluasi) as total_evaluasi,
                        COUNT(DISTINCT CASE WHEN e.status_evaluasi = 'selesai' THEN e.id_evaluasi END) as evaluasi_selesai,
                        (SELECT COUNT(DISTINCT s.id_siswa) 
                         FROM siswa s 
                         JOIN kelas k ON s.id_kelas = k.id_kelas 
                         WHERE k.id_gelombang = pe.id_gelombang AND s.status_aktif = 'aktif') as total_siswa_aktif
                     FROM periode_evaluasi pe
                     LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang
                     LEFT JOIN evaluasi e ON pe.id_periode = e.id_periode
                     WHERE pe.id_periode = $selectedPeriode
                     GROUP BY pe.id_periode";
    
    $detailResult = mysqli_query($conn, $detailQuery);
    $currentPeriode = mysqli_fetch_assoc($detailResult);

    // Get questions for this periode
    $pertanyaanData = [];
    if ($currentPeriode && $currentPeriode['pertanyaan_terpilih']) {
        $pertanyaan_terpilih = json_decode($currentPeriode['pertanyaan_terpilih'], true);
        if (is_array($pertanyaan_terpilih) && !empty($pertanyaan_terpilih)) {
            $pertanyaan_ids = implode(',', array_map('intval', $pertanyaan_terpilih));
            $pertanyaanQuery = "SELECT p.id_pertanyaan, p.pertanyaan, p.aspek_dinilai, p.tipe_jawaban, p.pilihan_jawaban
                                FROM pertanyaan_evaluasi p
                                WHERE p.id_pertanyaan IN ($pertanyaan_ids)
                                ORDER BY p.aspek_dinilai, p.id_pertanyaan";
            
            $pertanyaanResult = mysqli_query($conn, $pertanyaanQuery);
            while ($pertanyaan = mysqli_fetch_assoc($pertanyaanResult)) {
                $pertanyaanData[] = $pertanyaan;
            }
        }
    }

    // Get all answers for analysis
    $jawabanData = [];
    if (!empty($pertanyaanData)) {
        $jawabanQuery = "SELECT 
                           je.id_pertanyaan,
                           je.jawaban,
                           s.nama as nama_siswa,
                           k.nama_kelas
                         FROM jawaban_evaluasi je
                         JOIN evaluasi e ON je.id_evaluasi = e.id_evaluasi
                         JOIN siswa s ON je.id_siswa = s.id_siswa
                         JOIN kelas k ON s.id_kelas = k.id_kelas
                         WHERE e.id_periode = $selectedPeriode AND e.status_evaluasi = 'selesai'";
        
        $jawabanResult = mysqli_query($conn, $jawabanQuery);
        while ($jawaban = mysqli_fetch_assoc($jawabanResult)) {
            $jawabanData[] = $jawaban;
        }
    }

    // Process data for charts
    $ratingData = [];
    $multipleChoiceData = [];
    $feedbackData = [];
    $classPerformance = [];

    foreach ($pertanyaanData as $pertanyaan) {
        $id_pertanyaan = $pertanyaan['id_pertanyaan'];
        $answers = array_filter($jawabanData, function($j) use ($id_pertanyaan) {
            return $j['id_pertanyaan'] == $id_pertanyaan;
        });

        if ($pertanyaan['tipe_jawaban'] == 'skala') {
            $ratings = array_map('intval', array_column($answers, 'jawaban'));
            if (!empty($ratings)) {
                $ratingData[] = [
                    'aspect' => $pertanyaan['aspek_dinilai'],
                    'average' => round(array_sum($ratings) / count($ratings), 1),
                    'count' => count($ratings)
                ];
            }
        } elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda') {
            $pilihan = json_decode($pertanyaan['pilihan_jawaban'], true);
            if (is_array($pilihan)) {
                $distribution = [];
                foreach ($answers as $answer) {
                    $choiceIndex = (int)$answer['jawaban'];
                    $choiceLabel = chr(65 + $choiceIndex);
                    if (!isset($distribution[$choiceLabel])) {
                        $distribution[$choiceLabel] = 0;
                    }
                    $distribution[$choiceLabel]++;
                }
                $multipleChoiceData[$pertanyaan['aspek_dinilai']] = $distribution;
            }
        } elseif ($pertanyaan['tipe_jawaban'] == 'isian') {
            $texts = array_column($answers, 'jawaban');
            $feedbackData[$pertanyaan['aspek_dinilai']] = $texts;
        }

        // Class performance for rating questions
        if ($pertanyaan['tipe_jawaban'] == 'skala') {
            foreach ($answers as $answer) {
                $kelas = $answer['nama_kelas'];
                if (!isset($classPerformance[$kelas])) {
                    $classPerformance[$kelas] = [];
                }
                $classPerformance[$kelas][] = (int)$answer['jawaban'];
            }
        }
    }

    // Calculate class averages
    $classAverages = [];
    foreach ($classPerformance as $kelas => $ratings) {
        if (!empty($ratings)) {
            $classAverages[$kelas] = round(array_sum($ratings) / count($ratings), 1);
        }
    }

} else {
    $currentPeriode = null;
    $pertanyaanData = [];
    $ratingData = [];
    $multipleChoiceData = [];
    $feedbackData = [];
    $classAverages = [];
}

// Helper function
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
    
    return "$hari $bulan_nama $tahun";
}

$materi_labels = [
    'word' => 'Microsoft Word',
    'excel' => 'Microsoft Excel', 
    'ppt' => 'Microsoft PowerPoint',
    'internet' => 'Internet & Email'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Analisis Evaluasi</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <style>
  

  .chart-container {
    position: relative;
    height: 300px;
    margin: 1rem 0;
  }

  .chart-container.large {
    height: 400px;
  }

  .chart-container.small {
    height: 250px;
  }

  .chart-card {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1.5rem;
  }

  .chart-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .insight-card {
    background: #f8f9fa;
    border-left: 4px solid #3b82f6;
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
  }

  .insight-title {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
  }

  .insight-text {
    color: #6b7280;
    font-size: 0.9rem;
    margin-bottom: 0;
  }

  .feedback-item {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    line-height: 1.4;
  }

  .filter-section {
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1.5rem;
  }

  .empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
  }

  .empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
  }

  @media (max-width: 768px) {
    .stats-number {
      font-size: 2rem;
    }
    
    .chart-container {
      height: 250px;
    }
    
    .chart-container.large {
      height: 300px;
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
                <h2 class="page-title mb-1">DASHBOARD ANALISIS EVALUASI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Evaluasi & Feedback</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Analisis Evaluasi</li>
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
        <!-- Filter Section -->
        <div class="filter-section">
          <div class="row align-items-center">
            <div class="col-md-6">
              <h6 class="mb-2">
                <i class="bi bi-filter me-2"></i>Filter Periode Evaluasi
              </h6>
              <select class="form-select" id="periodeSelect" onchange="changePeriode()">
                <option value="">Pilih Periode Evaluasi</option>
                <?php foreach ($periodeList as $periode): ?>
                  <option value="<?= $periode['id_periode'] ?>" <?= $selectedPeriode == $periode['id_periode'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($periode['nama_evaluasi']) ?> 
                    (<?= $periode['evaluasi_selesai'] ?>/<?= $periode['total_evaluasi'] ?> selesai)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($currentPeriode): ?>
            <div class="col-md-6 mt-3 mt-md-0">
              <div class="d-flex justify-content-md-end">
                <div class="text-center text-md-end">
                  <h6 class="mb-1"><?= htmlspecialchars($currentPeriode['nama_evaluasi']) ?></h6>
                  <small class="text-muted">
                    <?= $currentPeriode['nama_gelombang'] ?> • 
                    <?= formatTanggalIndonesia($currentPeriode['tanggal_buka']) ?> - <?= formatTanggalIndonesia($currentPeriode['tanggal_tutup']) ?>
                    <?php if($currentPeriode['materi_terkait']): ?>
                      • <?= $materi_labels[$currentPeriode['materi_terkait']] ?? ucfirst($currentPeriode['materi_terkait']) ?>
                    <?php endif; ?>
                  </small>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

<?php if ($currentPeriode && !empty($pertanyaanData)): ?>
          <!-- Statistics Cards -->
          <div class="row mb-4">
            <div class="col-md-4 mb-3">
              <div class="card stats-card stats-card-mobile">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center stats-card-content">
                    <div class="flex-grow-1 stats-text-content">
                      <h6 class="mb-1 stats-title">Siswa Selesai</h6>
                      <h3 class="mb-0 stats-number"><?= number_format($currentPeriode['evaluasi_selesai']) ?></h3>
                      <small class="text-muted stats-subtitle">Telah menyelesaikan evaluasi</small>
                    </div>
                    <div class="stats-icon bg-primary-light stats-icon-mobile">
                      <i class="bi bi-check-circle text-primary"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-4 mb-3">
              <div class="card stats-card stats-card-mobile">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center stats-card-content">
                    <div class="flex-grow-1 stats-text-content">
                      <h6 class="mb-1 stats-title">Completion Rate</h6>
                      <h3 class="mb-0 stats-number">
                        <?php 
                        $completionRate = $currentPeriode['total_siswa_aktif'] > 0 ? 
                          round(($currentPeriode['evaluasi_selesai'] / $currentPeriode['total_siswa_aktif']) * 100, 1) : 0;
                        echo number_format($completionRate, 1);
                        ?>%
                      </h3>
                      <small class="text-muted stats-subtitle">Tingkat penyelesaian</small>
                    </div>
                    <div class="stats-icon bg-success-light stats-icon-mobile">
                      <i class="bi bi-graph-up text-success"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-4 mb-3">
              <div class="card stats-card stats-card-mobile">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center stats-card-content">
                    <div class="flex-grow-1 stats-text-content">
                      <h6 class="mb-1 stats-title">Rata-rata Rating</h6>
                      <h3 class="mb-0 stats-number">
                        <?php 
                        $avgRating = !empty($ratingData) ? round(array_sum(array_column($ratingData, 'average')) / count($ratingData), 1) : 0;
                        echo number_format($avgRating, 1);
                        ?>
                      </h3>
                      <small class="text-muted stats-subtitle">Rating keseluruhan</small>
                    </div>
                    <div class="stats-icon bg-warning-light stats-icon-mobile">
                      <i class="bi bi-star text-warning"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Row 1 -->
          <div class="row">
            <!-- Rating Overview -->
            <div class="col-lg-8 mb-4">
              <div class="chart-card">
                <div class="chart-title">
                  <i class="bi bi-bar-chart-fill text-primary"></i>
                  Rating Overview per Aspek
                </div>
                <div class="chart-container">
                  <canvas id="ratingChart"></canvas>
                </div>
              </div>
            </div>

            <!-- Completion Rate -->
            <div class="col-lg-4 mb-4">
              <div class="chart-card">
                <div class="chart-title">
                  <i class="bi bi-pie-chart-fill text-success"></i>
                  Tingkat Penyelesaian
                </div>
                <div class="chart-container small">
                  <canvas id="completionChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Row 2 -->
          <div class="row">
            <!-- Class Performance -->
            <?php if (!empty($classAverages)): ?>
            <div class="col-lg-6 mb-4">
              <div class="chart-card">
                <div class="chart-title">
                  <i class="bi bi-people-fill text-info"></i>
                  Performa per Kelas
                </div>
                <div class="chart-container">
                  <canvas id="classChart"></canvas>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <!-- Multiple Choice Distribution (First one) -->
            <?php if (!empty($multipleChoiceData)): ?>
            <div class="col-lg-<?= !empty($classAverages) ? '6' : '12' ?> mb-4">
              <div class="chart-card">
                <div class="chart-title">
                  <i class="bi bi-check2-square text-warning"></i>
                  Distribusi Jawaban Pilihan Ganda
                </div>
                <div class="chart-container">
                  <canvas id="multipleChoiceChart"></canvas>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Insights & Feedback -->
          <div class="row">
            <!-- Key Insights -->
            <div class="col-lg-8 mb-4">
              <div class="content-card">
                <div class="section-header">
                  <h5 class="mb-0 text-dark">
                    <i class="bi bi-lightbulb me-2"></i>Key Insights
                  </h5>
                </div>
                <div class="card-body">
                  <?php 
                  // Generate insights
                  $insights = [];
                  
                  if (!empty($ratingData)) {
                    $maxRating = max(array_column($ratingData, 'average'));
                    $minRating = min(array_column($ratingData, 'average'));
                    
                    $bestAspect = '';
                    $worstAspect = '';
                    
                    foreach ($ratingData as $data) {
                      if ($data['average'] == $maxRating) $bestAspect = $data['aspect'];
                      if ($data['average'] == $minRating) $worstAspect = $data['aspect'];
                    }
                    
                    if ($bestAspect) {
                      $insights[] = [
                        'title' => 'Aspek Terbaik',
                        'text' => "'{$bestAspect}' mendapat rating tertinggi ({$maxRating}/5). Pertahankan kualitas ini!"
                      ];
                    }
                    
                    if ($worstAspect && $minRating < 4) {
                      $insights[] = [
                        'title' => 'Area yang Perlu Diperbaiki',
                        'text' => "'{$worstAspect}' mendapat rating terendah ({$minRating}/5). Fokuskan perbaikan di area ini."
                      ];
                    }
                  }
                  
                  if ($completionRate < 80) {
                    $insights[] = [
                      'title' => 'Tingkat Partisipasi',
                      'text' => "Completion rate {$completionRate}% masih bisa ditingkatkan. Pertimbangkan reminder atau insentif untuk siswa."
                    ];
                  }
                  
                  if (!empty($classAverages)) {
                    $maxClass = array_search(max($classAverages), $classAverages);
                    $minClass = array_search(min($classAverages), $classAverages);
                    
                    if ($maxClass && $minClass && $maxClass != $minClass) {
                      $insights[] = [
                        'title' => 'Perbandingan Kelas',
                        'text' => "Kelas {$maxClass} memiliki rata-rata tertinggi (" . max($classAverages) . "), sementara {$minClass} terendah (" . min($classAverages) . ")."
                      ];
                    }
                  }
                  
                  if (empty($insights)) {
                    $insights[] = [
                      'title' => 'Status Baik',
                      'text' => 'Secara keseluruhan, evaluasi menunjukkan hasil yang positif dan memuaskan.'
                    ];
                  }
                  ?>
                  
                  <?php foreach ($insights as $insight): ?>
                    <div class="insight-card">
                      <div class="insight-title"><?= htmlspecialchars($insight['title']) ?></div>
                      <div class="insight-text"><?= htmlspecialchars($insight['text']) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Recent Feedback -->
            <div class="col-lg-4 mb-4">
              <div class="content-card">
                <div class="section-header">
                  <h5 class="mb-0 text-dark">
                    <i class="bi bi-chat-text me-2"></i>Sample Feedback
                  </h5>
                </div>
                <div class="card-body">
                  <?php 
                  $allFeedback = [];
                  foreach ($feedbackData as $aspect => $texts) {
                    foreach ($texts as $text) {
                      if (strlen(trim($text)) > 10) { // Filter out very short responses
                        $allFeedback[] = [
                          'aspect' => $aspect,
                          'text' => trim($text)
                        ];
                      }
                    }
                  }
                  
                  // Show random sample of feedback
                  shuffle($allFeedback);
                  $sampleFeedback = array_slice($allFeedback, 0, 5);
                  ?>
                  
                  <?php if (!empty($sampleFeedback)): ?>
                    <?php foreach ($sampleFeedback as $feedback): ?>
                      <div class="feedback-item">
                        <small class="text-muted d-block mb-1"><?= htmlspecialchars($feedback['aspect']) ?></small>
                        "<?= htmlspecialchars($feedback['text']) ?>"
                      </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($allFeedback) > 5): ?>
                      <div class="text-center mt-2">
                        <small class="text-muted">
                          +<?= count($allFeedback) - 5 ?> feedback lainnya
                        </small>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="text-muted text-center py-3">
                      <i class="bi bi-chat-text opacity-50 d-block mb-2"></i>
                      Belum ada feedback tersedia
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

        <?php elseif ($selectedPeriode > 0): ?>
          <!-- No Data State -->
          <div class="content-card">
            <div class="card-body">
              <div class="empty-state">
                <i class="bi bi-bar-chart"></i>
                <h5>Belum Ada Data Evaluasi</h5>
                <p>Periode evaluasi ini belum memiliki data yang cukup untuk dianalisis.</p>
              </div>
            </div>
          </div>

        <?php else: ?>
          <!-- No Period Selected -->
          <div class="content-card">
            <div class="card-body">
              <div class="empty-state">
                <i class="bi bi-search"></i>
                <h5>Pilih Periode Evaluasi</h5>
                <p>Silakan pilih periode evaluasi untuk melihat analisis dan visualisasi data.</p>
              </div>
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
  // Global chart configs
  Chart.defaults.font.family = "'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
  Chart.defaults.plugins.legend.labels.usePointStyle = true;

  function changePeriode() {
    const select = document.getElementById('periodeSelect');
    const selectedValue = select.value;
    if (selectedValue) {
      window.location.href = `?periode=${selectedValue}`;
    } else {
      window.location.href = 'index.php';
    }
  }

  <?php if ($currentPeriode && !empty($pertanyaanData)): ?>
  
  // Rating Chart
  <?php if (!empty($ratingData)): ?>
  const ratingCtx = document.getElementById('ratingChart').getContext('2d');
  new Chart(ratingCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($ratingData, 'aspect')) ?>,
      datasets: [{
        label: 'Rata-rata Rating',
        data: <?= json_encode(array_column($ratingData, 'average')) ?>,
        backgroundColor: 'rgba(59, 130, 246, 0.8)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 1,
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          max: 5,
          ticks: {
            stepSize: 1
          }
        },
        x: {
          ticks: {
            maxRotation: 45,
            minRotation: 0
          }
        }
      },
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });
  <?php endif; ?>

  // Completion Rate Chart
  const completionCtx = document.getElementById('completionChart').getContext('2d');
  new Chart(completionCtx, {
    type: 'doughnut',
    data: {
      labels: ['Selesai', 'Belum Selesai'],
      datasets: [{
        data: [
          <?= $currentPeriode['evaluasi_selesai'] ?>,
          <?= $currentPeriode['total_siswa_aktif'] - $currentPeriode['evaluasi_selesai'] ?>
        ],
        backgroundColor: [
          'rgba(34, 197, 94, 0.8)',
          'rgba(156, 163, 175, 0.8)'
        ],
        borderColor: [
          'rgba(34, 197, 94, 1)',
          'rgba(156, 163, 175, 1)'
        ],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });

  // Class Performance Chart
  <?php if (!empty($classAverages)): ?>
  const classCtx = document.getElementById('classChart').getContext('2d');
  new Chart(classCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($classAverages)) ?>,
      datasets: [{
        label: 'Rata-rata Rating',
        data: <?= json_encode(array_values($classAverages)) ?>,
        backgroundColor: 'rgba(14, 165, 233, 0.8)',
        borderColor: 'rgba(14, 165, 233, 1)',
        borderWidth: 1,
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          max: 5,
          ticks: {
            stepSize: 1
          }
        }
      },
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });
  <?php endif; ?>

  // Multiple Choice Chart (First dataset only)
  <?php if (!empty($multipleChoiceData)): ?>
  <?php $firstMCData = array_values($multipleChoiceData)[0]; ?>
  const mcCtx = document.getElementById('multipleChoiceChart').getContext('2d');
  new Chart(mcCtx, {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_keys($firstMCData)) ?>,
      datasets: [{
        data: <?= json_encode(array_values($firstMCData)) ?>,
        backgroundColor: [
          'rgba(245, 158, 11, 0.8)',
          'rgba(34, 197, 94, 0.8)',
          'rgba(239, 68, 68, 0.8)',
          'rgba(139, 92, 246, 0.8)',
          'rgba(6, 182, 212, 0.8)'
        ],
        borderColor: [
          'rgba(245, 158, 11, 1)',
          'rgba(34, 197, 94, 1)',
          'rgba(239, 68, 68, 1)',
          'rgba(139, 92, 246, 1)',
          'rgba(6, 182, 212, 1)'
        ],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
  <?php endif; ?>

  <?php endif; ?>
  </script>
</body>
</html>