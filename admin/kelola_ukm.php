<?php
// admin/kelola_ukm.php

// AKTIFKAN ERROR REPORTING UNTUK DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Panggil file konfigurasi penting
require_once '../config/database.php';
require_once '../config/functions.php';

// 1. Cek status login dan peran
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// 2. Inisiasi Database
$database = new Database();
$db = $database->getConnection();

// Cek koneksi
if ($db === null) {
    die("<div class='alert alert-danger'>ERROR: Koneksi database gagal! Silakan cek file config/database.php</div>");
}

// 3. Ambil ID admin yang sedang login
$current_admin_id = $_SESSION['user_id'] ?? 0;


// =================================================================
// -------------------- LOGIKA CRUD UTAMA UKM ----------------------
// =================================================================

// --- PROSES TAMBAH UKM ---
if (isset($_POST['tambah'])) {
    // Ambil dan sanitasi SEMUA KOLOM DARI FORM TAMBAH
    $nama_ukm = sanitize($_POST['nama_ukm']);
    $kategori_id = sanitize($_POST['kategori_id']);
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $ketua_umum = sanitize($_POST['ketua_umum']);
    $email = sanitize($_POST['email'] ?? '');
    $no_telepon = sanitize($_POST['no_telepon']);
    $alamat_sekretariat = sanitize($_POST['alamat_sekretariat'] ?? '');
    $visi = sanitize($_POST['visi'] ?? '');
    $misi = sanitize($_POST['misi'] ?? '');
    $program_kerja = sanitize($_POST['program_kerja'] ?? '');
    $syarat_pendaftaran = sanitize($_POST['syarat_pendaftaran'] ?? '');
    $status = sanitize($_POST['status'] ?? 'aktif');
    $max_anggota = intval($_POST['max_anggota'] ?? 0);
    $biaya_pendaftaran = floatval($_POST['biaya_pendaftaran'] ?? 0.00);
    $ketua = sanitize($_POST['ketua'] ?? '');
    $kontak = sanitize($_POST['kontak'] ?? '');

    // Upload logo
    $logo = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_upload_name = uploadFile($_FILES['logo'], '../uploads/');
        if ($logo_upload_name !== false) {
            $logo = $logo_upload_name;
        } else {
            showAlert('Gagal upload logo. Pastikan format file (jpg/png) dan ukuran < 5MB.', 'warning');
            redirect('kelola_ukm.php');
            exit;
        }
    }

    // Query INSERT DENGAN SEMUA KOLOM
    $query = "INSERT INTO ukm (
        nama_ukm, kategori_id, deskripsi, ketua_umum, email, no_telepon, alamat_sekretariat, visi, misi, 
        program_kerja, syarat_pendaftaran, status, max_anggota, biaya_pendaftaran, ketua, kontak, logo, admin_id, created_at
    ) VALUES (
        :nama_ukm, :kategori_id, :deskripsi, :ketua_umum, :email, :no_telepon, :alamat_sekretariat, :visi, :misi, 
        :program_kerja, :syarat_pendaftaran, :status, :max_anggota, :biaya_pendaftaran, :ketua, :kontak, :logo, :admin_id, NOW()
    )";

    $stmt = $db->prepare($query);
    $params = [
        ':nama_ukm' => $nama_ukm, 
        ':kategori_id' => $kategori_id, 
        ':deskripsi' => $deskripsi, 
        ':ketua_umum' => $ketua_umum, 
        ':email' => $email, 
        ':no_telepon' => $no_telepon, 
        ':alamat_sekretariat' => $alamat_sekretariat, 
        ':visi' => $visi, 
        ':misi' => $misi, 
        ':program_kerja' => $program_kerja, 
        ':syarat_pendaftaran' => $syarat_pendaftaran, 
        ':status' => $status, 
        ':max_anggota' => $max_anggota, 
        ':biaya_pendaftaran' => $biaya_pendaftaran, 
        ':ketua' => $ketua, 
        ':kontak' => $kontak, 
        ':logo' => $logo, 
        ':admin_id' => $current_admin_id
    ];

    if ($stmt->execute($params)) {
        showAlert('UKM **' . $nama_ukm . '** berhasil ditambahkan!', 'success');
        redirect('kelola_ukm.php');
    } else {
        showAlert('Gagal menambahkan UKM. Error: ' . implode(', ', $stmt->errorInfo()), 'danger');
    }
    exit;
}

// --- PROSES EDIT UKM ---
if (isset($_POST['edit'])) {
    $id = sanitize($_POST['id_ukm']);

    // 1. Verifikasi Kepemilikan & Ambil data lama
    $stmt_check = $db->prepare("SELECT id, logo FROM ukm WHERE id = :id AND admin_id = :admin_id");
    $stmt_check->execute([':id' => $id, ':admin_id' => $current_admin_id]);
    $old_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$old_data) {
        showAlert('Akses ditolak. UKM tidak ditemukan atau bukan milik Anda.', 'danger');
        redirect('kelola_ukm.php');
        exit;
    }

    // 2. Ambil dan sanitasi data baru
    $nama_ukm = sanitize($_POST['nama_ukm']);
    $kategori_id = sanitize($_POST['kategori_id']);
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $ketua_umum = sanitize($_POST['ketua_umum']);
    $email = sanitize($_POST['email'] ?? '');
    $no_telepon = sanitize($_POST['no_telepon']);
    $alamat_sekretariat = sanitize($_POST['alamat_sekretariat'] ?? '');
    $visi = sanitize($_POST['visi'] ?? '');
    $misi = sanitize($_POST['misi'] ?? '');
    $program_kerja = sanitize($_POST['program_kerja'] ?? '');
    $syarat_pendaftaran = sanitize($_POST['syarat_pendaftaran'] ?? '');
    $status = sanitize($_POST['status']);
    $max_anggota = intval($_POST['max_anggota'] ?? 0);
    $biaya_pendaftaran = floatval($_POST['biaya_pendaftaran'] ?? 0.00);
    $ketua = sanitize($_POST['ketua'] ?? '');
    $kontak = sanitize($_POST['kontak'] ?? '');

    $params = [
        ':nama_ukm' => $nama_ukm, 
        ':kategori_id' => $kategori_id, 
        ':deskripsi' => $deskripsi, 
        ':ketua_umum' => $ketua_umum, 
        ':email' => $email,
        ':no_telepon' => $no_telepon, 
        ':alamat_sekretariat' => $alamat_sekretariat, 
        ':visi' => $visi, 
        ':misi' => $misi, 
        ':program_kerja' => $program_kerja, 
        ':syarat_pendaftaran' => $syarat_pendaftaran, 
        ':status' => $status, 
        ':max_anggota' => $max_anggota, 
        ':biaya_pendaftaran' => $biaya_pendaftaran, 
        ':ketua' => $ketua, 
        ':kontak' => $kontak,
        ':id' => $id,
        ':admin_id' => $current_admin_id
    ];

    // 3. Proses upload logo baru
    $set_clauses = [
        "nama_ukm = :nama_ukm",
        "kategori_id = :kategori_id",
        "deskripsi = :deskripsi",
        "ketua_umum = :ketua_umum",
        "email = :email",
        "no_telepon = :no_telepon",
        "alamat_sekretariat = :alamat_sekretariat",
        "visi = :visi",
        "misi = :misi",
        "program_kerja = :program_kerja",
        "syarat_pendaftaran = :syarat_pendaftaran",
        "status = :status",
        "max_anggota = :max_anggota",
        "biaya_pendaftaran = :biaya_pendaftaran",
        "ketua = :ketua",
        "kontak = :kontak"
    ];

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_upload_name = uploadFile($_FILES['logo'], '../uploads/');

        if ($logo_upload_name !== false) {
            // Hapus file lama jika ada
            if (!empty($old_data['logo']) && file_exists('../uploads/' . $old_data['logo'])) {
                unlink('../uploads/' . $old_data['logo']);
            }
            $set_clauses[] = "logo = :logo";
            $params[':logo'] = $logo_upload_name;
        } else {
            showAlert('Gagal upload logo baru. Update data lain dibatalkan.', 'warning');
            redirect('kelola_ukm.php');
            exit;
        }
    }

    // 4. Query UPDATE - DIPERBAIKI
    $query = "UPDATE ukm SET " . implode(", ", $set_clauses) . ", updated_at = NOW() WHERE id = :id AND admin_id = :admin_id";

    $stmt = $db->prepare($query);
    if ($stmt->execute($params)) {
        showAlert('UKM **' . $nama_ukm . '** berhasil diperbarui!', 'success');
        redirect('kelola_ukm.php');
    } else {
        showAlert('Gagal memperbarui UKM. Error: ' . implode(', ', $stmt->errorInfo()), 'danger');
    }
    exit;
}

// --- PROSES HAPUS UKM ---
if (isset($_GET['hapus'])) {
    $id = sanitize($_GET['hapus']);
    
    // Verifikasi Kepemilikan & Ambil data logo
    $stmt_check = $db->prepare("SELECT logo FROM ukm WHERE id = :id AND admin_id = :admin_id");
    $stmt_check->execute([':id' => $id, ':admin_id' => $current_admin_id]);
    $data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        showAlert('Akses ditolak. UKM tidak ditemukan atau bukan milik Anda.', 'danger');
        redirect('kelola_ukm.php');
        exit;
    }

    // Mulai transaksi untuk memastikan integritas data
    $db->beginTransaction();

    try {
        // 1. Hapus foto kegiatan
        $stmt_foto = $db->prepare("SELECT file_foto FROM foto_kegiatan WHERE ukm_id = ?");
        $stmt_foto->execute([$id]);
        $fotos = $stmt_foto->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fotos as $foto) {
            if (file_exists('../uploads/kegiatan/' . $foto['file_foto'])) {
                unlink('../uploads/kegiatan/' . $foto['file_foto']);
            }
        }
        
        // 2. Hapus data terkait (Cascade Delete manual)
        $db->prepare("DELETE FROM foto_kegiatan WHERE ukm_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM kegiatan_ukm WHERE ukm_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM foto_ukm WHERE ukm_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM pendaftaran WHERE ukm_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM pengurus_ukm WHERE ukm_id = ?")->execute([$id]);

        // 3. Hapus UKM utama
        $query = "DELETE FROM ukm WHERE id = :id AND admin_id = :admin_id"; 
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':admin_id', $current_admin_id);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            // 4. Hapus file logo lama
            if (!empty($data['logo']) && file_exists('../uploads/' . $data['logo'])) {
                unlink('../uploads/' . $data['logo']);
            }
            $db->commit();
            showAlert('UKM berhasil dihapus beserta semua data terkait.', 'success');
        } else {
            $db->rollBack();
            showAlert('Gagal menghapus UKM! Data tidak berubah.', 'danger');
        }
    } catch (Exception $e) {
        $db->rollBack();
        showAlert('Terjadi error saat menghapus data: ' . $e->getMessage(), 'danger');
    }

    redirect('kelola_ukm.php');
    exit;
}

// =================================================================
// -------------------- CRUD FOTO KEGIATAN -------------------------
// =================================================================

// UPLOAD FOTO KEGIATAN
if (isset($_POST['upload_foto_kegiatan'])) {
    $kegiatan_id = sanitize($_POST['kegiatan_id']);
    $caption = sanitize($_POST['caption'] ?? '');
    
    // Verifikasi kepemilikan kegiatan
    $stmt_check = $db->prepare("SELECT k.id, k.ukm_id FROM kegiatan_ukm k 
                                 JOIN ukm u ON k.ukm_id = u.id 
                                 WHERE k.id = :kegiatan_id AND u.admin_id = :admin_id");
    $stmt_check->execute([':kegiatan_id' => $kegiatan_id, ':admin_id' => $current_admin_id]);
    $kegiatan = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$kegiatan) {
        showAlert('Akses ditolak.', 'danger');
        redirect('kelola_ukm.php?detail=' . ($_GET['detail'] ?? ''));
        exit;
    }
    
    // Buat folder jika belum ada
    if (!file_exists('../uploads/kegiatan')) {
        mkdir('../uploads/kegiatan', 0755, true);
    }
    
    // Upload multiple files
    $uploaded_count = 0;
    if (isset($_FILES['foto_kegiatan']) && !empty($_FILES['foto_kegiatan']['name'][0])) {
        $files = $_FILES['foto_kegiatan'];
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $foto_name = uploadFile($file_tmp, '../uploads/kegiatan/');
                
                if ($foto_name !== false) {
                    $query = "INSERT INTO foto_kegiatan (kegiatan_id, ukm_id, file_foto, caption, urutan, created_at) 
                              VALUES (:kegiatan_id, :ukm_id, :file_foto, :caption, :urutan, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        ':kegiatan_id' => $kegiatan_id,
                        ':ukm_id' => $kegiatan['ukm_id'],
                        ':file_foto' => $foto_name,
                        ':caption' => $caption,
                        ':urutan' => $i
                    ]);
                    $uploaded_count++;
                }
            }
        }
    }
    
    if ($uploaded_count > 0) {
        showAlert($uploaded_count . ' foto berhasil diupload!', 'success');
    } else {
        showAlert('Gagal mengupload foto.', 'danger');
    }
    
    redirect('kelola_ukm.php?detail=' . ($_GET['detail'] ?? ''));
    exit;
}

// HAPUS FOTO KEGIATAN
if (isset($_GET['hapus_foto'])) {
    $foto_id = sanitize($_GET['hapus_foto']);
    
    $stmt_check = $db->prepare("SELECT fk.*, u.admin_id FROM foto_kegiatan fk 
                                 JOIN ukm u ON fk.ukm_id = u.id 
                                 WHERE fk.id = :foto_id AND u.admin_id = :admin_id");
    $stmt_check->execute([':foto_id' => $foto_id, ':admin_id' => $current_admin_id]);
    $foto = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($foto) {
        // Hapus file
        if (file_exists('../uploads/kegiatan/' . $foto['file_foto'])) {
            unlink('../uploads/kegiatan/' . $foto['file_foto']);
        }
        
        // Hapus dari database
        $db->prepare("DELETE FROM foto_kegiatan WHERE id = ?")->execute([$foto_id]);
        showAlert('Foto berhasil dihapus!', 'success');
    } else {
        showAlert('Akses ditolak.', 'danger');
    }
    
    redirect('kelola_ukm.php?detail=' . ($_GET['detail'] ?? ''));
    exit;
}


// =================================================================
// -------------------- PENGAMBILAN DATA LISTING -------------------
// =================================================================

// --- Ambil data UKM (LISTING) ---
$query = "SELECT u.*, k.nama_kategori 
          FROM ukm u 
          LEFT JOIN kategori_ukm k ON u.kategori_id = k.id 
          WHERE u.admin_id = :admin_id 
          ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':admin_id', $current_admin_id);
$stmt->execute();
$ukm_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Ambil data Kategori ---
$kategori_list = getAllKategoriUKM();

// --- Detail UKM (jika ada parameter detail) ---
$ukm_detail = null;
$kegiatan_list = [];
if (isset($_GET['detail'])) {
    $ukm_id = sanitize($_GET['detail']);
    
    $stmt_detail = $db->prepare("SELECT u.*, k.nama_kategori FROM ukm u 
                                  LEFT JOIN kategori_ukm k ON u.kategori_id = k.id 
                                  WHERE u.id = :ukm_id AND u.admin_id = :admin_id");
    $stmt_detail->execute([':ukm_id' => $ukm_id, ':admin_id' => $current_admin_id]);
    $ukm_detail = $stmt_detail->fetch(PDO::FETCH_ASSOC);
    
    if ($ukm_detail) {
        // Ambil kegiatan UKM
        $stmt_kegiatan = $db->prepare("SELECT * FROM kegiatan_ukm WHERE ukm_id = :ukm_id ORDER BY tanggal_kegiatan DESC");
        $stmt_kegiatan->execute([':ukm_id' => $ukm_id]);
        $kegiatan_list = $stmt_kegiatan->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola UKM - Admin UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        // Definisikan fungsi di HEAD agar bisa dipanggil dari onclick
        function fillEditForm(button) {
            document.getElementById('edit_id_ukm').value = button.getAttribute('data-id') || '';
            document.getElementById('edit_nama_ukm_title').textContent = button.getAttribute('data-nama') || '';
            document.getElementById('nama_ukm_edit').value = button.getAttribute('data-nama') || '';
            document.getElementById('kategori_id_edit').value = button.getAttribute('data-kategori') || '';
            document.getElementById('ketua_umum_edit').value = button.getAttribute('data-ketua') || '';
            document.getElementById('email_edit').value = button.getAttribute('data-email') || '';
            document.getElementById('no_telepon_edit').value = button.getAttribute('data-telp') || '';
            document.getElementById('deskripsi_edit').value = button.getAttribute('data-deskripsi') || '';
            document.getElementById('visi_edit').value = button.getAttribute('data-visi') || '';
            document.getElementById('misi_edit').value = button.getAttribute('data-misi') || '';
            document.getElementById('program_kerja_edit').value = button.getAttribute('data-proker') || '';
            document.getElementById('alamat_sekretariat_edit').value = button.getAttribute('data-alamat') || '';
            document.getElementById('syarat_pendaftaran_edit').value = button.getAttribute('data-syarat') || '';
            document.getElementById('status_edit').value = button.getAttribute('data-status') || 'aktif';
            document.getElementById('max_anggota_edit').value = button.getAttribute('data-max') || 0;
            document.getElementById('biaya_pendaftaran_edit').value = button.getAttribute('data-biaya') || 0;
            
            const logo = button.getAttribute('data-logo');
            const currentLogoDisplay = document.getElementById('current_logo_display');
            if (logo) {
                currentLogoDisplay.innerHTML = '<img src="../uploads/' + logo + '" class="logo-img-small me-2" alt="Logo">' + logo;
            } else {
                currentLogoDisplay.textContent = 'Belum ada logo.';
            }
        }

        function toggleFotoKegiatan(kegiatanId) {
            const element = document.getElementById('fotoKegiatan' + kegiatanId);
            if (element.style.display === 'none' || element.style.display === '') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        }

        function setKegiatanId(kegiatanId) {
            document.getElementById('kegiatan_id_foto').value = kegiatanId;
        }
    </script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50, #34495e);
        }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.8); 
            padding: 15px 20px; 
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { 
            background: rgba(255,255,255,0.1); 
            color: white; 
        }
        .main-content { 
            background: #f8f9fa; 
            min-height: 100vh; 
        }
        .logo-img { 
            width: 80px; 
            height: 80px; 
            object-fit: cover; 
            border-radius: 10px; 
        }
        .logo-img-small { 
            width: 30px; 
            height: 30px; 
            object-fit: cover; 
            border-radius: 5px; 
        }
        .card { 
            border: none; 
            box-shadow: 0 3px 15px rgba(0,0,0,0.1); 
        }
        .foto-kegiatan-thumb {
            width: 150px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .foto-kegiatan-container {
            position: relative;
            display: inline-block;
            margin: 5px;
        }
        .foto-kegiatan-container .btn-hapus-foto {
            position: absolute;
            top: 5px;
            right: 5px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <div class="col-md-3 col-lg-2 px-0">
            <div class="sidebar">
                <div class="p-3 text-center border-bottom border-secondary">
                    <h5 class="text-white mb-0"><i class="fas fa-university"></i> Admin UKM</h5>
                    <small class="text-white-50">Politeknik Negeri Lampung</small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                    <a class="nav-link active" href="kelola_ukm.php"><i class="fas fa-users me-2"></i> Kelola UKM</a>
                    <a class="nav-link" href="kelola_kategori.php"><i class="fas fa-tags me-2"></i> Kategori UKM</a>
                    <a class="nav-link" href="kelola_mahasiswa.php"><i class="fas fa-user-graduate me-2"></i> Data Mahasiswa</a>
                    <a class="nav-link" href="kelola_pendaftaran.php"><i class="fas fa-clipboard-list me-2"></i> Pendaftaran</a>
                    <a class="nav-link" href="laporan.php"><i class="fas fa-chart-bar me-2"></i> Laporan</a>
                    <div class="dropdown-divider bg-secondary"></div>
                    <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </nav>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-9 col-lg-10 main-content">
            <div class="p-4">
                <?php if (isset($_GET['detail']) && $ukm_detail): ?>
                    <!-- DETAIL UKM & KEGIATAN -->
                    <div class="mb-4">
                        <a href="kelola_ukm.php" class="btn btn-secondary mb-3">
                            <i class="fas fa-arrow-left"></i> Kembali ke Daftar UKM
                        </a>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Detail UKM: <?= htmlspecialchars($ukm_detail['nama_ukm']) ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <?php if (!empty($ukm_detail['logo'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($ukm_detail['logo']) ?>" class="logo-img mb-2" alt="Logo">
                                        <?php else: ?>
                                            <div class="logo-img bg-secondary d-flex align-items-center justify-content-center mb-2">
                                                <i class="fas fa-image text-white fa-3x"></i>
                                            </div>
                                        <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH UKM -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTambahLabel"><i class="fas fa-plus-circle me-1"></i> Tambah UKM Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="kelola_ukm.php">
                <div class="modal-body">
                    <input type="hidden" name="tambah" value="1">
                    
                    <ul class="nav nav-tabs mb-3" id="tambahTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tambah-utama-tab" data-bs-toggle="tab" data-bs-target="#tambah-utama" type="button">Data Utama</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tambah-detail-tab" data-bs-toggle="tab" data-bs-target="#tambah-detail" type="button">Detail & Kontak</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tambah-info-tab" data-bs-toggle="tab" data-bs-target="#tambah-info" type="button">Info Pendaftaran</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="tambahTabContent">
                        <!-- TAB DATA UTAMA -->
                        <div class="tab-pane fade show active" id="tambah-utama" role="tabpanel">
                            <div class="mb-3">
                                <label for="nama_ukm_tambah" class="form-label">Nama UKM <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama_ukm_tambah" name="nama_ukm" required>
                            </div>
                            <div class="mb-3">
                                <label for="kategori_id_tambah" class="form-label">Kategori <span class="text-danger">*</span></label>
                                <select class="form-select" id="kategori_id_tambah" name="kategori_id" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($kategori_list as $kategori): ?>
                                        <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="ketua_umum_tambah" class="form-label">Ketua Umum <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ketua_umum_tambah" name="ketua_umum" required>
                            </div>
                            <div class="mb-3">
                                <label for="logo_tambah" class="form-label">Logo (JPG/PNG max 5MB)</label>
                                <input type="file" class="form-control" id="logo_tambah" name="logo" accept=".jpg,.jpeg,.png">
                            </div>
                            <div class="mb-3">
                                <label for="deskripsi_tambah" class="form-label">Deskripsi Singkat</label>
                                <textarea class="form-control" id="deskripsi_tambah" name="deskripsi" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- TAB DETAIL & KONTAK -->
                        <div class="tab-pane fade" id="tambah-detail" role="tabpanel">
                            <div class="mb-3">
                                <label for="visi_tambah" class="form-label">Visi</label>
                                <textarea class="form-control" id="visi_tambah" name="visi" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="misi_tambah" class="form-label">Misi</label>
                                <textarea class="form-control" id="misi_tambah" name="misi" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="program_kerja_tambah" class="form-label">Program Kerja</label>
                                <textarea class="form-control" id="program_kerja_tambah" name="program_kerja" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email_tambah" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email_tambah" name="email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="no_telepon_tambah" class="form-label">No Telepon <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="no_telepon_tambah" name="no_telepon" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="alamat_sekretariat_tambah" class="form-label">Alamat Sekretariat</label>
                                <textarea class="form-control" id="alamat_sekretariat_tambah" name="alamat_sekretariat" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- TAB INFO PENDAFTARAN -->
                        <div class="tab-pane fade" id="tambah-info" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status_tambah" class="form-label">Status UKM</label>
                                    <select class="form-select" id="status_tambah" name="status">
                                        <option value="aktif">Aktif</option>
                                        <option value="nonaktif">Nonaktif</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="max_anggota_tambah" class="form-label">Maksimal Anggota</label>
                                    <input type="number" class="form-control" id="max_anggota_tambah" name="max_anggota" value="0">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="biaya_pendaftaran_tambah" class="form-label">Biaya Pendaftaran (Rp)</label>
                                <input type="number" class="form-control" id="biaya_pendaftaran_tambah" name="biaya_pendaftaran" step="0.01" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="syarat_pendaftaran_tambah" class="form-label">Syarat Pendaftaran</label>
                                <textarea class="form-control" id="syarat_pendaftaran_tambah" name="syarat_pendaftaran" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan UKM</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT UKM -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalEditLabel"><i class="fas fa-edit me-1"></i> Edit UKM: <span id="edit_nama_ukm_title"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="kelola_ukm.php">
                <div class="modal-body">
                    <input type="hidden" name="edit" value="1">
                    <input type="hidden" name="id_ukm" id="edit_id_ukm">
                    
                    <ul class="nav nav-tabs mb-3" id="editTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="edit-utama-tab" data-bs-toggle="tab" data-bs-target="#edit-utama" type="button">Data Utama</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="edit-detail-tab" data-bs-toggle="tab" data-bs-target="#edit-detail" type="button">Detail & Kontak</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="edit-info-tab" data-bs-toggle="tab" data-bs-target="#edit-info" type="button">Info Pendaftaran</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="editTabContent">
                        <!-- TAB DATA UTAMA -->
                        <div class="tab-pane fade show active" id="edit-utama" role="tabpanel">
                            <div class="mb-3">
                                <label for="nama_ukm_edit" class="form-label">Nama UKM <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama_ukm_edit" name="nama_ukm" required>
                            </div>
                            <div class="mb-3">
                                <label for="kategori_id_edit" class="form-label">Kategori <span class="text-danger">*</span></label>
                                <select class="form-select" id="kategori_id_edit" name="kategori_id" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($kategori_list as $kategori): ?>
                                        <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="ketua_umum_edit" class="form-label">Ketua Umum <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ketua_umum_edit" name="ketua_umum" required>
                            </div>
                            <div class="mb-3">
                                <label for="logo_edit" class="form-label">Logo Baru (Kosongkan jika tidak diubah)</label>
                                <input type="file" class="form-control" id="logo_edit" name="logo" accept=".jpg,.jpeg,.png">
                                <small class="text-muted mt-2 d-block">Logo saat ini: <span id="current_logo_display"></span></small>
                            </div>
                            <div class="mb-3">
                                <label for="deskripsi_edit" class="form-label">Deskripsi Singkat</label>
                                <textarea class="form-control" id="deskripsi_edit" name="deskripsi" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- TAB DETAIL & KONTAK -->
                        <div class="tab-pane fade" id="edit-detail" role="tabpanel">
                            <div class="mb-3">
                                <label for="visi_edit" class="form-label">Visi</label>
                                <textarea class="form-control" id="visi_edit" name="visi" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="misi_edit" class="form-label">Misi</label>
                                <textarea class="form-control" id="misi_edit" name="misi" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="program_kerja_edit" class="form-label">Program Kerja</label>
                                <textarea class="form-control" id="program_kerja_edit" name="program_kerja" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email_edit" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email_edit" name="email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="no_telepon_edit" class="form-label">No Telepon <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="no_telepon_edit" name="no_telepon" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="alamat_sekretariat_edit" class="form-label">Alamat Sekretariat</label>
                                <textarea class="form-control" id="alamat_sekretariat_edit" name="alamat_sekretariat" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- TAB INFO PENDAFTARAN -->
                        <div class="tab-pane fade" id="edit-info" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status_edit" class="form-label">Status UKM</label>
                                    <select class="form-select" id="status_edit" name="status">
                                        <option value="aktif">Aktif</option>
                                        <option value="nonaktif">Nonaktif</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="max_anggota_edit" class="form-label">Maksimal Anggota</label>
                                    <input type="number" class="form-control" id="max_anggota_edit" name="max_anggota" value="0">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="biaya_pendaftaran_edit" class="form-label">Biaya Pendaftaran (Rp)</label>
                                <input type="number" class="form-control" id="biaya_pendaftaran_edit" name="biaya_pendaftaran" step="0.01" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="syarat_pendaftaran_edit" class="form-label">Syarat Pendaftaran</label>
                                <textarea class="form-control" id="syarat_pendaftaran_edit" name="syarat_pendaftaran" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-warning">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH KEGIATAN -->
<div class="modal fade" id="modalTambahKegiatan" tabindex="-1" aria-labelledby="modalTambahKegiatanLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTambahKegiatanLabel"><i class="fas fa-calendar-plus"></i> Tambah Kegiatan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="kelola_kegiatan.php">
                <div class="modal-body">
                    <input type="hidden" name="tambah_kegiatan" value="1">
                    <input type="hidden" name="ukm_id" value="<?= $ukm_detail['id'] ?? '' ?>">
                    
                    <div class="mb-3">
                        <label for="nama_kegiatan" class="form-label">Nama Kegiatan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_kegiatan" name="nama_kegiatan" required>
                    </div>
                    <div class="mb-3">
                        <label for="tanggal_kegiatan" class="form-label">Tanggal Kegiatan <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tanggal_kegiatan" name="tanggal_kegiatan" required>
                    </div>
                    <div class="mb-3">
                        <label for="tempat" class="form-label">Tempat</label>
                        <input type="text" class="form-control" id="tempat" name="tempat">
                    </div>
                    <div class="mb-3">
                        <label for="deskripsi_kegiatan" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi_kegiatan" name="deskripsi" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="status_kegiatan" class="form-label">Status</label>
                        <select class="form-select" id="status_kegiatan" name="status">
                            <option value="akan_datang">Akan Datang</option>
                            <option value="berlangsung">Berlangsung</option>
                            <option value="selesai">Selesai</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL UPLOAD FOTO KEGIATAN -->
<div class="modal fade" id="modalUploadFoto" tabindex="-1" aria-labelledby="modalUploadFotoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalUploadFotoLabel"><i class="fas fa-upload"></i> Upload Foto Kegiatan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" action="kelola_ukm.php">
                <div class="modal-body">
                    <input type="hidden" name="upload_foto_kegiatan" value="1">
                    <input type="hidden" name="kegiatan_id" id="kegiatan_id_foto">
                    
                    <div class="mb-3">
                        <label for="foto_kegiatan" class="form-label">Pilih Foto (Bisa multiple) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="foto_kegiatan" name="foto_kegiatan[]" accept=".jpg,.jpeg,.png" multiple required>
                        <small class="text-muted">Format: JPG/PNG, Max 5MB per file</small>
                    </div>
                    <div class="mb-3">
                        <label for="caption_foto" class="form-label">Caption (Opsional)</label>
                        <textarea class="form-control" id="caption_foto" name="caption" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-success">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
                                        <h6><?= htmlspecialchars($ukm_detail['nama_ukm']) ?></h6>
                                        <span class="badge bg-info"><?= htmlspecialchars($ukm_detail['nama_kategori']) ?></span>
                                    </div>
                                    <div class="col-md-9">
                                        <table class="table table-sm">
                                            <tr><th width="200">Ketua Umum</th><td><?= htmlspecialchars($ukm_detail['ketua_umum']) ?></td></tr>
                                            <tr><th>Email</th><td><?= htmlspecialchars($ukm_detail['email']) ?></td></tr>
                                            <tr><th>No Telepon</th><td><?= htmlspecialchars($ukm_detail['no_telepon']) ?></td></tr>
                                            <tr><th>Status</th><td><span class="badge bg-<?= $ukm_detail['status'] == 'aktif' ? 'success' : 'danger' ?>"><?= ucfirst($ukm_detail['status']) ?></span></td></tr>
                                            <tr><th>Deskripsi</th><td><?= nl2br(htmlspecialchars($ukm_detail['deskripsi'])) ?></td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DAFTAR KEGIATAN -->
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Kegiatan UKM</h5>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahKegiatan">
                                    <i class="fas fa-plus"></i> Tambah Kegiatan
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($kegiatan_list)): ?>
                                    <p class="text-muted text-center py-4">Belum ada kegiatan yang ditambahkan.</p>
                                <?php else: ?>
                                    <?php foreach ($kegiatan_list as $kegiatan): ?>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <h6><?= htmlspecialchars($kegiatan['nama_kegiatan']) ?></h6>
                                                        <p class="text-muted mb-2"><?= htmlspecialchars($kegiatan['deskripsi']) ?></p>
                                                        <small>
                                                            <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($kegiatan['tanggal_kegiatan'])) ?> |
                                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($kegiatan['tempat']) ?> |
                                                            <span class="badge bg-<?= $kegiatan['status'] == 'selesai' ? 'success' : ($kegiatan['status'] == 'berlangsung' ? 'warning' : 'info') ?>">
                                                                <?= ucfirst(str_replace('_', ' ', $kegiatan['status'])) ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <button class="btn btn-sm btn-info" onclick="toggleFotoKegiatan(<?= $kegiatan['id'] ?>)">
                                                            <i class="fas fa-images"></i> Foto
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <!-- FOTO KEGIATAN -->
                                                <div id="fotoKegiatan<?= $kegiatan['id'] ?>" class="mt-3" style="display:none;">
                                                    <hr>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <strong>Foto Kegiatan:</strong>
                                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalUploadFoto" 
                                                                onclick="setKegiatanId(<?= $kegiatan['id'] ?>)">
                                                            <i class="fas fa-upload"></i> Upload Foto
                                                        </button>
                                                    </div>
                                                    
                                                    <?php
                                                    $stmt_foto = $db->prepare("SELECT * FROM foto_kegiatan WHERE kegiatan_id = ? ORDER BY urutan ASC");
                                                    $stmt_foto->execute([$kegiatan['id']]);
                                                    $foto_list = $stmt_foto->fetchAll(PDO::FETCH_ASSOC);
                                                    ?>
                                                    
                                                    <?php if (empty($foto_list)): ?>
                                                        <p class="text-muted">Belum ada foto untuk kegiatan ini.</p>
                                                    <?php else: ?>
                                                        <div class="row">
                                                            <?php foreach ($foto_list as $foto): ?>
                                                                <div class="col-md-3 mb-3">
                                                                    <div class="foto-kegiatan-container">
                                                                        <img src="../uploads/kegiatan/<?= htmlspecialchars($foto['file_foto']) ?>" 
                                                                             class="foto-kegiatan-thumb" alt="Foto Kegiatan">
                                                                        <a href="?hapus_foto=<?= $foto['id'] ?>&detail=<?= $ukm_detail['id'] ?>" 
                                                                           class="btn btn-danger btn-sm btn-hapus-foto"
                                                                           onclick="return confirm('Yakin ingin menghapus foto ini?')">
                                                                            <i class="fas fa-trash"></i>
                                                                        </a>
                                                                    </div>
                                                                    <?php if (!empty($foto['caption'])): ?>
                                                                        <small class="text-muted d-block mt-1"><?= htmlspecialchars($foto['caption']) ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- DAFTAR UKM -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2><i class="fas fa-users text-primary"></i> Kelola Data UKM</h2>
                            <p class="text-muted mb-0">Manajemen Unit Kegiatan Mahasiswa yang Anda kelola</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus-circle"></i> Tambah UKM Baru
                        </button>
                    </div>

                    <?php displayAlert(); ?>

                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Daftar UKM Anda</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Logo</th>
                                            <th>Nama UKM</th>
                                            <th>Kategori</th>
                                            <th>Ketua Umum</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($ukm_list)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5 text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                                    Belum ada data UKM yang Anda kelola.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1; foreach ($ukm_list as $ukm): ?>
                                                <tr>
                                                    <td><?= $no++ ?></td>
                                                    <td>
                                                        <?php if (!empty($ukm['logo'])): ?>
                                                            <img src="../uploads/<?= htmlspecialchars($ukm['logo']) ?>" class="logo-img-small" alt="Logo UKM">
                                                        <?php else: ?>
                                                            <div class="logo-img-small bg-secondary d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-image text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong><?= htmlspecialchars($ukm['nama_ukm'] ?? '-') ?></strong></td>
                                                    <td><span class="badge bg-info"><?= htmlspecialchars($ukm['nama_kategori'] ?? 'Tidak Ada') ?></span></td>
                                                    <td><?= htmlspecialchars($ukm['ketua_umum'] ?? '-') ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= ($ukm['status'] == 'aktif' ? 'success' : 'danger') ?>">
                                                            <?= htmlspecialchars(ucfirst($ukm['status'] ?? '')) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="?detail=<?= $ukm['id'] ?>" class="btn btn-sm btn-info mb-1">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-warning mb-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="modalEdit"
                                                            data-id="<?= $ukm['id'] ?>"
                                                            data-nama="<?= htmlspecialchars($ukm['nama_ukm']) ?>"
                                                            data-kategori="<?= $ukm['kategori_id'] ?>"
                                                            data-ketua="<?= htmlspecialchars($ukm['ketua_umum']) ?>"
                                                            data-email="<?= htmlspecialchars($ukm['email']) ?>"
                                                            data-telp="<?= htmlspecialchars($ukm['no_telepon']) ?>"
                                                            data-deskripsi="<?= htmlspecialchars($ukm['deskripsi']) ?>"
                                                            data-visi="<?= htmlspecialchars($ukm['visi']) ?>"
                                                            data-misi="<?= htmlspecialchars($ukm['misi']) ?>"
                                                            data-proker="<?= htmlspecialchars($ukm['program_kerja']) ?>"
                                                            data-alamat="<?= htmlspecialchars($ukm['alamat_sekretariat']) ?>"
                                                            data-syarat="<?= htmlspecialchars($ukm['syarat_pendaftaran']) ?>"
                                                            data-status="<?= $ukm['status'] ?>"
                                                            data-max="<?= $ukm['max_anggota'] ?>"
                                                            data-biaya="<?= $ukm['biaya_pendaftaran'] ?>"
                                                            data-logo="<?= htmlspecialchars($ukm['logo']) ?>"
                                                            onclick="fillEditForm(this)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <a href="?hapus=<?= $ukm['id'] ?>" class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Yakin ingin menghapus UKM <?= htmlspecialchars($ukm['nama_ukm']) ?>? Tindakan ini akan menghapus semua data terkait dan tidak bisa dibatalkan.')">
                                                            <i class="fas fa-trash"></i> Hapus
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>