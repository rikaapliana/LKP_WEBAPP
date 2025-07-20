<?php
// File: pages/admin/kelas/cetak_laporan.php
// Cetak laporan kelas menggunakan library LKP_PDF dengan Multi-line Support

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Ambil parameter filter dari URL
$filterGelombang = isset($_GET['gelombang']) ? $_GET['gelombang'] : '';
$filterInstruktur = isset($_GET['instruktur']) ? $_GET['instruktur'] : '';
$filterKapasitas = isset($_GET['kapasitas']) ? $_GET['kapasitas'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterGelombang)) {
    $whereConditions[] = "g.nama_gelombang = ?";
    $params[] = $filterGelombang;
    $types .= "s";
}

if (!empty($filterInstruktur)) {
    $whereConditions[] = "i.nama = ?";
    $params[] = $filterInstruktur;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(k.nama_kelas LIKE ? OR g.nama_gelombang LIKE ? OR i.nama LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}

// Query data kelas dengan join
$query = "SELECT k.id_kelas, k.nama_kelas, k.kapasitas,
                 g.nama_gelombang, g.status as status_gelombang,
                 i.nama as nama_instruktur,
                 COUNT(s.id_siswa) as jumlah_siswa
          FROM kelas k 
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
          LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " GROUP BY k.id_kelas, k.nama_kelas, k.kapasitas, k.id_gelombang, k.id_instruktur, 
                     g.nama_gelombang, g.status, i.nama
            ORDER BY k.id_kelas DESC";

// Filter kapasitas setelah GROUP BY (menggunakan HAVING)
if (!empty($filterKapasitas)) {
    if ($filterKapasitas == 'penuh') {
        $query .= " HAVING k.kapasitas > 0 AND jumlah_siswa >= k.kapasitas";
    } elseif ($filterKapasitas == 'tersedia') {
        $query .= " HAVING k.kapasitas > 0 AND jumlah_siswa < k.kapasitas";
    } elseif ($filterKapasitas == 'kosong') {
        $query .= " HAVING jumlah_siswa = 0";
    }
}

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
        $dataArray[] = $row;
    }

    $totalKelas = count($dataArray);

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
        <a href='index.php' style='color: #007bff;'>Kembali ke Daftar Kelas</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
if (!empty($filterGelombang)) {
    $filter_info[] = "Gelombang: " . htmlspecialchars($filterGelombang);
}
if (!empty($filterInstruktur)) {
    $filter_info[] = "Instruktur: " . htmlspecialchars($filterInstruktur);
}
if (!empty($filterKapasitas)) {
    $kapasitas_label = [
        'penuh' => 'Kelas Penuh',
        'tersedia' => 'Masih Tersedia',
        'kosong' => 'Belum Ada Siswa'
    ];
    $filter_info[] = "Kapasitas: " . ($kapasitas_label[$filterKapasitas] ?? $filterKapasitas);
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Auto pilih orientation - 6 kolom cocok untuk portrait
    $pdf = LKP_ReportFactory::createKelasReport();
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Kelas',
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalKelas,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data kelas dengan layout yang lebih rapi
    if (!empty($dataArray)) {
        // Gunakan tabel yang sudah diperbaiki
        createKelasTableWithMultiCell($pdf, $dataArray);
        
        // Tambah ringkasan data di bawah tabel
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Data Kelas:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik
        $stats_gelombang = [];
        $stats_instruktur = [];
        $kelas_penuh = 0;
        $kelas_kosong = 0;
        $total_kapasitas = 0;
        $total_siswa = 0;
        
        foreach ($dataArray as $row) {
            $gelombang = $row['nama_gelombang'] ?? 'Belum Ditentukan';
            $instruktur = $row['nama_instruktur'] ?? 'Belum Ditentukan';
            $kapasitas = (int)($row['kapasitas'] ?? 0);
            $jumlah_siswa = (int)($row['jumlah_siswa'] ?? 0);
            
            // Statistik gelombang
            $stats_gelombang[$gelombang] = ($stats_gelombang[$gelombang] ?? 0) + 1;
            
            // Statistik instruktur
            $stats_instruktur[$instruktur] = ($stats_instruktur[$instruktur] ?? 0) + 1;
            
            // Statistik kapasitas
            if ($kapasitas > 0 && $jumlah_siswa >= $kapasitas) {
                $kelas_penuh++;
            }
            if ($jumlah_siswa == 0) {
                $kelas_kosong++;
            }
            
            $total_kapasitas += $kapasitas;
            $total_siswa += $jumlah_siswa;
        }
        
        $persentase_terisi = $total_kapasitas > 0 ? round(($total_siswa / $total_kapasitas) * 100, 1) : 0;
        
        // Tampilkan ringkasan dengan format yang lebih rapi
        $no_ringkasan = 1;
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Kelas: ' . number_format($totalKelas) . ' kelas', 0, 1, 'L');
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Kapasitas: ' . number_format($total_kapasitas) . ' siswa', 0, 1, 'L');
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Siswa Aktif: ' . number_format($total_siswa) . ' siswa (' . $persentase_terisi . '%)', 0, 1, 'L');
        
        if ($kelas_penuh > 0) {
            $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
            $pdf->Cell(0, 4, ' Kelas Penuh: ' . number_format($kelas_penuh) . ' kelas', 0, 1, 'L');
        }
        
        if ($kelas_kosong > 0) {
            $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
            $pdf->Cell(0, 4, ' Kelas Kosong: ' . number_format($kelas_kosong) . ' kelas', 0, 1, 'L');
        }
        
        
    } else {
        // Jika tidak ada data
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data kelas yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Kelas'];
    
    if (!empty($filterGelombang)) {
        $filename_parts[] = str_replace(' ', '_', $filterGelombang);
    }
    if (!empty($filterInstruktur)) {
        $filename_parts[] = str_replace(' ', '_', $filterInstruktur);
    }
    if (!empty($filterKapasitas)) {
        $filename_parts[] = ucfirst($filterKapasitas);
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan kelas.</p>
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
                                    <i class='bi bi-list-ul'></i> Daftar Kelas
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
                                <strong>Total Records:</strong> " . $totalKelas . "<br>
                                <strong>User:</strong> " . ($_SESSION['nama'] ?? 'Unknown') . "<br>
                                <strong>PHP Version:</strong> " . phpversion() . "<br>
                                <strong>Memory Usage:</strong> " . memory_get_usage(true) / 1024 / 1024 . " MB
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

// Function untuk membuat tabel dengan MultiCell dan Merge Cell untuk baris ke-2
function createKelasTableWithMultiCell($pdf, $data) {
    // Header kolom untuk laporan kelas
    $headers = ['NO', 'NAMA KELAS', 'GELOMBANG', 'INSTRUKTUR', 'KAPASITAS', 'JML SISWA'];
    
    // Lebar kolom yang disesuaikan - kecilkan nama kelas dan instruktur, besarkan gelombang
    $widths = [12, 35, 45, 40, 25, 28]; // Total masih ~185mm
    
    // Header tabel
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(70, 130, 180);
    $pdf->SetTextColor(255, 255, 255);
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Data tabel dengan MultiCell untuk handle teks panjang
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    $no = 1;
    foreach ($data as $row) {
        // Persiapkan data untuk split text dengan batas karakter yang disesuaikan
        $namaKelasData = prepareKelasNameData($row['nama_kelas'] ?? '', 18); // Kurangi dari 20
        $gelombangData = prepareGelombangData($row['nama_gelombang'] ?? 'Belum Ditentukan', 25); // Tambah dari 20
        $instrukturData = prepareInstrukturData($row['nama_instruktur'] ?? 'Belum Ditentukan', 20); // Kurangi dari 22
        
        // Tentukan apakah perlu 2 baris
        $needTwoRows = $namaKelasData['needSplit'] || $gelombangData['needSplit'] || $instrukturData['needSplit'];
        $rowHeight = $needTwoRows ? 7 : 7; // Tinggi per sub-baris
        
        // Zebra striping
        if ($no % 2 == 0) {
            $pdf->SetFillColor(248, 248, 248);
            $fill = true;
        } else {
            $pdf->SetFillColor(255, 255, 255);
            $fill = true;
        }
        
        // BARIS PERTAMA
        $yStart = $pdf->GetY();
        
        // NO - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[0], $rowHeight * 2, $no++, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[0], $rowHeight, $no++, 1, 0, 'C', $fill);
        }
        
        // NAMA KELAS - baris pertama
        $pdf->Cell($widths[1], $rowHeight, $namaKelasData['line1'], 1, 0, 'L', $fill);
        
        // GELOMBANG - baris pertama
        $pdf->Cell($widths[2], $rowHeight, $gelombangData['line1'], 1, 0, 'L', $fill);
        
        // INSTRUKTUR - baris pertama
        $pdf->Cell($widths[3], $rowHeight, $instrukturData['line1'], 1, 0, 'L', $fill);
        
        // KAPASITAS - merge jika ada 2 baris
        $kapasitas = $row['kapasitas'] ?? '0';
        if ($needTwoRows) {
            $pdf->Cell($widths[4], $rowHeight * 2, $kapasitas, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[4], $rowHeight, $kapasitas, 1, 0, 'C', $fill);
        }
        
        // JUMLAH SISWA - merge jika ada 2 baris dengan status warna
        $jumlah_siswa = (int)($row['jumlah_siswa'] ?? 0);
        $kapasitas_int = (int)$kapasitas;
        $siswa_text = $jumlah_siswa . '/' . $kapasitas_int;
        
        if ($needTwoRows) {
            $pdf->Cell($widths[5], $rowHeight * 2, $siswa_text, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[5], $rowHeight, $siswa_text, 1, 0, 'C', $fill);
        }
        
        $pdf->Ln();
        
        // BARIS KEDUA (jika diperlukan)
        if ($needTwoRows) {
            // Skip kolom yang sudah di-merge (NO, KAPASITAS, JML SISWA)
            $pdf->Cell($widths[0], 0, '', 0, 0); // NO - kosong
            
            // NAMA KELAS - baris kedua
            $pdf->Cell($widths[1], $rowHeight, $namaKelasData['line2'], 1, 0, 'L', $fill);
            
            // GELOMBANG - baris kedua
            $pdf->Cell($widths[2], $rowHeight, $gelombangData['line2'], 1, 0, 'L', $fill);
            
            // INSTRUKTUR - baris kedua
            $pdf->Cell($widths[3], $rowHeight, $instrukturData['line2'], 1, 0, 'L', $fill);
            
            $pdf->Cell($widths[4], 0, '', 0, 0); // KAPASITAS - kosong
            $pdf->Cell($widths[5], 0, '', 0, 0); // JML SISWA - kosong
            
            $pdf->Ln();
        }
    }
}

function prepareKelasNameData($namaKelas, $maxChars = 18) {
    if (strlen($namaKelas) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $namaKelas,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata atau tanda hubung
    $words = preg_split('/[\s\-]+/', $namaKelas);
    $line1 = '';
    $line2 = '';
    $currentLength = 0;
    
    foreach ($words as $word) {
        if ($currentLength + strlen($word) + 1 <= $maxChars && empty($line2)) {
            $line1 .= ($line1 ? ' ' : '') . $word;
            $currentLength += strlen($word) + 1;
        } else {
            $line2 .= ($line2 ? ' ' : '') . $word;
        }
    }
    
    // Jika line2 masih terlalu panjang, potong
    if (strlen($line2) > $maxChars) {
        $line2 = substr($line2, 0, $maxChars - 3) . '...';
    }
    
    return [
        'needSplit' => !empty($line2),
        'line1' => $line1,
        'line2' => $line2
    ];
}

function prepareGelombangData($gelombang, $maxChars = 25) {
    if (strlen($gelombang) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $gelombang,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata
    $words = explode(' ', $gelombang);
    $line1 = '';
    $line2 = '';
    $currentLength = 0;
    
    foreach ($words as $word) {
        if ($currentLength + strlen($word) + 1 <= $maxChars && empty($line2)) {
            $line1 .= ($line1 ? ' ' : '') . $word;
            $currentLength += strlen($word) + 1;
        } else {
            $line2 .= ($line2 ? ' ' : '') . $word;
        }
    }
    
    return [
        'needSplit' => !empty($line2),
        'line1' => $line1,
        'line2' => $line2
    ];
}

function prepareInstrukturData($instruktur, $maxChars = 20) {
    if (strlen($instruktur) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $instruktur,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata
    $words = explode(' ', $instruktur);
    $line1 = '';
    $line2 = '';
    $currentLength = 0;
    
    foreach ($words as $word) {
        if ($currentLength + strlen($word) + 1 <= $maxChars && empty($line2)) {
            $line1 .= ($line1 ? ' ' : '') . $word;
            $currentLength += strlen($word) + 1;
        } else {
            $line2 .= ($line2 ? ' ' : '') . $word;
        }
    }
    
    return [
        'needSplit' => !empty($line2),
        'line1' => $line1,
        'line2' => $line2
    ];
}
?>