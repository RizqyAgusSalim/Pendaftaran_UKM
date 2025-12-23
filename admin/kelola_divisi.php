<?php
// admin/kelola_divisi.php — VERSI DENGAN LAYOUT TERPADU
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan sudah login dan admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Pastikan pengguna adalah admin UKM (bukan superadmin)
if (!isset($_SESSION['ukm_id']) || $_SESSION['ukm_id'] <= 0) {
    showAlert('Anda tidak memiliki akses ke halaman ini.', 'danger');
    redirect('dashboard.php');
}

$ukm_id = (int)$_SESSION['ukm_id'];
$database = new Database();
$db = $database->getConnection();

// Ambil nama UKM untuk judul halaman & navbar
$stmt_ukm = $db->prepare("SELECT nama_ukm FROM ukm WHERE id = ?");
$stmt_ukm->execute([$ukm_id]);
$nama_ukm = $stmt_ukm->fetchColumn() ?? 'UKM Anda';

// =================================================================
// PROSES TAMBAH DIVISI
// =================================================================
if ($_POST && isset($_POST['tambah_divisi'])) {
    $nama_divisi = trim(sanitize($_POST['nama_divisi']));
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');

    if (empty($nama_divisi)) {
        showAlert('Nama divisi wajib diisi.', 'warning');
    } elseif (strlen($nama_divisi) > 100) {
        showAlert('Nama divisi maksimal 100 karakter.', 'warning');
    } else {
        // Cek duplikat di UKM ini
        $stmt_check = $db->prepare("SELECT id FROM divisi WHERE ukm_id = ? AND LOWER(nama_divisi) = LOWER(?)");
        $stmt_check->execute([$ukm_id, $nama_divisi]);
        if ($stmt_check->fetch()) {
            showAlert('Divisi dengan nama tersebut sudah ada di UKM Anda.', 'warning');
        } else {
            $stmt_ins = $db->prepare("
                INSERT INTO divisi (ukm_id, nama_divisi, deskripsi, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt_ins->execute([$ukm_id, $nama_divisi, $deskripsi]);
            showAlert('Divisi berhasil ditambahkan!', 'success');
        }
    }
}

// =================================================================
// PROSES EDIT DIVISI
// =================================================================
if ($_POST && isset($_POST['edit_divisi'])) {
    $id = (int)$_POST['id'];
    $nama_divisi = trim(sanitize($_POST['nama_divisi']));
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');

    if (empty($nama_divisi)) {
        showAlert('Nama divisi wajib diisi.', 'warning');
    } elseif (strlen($nama_divisi) > 100) {
        showAlert('Nama divisi maksimal 100 karakter.', 'warning');
    } else {
        // Pastikan divisi ini milik UKM saat ini
        $stmt_check = $db->prepare("SELECT id FROM divisi WHERE id = ? AND ukm_id = ?");
        $stmt_check->execute([$id, $ukm_id]);
        if (!$stmt_check->fetch()) {
            showAlert('Akses ditolak: Divisi tidak ditemukan atau bukan milik UKM Anda.', 'danger');
            redirect('kelola_divisi.php');
        }

        // Cek duplikat (kecuali diri sendiri)
        $stmt_dup = $db->prepare("
            SELECT id FROM divisi 
            WHERE ukm_id = ? AND LOWER(nama_divisi) = LOWER(?) AND id != ?
        ");
        $stmt_dup->execute([$ukm_id, $nama_divisi, $id]);
        if ($stmt_dup->fetch()) {
            showAlert('Divisi dengan nama tersebut sudah ada.', 'warning');
        } else {
            $stmt_upd = $db->prepare("
                UPDATE divisi 
                SET nama_divisi = ?, deskripsi = ?, updated_at = NOW()
                WHERE id = ? AND ukm_id = ?
            ");
            $stmt_upd->execute([$nama_divisi, $deskripsi, $id, $ukm_id]);
            showAlert('Divisi berhasil diperbarui!', 'success');
        }
    }
}

// =================================================================
// PROSES HAPUS DIVISI
// =================================================================
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    // Pastikan milik UKM ini
    $stmt_check = $db->prepare("SELECT id FROM divisi WHERE id = ? AND ukm_id = ?");
    $stmt_check->execute([$id, $ukm_id]);
    if (!$stmt_check->fetch()) {
        showAlert('Akses ditolak.', 'danger');
        redirect('kelola_divisi.php');
    }

    // Opsional: cek apakah divisi sedang digunakan di pendaftaran
    $stmt_used = $db->prepare("SELECT COUNT(*) FROM pendaftaran WHERE divisi_id = ?");
    $stmt_used->execute([$id]);
    if ($stmt_used->fetchColumn() > 0) {
        showAlert('Tidak bisa menghapus: Divisi ini sedang digunakan oleh anggota.', 'warning');
    } else {
        $stmt_del = $db->prepare("DELETE FROM divisi WHERE id = ? AND ukm_id = ?");
        $stmt_del->execute([$id, $ukm_id]);
        showAlert('Divisi berhasil dihapus!', 'success');
    }
}

// Ambil semua divisi milik UKM ini
$stmt = $db->prepare("SELECT * FROM divisi WHERE ukm_id = ? ORDER BY nama_divisi ASC");
$stmt->execute([$ukm_id]);
$divisi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ SET JUDUL & NAMA UKM UNTUK NAVBAR
$page_title = "Kelola Divisi - " . htmlspecialchars($nama_ukm);
?>

<?php include 'layout.php'; ?>

<style>
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
    .btn-action {
        font-size: 0.85rem;
        padding: 0.25rem 0.5rem;
    }
</style>

<!-- KONTEN UTAMA -->
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Kelola Divisi <small class="text-muted">- <?= htmlspecialchars($nama_ukm) ?></small></h2>
        <span class="badge bg-info">Hanya untuk UKM Anda</span>
    </div>

    <?php displayAlert(); ?>

    <!-- Form Tambah Divisi -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-plus-circle text-primary me-2"></i> Tambah Divisi Baru</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="tambah_divisi" value="1">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Divisi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_divisi" maxlength="100" placeholder="Contoh: PSDM, Olahraga, Seni" required>
                        <div class="form-text">Maks. 100 karakter</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" rows="2" placeholder="Opsional"></textarea>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan Divisi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Daftar Divisi -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-sitemap text-primary me-2"></i> Daftar Divisi</h5>
            <span class="badge bg-secondary"><?= count($divisi_list) ?> divisi</span>
        </div>
        <div class="card-body">
            <?php if (empty($divisi_list)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-folder-open fa-3x mb-2"></i>
                    <p>Belum ada divisi. Silakan tambahkan divisi pertama Anda!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama Divisi</th>
                                <th>Deskripsi</th>
                                <th>Tgl Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($divisi_list as $index => $div): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($div['nama_divisi']) ?></strong>
                                    </td>
                                    <td>
                                        <?= !empty($div['deskripsi']) ? nl2br(htmlspecialchars($div['deskripsi'])) : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= formatTanggal($div['created_at']) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-action"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal<?= $div['id'] ?>"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?hapus=<?= $div['id'] ?>"
                                               class="btn btn-outline-danger btn-action"
                                               title="Hapus"
                                               onclick="return confirm('Yakin hapus divisi \"<?= addslashes(htmlspecialchars($div['nama_divisi'])) ?>\"?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal Edit -->
                                <div class="modal fade" id="editModal<?= $div['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Divisi</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="edit_divisi" value="1">
                                                <input type="hidden" name="id" value="<?= $div['id'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label>Nama Divisi <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" name="nama_divisi"
                                                               value="<?= htmlspecialchars($div['nama_divisi']) ?>"
                                                               maxlength="100" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Deskripsi</label>
                                                        <textarea class="form-control" name="deskripsi" rows="3"><?= htmlspecialchars($div['deskripsi'] ?? '') ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Perbarui</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>