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
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$filterGelombang = isset($_GET['gelombang']) ? $_GET['gelombang'] : '';
$filterJK = isset($_GET['jk']) ? $_GET['jk'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterStatus)) {
    $whereConditions[] = "s.status_aktif = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($filterKelas)) {
    $whereConditions[] = "k.nama_kelas = ?";
    $params[] = $filterKelas;
    $types .= "s";
}

if (!empty($filterGelombang)) {
    $whereConditions[] = "g.nama_gelombang = ?";
    $params[] = $filterGelombang;
    $types .= "s";
}

if (!empty($filterJK)) {
    $whereConditions[] = "s.jenis_kelamin = ?";
    $params[] = $filterJK;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(s.nama LIKE ? OR s.nik LIKE ? OR s.email LIKE ? OR s.tempat_lahir LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= "ssss";
}

// Query data siswa dengan filter
$query = "SELECT s.*, k.nama_kelas, g.nama_gelombang 
          FROM siswa s 
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY s.nama ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

// Hitung statistik
$totalSiswa = mysqli_num_rows($result);
$siswaAktif = 0;
$siswaLaki = 0;
$siswaPerempuan = 0;
$dataArray = [];

// Ambil semua data untuk statistik dan simpan dalam array
while ($row = mysqli_fetch_assoc($result)) {
    $dataArray[] = $row;
    if ($row['status_aktif'] == 'aktif') $siswaAktif++;
    if ($row['jenis_kelamin'] == 'Laki-Laki') $siswaLaki++;
    if ($row['jenis_kelamin'] == 'Perempuan') $siswaPerempuan++;
}

$siswaInaktif = $totalSiswa - $siswaAktif;

// Set nama file dengan ekstensi .xls untuk auto-recognition Excel
$filename = "Laporan_Data_Siswa_" . date('Y-m-d_H-i-s') . ".xls";

// Header yang tepat untuk Excel format - ini yang penting!
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
<x:Name>Data Siswa</x:Name>
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
-->
</style>
</head>

<body>

<!-- Header Institusi -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="40">
    <td height="40" colspan="12" class="xl65">LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER</td>
</tr>
<tr height="25">
    <td height="25" colspan="12" class="xl66">Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571</td>
</tr>
<tr height="20">
    <td height="20" colspan="12" class="xl66">Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id</td>
</tr>
<tr height="10">
    <td height="10" colspan="12" style="border:none;"></td>
</tr>
</table>

<!-- Info Dokumen -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="30">
    <td height="30" colspan="12" class="xl66">LAPORAN DATA SISWA - PERIODE <?= strtoupper(formatTanggalIndonesia('', false)) ?></td>
</tr>
<tr>
    <td class="xl74" width="150">Nomor Dokumen:</td>
    <td class="xl75" colspan="11"><?= date('Y') ?>/LKP-PC/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?></td>
</tr>
<tr>
    <td class="xl74">Tanggal Export:</td>
    <td class="xl75" colspan="11"><?= formatTanggalIndonesia('', true) ?> WIB</td>
</tr>
<tr>
    <td class="xl74">Total Record:</td>
    <td class="xl75" colspan="11"><?= $totalSiswa ?> (<?= terbilang($totalSiswa) ?>) siswa</td>
</tr>
<tr>
    <td class="xl74">Dicetak Oleh:</td>
    <td class="xl75" colspan="11">Administrator Sistem</td>
</tr>
<?php if (!empty($filterStatus) || !empty($filterKelas) || !empty($filterGelombang) || !empty($filterJK) || !empty($searchTerm)): ?>
<tr height="5">
    <td height="5" colspan="12" style="border:none;"></td>
</tr>
<tr>
    <td colspan="12" class="xl76">
        FILTER YANG DITERAPKAN:
        <?php if (!empty($filterStatus)): ?>• Status: <?= ucfirst($filterStatus) ?> <?php endif; ?>
        <?php if (!empty($filterKelas)): ?>• Kelas: <?= htmlspecialchars($filterKelas) ?> <?php endif; ?>
        <?php if (!empty($filterGelombang)): ?>• Gelombang: <?= htmlspecialchars($filterGelombang) ?> <?php endif; ?>
        <?php if (!empty($filterJK)): ?>• Jenis Kelamin: <?= htmlspecialchars($filterJK) ?> <?php endif; ?>
        <?php if (!empty($searchTerm)): ?>• Pencarian: "<?= htmlspecialchars($searchTerm) ?>" <?php endif; ?>
    </td>
</tr>
<?php endif; ?>
<tr height="10">
    <td height="10" colspan="12" style="border:none;"></td>
</tr>
</table>

<!-- Data Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<thead>
<tr>
    <td class="xl67" width="40">No</td>
    <td class="xl67" width="120">NIK</td>
    <td class="xl67" width="200">Nama Lengkap</td>
    <td class="xl67" width="120">Tempat Lahir</td>
    <td class="xl67" width="100">Tanggal Lahir</td>
    <td class="xl67" width="80">JK</td>
    <td class="xl67" width="150">Pendidikan</td>
    <td class="xl67" width="120">No. HP</td>
    <td class="xl67" width="200">Email</td>
    <td class="xl67" width="120">Kelas</td>
    <td class="xl67" width="100">Gelombang</td>
    <td class="xl67" width="80">Status</td>
</tr>
</thead>
<tbody>
<?php if (!empty($dataArray)): ?>
    <?php $no = 1; ?>
    <?php foreach ($dataArray as $siswa): ?>
    <tr>
        <td class="xl68"><?= $no++ ?></td>
        <td class="xl68"><?= htmlspecialchars($siswa['nik']) ?></td>
        <td class="xl69"><?= htmlspecialchars($siswa['nama']) ?></td>
        <td class="xl68"><?= htmlspecialchars($siswa['tempat_lahir']) ?></td>
        <td class="xl68"><?= date('d/m/Y', strtotime($siswa['tanggal_lahir'])) ?></td>
        <td class="<?= $siswa['jenis_kelamin'] == 'Laki-Laki' ? 'xl72' : 'xl73' ?>"><?= $siswa['jenis_kelamin'] == 'Laki-Laki' ? 'L' : 'P' ?></td>
        <td class="xl68"><?= htmlspecialchars($siswa['pendidikan_terakhir']) ?></td>
        <td class="xl68"><?= htmlspecialchars($siswa['no_hp']) ?></td>
        <td class="xl69"><?= htmlspecialchars($siswa['email'] ?? '-') ?></td>
        <td class="xl68"><?= htmlspecialchars($siswa['nama_kelas'] ?? 'Belum ditentukan') ?></td>
        <td class="xl68"><?= htmlspecialchars($siswa['nama_gelombang'] ?? '-') ?></td>
        <td class="<?= $siswa['status_aktif'] == 'aktif' ? 'xl70' : 'xl71' ?>"><?= ucfirst($siswa['status_aktif']) ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="12" class="xl68" style="font-style:italic; color:#999;">
            Tidak ada data siswa yang sesuai dengan filter yang diterapkan
        </td>
    </tr>
<?php endif; ?>
</tbody>
</table>

<!-- Summary -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:20px;">
<tr height="10">
    <td height="10" colspan="12" style="border:none;"></td>
</tr>
<tr height="30">
    <td height="30" colspan="12" class="xl67">RINGKASAN DATA SISWA</td>
</tr>
<tr>
    <td class="xl74" width="200">Total Siswa:</td>
    <td class="xl75"><?= $totalSiswa ?> orang</td>
    <td class="xl74">Siswa Laki-laki:</td>
    <td class="xl72"><?= $siswaLaki ?> orang</td>
    <td colspan="8" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Siswa Aktif:</td>
    <td class="xl70"><?= $siswaAktif ?> orang</td>
    <td class="xl74">Siswa Perempuan:</td>
    <td class="xl73"><?= $siswaPerempuan ?> orang</td>
    <td colspan="8" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Siswa Tidak Aktif:</td>
    <td class="xl71"><?= $siswaInaktif ?> orang</td>
    <td class="xl74">Persentase Aktif:</td>
    <td class="xl75"><?= $totalSiswa > 0 ? round(($siswaAktif / $totalSiswa) * 100, 1) : 0 ?>%</td>
    <td colspan="8" class="xl75"></td>
</tr>
</table>

<!-- Footer -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:30px;">
<tr height="10">
    <td height="10" colspan="12" style="border:none;"></td>
</tr>
<tr>
    <td colspan="8" style="border:none;"></td>
    <td colspan="4" style="border:none; font-weight:bold;">Tabalong, <?= formatTanggalIndonesia('', false) ?></td>
</tr>
<tr>
    <td colspan="8" style="border:none;"></td>
    <td colspan="4" style="border:none;">Mengetahui,</td>
</tr>
<tr height="60">
    <td height="60" colspan="8" style="border:none;"></td>
    <td colspan="4" style="border:none;"></td>
</tr>
<tr>
    <td colspan="8" style="border:none;"></td>
    <td colspan="4" style="border:none; font-weight:bold; text-decoration:underline;">Awiek Hadi Widodo</td>
</tr>
<tr>
    <td colspan="8" style="border:none;"></td>
    <td colspan="4" style="border:none;">Direktur</td>
</tr>
<tr height="20">
    <td height="20" colspan="12" style="border:none;"></td>
</tr>
<tr>
    <td colspan="12" style="border:none; text-align:center; font-size:10pt; color:#666; font-style:italic;">
        Laporan ini dibuat secara otomatis oleh Sistem Informasi LKP Pradata Komputer
    </td>
</tr>
</table>

</body>
</html>