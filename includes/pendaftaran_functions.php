<?php
// File: includes/pendaftaran_functions.php
// Khusus untuk fungsi kelola pendaftaran

/**
 * Fungsi untuk cek status pendaftaran berdasarkan gelombang
 */
function cekStatusPendaftaran($conn, $id_gelombang = null) {
    // Jika tidak ada id_gelombang, ambil gelombang aktif
    if (!$id_gelombang) {
        $gelombang = getGelombangAktif($conn);
        $id_gelombang = $gelombang ? $gelombang['id_gelombang'] : 0;
    }
    
    $query = "SELECT pp.*, g.nama_gelombang, g.tahun, g.status as status_gelombang,
                     (SELECT COUNT(*) FROM pendaftar p 
                      WHERE p.status_pendaftaran = 'Terverifikasi') as jumlah_terdaftar
              FROM pengaturan_pendaftaran pp 
              JOIN gelombang g ON pp.id_gelombang = g.id_gelombang 
              WHERE pp.id_gelombang = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_gelombang);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        // Cek apakah kuota sudah penuh
        if ($data['jumlah_terdaftar'] >= $data['kuota_maksimal']) {
            $data['status_pendaftaran'] = 'ditutup';
            $data['alasan_tutup'] = 'Kuota pendaftaran sudah penuh';
            
            // Update status ke database jika belum ditutup
            updateStatusPendaftaran($conn, $id_gelombang, 'ditutup');
        }
        
        return $data;
    }
    
    return false;
}

/**
 * Fungsi untuk update status pendaftaran
 */
function updateStatusPendaftaran($conn, $id_gelombang, $status, $admin_id = null) {
    $query = "UPDATE pengaturan_pendaftaran 
              SET status_pendaftaran = ?, updated_at = CURRENT_TIMESTAMP";
    
    if ($admin_id) {
        $query .= ", dibuat_oleh = ?";
        $stmt = $conn->prepare($query . " WHERE id_gelombang = ?");
        $stmt->bind_param("sii", $status, $admin_id, $id_gelombang);
    } else {
        $stmt = $conn->prepare($query . " WHERE id_gelombang = ?");
        $stmt->bind_param("si", $status, $id_gelombang);
    }
    
    return $stmt->execute();
}

/**
 * Fungsi untuk toggle status pendaftaran
 */
function toggleStatusPendaftaran($conn, $id_gelombang, $admin_id) {
    $status_current = cekStatusPendaftaran($conn, $id_gelombang);
    
    if ($status_current) {
        $new_status = ($status_current['status_pendaftaran'] == 'dibuka') ? 'ditutup' : 'dibuka';
        
        // Cek kuota jika akan dibuka
        if ($new_status == 'dibuka' && $status_current['jumlah_terdaftar'] >= $status_current['kuota_maksimal']) {
            return [
                'success' => false, 
                'message' => 'Tidak dapat membuka pendaftaran karena kuota sudah penuh!'
            ];
        }
        
        $result = updateStatusPendaftaran($conn, $id_gelombang, $new_status, $admin_id);
        
        return [
            'success' => $result,
            'message' => $result ? 'Status pendaftaran berhasil diubah menjadi ' . $new_status : 'Gagal mengubah status',
            'new_status' => $new_status
        ];
    }
    
    return ['success' => false, 'message' => 'Gelombang tidak ditemukan'];
}

/**
 * Fungsi untuk mendapatkan gelombang aktif
 */
function getGelombangAktif($conn) {
    $query = "SELECT * FROM gelombang WHERE status = 'aktif' ORDER BY tahun DESC, gelombang_ke DESC LIMIT 1";
    $result = $conn->query($query);
    return $result->fetch_assoc();
}

/**
 * Fungsi untuk validasi apakah publik bisa mendaftar
 */
function validasiPendaftaran($conn, $id_gelombang = null) {
    if (!$id_gelombang) {
        $gelombang = getGelombangAktif($conn);
        if (!$gelombang) {
            return [
                'boleh_daftar' => false,
                'pesan' => 'Belum ada gelombang pendaftaran yang dibuka saat ini.',
                'kode_status' => 'NO_GELOMBANG'
            ];
        }
        $id_gelombang = $gelombang['id_gelombang'];
    }
    
    $status = cekStatusPendaftaran($conn, $id_gelombang);
    
    if (!$status) {
        return [
            'boleh_daftar' => false,
            'pesan' => 'Pengaturan pendaftaran belum dikonfigurasi oleh administrator.',
            'kode_status' => 'NO_CONFIG'
        ];
    }
    
    // Cek status ditutup oleh admin
    if ($status['status_pendaftaran'] == 'ditutup') {
        $pesan = isset($status['alasan_tutup']) 
            ? $status['alasan_tutup'] 
            : 'Formulir pendaftaran sedang ditutup sementara oleh administrator.';
        
        return [
            'boleh_daftar' => false,
            'pesan' => $pesan,
            'info_gelombang' => $status,
            'kode_status' => 'DITUTUP_ADMIN'
        ];
    }
    
    // Cek kuota penuh (auto-close)
    if ($status['jumlah_terdaftar'] >= $status['kuota_maksimal']) {
        return [
            'boleh_daftar' => false,
            'pesan' => 'Kuota pendaftaran sudah penuh (' . $status['jumlah_terdaftar'] . '/' . $status['kuota_maksimal'] . ' peserta).',
            'info_gelombang' => $status,
            'kode_status' => 'KUOTA_PENUH'
        ];
    }
    
    return [
        'boleh_daftar' => true,
        'pesan' => 'Formulir pendaftaran sedang dibuka untuk ' . $status['nama_gelombang'],
        'info_gelombang' => $status,
        'kode_status' => 'DIBUKA'
    ];
}

/**
 * Fungsi untuk update kuota gelombang
 */
function updateKuotaGelombang($conn, $id_gelombang, $kuota_baru, $admin_id) {
    $stmt = $conn->prepare("UPDATE pengaturan_pendaftaran SET kuota_maksimal = ?, dibuat_oleh = ?, updated_at = CURRENT_TIMESTAMP WHERE id_gelombang = ?");
    $stmt->bind_param("iii", $kuota_baru, $admin_id, $id_gelombang);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Kuota berhasil diupdate'];
    } else {
        return ['success' => false, 'message' => 'Gagal update kuota: ' . $conn->error];
    }
}
?>