<?php
session_start();  
require_once '../../../includes/auth.php';  
requireInstrukturAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

// Set timezone Makassar (WITA)
date_default_timezone_set('Asia/Makassar');

// Ambil ID instruktur yang sedang login
$stmt = $conn->prepare("SELECT id_instruktur, nama FROM instruktur WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$instrukturData = $stmt->get_result()->fetch_assoc();

if (!$instrukturData) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit();
}

$id_instruktur = $instrukturData['id_instruktur'];
$nama_instruktur = $instrukturData['nama'];

// Ambil parameter filter dari URL
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$filterTahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Debug: Log parameter yang diterima
error_log("cetak_laporan.php - Parameters: kelas=" . $filterKelas . ", tahun=" . $filterTahun);

// Validasi parameter wajib
if (empty($filterKelas)) {
    echo "<!DOCTYPE html>
    <html><head><title>Parameter Required</title></head><body>
    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h3>Parameter Kelas Diperlukan</h3>
        <p>Silakan pilih kelas terlebih dahulu untuk melihat laporan nilai.</p>
        <p><small>Debug Info: URL params = " . htmlspecialchars(http_build_query($_GET)) . "</small></p>
        <a href='index.php' style='color: #007bff;'>Kembali ke Halaman Kelola Nilai</a>
    </div></body></html>";
    exit;
}

// Build query nilai siswa berdasarkan kelas yang diampu instruktur
$whereConditions = ["k.id_instruktur = ?"];
$params = [$id_instruktur];
$types = "i";

// Filter berdasarkan ID kelas atau nama kelas
if (is_numeric($filterKelas)) {
    $whereConditions[] = "k.id_kelas = ?";
    $params[] = (int)$filterKelas;
    $types .= "i";
    error_log("Filter by ID kelas: " . $filterKelas);
} else {
    $whereConditions[] = "k.nama_kelas = ?";
    $params[] = $filterKelas;
    $types .= "s";
    error_log("Filter by nama kelas: " . $filterKelas);
}

// Query data nilai siswa - SIMPLIFIED untuk debugging
$query = "SELECT 
            s.id_siswa,
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
          FROM siswa s
          JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN nilai n ON s.id_siswa = n.id_siswa AND s.id_kelas = n.id_kelas
          WHERE " . implode(" AND ", $whereConditions) . " AND s.status_aktif = 'aktif'
          ORDER BY s.nama ASC";

error_log("Final query: " . $query);
error_log("Parameters: " . implode(", ", $params));

// Execute query
try {
    error_log("Preparing statement with types: " . $types);
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($conn));
    }
    
    error_log("Binding parameters: " . json_encode($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    error_log("Executing statement...");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        throw new Exception("Error getting result: " . mysqli_error($conn));
    }

    // Ambil semua data
    $dataArray = [];
    $namaKelas = '';
    $namaGelombang = '';
    
    $rowCount = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($namaKelas)) {
            $namaKelas = $row['nama_kelas'];
            $namaGelombang = $row['nama_gelombang'];
        }
        
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
        
        $dataArray[] = $row;
        $rowCount++;
    }

    $totalSiswa = count($dataArray);
    error_log("Query executed successfully. Found $totalSiswa rows.");
    error_log("Nama kelas: " . $namaKelas);
    
    mysqli_stmt_close($stmt);

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
        <a href='index.php' style='color: #007bff;'>Kembali ke Halaman Kelola Nilai</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
$filter_info[] = "Instruktur: " . htmlspecialchars($nama_instruktur);
$filter_info[] = "Kelas: " . htmlspecialchars($namaKelas);
if (!empty($namaGelombang)) {
    $filter_info[] = "Gelombang: " . htmlspecialchars($namaGelombang);
}


// Generate PDF
try {
    // Gunakan portrait untuk format standard
    $pdf = LKP_ReportFactory::createKelasReport(); // Gunakan yang sama seperti absensi (portrait)
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $subtitle = "Kelas: " . $namaKelas;
    if (!empty($namaGelombang)) {
        $subtitle .= " • Gelombang: " . $namaGelombang;
    }
    
    $pdf->setReportInfo(
        'Laporan Nilai Siswa',
        '',
        '../../../assets/img/favicon.png',
        $filter_info,
        $totalSiswa,
        $nama_instruktur . ' (Instruktur)'
    );

    $pdf->AddPage();
    
    // Buat tabel data nilai
    if (!empty($dataArray)) {
        createNilaiTableInstruktur($pdf, $dataArray);
        
        // Tambah ringkasan statistik di bawah tabel
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Statistik Nilai:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik keseluruhan
        $lulus_count = 0;
        $tidak_lulus_count = 0;
        $belum_lengkap_count = 0;
        $total_rata_nilai = 0;
        $siswa_dengan_nilai = 0;
        
        // Statistik per komponen - perbaiki pengecekan
        $word_total = 0; $word_count = 0;
        $excel_total = 0; $excel_count = 0;
        $ppt_total = 0; $ppt_count = 0;
        $internet_total = 0; $internet_count = 0;
        $pengembangan_total = 0; $pengembangan_count = 0;
        
        foreach ($dataArray as $row) {
            // Status kelulusan
            switch ($row['status_kelulusan_fix']) {
                case 'lulus':
                    $lulus_count++;
                    break;
                case 'tidak lulus':
                    $tidak_lulus_count++;
                    break;
                default:
                    $belum_lengkap_count++;
                    break;
            }
            
            // Rata-rata nilai
            if ($row['rata_rata'] && $row['rata_rata'] > 0) {
                $total_rata_nilai += $row['rata_rata'];
                $siswa_dengan_nilai++;
            }
            
            // Statistik per komponen - perbaiki pengecekan
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
        
        $rata_keseluruhan = $siswa_dengan_nilai > 0 ? 
                           round($total_rata_nilai / $siswa_dengan_nilai, 1) : 0;
        
        // Tampilkan ringkasan
        $no_ringkasan = 1;
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Siswa: ' . number_format($totalSiswa) . ' siswa', 0, 1, 'L');
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Rata-rata Nilai Keseluruhan: ' . $rata_keseluruhan, 0, 1, 'L');
        
    }
    
    // Tambah tanda tangan
    $pdf->addSignature();
    
    // Generate filename
    $filename_parts = ['Laporan_Nilai'];
    $filename_parts[] = str_replace(' ', '_', $namaKelas);
    $filename_parts[] = $filterTahun;
    $filename_parts[] = date('Y-m-d_H-i-s');
    $filename = implode('_', $filename_parts) . '.pdf';
    
    // Output PDF
    $pdf->Output('I', $filename);
    
} catch (Exception $e) {
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan nilai.</p>
                                <hr>
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            </div>
                            
                            <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                <a href='javascript:history.back()' class='btn btn-secondary'>
                                    <i class='bi bi-arrow-left'></i> Kembali
                                </a>
                                <a href='index.php' class='btn btn-primary'>
                                    <i class='bi bi-clipboard-data'></i> Halaman Kelola Nilai
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

// Function untuk membuat tabel nilai khusus instruktur (Portrait)
function createNilaiTableInstruktur($pdf, $data) {
    // Header kolom untuk laporan nilai - disesuaikan untuk portrait
    $headers = ['NO', 'NAMA SISWA', 'WORD', 'EXCEL', 'PPT', 'NET', 'SOFT', 'RATA', 'STATUS'];
    
    // Lebar kolom yang disesuaikan untuk portrait (A4 = ~210mm)
    $widths = [10, 50, 15, 15, 15, 15, 15, 18, 22]; // Total ~175mm (muat dalam portrait)
    
    // Header tabel
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(70, 130, 180);
    $pdf->SetTextColor(255, 255, 255);
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Data tabel
    $pdf->SetFont('Arial', '', 7);
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
        
        // Persiapkan data untuk teks panjang - lebih pendek untuk portrait
        $namaSiswa = prepareTextDataNilai($row['nama_siswa'] ?? '', 25);
        
        $needTwoRows = $namaSiswa['needSplit'];
        $rowHeight = $needTwoRows ? 5 : 5;
        
        // NO - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[0], $rowHeight * 2, $no++, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[0], $rowHeight, $no++, 1, 0, 'C', $fill);
        }
        
        // NAMA SISWA - baris pertama
        $pdf->Cell($widths[1], $rowHeight, $namaSiswa['line1'], 1, 0, 'L', $fill);
        
        // WORD - merge jika ada 2 baris
        $nilaiWord = (isset($row['nilai_word']) && $row['nilai_word'] > 0) ? $row['nilai_word'] : '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[2], $rowHeight * 2, $nilaiWord, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[2], $rowHeight, $nilaiWord, 1, 0, 'C', $fill);
        }
        
        // EXCEL - merge jika ada 2 baris
        $nilaiExcel = (isset($row['nilai_excel']) && $row['nilai_excel'] > 0) ? $row['nilai_excel'] : '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[3], $rowHeight * 2, $nilaiExcel, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[3], $rowHeight, $nilaiExcel, 1, 0, 'C', $fill);
        }
        
        // PPT - merge jika ada 2 baris
        $nilaiPpt = (isset($row['nilai_ppt']) && $row['nilai_ppt'] > 0) ? $row['nilai_ppt'] : '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[4], $rowHeight * 2, $nilaiPpt, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[4], $rowHeight, $nilaiPpt, 1, 0, 'C', $fill);
        }
        
        // INTERNET (disingkat NET) - merge jika ada 2 baris
        $nilaiInternet = (isset($row['nilai_internet']) && $row['nilai_internet'] > 0) ? $row['nilai_internet'] : '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[5], $rowHeight * 2, $nilaiInternet, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[5], $rowHeight, $nilaiInternet, 1, 0, 'C', $fill);
        }
        
        // SOFTSKILL (disingkat SOFT) - merge jika ada 2 baris
        $nilaiPengembangan = (isset($row['nilai_pengembangan']) && $row['nilai_pengembangan'] > 0) ? $row['nilai_pengembangan'] : '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[6], $rowHeight * 2, $nilaiPengembangan, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[6], $rowHeight, $nilaiPengembangan, 1, 0, 'C', $fill);
        }
        
        // RATA-RATA (disingkat RATA) - merge jika ada 2 baris
        $rataRata = (isset($row['rata_rata']) && $row['rata_rata'] > 0) ? number_format($row['rata_rata'], 1) : '-';
        if ($needTwoRows) {
            $pdf->Cell($widths[7], $rowHeight * 2, $rataRata, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[7], $rowHeight, $rataRata, 1, 0, 'C', $fill);
        }
        
        // STATUS - merge jika ada 2 baris
        $status = '';
        switch ($row['status_kelulusan_fix']) {
            case 'lulus':
                $status = 'LULUS';
                break;
            case 'tidak lulus':
                $status = 'T.LULUS';
                break;
            default:
                $status = 'SEMENTARA';
                break;
        }
        
        if ($needTwoRows) {
            $pdf->Cell($widths[8], $rowHeight * 2, $status, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[8], $rowHeight, $status, 1, 0, 'C', $fill);
        }
        
        $pdf->Ln();
        
        // BARIS KEDUA (jika diperlukan)
        if ($needTwoRows) {
            $pdf->Cell($widths[0], 0, '', 0, 0); // NO - kosong
            
            // NAMA SISWA - baris kedua
            $pdf->Cell($widths[1], $rowHeight, $namaSiswa['line2'], 1, 0, 'L', $fill);
            
            // Kolom lainnya - kosong
            for ($i = 2; $i < count($widths); $i++) {
                $pdf->Cell($widths[$i], 0, '', 0, 0);
            }
            
            $pdf->Ln();
        }
    }
}

function prepareTextDataNilai($text, $maxChars = 25) {
    if (strlen($text) <= $maxChars) {
        return [
            'needSplit' => false,
            'line1' => $text,
            'line2' => ''
        ];
    }
    
    // Split berdasarkan kata
    $words = explode(' ', $text);
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
?>