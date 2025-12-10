<?php
// admin/kelola_ukm.php — DIPERBARUI: TAMBAH TAHUN BERDIRI (DATE)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Pastikan ini admin UKM
if (!isset($_SESSION['ukm_id']) || $_SESSION['ukm_id'] <= 0) {
    showAlert('Anda bukan admin UKM.', 'danger');
    redirect('dashboard.php');
}

$ukm_id = (int)$_SESSION['ukm_id'];
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("<div class='alert alert-danger'>Koneksi database gagal.</div>");
}

// Ambil data UKM
$stmt = $db->prepare("SELECT u.*, k.nama_kategori FROM ukm u LEFT JOIN kategori_ukm k ON u.kategori_id = k.id WHERE u.id = :ukm_id");
$stmt->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
$stmt->execute();
$ukm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ukm) {
    die("<div class='alert alert-danger'>UKM Anda tidak ditemukan.</div>");
}

// Ambil kategori
$kategori_list = getAllKategoriUKM();

// Ambil kegiatan
$stmt_keg = $db->prepare("SELECT * FROM kegiatan_ukm WHERE ukm_id = :ukm_id ORDER BY tanggal_mulai DESC");
$stmt_keg->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
$stmt_keg->execute();
$kegiatan_list = $stmt_keg->fetchAll(PDO::FETCH_ASSOC);

// =================================================================
// PROSES SIMPAN PROFIL UKM
// =================================================================
if ($_POST && isset($_POST['simpan_profil'])) {
    $nama_ukm = sanitize($_POST['nama_ukm']);
    $kategori_id = (int)$_POST['kategori_id'];
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $tahun_berdiri = !empty($_POST['tahun_berdiri']) ? $_POST['tahun_berdiri'] : null; // ✅ BARU
    $ketua_umum = sanitize($_POST['ketua_umum']);
    $email = sanitize($_POST['email'] ?? '');
    $no_telepon = sanitize($_POST['no_telepon']);
    $alamat_sekretariat = sanitize($_POST['alamat_sekretariat'] ?? '');
    $visi = sanitize($_POST['visi'] ?? '');
    $misi = sanitize($_POST['misi'] ?? '');
    $program_kerja = sanitize($_POST['program_kerja'] ?? '');
    $syarat_pendaftaran = sanitize($_POST['syarat_pendaftaran'] ?? '');
    $status = sanitize($_POST['status']);
    $max_anggota = (int)$_POST['max_anggota'];
    $biaya_pendaftaran = (float)$_POST['biaya_pendaftaran'];
    $kontak = sanitize($_POST['kontak'] ?? '');

    $logo = $ukm['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_baru = uploadFile($_FILES['logo'], '../uploads/');
        if ($logo_baru !== false) {
            if (!empty($ukm['logo']) && file_exists('../uploads/' . $ukm['logo'])) {
                unlink('../uploads/' . $ukm['logo']);
            }
            $logo = $logo_baru;
        }
    }

    $stmt_update = $db->prepare("
        UPDATE ukm SET 
            nama_ukm = :nama_ukm,
            kategori_id = :kategori_id,
            deskripsi = :deskripsi,
            tahun_berdiri = :tahun_berdiri,  -- ✅ TAHUN BERDIRI
            ketua_umum = :ketua_umum,
            email = :email,
            no_telepon = :no_telepon,
            alamat_sekretariat = :alamat_sekretariat,
            visi = :visi,
            misi = :misi,
            program_kerja = :program_kerja,
            syarat_pendaftaran = :syarat_pendaftaran,
            status = :status,
            max_anggota = :max_anggota,
            biaya_pendaftaran = :biaya_pendaftaran,
            kontak = :kontak,
            logo = :logo,
            updated_at = NOW()
        WHERE id = :ukm_id
    ");
    $stmt_update->execute([
        ':nama_ukm' => $nama_ukm,
        ':kategori_id' => $kategori_id,
        ':deskripsi' => $deskripsi,
        ':tahun_berdiri' => $tahun_berdiri,  // ✅
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
        ':kontak' => $kontak,
        ':logo' => $logo,
        ':ukm_id' => $ukm_id
    ]);

    showAlert('Profil UKM berhasil diperbarui!', 'success');
    $stmt->execute();
    $ukm = $stmt->fetch(PDO::FETCH_ASSOC);
}

// =================================================================
// PROSES TAMBAH KEGIATAN
// =================================================================
if ($_POST && isset($_POST['tambah_kegiatan'])) {
    $nama_kegiatan = sanitize($_POST['nama_kegiatan']);
    $deskripsi_kegiatan = sanitize($_POST['deskripsi_kegiatan'] ?? '');
    $tanggal_mulai = sanitize($_POST['tanggal_mulai']);
    $tanggal_selesai = sanitize($_POST['tanggal_selesai'] ?? '');
    $lokasi = sanitize($_POST['lokasi'] ?? '');
    $biaya = (float)($_POST['biaya'] ?? 0.00);
    $status = sanitize($_POST['status'] ?? 'draft');

    if (empty($nama_kegiatan) || empty($tanggal_mulai)) {
        showAlert('Nama kegiatan dan tanggal mulai wajib diisi.', 'warning');
    } else {
        $stmt_ins = $db->prepare("
            INSERT INTO kegiatan_ukm (
                ukm_id, nama_kegiatan, deskripsi_kegiatan, tanggal_mulai, tanggal_selesai, lokasi, biaya, status, created_at
            ) VALUES (
                :ukm_id, :nama_kegiatan, :deskripsi_kegiatan, :tanggal_mulai, :tanggal_selesai, :lokasi, :biaya, :status, NOW()
            )
        ");
        $stmt_ins->execute([
            ':ukm_id' => $ukm_id,
            ':nama_kegiatan' => $nama_kegiatan,
            ':deskripsi_kegiatan' => $deskripsi_kegiatan,
            ':tanggal_mulai' => $tanggal_mulai,
            ':tanggal_selesai' => $tanggal_selesai,
            ':lokasi' => $lokasi,
            ':biaya' => $biaya,
            ':status' => $status
        ]);
        showAlert('Kegiatan berhasil ditambahkan!', 'success');
        $stmt_keg->execute();
        $kegiatan_list = $stmt_keg->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =================================================================
// PROSES UPLOAD FOTO KEGIATAN
// =================================================================
if ($_POST && isset($_POST['upload_foto_kegiatan'])) {
    $kegiatan_id = (int)$_POST['kegiatan_id'];
    $caption = sanitize($_POST['caption'] ?? '');

    $stmt_check = $db->prepare("SELECT id FROM kegiatan_ukm WHERE id = :id AND ukm_id = :ukm_id");
    $stmt_check->execute([':id' => $kegiatan_id, ':ukm_id' => $ukm_id]);
    if (!$stmt_check->fetch()) {
        showAlert('Akses ditolak.', 'danger');
        redirect('kelola_ukm.php');
    }

    if (!file_exists('../uploads/kegiatan')) {
        mkdir('../uploads/kegiatan', 0755, true);
    }

    $uploaded_count = 0;
    if (isset($_FILES['foto_kegiatan']) && !empty($_FILES['foto_kegiatan']['name'][0])) {
        $files = $_FILES['foto_kegiatan'];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                $foto = uploadFile($file, '../uploads/kegiatan/');
                if ($foto !== false) {
                    $db->prepare("
                        INSERT INTO foto_kegiatan (kegiatan_id, foto, keterangan, created_at)
                        VALUES (?, ?, ?, NOW())
                    ")->execute([$kegiatan_id, $foto, $caption]);
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
}

// =================================================================
// PROSES HAPUS FOTO
// =================================================================
if (isset($_GET['hapus_foto'])) {
    $foto_id = (int)$_GET['hapus_foto'];
    $stmt = $db->prepare("SELECT foto FROM foto_kegiatan WHERE id = :id");
    $stmt->execute([':id' => $foto_id]);
    $foto = $stmt->fetch();
    if ($foto) {
        if (file_exists('../uploads/kegiatan/' . $foto['foto'])) {
            unlink('../uploads/kegiatan/' . $foto['foto']);
        }
        $db->prepare("DELETE FROM foto_kegiatan WHERE id = ?")->execute([$foto_id]);
        showAlert('Foto berhasil dihapus!', 'success');
    } else {
        showAlert('Akses ditolak.', 'danger');
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola UKM - <?= htmlspecialchars($ukm['nama_ukm']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        .card-header {
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem;
        }
        .foto-thumb {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
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
                        <a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i> Lihat Website</a>
                        <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <h2 class="mb-4">Kelola UKM: <strong><?= htmlspecialchars($ukm['nama_ukm']) ?></strong></h2>

                    <?php displayAlert(); ?>

                    <!-- PROFIL UKM -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-building me-2"></i> Profil UKM</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="simpan_profil" value="1">
                                <div class="row">
                                    <div class="col-md-4 text-center mb-3">
                                        <?php if (!empty($ukm['logo'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($ukm['logo']) ?>" class="logo-img mb-2" alt="Logo">
                                        <?php else: ?>
                                            <div class="logo-img bg-secondary d-flex align-items-center justify-content-center">
                                                <i class="fas fa-image text-white fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                        <label class="form-label">Ganti Logo</label>
                                        <input type="file" class="form-control" name="logo" accept=".jpg,.jpeg,.png">
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Nama UKM <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nama_ukm" value="<?= htmlspecialchars($ukm['nama_ukm']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                            <select class="form-select" name="kategori_id" required>
                                                <option value="">Pilih</option>
                                                <?php foreach ($kategori_list as $k): ?>
                                                    <option value="<?= $k['id'] ?>" <?= $k['id'] == $ukm['kategori_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($k['nama_kategori']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <!-- ✅ INPUT TANGGAL BERDIRI -->
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal Berdiri</label>
                                            <input type="date" class="form-control" name="tahun_berdiri" 
                                                   value="<?= !empty($ukm['tahun_berdiri']) ? htmlspecialchars($ukm['tahun_berdiri']) : '' ?>"
                                                   max="<?= date('Y-m-d') ?>">
                                            <div class="form-text">Contoh: 17 Agustus 2010</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Ketua Umum <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="ketua_umum" value="<?= htmlspecialchars($ukm['ketua_umum']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($ukm['email']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">No Telepon <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="no_telepon" value="<?= htmlspecialchars($ukm['no_telepon']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Alamat Sekretariat</label>
                                            <textarea class="form-control" name="alamat_sekretariat" rows="2"><?= htmlspecialchars($ukm['alamat_sekretariat']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Deskripsi</label>
                                            <textarea class="form-control" name="deskripsi" rows="3"><?= htmlspecialchars($ukm['deskripsi']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Visi</label>
                                            <textarea class="form-control" name="visi" rows="2"><?= htmlspecialchars($ukm['visi']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Misi</label>
                                            <textarea class="form-control" name="misi" rows="3"><?= htmlspecialchars($ukm['misi']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Program Kerja</label>
                                            <textarea class="form-control" name="program_kerja" rows="3"><?= htmlspecialchars($ukm['program_kerja']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Syarat Pendaftaran</label>
                                            <textarea class="form-control" name="syarat_pendaftaran" rows="3"><?= htmlspecialchars($ukm['syarat_pendaftaran']) ?></textarea>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="aktif" <?= $ukm['status'] == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                                    <option value="nonaktif" <?= $ukm['status'] == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Maksimal Anggota</label>
                                                <input type="number" class="form-control" name="max_anggota" value="<?= (int)$ukm['max_anggota'] ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Biaya Pendaftaran (Rp)</label>
                                            <input type="number" step="0.01" class="form-control" name="biaya_pendaftaran" value="<?= (float)$ukm['biaya_pendaftaran'] ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kontak</label>
                                            <input type="text" class="form-control" name="kontak" value="<?= htmlspecialchars($ukm['kontak']) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">Simpan Profil</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- KEGIATAN UKM -->
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Kegiatan UKM</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahKegiatan">
                                <i class="fas fa-plus"></i> Tambah Kegiatan
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($kegiatan_list)): ?>
                                <p class="text-muted text-center py-3">Belum ada kegiatan.</p>
                            <?php else: ?>
                                <?php foreach ($kegiatan_list as $keg): ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h6><?= htmlspecialchars($keg['nama_kegiatan']) ?></h6>
                                            <p><?= htmlspecialchars($keg['deskripsi_kegiatan']) ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($keg['tanggal_mulai'])) ?> |
                                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($keg['lokasi']) ?> |
                                                <span class="badge bg-<?= $keg['status'] == 'completed' ? 'success' : ($keg['status'] == 'published' ? 'info' : 'secondary') ?>"><?= ucfirst($keg['status']) ?></span>
                                            </small>

                                            <!-- FOTO KEGIATAN -->
                                            <div class="mt-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong>Foto Kegiatan:</strong>
                                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalUploadFoto"
                                                            onclick="document.getElementById('kegiatan_id_foto').value = <?= $keg['id'] ?>">
                                                        <i class="fas fa-upload"></i> Upload Foto
                                                    </button>
                                                </div>
                                                <?php
                                                $stmt_foto = $db->prepare("SELECT * FROM foto_kegiatan WHERE kegiatan_id = ? ORDER BY id ASC");
                                                $stmt_foto->execute([$keg['id']]);
                                                $foto_list = $stmt_foto->fetchAll(PDO::FETCH_ASSOC);
                                                ?>
                                                <?php if (empty($foto_list)): ?>
                                                    <p class="text-muted">Belum ada foto.</p>
                                                <?php else: ?>
                                                    <div class="row">
                                                        <?php foreach ($foto_list as $foto): ?>
                                                            <div class="col-md-3 mb-2">
                                                                <img src="../uploads/kegiatan/<?= htmlspecialchars($foto['foto']) ?>" class="foto-thumb" alt="Foto">
                                                                <a href="?hapus_foto=<?= $foto['id'] ?>" class="btn btn-danger btn-sm mt-1"
                                                                   onclick="return confirm('Hapus foto ini?')">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                                <?php if (!empty($foto['keterangan'])): ?>
                                                                    <small class="text-muted"><?= htmlspecialchars($foto['keterangan']) ?></small>
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
            </div>
        </div>
    </div>

    <!-- MODAL: TAMBAH KEGIATAN -->
    <div class="modal fade" id="modalTambahKegiatan" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus"></i> Tambah Kegiatan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="tambah_kegiatan" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_kegiatan" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Tanggal Mulai <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tanggal_mulai" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Tanggal Selesai</label>
                                <input type="date" class="form-control" name="tanggal_selesai">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Lokasi</label>
                            <input type="text" class="form-control" name="lokasi">
                        </div>
                        <div class="mb-3">
                            <label>Deskripsi Kegiatan</label>
                            <textarea class="form-control" name="deskripsi_kegiatan" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Biaya (Rp)</label>
                            <input type="number" step="0.01" class="form-control" name="biaya" value="0">
                        </div>
                        <div class="mb-3">
                            <label>Status</label>
                            <select class="form-select" name="status">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: UPLOAD FOTO -->
    <div class="modal fade" id="modalUploadFoto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-upload"></i> Upload Foto Kegiatan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_foto_kegiatan" value="1">
                    <input type="hidden" name="kegiatan_id" id="kegiatan_id_foto">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Foto (multiple) <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="foto_kegiatan[]" accept=".jpg,.jpeg,.png" multiple required>
                        </div>
                        <div class="mb-3">
                            <label>Caption</label>
                            <textarea class="form-control" name="caption" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>