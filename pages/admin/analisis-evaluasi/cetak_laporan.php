<?php
// File: pages/admin/analisis-evaluasi/cetak_laporan.php
// Cetak laporan analisis hasil evaluasi siswa dengan charts menggunakan library LKP_PDF

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Handle both GET and POST requests
$selectedPeriode = 0;
$chartImages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST request with chart images
    $selectedPeriode = isset($_POST['periode']) ? (int)$_POST['periode'] : 0;
    
    // Decode chart images
    if (isset($_POST['chartImages'])) {
        $chartImagesJson = $_POST['chartImages'];
        $chartImages = json_decode($chartImagesJson, true) ?: [];
    }
} else {
    // GET request (fallback without charts)
    $selectedPeriode = isset($_GET['periode']) ? (int)$_GET['periode'] : 0;
}

if ($selectedPeriode <= 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid periode parameter']);
        exit;
    } else {
        echo "<!DOCTYPE html>
        <html><head><title>Error Parameter</title></head><body>
        <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
            <h3>Parameter Periode Evaluasi Tidak Valid</h3>
            <p>Silakan pilih periode evaluasi yang valid.</p>
            <a href='index.php' style='color: #007bff;'>Kembali ke Dashboard Analisis</a>
        </div></body></html>";
        exit;
    }
}

try {
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

    if (!$currentPeriode) {
        throw new Exception("Periode evaluasi tidak ditemukan.");
    }

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

    // Process data for analysis
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
                    'question' => $pertanyaan['pertanyaan'],
                    'average' => round(array_sum($ratings) / count($ratings), 1),
                    'count' => count($ratings),
                    'total_score' => array_sum($ratings),
                    'min' => min($ratings),
                    'max' => max($ratings)
                ];
            }
        } elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda') {
            $pilihan = json_decode($pertanyaan['pilihan_jawaban'], true);
            if (is_array($pilihan)) {
                $distribution = [];
                $total_responses = count($answers);
                
                foreach ($answers as $answer) {
                    $choiceIndex = (int)$answer['jawaban'];
                    if (isset($pilihan[$choiceIndex])) {
                        $choiceText = $pilihan[$choiceIndex];
                        if (!isset($distribution[$choiceText])) {
                            $distribution[$choiceText] = 0;
                        }
                        $distribution[$choiceText]++;
                    }
                }
                
                // Add percentage
                foreach ($distribution as $choice => $count) {
                    $percentage = $total_responses > 0 ? round(($count / $total_responses) * 100, 1) : 0;
                    $distribution[$choice] = [
                        'count' => $count,
                        'percentage' => $percentage
                    ];
                }
                
                $multipleChoiceData[] = [
                    'aspect' => $pertanyaan['aspek_dinilai'],
                    'question' => $pertanyaan['pertanyaan'],
                    'distribution' => $distribution,
                    'total_responses' => $total_responses
                ];
            }
        } elseif ($pertanyaan['tipe_jawaban'] == 'isian') {
            $texts = array_column($answers, 'jawaban');
            $cleanTexts = [];
            foreach ($texts as $text) {
                $cleanText = trim($text);
                if (strlen($cleanText) > 5) { // Filter responses that are too short
                    $cleanTexts[] = $cleanText;
                }
            }
            
            if (!empty($cleanTexts)) {
                $feedbackData[] = [
                    'aspect' => $pertanyaan['aspek_dinilai'],
                    'question' => $pertanyaan['pertanyaan'],
                    'responses' => $cleanTexts,
                    'count' => count($cleanTexts)
                ];
            }
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
            $classAverages[] = [
                'kelas' => $kelas,
                'average' => round(array_sum($ratings) / count($ratings), 1),
                'count' => count($ratings),
                'total' => array_sum($ratings)
            ];
        }
    }

    // Sort class averages by performance
    usort($classAverages, function($a, $b) {
        return $b['average'] <=> $a['average'];
    });

    // Calculate overall statistics
    $totalEvaluasiSelesai = $currentPeriode['evaluasi_selesai'];
    $totalSiswaAktif = $currentPeriode['total_siswa_aktif'];
    $completionRate = $totalSiswaAktif > 0 ? round(($totalEvaluasiSelesai / $totalSiswaAktif) * 100, 1) : 0;
    $avgRating = !empty($ratingData) ? round(array_sum(array_column($ratingData, 'average')) / count($ratingData), 1) : 0;

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
            $insights[] = "Aspek Terbaik: '{$bestAspect}' mendapat rating tertinggi ({$maxRating}/5)";
        }
        
        if ($worstAspect && $minRating < 4) {
            $insights[] = "Area Perbaikan: '{$worstAspect}' perlu perhatian khusus (rating: {$minRating}/5)";
        }
    }
    
    if ($completionRate < 80) {
        $insights[] = "Tingkat partisipasi {$completionRate}% masih bisa ditingkatkan";
    }
    
    if (!empty($classAverages) && count($classAverages) > 1) {
        $best = $classAverages[0];
        $worst = end($classAverages);
        $insights[] = "Performa kelas terbaik: {$best['kelas']} ({$best['average']}/5), terendah: {$worst['kelas']} ({$worst['average']}/5)";
    }

    $materi_labels = [
        'word' => 'Microsoft Word',
        'excel' => 'Microsoft Excel', 
        'ppt' => 'Microsoft PowerPoint',
        'internet' => 'Internet & Email'
    ];

} catch (Exception $e) {
    error_log("Database error in analisis evaluasi cetak_laporan.php: " . $e->getMessage());
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
    echo "<!DOCTYPE html>
    <html><head><title>Error Database</title></head><body>
    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h3>Terjadi Kesalahan Database</h3>
        <p>Silakan coba lagi atau hubungi administrator.</p>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <a href='index.php' style='color: #007bff;'>Kembali ke Dashboard Analisis</a>
    </div></body></html>";
    exit;
}

// Generate PDF
try {
    // Create PDF dengan factory method untuk analisis evaluasi (landscape)
    $pdf = LKP_ReportFactory::createAnalisisEvaluasiReport();
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $filter_info = [
        "Periode: " . htmlspecialchars($currentPeriode['nama_evaluasi']),
        "Gelombang: " . htmlspecialchars($currentPeriode['nama_gelombang']),
        "Tanggal: " . formatTanggalIndonesia($currentPeriode['tanggal_buka']) . " - " . formatTanggalIndonesia($currentPeriode['tanggal_tutup'])
    ];
    
    if ($currentPeriode['materi_terkait']) {
        $filter_info[] = "Materi: " . ($materi_labels[$currentPeriode['materi_terkait']] ?? ucfirst($currentPeriode['materi_terkait']));
    }
    
    $pdf->setReportInfo(
        'Laporan Analisis Hasil Evaluasi',
        'Evaluasi Pembelajaran Siswa LKP Pradata Komputer',
        '../../../assets/img/favicon.png',
        $filter_info,
        $totalEvaluasiSelesai,
        $_SESSION['nama'] ?? 'Administrator Sistem'
    );
    
    $pdf->AddPage();
    
    // SECTION 1: SUMMARY STATISTICS
    addSectionWithTable($pdf, 'RINGKASAN STATISTIK EVALUASI', function() use ($pdf, $totalSiswaAktif, $totalEvaluasiSelesai, $completionRate, $avgRating, $pertanyaanData, $jawabanData) {
        $summaryData = [
            ['Metrik', 'Nilai', 'Keterangan'],
            ['Total Siswa Aktif', number_format($totalSiswaAktif), 'Siswa terdaftar dalam gelombang'],
            ['Evaluasi Selesai', number_format($totalEvaluasiSelesai), 'Siswa yang menyelesaikan evaluasi'],
            ['Completion Rate', $completionRate . '%', 'Tingkat penyelesaian evaluasi'],
            ['Rata-rata Rating', number_format($avgRating, 1) . '/5', 'Rating keseluruhan dari semua aspek'],
            ['Total Pertanyaan', count($pertanyaanData), 'Jumlah pertanyaan dalam evaluasi'],
            ['Total Respon', count($jawabanData), 'Total jawaban yang terkumpul']
        ];
        
        $pdf->createTable(
            array_shift($summaryData), // Headers
            $summaryData,              // Data
            [60, 40, 140],            // Column widths
            [
                'header_bg' => [52, 152, 219],
                'font_size' => 9,
                'cell_height' => 6
            ]
        );
    });
    
    // SECTION 2: COMPLETION RATE WITH CHART
    if (isset($chartImages['completionChart'])) {
        addSectionWithChart($pdf, 'TINGKAT PENYELESAIAN EVALUASI', $chartImages['completionChart'], 'Tingkat Penyelesaian Evaluasi', 120, 80, function() use ($pdf, $completionRate, $totalEvaluasiSelesai, $totalSiswaAktif) {
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, "Dari total {$totalSiswaAktif} siswa aktif, {$totalEvaluasiSelesai} siswa ({$completionRate}%) telah menyelesaikan evaluasi.", 0, 1, 'L');
            
            $status = '';
            if ($completionRate >= 90) $status = 'Sangat Baik';
            elseif ($completionRate >= 80) $status = 'Baik';
            elseif ($completionRate >= 70) $status = 'Cukup';
            else $status = 'Perlu Peningkatan';
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(30, 6, 'Status: ', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, $status, 0, 1, 'L');
        });
    }
    
    // SECTION 3: RATING ANALYSIS WITH CHART
    if (!empty($ratingData)) {
        addSectionWithChart($pdf, 'ANALISIS RATING PER ASPEK', 
            isset($chartImages['ratingChart']) ? $chartImages['ratingChart'] : null, 
            'Rating Overview per Aspek', 200, 100, 
            function() use ($pdf, $ratingData) {
                $ratingTableData = [
                    ['Aspek Dinilai', 'Rata-rata', 'Responden', 'Min-Max', 'Kategori']
                ];
                
                foreach ($ratingData as $rating) {
                    $kategori = '';
                    if ($rating['average'] >= 4.5) $kategori = 'Sangat Baik';
                    elseif ($rating['average'] >= 4.0) $kategori = 'Baik';
                    elseif ($rating['average'] >= 3.5) $kategori = 'Cukup';
                    elseif ($rating['average'] >= 3.0) $kategori = 'Kurang';
                    else $kategori = 'Sangat Kurang';
                    
                    $ratingTableData[] = [
                        $rating['aspect'],
                        number_format($rating['average'], 1) . '/5',
                        $rating['count'] . ' orang',
                        $rating['min'] . '-' . $rating['max'],
                        $kategori
                    ];
                }
                
                $pdf->createTable(
                    array_shift($ratingTableData),
                    $ratingTableData,
                    [80, 25, 30, 25, 35],
                    [
                        'header_bg' => [46, 125, 50],
                        'font_size' => 8,
                        'cell_height' => 6
                    ]
                );
            }
        );
    }
    
    // SECTION 4: CLASS PERFORMANCE WITH CHART
    if (!empty($classAverages)) {
        addSectionWithChart($pdf, 'PERFORMA PER KELAS', 
            isset($chartImages['classChart']) ? $chartImages['classChart'] : null, 
            'Performa per Kelas', 180, 90, 
            function() use ($pdf, $classAverages) {
                $classTableData = [
                    ['Ranking', 'Nama Kelas', 'Rata-rata Rating', 'Jumlah Respon', 'Status']
                ];
                
                $rank = 1;
                foreach ($classAverages as $class) {
                    $status = '';
                    if ($class['average'] >= 4.0) $status = 'Excellent';
                    elseif ($class['average'] >= 3.5) $status = 'Good';
                    elseif ($class['average'] >= 3.0) $status = 'Average';
                    else $status = 'Needs Improvement';
                    
                    $classTableData[] = [
                        $rank++,
                        $class['kelas'],
                        number_format($class['average'], 1) . '/5',
                        $class['count'] . ' respon',
                        $status
                    ];
                }
                
                $pdf->createTable(
                    array_shift($classTableData),
                    $classTableData,
                    [25, 60, 35, 35, 45],
                    [
                        'header_bg' => [255, 152, 0],
                        'font_size' => 8,
                        'cell_height' => 6
                    ]
                );
            }
        );
    }
    
    // SECTION 5: MULTIPLE CHOICE ANALYSIS WITH CHART
    if (!empty($multipleChoiceData)) {
        addSectionWithChart($pdf, 'ANALISIS JAWABAN PILIHAN GANDA', 
            isset($chartImages['multipleChoiceChart']) ? $chartImages['multipleChoiceChart'] : null, 
            'Distribusi Jawaban Pilihan Ganda', 120, 80, 
            function() use ($pdf, $multipleChoiceData) {
                foreach ($multipleChoiceData as $index => $mcData) {
                    if ($index > 0) $pdf->Ln(8); // Space between questions
                    
                    $pdf->SetFont('Arial', 'B', 9);
                    $pdf->Cell(0, 6, 'Aspek: ' . $mcData['aspect'], 0, 1, 'L');
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Cell(0, 5, 'Total Respon: ' . $mcData['total_responses'] . ' siswa', 0, 1, 'L');
                    $pdf->Ln(2);
                    
                    $mcTableData = [
                        ['Pilihan Jawaban', 'Jumlah', 'Persentase']
                    ];
                    
                    foreach ($mcData['distribution'] as $choice => $data) {
                        $mcTableData[] = [
                            $choice,
                            $data['count'] . ' orang',
                            $data['percentage'] . '%'
                        ];
                    }
                    
                    $pdf->createTable(
                        array_shift($mcTableData),
                        $mcTableData,
                        [120, 30, 30],
                        [
                            'header_bg' => [155, 89, 182],
                            'font_size' => 8,
                            'cell_height' => 5
                        ]
                    );
                }
            }
        );
    }
    
    // SECTION 6: KEY INSIGHTS
    if (!empty($insights)) {
        addSectionWithTable($pdf, 'KEY INSIGHTS & REKOMENDASI', function() use ($pdf, $insights) {
            $pdf->SetFont('Arial', '', 9);
            $counter = 1;
            foreach ($insights as $insight) {
                $pdf->Cell(8, 6, $counter . '.', 0, 0, 'L');
                
                // Word wrap untuk insight yang panjang
                $maxWidth = 230;
                if ($pdf->GetStringWidth($insight) > $maxWidth) {
                    $words = explode(' ', $insight);
                    $line = '';
                    $firstLine = true;
                    
                    foreach ($words as $word) {
                        $testLine = $line . $word . ' ';
                        if ($pdf->GetStringWidth($testLine) > $maxWidth) {
                            if (!empty($line)) {
                                $pdf->Cell(0, 6, trim($line), 0, 1, 'L');
                                if ($firstLine) {
                                    $pdf->Cell(8, 0, '', 0, 0, 'L'); // Indent
                                    $firstLine = false;
                                }
                            }
                            $line = $word . ' ';
                        } else {
                            $line = $testLine;
                        }
                    }
                    
                    if (!empty($line)) {
                        $pdf->Cell(0, 6, trim($line), 0, 1, 'L');
                    }
                } else {
                    $pdf->Cell(0, 6, $insight, 0, 1, 'L');
                }
                
                $counter++;
                $pdf->Ln(2);
            }
        });
    }
    
    // SECTION 7: SAMPLE FEEDBACK
    if (!empty($feedbackData)) {
        addSectionWithTable($pdf, 'SAMPLE FEEDBACK SISWA', function() use ($pdf, $feedbackData) {
            foreach ($feedbackData as $index => $feedback) {
                if ($index > 0) $pdf->Ln(8);
                
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(0, 6, 'Aspek: ' . $feedback['aspect'] . ' (' . $feedback['count'] . ' respon)', 0, 1, 'L');
                $pdf->SetFont('Arial', '', 8);
                $pdf->Ln(2);
                
                // Show max 3 sample responses per aspect
                $sampleResponses = array_slice($feedback['responses'], 0, 3);
                $responseCounter = 1;
                
                foreach ($sampleResponses as $response) {
                    $responseText = '"' . truncateText($response, 200) . '"';
                    
                    $pdf->Cell(8, 5, $responseCounter . '.', 0, 0, 'L');
                    
                    // Improved word wrap
                    $maxWidth = 240;
                    $words = explode(' ', $responseText);
                    $line = '';
                    $firstLine = true;
                    
                    foreach ($words as $word) {
                        $testLine = $line . $word . ' ';
                        if ($pdf->GetStringWidth($testLine) > $maxWidth) {
                            if (!empty($line)) {
                                $pdf->Cell(0, 5, trim($line), 0, 1, 'L');
                                if ($firstLine) {
                                    $pdf->Cell(8, 0, '', 0, 0, 'L'); // Indent
                                    $firstLine = false;
                                }
                            }
                            $line = $word . ' ';
                        } else {
                            $line = $testLine;
                        }
                    }
                    
                    if (!empty($line)) {
                        $pdf->Cell(0, 5, trim($line), 0, 1, 'L');
                    }
                    
                    $responseCounter++;
                    $pdf->Ln(3);
                }
                
                if (count($feedback['responses']) > 3) {
                    $pdf->SetFont('Arial', 'I', 8);
                    $pdf->Cell(8, 4, '', 0, 0, 'L');
                    $pdf->Cell(0, 4, '... dan ' . (count($feedback['responses']) - 3) . ' feedback lainnya', 0, 1, 'L');
                    $pdf->SetFont('Arial', '', 8);
                }
            }
        });
    }
    
    // Add signature
    $pdf->addSignature();
    
    // Generate filename
    $filename = 'Analisis_Evaluasi_' . 
                str_replace(' ', '_', $currentPeriode['nama_evaluasi']) . '_' .
                str_replace(' ', '_', $currentPeriode['nama_gelombang']) . '_' .
                date('Y-m-d_H-i-s') . '.pdf';
    
    // Output PDF
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // For POST request, output directly to browser
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        $pdf->Output('I', $filename);
    } else {
        // For GET request, output normally
        $pdf->Output('I', $filename);
    }
    
} catch (Exception $e) {
    error_log("PDF generation error in analisis evaluasi: " . $e->getMessage());
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate PDF: ' . $e->getMessage()]);
        exit;
    }
    
    echo "<!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error - Generate PDF</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-8'>
                    <div class='card border-danger'>
                        <div class='card-header bg-danger text-white'>
                            <h5 class='mb-0'>
                                <i class='bi bi-exclamation-triangle'></i> 
                                Error Generating PDF
                            </h5>
                        </div>
                        <div class='card-body'>
                            <div class='alert alert-danger' role='alert'>
                                <h6 class='alert-heading'>Gagal Membuat PDF Analisis Evaluasi</h6>
                                <p>Terjadi kesalahan saat membuat file PDF laporan analisis evaluasi dengan charts.</p>
                                <hr>
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            </div>
                            
                            <h6 class='mt-4'>Kemungkinan Penyebab:</h6>
                            <ul class='small'>
                                <li><strong>Chart Images:</strong> Gagal memproses gambar chart dari browser</li>
                                <li><strong>Data Evaluasi:</strong> Tidak ada data evaluasi yang selesai untuk periode ini</li>
                                <li><strong>Library FPDF:</strong> File library tidak ditemukan atau rusak</li>
                                <li><strong>Memory Limit:</strong> Data terlalu banyak untuk diproses sekaligus</li>
                                <li><strong>Database:</strong> Koneksi database bermasalah atau data corrupt</li>
                                <li><strong>Base64 Images:</strong> Format gambar chart tidak valid</li>
                            </ul>
                            
                            <h6 class='mt-4'>Solusi yang Bisa Dicoba:</h6>
                            <ul class='small'>
                                <li>Coba generate PDF tanpa charts (versi teks saja)</li>
                                <li>Pastikan periode evaluasi memiliki data jawaban siswa</li>
                                <li>Cek apakah ada evaluasi dengan status 'selesai'</li>
                                <li>Refresh halaman dan coba lagi</li>
                                <li>Pilih periode evaluasi yang berbeda</li>
                                <li>Hubungi administrator sistem jika masalah berlanjut</li>
                            </ul>
                            
                            <hr>
                            
                            <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                <a href='index.php' class='btn btn-primary'>
                                    <i class='bi bi-bar-chart'></i> Dashboard Analisis
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
}

// Close database connection
mysqli_close($conn);

// HELPER FUNCTIONS FOR IMPROVED LAYOUT
function addSectionWithChart($pdf, $sectionTitle, $chartImage, $chartTitle, $chartWidth, $chartHeight, $additionalContent = null) {
    // Calculate required space
    $titleHeight = 8;
    $chartTitleHeight = 6;
    $spacing = 8;
    $totalChartSpace = $chartHeight + $chartTitleHeight + $spacing;
    $additionalSpace = $additionalContent ? 50 : 0; // Estimate for additional content
    
    $totalRequired = $titleHeight + $totalChartSpace + $additionalSpace + 10; // Extra margin
    
    // Check if we need a new page
    if (!checkSpaceAvailable($pdf, $totalRequired)) {
        $pdf->AddPage();
    }
    
    // Add section title
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, $titleHeight, $sectionTitle, 0, 1, 'L');
    $pdf->Ln(3);
    
    // Add chart if available
    if ($chartImage) {
        addChartToPDF($pdf, $chartImage, $chartTitle, $chartWidth, $chartHeight);
        $pdf->Ln(5);
    }
    
    // Add additional content
    if ($additionalContent && is_callable($additionalContent)) {
        $additionalContent();
    }
    
    $pdf->Ln(10); // Section spacing
}

function addSectionWithTable($pdf, $sectionTitle, $tableContent) {
    // Estimate space needed (more conservative)
    $estimatedHeight = 60; // Base estimate for table
    
    if (!checkSpaceAvailable($pdf, $estimatedHeight)) {
        $pdf->AddPage();
    }
    
    // Add section title
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $sectionTitle, 0, 1, 'L');
    $pdf->Ln(2);
    
    // Add table content
    if ($tableContent && is_callable($tableContent)) {
        $tableContent();
    }
    
    $pdf->Ln(10); // Section spacing
}

function checkSpaceAvailable($pdf, $requiredHeight) {
    $currentY = $pdf->GetY();
    $pageHeight = $pdf->GetPageHeight();
    $bottomMargin = 25;
    $availableSpace = $pageHeight - $currentY - $bottomMargin;
    
    return $availableSpace >= $requiredHeight;
}

// Improved chart addition function
function addChartToPDF($pdf, $base64Image, $title, $width = 180, $height = 90) {
    try {
        // Remove base64 prefix if exists
        if (strpos($base64Image, ',') !== false) {
            $base64Image = explode(',', $base64Image)[1];
        }
        
        // Decode base64 image
        $imageData = base64_decode($base64Image);
        
        if ($imageData === false) {
            throw new Exception("Invalid base64 image data");
        }
        
        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'chart_') . '.png';
        file_put_contents($tempFile, $imageData);
        
        // Add chart title
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 6, $title, 0, 1, 'C');
        $pdf->Ln(2);
        
        // Calculate position to center the image
        $pageWidth = $pdf->GetPageWidth();
        $x = ($pageWidth - $width) / 2;
        
        // Add image to PDF
        $pdf->Image($tempFile, $x, $pdf->GetY(), $width, $height, 'PNG');
        
        // Move Y position after image
        $pdf->SetY($pdf->GetY() + $height);
        
        // Clean up temporary file
        unlink($tempFile);
        
    } catch (Exception $e) {
        // If chart fails, add text placeholder
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 20, '[Chart: ' . $title . ' - Error loading image]', 1, 1, 'C');
        error_log("Chart image error: " . $e->getMessage());
    }
}

// Helper Functions
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

function truncateText($text, $maxLength) {
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength - 3) . '...';
    }
    return $text;
}
?>