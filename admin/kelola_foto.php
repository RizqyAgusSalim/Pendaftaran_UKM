<?php
// admin/kelola_foto.php — VERSI DENGAN LAYOUT TERPADU
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan login sebagai admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$is_superadmin = ($_SESSION['user_role'] === 'superadmin');
$ukm_id = $is_superadmin ? null : (int)($_SESSION['ukm_id'] ?? 0);

// Validasi akses
if (!$is_superadmin && empty($_SESSION['ukm_id'])) {
    showAlert('Akses ditolak: Anda bukan admin UKM.', 'danger');
    redirect('dashboard.php');
}

// ✅ AMBIL NAMA UKM UNTUK NAVBAR (jika admin UKM)
$nama_ukm = "Admin UKM";
if (!$is_superadmin && $ukm_id) {
    $stmt_ukm = $db->prepare("SELECT nama_ukm FROM ukm WHERE id = ?");
    $stmt_ukm->execute([$ukm_id]);
    $nama_ukm = $stmt_ukm->fetchColumn() ?? "UKM Anda";
} elseif ($is_superadmin) {
    $nama_ukm = "Super Admin";
}

// ----------------------------------------------------
// HAPUS FOTO
// ----------------------------------------------------
if (isset($_GET['hapus_foto'])) {
    $foto_id = (int)$_GET['hapus_foto'];

    if ($is_superadmin) {
        $stmt = $db->prepare("
            SELECT fk.foto, k.nama_kegiatan, u.nama_ukm
            FROM foto_kegiatan fk
            JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
            JOIN ukm u ON k.ukm_id = u.id
            WHERE fk.id = ?
        ");
        $stmt->execute([$foto_id]);
        $foto_data = $stmt->fetch();
    } else {
        $stmt = $db->prepare("
            SELECT fk.foto, k.nama_kegiatan, u.nama_ukm
            FROM foto_kegiatan fk
            JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
            JOIN ukm u ON k.ukm_id = u.id
            WHERE fk.id = ? AND u.id = ?
        ");
        $stmt->execute([$foto_id, $ukm_id]);
        $foto_data = $stmt->fetch();
    }

    if ($foto_data) {
        $file_path = '../uploads/kegiatan/' . $foto_data['foto'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $db->prepare("DELETE FROM foto_kegiatan WHERE id = ?")->execute([$foto_id]);
        $kegiatan_info = '"' . htmlspecialchars($foto_data['nama_kegiatan']) . '" (' . htmlspecialchars($foto_data['nama_ukm']) . ')';
        showAlert("Foto dari kegiatan {$kegiatan_info} berhasil dihapus.", 'success');
    } else {
        showAlert('Foto tidak ditemukan atau Anda tidak memiliki akses.', 'danger');
    }
    redirect('kelola_foto.php');
}

// ----------------------------------------------------
// AMBIL DATA FOTO
// ----------------------------------------------------
if ($is_superadmin) {
    $stmt = $db->prepare("
        SELECT 
            fk.id AS foto_id,
            fk.foto,
            fk.keterangan,
            fk.created_at,
            k.id AS kegiatan_id,
            k.nama_kegiatan,
            u.nama_ukm,
            u.id AS ukm_id
        FROM foto_kegiatan fk
        JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
        JOIN ukm u ON k.ukm_id = u.id
        ORDER BY fk.created_at DESC
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT 
            fk.id AS foto_id,
            fk.foto,
            fk.keterangan,
            fk.created_at,
            k.id AS kegiatan_id,
            k.nama_kegiatan,
            u.nama_ukm,
            u.id AS ukm_id
        FROM foto_kegiatan fk
        JOIN kegiatan_ukm k ON fk.kegiatan_id = k.id
        JOIN ukm u ON k.ukm_id = u.id
        WHERE u.id = ?
        ORDER BY fk.created_at DESC
    ");
    $stmt->execute([$ukm_id]);
}

$foto_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ SET JUDUL HALAMAN
$page_title = "Galeri Foto - " . ($is_superadmin ? "Semua UKM" : htmlspecialchars($nama_ukm));
?>

<?php include 'layout.php'; ?>

<style>
    .foto-preview {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .foto-card {
        transition: transform 0.2s;
    }
    .foto-card:hover {
        transform: translateY(-3px);
    }
    .badge-ukm {
        font-size: 0.75em;
        padding: 4px 8px;
    }
</style>

<!-- KONTEN UTAMA -->
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-images me-2"></i>Galeri Foto Kegiatan</h2>
        <?php if (!$is_superadmin): ?>
            <a href="kelola_kegiatan.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Kegiatan
            </a>
        <?php endif; ?>
    </div>

    <?php displayAlert(); ?>

    <?php if (empty($foto_list)): ?>
        <div class="text-center py-5">
            <i class="fas fa-images fa-3x text-muted mb-3"></i>
            <h5>Tidak ada foto kegiatan</h5>
            <?php if ($is_superadmin): ?>
                <p class="text-muted">Belum ada UKM yang mengupload foto kegiatan.</p>
            <?php else: ?>
                <p class="text-muted">Upload foto melalui halaman <strong>Kelola Kegiatan</strong>.</p>
                <a href="kelola_kegiatan.php" class="btn btn-primary">Kelola Kegiatan</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($foto_list as $foto): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card foto-card h-100">
                        <img src="../uploads/kegiatan/<?= htmlspecialchars($foto['foto']) ?>" 
                             class="foto-preview" 
                             alt="Foto">
                        <div class="card-body p-2">
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars(substr($foto['nama_kegiatan'], 0, 25)) ?>...</h6>
                            <?php if ($is_superadmin): ?>
                                <span class="badge badge-ukm bg-primary"><?= htmlspecialchars($foto['nama_ukm']) ?></span>
                            <?php endif; ?>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-calendar"></i> <?= formatTanggal($foto['created_at']) ?>
                            </small>
                            <?php if (!empty($foto['keterangan'])): ?>
                                <p class="text-muted small mb-2"><?= htmlspecialchars($foto['keterangan']) ?></p>
                            <?php endif; ?>
                            <div class="d-grid">
                                <a href="?hapus_foto=<?= $foto['foto_id'] ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Yakin hapus foto ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>