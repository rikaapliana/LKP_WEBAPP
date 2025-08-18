<?php
// pages/admin/pendaftar/export_excel.php (Versi Tabel Saja)

session_start();
require_once '../../../includes/auth.php';
require_once '../../../includes/db.php';
require_once '../../../vendor/autoload.php'; // Panggil autoloader Composer

// Pastikan user adalah admin
requireAdminAuth();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

//======================================================================
// 1. MENGAMBIL FILTER DAN MEMBUAT QUERY DINAMIS
//======================================================================
$id_gelombang = $_GET['gelombang'] ?? '';
$tahun = $_GET['tahun'] ?? '';

$whereClauses = [];
$params = [];
$types = '';

if (!empty($id_gelombang) && is_numeric($id_gelombang)) {
    $whereClauses[] = "p.id_gelombang = ?";
    $params[] = $id_gelombang;
    $types .= 'i';
}
if (!empty($tahun) && is_numeric($tahun)) {
    $whereClauses[] = "g.tahun = ?";
    $params[] = $tahun;
    $types .= 'i';
}
$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Helper function untuk eksekusi query dengan aman
function executeQuery($conn, $sql, $types, $params) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($types) && count($params) > 0) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

//======================================================================
// 2. MENGAMBIL SEMUA DATA DARI DATABASE
//======================================================================

// Data Statistik Umum dan Jenis Kelamin
$sqlUmum = "
    SELECT
        COUNT(p.id_pendaftar) as total_pendaftar,
        SUM(CASE WHEN p.jenis_kelamin = 'Laki-Laki' THEN 1 ELSE 0 END) as total_laki,
        SUM(CASE WHEN p.jenis_kelamin = 'Perempuan' THEN 1 ELSE 0 END) as total_perempuan,
        SUM(CASE WHEN p.status_pendaftaran = 'Belum di Verifikasi' THEN 1 ELSE 0 END) as belum_verifikasi
    FROM pendaftar p
    LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
    $whereSql
";
$resultUmum = executeQuery($conn, $sqlUmum, $types, $params);
$dataStatistik = mysqli_fetch_assoc($resultUmum);

// Data Pendidikan
$sqlPendidikan = "
    SELECT pendidikan_terakhir as label, COUNT(*) as value
    FROM pendaftar p
    LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
    $whereSql
    GROUP BY pendidikan_terakhir
    ORDER BY value DESC
";
$resultPendidikan = executeQuery($conn, $sqlPendidikan, $types, $params);
$dataPendidikan = mysqli_fetch_all($resultPendidikan, MYSQLI_ASSOC);

// Data Kategori Usia
$sqlUsia = "
    SELECT
        CASE
            WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) < 17 THEN '< 17 Tahun'
            WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 17 AND 20 THEN '17-20 Tahun'
            WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 21 AND 25 THEN '21-25 Tahun'
            WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN 26 AND 30 THEN '26-30 Tahun'
            ELSE '> 30 Tahun'
        END as label,
        COUNT(*) as value
    FROM pendaftar p
    LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
    $whereSql
    GROUP BY label
    ORDER BY FIELD(label, '< 17 Tahun', '17-20 Tahun', '21-25 Tahun', '26-30 Tahun', '> 30 Tahun')
";
$resultUsia = executeQuery($conn, $sqlUsia, $types, $params);
$dataUsia = mysqli_fetch_all($resultUsia, MYSQLI_ASSOC);


//======================================================================
// 3. MEMBUAT FILE EXCEL DENGAN PHPSPREADSHEET
//======================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Statistik Pendaftar');

// Judul Laporan
$sheet->mergeCells('A1:B1');
$sheet->setCellValue('A1', 'Laporan Statistik Pendaftar');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->setCellValue('A2', 'Diekspor pada: ' . date('d F Y, H:i') . ' WIB');
$sheet->mergeCells('A2:B2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row = 4;

// Bagian Statistik Umum
$sheet->setCellValue('A'.$row, 'Statistik Umum');
$sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
$sheet->mergeCells('A'.$row.':B'.$row);
$row++;
$sheet->setCellValue('A'.$row, 'Total Pendaftar');
$sheet->setCellValue('B'.$row, $dataStatistik['total_pendaftar']);
$row++;
$sheet->setCellValue('A'.$row, 'Laki-laki');
$sheet->setCellValue('B'.$row, $dataStatistik['total_laki']);
$row++;
$sheet->setCellValue('A'.$row, 'Perempuan');
$sheet->setCellValue('B'.$row, $dataStatistik['total_perempuan']);
$row++;
$sheet->setCellValue('A'.$row, 'Belum Diverifikasi');
$sheet->setCellValue('B'.$row, $dataStatistik['belum_verifikasi']);
$row+=2;

// Bagian Pendidikan
$sheet->setCellValue('A'.$row, 'Berdasarkan Pendidikan');
$sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
$sheet->mergeCells('A'.$row.':B'.$row);
$row++;
foreach($dataPendidikan as $data) {
    $sheet->setCellValue('A'.$row, $data['label']);
    $sheet->setCellValue('B'.$row, $data['value']);
    $row++;
}
$row++;

// Bagian Usia
$sheet->setCellValue('A'.$row, 'Berdasarkan Kategori Usia');
$sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
$sheet->mergeCells('A'.$row.':B'.$row);
$row++;
foreach($dataUsia as $data) {
    $sheet->setCellValue('A'.$row, $data['label']);
    $sheet->setCellValue('B'.$row, $data['value']);
    $row++;
}

// Atur lebar kolom
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setWidth(20);

//======================================================================
// 4. MENGIRIM FILE EXCEL KE BROWSER UNTUK DI-DOWNLOAD
//======================================================================
$fileName = "statistik_pendaftar_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();