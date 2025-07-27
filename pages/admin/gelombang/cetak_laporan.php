<?php
// File: pages/admin/gelombang/cetak_laporan.php
// Cetak laporan gelombang menggunakan library LKP_PDF dengan Multi-line Support

session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Function untuk terbilang angka (sama seperti di index.php)
function terbilang($angka) {
    $bilangan = array(
        1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima',
        6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan', 10 => 'sepuluh',
        11 => 'sebelas', 12 => 'dua belas'
    );
    return isset($bilangan[$angka]) ? $bilangan[$angka] : $angka;
}

// Ambil parameter filter dari URL
$filterTahun = isset($_GET['tahun']) ? $_GET['tahun'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterFormulir = isset($_GET['formulir']) ? $_GET['formulir'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter (sama seperti di index.php tapi tanpa pagination)
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterTahun)) {
    $whereConditions[] = "g.tahun = ?";
    $params[] = $filterTahun;
    $types .= "s";
}

if (!empty($filterStatus)) {
    $whereConditions[] = "g.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($filterFormulir)) {
    if ($filterFormulir == 'belum_diatur') {
        $whereConditions[] = "(p.status_pendaftaran IS NULL)";
    } else {
        $whereConditions[] = "p.status_pendaftaran = ?";
        $params[] = $filterFormulir;
        $types .= "s";
    }
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(g.nama_gelombang LIKE ? OR g.tahun LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam]);
    $types .= "ss";
}

// Query utama - sama seperti di index.php
$query = "SELECT g.*, 
                 COALESCE(kelas_count.jumlah_kelas, 0) as jumlah_kelas,
                 COALESCE(siswa_count.jumlah_siswa, 0) as jumlah_siswa,
                 p.status_pendaftaran,
                 p.kuota_maksimal,
                 COALESCE(pendaftar_count.jumlah_pendaftar, 0) as jumlah_pendaftar
          FROM gelombang g 
          LEFT JOIN (
              SELECT id_gelombang, COUNT(*) as jumlah_kelas 
              FROM kelas 
              GROUP BY id_gelombang
          ) kelas_count ON g.id_gelombang = kelas_count.id_gelombang
          LEFT JOIN (
              SELECT k.id_gelombang, COUNT(s.id_siswa) as jumlah_siswa
              FROM kelas k
              LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
              GROUP BY k.id_gelombang
          ) siswa_count ON g.id_gelombang = siswa_count.id_gelombang
          LEFT JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang
          LEFT JOIN (
              SELECT id_gelombang, COUNT(*) as jumlah_pendaftar
              FROM pendaftar
              GROUP BY id_gelombang
          ) pendaftar_count ON g.id_gelombang = pendaftar_count.id_gelombang";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY g.tahun DESC, g.gelombang_ke DESC";

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

    $totalGelombang = count($dataArray);

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
        <a href='index.php' style='color: #007bff;'>Kembali ke Daftar Gelombang</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
if (!empty($filterTahun)) {
    $filter_info[] = "Tahun: " . htmlspecialchars($filterTahun);
}
if (!empty($filterStatus)) {
    $status_label = [
        'aktif' => 'Aktif',
        'dibuka' => 'Dibuka',
        'selesai' => 'Selesai'
    ];
    $filter_info[] = "Status: " . ($status_label[$filterStatus] ?? $filterStatus);
}
if (!empty($filterFormulir)) {
    $formulir_label = [
        'dibuka' => 'Dibuka',
        'ditutup' => 'Ditutup',
        'belum_diatur' => 'Belum Diatur'
    ];
    $filter_info[] = "Formulir: " . ($formulir_label[$filterFormulir] ?? $filterFormulir);
}
if (!empty($searchTerm)) {
    $filter_info[] = "Pencarian: \"" . htmlspecialchars($searchTerm) . "\"";
}

// Generate PDF
try {
    // Gunakan orientation portrait seperti laporan kelas
    $pdf = LKP_ReportFactory::createKelasReport(); // Portrait mode
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $pdf->setReportInfo(
        'Laporan Data Gelombang',
        '', // subtitle kosong, akan otomatis pakai periode hari ini
        '../../../assets/img/favicon.png', // path ke logo
        $filter_info,
        $totalGelombang,
        $_SESSION['nama'] ?? 'Administrator Sistem' // Nama user yang login
    );
    
    $pdf->AddPage();
    
    // Buat tabel data gelombang dengan layout yang lebih rapi
    if (!empty($dataArray)) {
        // Gunakan tabel yang sudah diperbaiki
        createGelombangTableWithMultiCell($pdf, $dataArray);
        
        // Tambah ringkasan data di bawah tabel
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Data Gelombang:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik
        $stats_tahun = [];
        $stats_status = [];
        $total_kapasitas = 0;
        $total_siswa = 0;
        $gelombang_dengan_kelas = 0;
        
        foreach ($dataArray as $row) {
            $tahun = $row['tahun'] ?? date('Y');
            $status = $row['status'] ?? 'Draft';
            $kapasitas = (int)($row['kuota_maksimal'] ?? 0);
            $jumlah_siswa = (int)($row['jumlah_siswa'] ?? 0);
            $jumlah_kelas = (int)($row['jumlah_kelas'] ?? 0);
            
            // Statistik tahun
            $stats_tahun[$tahun] = ($stats_tahun[$tahun] ?? 0) + 1;
            
            // Statistik status
            $stats_status[$status] = ($stats_status[$status] ?? 0) + 1;
            
            // Total kapasitas dan siswa
            $total_kapasitas += $kapasitas;
            $total_siswa += $jumlah_siswa;
            
            if ($jumlah_kelas > 0) {
                $gelombang_dengan_kelas++;
            }
        }
        
        $rata_rata_siswa = $gelombang_dengan_kelas > 0 ? round($total_siswa / $gelombang_dengan_kelas, 1) : 0;
        
        // Tampilkan ringkasan dengan format yang lebih rapi
        $no_ringkasan = 1;
        
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Kapasitas Semua Gelombang: ' . number_format($total_kapasitas) . ' siswa', 0, 1, 'L');
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Rata-rata Siswa per Gelombang: ' . $rata_rata_siswa . ' siswa', 0, 1, 'L');
        
        // Statistik per tahun
        if (count($stats_tahun) > 1) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 4, 'Distribusi per Tahun:', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8);
            
            foreach ($stats_tahun as $tahun => $jumlah) {
                $pdf->Cell(5, 4, '', 0, 0, 'L');
                $pdf->Cell(0, 4, '- Tahun ' . $tahun . ': ' . number_format($jumlah) . ' gelombang', 0, 1, 'L');
            }
        }
        
        // Statistik per status
        if (count($stats_status) > 1) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 4, 'Distribusi per Status:', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 8);
            
            foreach ($stats_status as $status => $jumlah) {
                $pdf->Cell(5, 4, '', 0, 0, 'L');
                $pdf->Cell(0, 4, '- Status ' . ucfirst($status) . ': ' . number_format($jumlah) . ' gelombang', 0, 1, 'L');
            }
        }
        
    } else {
        // Jika tidak ada data
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data gelombang yang sesuai dengan filter yang diterapkan', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Silakan coba dengan filter yang berbeda atau reset filter', 0, 1, 'C');
    }
    
    // Tambah tanda tangan (akan otomatis cek apakah muat di halaman)
    $pdf->addSignature();
    
    // Generate filename berdasarkan filter dan timestamp
    $filename_parts = ['Laporan_Gelombang'];
    
    if (!empty($filterTahun)) {
        $filename_parts[] = 'Tahun_' . $filterTahun;
    }
    if (!empty($filterStatus)) {
        $filename_parts[] = 'Status_' . ucfirst($filterStatus);
    }
    if (!empty($filterFormulir)) {
        $filename_parts[] = 'Formulir_' . ucfirst($filterFormulir);
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan gelombang.</p>
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
                                    <i class='bi bi-list-ul'></i> Daftar Gelombang
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
                                <strong>Total Records:</strong> " . $totalGelombang . "<br>
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

// Function untuk membuat tabel dengan MultiCell untuk gelombang
function createGelombangTableWithMultiCell($pdf, $data) {
    // Header kolom untuk laporan gelombang - UPDATED: hapus tahun dan gelombang ke
    $headers = ['NO', 'NAMA GELOMBANG', 'STATUS', 'KELAS', 'SISWA', 'FORMULIR', 'KUOTA'];
    
    // Lebar kolom yang disesuaikan untuk portrait (total ~185mm)
    $widths = [12, 60, 23, 20, 20, 25, 25]; 
    
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
        $namaGelombangData = prepareGelombangNameData($row['nama_gelombang'] ?? '', 32); // Kurangi untuk portrait
        
        // Tentukan apakah perlu 2 baris
        $needTwoRows = $namaGelombangData['needSplit'];
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
        
        // NAMA GELOMBANG - baris pertama
        $pdf->Cell($widths[1], $rowHeight, $namaGelombangData['line1'], 1, 0, 'L', $fill);
        
        // STATUS - merge jika ada 2 baris
        $status = getStatusText($row['status'] ?? '');
        if ($needTwoRows) {
            $pdf->Cell($widths[2], $rowHeight * 2, $status, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[2], $rowHeight, $status, 1, 0, 'C', $fill);
        }
        
        // KELAS - merge jika ada 2 baris
        $jumlah_kelas = (int)($row['jumlah_kelas'] ?? 0);
        $kelas_text = $jumlah_kelas > 0 ? $jumlah_kelas . ' kelas' : '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[3], $rowHeight * 2, $kelas_text, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[3], $rowHeight, $kelas_text, 1, 0, 'C', $fill);
        }
        
        // SISWA - merge jika ada 2 baris
        $jumlah_siswa = (int)($row['jumlah_siswa'] ?? 0);
        $siswa_text = $jumlah_siswa > 0 ? $jumlah_siswa . ' siswa' : '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[4], $rowHeight * 2, $siswa_text, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[4], $rowHeight, $siswa_text, 1, 0, 'C', $fill);
        }
        
        // FORMULIR - merge jika ada 2 baris
        $formulir = getFormulirText($row['status_pendaftaran'] ?? null);
        if ($needTwoRows) {
            $pdf->Cell($widths[5], $rowHeight * 2, $formulir, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[5], $rowHeight, $formulir, 1, 0, 'C', $fill);
        }
        
        // KUOTA - merge jika ada 2 baris
        $kuota = $row['kuota_maksimal'] ? number_format($row['kuota_maksimal']) . ' orang' : 'Belum diatur';
        if ($needTwoRows) {
            $pdf->Cell($widths[6], $rowHeight * 2, $kuota, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[6], $rowHeight, $kuota, 1, 0, 'C', $fill);
        }
        
        $pdf->Ln();
        
        // BARIS KEDUA (jika diperlukan)
        if ($needTwoRows) {
            // Skip kolom yang sudah di-merge
            $pdf->Cell($widths[0], 0, '', 0, 0); // NO - kosong
            
            // NAMA GELOMBANG - baris kedua
            $pdf->Cell($widths[1], $rowHeight, $namaGelombangData['line2'], 1, 0, 'L', $fill);
            
            // Skip kolom lain yang sudah di-merge
            $pdf->Cell($widths[2], 0, '', 0, 0); // STATUS - kosong
            $pdf->Cell($widths[3], 0, '', 0, 0); // KELAS - kosong
            $pdf->Cell($widths[4], 0, '', 0, 0); // SISWA - kosong
            $pdf->Cell($widths[5], 0, '', 0, 0); // FORMULIR - kosong
            $pdf->Cell($widths[6], 0, '', 0, 0); // KUOTA - kosong
            
            $pdf->Ln();
        }
    }
}

// Helper functions
function prepareGelombangNameData($namaGelombang, $maxChars = 32) { // Disesuaikan untuk portrait
    if (strlen($namaGelombang) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $namaGelombang,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata
    $words = explode(' ', $namaGelombang);
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

function getStatusText($status) {
    switch($status) {
        case 'aktif': return 'Aktif';
        case 'dibuka': return 'Dibuka';
        case 'selesai': return 'Selesai';
        default: return 'Draft';
    }
}

function getFormulirText($status_pendaftaran) {
    if ($status_pendaftaran === 'dibuka') {
        return 'Dibuka';
    } elseif ($status_pendaftaran === 'ditutup') {
        return 'Ditutup';
    } else {
        return 'Belum diatur';
    }
}
?>