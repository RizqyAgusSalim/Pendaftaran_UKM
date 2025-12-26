<?php
// superadmin/kelola_adminUKM.php – Terpadu, Aman, Sesuai Struktur Database Anda
require_once '../config/database.php';
require_once '../config/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn() || $_SESSION['user_role'] !== 'superadmin') {
    redirect('../auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? null;
$id = isset($_GET['id']) ? intval($_GET['id']) : null; // admin_id

$message = '';
$error = '';

// === AMBIL DATA KATEGORI ===
$kategori_list = [];
try {
    $stmt = $db->query("SELECT id, nama_kategori FROM kategori_ukm ORDER BY nama_kategori ASC");
    $kategori_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Gagal memuat kategori: " . $e->getMessage();
}

// === LOAD DATA UNTUK EDIT ===
$edit_data = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("
        SELECT 
            a.id AS admin_id,
            a.nama AS admin_nama,
            a.username,
            a.email AS admin_email,
            a.role,
            u.id AS ukm_id,
            u.nama_ukm,
            u.deskripsi,
            u.kategori_id,
            u.ketua_umum,
            u.email AS ukm_email,
            u.no_telepon,
            u.status
        FROM admin a
        LEFT JOIN ukm u ON a.ukm_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_data) {
        $error = "Data tidak ditemukan.";
    }
}

// === PROSES FORM (TAMBAH/EDIT) ===
if ($_POST) {
    try {
        $nama_admin = sanitize($_POST['nama_admin']);
        $username = sanitize($_POST['username']);
        $email_admin = sanitize($_POST['email_admin']);
        $role = sanitize($_POST['role']);
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        $nama_ukm = sanitize($_POST['nama_ukm']);
        $deskripsi = sanitize($_POST['deskripsi']);
        $kategori_id = !empty($_POST['kategori_id']) ? intval($_POST['kategori_id']) : null;
        $ketua_umum = sanitize($_POST['ketua_umum']);
        $email_ukm = sanitize($_POST['email_ukm']);
        $no_telepon = sanitize($_POST['no_telepon']);
        $status = sanitize($_POST['status']);

        $admin_id = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;

        // Validasi
        if (empty($nama_admin) || empty($username) || empty($email_admin) || empty($role)) {
            throw new Exception('Data admin wajib diisi.');
        }
        if (empty($nama_ukm) || empty($deskripsi) || empty($status)) {
            throw new Exception('Data UKM wajib diisi.');
        }
        if (!$admin_id) {
            if (empty($password) || empty($password_confirm)) {
                throw new Exception('Password wajib diisi untuk akun baru.');
            }
            if ($password !== $password_confirm) {
                throw new Exception('Konfirmasi password tidak cocok.');
            }
            if (strlen($password) < 6) {
                throw new Exception('Password minimal 6 karakter.');
            }
        }

        // === ✅ PERBAIKAN: Cek duplikat username dengan aman ===
        $check = "SELECT id FROM admin WHERE username = ?";
        $params_check = [$username];
        if ($admin_id) {
            $check .= " AND id != ?";
            $params_check[] = $admin_id;
        }
        $stmt_check = $db->prepare($check);
        if (!$stmt_check) {
            throw new Exception("Error query: " . print_r($db->errorInfo(), true));
        }
        $stmt_check->execute($params_check);
        if ($stmt_check->fetch()) {
            throw new Exception('Username sudah digunakan.');
        }

        $db->beginTransaction();

        if ($admin_id) {
            // === UPDATE ===
            $sql_admin = "UPDATE admin SET nama = ?, username = ?, email = ?, role = ?";
            $params_admin = [$nama_admin, $username, $email_admin, $role];
            if (!empty($password)) {
                $sql_admin .= ", password = ?";
                $params_admin[] = password_hash($password, PASSWORD_BCRYPT);
            }
            $sql_admin .= " WHERE id = ?";
            $params_admin[] = $admin_id;
            $db->prepare($sql_admin)->execute($params_admin);

            $ukm_id = $edit_data['ukm_id'] ?? null;
            if ($ukm_id) {
                // Update UKM yang sudah ada
                $db->prepare("
                    UPDATE ukm SET 
                        nama_ukm = ?, deskripsi = ?, kategori_id = ?, ketua_umum = ?,
                        email = ?, no_telepon = ?, status = ?, admin_id = ?
                    WHERE id = ?
                ")->execute([
                    $nama_ukm, $deskripsi, $kategori_id, $ketua_umum,
                    $email_ukm, $no_telepon, $status, $admin_id, $ukm_id
                ]);
            } else {
                // Buat UKM baru karena belum ada
                $db->prepare("
                    INSERT INTO ukm (nama_ukm, deskripsi, kategori_id, ketua_umum, email, no_telepon, status, admin_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $nama_ukm, $deskripsi, $kategori_id, $ketua_umum,
                    $email_ukm, $no_telepon, $status, $admin_id
                ]);
                $new_ukm_id = $db->lastInsertId();
                $db->prepare("UPDATE admin SET ukm_id = ? WHERE id = ?")->execute([$new_ukm_id, $admin_id]);
            }

            $status_msg = 'success_edit';
        } else {
            // === INSERT BARU ===
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $created_at = date('Y-m-d H:i:s');
            $db->prepare("
                INSERT INTO admin (nama, username, email, password, role, created_at, ukm_id)
                VALUES (?, ?, ?, ?, ?, ?, NULL)
            ")->execute([$nama_admin, $username, $email_admin, $hashed, $role, $created_at]);
            $new_admin_id = $db->lastInsertId();

            $db->prepare("
                INSERT INTO ukm (nama_ukm, deskripsi, kategori_id, ketua_umum, email, no_telepon, status, admin_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $nama_ukm, $deskripsi, $kategori_id, $ketua_umum,
                $email_ukm, $no_telepon, $status, $new_admin_id
            ]);
            $new_ukm_id = $db->lastInsertId();

            $db->prepare("UPDATE admin SET ukm_id = ? WHERE id = ?")->execute([$new_ukm_id, $new_admin_id]);

            $status_msg = 'success_add';
        }

        $db->commit();
        header("Location: kelola_adminUKM.php?status=$status_msg");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Database error: " . $e->getMessage();
    }
}

// === PROSES HAPUS ===
if ($action === 'delete' && $id) {
    try {
        if ($id == $_SESSION['user_id']) {
            $error = "Anda tidak dapat menghapus akun sendiri.";
        } else {
            $db->beginTransaction();
            // Dapatkan ukm_id dari admin
            $stmt = $db->prepare("SELECT ukm_id FROM admin WHERE id = ?");
            $stmt->execute([$id]);
            $ukm_id = $stmt->fetchColumn();
            if ($ukm_id) {
                $db->prepare("DELETE FROM ukm WHERE id = ?")->execute([$ukm_id]);
            }
            $db->prepare("DELETE FROM admin WHERE id = ?")->execute([$id]);
            $db->commit();
            header("Location: kelola_adminUKM.php?status=success_delete");
            exit;
        }
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Gagal menghapus: " . $e->getMessage();
    }
}

// === NOTIFIKASI ===
$messages = [
    'success_add' => "Admin dan UKM berhasil ditambahkan.",
    'success_edit' => "Data berhasil diperbarui.",
    'success_delete' => "Data berhasil dihapus."
];
$message = $messages[$_GET['status'] ?? ''] ?? '';

// === AMBIL DATA UTAMA (AMAN DENGAN PARAMETERIZED QUERY) ===
$stmt = $db->prepare("
    SELECT 
        a.id AS admin_id,
        a.nama AS admin_nama,
        a.username,
        a.role,
        a.ukm_id,
        u.id AS ukm_id_real,
        u.nama_ukm,
        u.status AS ukm_status,
        u.email AS ukm_email,
        k.nama_kategori
    FROM admin a
    LEFT JOIN ukm u ON a.ukm_id = u.id
    LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
    WHERE a.role != 'superadmin' OR a.id = ?
    ORDER BY a.id DESC
");
$stmt->execute([$_SESSION['user_id']]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$form_data = $_POST ?? [];

// Set page title
$page_title = "Kelola Admin & UKM - Superadmin";

// Start output buffering untuk content
ob_start();
?>

<!-- KONTEN KELOLA ADMIN & UKM -->
<div class="header">
    <h3 class="mb-0">Kelola Admin & UKM (Terpadu)</h3>
    <p class="text-light">Satu entitas: setiap admin memiliki satu UKM.</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- === FORM TAMBAH/EDIT === -->
<?php if (in_array($action, ['tambah', 'edit'])): 
    $is_edit = ($action === 'edit');
    $data = $is_edit ? array_merge($edit_data ?? [], $form_data) : $form_data;
?>
    <div class="card-section">
        <h4 class="section-title">
            <?= $is_edit ? 'Edit Admin & UKM: ' . htmlspecialchars($data['nama_ukm'] ?? $data['admin_nama'] ?? '') : 'Tambah Admin & UKM Baru' ?>
        </h4>
        <form method="POST">
            <?php if ($is_edit): ?>
                <input type="hidden" name="admin_id" value="<?= htmlspecialchars($data['admin_id']) ?>">
            <?php endif; ?>

            <h5 class="mt-3">👤 Data Administrator</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_admin" class="form-control" required 
                        value="<?= htmlspecialchars($data['admin_nama'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required 
                        value="<?= htmlspecialchars($data['username'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Email</label>
                    <input type="email" name="email_admin" class="form-control" required 
                        value="<?= htmlspecialchars($data['admin_email'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Role</label>
                    <select name="role" class="form-select" required>
                        <option value="admin" <?= ($data['role'] ?? 'admin') === 'admin' ? 'selected' : '' ?>>Admin UKM</option>
                        <option value="superadmin" <?= ($data['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                    </select>
                </div>
            </div>

            <?php if (!$is_edit): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Konfirmasi Password</label>
                        <input type="password" name="password_confirm" class="form-control" required>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Password Baru (kosongkan jika tidak diubah)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirm" class="form-control">
                    </div>
                </div>
            <?php endif; ?>

            <h5 class="mt-4">🏛️ Data Unit Kegiatan Mahasiswa (UKM)</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Nama UKM</label>
                    <input type="text" name="nama_ukm" class="form-control" required 
                        value="<?= htmlspecialchars($data['nama_ukm'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Kategori</label>
                    <select name="kategori_id" class="form-select">
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($kategori_list as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= ($k['id'] == ($data['kategori_id'] ?? null)) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="3" required><?= htmlspecialchars($data['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Ketua Umum</label>
                    <input type="text" name="ketua_umum" class="form-control" 
                        value="<?= htmlspecialchars($data['ketua_umum'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Status</label>
                    <select name="status" class="form-select" required>
                        <option value="aktif" <?= ($data['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= ($data['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Email UKM</label>
                    <input type="email" name="email_ukm" class="form-control" 
                        value="<?= htmlspecialchars($data['ukm_email'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label>No. Telepon</label>
                    <input type="text" name="no_telepon" class="form-control" 
                        value="<?= htmlspecialchars($data['no_telepon'] ?? '') ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-success mt-3">
                <i class="fas fa-save"></i> <?= $is_edit ? 'Simpan Perubahan' : 'Tambah Admin & UKM' ?>
            </button>
            <a href="kelola_adminUKM.php" class="btn btn-secondary mt-3">Batal</a>
        </form>
    </div>

<!-- === TAMPILAN DAFTAR === -->
<?php else: ?>
    <div class="card-section">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h4 class="section-title mb-0">Daftar Admin & UKM Terpadu</h4>
            <a href="?action=tambah" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Admin & UKM
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Admin ID</th>
                        <th>Nama Admin</th>
                        <th>Username</th>
                        <th>Nama UKM</th>
                        <th>Kategori</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($records)): ?>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['admin_id']) ?></td>
                                <td><?= htmlspecialchars($r['admin_nama']) ?></td>
                                <td><?= htmlspecialchars($r['username']) ?></td>
                                <td><?= htmlspecialchars($r['nama_ukm'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($r['nama_kategori'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($r['ukm_email'] ?? '—') ?></td>
                                <td>
                                    <?php if ($r['ukm_status']): ?>
                                        <span class="badge bg-<?= $r['ukm_status'] === 'aktif' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($r['ukm_status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Belum Ada UKM</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?action=edit&id=<?= $r['admin_id'] ?>" class="btn btn-sm btn-warning mb-1">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($r['admin_id'] != $_SESSION['user_id']): ?>
                                        <a href="?action=delete&id=<?= $r['admin_id'] ?>" 
                                            onclick="return confirm('Hapus Admin & UKM ini?')" 
                                            class="btn btn-sm btn-danger mb-1">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Belum ada data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
// Extra CSS untuk styling khusus halaman ini
$extra_css = '
<style>
    .header {
        background: linear-gradient(135deg, #2980b9, #6dd5fa);
        padding: 25px;
        color: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .card-section {
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        padding: 20px;
        margin-bottom: 30px;
    }
    .section-title {
        border-left: 4px solid #2980b9;
        padding-left: 12px;
        margin-bottom: 20px;
        color: #2c3e50;
    }
</style>
';

// Simpan content ke variable
$content = ob_get_clean();

// Include layout
include 'layout.php';
?>