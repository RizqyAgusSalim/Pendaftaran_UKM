<?php
// superadmin/kelola_admin.php (Single File CRUD)

require_once '../config/database.php';
require_once '../config/functions.php';

// Cek apakah session sudah aktif sebelum memanggil session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autentikasi dan Otorisasi
if (!isLoggedIn() || $_SESSION['user_type'] !== 'admin' || $_SESSION['user_role'] !== 'superadmin') {
    redirect('../auth/login.php');
    exit;
}

// Inisialisasi Database
$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$action = $_GET['action'] ?? 'view'; // Default action: view
$edit_admin_data = null; // Untuk menyimpan data admin yang sedang diedit

// --- START REVISI: Ambil data UKM dan data kepemilikan UKM saat ini ---
$ukm_list = [];
try {
    // Ambil daftar semua UKM beserta nama admin pemilik saat ini (JOIN dengan tabel admin)
    $query_ukm = "SELECT u.id, u.nama_ukm, u.admin_id, a.nama AS admin_owner_name 
                  FROM ukm u 
                  LEFT JOIN admin a ON u.admin_id = a.id
                  ORDER BY u.nama_ukm ASC";
    $stmt_ukm = $db->prepare($query_ukm);
    $stmt_ukm->execute();
    $ukm_list = $stmt_ukm->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Tampilkan error jika terjadi masalah database saat mengambil data UKM
    $error = "Gagal memuat data UKM: " . $e->getMessage();
}
// --- END REVISI ---

// --- PROSES DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $admin_id = intval($_GET['id']);
    if ($admin_id == $_SESSION['user_id']) {
        $error = "Anda tidak dapat menghapus akun Anda sendiri.";
    } else {
        try {
            // Opsional: Set admin_id di tabel ukm menjadi NULL untuk admin yang akan dihapus
            $query_update_ukm = "UPDATE ukm SET admin_id = NULL WHERE admin_id = ?";
            $stmt_update_ukm = $db->prepare($query_update_ukm);
            $stmt_update_ukm->execute([$admin_id]);

            $query = "DELETE FROM admin WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$admin_id])) {
                header('Location: kelola_admin.php?status=success_delete'); 
                exit;
            } else { $error = "Gagal menghapus admin."; }
        } catch (PDOException $e) { $error = "Terjadi error database: " . $e->getMessage(); }
    }
    $action = 'view';
}

// --- PROSES EDIT (Ambil Data Lama) ---
if ($action === 'edit' && isset($_GET['id'])) {
    $admin_id = intval($_GET['id']);
    $query = "SELECT * FROM admin WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$admin_id]);
    $edit_admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$edit_admin_data) {
        $error = "Admin tidak ditemukan.";
        $action = 'view';
    }
}

// --- PROSES POST (TAMBAH & EDIT) ---
if ($_POST) {
    // 1. Ambil Input
    $username = sanitize($_POST['username']);
    $nama = sanitize($_POST['nama']);
    $email = sanitize($_POST['email']);
    $role = sanitize($_POST['role']);
    $password_input = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    // --- START REVISI: Ambil input untuk pendelegasian UKM ---
    $ukm_id_to_assign = isset($_POST['ukm_id_to_assign']) ? intval($_POST['ukm_id_to_assign']) : 0;
    // --- END REVISI ---

    $is_edit_mode = isset($_POST['admin_id']) && $_POST['admin_id'] > 0;
    $current_id = $is_edit_mode ? intval($_POST['admin_id']) : 0;
    $action_after_post = $is_edit_mode ? 'edit' : 'tambah'; 
    $success = false;
    $error_ukm_assign = false;

    // 2. Validasi Umum
    if (empty($username) || empty($nama) || empty($email) || empty($role)) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!$is_edit_mode && (empty($password_input) || empty($password_confirm))) {
        $error = 'Password dan konfirmasi password wajib diisi untuk admin baru.';
    } elseif (!empty($password_input) && $password_input !== $password_confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (!empty($password_input) && strlen($password_input) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        // 3. Cek Duplikasi Username
        $query_check = "SELECT id FROM admin WHERE username = ?";
        if ($is_edit_mode) {
            $query_check .= " AND id != ?";
            $stmt_check = $db->prepare($query_check);
            $stmt_check->execute([$username, $current_id]);
        } else {
            $stmt_check = $db->prepare($query_check);
            $stmt_check->execute([$username]);
        }
        
        if ($stmt_check->rowCount() > 0) {
            $error = 'Username sudah digunakan oleh admin lain.';
        } else {
            // 4. Proses Insert atau Update
            

            if (!$is_edit_mode) { // --- INSERT / TAMBAH ---
                $hashed_password = password_hash($password_input, PASSWORD_BCRYPT);
                $created_at = date('Y-m-d H:i:s');
                $query = "INSERT INTO admin (username, password, nama, email, created_at, role) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$username, $hashed_password, $nama, $email, $created_at, $role])) {
                    $success = true;
                } else { $error = 'Gagal menambahkan admin.'; }
            } else { // --- UPDATE / EDIT ---
                $query = "UPDATE admin SET username = ?, nama = ?, email = ?, role = ?";
                $params = [$username, $nama, $email, $role];
                
                if (!empty($password_input)) {
                    $hashed_password = password_hash($password_input, PASSWORD_BCRYPT);
                    $query .= ", password = ?";
                    $params[] = $hashed_password;
                }
                
                $query .= " WHERE id = ?";
                $params[] = $current_id;
                
                $stmt = $db->prepare($query);
                if ($stmt->execute($params)) {
                    $success = true;
                } else { $error = 'Gagal memperbarui admin.'; }
            }

            // --- START REVISI: Proses pendelegasian UKM setelah Admin berhasil di-UPDATE ---
            if ($success && $is_edit_mode) {
                if ($ukm_id_to_assign > 0) {
                    try {
                        // Cek apakah ada UKM lain yang dimiliki oleh admin ini, dan SET NULL (optional, tergantung requirement)
                        // Untuk kasus ini, kita hanya fokus pada UKM yang baru dipilih, UKM lain yang mungkin pernah dimiliki admin ini
                        // tidak akan disentuh, kecuali jika UKM lain tersebut dipilih di form edit admin lain.
                        
                        // Lakukan assignment UKM
                        $query_assign = "UPDATE ukm SET admin_id = :new_admin_id WHERE id = :ukm_id";
                        $stmt_assign = $db->prepare($query_assign);
                        $stmt_assign->bindParam(':new_admin_id', $current_id); // Admin ID yang sedang diedit
                        $stmt_assign->bindParam(':ukm_id', $ukm_id_to_assign);
                        if ($stmt_assign->execute()) {
                            // Delegasi UKM sukses
                        } else {
                            // Jika admin update sukses, tapi delegasi UKM gagal
                            $error_ukm_assign = true;
                            $error .= ' | Admin berhasil diperbarui, tetapi GAGAL mengubah kepemilikan UKM.';
                        }
                    } catch (PDOException $e) {
                           $error_ukm_assign = true;
                           $error .= ' | Admin berhasil diperbarui, tetapi GAGAL mengubah kepemilikan UKM (DB Error).';
                    }
                } else {
                    // Jika ukm_id_to_assign adalah 0, berarti user tidak ingin mengubah kepemilikan UKM untuk admin ini.
                }
            }
            // --- END REVISI ---
            
            // Pengalihan hanya jika sukses dan tidak ada error (atau hanya error non-fatal pada pendelegasian)
            if ($success && !$error) {
                // Semua sukses, tidak ada error
                header('Location: kelola_admin.php?status=' . ($is_edit_mode ? 'success_edit' : 'success_add'));
                exit;
            } elseif ($success && $error_ukm_assign) {
                // Admin sukses diupdate, tapi ada warning di UKM assign
                header('Location: kelola_admin.php?status=success_edit_with_warning');
                exit;
            }
        }
    }
    
    // Jika ada error fatal (validasi), tampilkan kembali form
    if ($error && $action_after_post === 'edit') {
        $action = $action_after_post; 
        if ($is_edit_mode) {
            // Memastikan data yang diisi ulang diambil dari $_POST
            $edit_admin_data = [
                'id' => $current_id, 
                'username' => $username, 
                'nama' => $nama, 
                'email' => $email, 
                'role' => $role
            ];
            // Karena form edit butuh $edit_admin_data, kita re-populate dari POST
        }
    } elseif ($error && $action_after_post === 'tambah') {
        $action = $action_after_post;
        // Data yang diisi ulang akan diambil langsung dari $_POST pada form.
    }
}


// --- AMBIL DATA ADMIN UNTUK VIEW (dengan detail UKM yang dikelola) ---
$admins = [];
if ($action === 'view') {
    try {
        // Gabungkan tabel admin dengan tabel ukm untuk mengetahui UKM mana yang dikelola setiap admin
        $query = "
            SELECT 
                a.id, a.username, a.nama, a.email, a.role, a.created_at,
                GROUP_CONCAT(u.nama_ukm SEPARATOR ', ') AS ukm_dikelola
            FROM admin a
            LEFT JOIN ukm u ON a.id = u.admin_id
            GROUP BY a.id
            ORDER BY a.id DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Gagal memuat data admin.";
    }
}

// Tampilkan notifikasi status
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success_add') { $message = "Admin baru berhasil ditambahkan."; } 
    elseif ($_GET['status'] === 'success_edit') { $message = "Data admin berhasil diperbarui."; }
    elseif ($_GET['status'] === 'success_delete') { $message = "Admin berhasil dihapus."; }
    // --- START REVISI: Tambahkan status baru ---
    elseif ($_GET['status'] === 'success_edit_with_warning') { $message = "Data admin berhasil diperbarui. Namun, ada peringatan terkait pendelegasian UKM."; }
    // --- END REVISI ---
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Admin - Superadmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Gaya CSS disesuaikan sedikit untuk konsistensi */
        body {
            background: #f5f6fa;
        }
        .sidebar {
            height: 100vh;
            background: #2c3e50;
            padding-top: 20px;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        .sidebar a {
            padding: 12px;
            display: block;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #1abc9c;
            border-radius: 5px;
        }
        .main-content {
            padding: 0;
        }
        .header {
            background: linear-gradient(135deg, #2980b9, #6dd5fa);
            padding: 25px;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-form {
             /* Menggunakan class card standar */
             box-shadow: 0 5px 20px rgba(0,0,0,0.1);
             border-radius: 15px;
        }
        .table-responsive {
            background-color: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <div class="col-2 sidebar">
            <h4 class="text-center mb-4">
                <i class="fas fa-crown"></i> Super Admin
            </h4>

            <a href="dashboard.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="kelola_admin.php" class="active">
                <i class="fas fa-users-cog"></i> Kelola Admin
            </a>
            <a href="kelola_mahasiswa.php" class="active"><i class="fas fa-user-graduate"></i> Kelola Mahasiswa</a>
            <a href="kelola_ukm.php">
                <i class="fas fa-sitemap"></i> Kelola UKM
            </a>
            <a href="../admin/dashboard.php">
                <i class="fas fa-user-shield"></i> Mode Admin
            </a>
            <a href="../auth/logout.php" class="text-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <div class="col-10 offset-2 p-4 main-content">

            <div class="header mb-4 rounded-3">
                <h3 class="mb-0">Kelola Administrator</h3>
                <p class="text-light">Tambah, Edit, dan Hapus data administrator sistem.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($action === 'tambah' || $action === 'edit'): 
                $is_edit = $action === 'edit';
                $form_title = $is_edit ? "Edit Admin: " . htmlspecialchars($edit_admin_data['nama'] ?? 'N/A') : "Tambah Admin Baru";
                $submit_text = $is_edit ? "Perbarui Admin" : "Simpan Admin";
                
                $display_data = $_POST ? $_POST : ($is_edit ? $edit_admin_data : []);
            ?>
                <div class="card p-4 mb-4 card-form">
                    <h4 class="card-title"><?php echo $form_title; ?></h4>
                    <hr>
                    <form method="POST" action="kelola_admin.php?action=<?php echo $action; ?>">
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($edit_admin_data['id']); ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username:</label>
                                    <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($display_data['username'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role:</label>
                                    <select name="role" class="form-select" required>
                                        <option value="admin" <?php echo (($display_data['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>Admin Biasa</option>
                                        <option value="superadmin" <?php echo (($display_data['role'] ?? '') == 'superadmin') ? 'selected' : ''; ?>>Superadmin</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Lengkap:</label>
                            <input type="text" name="nama" class="form-control" required value="<?php echo htmlspecialchars($display_data['nama'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($display_data['email'] ?? ''); ?>">
                        </div>

                        <?php if ($is_edit): ?>
                        <div class="mb-4 p-3 border rounded bg-light">
                            <h6 class="mb-3"><i class="fas fa-handshake me-1 text-primary"></i> Delegasikan UKM ke Admin Ini</h6>
                            <div class="mb-3">
                                <label for="ukm_id_to_assign" class="form-label">Tugaskan UKM:</label>
                                <select name="ukm_id_to_assign" class="form-select">
                                    <option value="0">-- Pilih UKM untuk Ditugaskan (Opsional) --</option>
                                    <?php foreach ($ukm_list as $ukm): ?>
                                        <option value="<?php echo $ukm['id']; ?>"
                                            <?php echo ($ukm['admin_id'] == ($edit_admin_data['id'] ?? 0)) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ukm['nama_ukm']); ?> 
                                            (Pemilik Saat Ini: <?php echo htmlspecialchars($ukm['admin_owner_name'] ?? 'Tidak Ada'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Memilih UKM di sini akan mengubah pemilik UKM tersebut menjadi **<?php echo htmlspecialchars($edit_admin_data['nama'] ?? 'Admin Ini'); ?>**.</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <h5 class="mt-4"><?php echo $is_edit ? 'Ganti Password (Kosongkan jika tidak diubah)' : 'Password Baru'; ?></h5>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password (min 6 char):</label>
                            <input type="password" name="password" class="form-control" <?php echo !$is_edit ? 'required' : ''; ?>>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Konfirmasi Password:</label>
                            <input type="password" name="password_confirm" class="form-control" <?php echo !$is_edit ? 'required' : ''; ?>>
                        </div>
                        
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?php echo $submit_text; ?></button>
                        <a href="kelola_admin.php" class="btn btn-secondary">Batal</a>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($action === 'view'): ?>
                <div class="table-responsive">
                    <h4 class="mb-3">Daftar Administrator</h4>
                    <p>
                        <a href="kelola_admin.php?action=tambah" class="btn btn-primary mb-3">
                            <i class="fas fa-plus"></i> Tambah Admin Baru
                        </a>
                    </p>
                    
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>UKM Dikelola</th> <th>Tgl Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($admins)): ?>
                                <?php foreach ($admins as $admin): 
                                    $ukm_dikelola_display = $admin['ukm_dikelola'] ? htmlspecialchars($admin['ukm_dikelola']) : '<span class="text-muted">N/A</span>';
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin['id']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td><span class="badge bg-<?php echo $admin['role'] == 'superadmin' ? 'info' : 'secondary'; ?>"><?php echo htmlspecialchars(ucfirst($admin['role'])); ?></span></td>
                                        <td><?php echo $ukm_dikelola_display; ?></td> <td><?php echo htmlspecialchars($admin['created_at']); ?></td>
                                        <td>
                                            <a href="kelola_admin.php?action=edit&id=<?php echo htmlspecialchars($admin['id']); ?>" class="btn btn-sm btn-warning me-1">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                                <a href="kelola_admin.php?action=delete&id=<?php echo htmlspecialchars($admin['id']); ?>" 
                                                onclick="return confirm('Yakin ingin menghapus administrator <?php echo htmlspecialchars($admin['username']); ?>? UKM yang dikelola admin ini akan dilepaskan (dibuat N/A).');" 
                                                class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash-alt"></i> Hapus
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border border-secondary">Akun Aktif Anda</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">Belum ada data administrator.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>