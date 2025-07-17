<?php
// File: pendaftaran.php (Public access)
include 'includes/db.php';
include 'includes/pendaftaran_functions.php';

// Cek status pendaftaran (public function)
$validasi = validasiPendaftaran($conn);
$gelombang_aktif = getGelombangAktif($conn);

// Jika ada error dalam validasi, set default values  
if (!$validasi) {
    $validasi = [
        'boleh_daftar' => false,
        'pesan' => 'Sistem pendaftaran sedang dalam pemeliharaan',
        'kode_status' => 'ERROR'
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Daftar kursus komputer di LKP Pradata Komputer Tabalong." />
    <title>Formulir Pendaftaran - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png" />
    <link href="assets/css/bootstrap-icons.css" rel="stylesheet" />
    <link href="assets/css/styles.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Alert Status Pendaftaran -->
<?php if (!$validasi['boleh_daftar']): ?>
<div class="alert alert-warning alert-dismissible fade show m-0" role="alert">
    <div class="container">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div class="flex-grow-1">
                <strong>Pendaftaran Ditutup!</strong> <?= htmlspecialchars($validasi['pesan']) ?>
                <?php if (isset($validasi['info_gelombang']) && $validasi['info_gelombang']['jumlah_terdaftar'] >= $validasi['info_gelombang']['kuota_maksimal']): ?>
                    <br><small>Kuota sudah penuh: <?= $validasi['info_gelombang']['jumlah_terdaftar'] ?>/<?= $validasi['info_gelombang']['kuota_maksimal'] ?> peserta</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php else: ?>
<div class="alert alert-success alert-dismissible fade show m-0" role="alert">
    <div class="container">
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div class="flex-grow-1">
                <strong>Pendaftaran Dibuka!</strong> <?= htmlspecialchars($validasi['pesan']) ?>
                <?php if (isset($validasi['info_gelombang'])): ?>
                    <br><small>Sisa kuota: <?= $validasi['info_gelombang']['kuota_maksimal'] - $validasi['info_gelombang']['jumlah_terdaftar'] ?>/<?= $validasi['info_gelombang']['kuota_maksimal'] ?> peserta</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Formulir Pendaftaran Kursus</h2>
            <p class="text-muted">Silakan lengkapi formulir di bawah untuk mendaftar.</p>
            
            <?php if (isset($validasi['info_gelombang'])): ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <h5 class="text-primary mb-1"><?= $validasi['info_gelombang']['nama_gelombang'] ?></h5>
                                        <small class="text-muted">Gelombang Aktif</small>
                                    </div>
                                    <div class="col-md-4">
                                        <h5 class="text-success mb-1"><?= $validasi['info_gelombang']['kuota_maksimal'] - $validasi['info_gelombang']['jumlah_terdaftar'] ?></h5>
                                        <small class="text-muted">Sisa Kuota</small>
                                    </div>
                                    <div class="col-md-4">
                                        <h5 class="text-info mb-1"><?= $validasi['info_gelombang']['jumlah_terdaftar'] ?></h5>
                                        <small class="text-muted">Sudah Terdaftar</small>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <?php 
                                $progress = round(($validasi['info_gelombang']['jumlah_terdaftar'] / $validasi['info_gelombang']['kuota_maksimal']) * 100, 1);
                                ?>
                                <div class="progress mt-3" style="height: 10px;">
                                    <div class="progress-bar bg-<?= $progress >= 80 ? 'danger' : ($progress >= 60 ? 'warning' : 'success') ?>" 
                                         style="width: <?= min($progress, 100) ?>%">
                                    </div>
                                </div>
                                <small class="text-muted"><?= $progress ?>% kuota terisi</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-10">
                <?php if ($validasi['boleh_daftar']): ?>
                    <form action="proses_pendaftaran.php" method="post" enctype="multipart/form-data" class="p-4 bg-white rounded shadow-sm">
                        <input type="hidden" name="id_gelombang" value="<?= $gelombang_aktif['id_gelombang'] ?? '' ?>">
                        
                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label for="nik" class="form-label">NIK</label>
                                <input type="text" class="form-control" id="nik" name="nik" maxlength="16" required>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="nama_pendaftar" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="nama_pendaftar" name="nama_pendaftar" required>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                                <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" required>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                                <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" required>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label">Jenis Kelamin</label><br>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="Laki-Laki" required>
                                    <label class="form-check-label" for="jk_l">Laki-laki</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="Perempuan">
                                    <label class="form-check-label" for="jk_p">Perempuan</label>
                                </div>
                            </div>

                            <div class="mb-3 col-md-6">
                                <label for="jam_pilihan" class="form-label">Pilihan Jam Kursus</label>
                                <select class="form-select" id="jam_pilihan" name="jam_pilihan" required>
                                    <option value="" selected disabled>Pilih Jam</option>
                                    <option value="08.00 - 09.00">08.00 - 09.00</option>
                                    <option value="09.00 - 10.00">09.00 - 10.00</option>
                                    <option value="10.00 - 11.00">10.00 - 11.00</option>
                                    <option value="11.00 - 12.00">11.00 - 12.00</option>
                                    <option value="13.00 - 14.00">13.00 - 14.00</option>
                                    <option value="14.00 - 15.00">14.00 - 15.00</option>
                                    <option value="15.00 - 16.00">15.00 - 16.00</option>
                                    <option value="16.00 - 17.00">16.00 - 17.00</option>
                                    <option value="17.00 - 18.00">17.00 - 18.00</option>
                                    <option value="19.00 - 20.00">19.00 - 20.00</option>
                                    <option value="20.00 - 21.00">20.00 - 21.00</option>
                                    <option value="21.00 - 22.00">21.00 - 22.00</option>
                                </select>
                            </div>

                            <div class="mb-3 col-md-6">
                                <label for="alamat_lengkap" class="form-label">Alamat Lengkap</label>
                                <textarea class="form-control" id="alamat_lengkap" name="alamat_lengkap" rows="2" required></textarea>
                            </div>

                            <div class="mb-3 col-md-6">
                                <label for="no_hp" class="form-label">No. HP</label>
                                <input type="text" class="form-control" id="no_hp" name="no_hp" required>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="email" class="form-label">Email Aktif</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3 col-md-6">
                                <label for="pendidikan_terakhir" class="form-label">Pendidikan Terakhir</label>
                                <select class="form-select" id="pendidikan_terakhir" name="pendidikan_terakhir" required>
                                    <option value="" selected disabled>Pilih Pendidikan</option>
                                    <option value="SD">SD</option>
                                    <option value="SLTP">SLTP</option>
                                    <option value="SLTA">SLTA</option>
                                    <option value="D1">D1</option>
                                    <option value="D2">D2</option>
                                    <option value="S1">S1</option>
                                    <option value="S2">S2</option>
                                    <option value="S3">S3</option>
                                </select>
                            </div>

                            <!-- Upload Dokumen -->
                            <div class="mb-3 col-md-6">
                                <label for="pas_foto" class="form-label">Pas Foto (JPG/PNG)</label>
                                <input type="file" class="form-control" id="pas_foto" name="pas_foto" accept="image/png, image/jpeg" required>
                                <small class="form-text text-muted">Unggah pas foto terbaru dengan pakaian formal (jas/kemeja).</small>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="ktp" class="form-label">Scan KTP</label>
                                <input type="file" class="form-control" id="ktp" name="ktp" accept=".jpg,.jpeg,.png,.pdf" required>
                                <small>Format file: JPG/PNG, Maksimal 2 MB</small>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="kk" class="form-label">Scan Kartu Keluarga</label>
                                <input type="file" class="form-control" id="kk" name="kk" accept=".jpg,.jpeg,.png,.pdf" required>
                                <small>Format file: JPG/PNG, Maksimal 2 MB</small>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="ijazah" class="form-label">Scan Ijazah Terakhir</label>
                                <input type="file" class="form-control" id="ijazah" name="ijazah" accept=".jpg,.jpeg,.png,.pdf" required>
                                <small>Format file: JPG/PNG, Maksimal 2 MB</small>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="bi bi-send me-2"></i>Daftar Sekarang
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Form Disabled State -->
                    <div class="p-4 bg-white rounded shadow-sm text-center">
                        <div class="py-5">
                            <i class="bi bi-lock-fill text-muted display-1 mb-4"></i>
                            <h4 class="text-muted mb-3">Formulir Pendaftaran Tidak Tersedia</h4>
                            <p class="text-muted mb-4"><?= htmlspecialchars($validasi['pesan']) ?></p>
                            
                            <?php if ($validasi['kode_status'] == 'DITUTUP' && isset($validasi['info_gelombang'])): ?>
                                <div class="alert alert-info">
                                    <h6 class="fw-bold">Informasi Gelombang</h6>
                                    <div class="row text-center mt-3">
                                        <div class="col">
                                            <div class="fw-bold"><?= $validasi['info_gelombang']['nama_gelombang'] ?></div>
                                            <small class="text-muted">Gelombang</small>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold"><?= $validasi['info_gelombang']['kuota_maksimal'] ?></div>
                                            <small class="text-muted">Kuota Maksimal</small>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold"><?= $validasi['info_gelombang']['jumlah_terdaftar'] ?></div>
                                            <small class="text-muted">Sudah Terdaftar</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <button class="btn btn-secondary" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh Halaman
                                </button>
                                <a href="index.php" class="btn btn-outline-primary ms-2">
                                    <i class="bi bi-house me-2"></i>Kembali ke Beranda
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
// Auto refresh setiap 5 menit untuk update status pendaftaran
setTimeout(function() {
    location.reload();
}, 300000); // 5 menit

// Validasi form sebelum submit
document.querySelector('form')?.addEventListener('submit', function(e) {
    const nikInput = document.getElementById('nik');
    const nik = nikInput.value;
    
    // Validasi NIK 16 digit
    if (nik.length !== 16 || !/^\d+$/.test(nik)) {
        e.preventDefault();
        alert('NIK harus terdiri dari 16 digit angka!');
        nikInput.focus();
        return false;
    }
    
    // Konfirmasi submit
    if (!confirm('Apakah data yang Anda masukkan sudah benar? Data tidak dapat diubah setelah dikirim.')) {
        e.preventDefault();
        return false;
    }
});
</script>
</body>
</html>