<?php
// superadmin/kelola_ukm.php (Revisi 3: DIPERBAIKI - Hapus kolom 'ketua' yang tidak ada)

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
$edit_ukm_data = null; // Untuk menyimpan data UKM yang sedang diedit

// Ambil daftar semua Admin dan Kategori
$admin_list = [];
$kategori_list = [];
$kategori_error = ''; // Inisialisasi variabel error kategori

try {
    // 1. Ambil daftar Admin
    $query_admin = "SELECT id, nama FROM admin ORDER BY nama ASC";
    $stmt_admin = $db->prepare($query_admin);
    $stmt_admin->execute();
    $admin_list = $stmt_admin->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ambil daftar Kategori (PERBAIKAN: Menggunakan tabel 'kategori_ukm')
    try {
        $query_kategori = "SELECT id, nama_kategori FROM kategori_ukm ORDER BY nama_kategori ASC";
        $stmt_kategori = $db->prepare($query_kategori);
        $stmt_kategori->execute();
        $kategori_list = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Jika tabel kategori_ukm masih tidak ditemukan atau error lainnya
        $kategori_error = "PERINGATAN: Gagal memuat tabel 'kategori_ukm'. Pastikan nama tabel sudah benar dan data ada.";
    }

} catch (PDOException $e) {
    $error = "Gagal memuat daftar Admin: " . $e->getMessage();
}


// --- PROSES DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $ukm_id = intval($_GET['id']);
    try {
        $query = "DELETE FROM ukm WHERE id = ?";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$ukm_id])) {
            header('Location: kelola_ukm.php?status=success_delete'); 
            exit;
        } else { $error = "Gagal menghapus UKM."; }
    } catch (PDOException $e) { $error = "Terjadi error database: " . $e->getMessage(); }
    $action = 'view';
}

// --- PROSES EDIT (Ambil Data Lama) ---
if ($action === 'edit' && isset($_GET['id'])) {
    $ukm_id = intval($_GET['id']);
    $query = "SELECT * FROM ukm WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$ukm_id]);
    $edit_ukm_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$edit_ukm_data) {
        $error = "UKM tidak ditemukan.";
        $action = 'view';
    }
}

// --- PROSES POST (TAMBAH & EDIT) ---
if ($_POST) {
    // 1. Ambil Input UKM (Termasuk kolom baru)
    $nama_ukm = sanitize($_POST['nama_ukm']);
    $deskripsi = sanitize($_POST['deskripsi']);
    
    // Kolom Baru yang diambil dari form
    $kategori_id = isset($_POST['kategori_id']) ? intval($_POST['kategori_id']) : null; 
    $ketua_umum = sanitize($_POST['ketua_umum']);
    $email_ukm = sanitize($_POST['email_ukm']);
    $no_telepon = sanitize($_POST['no_telepon']);
    $status = sanitize($_POST['status']); 

    // Kolom lain dari DB yang di-set default/NULL pada INSERT jika tidak diisi
    $logo = null;
    $alamat_sekretariat = null;
    $visi = null;
    $misi = null;
    $program_kerja = null;
    $syarat_pendaftaran = null;
    $max_anggota = 100;
    $biaya_pendaftaran = 0.00;
    // $ketua = null;  // ❌ DIHAPUS - TIDAK ADA DI TABEL
    $kontak = null;
    
    $is_edit_mode = isset($_POST['ukm_id']) && $_POST['ukm_id'] > 0;
    $current_id = $is_edit_mode ? intval($_POST['ukm_id']) : 0;
    $action_after_post = $is_edit_mode ? 'edit' : 'tambah'; 
    $success = false;
    $new_admin_id = null;
    $db->beginTransaction(); 

    try {
        if (!$is_edit_mode) { 
            // =========================================================
            // --- MODE INSERT/TAMBAH (BUAT UKM BARU + ADMIN BARU) ---
            // =========================================================
            
            // 1A. Ambil Input Admin Baru
            $username = sanitize($_POST['username']);
            $nama = sanitize($_POST['nama_admin']);
            $email_admin = sanitize($_POST['email_admin']); 
            $password_input = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            
            // 1B. Validasi Admin Baru
            if (empty($nama) || empty($username) || empty($email_admin) || empty($password_input) || empty($password_confirm)) {
                $error = 'Semua kolom UKM dan detail Admin wajib diisi.';
            } elseif ($password_input !== $password_confirm) {
                $error = 'Konfirmasi password Admin tidak cocok.';
            } elseif (strlen($password_input) < 6) {
                $error = 'Password Admin minimal 6 karakter.';
            }

            if (!$error) {
                // 1C. Cek Duplikasi Username
                $query_check = "SELECT id FROM admin WHERE username = ?";
                $stmt_check = $db->prepare($query_check);
                $stmt_check->execute([$username]);
                
                if ($stmt_check->rowCount() > 0) {
                    $error = 'Username Admin sudah digunakan.';
                }
            }

            if (!$error) {
                // 1D. INSERT ADMIN BARU 
                $hashed_password = password_hash($password_input, PASSWORD_BCRYPT);
                $created_at = date('Y-m-d H:i:s');
                $query_admin_insert = "INSERT INTO admin (username, password, nama, email, created_at, role) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_admin_insert = $db->prepare($query_admin_insert);
                
                if (!$stmt_admin_insert->execute([$username, $hashed_password, $nama, $email_admin, $created_at, 'admin'])) {
                    $error = 'Gagal menambahkan Admin baru.';
                } else {
                    $new_admin_id = $db->lastInsertId();
                    
                    // 1E. INSERT UKM BARU (TANPA 'ketua')
                    $query_ukm_insert = "
                        INSERT INTO ukm (
                            nama_ukm, deskripsi, kategori_id, ketua_umum, email, no_telepon, status, admin_id,
                            logo, alamat_sekretariat, visi, misi, program_kerja, syarat_pendaftaran, max_anggota, biaya_pendaftaran, kontak
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?, ?
                        )";
                    $stmt_ukm_insert = $db->prepare($query_ukm_insert);
                    
                    // Total 17 parameters (1 kolom dihapus: 'ketua')
                    $params = [
                        $nama_ukm, $deskripsi, $kategori_id, $ketua_umum, $email_ukm, $no_telepon, $status, $new_admin_id,
                        $logo, $alamat_sekretariat, $visi, $misi, $program_kerja, $syarat_pendaftaran, $max_anggota, $biaya_pendaftaran, $kontak
                    ];

                    if (!$stmt_ukm_insert->execute($params)) {
                        $error = 'Gagal menambahkan UKM. Pastikan kategori ID valid.';
                    } else {
                        $success = true;
                    }
                }
            }
            
        } else { 
            // =========================================================
            // --- MODE UPDATE / EDIT (Update UKM) ---
            // =========================================================
            $admin_id_edit = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : null;
            
            // Query UPDATE yang lebih lengkap
            $query = "
                UPDATE ukm SET 
                    nama_ukm = ?, deskripsi = ?, kategori_id = ?, ketua_umum = ?, 
                    email = ?, no_telepon = ?, status = ?, admin_id = ? 
                WHERE id = ?";
                
            $stmt = $db->prepare($query);
            $update_params = [
                $nama_ukm, $deskripsi, $kategori_id, $ketua_umum, $email_ukm, $no_telepon, $status, $admin_id_edit, $current_id
            ];
            
            if ($stmt->execute($update_params)) {
                $success = true;
            } else { $error = 'Gagal memperbarui UKM.'; }
        }

        // --- MANAJEMEN TRANSAKSI ---
        if ($success && !$error) {
            $db->commit();
            header('Location: kelola_ukm.php?status=' . ($is_edit_mode ? 'success_edit' : 'success_add'));
            exit;
        } else {
            $db->rollBack();
        }

    } catch (PDOException $e) { 
        $db->rollBack();
        if ($e->getCode() === '23000') { 
            $error = 'Nama UKM, Username Admin, atau Kategori ID tidak valid (Duplikasi/Foreign Key). Detail: ' . $e->getMessage();
        } else {
            $error = "Terjadi error database: " . $e->getMessage(); 
        }
    }
    
    if ($error && $action_after_post === 'edit') {
        $action = $action_after_post; 
        if ($is_edit_mode) {
            $edit_ukm_data = array_merge($edit_ukm_data ?? [], $_POST);
        }
    } elseif ($error && $action_after_post === 'tambah') {
        $action = $action_after_post;
    }
}

// --- AMBIL DATA UKM (Untuk mode VIEW) ---
$ukms = [];
if ($action === 'view') {
    try {
        $query = "
            SELECT 
                u.id, u.nama_ukm, u.deskripsi, u.created_at, u.status,
                a.nama AS admin_owner_name,
                k.nama_kategori
            FROM ukm u
            LEFT JOIN admin a ON u.admin_id = a.id
            LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
            ORDER BY u.nama_ukm ASC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $ukms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Gagal memuat data UKM. Detail: " . $e->getMessage();
    }
}

// Tampilkan notifikasi status
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success_add') { $message = "UKM baru dan Admin penanggung jawab berhasil ditambahkan."; } 
    elseif ($_GET['status'] === 'success_edit') { $message = "Data UKM berhasil diperbarui."; }
    elseif ($_GET['status'] === 'success_delete') { $message = "UKM berhasil dihapus."; }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola UKM - Superadmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6fa; }
        .sidebar { height: 100vh; background: #2c3e50; padding-top: 20px; color: white; position: fixed; left: 0; top: 0; z-index: 1000; }
        .sidebar a { padding: 12px; display: block; color: white; text-decoration: none; font-weight: 500; }
        .sidebar a:hover, .sidebar a.active { background: #1abc9c; border-radius: 5px; }
        .main-content { padding: 0; margin-left: 16.66%; } /* offset-2 ≈ 16.66% */
        .header { background: linear-gradient(135deg, #2980b9, #6dd5fa); padding: 25px; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card-form { box-shadow: 0 5px 20px rgba(0,0,0,0.1); border-radius: 15px; }
        .table-responsive { background-color: white; padding: 15px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <div class="col-2 sidebar">
            <h4 class="text-center mb-4"><i class="fas fa-crown"></i> Super Admin</h4>
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="kelola_admin.php"><i class="fas fa-users-cog"></i> Kelola Admin</a>
            <a href="kelola_mahasiswa.php"><i class="fas fa-user-graduate"></i> Kelola Mahasiswa</a>
            <a href="kelola_ukm.php" class="active"><i class="fas fa-sitemap"></i> Kelola UKM</a>
            <a href="../admin/dashboard.php"><i class="fas fa-user-shield"></i> Mode Admin</a>
            <a href="../auth/logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="col-10 p-4 main-content">

            <div class="header mb-4 rounded-3">
                <h3 class="mb-0">Kelola Unit Kegiatan Mahasiswa (UKM)</h3>
                <p class="text-light">Tambah, Edit, dan Hapus data UKM (Struktur Lengkap).</p>
            </div>

            <?php if (!empty($kategori_error)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($kategori_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

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
                $form_title = $is_edit ? "Edit UKM: " . htmlspecialchars($edit_ukm_data['nama_ukm'] ?? 'N/A') : "Tambah UKM Baru & Buat Admin PJ";
                $submit_text = $is_edit ? "Perbarui UKM" : "Simpan UKM & Buat Admin";
                
                $display_data = $_POST ? $_POST : ($is_edit ? $edit_ukm_data : []);
            ?>
                <div class="card p-4 mb-4 card-form">
                    <h4 class="card-title"><?php echo $form_title; ?></h4>
                    <hr>
                    <form method="POST" action="kelola_ukm.php?action=<?php echo $action; ?>">
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="ukm_id" value="<?php echo htmlspecialchars($edit_ukm_data['id']); ?>">
                        <?php endif; ?>

                        <h5 class="mb-3 text-primary"><i class="fas fa-sitemap me-1"></i> Detail UKM</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama_ukm" class="form-label">Nama UKM:</label>
                                <input type="text" name="nama_ukm" class="form-control" required value="<?php echo htmlspecialchars($display_data['nama_ukm'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="kategori_id" class="form-label">Kategori:</label>
                                <select name="kategori_id" class="form-select" <?php echo empty($kategori_list) ? '' : ''; ?>>
                                    <option value="" selected>
                                        <?php echo empty($kategori_list) ? '-- ERROR: Gagal Muat Kategori --' : '-- Pilih Kategori --'; ?>
                                    </option>
                                    <?php foreach ($kategori_list as $kategori): ?>
                                        <option value="<?php echo $kategori['id']; ?>"
                                            <?php echo (isset($display_data['kategori_id']) && $kategori['id'] == $display_data['kategori_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi Singkat:</label>
                            <textarea name="deskripsi" class="form-control" rows="3" required><?php echo htmlspecialchars($display_data['deskripsi'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ketua_umum" class="form-label">Nama Ketua Umum:</label>
                                <input type="text" name="ketua_umum" class="form-control" value="<?php echo htmlspecialchars($display_data['ketua_umum'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status UKM:</label>
                                <select name="status" class="form-select" required>
                                    <option value="aktif" <?php echo (isset($display_data['status']) && $display_data['status'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="nonaktif" <?php echo (isset($display_data['status']) && $display_data['status'] == 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email_ukm" class="form-label">Email UKM:</label>
                                <input type="email" name="email_ukm" class="form-control" value="<?php echo htmlspecialchars($display_data['email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="no_telepon" class="form-label">Nomor Telepon UKM:</label>
                                <input type="text" name="no_telepon" class="form-control" value="<?php echo htmlspecialchars($display_data['no_telepon'] ?? ''); ?>">
                            </div>
                        </div>

                        <?php if ($is_edit): ?>
                            <h5 class="mt-4 mb-3 text-warning"><i class="fas fa-handshake me-1"></i> Pilih Admin Penanggung Jawab</h5>
                            <div class="mb-4">
                                <label for="admin_id" class="form-label">Admin Penanggung Jawab:</label>
                                <select name="admin_id" class="form-select">
                                    <option value="" selected>-- Belum Ada Admin (N/A) --</option>
                                    <?php foreach ($admin_list as $admin): ?>
                                        <option value="<?php echo $admin['id']; ?>"
                                            <?php 
                                            $selected_id = $display_data['admin_id'] ?? null;
                                            echo ($admin['id'] == $selected_id) ? 'selected' : ''; 
                                            ?>>
                                            <?php echo htmlspecialchars($admin['nama']); ?> (ID: <?php echo $admin['id']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <h5 class="mt-4 mb-3 text-success"><i class="fas fa-user-plus me-1"></i> Buat Admin Penanggung Jawab Baru</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nama_admin" class="form-label">Nama Admin:</label>
                                    <input type="text" name="nama_admin" class="form-control" required value="<?php echo htmlspecialchars($_POST['nama_admin'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username (Login):</label>
                                    <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email_admin" class="form-label">Email Admin:</label>
                                <input type="email" name="email_admin" class="form-control" required value="<?php echo htmlspecialchars($_POST['email_admin'] ?? ''); ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password (min 6 char):</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirm" class="form-label">Konfirmasi Password:</label>
                                    <input type="password" name="password_confirm" class="form-control" required>
                                </div>
                            </div>

                        <?php endif; ?>

                        <button type="submit" class="btn btn-success mt-3"><i class="fas fa-save"></i> <?php echo $submit_text; ?></button>
                        <a href="kelola_ukm.php" class="btn btn-secondary mt-3">Batal</a>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($action === 'view'): ?>
                <div class="table-responsive">
                    <h4 class="mb-3">Daftar UKM yang Terdaftar</h4>
                    <p>
                        <a href="kelola_ukm.php?action=tambah" class="btn btn-primary mb-3">
                            <i class="fas fa-plus"></i> Tambah UKM Baru & Buat Admin PJ
                        </a>
                    </p>
                    
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nama UKM</th>
                                <th>Kategori</th>
                                <th>Status</th>
                                <th>Admin PJ</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ukms)): ?>
                                <?php foreach ($ukms as $ukm): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ukm['id']); ?></td>
                                        <td><?php echo htmlspecialchars($ukm['nama_ukm']); ?></td>
                                        <td><?php echo htmlspecialchars($ukm['nama_kategori'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($ukm['status'] === 'aktif' ? 'success' : 'danger'); ?>">
                                                <?php echo htmlspecialchars(ucwords($ukm['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($ukm['admin_owner_name']): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($ukm['admin_owner_name']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Belum Ditugaskan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="kelola_ukm.php?action=edit&id=<?php echo htmlspecialchars($ukm['id']); ?>" class="btn btn-sm btn-warning me-1">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="kelola_ukm.php?action=delete&id=<?php echo htmlspecialchars($ukm['id']); ?>" 
                                            onclick="return confirm('Yakin ingin menghapus UKM <?php echo htmlspecialchars($ukm['nama_ukm']); ?>?');" 
                                            class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Belum ada data UKM yang terdaftar.</td>
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