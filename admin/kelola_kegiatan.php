<?php
session_start(); // ← WAJIB DI AWAL
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Pastikan ini admin UKM (bukan superadmin)
if ($_SESSION['user_role'] === 'superadmin' || !isset($_SESSION['ukm_id']) || $_SESSION['ukm_id'] <= 0) {
    showAlert('Akses ditolak: Anda bukan admin UKM.', 'danger');
    redirect('dashboard.php');
}

$ukm_id = (int)$_SESSION['ukm_id'];
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("<div class='alert alert-danger'>Koneksi database gagal.</div>");
}

// ✅ AMBIL NAMA UKM UNTUK NAVBAR (INI YANG KURANG SEBELUMNYA)
$stmt_ukm = $db->prepare("SELECT nama_ukm FROM ukm WHERE id = ?");
$stmt_ukm->execute([$ukm_id]);
$nama_ukm = $stmt_ukm->fetchColumn() ?? "UKM Anda";

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
    }
}

// =================================================================
// PROSES UPDATE STATUS KEGIATAN
// =================================================================
if (isset($_GET['action']) && $_GET['action'] === 'update_status') {
    $kegiatan_id = (int)$_GET['id'];
    $status = $_GET['status'];

    if (in_array($status, ['draft', 'published', 'completed'])) {
        $stmt_check = $db->prepare("SELECT id FROM kegiatan_ukm WHERE id = ? AND ukm_id = ?");
        $stmt_check->execute([$kegiatan_id, $ukm_id]);
        if ($stmt_check->fetch()) {
            $db->prepare("UPDATE kegiatan_ukm SET status = ? WHERE id = ?")->execute([$status, $kegiatan_id]);
            showAlert('Status kegiatan diperbarui!', 'success');
        } else {
            showAlert('Akses ditolak.', 'danger');
        }
    }
    redirect('kelola_kegiatan.php');
}

// =================================================================
// PROSES HAPUS KEGIATAN
// =================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $kegiatan_id = (int)$_GET['id'];
    $stmt_check = $db->prepare("SELECT id FROM kegiatan_ukm WHERE id = ? AND ukm_id = ?");
    $stmt_check->execute([$kegiatan_id, $ukm_id]);
    if ($stmt_check->fetch()) {
        $stmt_foto = $db->prepare("SELECT foto FROM foto_kegiatan WHERE kegiatan_id = ?");
        $stmt_foto->execute([$kegiatan_id]);
        $foto_list = $stmt_foto->fetchAll(PDO::FETCH_COLUMN);
        foreach ($foto_list as $foto) {
            if (file_exists('../uploads/kegiatan/' . $foto)) {
                unlink('../uploads/kegiatan/' . $foto);
            }
        }
        $db->prepare("DELETE FROM foto_kegiatan WHERE kegiatan_id = ?")->execute([$kegiatan_id]);
        $db->prepare("DELETE FROM kegiatan_ukm WHERE id = ?")->execute([$kegiatan_id]);
        showAlert('Kegiatan berhasil dihapus!', 'success');
    } else {
        showAlert('Akses ditolak.', 'danger');
    }
    redirect('kelola_kegiatan.php');
}

// =================================================================
// PROSES UPLOAD FOTO KEGIATAN
// =================================================================
if ($_POST && isset($_POST['upload_foto_kegiatan'])) {
    $kegiatan_id = (int)$_POST['kegiatan_id'];
    $caption = sanitize($_POST['caption'] ?? '');

    $stmt_check = $db->prepare("SELECT id FROM kegiatan_ukm WHERE id = ? AND ukm_id = ?");
    $stmt_check->execute([$kegiatan_id, $ukm_id]);
    if (!$stmt_check->fetch()) {
        showAlert('Akses ditolak.', 'danger');
        redirect('kelola_kegiatan.php');
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
    $stmt = $db->prepare("
        SELECT fk.foto 
        FROM foto_kegiatan fk
        JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
        WHERE fk.id = ? AND k.ukm_id = ?
    ");
    $stmt->execute([$foto_id, $ukm_id]);
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
    redirect('kelola_kegiatan.php');
}

// =================================================================
// AMBIL DATA KEGIATAN
// =================================================================
$stmt_keg = $db->prepare("SELECT * FROM kegiatan_ukm WHERE ukm_id = ? ORDER BY created_at DESC");
$stmt_keg->execute([$ukm_id]);
$kegiatan_list = $stmt_keg->fetchAll(PDO::FETCH_ASSOC);

// ✅ SET JUDUL & NAMA UKM UNTUK NAVBAR
$page_title = "Kelola Kegiatan - " . htmlspecialchars($nama_ukm);
?>

<?php include 'layout.php'; ?>

<style>
    .foto-thumb {
        width: 120px;
        height: 90px;
        object-fit: cover;
        border-radius: 6px;
    }
    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8em;
    }
</style>

<!-- KONTEN UTAMA -->
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-alt me-2"></i>Kelola Kegiatan UKM</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahKegiatan">
            <i class="fas fa-plus"></i> Tambah Kegiatan
        </button>
    </div>

    <?php displayAlert(); ?>

    <?php if (empty($kegiatan_list)): ?>
        <div class="text-center py-5">
            <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
            <h5>Belum ada kegiatan</h5>
            <p class="text-muted">Buat kegiatan pertama Anda.</p>
        </div>
    <?php else: ?>
        <?php foreach ($kegiatan_list as $keg): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars($keg['nama_kegiatan']) ?></h5>
                            <p class="text-muted mb-2"><?= htmlspecialchars($keg['deskripsi_kegiatan']) ?></p>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"></button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="?action=update_status&id=<?= $keg['id'] ?>&status=draft">
                                        Set ke Draft
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?action=update_status&id=<?= $keg['id'] ?>&status=published">
                                        Publikasikan
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?action=update_status&id=<?= $keg['id'] ?>&status=completed">
                                        Tandai Selesai
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="?action=delete&id=<?= $keg['id'] ?>" 
                                       onclick="return confirm('Yakin hapus kegiatan ini? Semua foto akan dihapus.')">
                                        Hapus Kegiatan
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> 
                                <?= formatTanggal($keg['tanggal_mulai']) ?>
                                <?php if (!empty($keg['tanggal_selesai']) && $keg['tanggal_selesai'] !== $keg['tanggal_mulai']): ?>
                                    – <?= formatTanggal($keg['tanggal_selesai']) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="status-badge bg-<?= 
                                $keg['status'] === 'completed' ? 'success' : 
                                ($keg['status'] === 'published' ? 'info' : 'secondary') 
                            ?> text-white">
                                <?= ucfirst($keg['status']) ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($keg['lokasi'])): ?>
                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($keg['lokasi']) ?></small>
                    <?php endif; ?>
                    <?php if ($keg['biaya'] > 0): ?>
                        <br><small class="text-muted"><i class="fas fa-money-bill"></i> Rp <?= number_format($keg['biaya'], 0, ',', '.') ?></small>
                    <?php endif; ?>

                    <!-- FOTO KEGIATAN -->
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Foto Kegiatan</strong>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalUploadFoto"
                                    onclick="document.getElementById('kegiatan_id_foto').value = <?= (int)$keg['id'] ?>">
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
                                            <small class="text-muted d-block"><?= htmlspecialchars($foto['keterangan']) ?></small>
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

<?php include 'footer.php'; ?>