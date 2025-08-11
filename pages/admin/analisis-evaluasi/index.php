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
    // IMPROVED: Get detailed data with more metrics
    $detailQuery = "SELECT 
                        pe.*,
                        g.nama_gelombang,
                        g.tahun,
                        COUNT(DISTINCT e.id_evaluasi) as total_evaluasi,
                        COUNT(DISTINCT CASE WHEN e.status_evaluasi = 'selesai' THEN e.id_evaluasi END) as evaluasi_selesai,
                        (SELECT COUNT(DISTINCT s.id_siswa) 
                         FROM siswa s 
                         JOIN kelas k ON s.id_kelas = k.id_kelas 
                         WHERE k.id_gelombang = pe.id_gelombang AND s.status_aktif = 'aktif') as total_siswa_aktif,
                        (SELECT COUNT(DISTINCT k.id_kelas)
                         FROM kelas k 
                         WHERE k.id_gelombang = pe.id_gelombang) as total_kelas
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
                                ORDER BY p.aspek_dinilai, p.question_order, p.id_pertanyaan";
            
            $pertanyaanResult = mysqli_query($conn, $pertanyaanQuery);
            while ($pertanyaan = mysqli_fetch_assoc($pertanyaanResult)) {
                $pertanyaanData[] = $pertanyaan;
            }
        }
    }

    // IMPROVED: Get comprehensive evaluation data with demographics
    $evaluationData = [];
    if (!empty($pertanyaanData)) {
        $evaluationQuery = "SELECT 
                           je.id_pertanyaan,
                           je.jawaban,
                           je.answered_at,
                           s.nama as nama_siswa,
                           s.nik,
                           s.jenis_kelamin,
                           s.pendidikan_terakhir,
                           s.tempat_lahir,
                           YEAR(CURDATE()) - YEAR(s.tanggal_lahir) as usia,
                           k.nama_kelas,
                           k.id_kelas,
                           e.id_evaluasi,
                           e.tanggal_evaluasi,
                           i.nama as nama_instruktur
                         FROM jawaban_evaluasi je
                         JOIN evaluasi e ON je.id_evaluasi = e.id_evaluasi
                         JOIN siswa s ON je.id_siswa = s.id_siswa
                         JOIN kelas k ON s.id_kelas = k.id_kelas
                         LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
                         WHERE e.id_periode = ? AND e.status_evaluasi = 'selesai'
                         ORDER BY je.answered_at DESC";
        
        $stmt = mysqli_prepare($conn, $evaluationQuery);
        mysqli_stmt_bind_param($stmt, "i", $selectedPeriode);
        mysqli_stmt_execute($stmt);
        $evaluationResult = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($evaluationResult)) {
            $evaluationData[] = $row;
        }
    }

    // IMPROVED: Enhanced data processing with statistical analysis
    $analytics = [
        'rating_analysis' => [],
        'demographic_insights' => [],
        'class_performance' => [],
        'aspect_performance' => [],
        'satisfaction_levels' => [],
        'response_patterns' => [],
        'improvement_areas' => []
    ];

    // Enhanced processing functions
    function calculateStatistics($ratings) {
        if (empty($ratings)) return null;
        
        sort($ratings);
        $count = count($ratings);
        $sum = array_sum($ratings);
        $mean = $sum / $count;
        
        // Median
        $middle = floor($count / 2);
        $median = $count % 2 ? $ratings[$middle] : ($ratings[$middle - 1] + $ratings[$middle]) / 2;
        
        // Mode
        $frequency = array_count_values($ratings);
        $maxFreq = max($frequency);
        $mode = array_keys($frequency, $maxFreq)[0];
        
        // Standard Deviation
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $ratings)) / $count;
        $stdDev = sqrt($variance);
        
        // Distribution
        $distribution = array_count_values($ratings);
        
        // Satisfaction metrics
        $satisfied = ($distribution[4] ?? 0) + ($distribution[5] ?? 0);
        $neutral = $distribution[3] ?? 0;
        $dissatisfied = ($distribution[1] ?? 0) + ($distribution[2] ?? 0);
        
        return [
            'count' => $count,
            'mean' => round($mean, 2),
            'median' => $median,
            'mode' => $mode,
            'std_dev' => round($stdDev, 2),
            'distribution' => $distribution,
            'satisfaction_rate' => round(($satisfied / $count) * 100, 1),
            'neutral_rate' => round(($neutral / $count) * 100, 1),
            'dissatisfaction_rate' => round(($dissatisfied / $count) * 100, 1)
        ];
    }

    // Group data by question for enhanced analysis
    $questionGroups = [];
    foreach ($evaluationData as $data) {
        $questionGroups[$data['id_pertanyaan']][] = $data;
    }

    // Process each question type with improved analytics
    foreach ($pertanyaanData as $pertanyaan) {
        $id_pertanyaan = $pertanyaan['id_pertanyaan'];
        $responses = $questionGroups[$id_pertanyaan] ?? [];
        
        if (empty($responses)) continue;

        if ($pertanyaan['tipe_jawaban'] == 'skala') {
            // ENHANCED: Rating analysis with demographic breakdown
            $ratings = [];
            $classRatings = [];
            $genderRatings = ['Laki-Laki' => [], 'Perempuan' => []];
            $educationRatings = [];
            $ageGroupRatings = ['18-25' => [], '26-35' => [], '36-45' => [], '46+' => []];
            
            foreach ($responses as $response) {
                $rating = (int)trim($response['jawaban']);
                if ($rating >= 1 && $rating <= 5) {
                    $ratings[] = $rating;
                    
                    // Class breakdown
                    $classRatings[$response['nama_kelas']][] = $rating;
                    
                    // Gender breakdown
                    if (isset($genderRatings[$response['jenis_kelamin']])) {
                        $genderRatings[$response['jenis_kelamin']][] = $rating;
                    }
                    
                    // Education breakdown
                    $educationRatings[$response['pendidikan_terakhir']][] = $rating;
                    
                    // Age group breakdown
                    $age = (int)$response['usia'];
                    if ($age <= 25) $ageGroupRatings['18-25'][] = $rating;
                    elseif ($age <= 35) $ageGroupRatings['26-35'][] = $rating;
                    elseif ($age <= 45) $ageGroupRatings['36-45'][] = $rating;
                    else $ageGroupRatings['46+'][] = $rating;
                }
            }
            
            $stats = calculateStatistics($ratings);
            if ($stats) {
                $analytics['rating_analysis'][] = [
                    'aspect' => $pertanyaan['aspek_dinilai'],
                    'question' => $pertanyaan['pertanyaan'],
                    'id_pertanyaan' => $id_pertanyaan,
                    'statistics' => $stats,
                    'demographics' => [
                        'by_class' => array_map(function($classRatings) {
                            return calculateStatistics($classRatings);
                        }, array_filter($classRatings)),
                        'by_gender' => array_map(function($genderRatings) {
                            return empty($genderRatings) ? null : calculateStatistics($genderRatings);
                        }, $genderRatings),
                        'by_education' => array_map(function($eduRatings) {
                            return calculateStatistics($eduRatings);
                        }, array_filter($educationRatings)),
                        'by_age' => array_map(function($ageRatings) {
                            return empty($ageRatings) ? null : calculateStatistics($ageRatings);
                        }, $ageGroupRatings)
                    ]
                ];
                
                // Aspect performance summary
                if (!isset($analytics['aspect_performance'][$pertanyaan['aspek_dinilai']])) {
                    $analytics['aspect_performance'][$pertanyaan['aspek_dinilai']] = [
                        'ratings' => [],
                        'questions_count' => 0
                    ];
                }
                $analytics['aspect_performance'][$pertanyaan['aspek_dinilai']]['ratings'] = array_merge(
                    $analytics['aspect_performance'][$pertanyaan['aspek_dinilai']]['ratings'], 
                    $ratings
                );
                $analytics['aspect_performance'][$pertanyaan['aspek_dinilai']]['questions_count']++;
            }
            
        } elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda') {
            // ENHANCED: Multiple choice analysis with cross-tabulation
            $choices = json_decode($pertanyaan['pilihan_jawaban'], true) ?: [];
            $distribution = [];
            $classDistribution = [];
            $genderDistribution = ['Laki-Laki' => [], 'Perempuan' => []];
            
            foreach ($responses as $response) {
                $answer = trim($response['jawaban']);
                $choiceIndex = array_search($answer, $choices);
                
                if ($choiceIndex !== false) {
                    $choiceLabel = chr(65 + $choiceIndex);
                    $distribution[$choiceLabel] = ($distribution[$choiceLabel] ?? 0) + 1;
                    
                    // Class distribution
                    $kelas = $response['nama_kelas'];
                    if (!isset($classDistribution[$kelas])) {
                        $classDistribution[$kelas] = [];
                    }
                    $classDistribution[$kelas][$choiceLabel] = ($classDistribution[$kelas][$choiceLabel] ?? 0) + 1;
                    
                    // Gender distribution
                    $gender = $response['jenis_kelamin'];
                    if (isset($genderDistribution[$gender])) {
                        $genderDistribution[$gender][$choiceLabel] = ($genderDistribution[$gender][$choiceLabel] ?? 0) + 1;
                    }
                } else {
                    // Partial match fallback
                    foreach ($choices as $idx => $option) {
                        if (stripos($option, $answer) !== false || stripos($answer, $option) !== false) {
                            $choiceLabel = chr(65 + $idx);
                            $distribution[$choiceLabel] = ($distribution[$choiceLabel] ?? 0) + 1;
                            break;
                        }
                    }
                }
            }
            
            if (!empty($distribution)) {
                $totalResponses = array_sum($distribution);
                $dominantChoice = array_keys($distribution, max($distribution))[0] ?? null;
                
                $analytics['multiple_choice'][] = [
                    'aspect' => $pertanyaan['aspek_dinilai'],
                    'question' => $pertanyaan['pertanyaan'],
                    'id_pertanyaan' => $id_pertanyaan,
                    'choices' => $choices,
                    'distribution' => $distribution,
                    'percentages' => array_map(function($count) use ($totalResponses) {
                        return round(($count / $totalResponses) * 100, 1);
                    }, $distribution),
                    'demographics' => [
                        'by_class' => $classDistribution,
                        'by_gender' => array_filter($genderDistribution)
                    ],
                    'total_responses' => $totalResponses,
                    'dominant_choice' => $dominantChoice,
                    'consensus_level' => $dominantChoice ? round((max($distribution) / $totalResponses) * 100, 1) : 0
                ];
            }
            
        } elseif ($pertanyaan['tipe_jawaban'] == 'isian') {
            // ENHANCED: Text analysis with sentiment and keywords
            $textResponses = [];
            $wordCounts = [];
            $sentimentAnalysis = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
            $keywordFrequency = [];
            
            // Enhanced sentiment keywords (Indonesian)
            $sentimentWords = [
                'positive' => ['baik', 'bagus', 'sangat', 'membantu', 'jelas', 'mudah', 'senang', 'puas', 'excellent', 'mantap', 'oke', 'memuaskan', 'hebat'],
                'negative' => ['sulit', 'susah', 'kurang', 'tidak', 'buruk', 'jelek', 'bingung', 'kecewa', 'lambat', 'ribet', 'membosankan']
            ];
            
            // Common stopwords (Indonesian)
            $stopwords = ['dan', 'atau', 'yang', 'untuk', 'dari', 'dengan', 'pada', 'di', 'ke', 'oleh', 'ini', 'itu', 'adalah', 'akan', 'telah', 'sudah', 'saya', 'kami', 'kita'];
            
            foreach ($responses as $response) {
                $text = trim($response['jawaban']);
                if (strlen($text) > 5) {
                    $wordCount = str_word_count($text);
                    $wordCounts[] = $wordCount;
                    
                    // Simple sentiment analysis
                    $sentiment = 'neutral';
                    $positiveCount = 0;
                    $negativeCount = 0;
                    
                    foreach ($sentimentWords['positive'] as $word) {
                        if (stripos($text, $word) !== false) $positiveCount++;
                    }
                    foreach ($sentimentWords['negative'] as $word) {
                        if (stripos($text, $word) !== false) $negativeCount++;
                    }
                    
                    if ($positiveCount > $negativeCount) {
                        $sentiment = 'positive';
                        $sentimentAnalysis['positive']++;
                    } elseif ($negativeCount > $positiveCount) {
                        $sentiment = 'negative';
                        $sentimentAnalysis['negative']++;
                    } else {
                        $sentimentAnalysis['neutral']++;
                    }
                    
                    // Extract keywords (simple approach)
                    $words = array_filter(
                        array_map('trim', explode(' ', strtolower($text))),
                        function($word) use ($stopwords) {
                            return strlen($word) > 3 && !in_array($word, $stopwords);
                        }
                    );
                    
                    foreach ($words as $word) {
                        $keywordFrequency[$word] = ($keywordFrequency[$word] ?? 0) + 1;
                    }
                    
                    $textResponses[] = [
                        'text' => $text,
                        'word_count' => $wordCount,
                        'sentiment' => $sentiment,
                        'class' => $response['nama_kelas'],
                        'student' => $response['nama_siswa'],
                        'gender' => $response['jenis_kelamin']
                    ];
                }
            }
            
            if (!empty($textResponses)) {
                // Get top keywords
                arsort($keywordFrequency);
                $topKeywords = array_slice($keywordFrequency, 0, 10, true);
                
                $analytics['text_feedback'][] = [
                    'aspect' => $pertanyaan['aspek_dinilai'],
                    'question' => $pertanyaan['pertanyaan'],
                    'id_pertanyaan' => $id_pertanyaan,
                    'responses' => $textResponses,
                    'statistics' => [
                        'total_responses' => count($textResponses),
                        'avg_word_count' => empty($wordCounts) ? 0 : round(array_sum($wordCounts) / count($wordCounts), 1),
                        'sentiment_distribution' => $sentimentAnalysis,
                        'response_quality' => [
                            'short' => count(array_filter($wordCounts, function($c) { return $c < 5; })),
                            'medium' => count(array_filter($wordCounts, function($c) { return $c >= 5 && $c < 15; })),
                            'detailed' => count(array_filter($wordCounts, function($c) { return $c >= 15; }))
                        ]
                    ],
                    'top_keywords' => $topKeywords,
                    'sentiment_percentage' => [
                        'positive' => round(($sentimentAnalysis['positive'] / count($textResponses)) * 100, 1),
                        'negative' => round(($sentimentAnalysis['negative'] / count($textResponses)) * 100, 1),
                        'neutral' => round(($sentimentAnalysis['neutral'] / count($textResponses)) * 100, 1)
                    ]
                ];
            }
        }
    }

    // Calculate enhanced overall metrics
    $overallMetrics = [];
    
    // Overall satisfaction calculation
    $allRatings = [];
    foreach ($analytics['rating_analysis'] as $ratingData) {
        $stats = $ratingData['statistics'];
        for ($i = 1; $i <= 5; $i++) {
            $count = $stats['distribution'][$i] ?? 0;
            $allRatings = array_merge($allRatings, array_fill(0, $count, $i));
        }
    }
    
    if (!empty($allRatings)) {
        $overallStats = calculateStatistics($allRatings);
        $overallMetrics['satisfaction'] = $overallStats;
    }

    // Prepare simplified data for existing charts (backward compatibility)
    $ratingData = [];
    $multipleChoiceData = [];
    $feedbackData = [];
    $classAverages = [];
    $aspectAverages = [];

    // Convert enhanced data back to simple format for charts
    foreach ($analytics['rating_analysis'] as $data) {
        $ratingData[] = [
            'aspect' => $data['aspect'],
            'pertanyaan' => $data['question'],
            'average' => $data['statistics']['mean'],
            'count' => $data['statistics']['count'],
            'detail' => $data['statistics']['distribution']
        ];
    }

    if (isset($analytics['multiple_choice'])) {
        foreach ($analytics['multiple_choice'] as $data) {
            $distribution = [];
            foreach ($data['distribution'] as $choice => $count) {
                $distribution[$choice] = [
                    'count' => $count,
                    'text' => $data['choices'][ord($choice) - 65] ?? $choice
                ];
            }
            
            $multipleChoiceData[] = [
                'aspect' => $data['aspect'],
                'pertanyaan' => $data['question'],
                'distribution' => $distribution,
                'total_responses' => $data['total_responses']
            ];
        }
    }

    if (isset($analytics['text_feedback'])) {
        foreach ($analytics['text_feedback'] as $data) {
            $feedbackData[] = [
                'aspect' => $data['aspect'],
                'pertanyaan' => $data['question'],
                'responses' => array_column($data['responses'], 'text'),
                'count' => $data['statistics']['total_responses']
            ];
        }
    }

    // Calculate class and aspect averages
    foreach ($analytics['rating_analysis'] as $data) {
        $aspect = $data['aspect'];
        if (!isset($aspectAverages[$aspect])) {
            $aspectAverages[$aspect] = [];
        }
        $aspectAverages[$aspect][] = $data['statistics']['mean'];
        
        foreach ($data['demographics']['by_class'] as $className => $classStats) {
            if ($classStats && !isset($classAverages[$className])) {
                $classAverages[$className] = [];
            }
            if ($classStats) {
                $classAverages[$className][] = $classStats['mean'];
            }
        }
    }

    // Average the averages
    foreach ($aspectAverages as $aspect => $means) {
        $aspectAverages[$aspect] = round(array_sum($means) / count($means), 1);
    }

    foreach ($classAverages as $class => $means) {
        $classAverages[$class] = round(array_sum($means) / count($means), 1);
    }

} else {
    $currentPeriode = null;
    $pertanyaanData = [];
    $analytics = [];
    $overallMetrics = [];
    $ratingData = [];
    $multipleChoiceData = [];
    $feedbackData = [];
    $classAverages = [];
    $aspectAverages = [];
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
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
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

  .feedback-meta {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
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

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
  }

  .stat-card {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    text-align: center;
  }

  .stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 0.5rem;
  }

  .stat-label {
    color: #6b7280;
    font-size: 0.9rem;
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
          <div class="row align-items-center mb-3">
            <div class="col-md-8">
              <h6 class="mb-2">
                <i class="bi bi-filter me-2"></i>Filter Periode Evaluasi
              </h6>
              <div class="d-flex align-items-center gap-3">
                <div class="flex-grow-1">
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
                <div class="flex-shrink-0">
                  <button type="button" 
                          class="btn btn-cetak-soft" 
                          onclick="cetakLaporanPDF()" 
                          id="btnCetakPDF"
                          title="Cetak laporan analisis evaluasi dalam format PDF"
                          <?php 
                          $hasData = !empty($ratingData) || !empty($multipleChoiceData) || !empty($feedbackData);
                          $hasCompletedEvaluation = $currentPeriode && $currentPeriode['evaluasi_selesai'] > 0;
                          
                          if (!$hasData || !$hasCompletedEvaluation) {
                              echo 'disabled';
                          }
                          ?>>
                    <i class="bi bi-printer me-2"></i>
                    <?php if ($hasData && $hasCompletedEvaluation): ?>
                      Cetak Data
                    <?php else: ?>
                      Tidak Ada Data
                    <?php endif; ?>
                  </button>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <?php if ($currentPeriode): ?>
          <div class="row">
            <div class="col-12">
              <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                <?= $currentPeriode['nama_gelombang'] ?> • 
                <?= formatTanggalIndonesia($currentPeriode['tanggal_buka']) ?> - <?= formatTanggalIndonesia($currentPeriode['tanggal_tutup']) ?>
                <?php if($currentPeriode['materi_terkait']): ?>
                  • <?= $materi_labels[$currentPeriode['materi_terkait']] ?? ucfirst($currentPeriode['materi_terkait']) ?>
                <?php endif; ?>
              </small>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($currentPeriode && !empty($pertanyaanData)): ?>
          <!-- Statistics Cards -->
          <div class="row mb-4">
            <div class="col-md-3 mb-3">
              <div class="card stats-card stats-card-mobile">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center stats-card-content">
                    <div class="flex-grow-1 stats-text-content">
                      <h6 class="mb-1 stats-title">Siswa Selesai</h6>
                      <h3 class="mb-0 stats-number"><?= number_format($currentPeriode['evaluasi_selesai']) ?></h3>
                      <small class="text-muted stats-subtitle">Total responses</small>
                    </div>
                    <div class="stats-icon bg-primary-light stats-icon-mobile">
                      <i class="bi bi-check-circle text-primary"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
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
                      <small class="text-muted stats-subtitle">Participation rate</small>
                    </div>
                    <div class="stats-icon bg-success-light stats-icon-mobile">
                      <i class="bi bi-graph-up text-success"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="card stats-card stats-card-mobile">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center stats-card-content">
                    <div class="flex-grow-1 stats-text-content">
                      <h6 class="mb-1 stats-title">Rata-rata Rating</h6>
                      <h3 class="mb-0 stats-number">
                        <?php 
                        $avgRating = !empty($aspectAverages) ? round(array_sum($aspectAverages) / count($aspectAverages), 1) : 0;
                        echo number_format($avgRating, 1);
                        ?>
                      </h3>
                      <small class="text-muted stats-subtitle">Overall satisfaction</small>
                    </div>
                    <div class="stats-icon bg-warning-light stats-icon-mobile">
                      <i class="bi bi-star text-warning"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="card stats-card stats-card-mobile">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center stats-card-content">
                    <div class="flex-grow-1 stats-text-content">
                      <h6 class="mb-1 stats-title">Total Feedback</h6>
                      <h3 class="mb-0 stats-number">
                        <?php 
                        $totalFeedback = 0;
                        foreach ($feedbackData as $data) {
                            $totalFeedback += $data['count'];
                        }
                        echo number_format($totalFeedback);
                        ?>
                      </h3>
                      <small class="text-muted stats-subtitle">Written responses</small>
                    </div>
                    <div class="stats-icon bg-info-light stats-icon-mobile">
                      <i class="bi bi-chat-text text-info"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Row 1 -->
          <div class="row">
            <!-- Rating Overview by Aspect -->
            <?php if (!empty($aspectAverages)): ?>
            <div class="col-lg-8 mb-4">
              <div class="chart-card">
                <div class="chart-title">
                  <i class="bi bi-bar-chart-fill text-primary"></i>
                  Rating Overview per Aspek
                </div>
                <div class="chart-container">
                  <canvas id="aspectChart"></canvas>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <!-- Completion Rate -->
            <div class="col-lg-<?= !empty($aspectAverages) ? '4' : '6' ?> mb-4">
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

            <!-- Multiple Choice Distribution -->
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
                <div class="mt-2">
                  <small class="text-muted">
                    Aspek: <?= htmlspecialchars($multipleChoiceData[0]['aspect']) ?>
                  </small>
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
                  // Generate insights berdasarkan data yang sebenarnya
                  $insights = [];
                  
                  if (!empty($aspectAverages)) {
                    $maxRating = max($aspectAverages);
                    $minRating = min($aspectAverages);
                    
                    $bestAspect = array_search($maxRating, $aspectAverages);
                    $worstAspect = array_search($minRating, $aspectAverages);
                    
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
                  
                  // Insight dari pilihan ganda
                  if (!empty($multipleChoiceData)) {
                    foreach ($multipleChoiceData as $mcData) {
                      $maxChoice = '';
                      $maxCount = 0;
                      foreach ($mcData['distribution'] as $choice => $data) {
                        if ($data['count'] > $maxCount) {
                          $maxCount = $data['count'];
                          $maxChoice = $choice;
                        }
                      }
                      if ($maxChoice) {
                        $percentage = round(($maxCount / $mcData['total_responses']) * 100, 1);
                        $insights[] = [
                          'title' => 'Pilihan Dominan',
                          'text' => "Pada aspek '{$mcData['aspect']}', {$percentage}% siswa memilih opsi {$maxChoice}."
                        ];
                      }
                      break; // Hanya tampilkan insight untuk data pertama
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
                  foreach ($feedbackData as $data) {
                    foreach ($data['responses'] as $text) {
                      if (strlen(trim($text)) > 10) {
                        $allFeedback[] = [
                          'aspect' => $data['aspect'],
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
                        <div class="feedback-meta"><?= htmlspecialchars($feedback['aspect']) ?></div>
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

          <!-- Detailed Rating Analysis -->
          <?php if (!empty($ratingData)): ?>
          <div class="row">
            <div class="col-12 mb-4">
              <div class="content-card">
                <div class="section-header">
                  <h5 class="mb-0 text-dark">
                    <i class="bi bi-graph-up me-2"></i>Analisis Detail Rating
                  </h5>
                </div>
                <div class="card-body">
                  <div class="row">
                    <?php foreach ($ratingData as $data): ?>
                      <div class="col-lg-6 col-xl-4 mb-3">
                        <div class="border rounded p-3">
                          <h6 class="mb-2"><?= htmlspecialchars($data['aspect']) ?></h6>
                          <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary me-2"><?= $data['average'] ?>/5</span>
                            <small class="text-muted"><?= $data['count'] ?> responses</small>
                          </div>
                          
                          <!-- Rating distribution -->
                          <div class="mt-2">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                              <?php 
                              $count = $data['detail'][$i] ?? 0;
                              $percentage = $data['count'] > 0 ? round(($count / $data['count']) * 100, 1) : 0;
                              ?>
                              <div class="d-flex align-items-center mb-1">
                                <span class="me-2" style="width: 15px; font-size: 0.8rem;"><?= $i ?>★</span>
                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                  <div class="progress-bar bg-warning" style="width: <?= $percentage ?>%"></div>
                                </div>
                                <span style="width: 35px; font-size: 0.75rem;"><?= $count ?></span>
                              </div>
                            <?php endfor; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

        <?php elseif ($selectedPeriode > 0): ?>
          <!-- No Data State -->
          <div class="content-card">
            <div class="card-body">
              <div class="empty-state">
                <i class="bi bi-bar-chart"></i>
                <h5>Belum Ada Data Evaluasi</h5>
                <p>Periode evaluasi ini belum memiliki data yang cukup untuk dianalisis.</p>
                <?php if ($currentPeriode): ?>
                  <small class="text-muted">
                    Status: <?= ucfirst($currentPeriode['status']) ?> | 
                    Responses: <?= $currentPeriode['evaluasi_selesai'] ?>/<?= $currentPeriode['total_siswa_aktif'] ?>
                  </small>
                <?php endif; ?>
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

  // Fungsi Cetak PDF untuk Analisis Evaluasi
  function cetakLaporanPDF() {
    const button = document.getElementById('btnCetakPDF');
    const originalHTML = button.innerHTML;
    
    // Set loading state
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating PDF...';
    
    const selectedPeriode = document.getElementById('periodeSelect')?.value || '';
    
    if (!selectedPeriode) {
      button.disabled = false;
      button.innerHTML = originalHTML;
      
      Swal.fire({
        title: 'Error!',
        text: 'Silakan pilih periode evaluasi terlebih dahulu.',
        icon: 'warning',
        confirmButtonText: 'OK'
      });
      return;
    }
    
    // Capture charts if available and generate PDF
    captureChartsAndGeneratePDF(selectedPeriode, button, originalHTML);
  }

  async function captureChartsAndGeneratePDF(selectedPeriode, button, originalHTML) {
    try {
      button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
      
      const chartImages = {};
      
      // Capture charts if they exist
      const aspectChart = Chart.getChart('aspectChart');
      if (aspectChart) {
        chartImages.aspectChart = aspectChart.toBase64Image('image/png', 1.0);
      }
      
      const completionChart = Chart.getChart('completionChart');
      if (completionChart) {
        chartImages.completionChart = completionChart.toBase64Image('image/png', 1.0);
      }
      
      const classChart = Chart.getChart('classChart');
      if (classChart) {
        chartImages.classChart = classChart.toBase64Image('image/png', 1.0);
      }
      
      const mcChart = Chart.getChart('multipleChoiceChart');
      if (mcChart) {
        chartImages.multipleChoiceChart = mcChart.toBase64Image('image/png', 1.0);
      }
      
      // Generate PDF URL
      const cetakURL = `cetak_laporan.php?periode=${selectedPeriode}`;
      
      // Open PDF in new tab
      const newWindow = window.open(cetakURL, '_blank');
      
      // Handle popup blocked
      if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
        Swal.fire({
          title: 'Pop-up Diblokir!',
          html: `Browser memblokir pop-up. Klik tombol di bawah untuk membuka PDF secara manual:<br><br>
                 <a href="${cetakURL}" target="_blank" class="btn btn-danger">
                 <i class="bi bi-file-earmark-pdf"></i> Buka PDF Manual</a>`,
          icon: 'warning',
          showConfirmButton: false,
          showCloseButton: true,
          allowOutsideClick: true
        });
      }
      
    } catch (error) {
      console.error('Error generating PDF:', error);
      
      // Fallback to simple PDF
      const cetakURL = `cetak_laporan.php?periode=${selectedPeriode}`;
      window.open(cetakURL, '_blank');
    } finally {
      // Reset button state
      setTimeout(() => {
        button.disabled = false;
        button.innerHTML = originalHTML;
      }, 1000);
    }
  }

  <?php if ($currentPeriode && !empty($pertanyaanData)): ?>
  
  // Aspect Rating Chart
  <?php if (!empty($aspectAverages)): ?>
  const aspectCtx = document.getElementById('aspectChart').getContext('2d');
  new Chart(aspectCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($aspectAverages)) ?>,
      datasets: [{
        label: 'Rata-rata Rating',
        data: <?= json_encode(array_values($aspectAverages)) ?>,
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
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return `Rating: ${context.parsed.y}/5`;
            }
          }
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
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((context.parsed / total) * 100).toFixed(1);
              return `${context.label}: ${context.parsed} (${percentage}%)`;
            }
          }
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
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return `${context.label}: ${context.parsed.y}/5`;
            }
          }
        }
      }
    }
  });
  <?php endif; ?>

  // Multiple Choice Chart
  <?php if (!empty($multipleChoiceData)): ?>
  <?php 
  $firstMCData = $multipleChoiceData[0]['distribution'];
  $mcLabels = [];
  $mcData = [];
  $mcColors = [
    'rgba(245, 158, 11, 0.8)',
    'rgba(34, 197, 94, 0.8)', 
    'rgba(239, 68, 68, 0.8)',
    'rgba(139, 92, 246, 0.8)',
    'rgba(6, 182, 212, 0.8)'
  ];
  
  $colorIndex = 0;
  foreach ($firstMCData as $choice => $data) {
    $mcLabels[] = $choice . '. ' . substr($data['text'], 0, 30) . (strlen($data['text']) > 30 ? '...' : '');
    $mcData[] = $data['count'];
    $colorIndex++;
  }
  ?>
  const mcCtx = document.getElementById('multipleChoiceChart').getContext('2d');
  new Chart(mcCtx, {
    type: 'pie',
    data: {
      labels: <?= json_encode($mcLabels) ?>,
      datasets: [{
        data: <?= json_encode($mcData) ?>,
        backgroundColor: <?= json_encode(array_slice($mcColors, 0, count($mcData))) ?>,
        borderColor: <?= json_encode(array_map(function($color) { 
          return str_replace('0.8)', '1)', $color); 
        }, array_slice($mcColors, 0, count($mcData)))) ?>,
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 12,
            padding: 15
          }
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((context.parsed / total) * 100).toFixed(1);
              return `${context.label}: ${context.parsed} (${percentage}%)`;
            }
          }
        }
      }
    }
  });
  <?php endif; ?>

  <?php endif; ?>
  </script>
</body>
</html>