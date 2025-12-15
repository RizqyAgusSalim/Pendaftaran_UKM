<?php
session_start(); // ← HARUS ADA DI BARIS PERTAMA SETELAH <?php
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

// ----------------------------------------------------
// PENANGANAN UPDATE STATUS KEANGGOTAAN
// ----------------------------------------------------
if ($_POST && isset($_POST['update_status'])) {
    $pendaftaran_id = (int)$_POST['pendaftaran_id'];
    $status_baru = $_POST['status_keanggotaan'];

    $status_valid = ['aktif', 'tidak_aktif', 'cuti', 'dikeluarkan'];
    if (!in_array($status_baru, $status_valid)) {
        showAlert('Status tidak valid.', 'danger');
        redirect('kelola_mahasiswa.php');
    }

    $stmt_check = $db->prepare("SELECT id FROM pendaftaran WHERE id = :id AND ukm_id = :ukm_id AND status = 'diterima'");
    $stmt_check->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if (!$stmt_check->fetch()) {
        showAlert('Data tidak ditemukan atau bukan anggota UKM ini.', 'danger');
        redirect('kelola_mahasiswa.php');
    }

    $stmt_update = $db->prepare("UPDATE pendaftaran SET status_keanggotaan = :status WHERE id = :id");
    $stmt_update->bindParam(':status', $status_baru, PDO::PARAM_STR);
    $stmt_update->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);

    if ($stmt_update->execute()) {
        showAlert('Status keanggotaan berhasil diperbarui.', 'success');
    } else {
        showAlert('Gagal memperbarui status.', 'danger');
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
        p.id AS pendaftaran_id,
        p.status_keanggotaan
    FROM mahasiswa m
    INNER JOIN pendaftaran p ON m.id = p.mahasiswa_id
    WHERE p.ukm_id = :ukm_id 
      AND p.status = 'diterima'
    ORDER BY m.nama
";
$stmt = $db->prepare($query);
$stmt->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
$stmt->execute();
$anggota_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anggota <?= htmlspecialchars($nama_ukm) ?> - Sistem UKM Polinela</title>
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
            border-radius: 0;
            margin-bottom: 2px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
        }
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar dari file terpisah -->
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'sidebar.php'; ?>
            </div>

            <!-- Konten Utama -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>Kelola Anggota</h2>
                            <p class="text-muted mb-0">Kelola status keanggotaan di <strong><?= htmlspecialchars($nama_ukm) ?></strong></p>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> <?= formatTanggal(date('Y-m-d')) ?>
                        </small>
                    </div>

                    <?php displayAlert(); ?>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-users text-primary"></i> Daftar Anggota <?= htmlspecialchars($nama_ukm) ?>
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
                                            <th>Email</th>
                                            <th>Status Keanggotaan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($anggota_list)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    <i class="fas fa-users-slash fa-2x mb-2"></i><br>
                                                    Belum ada anggota di UKM ini.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1; ?>
                                            <?php foreach ($anggota_list as $m): ?>
                                                <tr>
                                                    <td><?= $no++ ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-initials">
                                                                <?= strtoupper(substr(htmlspecialchars($m['nama']), 0, 2)) ?>
                                                            </div>
                                                            <div class="ms-2 fw-bold"><?= htmlspecialchars($m['nama']) ?></div>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($m['nim']) ?></td>
                                                    <td><?= htmlspecialchars($m['email']) ?></td>
                                                    <td><?= getKeanggotaanBadge($m['status_keanggotaan']) ?></td>
                                                    <td>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="pendaftaran_id" value="<?= $m['pendaftaran_id'] ?>">
                                                            <select name="status_keanggotaan" class="form-select form-select-sm d-inline w-auto me-1">
                                                                <option value="aktif" <?= $m['status_keanggotaan'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                                                <option value="tidak_aktif" <?= $m['status_keanggotaan'] === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                                                                <option value="cuti" <?= $m['status_keanggotaan'] === 'cuti' ? 'selected' : '' ?>>Cuti</option>
                                                                <option value="dikeluarkan" <?= $m['status_keanggotaan'] === 'dikeluarkan' ? 'selected' : '' ?>>Dikeluarkan</option>
                                                            </select>
                                                            <button type="submit" name="update_status" value="1" class="btn btn-sm btn-primary">
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>