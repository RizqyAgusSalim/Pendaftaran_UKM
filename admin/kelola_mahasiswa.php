<?php
// admin/kelola_mahasiswa.php — VERSI RESPONSIF & SCROLLABLE
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

if (!isset($_SESSION['ukm_id']) || $_SESSION['ukm_id'] <= 0) {
    showAlert('Akses ditolak: Anda bukan admin UKM.', 'danger');
    redirect('dashboard.php');
}

$ukm_id = (int)$_SESSION['ukm_id'];
$database = new Database();
$db = $database->getConnection();

// Ambil nama UKM
$stmt_ukm = $db->prepare("SELECT nama_ukm FROM ukm WHERE id = :ukm_id");
$stmt_ukm->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
$stmt_ukm->execute();
$nama_ukm = $stmt_ukm->fetchColumn() ?? "UKM Anda";

// =================================================================
// PROSES UPDATE DATA MAHASISWA (JURUSAN + DIVISI)
// =================================================================
if ($_POST && isset($_POST['update_mahasiswa'])) {
    $pendaftaran_id = (int)$_POST['pendaftaran_id'];
    $jurusan = sanitize($_POST['jurusan'] ?? '');
    $divisi_id = !empty($_POST['divisi_id']) ? (int)$_POST['divisi_id'] : null;

    $stmt_check = $db->prepare("
        SELECT p.id, p.mahasiswa_id 
        FROM pendaftaran p 
        WHERE p.id = ? AND p.ukm_id = ? AND p.status = 'diterima'
    ");
    $stmt_check->execute([$pendaftaran_id, $ukm_id]);
    $data = $stmt_check->fetch();

    if (!$data) {
        showAlert('Data tidak ditemukan.', 'danger');
        redirect('kelola_mahasiswa.php');
    }

    $mahasiswa_id = $data['mahasiswa_id'];

    try {
        $stmt_update_mhs = $db->prepare("UPDATE mahasiswa SET jurusan = ? WHERE id = ?");
        $stmt_update_mhs->execute([$jurusan, $mahasiswa_id]);

        $stmt_update_pendaftaran = $db->prepare("UPDATE pendaftaran SET divisi_id = ? WHERE id = ?");
        $stmt_update_pendaftaran->execute([$divisi_id, $pendaftaran_id]);

        showAlert('Data mahasiswa berhasil diperbarui.', 'success');
    } catch (PDOException $e) {
        error_log("Error update mahasiswa: " . $e->getMessage());
        showAlert('Gagal memperbarui data.', 'danger');
    }

    redirect('kelola_mahasiswa.php');
}

// Ambil data anggota
$query = "
    SELECT 
        m.id AS mahasiswa_id,
        m.nama, 
        m.nim, 
        m.email,
        m.jurusan,
        p.id AS pendaftaran_id,
        p.status_keanggotaan,
        d.id AS divisi_id,
        d.nama_divisi
    FROM mahasiswa m
    INNER JOIN pendaftaran p ON m.id = p.mahasiswa_id
    LEFT JOIN divisi d ON p.divisi_id = d.id
    WHERE p.ukm_id = :ukm_id 
      AND p.status = 'diterima'
    ORDER BY m.nama
";
$stmt = $db->prepare($query);
$stmt->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
$stmt->execute();
$anggota_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar divisi
$divisi_list = [];
$stmt_div = $db->prepare("SELECT id, nama_divisi FROM divisi WHERE ukm_id = ? ORDER BY nama_divisi");
$stmt_div->execute([$ukm_id]);
$divisi_list = $stmt_div->fetchAll(PDO::FETCH_ASSOC);

function getKeanggotaanBadge($status) {
    $label = match($status) {
        'aktif' => 'Aktif',
        'tidak_aktif' => 'Tidak Aktif',
        'cuti' => 'Cuti',
        'dikeluarkan' => 'Dikeluarkan',
        default => 'Aktif'
    };
    $color = match($status) {
        'aktif' => 'success',
        'tidak_aktif' => 'secondary',
        'cuti' => 'warning',
        'dikeluarkan' => 'danger',
        default => 'success'
    };
    return "<span class='badge bg-{$color}'>{$label}</span>";
}

$page_title = "Kelola Anggota - " . htmlspecialchars($nama_ukm);
?>

<?php include 'layout.php'; ?>

<style>
    .avatar-initials {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: #3498db;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: bold;
    }
    .edit-form {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
    }
    .edit-form select,
    .edit-form input {
        width: auto;
        min-width: 100px;
        font-size: 0.875rem;
    }
    /* Pastikan tabel bisa scroll horizontal di mobile */
    @media (max-width: 767px) {
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table {
            min-width: 700px; /* cukup untuk Nama, NIM, Status, Aksi */
        }
    }
</style>

<div class="p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-md-row">
        <div class="text-center text-md-start">
            <h2 class="h4 mb-1 mb-md-0">Kelola Anggota</h2>
            <p class="text-muted mb-0">Kelola status keanggotaan di <strong><?= htmlspecialchars($nama_ukm) ?></strong></p>
        </div>
        <small class="text-muted mt-2 mt-md-0">
            <i class="fas fa-calendar"></i> <?= formatTanggal(date('Y-m-d')) ?>
        </small>
    </div>

    <?php displayAlert(); ?>

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="fas fa-users text-primary"></i> Daftar Anggota
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>NIM</th>
                            <th class="d-none d-md-table-cell">Jurusan</th>
                            <th class="d-none d-md-table-cell">Divisi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($anggota_list)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-users-slash fa-2x mb-2"></i><br>
                                    Belum ada anggota di UKM ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($anggota_list as $m): ?>
                                <tr>
                                    <td class="align-middle"><?= $no++ ?></td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials d-none d-md-flex me-2">
                                                <?= strtoupper(substr(htmlspecialchars($m['nama']), 0, 2)) ?>
                                            </div>
                                            <div class="fw-bold"><?= htmlspecialchars($m['nama']) ?></div>
                                        </div>
                                    </td>
                                    <td class="align-middle"><?= htmlspecialchars($m['nim']) ?></td>
                                    <td class="align-middle d-none d-md-table-cell"><?= htmlspecialchars($m['jurusan'] ?? '-') ?></td>
                                    <td class="align-middle d-none d-md-table-cell"><?= $m['nama_divisi'] ?? '<span class="text-muted">-</span>' ?></td>
                                    <td class="align-middle"><?= getKeanggotaanBadge($m['status_keanggotaan']) ?></td>
                                    <td class="align-middle">
                                        <form method="POST" class="edit-form">
                                            <input type="hidden" name="pendaftaran_id" value="<?= $m['pendaftaran_id'] ?>">
                                            <input type="text" 
                                                name="jurusan" 
                                                class="form-control form-control-sm" 
                                                value="<?= htmlspecialchars($m['jurusan'] ?? '') ?>" 
                                                placeholder="Jurusan">
                                            <select name="divisi_id" class="form-select form-select-sm">
                                                <option value="">-- Umum --</option>
                                                <?php foreach ($divisi_list as $div): ?>
                                                    <option value="<?= $div['id'] ?>" 
                                                        <?= ($div['id'] == $m['divisi_id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($div['nama_divisi']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="update_mahasiswa" class="btn btn-sm btn-primary">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>