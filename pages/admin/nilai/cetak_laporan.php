<?php
// File: pages/admin/nilai/cetak_laporan.php
// Cetak laporan nilai siswa menggunakan library LKP_PDF (landscape dengan format instruktur)

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Set timezone Makassar (WITA)
date_default_timezone_set('Asia/Makassar');

// Ambil parameter filter dari URL
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$filterGelombang = isset($_GET['gelombang']) ? $_GET['gelombang'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterKategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Debug: Log parameter yang diterima
error_log("cetak_laporan.php - Parameters: kelas=" . $filterKelas . ", gelombang=" . $filterGelombang . ", status=" . $filterStatus);

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterKelas)) {
    $whereConditions[] = "k.nama_kelas LIKE ?";
    $params[] = "%$filterKelas%";
    $types .= "s";
}

if (!empty($filterGelombang)) {
    $whereConditions[] = "g.nama_gelombang LIKE ?";
    $params[] = "%$filterGelombang%";
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(s.nama LIKE ? OR s.nik LIKE ? OR k.nama_kelas LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}

// Query data nilai dengan join
$query = "SELECT 
            n.id_nilai,
            s.nama as nama_siswa,
            s.nik,
            k.nama_kelas,
            COALESCE(g.nama_gelombang, '') as nama_gelombang,
            COALESCE(n.nilai_word, 0) as nilai_word,
            COALESCE(n.nilai_excel, 0) as nilai_excel,
            COALESCE(n.nilai_ppt, 0) as nilai_ppt,
            COALESCE(n.nilai_internet, 0) as nilai_internet,
            COALESCE(n.nilai_pengembangan, 0) as nilai_pengembangan,
            COALESCE(n.rata_rata, 0) as rata_rata,
            COALESCE(n.status_kelulusan, 'belum_lengkap') as status_kelulusan
          FROM nilai n 
          LEFT JOIN siswa s ON n.id_siswa = s.id_siswa
          LEFT JOIN kelas k ON n.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          WHERE n.id_siswa IS NOT NULL AND s.status_aktif = 'aktif'";

if (!empty($whereConditions)) {
    $query .= " AND " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY s.nama ASC";

error_log("Final query: " . $query);
error_log("Parameters: " . implode(", ", $params));

// Execute query
try {
    error_log("Preparing statement with types: " . $types);
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($conn));
        }
        
        error_log("Binding parameters: " . json_encode($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        
        error_log("Executing statement...");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
    }

    if (!$result) {
        throw new Exception("Error getting result: " . mysqli_error($conn));
    }

    // Ambil semua data
    $dataArray = [];
    $rowCount = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Hitung nilai terisi dan status untuk setiap row
        $nilaiTerisi = 0;
        if ($row['nilai_word'] && $row['nilai_word'] > 0) $nilaiTerisi++;
        if ($row['nilai_excel'] && $row['nilai_excel'] > 0) $nilaiTerisi++;
        if ($row['nilai_ppt'] && $row['nilai_ppt'] > 0) $nilaiTerisi++;
        if ($row['nilai_internet'] && $row['nilai_internet'] > 0) $nilaiTerisi++;
        if ($row['nilai_pengembangan'] && $row['nilai_pengembangan'] > 0) $nilaiTerisi++;
        
        $row['nilai_terisi'] = $nilaiTerisi;
        
        // Status kelulusan
        if ($nilaiTerisi == 5) {
            $row['status_kelulusan_fix'] = ($row['rata_rata'] >= 60) ? 'lulus' : 'tidak lulus';
        } else {
            $row['status_kelulusan_fix'] = 'belum_lengkap';
        }
        
        // Filter status jika ada
        if (!empty($filterStatus)) {
            if ($filterStatus === 'belum_lengkap' && $row['status_kelulusan_fix'] !== 'belum_lengkap') continue;
            if ($filterStatus !== 'belum_lengkap' && $row['status_kelulusan_fix'] !== $filterStatus) continue;
        }
        
        // Filter kategori nilai jika ada
        if (!empty($filterKategori)) {
            $rataRata = (float)($row['rata_rata'] ?: 0);
            $match = false;
            
            if ($filterKategori === 'sangat_baik' && $rataRata >= 80) $match = true;
            if ($filterKategori === 'baik' && $rataRata >= 70 && $rataRata < 80) $match = true;
            if ($filterKategori === 'cukup' && $rataRata >= 60 && $rataRata < 70) $match = true;
            if ($filterKategori === 'kurang' && $rataRata < 60) $match = true;
            
            if (!$match) continue;
        }
        
        $dataArray[] = $row;
        $rowCount++;
    }

    $totalNilai = count($dataArray);
    error_log("Query executed successfully. Found $totalNilai rows.");

    // Tutup statement jika ada
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }

} catch (Exception $e) {
    error_log("Database error in cetak_laporan.php: " . $e->getMessage());
    error_log("SQL Query: " . $query);
    error_log("Parameters: " . json_encode($params));
    
    echo "<!DOCTYPE html>
    <html><head><title>Error Database</title></head><body>
    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h3>Terjadi Kesalahan Database</h3>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Query:</strong> " . htmlspecialchars($query) . "</p>
        <p><strong>Parameters:</strong> " . htmlspecialchars(json_encode($params)) . "</p>
        <a href='index.php' style='color: #007bff;'>Kembali ke Daftar Nilai</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
if (!empty($filterKelas)) {
    $filter_info[] = "Kelas: " . htmlspecialchars($filterKelas);
}
if (!empty($filterGelombang)) {
    $filter_info[] = "Gelombang: " . htmlspecialchars($filterGelombang);
}
if (!empty($filterStatus)) {
    $status_text = '';
    switch($filterStatus) {
        case 'lulus': $status_text = 'Lulus'; break;
        case 'tidak lulus': $status_text = 'Tidak Lulus'; break;
        case 'belum_lengkap': $status_text = 'Belum Lengkap'; break;
        default: $status_text = $filterStatus; break;
    }
    $filter_info[] = "Status: " . $status_text;
}
if (!empty($filterKategori)) {
    $kategori_text = '';
    switch($filterKategori) {
        case 'sangat_baik': $kategori_text = 'Sangat Baik (80-100)'; break;
        case 'baik': $kategori_text = 'Baik (70-79)'; break;
        case 'cukup': $kategori_text = 'Cukup (60-69)'; break;
        case 'kurang': $kategori_text = 'Kurang (<60)'; break;
        default: $kategori_text = $filterKategori; break;
    }
    $filter_info[] = "Kategori: " . $kategori_text;
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Gunakan landscape untuk format admin
    $pdf = LKP_ReportFactory::createNilaiReport();
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Nilai Siswa',
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalNilai,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data nilai dengan format instruktur (landscape)
    if (!empty($dataArray)) {
        createNilaiTableAdmin($pdf, $dataArray);
        
        // Tambah detail statistik di bawah tabel
        addStatisticsSection($pdf, $dataArray, $totalNilai);
        
    } else {
        // Jika tidak ada data yang sesuai filter
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data nilai yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Nilai'];
    
    if (!empty($filterKelas)) {
        $filename_parts[] = str_replace(' ', '_', $filterKelas);
    }
    if (!empty($filterGelombang)) {
        $filename_parts[] = str_replace(' ', '_', $filterGelombang);
    }
    if (!empty($filterStatus)) {
        $filename_parts[] = str_replace(' ', '_', $filterStatus);
    }
    
    $filename_parts[] = date('Y-m-d_H-i-s');
    $filename = implode('_', $filename_parts) . '.pdf';
    
    // Output PDF
    $pdf->Output('I', $filename); // 'I' = inline di browser, 'D' = download
    
} catch (Exception $e) {
    // Error handling yang user-friendly
    error_log("PDF generation error: " . $e->getMessage());
    showPDFError($e->getMessage());
}

// Tutup koneksi database
mysqli_close($conn);

// ========== FUNCTIONS ==========

/**
 * Membuat tabel nilai admin dengan format instruktur (landscape)
 * Layout diperbaiki dengan border yang konsisten dan spacing yang rapi
 */
function createNilaiTableAdmin($pdf, $data) {
    // Header kolom untuk laporan nilai admin - landscape
    $headers = ['NO', 'NAMA SISWA / NIK', 'KELAS / GELOMBANG', 'WORD', 'EXCEL', 'PPT', 'INTERNET', 'SOFTSKILL', 'RATA-RATA', 'STATUS'];
    
    // Lebar kolom yang disesuaikan untuk landscape (A4 = ~297mm)
    // Total: 277mm (sisakan margin 20mm)
    $widths = [15, 60, 50, 20, 20, 20, 20, 20, 27, 25];
    
    // Function untuk membuat header tabel
    $createTableHeader = function() use ($pdf, $headers, $widths) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(70, 130, 180);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(50, 50, 50);
        $pdf->SetLineWidth(0.3);
        
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($widths[$i], 10, $headers[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();
    };
    
    // Buat header pertama kali
    $createTableHeader();
    
    // Data tabel
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.2);
    
    $no = 1;
    foreach ($data as $row) {
        // Cek apakah perlu halaman baru SEBELUM menulis data
        if ($pdf->GetY() > 180) { // Sisakan ruang untuk footer
            $pdf->AddPage();
            $createTableHeader(); // Buat header ulang di halaman baru
        }
        
        // Zebra striping dengan warna yang lebih soft
        if ($no % 2 == 0) {
            $pdf->SetFillColor(248, 250, 252);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $fill = true;
        
        $rowHeight = 7;
        
        // Persiapkan data
        $namaSiswa = truncateText($row['nama_siswa'] ?? '', 35);
        $nikSiswa = $row['nik'] ?? '';
        $namaKelas = truncateText($row['nama_kelas'] ?? '', 25);
        $namaGelombang = truncateText($row['nama_gelombang'] ?? '', 25);
        
        // Format nilai - tampilkan 0 jika tidak ada nilai
        $nilaiWord = formatNilai($row['nilai_word']);
        $nilaiExcel = formatNilai($row['nilai_excel']);
        $nilaiPpt = formatNilai($row['nilai_ppt']);
        $nilaiInternet = formatNilai($row['nilai_internet']);
        $nilaiPengembangan = formatNilai($row['nilai_pengembangan']);
        $rataRata = formatRataRata($row['rata_rata']);
        
        // Status dengan warna
        $status = formatStatus($row['status_kelulusan_fix']);
        
        // BARIS PERTAMA
        $pdf->Cell($widths[0], $rowHeight, $no++, 'LTR', 0, 'C', $fill);
        $pdf->Cell($widths[1], $rowHeight, $namaSiswa, 'LTR', 0, 'L', $fill);
        $pdf->Cell($widths[2], $rowHeight, $namaKelas, 'LTR', 0, 'L', $fill);
        
        // Kolom nilai dengan center alignment
        $pdf->Cell($widths[3], $rowHeight, $nilaiWord, 'LTR', 0, 'C', $fill);
        $pdf->Cell($widths[4], $rowHeight, $nilaiExcel, 'LTR', 0, 'C', $fill);
        $pdf->Cell($widths[5], $rowHeight, $nilaiPpt, 'LTR', 0, 'C', $fill);
        $pdf->Cell($widths[6], $rowHeight, $nilaiInternet, 'LTR', 0, 'C', $fill);
        $pdf->Cell($widths[7], $rowHeight, $nilaiPengembangan, 'LTR', 0, 'C', $fill);
        $pdf->Cell($widths[8], $rowHeight, $rataRata, 'LTR', 0, 'C', $fill);
        $pdf->Cell($widths[9], $rowHeight, $status, 'LTR', 0, 'C', $fill);
        
        $pdf->Ln();
        
        // BARIS KEDUA - NIK dan Gelombang
        $pdf->Cell($widths[0], $rowHeight, '', 'LBR', 0, 'C', $fill);
        
        // NIK dengan format yang lebih rapi
        $nikText = !empty($nikSiswa) ? 'NIK: ' . $nikSiswa : '';
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell($widths[1], $rowHeight, $nikText, 'LBR', 0, 'L', $fill);
        
        // Gelombang
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell($widths[2], $rowHeight, $namaGelombang, 'LBR', 0, 'L', $fill);
        
        // Reset font untuk kolom nilai (kosong di baris kedua)
        $pdf->SetFont('Arial', '', 8);
        for ($i = 3; $i < count($widths); $i++) {
            $pdf->Cell($widths[$i], $rowHeight, '', 'LBR', 0, 'C', $fill);
        }
        
        $pdf->Ln();
    }
}

/**
 * Menambahkan section statistik dengan layout yang rapi
 */
function addStatisticsSection($pdf, $dataArray, $totalNilai) {
    $pdf->Ln(8);
    
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    
    // Hitung statistik
    $stats = calculateStatistics($dataArray);
    
    // Layout statistik dalam 2 kolom
    $col1Width = 140;
    $col2Width = 140;
    
    // Kolom 1 - Statistik Umum
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($col1Width, 6, 'Statistik Umum:', 0, 0, 'L');
    $pdf->Cell($col2Width, 6, 'Rata-rata Per Komponen:', 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 8);
    
    // Baris 1
    $pdf->Cell($col1Width, 5, '1. Total Data: ' . number_format($totalNilai) . ' siswa', 0, 0, 'L');
    $pdf->Cell($col2Width, 5, '1. Word: ' . $stats['avg_word'], 0, 1, 'L');
    
    // Baris 2
    $pdf->Cell($col1Width, 5, '2. Lulus: ' . $stats['lulus'] . ' siswa (' . $stats['persentase_lulus'] . '%)', 0, 0, 'L');
    $pdf->Cell($col2Width, 5, '2. Excel: ' . $stats['avg_excel'], 0, 1, 'L');
    
    // Baris 3
    $pdf->Cell($col1Width, 5, '3. Tidak Lulus: ' . $stats['tidak_lulus'] . ' siswa', 0, 0, 'L');
    $pdf->Cell($col2Width, 5, '3. PowerPoint: ' . $stats['avg_ppt'], 0, 1, 'L');
    
    // Baris 4
    $pdf->Cell($col1Width, 5, '4. Belum Lengkap: ' . $stats['belum_lengkap'] . ' siswa', 0, 0, 'L');
    $pdf->Cell($col2Width, 5, '4. Internet: ' . $stats['avg_internet'], 0, 1, 'L');
    
    // Baris 5
    $pdf->Cell($col1Width, 5, '5. Rata-rata Keseluruhan: ' . $stats['rata_rata_keseluruhan'], 0, 0, 'L');
    $pdf->Cell($col2Width, 5, '5. Softskill: ' . $stats['avg_pengembangan'], 0, 1, 'L');
}

/**
 * Menghitung statistik dari data
 */
function calculateStatistics($dataArray) {
    $stats_lulus = 0;
    $stats_tidak_lulus = 0;
    $stats_belum_lengkap = 0;
    $total_rata_rata = 0;
    $count_rata_rata = 0;
    
    // Statistik per komponen
    $word_total = 0; $word_count = 0;
    $excel_total = 0; $excel_count = 0;
    $ppt_total = 0; $ppt_count = 0;
    $internet_total = 0; $internet_count = 0;
    $pengembangan_total = 0; $pengembangan_count = 0;
    
    foreach ($dataArray as $row) {
        // Status kelulusan
        switch($row['status_kelulusan_fix']) {
            case 'lulus': $stats_lulus++; break;
            case 'tidak lulus': $stats_tidak_lulus++; break;
            case 'belum_lengkap': $stats_belum_lengkap++; break;
        }
        
        // Rata-rata keseluruhan
        if ($row['rata_rata'] && $row['rata_rata'] > 0) {
            $total_rata_rata += (float)$row['rata_rata'];
            $count_rata_rata++;
        }
        
        // Statistik per komponen
        if (isset($row['nilai_word']) && $row['nilai_word'] > 0) {
            $word_total += $row['nilai_word'];
            $word_count++;
        }
        if (isset($row['nilai_excel']) && $row['nilai_excel'] > 0) {
            $excel_total += $row['nilai_excel'];
            $excel_count++;
        }
        if (isset($row['nilai_ppt']) && $row['nilai_ppt'] > 0) {
            $ppt_total += $row['nilai_ppt'];
            $ppt_count++;
        }
        if (isset($row['nilai_internet']) && $row['nilai_internet'] > 0) {
            $internet_total += $row['nilai_internet'];
            $internet_count++;
        }
        if (isset($row['nilai_pengembangan']) && $row['nilai_pengembangan'] > 0) {
            $pengembangan_total += $row['nilai_pengembangan'];
            $pengembangan_count++;
        }
    }
    
    $rata_rata_keseluruhan = $count_rata_rata > 0 ? $total_rata_rata / $count_rata_rata : 0;
    $persentase_lulus = count($dataArray) > 0 ? round(($stats_lulus / count($dataArray)) * 100, 1) : 0;
    
    return [
        'lulus' => $stats_lulus,
        'tidak_lulus' => $stats_tidak_lulus,
        'belum_lengkap' => $stats_belum_lengkap,
        'rata_rata_keseluruhan' => number_format($rata_rata_keseluruhan, 1),
        'persentase_lulus' => $persentase_lulus,
        'avg_word' => $word_count > 0 ? number_format($word_total / $word_count, 1) : '0',
        'avg_excel' => $excel_count > 0 ? number_format($excel_total / $excel_count, 1) : '0',
        'avg_ppt' => $ppt_count > 0 ? number_format($ppt_total / $ppt_count, 1) : '0',
        'avg_internet' => $internet_count > 0 ? number_format($internet_total / $internet_count, 1) : '0',
        'avg_pengembangan' => $pengembangan_count > 0 ? number_format($pengembangan_total / $pengembangan_count, 1) : '0'
    ];
}

/**
 * Format nilai untuk tampilan yang konsisten
 */
function formatNilai($nilai) {
    if (isset($nilai) && $nilai > 0) {
        return number_format($nilai, 0);
    }
    return '-';
}

/**
 * Format rata-rata dengan 1 desimal
 */
function formatRataRata($rataRata) {
    if (isset($rataRata) && $rataRata > 0) {
        return number_format($rataRata, 1);
    }
    return '-';
}

/**
 * Format status kelulusan
 */
function formatStatus($status) {
    switch ($status) {
        case 'lulus':
            return 'LULUS';
        case 'tidak lulus':
            return 'T.LULUS';
        default:
            return 'SEMENTARA';
    }
}

/**
 * Truncate text dengan ellipsis jika terlalu panjang
 */
function truncateText($text, $maxLength) {
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength - 3) . '...';
    }
    return $text;
}

/**
 * Tampilkan error PDF yang user-friendly
 */
function showPDFError($errorMessage) {
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
                                <h6 class='alert-heading'>Gagal Membuat PDF</h6>
                                <p>Terjadi kesalahan saat membuat file PDF laporan nilai siswa.</p>
                                <hr>
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($errorMessage) . "</p>
                            </div>
                            
                            <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                <a href='javascript:history.back()' class='btn btn-secondary'>
                                    <i class='bi bi-arrow-left'></i> Kembali
                                </a>
                                <a href='index.php' class='btn btn-primary'>
                                    <i class='bi bi-list-ul'></i> Daftar Nilai
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
?>