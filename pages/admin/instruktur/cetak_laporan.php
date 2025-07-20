<?php
// File: pages/admin/instruktur/cetak_laporan.php
// Cetak laporan instruktur menggunakan library LKP_PDF dengan Multi-line Support

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Ambil parameter filter dari URL
$filterJK = isset($_GET['jk']) ? $_GET['jk'] : '';
$filterAngkatan = isset($_GET['angkatan']) ? $_GET['angkatan'] : '';
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterJK)) {
    $whereConditions[] = "i.jenis_kelamin = ?";
    $params[] = $filterJK;
    $types .= "s";
}

if (!empty($filterAngkatan)) {
    $whereConditions[] = "i.angkatan = ?";
    $params[] = $filterAngkatan;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(i.nama LIKE ? OR i.nik LIKE ? OR i.email LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}

// Query data instruktur dengan kelas yang diampu
$query = "SELECT i.nik, i.nama, i.jenis_kelamin, i.angkatan, i.status_aktif, i.email,
                 GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') as kelas_diampu
          FROM instruktur i 
          LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " GROUP BY i.id_instruktur, i.nik, i.nama, i.jenis_kelamin, i.angkatan, i.status_aktif, i.email
            ORDER BY i.nama ASC";

// Filter kelas setelah GROUP BY (menggunakan HAVING)
if (!empty($filterKelas)) {
    $query .= " HAVING kelas_diampu LIKE ?";
    $params[] = "%$filterKelas%";
    $types .= "s";
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

    $totalInstruktur = count($dataArray);

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
        <a href='index.php' style='color: #007bff;'>Kembali ke Daftar Instruktur</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
if (!empty($filterJK)) {
    $filter_info[] = "Jenis Kelamin: " . htmlspecialchars($filterJK);
}
if (!empty($filterAngkatan)) {
    $filter_info[] = "Angkatan: " . htmlspecialchars($filterAngkatan);
}
if (!empty($filterKelas)) {
    $filter_info[] = "Kelas: " . htmlspecialchars($filterKelas);
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Auto pilih orientation - 6 kolom cocok untuk portrait
    $pdf = LKP_ReportFactory::createPendaftarReport(); // Gunakan yang sudah ada (6 kolom)
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Instruktur',
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalInstruktur,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data instruktur dengan MultiCell untuk teks panjang
    if (!empty($dataArray)) {
        // Custom MultiCell Table untuk Instruktur
        createInstrukturTableWithMultiCell($pdf, $dataArray);
        
        // Tambah detail tambahan di bawah tabel
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Data:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik jenis kelamin
        $stats_jk = [];
        foreach ($dataArray as $row) {
            $jk = $row['jenis_kelamin'] ?? 'Tidak Diketahui';
            $stats_jk[$jk] = ($stats_jk[$jk] ?? 0) + 1;
        }
        
        
        $no_ringkasan = 1;
        $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Instruktur: ' . $totalInstruktur . ' orang', 0, 1, 'L');
        $no_ringkasan++;
        
        foreach ($stats_jk as $jk => $count) {
            $pdf->Cell(5, 4, $no_ringkasan . '.', 0, 0, 'L');
            $pdf->Cell(0, 4, ' ' . $jk . ': ' . $count . ' orang', 0, 1, 'L');
            $no_ringkasan++;
        }
        
        
    } else {
        // Jika tidak ada data yang sesuai filter
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data instruktur yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Instruktur'];
    
    if (!empty($filterAngkatan)) {
        $filename_parts[] = str_replace(' ', '_', $filterAngkatan);
    }
    if (!empty($filterJK)) {
        $filename_parts[] = $filterJK;
    }
    if (!empty($filterKelas)) {
        $filename_parts[] = str_replace(' ', '_', $filterKelas);
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan instruktur.</p>
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
                                    <i class='bi bi-list-ul'></i> Daftar Instruktur
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
                                <strong>Total Records:</strong> " . $totalInstruktur . "<br>
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
function createInstrukturTableWithMultiCell($pdf, $data) {
    // Header kolom untuk laporan instruktur
    $headers = ['NO', 'NIK', 'NAMA LENGKAP', 'JK', 'ANGKATAN', 'KELAS DIAMPU'];
    
    // Lebar kolom yang optimal untuk portrait (total ~190mm)
    $widths = [12, 30, 45, 15, 35, 53];
    
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
        // Persiapkan data untuk split text
        $namaData = prepareNameData($row['nama'] ?? '');
        $kelasData = prepareKelasData($row['kelas_diampu'] ?? 'Belum ada kelas');
        
        // Tentukan apakah perlu 2 baris
        $needTwoRows = $namaData['needSplit'] || $kelasData['needSplit'];
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
        
        // NIK - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[1], $rowHeight * 2, $row['nik'] ?? '', 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[1], $rowHeight, $row['nik'] ?? '', 1, 0, 'C', $fill);
        }
        
        // NAMA - baris pertama
        $pdf->Cell($widths[2], $rowHeight, $namaData['line1'], 1, 0, 'L', $fill);
        
        // JK - merge jika ada 2 baris
        $jk = ($row['jenis_kelamin'] == 'Laki-Laki') ? 'L' : 'P';
        if ($needTwoRows) {
            $pdf->Cell($widths[3], $rowHeight * 2, $jk, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[3], $rowHeight, $jk, 1, 0, 'C', $fill);
        }
        
        // ANGKATAN - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[4], $rowHeight * 2, $row['angkatan'] ?? '', 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[4], $rowHeight, $row['angkatan'] ?? '', 1, 0, 'C', $fill);
        }
        
        // KELAS - baris pertama
        $pdf->Cell($widths[5], $rowHeight, $kelasData['line1'], 1, 0, 'L', $fill);
        $pdf->Ln();
        
        // BARIS KEDUA (jika diperlukan)
        if ($needTwoRows) {
            // Skip kolom yang sudah di-merge (NO, NIK, JK, ANGKATAN)
            $pdf->Cell($widths[0], 0, '', 0, 0); // NO - kosong
            $pdf->Cell($widths[1], 0, '', 0, 0); // NIK - kosong
            
            // NAMA - baris kedua
            $pdf->Cell($widths[2], $rowHeight, $namaData['line2'], 1, 0, 'L', $fill);
            
            $pdf->Cell($widths[3], 0, '', 0, 0); // JK - kosong
            $pdf->Cell($widths[4], 0, '', 0, 0); // ANGKATAN - kosong
            
            // KELAS - baris kedua
            $pdf->Cell($widths[5], $rowHeight, $kelasData['line2'], 1, 0, 'L', $fill);
            $pdf->Ln();
        }
    }
}

function prepareNameData($nama) {
    $maxChars = 22; // Maksimal karakter per baris untuk nama
    
    if (strlen($nama) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $nama,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata
    $words = explode(' ', $nama);
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
        'needSplit' => true,
        'line1' => $line1,
        'line2' => $line2
    ];
}

function prepareKelasData($kelas) {
    $maxChars = 28; // Maksimal karakter per baris untuk kelas
    
    if (strlen($kelas) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $kelas,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan koma (untuk multiple kelas)
    if (strpos($kelas, ', ') !== false) {
        $kelasArray = explode(', ', $kelas);
        $line1 = '';
        $line2 = '';
        $currentLength = 0;
        
        foreach ($kelasArray as $kelasItem) {
            $itemLength = strlen($kelasItem) + 2; // +2 untuk ", "
            
            if ($currentLength + $itemLength <= $maxChars && empty($line2)) {
                $line1 .= ($line1 ? ', ' : '') . $kelasItem;
                $currentLength += $itemLength;
            } else {
                $line2 .= ($line2 ? ', ' : '') . $kelasItem;
            }
        }
    } else {
        // Split berdasarkan kata jika tidak ada koma
        $words = explode(' ', $kelas);
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

function calculateRowHeight($pdf, $row, $widths) {
    // Function ini tidak digunakan lagi karena kita pakai approach yang berbeda
    return 7;
}
?>