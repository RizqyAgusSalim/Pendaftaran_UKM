<?php
// admin/kelola_pendaftaran.php — VERSI DENGAN LAYOUT TERPADU
session_start(); // ← WAJIB DI AWAL
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan login sebagai admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Pastikan ini admin UKM
if (!isset($_SESSION['ukm_id']) || $_SESSION['ukm_id'] <= 0) {
    showAlert('Akses ditolak: Anda bukan admin UKM.', 'danger');
    redirect('dashboard.php');
}

$ukm_id = (int)$_SESSION['ukm_id'];
$admin_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

// ----------------------------------------------------
// Ambil nama UKM
// ----------------------------------------------------
$stmt_ukm = $db->prepare("SELECT nama_ukm FROM ukm WHERE id = :ukm_id");
$stmt_ukm->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
$stmt_ukm->execute();
$nama_ukm = $stmt_ukm->fetchColumn() ?? "UKM Anda";

// ----------------------------------------------------
// PROSES APPROVE
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
    $pendaftaran_id = (int)$_GET['id'];

    $stmt_check = $db->prepare("
        SELECT id FROM pendaftaran 
        WHERE id = :id AND ukm_id = :ukm_id AND status = 'pending'
    ");
    $stmt_check->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if (!$stmt_check->fetch()) {
        showAlert('Pendaftaran tidak ditemukan atau bukan milik UKM Anda.', 'danger');
        redirect('kelola_pendaftaran.php');
    }

    try {
        $stmt_update = $db->prepare("
            UPDATE pendaftaran 
            SET status = 'diterima',
                diproses_oleh = :admin_id,
                tanggal_diproses = NOW()
            WHERE id = :id
        ");
        $stmt_update->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);
        $stmt_update->execute();

        showAlert('Pendaftaran berhasil diterima!', 'success');
    } catch (Exception $e) {
        error_log("Error approve: " . $e->getMessage());
        showAlert('Gagal menerima pendaftaran.', 'danger');
    }

    redirect('kelola_pendaftaran.php');
}

// ----------------------------------------------------
// PROSES REJECT
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['id'])) {
    $pendaftaran_id = (int)$_GET['id'];
    $alasan = trim($_POST['alasan_ditolak']) ?: 'Tidak memenuhi persyaratan';

    $stmt_check = $db->prepare("
        SELECT id FROM pendaftaran 
        WHERE id = :id AND ukm_id = :ukm_id AND status = 'pending'
    ");
    $stmt_check->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if (!$stmt_check->fetch()) {
        showAlert('Pendaftaran tidak ditemukan atau bukan milik UKM Anda.', 'danger');
        redirect('kelola_pendaftaran.php');
    }

    try {
        $stmt_update = $db->prepare("
            UPDATE pendaftaran 
            SET status = 'ditolak',
                catatan_admin = :alasan,
                diproses_oleh = :admin_id,
                tanggal_diproses = NOW()
            WHERE id = :id
        ");
        $stmt_update->bindParam(':alasan', $alasan);
        $stmt_update->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);
        $stmt_update->execute();

        showAlert('Pendaftaran telah ditolak.', 'info');
    } catch (Exception $e) {
        error_log("Error reject: " . $e->getMessage());
        showAlert('Gagal menolak pendaftaran.', 'danger');
    }

    redirect('kelola_pendaftaran.php');
}

// ----------------------------------------------------
// FILTER DATA
// ----------------------------------------------------
$status_filter = $_GET['status'] ?? '';

// 🔹 Tambahkan JOIN ke tabel divisi
$query = "
    SELECT p.*, 
           m.nama, m.nim, m.email, m.no_telepon, m.jurusan, m.angkatan, m.alamat,
           u.nama_ukm,
           d.nama_divisi,
           p.catatan_admin
    FROM pendaftaran p
    JOIN mahasiswa m ON p.mahasiswa_id = m.id
    JOIN ukm u ON p.ukm_id = u.id
    LEFT JOIN divisi d ON p.divisi_id = d.id  -- 🔹 Tambahkan JOIN divisi
    WHERE p.ukm_id = :ukm_id
";

$params = [':ukm_id' => $ukm_id];

if (!empty($status_filter)) {
    $query .= " AND p.status = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY 
            CASE p.status 
                WHEN 'pending' THEN 1 
                WHEN 'diterima' THEN 2 
                WHEN 'ditolak' THEN 3 
            END,
            p.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$pendaftaran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ SET JUDUL & NAMA UKM UNTUK NAVBAR
$page_title = "Kelola Pendaftaran - " . htmlspecialchars($nama_ukm);
?>

<?php include 'layout.php'; ?>

<style>
    .badge-pending { background: #ffc107 !important; color: #000 !important; }
    .badge-diterima { background: #28a745 !important; color: white !important; }
    .badge-ditolak { background: #dc3545 !important; color: white !important; }
</style>

<!-- KONTEN UTAMA -->
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-clipboard-list text-primary"></i> Kelola Pendaftaran</h2>
            <p class="text-muted mb-0">Kelola pendaftaran anggota <strong><?= htmlspecialchars($nama_ukm) ?></strong></p>
        </div>
    </div>

    <?php displayAlert(); ?>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">Filter Status:</h5>
                </div>
                <div class="col-md-6">
                    <div class="btn-group w-100" role="group">
                        <a href="kelola_pendaftaran.php" class="btn <?= empty($status_filter) ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Semua
                        </a>
                        <a href="kelola_pendaftaran.php?status=pending" class="btn <?= $status_filter == 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
                            Pending
                        </a>
                        <a href="kelola_pendaftaran.php?status=diterima" class="btn <?= $status_filter == 'diterima' ? 'btn-success' : 'btn-outline-success' ?>">
                            Diterima
                        </a>
                        <a href="kelola_pendaftaran.php?status=ditolak" class="btn <?= $status_filter == 'ditolak' ? 'btn-danger' : 'btn-outline-danger' ?>">
                            Ditolak
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Pendaftaran -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="fas fa-list"></i> Daftar Pendaftaran
                <span class="badge bg-primary"><?= count($pendaftaran_list) ?> Data</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th>Mahasiswa</th>
                            <th>NIM</th>
                            <th>Divisi</th> <!-- 🔹 KOLOM BARU -->
                            <th>Jurusan</th>
                            <th>No HP</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendaftaran_list)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                    Belum ada pendaftaran
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($pendaftaran_list as $p): ?>
                                <?php
                                $status = strtolower($p['status']);
                                $class = match($status) {
                                    'pending' => 'badge-pending',
                                    'diterima' => 'badge-diterima',
                                    'ditolak' => 'badge-ditolak',
                                    default => 'bg-secondary text-white'
                                };
                                // 🔹 Tentukan teks divisi
                                $divisi_tampil = !empty($p['nama_divisi']) ? htmlspecialchars($p['nama_divisi']) : '<span class="text-muted">Umum</span>';
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                                <?= strtoupper(substr(htmlspecialchars($p['nama']), 0, 2)) ?>
                                            </div>
                                            <div class="ms-2">
                                                <strong><?= htmlspecialchars($p['nama']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($p['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($p['nim']) ?></span></td>
                                    <td><?= $divisi_tampil ?></td> <!-- 🔹 TAMPILKAN DIVISI -->
                                    <td><?= htmlspecialchars($p['jurusan']) ?></td>
                                    <td><?= htmlspecialchars($p['no_telepon']) ?></td>
                                    <td><?= formatTanggal($p['created_at']) ?></td>
                                    <td>
                                        <span class="badge <?= $class ?>"><?= ucfirst($p['status']) ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalDetail<?= $p['id'] ?>" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($p['status'] == 'pending'): ?>
                                                <a href="?action=approve&id=<?= $p['id'] ?>" 
                                                   class="btn btn-outline-success"
                                                   onclick="return confirm('Terima pendaftaran dari <?= addslashes(htmlspecialchars($p['nama'])) ?>?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalReject<?= $p['id'] ?>" title="Tolak">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Modal Detail -->
                                        <div class="modal fade" id="modalDetail<?= $p['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Detail Pendaftaran</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <table class="table table-sm">
                                                            <tr><th width="40%">Nama</th><td><?= htmlspecialchars($p['nama']) ?></td></tr>
                                                            <tr><th>NIM</th><td><?= htmlspecialchars($p['nim']) ?></td></tr>
                                                            <tr><th>Email</th><td><?= htmlspecialchars($p['email']) ?></td></tr>
                                                            <tr><th>No HP</th><td><?= htmlspecialchars($p['no_telepon']) ?></td></tr>
                                                            <tr><th>Jurusan</th><td><?= htmlspecialchars($p['jurusan']) ?></td></tr>
                                                            <!-- 🔹 TAMBAHKAN DIVISI DI MODAL -->
                                                            <tr><th>Divisi</th><td><?= !empty($p['nama_divisi']) ? htmlspecialchars($p['nama_divisi']) : 'Umum' ?></td></tr>
                                                            <tr><th>Angkatan</th><td><?= htmlspecialchars($p['angkatan']) ?></td></tr>
                                                            <tr><th>Alasan Bergabung</th><td><?= nl2br(htmlspecialchars($p['alasan_bergabung'])) ?></td></tr>
                                                            <tr><th>Status</th><td><span class="badge <?= $class ?>"><?= ucfirst($p['status']) ?></span></td></tr>
                                                            <?php if ($p['status'] === 'ditolak' && !empty($p['catatan_admin'])): ?>
                                                                <tr><th>Alasan Penolakan</th><td><?= nl2br(htmlspecialchars($p['catatan_admin'])) ?></td></tr>
                                                            <?php endif; ?>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal Tolak -->
                                        <div class="modal fade" id="modalReject<?= $p['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Tolak Pendaftaran</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="?action=reject&id=<?= $p['id'] ?>">
                                                        <div class="modal-body">
                                                            <p>Tolak pendaftaran dari <strong><?= htmlspecialchars($p['nama']) ?></strong>?</p>
                                                            <div class="mb-3">
                                                                <label>Alasan Penolakan</label>
                                                                <textarea name="alasan_ditolak" class="form-control" rows="3" required>Tidak memenuhi persyaratan</textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-danger">Tolak</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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