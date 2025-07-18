<?php
// File: test_lkp_pdf.php (buat di root project)
// Test library LKP_PDF dengan data dummy

require_once('vendor/fpdf/lkp_pdf.php');

// Data dummy siswa
$dataSiswa = [
    [
        'nik' => '6301012345678901',
        'nama' => 'Ahmad Suryadi Pratama',
        'tempat_lahir' => 'Banjarmasin',
        'tanggal_lahir' => '1995-05-15',
        'jenis_kelamin' => 'Laki-Laki',
        'pendidikan_terakhir' => 'SMA',
        'no_hp' => '081234567890',
        'email' => 'ahmad@email.com',
        'nama_kelas' => 'Web Development',
        'nama_gelombang' => 'Gelombang 1'
    ],
    [
        'nik' => '6301012345678902',
        'nama' => 'Siti Aminah Rahayu',
        'tempat_lahir' => 'Tabalong',
        'tanggal_lahir' => '1992-08-20',
        'jenis_kelamin' => 'Perempuan',
        'pendidikan_terakhir' => 'D3 Akuntansi',
        'no_hp' => '082345678901',
        'email' => 'siti@email.com',
        'nama_kelas' => 'Digital Marketing',
        'nama_gelombang' => 'Gelombang 2'
    ],
    [
        'nik' => '6301012345678903',
        'nama' => 'Budi Santoso',
        'tempat_lahir' => 'Barito Kuala',
        'tanggal_lahir' => '1988-12-10',
        'jenis_kelamin' => 'Laki-Laki',
        'pendidikan_terakhir' => 'S1 Teknik',
        'no_hp' => '083456789012',
        'email' => 'budi@email.com',
        'nama_kelas' => 'Graphic Design',
        'nama_gelombang' => 'Gelombang 1'
    ],
    [
        'nik' => '6301012345678904',
        'nama' => 'Rina Marlina Sari',
        'tempat_lahir' => 'Hulu Sungai Selatan',
        'tanggal_lahir' => '1997-03-25',
        'jenis_kelamin' => 'Perempuan',
        'pendidikan_terakhir' => 'SMK',
        'no_hp' => '084567890123',
        'email' => 'rina@email.com',
        'nama_kelas' => 'Office Administration',
        'nama_gelombang' => 'Gelombang 3'
    ],
    [
        'nik' => '6301012345678905',
        'nama' => 'Dedi Kurniawan',
        'tempat_lahir' => 'Tanah Laut',
        'tanggal_lahir' => '1990-07-12',
        'jenis_kelamin' => 'Laki-Laki',
        'pendidikan_terakhir' => 'SMA',
        'no_hp' => '085678901234',
        'email' => 'dedi@email.com',
        'nama_kelas' => 'Web Development',
        'nama_gelombang' => 'Gelombang 2'
    ]
];

// Filter info dummy
$filter_info = [
    'Status: Aktif',
    'Kelas: Web Development', 
    'Gelombang: Gelombang 1'
];

try {
    // Test dengan format final - sesuai permintaan
    $pdf = LKP_ReportFactory::createSiswaReport(); // Auto landscape untuk 7 kolom
    $pdf->AliasNbPages();
    
    $pdf->setReportInfo(
        'Laporan Data Siswa',
        '', // Kosong, akan auto pakai "Periode [tanggal hari ini]"
        'assets/img/favicon.png', // Logo akan muncul di atas center, lebih dekat
        $filter_info,
        count($dataSiswa),
        'Administrator Test'
    );
    
    $pdf->AddPage();
    
    // Test tabel versi compact (kolom penting saja)
    $pdf->createSiswaTable($dataSiswa);
    
    // Test multiple pages dengan banyak data
    $moreDummyData = [];
    for ($i = 6; $i <= 35; $i++) {
        $moreDummyData[] = [
            'nik' => '630101234567890' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'nama' => 'Siswa Test Nomor ' . $i,
            'tempat_lahir' => 'Kota Test ' . $i,
            'tanggal_lahir' => '199' . ($i % 10) . '-0' . ($i % 9 + 1) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'jenis_kelamin' => $i % 2 == 0 ? 'Laki-Laki' : 'Perempuan',
            'pendidikan_terakhir' => 'SMA',
            'no_hp' => '08123456789' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'email' => 'siswa' . $i . '@test.com',
            'nama_kelas' => 'Kelas Test ' . ($i % 3 + 1),
            'nama_gelombang' => 'Gelombang ' . ($i % 2 + 1)
        ];
    }
    
    // Tambah data dummy untuk test multi halaman
    $pdf->createSiswaTable($moreDummyData);
    
    // Test signature (akan otomatis cek apakah muat di halaman atau buat halaman baru)
    $pdf->addSignature();
    
    // Output
    $pdf->Output('I', 'test_laporan_siswa_final.pdf');
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    echo "<br><br>Pastikan:";
    echo "<br>1. File lkp_pdf.php (final version) ada di vendor/fpdf/";
    echo "<br>2. File fpdf.php ada di vendor/fpdf/"; 
    echo "<br>3. File logo ada di assets/img/favicon.png";
    echo "<br>4. Tidak ada syntax error";
    echo "<br><br><strong>Test ini akan menunjukkan:</strong>";
    echo "<br>- Logo lebih dekat dengan nama lembaga";
    echo "<br>- Meta info 1 kolom di halaman 1 saja";
    echo "<br>- Judul laporan & periode hanya di halaman 1";
    echo "<br>- Signature tidak terpisah halaman";
    echo "<br>- Tabel compact dengan kolom penting saja";
}
?>