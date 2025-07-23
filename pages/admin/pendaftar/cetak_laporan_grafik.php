<?php
// File: pages/admin/pendaftar/cetak_laporan_grafik.php  
// Cetak laporan grafik statistik pendaftar dengan visual charts menggunakan LKP_PDF_Charts

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');
require_once('../../../vendor/fpdf/lkp_pdf_charts_extension.php');

// Ambil parameter filter dari URL
$filterGelombang = isset($_GET['gelombang']) ? (int)$_GET['gelombang'] : null;
$filterTahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;

// Build WHERE clause untuk filter
$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if ($filterGelombang) {
    $whereClause .= " AND p.id_gelombang = ?";
    $params[] = $filterGelombang;
    $types .= "i";
}

if ($filterTahun) {
    $whereClause .= " AND g.tahun = ?";
    $params[] = $filterTahun;
    $types .= "i";
}

try {
    // 1. AMBIL DATA STATISTIK JENIS KELAMIN
    $queryJK = "
        SELECT 
            p.jenis_kelamin,
            COUNT(*) as jumlah
        FROM pendaftar p 
        LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
        $whereClause
        AND p.jenis_kelamin IS NOT NULL
        GROUP BY p.jenis_kelamin
        ORDER BY p.jenis_kelamin
    ";
    
    $stmtJK = mysqli_prepare($conn, $queryJK);
    if ($params) {
        mysqli_stmt_bind_param($stmtJK, $types, ...$params);
    }
    mysqli_stmt_execute($stmtJK);
    $resultJK = mysqli_stmt_get_result($stmtJK);
    
    $dataJenisKelamin = [];
    while ($row = mysqli_fetch_assoc($resultJK)) {
        $dataJenisKelamin[] = [
            'kategori' => $row['jenis_kelamin'],
            'jumlah' => (int)$row['jumlah']
        ];
    }

    // 2. AMBIL DATA STATISTIK PENDIDIKAN  
    $queryPendidikan = "
        SELECT 
            p.pendidikan_terakhir,
            COUNT(*) as jumlah
        FROM pendaftar p 
        LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
        $whereClause
        AND p.pendidikan_terakhir IS NOT NULL
        GROUP BY p.pendidikan_terakhir
        ORDER BY 
            CASE p.pendidikan_terakhir
                WHEN 'SD' THEN 1
                WHEN 'SLTP' THEN 2
                WHEN 'SLTA' THEN 3
                WHEN 'D1' THEN 4
                WHEN 'D2' THEN 5
                WHEN 'S1' THEN 6
                WHEN 'S2' THEN 7
                WHEN 'S3' THEN 8
                ELSE 9
            END
    ";
    
    $stmtPendidikan = mysqli_prepare($conn, $queryPendidikan);
    if ($params) {
        mysqli_stmt_bind_param($stmtPendidikan, $types, ...$params);
    }
    mysqli_stmt_execute($stmtPendidikan);
    $resultPendidikan = mysqli_stmt_get_result($stmtPendidikan);
    
    $dataPendidikan = [];
    while ($row = mysqli_fetch_assoc($resultPendidikan)) {
        $dataPendidikan[] = [
            'kategori' => $row['pendidikan_terakhir'],
            'jumlah' => (int)$row['jumlah']
        ];
    }

    // 3. AMBIL DATA STATISTIK USIA
    $queryUsia = "
        SELECT 
            TIMESTAMPDIFF(YEAR, p.tanggal_lahir, CURDATE()) as usia
        FROM pendaftar p 
        LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
        $whereClause
        AND p.tanggal_lahir IS NOT NULL
    ";
    
    $stmtUsia = mysqli_prepare($conn, $queryUsia);
    if ($params) {
        mysqli_stmt_bind_param($stmtUsia, $types, ...$params);
    }
    mysqli_stmt_execute($stmtUsia);
    $resultUsia = mysqli_stmt_get_result($stmtUsia);
    
    // Kelompokkan usia ke kategori
    $kategoriUsia = [
        '17-20 tahun' => 0,
        '21-25 tahun' => 0,
        '26-30 tahun' => 0,
        '31-35 tahun' => 0,
        '36+ tahun' => 0
    ];
    
    while ($row = mysqli_fetch_assoc($resultUsia)) {
        $usia = (int)$row['usia'];
        
        if ($usia >= 17 && $usia <= 20) {
            $kategoriUsia['17-20 tahun']++;
        } elseif ($usia >= 21 && $usia <= 25) {
            $kategoriUsia['21-25 tahun']++;
        } elseif ($usia >= 26 && $usia <= 30) {
            $kategoriUsia['26-30 tahun']++;
        } elseif ($usia >= 31 && $usia <= 35) {
            $kategoriUsia['31-35 tahun']++;
        } elseif ($usia >= 36) {
            $kategoriUsia['36+ tahun']++;
        }
    }
    
    $dataUsia = [];
    foreach ($kategoriUsia as $kategori => $jumlah) {
        $dataUsia[] = [
            'kategori' => $kategori,
            'jumlah' => $jumlah
        ];
    }

    // 4. AMBIL DATA TOTAL UNTUK RINGKASAN
    $queryTotal = "
        SELECT 
            COUNT(*) as total_pendaftar,
            SUM(CASE WHEN p.jenis_kelamin = 'Laki-Laki' THEN 1 ELSE 0 END) as total_laki,
            SUM(CASE WHEN p.jenis_kelamin = 'Perempuan' THEN 1 ELSE 0 END) as total_perempuan,
            SUM(CASE WHEN p.status_pendaftaran = 'Belum di Verifikasi' THEN 1 ELSE 0 END) as belum_verifikasi,
            SUM(CASE WHEN p.status_pendaftaran = 'Terverifikasi' THEN 1 ELSE 0 END) as terverifikasi,
            SUM(CASE WHEN p.status_pendaftaran = 'Diterima' THEN 1 ELSE 0 END) as diterima
        FROM pendaftar p 
        LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
        $whereClause
    ";
    
    $stmtTotal = mysqli_prepare($conn, $queryTotal);
    if ($params) {
        mysqli_stmt_bind_param($stmtTotal, $types, ...$params);
    }
    mysqli_stmt_execute($stmtTotal);
    $resultTotal = mysqli_stmt_get_result($stmtTotal);
    $dataTotal = mysqli_fetch_assoc($resultTotal);
    
    $totalPendaftar = (int)$dataTotal['total_pendaftar'];

    // 5. AMBIL INFORMASI FILTER UNTUK HEADER  
    $filter_info = [];
    
    if ($filterGelombang) {
        $queryFilterGelombang = "SELECT nama_gelombang, tahun FROM gelombang WHERE id_gelombang = ?";
        $stmtFilterGelombang = mysqli_prepare($conn, $queryFilterGelombang);
        mysqli_stmt_bind_param($stmtFilterGelombang, "i", $filterGelombang);
        mysqli_stmt_execute($stmtFilterGelombang);
        $resultFilterGelombang = mysqli_stmt_get_result($stmtFilterGelombang);
        
        if ($rowGelombang = mysqli_fetch_assoc($resultFilterGelombang)) {
            $filter_info[] = "Gelombang: " . $rowGelombang['nama_gelombang'] . " (" . $rowGelombang['tahun'] . ")";
        }
    }
    
    if ($filterTahun) {
        $filter_info[] = "Tahun: " . $filterTahun;
    }
    
    if (empty($filter_info)) {
        $filter_info[] = "Menampilkan data dari semua gelombang dan tahun";
    }

} catch (Exception $e) {
    // Log error dan tampilkan pesan user-friendly
    error_log("Database error in cetak_laporan_grafik.php: " . $e->getMessage());
    
    echo "<!DOCTYPE html>
    <html><head><title>Error Database</title></head><body>
    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h3>Terjadi Kesalahan Database</h3>
        <p>Silakan coba lagi atau hubungi administrator.</p>
        <a href='grafik.php' style='color: #007bff;'>Kembali ke Grafik Pendaftar</a>
    </div></body></html>";
    exit;
}

// Generate PDF dengan Charts
try {
    // Gunakan landscape dengan chart capability
    $pdf = LKP_ReportFactory_Charts::createGrafikPendaftarReport();
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Grafik Statistik Pendaftar',
        'Analisis demografi dan karakteristik pendaftar LKP Pradata Komputer',
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalPendaftar,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // BAGIAN 1: RINGKASAN STATISTIK UTAMA
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'RINGKASAN STATISTIK PENDAFTAR', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Layout 2 kolom untuk ringkasan
    $pdf->SetFont('Arial', '', 10);
    
    // Kolom kiri
    $pdf->Cell(60, 6, 'Total Pendaftar', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 6, number_format($totalPendaftar) . ' orang', 0, 0, 'L');
    
    // Kolom kanan
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Terverifikasi', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, number_format($dataTotal['terverifikasi']) . ' orang', 0, 1, 'L');
    
    // Baris kedua
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 6, 'Laki-laki', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 6, number_format($dataTotal['total_laki']) . ' orang (' . ($totalPendaftar > 0 ? round($dataTotal['total_laki']/$totalPendaftar*100, 1) : 0) . '%)', 0, 0, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Belum Diverifikasi', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, number_format($dataTotal['belum_verifikasi']) . ' orang', 0, 1, 'L');
    
    // Baris ketiga
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 6, 'Perempuan', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 6, number_format($dataTotal['total_perempuan']) . ' orang (' . ($totalPendaftar > 0 ? round($dataTotal['total_perempuan']/$totalPendaftar*100, 1) : 0) . '%)', 0, 0, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Diterima', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, number_format($dataTotal['diterima']) . ' orang', 0, 1, 'L');
    
    $pdf->Ln(15);
    
    // BAGIAN 2: STATISTIK JENIS KELAMIN (LAYOUT BARU - TABEL ATAS, CHART BAWAH)
    if (!empty($dataJenisKelamin)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'STATISTIK BERDASARKAN JENIS KELAMIN', 0, 1, 'L');
        $pdf->Ln(5);
        
        // TABEL DI ATAS (full width)
        $headers_jk = ['NO', 'JENIS KELAMIN', 'JUMLAH', 'PERSENTASE'];
        $widths_jk = [30, 80, 60, 60]; // Total 230mm (landscape)
        
        $table_data_jk = [];
        $no = 1;
        foreach ($dataJenisKelamin as $row) {
            $persentase = $totalPendaftar > 0 ? round($row['jumlah']/$totalPendaftar*100, 1) : 0;
            $table_data_jk[] = [
                $no++,
                $row['kategori'],
                number_format($row['jumlah']) . ' orang',
                $persentase . '%'
            ];
        }
        
        $options_jk = [
            'header_bg' => [70, 130, 180],
            'header_text' => [255, 255, 255],
            'border' => true,
            'zebra' => true,
            'font_size' => 10,
            'header_font_size' => 11,
            'cell_height' => 8,
            'header_height' => 9
        ];
        
        $pdf->createTable($headers_jk, $table_data_jk, $widths_jk, $options_jk);
        $pdf->Ln(10);
        
        // PIE CHART DI TENGAH BAWAH - lebih besar
        $chart_x = ($pdf->GetPageWidth()) / 2; // Center horizontal
        $chart_y = $pdf->GetY() + 5;
        $pdf->drawPieChart($dataJenisKelamin, $totalPendaftar, 'Distribusi Jenis Kelamin', $chart_x, $chart_y, 40);
        
        $pdf->Ln(90); // Space untuk chart + legend
    }
    
    // BAGIAN 3: STATISTIK PENDIDIKAN (TABEL + BAR CHART)
    if (!empty($dataPendidikan)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'STATISTIK BERDASARKAN PENDIDIKAN TERAKHIR', 0, 1, 'L');
        $pdf->Ln(3);
        
        // Layout: Tabel di atas, Bar Chart di bawah
        $headers_pendidikan = ['NO', 'JENJANG PENDIDIKAN', 'JUMLAH', 'PERSENTASE'];
        $widths_pendidikan = [20, 80, 40, 50];
        
        $table_data_pendidikan = [];
        $no = 1;
        foreach ($dataPendidikan as $row) {
            $persentase = $totalPendaftar > 0 ? round($row['jumlah']/$totalPendaftar*100, 1) : 0;
            
            // Format nama jenjang pendidikan
            $jenjang = $row['kategori'];
            switch($jenjang) {
                case 'SD': $jenjang = 'Sekolah Dasar (SD)'; break;
                case 'SLTP': $jenjang = 'SMP / MTs'; break;
                case 'SLTA': $jenjang = 'SMA / SMK / MA'; break;
                case 'D1': $jenjang = 'Diploma 1 (D1)'; break;
                case 'D2': $jenjang = 'Diploma 2 (D2)'; break;
                case 'S1': $jenjang = 'Sarjana (S1)'; break;
                case 'S2': $jenjang = 'Magister (S2)'; break;
                case 'S3': $jenjang = 'Doktor (S3)'; break;
            }
            
            $table_data_pendidikan[] = [
                $no++,
                $jenjang,
                number_format($row['jumlah']) . ' orang',
                $persentase . '%'
            ];
        }
        
        $pdf->createTable($headers_pendidikan, $table_data_pendidikan, $widths_pendidikan, $options_jk);
        $pdf->Ln(5);
        
        // BAR CHART HORIZONTAL di bawah tabel
        $pdf->drawBarChart($dataPendidikan, $totalPendaftar, 'Distribusi Pendidikan Terakhir');
        $pdf->Ln(15);
    }
    
    // BAGIAN 4: STATISTIK USIA (TABEL + COLUMN CHART)
    if (!empty($dataUsia)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'STATISTIK BERDASARKAN KELOMPOK USIA', 0, 1, 'L');
        $pdf->Ln(3);
        
        // Header tabel usia
        $headers_usia = ['NO', 'KELOMPOK USIA', 'JUMLAH', 'PERSENTASE'];
        $widths_usia = [20, 60, 40, 50];
        
        $table_data_usia = [];
        $chart_data_usia = []; // For chart
        $no = 1;
        foreach ($dataUsia as $row) {
            // Skip jika jumlah 0
            if ($row['jumlah'] == 0) continue;
            
            $persentase = $totalPendaftar > 0 ? round($row['jumlah']/$totalPendaftar*100, 1) : 0;
            $table_data_usia[] = [
                $no++,
                $row['kategori'],
                number_format($row['jumlah']) . ' orang',
                $persentase . '%'
            ];
            
            $chart_data_usia[] = [
                'kategori' => $row['kategori'],
                'jumlah' => $row['jumlah']
            ];
        }
        
        if (!empty($table_data_usia)) {
            $pdf->createTable($headers_usia, $table_data_usia, $widths_usia, $options_jk);
            $pdf->Ln(5);
            
            // COLUMN CHART di bawah tabel
            $pdf->drawColumnChart($chart_data_usia, $totalPendaftar, 'Distribusi Kelompok Usia');
            $pdf->Ln(15);
        }
    }
    
    // BAGIAN 5: KESIMPULAN DAN ANALISIS
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'KESIMPULAN ANALISIS', 0, 1, 'L');
    $pdf->Ln(3);
    
    $pdf->SetFont('Arial', '', 10);
    
    // Analisis jenis kelamin
    if (!empty($dataJenisKelamin)) {
        $dominan_jk = '';
        $max_jk = 0;
        foreach ($dataJenisKelamin as $row) {
            if ($row['jumlah'] > $max_jk) {
                $max_jk = $row['jumlah'];
                $dominan_jk = $row['kategori'];
            }
        }
        
        $pdf->Cell(10, 6, '1.', 0, 0, 'L');
        $pdf->Cell(0, 6, 'Berdasarkan jenis kelamin, pendaftar ' . strtolower($dominan_jk) . ' lebih dominan dengan ' . number_format($max_jk) . ' orang', 0, 1, 'L');
        $pdf->Cell(10, 6, '', 0, 0, 'L');
        $persentase_dominan = $totalPendaftar > 0 ? round($max_jk/$totalPendaftar*100, 1) : 0;
        $pdf->Cell(0, 6, 'atau sebesar ' . $persentase_dominan . '% dari total pendaftar.', 0, 1, 'L');
        $pdf->Ln(2);
    }
    
    // Analisis pendidikan
    if (!empty($dataPendidikan)) {
        $dominan_pendidikan = '';
        $max_pendidikan = 0;
        foreach ($dataPendidikan as $row) {
            if ($row['jumlah'] > $max_pendidikan) {
                $max_pendidikan = $row['jumlah'];
                $dominan_pendidikan = $row['kategori'];
            }
        }
        
        $pdf->Cell(10, 6, '2.', 0, 0, 'L');
        $pdf->Cell(0, 6, 'Mayoritas pendaftar memiliki latar belakang pendidikan ' . $dominan_pendidikan . ' dengan jumlah', 0, 1, 'L');
        $pdf->Cell(10, 6, '', 0, 0, 'L');
        $persentase_pendidikan = $totalPendaftar > 0 ? round($max_pendidikan/$totalPendaftar*100, 1) : 0;
        $pdf->Cell(0, 6, number_format($max_pendidikan) . ' orang (' . $persentase_pendidikan . '%) dari total pendaftar.', 0, 1, 'L');
        $pdf->Ln(2);
    }
    
    // Analisis usia
    if (!empty($dataUsia)) {
        $dominan_usia = '';
        $max_usia = 0;
        foreach ($dataUsia as $row) {
            if ($row['jumlah'] > $max_usia) {
                $max_usia = $row['jumlah'];
                $dominan_usia = $row['kategori'];
            }
        }
        
        $pdf->Cell(10, 6, '3.', 0, 0, 'L');
        $pdf->Cell(0, 6, 'Kelompok usia terbanyak adalah ' . $dominan_usia . ' dengan ' . number_format($max_usia) . ' orang pendaftar', 0, 1, 'L');
        $pdf->Cell(10, 6, '', 0, 0, 'L');
        $persentase_usia = $totalPendaftar > 0 ? round($max_usia/$totalPendaftar*100, 1) : 0;
        $pdf->Cell(0, 6, 'atau ' . $persentase_usia . '% dari keseluruhan.', 0, 1, 'L');
    }
    
    // Tambah tanda tangan
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter
    $filename_parts = ['Laporan_Grafik_Pendaftar'];
    
    if ($filterGelombang) {
        $filename_parts[] = 'Gelombang_' . $filterGelombang;
    }
    if ($filterTahun) {
        $filename_parts[] = 'Tahun_' . $filterTahun;
    }
    
    $filename_parts[] = date('Y-m-d_H-i-s');
    $filename = implode('_', $filename_parts) . '.pdf';
    
    // Output PDF
    $pdf->Output('I', $filename); // 'I' = inline di browser, 'D' = download
    
} catch (Exception $e) {
    // Error handling yang user-friendly
    error_log("PDF generation error: " . $e->getMessage());
    
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
                                <h6 class='alert-heading'>Gagal Membuat PDF Laporan Grafik</h6>
                                <p>Terjadi kesalahan saat membuat file PDF laporan grafik pendaftar dengan charts.</p>
                                <hr>
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            </div>
                            
                            <h6 class='mt-4'>Kemungkinan Penyebab:</h6>
                            <ul class='small'>
                                <li><strong>Library FPDF:</strong> File library atau extension charts tidak ditemukan</li>
                                <li><strong>File Logo:</strong> Logo LKP tidak ditemukan di path yang ditentukan</li>
                                <li><strong>Chart Functions:</strong> Method chart tidak terdefinisi dengan benar</li>
                                <li><strong>Memory Limit:</strong> Proses chart membutuhkan memory yang cukup</li>
                                <li><strong>Database:</strong> Koneksi atau query database bermasalah</li>
                            </ul>
                            
                            <h6 class='mt-4'>Solusi yang Bisa Dicoba:</h6>
                            <ul class='small'>
                                <li>Pastikan file lkp_pdf_charts_extension.php ada di folder vendor/fpdf/</li>
                                <li>Cek apakah logo favicon.png ada di assets/img/</li>
                                <li>Gunakan filter untuk mengurangi kompleksitas data</li>
                                <li>Refresh halaman dan coba lagi</li>
                                <li>Hubungi administrator sistem jika masalah berlanjut</li>
                            </ul>
                            
                            <hr>
                            
                            <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                <a href='grafik.php' class='btn btn-secondary'>
                                    <i class='bi bi-arrow-left'></i> Kembali ke Grafik
                                </a>
                                <a href='index.php' class='btn btn-primary'>
                                    <i class='bi bi-list-ul'></i> Daftar Pendaftar
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Debug Info untuk Development -->
                    <div class='card mt-4'>
                        <div class='card-header'>
                            <h6 class='mb-0'>
                                <i class='bi bi-info-circle'></i> 
                                Debug Information
                            </h6>
                        </div>
                        <div class='card-body'>
                            <small class='text-muted'>
                                <strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "<br>
                                <strong>Filter Applied:</strong> " . (!empty($filter_info) ? implode(', ', $filter_info) : 'Tidak ada filter') . "<br>
                                <strong>Total Records:</strong> " . $totalPendaftar . "<br>
                                <strong>User:</strong> " . ($_SESSION['nama'] ?? 'Unknown') . "<br>
                                <strong>PHP Version:</strong> " . phpversion() . "<br>
                                <strong>Memory Usage:</strong> " . memory_get_usage(true) / 1024 / 1024 . " MB<br>
                                <strong>Data JK:</strong> " . count($dataJenisKelamin) . " items<br>
                                <strong>Data Pendidikan:</strong> " . count($dataPendidikan) . " items<br>
                                <strong>Data Usia:</strong> " . count($dataUsia) . " items
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
}

// Tutup koneksi database
mysqli_close($conn);
?>