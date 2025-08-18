<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'analisis-evaluasi';
$baseURL = '../';

// Query yang lebih akurat untuk daftar periode
$periodeQuery = "SELECT 
                    pe.*,
                    g.nama_gelombang,
                    g.tahun,
                    (SELECT COUNT(DISTINCT s.id_siswa) 
                     FROM siswa s 
                     JOIN kelas k ON s.id_kelas = k.id_kelas 
                     WHERE k.id_gelombang = pe.id_gelombang AND s.status_aktif = 'aktif') as total_siswa_aktif,
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
$selectedPeriodeId = isset($_GET['periode']) ? (int)$_GET['periode'] : ($periodeList[0]['id_periode'] ?? 0);
$currentPeriode = null;

// Efisiensi - Data detail periode diambil dari array $periodeList
if ($selectedPeriodeId > 0) {
    foreach ($periodeList as $p) {
        if ($p['id_periode'] == $selectedPeriodeId) {
            $currentPeriode = $p;
            break;
        }
    }
}

// Inisialisasi variabel untuk menghindari error jika tidak ada data
$pertanyaanData = [];
$evaluationData = [];
$analytics = [];
$ratingData = [];
$multipleChoiceData = [];
$feedbackData = [];
$classAverages = [];
$aspectAverages = [];

if ($currentPeriode) {
    // Get questions for this periode
    if ($currentPeriode['pertanyaan_terpilih']) {
        $pertanyaan_terpilih = json_decode($currentPeriode['pertanyaan_terpilih'], true);
        if (is_array($pertanyaan_terpilih) && !empty($pertanyaan_terpilih)) {
            $pertanyaan_ids = implode(',', array_map('intval', $pertanyaan_terpilih));
            $pertanyaanQuery = "SELECT p.id_pertanyaan, p.pertanyaan, p.aspek_dinilai, p.tipe_jawaban, p.pilihan_jawaban
                                FROM pertanyaan_evaluasi p
                                WHERE p.id_pertanyaan IN ($pertanyaan_ids)
                                ORDER BY p.aspek_dinilai, p.question_order, p.id_pertanyaan";
            
            $pertanyaanResult = mysqli_query($conn, $pertanyaanQuery);
            while ($pertanyaan = mysqli_fetch_assoc($pertanyaanResult)) {
                $pertanyaanData[] = $pertanyaan;
            }
        }
    }

    // Get comprehensive evaluation data with demographics
    if (!empty($pertanyaanData)) {
        $evaluationQuery = "SELECT 
                                je.id_pertanyaan, je.jawaban, je.answered_at,
                                s.nama as nama_siswa, s.nik, s.jenis_kelamin, s.pendidikan_terakhir, s.tempat_lahir,
                                YEAR(CURDATE()) - YEAR(s.tanggal_lahir) as usia,
                                k.nama_kelas, k.id_kelas,
                                e.id_evaluasi, e.tanggal_evaluasi,
                                i.nama as nama_instruktur
                            FROM jawaban_evaluasi je
                            JOIN evaluasi e ON je.id_evaluasi = e.id_evaluasi
                            JOIN siswa s ON je.id_siswa = s.id_siswa
                            JOIN kelas k ON s.id_kelas = k.id_kelas
                            LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
                            WHERE e.id_periode = ? AND e.status_evaluasi = 'selesai'
                            ORDER BY je.answered_at DESC";
        
        $stmt = mysqli_prepare($conn, $evaluationQuery);
        mysqli_stmt_bind_param($stmt, "i", $selectedPeriodeId);
        mysqli_stmt_execute($stmt);
        $evaluationResult = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($evaluationResult)) {
            $evaluationData[] = $row;
        }
    }

    // --- (BAGIAN ANALISIS DATA TETAP SAMA) ---

    $analytics = [
        'rating_analysis' => [], 'demographic_insights' => [], 'class_performance' => [],
        'aspect_performance' => [], 'satisfaction_levels' => [], 'response_patterns' => [],
        'improvement_areas' => [], 'multiple_choice' => [], 'text_feedback' => []
    ];

    function calculateStatistics($ratings) {
        if (empty($ratings)) return null;
        $count = count($ratings);
        $sum = array_sum($ratings);
        $mean = $sum / $count;
        sort($ratings);
        $middle = floor($count / 2);
        $median = $count % 2 ? $ratings[$middle] : ($ratings[$middle - 1] + $ratings[$middle]) / 2;
        $frequency = array_count_values($ratings);
        arsort($frequency);
        $mode = key($frequency);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $ratings)) / $count;
        $stdDev = sqrt($variance);
        $distribution = array_count_values($ratings);
        $satisfied = ($distribution[4] ?? 0) + ($distribution[5] ?? 0);
        $neutral = $distribution[3] ?? 0;
        $dissatisfied = ($distribution[1] ?? 0) + ($distribution[2] ?? 0);
        return [
            'count' => $count, 'mean' => round($mean, 2), 'median' => $median, 'mode' => $mode,
            'std_dev' => round($stdDev, 2), 'distribution' => $distribution,
            'satisfaction_rate' => round(($satisfied / $count) * 100, 1),
            'neutral_rate' => round(($neutral / $count) * 100, 1),
            'dissatisfaction_rate' => round(($dissatisfied / $count) * 100, 1)
        ];
    }

    $questionGroups = [];
    foreach ($evaluationData as $data) {
        $questionGroups[$data['id_pertanyaan']][] = $data;
    }

    foreach ($pertanyaanData as $pertanyaan) {
        $id_pertanyaan = $pertanyaan['id_pertanyaan'];
        $responses = $questionGroups[$id_pertanyaan] ?? [];
        if (empty($responses)) continue;

        if ($pertanyaan['tipe_jawaban'] == 'skala') {
            $ratings = []; $classRatings = [];
            $genderRatings = ['Laki-Laki' => [], 'Perempuan' => []];
            foreach ($responses as $response) {
                $rating = (int)trim($response['jawaban']);
                if ($rating >= 1 && $rating <= 5) {
                    $ratings[] = $rating;
                    $classRatings[$response['nama_kelas']][] = $rating;
                    if (isset($genderRatings[$response['jenis_kelamin']])) {
                        $genderRatings[$response['jenis_kelamin']][] = $rating;
                    }
                }
            }
            $stats = calculateStatistics($ratings);
            if ($stats) {
                $analytics['rating_analysis'][] = [
                    'question' => $pertanyaan['pertanyaan'], 'id_pertanyaan' => $id_pertanyaan,
                    'aspect' => $pertanyaan['aspek_dinilai'], 'statistics' => $stats,
                    'demographics' => [
                        'by_class' => array_map('calculateStatistics', array_filter($classRatings)),
                        'by_gender' => array_map(fn($r) => empty($r) ? null : calculateStatistics($r), $genderRatings),
                    ]
                ];
                if (!isset($analytics['aspect_performance'][$pertanyaan['aspek_dinilai']])) {
                    $analytics['aspect_performance'][$pertanyaan['aspek_dinilai']] = ['ratings' => [], 'count' => 0];
                }
                $analytics['aspect_performance'][$pertanyaan['aspek_dinilai']]['ratings'] = array_merge($analytics['aspect_performance'][$pertanyaan['aspek_dinilai']]['ratings'], $ratings);
                $analytics['aspect_performance'][$pertanyaan['aspek_dinilai']]['count']++;
            }
        } elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda') {
            $choices = json_decode($pertanyaan['pilihan_jawaban'], true) ?: [];
            $distribution = [];
            foreach($choices as $c) { $distribution[$c] = 0; }
            
            foreach ($responses as $response) {
                $answer = trim($response['jawaban']);
                if (in_array($answer, $choices)) {
                    $distribution[$answer]++;
                }
            }

            if (array_sum($distribution) > 0) {
                 $analytics['multiple_choice'][] = [
                    'aspect' => $pertanyaan['aspek_dinilai'], 'question' => $pertanyaan['pertanyaan'],
                    'id_pertanyaan' => $id_pertanyaan, 'choices' => $choices,
                    'distribution' => array_filter($distribution, fn($c) => $c >= 0)
                ];
            }
        } elseif ($pertanyaan['tipe_jawaban'] == 'isian') {
            $sentimentWords = [
                'positive' => ['baik', 'bagus', 'sangat', 'membantu', 'jelas', 'mudah', 'senang', 'puas', 'excellent', 'mantap', 'oke', 'memuaskan', 'hebat', 'luar biasa', 'profesional', 'terstruktur', 'bermanfaat'],
                'negative' => ['sulit', 'susah', 'kurang', 'tidak', 'buruk', 'jelek', 'bingung', 'kecewa', 'lambat', 'ribet', 'membosankan', 'berantakan', 'monoton', 'perlu perbaikan']
            ];
            $stopwords = ['dan', 'atau', 'yang', 'untuk', 'dari', 'dengan', 'pada', 'di', 'ke', 'oleh', 'ini', 'itu', 'adalah', 'akan', 'telah', 'sudah', 'saya', 'kami', 'kita', 'sehingga'];
            $textResponses = [];
            
            foreach ($responses as $response) {
                $text = trim($response['jawaban']);
                if (strlen($text) > 5) {
                    $sentiment = 'neutral'; $positiveCount = 0; $negativeCount = 0;
                    foreach ($sentimentWords['positive'] as $word) { if (stripos($text, $word) !== false) $positiveCount++; }
                    foreach ($sentimentWords['negative'] as $word) { if (stripos($text, $word) !== false) $negativeCount++; }
                    if ($positiveCount > $negativeCount) $sentiment = 'positive';
                    elseif ($negativeCount > $positiveCount) $sentiment = 'negative';
                    
                    $textResponses[] = [
                        'text' => $text, 'sentiment' => $sentiment,
                        'student' => $response['nama_siswa'], 'class' => $response['nama_kelas']
                    ];
                }
            }
            if(!empty($textResponses)) {
                $analytics['text_feedback'][] = [
                    'question' => $pertanyaan['pertanyaan'], 'id_pertanyaan' => $id_pertanyaan,
                    'aspect' => $pertanyaan['aspek_dinilai'], 'responses' => $textResponses,
                    'count' => count($textResponses)
                ];
            }
        }
    }

    // Convert enhanced data back to simple format for charts
    foreach ($analytics['rating_analysis'] as $data) {
        $ratingData[] = [
            'aspect' => $data['aspect'], 'pertanyaan' => $data['question'],
            'average' => $data['statistics']['mean'], 'count' => $data['statistics']['count'],
            'detail' => $data['statistics']['distribution']
        ];
    }
    
    if (isset($analytics['multiple_choice'])) {
        foreach ($analytics['multiple_choice'] as $data) {
            $multipleChoiceData[] = [
                'aspect' => $data['aspect'], 'pertanyaan' => $data['question'],
                'distribution' => $data['distribution'], 'total_responses' => array_sum($data['distribution'])
            ];
        }
    }

    if (isset($analytics['text_feedback'])) {
        foreach ($analytics['text_feedback'] as $data) {
            $feedbackData[] = [
                'aspect' => $data['aspect'], 'pertanyaan' => $data['question'],
                'responses' => array_column($data['responses'], 'text'), 'count' => $data['count']
            ];
        }
    }

    // Calculate class and aspect averages
    foreach ($analytics['rating_analysis'] as $data) {
        $aspect = $data['aspect'];
        if (!isset($aspectAverages[$aspect])) { $aspectAverages[$aspect] = []; }
        $aspectAverages[$aspect][] = $data['statistics']['mean'];
        
        foreach ($data['demographics']['by_class'] as $className => $classStats) {
            if ($classStats) {
                if (!isset($classAverages[$className])) { $classAverages[$className] = []; }
                $classAverages[$className][] = $classStats['mean'];
            }
        }
    }

    foreach ($aspectAverages as $aspect => $means) {
        if(!empty($means)) $aspectAverages[$aspect] = round(array_sum($means) / count($means), 2);
    }
    foreach ($classAverages as $class => $means) {
        if(!empty($means)) $classAverages[$class] = round(array_sum($means) / count($means), 2);
    }
}

// Helper function
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';
    $bulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
    $timestamp = strtotime($tanggal);
    return date('d', $timestamp) . ' ' . $bulan[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

$materi_labels = [
    'word' => 'Microsoft Word', 'excel' => 'Microsoft Excel', 
    'ppt' => 'Microsoft PowerPoint', 'internet' => 'Internet & Email'
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../../assets/css/fonts.css" />
    <link rel="stylesheet" href="../../../assets/css/styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .chart-container { position: relative; height: 300px; margin: 1rem 0; }
        .chart-card { background: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); margin-bottom: 1.5rem; }
        .chart-title { font-size: 1.1rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .chart-subtitle { font-size: 0.85rem; color: #6b7280; margin-bottom: 1rem; }
        .insight-card { background: #f8f9fa; border-left: 4px solid #3b82f6; padding: 1rem 1.5rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .insight-title { font-weight: 600; color: #1f2937; margin-bottom: 0.5rem; }
        .feedback-item { background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 0.5rem; font-size: 0.85rem; }
        .filter-section { background: white; padding: 1rem 1.5rem; border-radius: 0.75rem; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); margin-bottom: 1.5rem; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: #6b7280; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../../../includes/sidebar/admin.php'; ?>

        <div class="flex-fill main-content">
            <nav class="top-navbar">
                <div class="container-fluid px-3 px-md-4">
                    <div class="d-flex align-items-center">
                        <div class="d-flex align-items-center flex-grow-1">
                            <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
                            <div class="page-info">
                                <h2 class="page-title mb-1">DASHBOARD ANALISIS EVALUASI</h2>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb page-breadcrumb mb-0">
                                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="#">Evaluasi & Feedback</a></li>
                                        <li class="breadcrumb-item active" aria-current="page">Analisis Evaluasi</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid mt-4">
                <div class="filter-section">
                    <h6 class="mb-2"><i class="bi bi-filter me-2"></i>Filter Periode Evaluasi</h6>
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <select class="form-select" id="periodeSelect" onchange="changePeriode()">
                                <option value="">Pilih Periode Evaluasi...</option>
                                <?php foreach ($periodeList as $periode): ?>
                                    <option value="<?= $periode['id_periode'] ?>" <?= $selectedPeriodeId == $periode['id_periode'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($periode['nama_evaluasi']) ?> 
                                        (<?= htmlspecialchars($periode['nama_gelombang']) ?>) - 
                                        Partisipasi: <?= $periode['evaluasi_selesai'] ?>/<?= $periode['total_siswa_aktif'] ?> siswa
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                     <?php if ($currentPeriode): ?>
                     <div class="row mt-2">
                        <div class="col-12"><small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            <?= formatTanggalIndonesia($currentPeriode['tanggal_buka']) ?> - <?= formatTanggalIndonesia($currentPeriode['tanggal_tutup']) ?>
                            <?php if($currentPeriode['materi_terkait']): ?>
                                • Materi: <?= $materi_labels[$currentPeriode['materi_terkait']] ?? ucfirst($currentPeriode['materi_terkait']) ?>
                            <?php endif; ?>
                        </small></div>
                     </div>
                     <?php endif; ?>
                </div>

                <?php if ($currentPeriode && $currentPeriode['evaluasi_selesai'] > 0): ?>
                    
                    <div class="row mt-4">
                        <?php if (!empty($aspectAverages)): ?>
                        <div class="col-lg-8 mb-4">
                            <div class="chart-card">
                                <div class="chart-title"><i class="bi bi-bar-chart-fill text-primary"></i>Rata-rata Rating per Aspek</div>
                                <div class="chart-container"><canvas id="aspectChart"></canvas></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-lg-4 mb-4">
                             <div class="chart-card">
                                <div class="chart-title"><i class="bi bi-pie-chart-fill text-success"></i>Diagram Partisipasi</div>
                                <div class="chart-container"><canvas id="completionChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($classAverages)): ?>
                    <div class="row">
                        <div class="col-12 mb-4">
                             <div class="chart-card">
                                <div class="chart-title"><i class="bi bi-people-fill text-info"></i>Perbandingan Performa per Kelas</div>
                                <div class="chart-container"><canvas id="classChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($multipleChoiceData)): ?>
                    <div class="row">
                        <div class="col-12">
                            <h4 class="mb-3">Analisis Jawaban Pilihan Ganda</h4>
                        </div>
                        <?php foreach ($multipleChoiceData as $index => $mcData): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="chart-card">
                                <div class="chart-title"><i class="bi bi-check2-square text-warning"></i><?= htmlspecialchars($mcData['aspect']) ?></div>
                                <div class="chart-subtitle fst-italic">"<?= htmlspecialchars($mcData['pertanyaan']) ?>"</div>
                                <div class="chart-container">
                                    <canvas id="multipleChoiceChart-<?= $index ?>"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                <?php elseif ($selectedPeriodeId > 0): ?>
                    <div class="content-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="bi bi-bar-chart-line"></i>
                                <h5>Belum Ada Data Evaluasi</h5>
                                <p>Belum ada siswa yang menyelesaikan evaluasi untuk periode ini.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="content-card">
                         <div class="card-body">
                            <div class="empty-state">
                                <i class="bi bi-search"></i>
                                <h5>Pilih Periode Evaluasi</h5>
                                <p>Silakan pilih periode evaluasi dari menu di atas untuk melihat analisis data.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/scripts.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Chart.defaults.font.family = "'Poppins', sans-serif";
        Chart.defaults.plugins.legend.labels.usePointStyle = true;

        <?php if ($currentPeriode && $currentPeriode['evaluasi_selesai'] > 0): ?>
        
        // Aspect Rating Chart
        <?php if (!empty($aspectAverages)): ?>
        new Chart(document.getElementById('aspectChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($aspectAverages)) ?>,
                datasets: [{
                    label: 'Rata-rata Rating',
                    data: <?= json_encode(array_values($aspectAverages)) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1, borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                scales: { x: { beginAtZero: true, max: 5 } },
                plugins: { legend: { display: false } }
            }
        });
        <?php endif; ?>

        // Completion Rate Chart
        new Chart(document.getElementById('completionChart'), {
            type: 'doughnut',
            data: {
                labels: ['Selesai', 'Belum Selesai'],
                datasets: [{
                    data: [<?= $currentPeriode['evaluasi_selesai'] ?>, <?= $currentPeriode['total_siswa_aktif'] - $currentPeriode['evaluasi_selesai'] ?>],
                    backgroundColor: ['rgba(34, 197, 94, 0.8)', 'rgba(209, 213, 219, 0.8)'],
                    borderColor: ['#ffffff'], borderWidth: 3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // Class Performance Chart
        <?php if (!empty($classAverages)): ?>
        new Chart(document.getElementById('classChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($classAverages)) ?>,
                datasets: [{
                    label: 'Rata-rata Rating',
                    data: <?= json_encode(array_values($classAverages)) ?>,
                    backgroundColor: 'rgba(14, 165, 233, 0.8)',
                    borderColor: 'rgba(14, 165, 233, 1)',
                    borderWidth: 1, borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 5 } },
                plugins: { legend: { display: false } }
            }
        });
        <?php endif; ?>
        
        // Multiple Choice Charts
        <?php if (!empty($multipleChoiceData)): ?>
        const multipleChoiceData = <?= json_encode($multipleChoiceData) ?>;
        const mcColors = ['rgba(245, 158, 11, 0.8)', 'rgba(34, 197, 94, 0.8)', 'rgba(239, 68, 68, 0.8)', 'rgba(139, 92, 246, 0.8)', 'rgba(6, 182, 212, 0.8)', 'rgba(217, 70, 239, 0.8)'];
        
        multipleChoiceData.forEach((data, index) => {
            new Chart(document.getElementById(`multipleChoiceChart-${index}`), {
                type: 'pie',
                data: {
                    labels: Object.keys(data.distribution),
                    datasets: [{
                        data: Object.values(data.distribution),
                        backgroundColor: mcColors,
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'bottom', labels: { padding: 15, boxWidth: 12 } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>

        <?php endif; ?>
    });

    function changePeriode() {
        const select = document.getElementById('periodeSelect');
        if (select.value) {
            window.location.href = `?periode=${select.value}`;
        } else {
            window.location.href = 'index.php';
        }
    }

    function cetakLaporanExcel() {
    const periodeSelect = document.getElementById('periodeSelect');
    const selectedPeriode = periodeSelect?.value || '';

    if (!selectedPeriode) {
        Swal.fire('Error', 'Silakan pilih periode evaluasi terlebih dahulu.', 'warning');
        return;
    }

    // Tampilkan loading
    Swal.fire({
        title: 'Mempersiapkan Laporan',
        text: 'Sedang mengambil data dan grafik...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Objek untuk menampung gambar grafik dalam format base64
    const chartImages = {};
    
    // "Potret" setiap grafik yang ada di halaman
    const completionChart = Chart.getChart('completionChart');
    if (completionChart) {
        chartImages.completionChart = completionChart.toBase64Image('image/png', 1.0);
    }

    const aspectChart = Chart.getChart('aspectChart');
    if (aspectChart) {
        chartImages.aspectChart = aspectChart.toBase64Image('image/png', 1.0);
    }

    const classChart = Chart.getChart('classChart');
    if (classChart) {
        chartImages.classChart = classChart.toBase64Image('image/png', 1.0);
    }
    
    // Buat form tak terlihat untuk mengirim data via POST
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cetak_laporan.php'; // Arahkan ke skrip pencetakan
    form.target = '_blank'; // Buka di tab baru

    // Tambahkan input untuk ID periode
    const periodeInput = document.createElement('input');
    periodeInput.type = 'hidden';
    periodeInput.name = 'periode';
    periodeInput.value = selectedPeriode;
    form.appendChild(periodeInput);

    // Tambahkan input untuk data gambar grafik (dalam format JSON)
    const chartsInput = document.createElement('input');
    chartsInput.type = 'hidden';
    chartsInput.name = 'chartImages';
    chartsInput.value = JSON.stringify(chartImages);
    form.appendChild(chartsInput);

    // Tambahkan form ke body, kirim, lalu hapus
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    // Tutup notifikasi loading setelah beberapa saat
    setTimeout(() => {
        Swal.close();
    }, 1500);
}

// Jangan lupa ganti pemanggilan fungsi di tombol cetak Anda
// Contoh: <button onclick="cetakLaporanExcel()">Cetak Laporan</button>

    </script>
</body>
</html>