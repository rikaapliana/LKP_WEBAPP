<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Set timezone Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// Function untuk format tanggal Indonesia
function formatTanggalIndonesia($date, $withTime = false) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    if ($withTime) {
        $hari = date('d');
        $bulanNum = date('n');
        $tahun = date('Y');
        $jam = date('H:i:s');
        return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun . ', ' . $jam;
    } else {
        $hari = date('d');
        $bulanNum = date('n');
        $tahun = date('Y');
        return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun;
    }
}

// Function helper untuk terbilang
function terbilang($angka) {
    $angka = abs($angka);
    $baca = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
    $terbilang = "";
    
    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } else if ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " belas";
    } else if ($angka < 100) {
        $terbilang = terbilang($angka / 10) . " puluh" . terbilang($angka % 10);
    } else if ($angka < 200) {
        $terbilang = " seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $terbilang = terbilang($angka / 100) . " ratus" . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $terbilang = " seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $terbilang = terbilang($angka / 1000) . " ribu" . terbilang($angka % 1000);
    }
    
    return $terbilang;
}

// Ambil filter dari URL
$filterJK = isset($_GET['jk']) ? $_GET['jk'] : '';
$filterAngkatan = isset($_GET['angkatan']) ? $_GET['angkatan'] : '';
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
    $whereConditions[] = "(i.nama LIKE ? OR i.nik LIKE ? OR i.angkatan LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}

// Query data instruktur dengan kelas yang diampu
$query = "SELECT i.*, 
          GROUP_CONCAT(DISTINCT CONCAT(k.nama_kelas, ' (', g.nama_gelombang, ')') SEPARATOR ', ') as kelas_diampu,
          COUNT(DISTINCT k.id_kelas) as jumlah_kelas
          FROM instruktur i 
          LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " GROUP BY i.id_instruktur ORDER BY i.nama ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

// Hitung statistik
$totalInstruktur = mysqli_num_rows($result);
$instrukturLaki = 0;
$instrukturPerempuan = 0;
$totalKelasAktif = 0;
$dataArray = [];

// Ambil semua data untuk statistik dan simpan dalam array
while ($row = mysqli_fetch_assoc($result)) {
    $dataArray[] = $row;
    if ($row['jenis_kelamin'] == 'Laki-Laki') $instrukturLaki++;
    if ($row['jenis_kelamin'] == 'Perempuan') $instrukturPerempuan++;
    $totalKelasAktif += $row['jumlah_kelas'];
}

// Set nama file dengan ekstensi .xls untuk auto-recognition Excel
$filename = "Laporan_Data_Instruktur_" . date('Y-m-d_H-i-s') . ".xls";

// Header yang tepat untuk Excel format
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');
header('Pragma: public');

// Tidak pakai DOCTYPE dan HTML5 tags - langsung table HTML
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:x="urn:schemas-microsoft-com:office:excel" 
      xmlns="http://www.w3.org/TR/REC-html40">

<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="ProgId" content="Excel.Sheet">
<meta name="Generator" content="Microsoft Excel 15">
<!--[if gte mso 9]><xml>
<x:ExcelWorkbook>
<x:ExcelWorksheets>
<x:ExcelWorksheet>
<x:Name>Data Instruktur</x:Name>
<x:WorksheetOptions>
<x:DisplayGridlines/>
</x:WorksheetOptions>
</x:ExcelWorksheet>
</x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml><![endif]-->

<style>
<!--
table {
    mso-displayed-decimal-separator:"\.";
    mso-displayed-thousand-separator:"\,";
}

.xl65 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#2E75B6;
    mso-pattern:auto none;
    color:white;
    font-size:16pt;
    font-weight:700;
    text-align:center;
}

.xl66 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#D5E4F7;
    mso-pattern:auto none;
    font-size:11pt;
    font-weight:700;
    text-align:center;
}

.xl67 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#4472C4;
    mso-pattern:auto none;
    color:white;
    font-size:10pt;
    font-weight:700;
    text-align:center;
}

.xl68 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    font-size:9pt;
    text-align:center;
    mso-number-format:"\@";
}

.xl69 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    font-size:9pt;
    text-align:left;
    mso-number-format:"\@";
}

.xl70 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#C6EFCE;
    mso-pattern:auto none;
    color:#006100;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl71 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FFC7CE;
    mso-pattern:auto none;
    color:#9C0006;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl72 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#DDEBF7;
    mso-pattern:auto none;
    color:#1F4E79;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl73 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FCE4EC;
    mso-pattern:auto none;
    color:#880E4F;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl74 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#E7E6E6;
    mso-pattern:auto none;
    font-size:10pt;
    font-weight:700;
}

.xl75 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#F2F2F2;
    mso-pattern:auto none;
    font-size:10pt;
}

.xl76 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FFF2CC;
    mso-pattern:auto none;
    font-size:10pt;
    font-weight:700;
}

.xl77 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#DEEBF7;
    mso-pattern:auto none;
    color:#1F4E79;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}
-->
</style>
</head>

<body>

<!-- Header Institusi -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="40">
    <td height="40" colspan="7" class="xl65">LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER</td>
</tr>
<tr height="25">
    <td height="25" colspan="7" class="xl66">Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571</td>
</tr>
<tr height="20">
    <td height="20" colspan="7" class="xl66">Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id</td>
</tr>
<tr height="10">
    <td height="10" colspan="7" style="border:none;"></td>
</tr>
</table>

<!-- Info Dokumen -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="30">
    <td height="30" colspan="7" class="xl66">LAPORAN DATA INSTRUKTUR - PERIODE <?= strtoupper(formatTanggalIndonesia('', false)) ?></td>
</tr>
<tr>
    <td class="xl74" width="150">Nomor Dokumen:</td>
    <td class="xl75" colspan="6"><?= date('Y') ?>/LKP-PC/INST/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?></td>
</tr>
<tr>
    <td class="xl74">Tanggal Export:</td>
    <td class="xl75" colspan="6"><?= formatTanggalIndonesia('', true) ?> WIB</td>
</tr>
<tr>
    <td class="xl74">Total Record:</td>
    <td class="xl75" colspan="6"><?= $totalInstruktur ?> (<?= terbilang($totalInstruktur) ?>) instruktur</td>
</tr>
<tr>
    <td class="xl74">Dicetak Oleh:</td>
    <td class="xl75" colspan="6">Administrator Sistem</td>
</tr>
<?php if (!empty($filterJK) || !empty($filterAngkatan) || !empty($searchTerm)): ?>
<tr height="5">
    <td height="5" colspan="7" style="border:none;"></td>
</tr>
<tr>
    <td colspan="7" class="xl76">
        FILTER YANG DITERAPKAN:
        <?php if (!empty($filterJK)): ?>• Jenis Kelamin: <?= htmlspecialchars($filterJK) ?> <?php endif; ?>
        <?php if (!empty($filterAngkatan)): ?>• Angkatan: <?= htmlspecialchars($filterAngkatan) ?> <?php endif; ?>
        <?php if (!empty($searchTerm)): ?>• Pencarian: "<?= htmlspecialchars($searchTerm) ?>" <?php endif; ?>
    </td>
</tr>
<?php endif; ?>
<tr height="10">
    <td height="10" colspan="7" style="border:none;"></td>
</tr>
</table>

<!-- Data Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<thead>
<tr>
    <td class="xl67" width="40">No</td>
    <td class="xl67" width="120">NIK</td>
    <td class="xl67" width="250">Nama Lengkap</td>
    <td class="xl67" width="80">JK</td>
    <td class="xl67" width="150">Angkatan</td>
    <td class="xl67" width="300">Kelas yang Diampu</td>
    <td class="xl67" width="100">Jml Kelas</td>
</tr>
</thead>
<tbody>
<?php if (!empty($dataArray)): ?>
    <?php $no = 1; ?>
    <?php foreach ($dataArray as $instruktur): ?>
    <tr>
        <td class="xl68"><?= $no++ ?></td>
        <td class="xl68"><?= htmlspecialchars($instruktur['nik'] ?? '-') ?></td>
        <td class="xl69"><?= htmlspecialchars($instruktur['nama']) ?></td>
        <td class="<?= $instruktur['jenis_kelamin'] == 'Laki-Laki' ? 'xl72' : 'xl73' ?>"><?= $instruktur['jenis_kelamin'] == 'Laki-Laki' ? 'L' : 'P' ?></td>
        <td class="xl68"><?= htmlspecialchars($instruktur['angkatan'] ?? '-') ?></td>
        <td class="xl69"><?= $instruktur['kelas_diampu'] ? htmlspecialchars($instruktur['kelas_diampu']) : 'Belum ada kelas' ?></td>
        <td class="xl77"><?= $instruktur['jumlah_kelas'] ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="7" class="xl68" style="font-style:italic; color:#999;">
            Tidak ada data instruktur yang sesuai dengan filter yang diterapkan
        </td>
    </tr>
<?php endif; ?>
</tbody>
</table>

<!-- Summary -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:20px;">
<tr height="10">
    <td height="10" colspan="7" style="border:none;"></td>
</tr>
<tr height="30">
    <td height="30" colspan="7" class="xl67">RINGKASAN DATA INSTRUKTUR</td>
</tr>
<tr>
    <td class="xl74" width="200">Total Instruktur:</td>
    <td class="xl75"><?= $totalInstruktur ?> orang</td>
    <td class="xl74">Instruktur Laki-laki:</td>
    <td class="xl72"><?= $instrukturLaki ?> orang</td>
    <td colspan="3" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Total Kelas Aktif:</td>
    <td class="xl77"><?= $totalKelasAktif ?> kelas</td>
    <td class="xl74">Instruktur Perempuan:</td>
    <td class="xl73"><?= $instrukturPerempuan ?> orang</td>
    <td colspan="3" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Rata-rata Kelas/Instruktur:</td>
    <td class="xl75"><?= $totalInstruktur > 0 ? round($totalKelasAktif / $totalInstruktur, 1) : 0 ?> kelas</td>
    <td class="xl74">Persentase Laki-laki:</td>
    <td class="xl75"><?= $totalInstruktur > 0 ? round(($instrukturLaki / $totalInstruktur) * 100, 1) : 0 ?>%</td>
    <td colspan="3" class="xl75"></td>
</tr>
</table>

<!-- Footer -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:30px;">
<tr height="10">
    <td height="10" colspan="7" style="border:none;"></td>
</tr>
<tr>
    <td colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none; font-weight:bold;">Tabalong, <?= formatTanggalIndonesia('', false) ?></td>
</tr>
<tr>
    <td colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none;">Mengetahui,</td>
</tr>
<tr height="60">
    <td height="60" colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none;"></td>
</tr>
<tr>
    <td colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none; font-weight:bold; text-decoration:underline;">Awiek Hadi Widodo</td>
</tr>
<tr>
    <td colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none;">Direktur</td>
</tr>
<tr height="20">
    <td height="20" colspan="7" style="border:none;"></td>
</tr>
<tr>
    <td colspan="7" style="border:none; text-align:center; font-size:10pt; color:#666; font-style:italic;">
        Laporan ini dibuat secara otomatis oleh Sistem Informasi LKP Pradata Komputer
    </td>
</tr>
</table>

</body>
</html>