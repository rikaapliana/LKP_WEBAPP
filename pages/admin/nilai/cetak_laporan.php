<?php
// File: pages/admin/nilai/cetak_laporan.php
// Cetak laporan nilai siswa menggunakan library LKP_PDF (9 kolom landscape)

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Ambil parameter filter dari URL
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$filterGelombang = isset($_GET['gelombang']) ? $_GET['gelombang'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterKategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

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
$query = "SELECT n.*, s.nama as nama_siswa, s.nik, 
          k.nama_kelas, g.nama_gelombang,
          -- Hitung nilai yang sudah terisi
          CASE WHEN n.nilai_word IS NOT NULL AND n.nilai_word > 0 THEN 1 ELSE 0 END +
          CASE WHEN n.nilai_excel IS NOT NULL AND n.nilai_excel > 0 THEN 1 ELSE 0 END +
          CASE WHEN n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0 THEN 1 ELSE 0 END +
          CASE WHEN n.nilai_internet IS NOT NULL AND n.nilai_internet > 0 THEN 1 ELSE 0 END +
          CASE WHEN n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0 THEN 1 ELSE 0 END as nilai_terisi,
          -- Status kelulusan berdasarkan kelengkapan nilai
          CASE 
            WHEN (n.nilai_word IS NOT NULL AND n.nilai_word > 0) AND
                 (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) AND
                 (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) AND
                 (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) AND
                 (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0) THEN
              CASE 
                WHEN n.rata_rata >= 60 THEN 'lulus'
                ELSE 'tidak lulus'
              END
            ELSE 'belum_lengkap'
          END as status_kelulusan_fix,
          -- Rata-rata sementara (dari nilai yang ada)
          CASE 
            WHEN (n.nilai_word IS NOT NULL AND n.nilai_word > 0) OR
                 (n.nilai_excel IS NOT NULL AND n.nilai_excel > 0) OR
                 (n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0) OR
                 (n.nilai_internet IS NOT NULL AND n.nilai_internet > 0) OR
                 (n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0) THEN
              (COALESCE(n.nilai_word, 0) + COALESCE(n.nilai_excel, 0) + COALESCE(n.nilai_ppt, 0) + 
               COALESCE(n.nilai_internet, 0) + COALESCE(n.nilai_pengembangan, 0)) / 
              (CASE WHEN n.nilai_word IS NOT NULL AND n.nilai_word > 0 THEN 1 ELSE 0 END +
               CASE WHEN n.nilai_excel IS NOT NULL AND n.nilai_excel > 0 THEN 1 ELSE 0 END +
               CASE WHEN n.nilai_ppt IS NOT NULL AND n.nilai_ppt > 0 THEN 1 ELSE 0 END +
               CASE WHEN n.nilai_internet IS NOT NULL AND n.nilai_internet > 0 THEN 1 ELSE 0 END +
               CASE WHEN n.nilai_pengembangan IS NOT NULL AND n.nilai_pengembangan > 0 THEN 1 ELSE 0 END)
            ELSE NULL
          END as rata_rata_sementara
          FROM nilai n 
          LEFT JOIN siswa s ON n.id_siswa = s.id_siswa
          LEFT JOIN kelas k ON n.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          WHERE n.id_siswa IS NOT NULL";

if (!empty($whereConditions)) {
    $query .= " AND " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY s.nama ASC";

// Execute query
try {
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
        if (!$result) {
            throw new Exception("Error executing query: " . mysqli_error($conn));
        }
    }

    // Ambil semua data
    $dataArray = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Filter status jika ada
        if (!empty($filterStatus)) {
            if ($filterStatus === 'belum_lengkap' && $row['status_kelulusan_fix'] !== 'belum_lengkap') continue;
            if ($filterStatus !== 'belum_lengkap' && $row['status_kelulusan_fix'] !== $filterStatus) continue;
        }
        
        // Filter kategori nilai jika ada
        if (!empty($filterKategori)) {
            $rataRata = (float)($row['rata_rata_sementara'] ?: 0);
            $match = false;
            
            if ($filterKategori === 'sangat_baik' && $rataRata >= 80) $match = true;
            if ($filterKategori === 'baik' && $rataRata >= 70 && $rataRata < 80) $match = true;
            if ($filterKategori === 'cukup' && $rataRata >= 60 && $rataRata < 70) $match = true;
            if ($filterKategori === 'kurang' && $rataRata < 60) $match = true;
            
            if (!$match) continue;
        }
        
        $dataArray[] = $row;
    }

    $totalNilai = count($dataArray);

    // Tutup statement jika ada
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }

} catch (Exception $e) {
    // Log error dan tampilkan pesan user-friendly
    error_log("Database error in cetak_laporan.php: " . $e->getMessage());
    
    echo "<!DOCTYPE html>
    <html><head><title>Error Database</title></head><body>
    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h3>Terjadi Kesalahan Database</h3>
        <p>Silakan coba lagi atau hubungi administrator.</p>
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
    // Auto pilih orientation - 9 kolom = landscape
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
    
    // Buat tabel data nilai landscape (9 kolom)
    if (!empty($dataArray)) {
        createNilaiTableLandscape($pdf, $dataArray);
        
        // Tambah detail statistik di bawah tabel
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Data:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik
        $stats_lulus = 0;
        $stats_tidak_lulus = 0;
        $stats_belum_lengkap = 0;
        $total_rata_rata = 0;
        $count_rata_rata = 0;
        
        foreach ($dataArray as $row) {
            switch($row['status_kelulusan_fix']) {
                case 'lulus': $stats_lulus++; break;
                case 'tidak lulus': $stats_tidak_lulus++; break;
                case 'belum_lengkap': $stats_belum_lengkap++; break;
            }
            
            if ($row['rata_rata_sementara'] && $row['rata_rata_sementara'] > 0) {
                $total_rata_rata += (float)$row['rata_rata_sementara'];
                $count_rata_rata++;
            }
        }
        
        $rata_rata_keseluruhan = $count_rata_rata > 0 ? $total_rata_rata / $count_rata_rata : 0;
        
        $no_ringkasan = 1;
        $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Data Nilai: ' . $totalNilai . ' siswa', 0, 1, 'L');
        $no_ringkasan++;
        
        $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Siswa Lulus: ' . $stats_lulus . ' orang', 0, 1, 'L');
        $no_ringkasan++;
        
        $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Siswa Tidak Lulus: ' . $stats_tidak_lulus . ' orang', 0, 1, 'L');
        $no_ringkasan++;
        
        $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Nilai Belum Lengkap: ' . $stats_belum_lengkap . ' orang', 0, 1, 'L');
        $no_ringkasan++;
        
        if ($count_rata_rata > 0) {
            $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
            $pdf->Cell(0, 4, ' Rata-rata Keseluruhan: ' . number_format($rata_rata_keseluruhan, 2), 0, 1, 'L');
        }
        
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
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            </div>
                            
                            <h6 class='mt-4'>Kemungkinan Penyebab:</h6>
                            <ul class='small'>
                                <li><strong>Library FPDF:</strong> File library tidak ditemukan atau rusak</li>
                                <li><strong>File Logo:</strong> Logo LKP tidak ditemukan di lokasi yang ditentukan</li>
                                <li><strong>Memory Limit:</strong> Data terlalu banyak untuk diproses sekaligus</li>
                                <li><strong>Database:</strong> Koneksi database bermasalah</li>
                                <li><strong>Permission:</strong> Tidak ada izin untuk menulis file</li>
                            </ul>
                            
                            <h6 class='mt-4'>Solusi yang Bisa Dicoba:</h6>
                            <ul class='small'>
                                <li>Gunakan filter untuk mengurangi jumlah data</li>
                                <li>Refresh halaman dan coba lagi</li>
                                <li>Hubungi administrator sistem jika masalah berlanjut</li>
                            </ul>
                            
                            <hr>
                            
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

// Tutup koneksi database
mysqli_close($conn);

// Function untuk membuat tabel nilai landscape (9 kolom) dengan merge cell yang benar
function createNilaiTableLandscape($pdf, $data) {
    // Header kolom untuk laporan nilai (9 kolom landscape)
    $headers = ['NO', 'NAMA SISWA', 'KELAS', 'WORD', 'EXCEL', 'PPT', 'INTERNET', 'SOFTSKILL', 'RATA-RATA & STATUS'];
    
    // Lebar kolom yang optimal untuk landscape (nama dikecilkan, kelas diperlebar)
    $widths = [15, 45, 40, 25, 25, 25, 25, 25, 45]; // Total ~270mm
    
    // Header tabel
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(70, 130, 180);
    $pdf->SetTextColor(255, 255, 255);
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Data tabel
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    $no = 1;
    foreach ($data as $row) {
        // Zebra striping
        if ($no % 2 == 0) {
            $pdf->SetFillColor(248, 248, 248);
            $fill = true;
        } else {
            $pdf->SetFillColor(255, 255, 255);
            $fill = true;
        }
        
        $rowHeight = 7;
        
        // Persiapkan data untuk 2 baris
        $namaData = prepareNamaForNilai($row['nama_siswa'] ?? '', $row['nik'] ?? '');
        $kelasData = prepareKelasForNilai($row['nama_kelas'] ?? '', $row['nama_gelombang'] ?? '');
        
        // BARIS PERTAMA
        $yStart = $pdf->GetY();
        
        // NO - merge untuk 2 baris
        $pdf->Cell($widths[0], $rowHeight * 2, $no++, 1, 0, 'C', $fill);
        
        // NAMA SISWA - baris pertama (nama)
        $pdf->Cell($widths[1], $rowHeight, $namaData['line1'], 1, 0, 'L', $fill);
        
        // KELAS - baris pertama (nama kelas)
        $pdf->Cell($widths[2], $rowHeight, $kelasData['line1'], 1, 0, 'C', $fill);
        
        // NILAI WORD - merge untuk 2 baris
        $nilaiWord = ($row['nilai_word'] && $row['nilai_word'] > 0) ? $row['nilai_word'] : '-';
        $pdf->Cell($widths[3], $rowHeight * 2, $nilaiWord, 1, 0, 'C', $fill);
        
        // NILAI EXCEL - merge untuk 2 baris
        $nilaiExcel = ($row['nilai_excel'] && $row['nilai_excel'] > 0) ? $row['nilai_excel'] : '-';
        $pdf->Cell($widths[4], $rowHeight * 2, $nilaiExcel, 1, 0, 'C', $fill);
        
        // NILAI PPT - merge untuk 2 baris
        $nilaiPpt = ($row['nilai_ppt'] && $row['nilai_ppt'] > 0) ? $row['nilai_ppt'] : '-';
        $pdf->Cell($widths[5], $rowHeight * 2, $nilaiPpt, 1, 0, 'C', $fill);
        
        // NILAI INTERNET - merge untuk 2 baris
        $nilaiInternet = ($row['nilai_internet'] && $row['nilai_internet'] > 0) ? $row['nilai_internet'] : '-';
        $pdf->Cell($widths[6], $rowHeight * 2, $nilaiInternet, 1, 0, 'C', $fill);
        
        // NILAI SOFTSKILL - merge untuk 2 baris
        $nilaiSoftskill = ($row['nilai_pengembangan'] && $row['nilai_pengembangan'] > 0) ? $row['nilai_pengembangan'] : '-';
        $pdf->Cell($widths[7], $rowHeight * 2, $nilaiSoftskill, 1, 0, 'C', $fill);
        
        // RATA-RATA - baris pertama
        $rataRata = $row['rata_rata_sementara'] ? number_format((float)$row['rata_rata_sementara'], 1) : '-';
        $pdf->Cell($widths[8], $rowHeight, 'Rata: ' . $rataRata, 1, 0, 'C', $fill);
        
        $pdf->Ln();
        
        // BARIS KEDUA
        // Skip kolom yang sudah di-merge (NO, WORD, EXCEL, PPT, INTERNET, SOFTSKILL)
        $pdf->Cell($widths[0], 0, '', 0, 0); // NO - kosong
        
        // NAMA - baris kedua (NIK)
        $pdf->Cell($widths[1], $rowHeight, $namaData['line2'], 1, 0, 'L', $fill);
        
        // KELAS - baris kedua (Gelombang)
        $pdf->Cell($widths[2], $rowHeight, $kelasData['line2'], 1, 0, 'C', $fill);
        
        // Skip kolom nilai yang sudah di-merge
        $pdf->Cell($widths[3], 0, '', 0, 0); // WORD - kosong
        $pdf->Cell($widths[4], 0, '', 0, 0); // EXCEL - kosong
        $pdf->Cell($widths[5], 0, '', 0, 0); // PPT - kosong
        $pdf->Cell($widths[6], 0, '', 0, 0); // INTERNET - kosong
        $pdf->Cell($widths[7], 0, '', 0, 0); // SOFTSKILL - kosong
        
        // STATUS - baris kedua
        $status = '';
        switch($row['status_kelulusan_fix']) {
            case 'lulus': $status = 'LULUS'; break;
            case 'tidak lulus': $status = 'TDK LULUS'; break;
            case 'belum_lengkap': $status = 'BLUM LENGKAP'; break;
            default: $status = 'UNKNOWN'; break;
        }
        $pdf->Cell($widths[8], $rowHeight, $status, 1, 0, 'C', $fill);
        
        $pdf->Ln();
    }
}

// Function untuk prepare nama dengan NIK yang benar dari database (untuk 2 baris terpisah)
function prepareNamaForNilai($nama, $nik) {
    $maxCharsLine1 = 22; // Maksimal karakter untuk nama (kolom diperkecil)
    
    if (strlen($nama) <= $maxCharsLine1) {
        return [
            'line1' => $nama,
            'line2' => $nik ? 'NIK: ' . $nik : '' // NIK lengkap dari database
        ];
    }
    
    // Jika nama terlalu panjang, potong
    return [
        'line1' => substr($nama, 0, $maxCharsLine1 - 3) . '...',
        'line2' => $nik ? 'NIK: ' . $nik : ''
    ];
}

// Function untuk prepare kelas dengan gelombang di baris kedua (kolom diperlebar)
function prepareKelasForNilai($namaKelas, $namaGelombang) {
    return [
        'line1' => $namaKelas ?: '-',
        'line2' => $namaGelombang ? '(' . $namaGelombang . ')' : ''
    ];
}

// Function utility
function truncateText($text, $maxLength) {
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength - 3) . '...';
    }
    return $text;
}
?>