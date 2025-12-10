<?php
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'superadmin') {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// === Ambil semua UKM untuk filter ===
$all_ukm = $db->query("SELECT id, nama_ukm FROM ukm ORDER BY nama_ukm")->fetchAll(PDO::FETCH_ASSOC);

// === Proses Filter ===
$filter_ukm = $_GET['filter_ukm'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$sort_jurusan = $_GET['sort_jurusan'] ?? 'asc'; // asc atau desc

// === Bangun Query Dinamis ===
$sql = "
    SELECT 
        m.id,
        m.nim,
        m.nama,
        m.email,
        m.jurusan,
        m.status_akun,
        p.id AS id_pendaftaran,
        p.ukm_id,
        u.nama_ukm,
        p.status AS status_pendaftaran,
        p.status_keanggotaan
    FROM mahasiswa m
    JOIN pendaftaran p ON m.id = p.mahasiswa_id
    JOIN ukm u ON p.ukm_id = u.id
    WHERE 1=1
";

$params = [];

// Filter UKM
if ($filter_ukm !== '') {
    $sql .= " AND u.id = ?";
    $params[] = (int)$filter_ukm;
}

// Filter Status Pendaftaran
if ($filter_status !== '') {
    $sql .= " AND p.status = ?";
    $params[] = $filter_status;
}

// Urutkan
$sql .= " ORDER BY m.jurusan " . ($sort_jurusan === 'desc' ? 'DESC' : 'ASC') . ", m.nama ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$mahasiswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Nonaktifkan Akun ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nonaktifkan'])) {
    $id = (int)$_POST['id_mahasiswa'];
    $stmt = $db->prepare("UPDATE mahasiswa SET status_akun = 'nonaktif' WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['flash_message'] = "Akun mahasiswa berhasil dinonaktifkan.";
    $_SESSION['flash_type'] = "success";
    redirect('kelola_mahasiswa.php');
}

// === Aktifkan Akun ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aktifkan'])) {
    $id = (int)$_POST['id_mahasiswa'];
    $stmt = $db->prepare("UPDATE mahasiswa SET status_akun = 'aktif' WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['flash_message'] = "Akun mahasiswa berhasil diaktifkan.";
    $_SESSION['flash_type'] = "success";
    redirect('kelola_mahasiswa.php');
}

// === Edit Pendaftaran ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pendaftaran'])) {
    $id_pendaftaran = (int)$_POST['id_pendaftaran'];
    $ukm_id = (int)$_POST['ukm_id'];
    $status = $_POST['status'];
    
    $stmt = $db->prepare("UPDATE pendaftaran SET ukm_id = ?, status = ? WHERE id = ?");
    if ($stmt->execute([$ukm_id, $status, $id_pendaftaran])) {
        $_SESSION['flash_message'] = "Data pendaftaran berhasil diperbarui.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal memperbarui data.";
        $_SESSION['flash_type'] = "danger";
    }
    redirect('kelola_mahasiswa.php');
}

// === Ubah Password ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_password'])) {
    $id = (int)$_POST['id_mahasiswa'];
    $password_baru = $_POST['password_baru'];

    if (strlen($password_baru) < 6) {
        $_SESSION['flash_message'] = "Password minimal 6 karakter.";
        $_SESSION['flash_type'] = "warning";
    } else {
        $hashed = password_hash($password_baru, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE mahasiswa SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed, $id])) {
            $_SESSION['flash_message'] = "Password berhasil diubah.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Gagal mengubah password.";
            $_SESSION['flash_type'] = "danger";
        }
    }
    redirect('kelola_mahasiswa.php');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mahasiswa - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6fa; }
        .sidebar {
            height: 100vh;
            background: #2c3e50;
            padding-top: 20px;
            color: white;
        }
        .sidebar a {
            padding: 12px;
            display: block;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        .sidebar a:hover {
            background: #1abc9c;
            border-radius: 5px;
        }
        .card-custom {
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.85em;
            padding: 0.35em 0.5em;
        }
        .filter-section {
            background: #fff;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-2 sidebar">
            <h4 class="text-center mb-4"><i class="fas fa-crown"></i> Super Admin</h4>
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="kelola_admin.php"><i class="fas fa-users-cog"></i> Kelola Admin</a>
            <a href="kelola_mahasiswa.php" class="active"><i class="fas fa-user-graduate"></i> Kelola Mahasiswa</a>
            <a href="kelola_ukm.php"><i class="fas fa-sitemap"></i> Kelola UKM</a>
            <a href="../admin/dashboard.php"><i class="fas fa-user-shield"></i> Mode Admin</a>
            <a href="../auth/logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <!-- KONTEN -->
        <div class="col-10 p-4">
            <h2 class="mb-4"><i class="fas fa-user-graduate"></i> Kelola Mahasiswa</h2>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- FILTER SECTION -->
            <div class="filter-section mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter UKM</label>
                        <select name="filter_ukm" class="form-select">
                            <option value="">— Semua UKM —</option>
                            <?php foreach ($all_ukm as $ukm): ?>
                                <option value="<?= $ukm['id'] ?>" <?= ($filter_ukm == $ukm['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ukm['nama_ukm']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status Pendaftaran</label>
                        <select name="filter_status" class="form-select">
                            <option value="">— Semua Status —</option>
                            <option value="menunggu" <?= ($filter_status === 'menunggu') ? 'selected' : '' ?>>Menunggu</option>
                            <option value="diterima" <?= ($filter_status === 'diterima') ? 'selected' : '' ?>>Diterima</option>
                            <option value="ditolak" <?= ($filter_status === 'ditolak') ? 'selected' : '' ?>>Ditolak</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Urutkan Jurusan</label>
                        <select name="sort_jurusan" class="form-select">
                            <option value="asc" <?= ($sort_jurusan === 'asc') ? 'selected' : '' ?>>A → Z</option>
                            <option value="desc" <?= ($sort_jurusan === 'desc') ? 'selected' : '' ?>>Z → A</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter"></i> Terapkan
                        </button>
                        <a href="kelola_mahasiswa.php" class="btn btn-outline-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="card card-custom">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Jurusan</th>
                                    <th>Email</th>
                                    <th>UKM & Status</th>
                                    <th>Status Akun</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mahasiswa_list)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Tidak ada data sesuai filter.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mahasiswa_list as $m): ?>
                                        <tr>
                                            <td><?= $m['id'] ?></td>
                                            <td><?= htmlspecialchars($m['nim']) ?></td>
                                            <td><?= htmlspecialchars($m['nama']) ?></td>
                                            <td><?= htmlspecialchars($m['jurusan'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($m['email']) ?></td>
                                            <td>
                                                <span class="fw-bold"><?= htmlspecialchars($m['nama_ukm']) ?></span>
                                                <br>
                                                <span class="badge bg-<?= 
                                                    $m['status_pendaftaran'] === 'diterima' ? 'success' : 
                                                    ($m['status_pendaftaran'] === 'ditolak' ? 'danger' : 'warning')
                                                ?> status-badge">
                                                    <?= ucfirst($m['status_pendaftaran']) ?>
                                                </span>
                                                <?php if (!empty($m['status_keanggotaan'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($m['status_keanggotaan']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $m['status_akun'] === 'aktif' ? 'success' : 'danger' ?> status-badge">
                                                    <?= ucfirst($m['status_akun']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <!-- Edit Pendaftaran -->
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $m['id_pendaftaran'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <!-- Ubah Password -->
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#passModal<?= $m['id'] ?>">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <!-- Nonaktifkan -->
                                                <?php if ($m['status_akun'] === 'aktif'): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Nonaktifkan akun ini?')">
                                                        <input type="hidden" name="id_mahasiswa" value="<?= $m['id'] ?>">
                                                        <button type="submit" name="nonaktifkan" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Modal Edit Pendaftaran -->
                                        <div class="modal fade" id="editModal<?= $m['id_pendaftaran'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Pendaftaran: <?= htmlspecialchars($m['nama']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id_pendaftaran" value="<?= $m['id_pendaftaran'] ?>">
                                                            <div class="mb-3">
                                                                <label>UKM</label>
                                                                <select name="ukm_id" class="form-select">
                                                                    <option value="">— Pilih UKM —</option>
                                                                    <?php foreach ($all_ukm as $ukm): ?>
                                                                        <option value="<?= $ukm['id'] ?>" <?= ($ukm['id'] == $m['ukm_id']) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($ukm['nama_ukm']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label>Status Pendaftaran</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="menunggu" <?= ($m['status_pendaftaran'] === 'menunggu') ? 'selected' : '' ?>>Menunggu</option>
                                                                    <option value="diterima" <?= ($m['status_pendaftaran'] === 'diterima') ? 'selected' : '' ?>>Diterima</option>
                                                                    <option value="ditolak" <?= ($m['status_pendaftaran'] === 'ditolak') ? 'selected' : '' ?>>Ditolak</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" name="edit_pendaftaran" class="btn btn-primary">Simpan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal Ubah Password -->
                                        <div class="modal fade" id="passModal<?= $m['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Ubah Password: <?= htmlspecialchars($m['nama']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id_mahasiswa" value="<?= $m['id'] ?>">
                                                            <div class="mb-3">
                                                                <label>Password Baru (min. 6 karakter)</label>
                                                                <input type="password" name="password_baru" class="form-control" required minlength="6">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" name="ubah_password" class="btn btn-warning text-white">Ubah Password</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>