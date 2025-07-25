<?php
session_start();  
require_once '../../../includes/auth.php';  
requireInstrukturAuth();

include '../../../includes/db.php';
require_once('../../../vendor/fpdf/lkp_pdf.php');

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

// Ambil parameter filter dari URL - disesuaikan dengan parameter yang sebenarnya
$filterTanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$filterJadwal = isset($_GET['jadwal']) ? $_GET['jadwal'] : '';
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$filterBulan = isset($_GET['bulan']) ? $_GET['bulan'] : '';
$filterTahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Jika ada parameter jadwal, ambil info kelas dari jadwal tersebut
if (!empty($filterJadwal)) {
    $jadwalQuery = "SELECT j.*, k.nama_kelas, k.id_kelas, g.nama_gelombang 
                    FROM jadwal j
                    JOIN kelas k ON j.id_kelas = k.id_kelas
                    LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                    WHERE j.id_jadwal = ? AND k.id_instruktur = ?";
    
    $stmtJadwal = mysqli_prepare($conn, $jadwalQuery);
    mysqli_stmt_bind_param($stmtJadwal, "ii", $filterJadwal, $id_instruktur);
    mysqli_stmt_execute($stmtJadwal);
    $jadwalResult = mysqli_stmt_get_result($stmtJadwal);
    
    if ($jadwalData = mysqli_fetch_assoc($jadwalResult)) {
        $filterKelas = $jadwalData['nama_kelas'];
        $jadwalInfo = $jadwalData;
        
        // Set filter bulan dan tahun berdasarkan tanggal jadwal
        if (!empty($filterTanggal)) {
            $filterBulan = date('n', strtotime($filterTanggal));
            $filterTahun = date('Y', strtotime($filterTanggal));
        }
    } else {
        echo "<!DOCTYPE html>
        <html><head><title>Jadwal Not Found</title></head><body>
        <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
            <h3>Jadwal Tidak Ditemukan</h3>
            <p>Jadwal yang dipilih tidak ditemukan atau bukan milik Anda.</p>
            <a href='index.php' style='color: #007bff;'>Kembali ke Halaman Absensi</a>
        </div></body></html>";
        exit;
    }
    
    mysqli_stmt_close($stmtJadwal);
}

// Jika hanya ada parameter kelas (untuk laporan keseluruhan)
if (!empty($filterKelas) && empty($filterJadwal)) {
    // Mode laporan kelas keseluruhan - sampai hari ini
    $filterBulan = '';
    $filterTahun = date('Y');
}

// Validasi parameter wajib
if (empty($filterKelas)) {
    echo "<!DOCTYPE html>
    <html><head><title>Parameter Required</title></head><body>
    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h3>Parameter Kelas Diperlukan</h3>
        <p>Silakan pilih kelas terlebih dahulu untuk melihat laporan absensi.</p>
        <p><small>Debug Info: URL params = " . htmlspecialchars(http_build_query($_GET)) . "</small></p>
        <a href='index.php' style='color: #007bff;'>Kembali ke Halaman Absensi</a>
    </div></body></html>";
    exit;
}

// Build query absensi siswa berdasarkan kelas yang diampu instruktur
$whereConditions = ["k.id_instruktur = ?", "k.nama_kelas = ?"];
$params = [$id_instruktur, $filterKelas];
$types = "is";

// Filter berdasarkan bulan dan tahun jika ada
if (!empty($filterBulan)) {
    $whereConditions[] = "MONTH(j.tanggal) = ?";
    $params[] = $filterBulan;
    $types .= "i";
}

if (!empty($filterTahun)) {
    $whereConditions[] = "YEAR(j.tanggal) = ?";
    $params[] = $filterTahun;
    $types .= "i";
}

// Query data absensi siswa - disesuaikan dengan sistem yang ada
$query = "SELECT 
            s.id_siswa,
            s.nama as nama_siswa,
            s.nik,
            k.nama_kelas,
            g.nama_gelombang,
            COUNT(DISTINCT j.id_jadwal) as total_pertemuan_terjadwal,
            COUNT(DISTINCT CASE WHEN j.tanggal <= CURDATE() THEN j.id_jadwal END) as total_pertemuan_terlaksana,
            COUNT(DISTINCT abs.id_absen) as total_kehadiran_tercatat,
            SUM(CASE WHEN abs.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN abs.status = 'izin' THEN 1 ELSE 0 END) as izin,
            SUM(CASE WHEN abs.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
            SUM(CASE WHEN abs.status = 'tanpa keterangan' THEN 1 ELSE 0 END) as alpha
          FROM siswa s
          JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN jadwal j ON k.id_kelas = j.id_kelas
          LEFT JOIN absensi_siswa abs ON s.id_siswa = abs.id_siswa AND j.id_jadwal = abs.id_jadwal
          WHERE " . implode(" AND ", $whereConditions) . " AND s.status_aktif = 'aktif'
          GROUP BY s.id_siswa, s.nama, s.nik, k.nama_kelas, g.nama_gelombang
          ORDER BY s.nama ASC";

// Execute query
try {
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // Ambil semua data
    $dataArray = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Hitung persentase kehadiran berdasarkan pertemuan yang sudah terlaksana
        $persentase = $row['total_pertemuan_terlaksana'] > 0 ? 
                     round(($row['hadir'] / $row['total_pertemuan_terlaksana']) * 100, 1) : 0;
        $row['persentase_kehadiran'] = $persentase;
        
        // Hitung total tidak hadir (alpha + izin + sakit)
        $row['tidak_hadir_total'] = $row['alpha'] + $row['izin'] + $row['sakit'];
        
        $dataArray[] = $row;
    }

    $totalSiswa = count($dataArray);
    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    error_log("Database error in cetak_laporan.php: " . $e->getMessage());
    
    echo "<!DOCTYPE html>
    <html><head><title>Error Database</title></head><body>
    <div style='text-align: center; margin-top: 50px; font-family: Arial;'>
        <h3>Terjadi Kesalahan Database</h3>
        <p>Silakan coba lagi atau hubungi administrator.</p>
        <a href='index.php' style='color: #007bff;'>Kembali ke Halaman Absensi</a>
    </div></body></html>";
    exit;
}

// Buat informasi filter untuk header PDF
$filter_info = [];
$filter_info[] = "Instruktur: " . htmlspecialchars($nama_instruktur);
$filter_info[] = "Kelas: " . htmlspecialchars($filterKelas);

// Jika dipanggil dari jadwal tertentu
if (!empty($filterJadwal) && isset($jadwalInfo)) {
    $filter_info[] = "Jadwal: " . date('d/m/Y', strtotime($jadwalInfo['tanggal'])) . 
                     " (" . $jadwalInfo['waktu_mulai'] . " - " . $jadwalInfo['waktu_selesai'] . ")";
} else {
    if (!empty($filterBulan)) {
        $namaBulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $filter_info[] = "Bulan: " . $namaBulan[$filterBulan] . " " . $filterTahun;
    } else {
        $filter_info[] = "Tahun: " . $filterTahun;
    }
}

// Generate PDF
try {
    // Auto pilih orientation - 6 kolom cocok untuk portrait
    $pdf = LKP_ReportFactory::createKelasReport();
    $pdf->AliasNbPages();
    
    // Set informasi laporan
    $subtitle = "Kelas: " . $filterKelas;
    if (!empty($filterJadwal) && isset($jadwalInfo)) {
        $subtitle .= " • Jadwal: " . date('d/m/Y', strtotime($jadwalInfo['tanggal']));
    } elseif (!empty($filterBulan)) {
        $subtitle .= " • Periode: " . $namaBulan[$filterBulan] . " " . $filterTahun;
    } else {
        $subtitle .= " • Tahun: " . $filterTahun;
    }
    
    $pdf->setReportInfo(
        'Laporan Absensi Siswa',
        '',
        '../../../assets/img/favicon.png',
        $filter_info,
        $totalSiswa,
        $nama_instruktur . ' (Instruktur)'
    );

    $pdf->AddPage();
    
    // Buat tabel data absensi
    if (!empty($dataArray)) {
        createAbsensiTableInstruktur($pdf, $dataArray);
        
        // Tambah ringkasan statistik di bawah tabel
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 5, 'Ringkasan Statistik Kehadiran:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        
        // Hitung statistik keseluruhan
        $total_pertemuan_terjadwal = 0;
        $total_pertemuan_terlaksana = 0;
        $total_hadir_all = 0;
        $total_izin_all = 0;
        $total_sakit_all = 0;
        $total_alpha_all = 0;
        $siswa_hadir_baik = 0; // >= 80%
        $siswa_hadir_kurang = 0; // < 80%
        
        foreach ($dataArray as $row) {
            $total_pertemuan_terjadwal += $row['total_pertemuan_terjadwal'];
            $total_pertemuan_terlaksana += $row['total_pertemuan_terlaksana'];
            $total_hadir_all += $row['hadir'];
            $total_izin_all += $row['izin'];
            $total_sakit_all += $row['sakit'];
            $total_alpha_all += $row['alpha'];
            
            if ($row['persentase_kehadiran'] >= 80) {
                $siswa_hadir_baik++;
            } else {
                $siswa_hadir_kurang++;
            }
        }
        
        $rata_kehadiran = $total_pertemuan_terlaksana > 0 ? 
                         round(($total_hadir_all / $total_pertemuan_terlaksana) * 100, 1) : 0;
        
        // Tampilkan ringkasan
        $no_ringkasan = 1;
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Siswa: ' . number_format($totalSiswa) . ' siswa', 0, 1, 'L');
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Pertemuan Terjadwal: ' . number_format($total_pertemuan_terjadwal / $totalSiswa) . ' pertemuan', 0, 1, 'L');
        
        $pdf->Cell(5, 4, $no_ringkasan++ . '.', 0, 0, 'L');
        $pdf->Cell(0, 4, ' Total Pertemuan Terlaksana: ' . number_format($total_pertemuan_terlaksana / $totalSiswa) . ' pertemuan', 0, 1, 'L');
        
        
    } else {
        // Jika tidak ada data
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 20, 'Tidak ada data absensi untuk kelas yang dipilih', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'Pastikan kelas sudah memiliki jadwal dan siswa aktif', 0, 1, 'C');
    }
    
    // Tambah tanda tangan
    $pdf->addSignature();
    
    // Generate filename
    $filename_parts = ['Laporan_Absensi'];
    $filename_parts[] = str_replace(' ', '_', $filterKelas);
    
    if (!empty($filterBulan)) {
        $filename_parts[] = str_pad($filterBulan, 2, '0', STR_PAD_LEFT) . '-' . $filterTahun;
    } else {
        $filename_parts[] = $filterTahun;
    }
    
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
                                <p>Terjadi kesalahan saat membuat file PDF laporan absensi.</p>
                                <hr>
                                <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            </div>
                            
                            <div class='d-grid gap-2 d-md-flex justify-content-md-end'>
                                <a href='javascript:history.back()' class='btn btn-secondary'>
                                    <i class='bi bi-arrow-left'></i> Kembali
                                </a>
                                <a href='index.php' class='btn btn-primary'>
                                    <i class='bi bi-list-ul'></i> Halaman Absensi
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

// Function untuk membuat tabel absensi khusus instruktur
function createAbsensiTableInstruktur($pdf, $data) {
    // Header kolom untuk laporan absensi - disesuaikan dengan sistem yang ada
    $headers = ['NO', 'NAMA SISWA', 'HADIR', 'IZIN', 'SAKIT', 'ALPHA', 'PERSENTASE'];
    
    // Lebar kolom yang disesuaikan
    $widths = [12, 60, 20, 18, 18, 18, 25]; // Total ~171mm
    
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
        
        // Persiapkan data untuk teks panjang
        $namaSiswa = prepareTextData($row['nama_siswa'] ?? '', 35);
        
        $needTwoRows = $namaSiswa['needSplit'];
        $rowHeight = $needTwoRows ? 7 : 7;
        
        // NO - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[0], $rowHeight * 2, $no++, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[0], $rowHeight, $no++, 1, 0, 'C', $fill);
        }
        
        // NAMA SISWA - baris pertama
        $pdf->Cell($widths[1], $rowHeight, $namaSiswa['line1'], 1, 0, 'L', $fill);
        
        // HADIR - merge jika ada 2 baris
        $hadir = $row['hadir'] . '/' . $row['total_pertemuan_terlaksana'];
        if ($needTwoRows) {
            $pdf->Cell($widths[2], $rowHeight * 2, $hadir, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[2], $rowHeight, $hadir, 1, 0, 'C', $fill);
        }
        
        // IZIN - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[3], $rowHeight * 2, $row['izin'], 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[3], $rowHeight, $row['izin'], 1, 0, 'C', $fill);
        }
        
        // SAKIT - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[4], $rowHeight * 2, $row['sakit'], 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[4], $rowHeight, $row['sakit'], 1, 0, 'C', $fill);
        }
        
        // ALPHA - merge jika ada 2 baris
        if ($needTwoRows) {
            $pdf->Cell($widths[5], $rowHeight * 2, $row['alpha'], 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[5], $rowHeight, $row['alpha'], 1, 0, 'C', $fill);
        }
        
        // PERSENTASE - merge jika ada 2 baris dengan color indication
        $persentase = $row['persentase_kehadiran'] . '%';
        if ($needTwoRows) {
            $pdf->Cell($widths[6], $rowHeight * 2, $persentase, 1, 0, 'C', $fill);
        } else {
            $pdf->Cell($widths[6], $rowHeight, $persentase, 1, 0, 'C', $fill);
        }
        
        $pdf->Ln();
        
        // BARIS KEDUA (jika diperlukan)
        if ($needTwoRows) {
            $pdf->Cell($widths[0], 0, '', 0, 0); // NO - kosong
            
            // NAMA SISWA - baris kedua
            $pdf->Cell($widths[1], $rowHeight, $namaSiswa['line2'], 1, 0, 'L', $fill);
            
            $pdf->Cell($widths[2], 0, '', 0, 0); // HADIR - kosong
            $pdf->Cell($widths[3], 0, '', 0, 0); // IZIN - kosong
            $pdf->Cell($widths[4], 0, '', 0, 0); // SAKIT - kosong
            $pdf->Cell($widths[5], 0, '', 0, 0); // ALPHA - kosong
            $pdf->Cell($widths[6], 0, '', 0, 0); // PERSENTASE - kosong
            
            $pdf->Ln();
        }
    }
}

function prepareTextData($text, $maxChars = 25) {
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