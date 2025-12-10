<?php
// edit_profil.php - Versi Diperbarui dengan Preview Profil Saat Ini
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isMahasiswa()) {
    header('Location: ../auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$mahasiswa_id = $_SESSION['user_id'];

// Proses update jika form dikirim
if ($_POST) {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $jurusan = trim($_POST['jurusan'] ?? '');
    $angkatan = trim($_POST['angkatan'] ?? '');

    // Validasi minimal
    if (empty($nama) || empty($email)) {
        $error = "Nama dan Email wajib diisi.";
    } else {
        // Proses upload foto jika ada
        $foto_path = null;
        if (!empty($_FILES['foto']['name'])) {
            $foto = $_FILES['foto'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed) && $foto['error'] === UPLOAD_ERR_OK) {
                $foto_path = 'profil_' . $mahasiswa_id . '_' . time() . '.' . $ext;
                $target = '../uploads/' . $foto_path;
                if (!move_uploaded_file($foto['tmp_name'], $target)) {
                    $error = "Gagal mengupload foto.";
                }
            } else {
                $error = "Format foto tidak didukung (jpg, jpeg, png, gif).";
            }
        }

        if (!isset($error)) {
            // Siapkan query
            $fields = "nama = ?, email = ?, no_telepon = ?, alamat = ?, jurusan = ?, angkatan = ?";
            $params = [$nama, $email, $no_telepon, $alamat, $jurusan, $angkatan];
            
            if ($foto_path) {
                $fields .= ", foto = ?";
                $params[] = $foto_path;
            }
            $params[] = $mahasiswa_id;

            $stmt = $db->prepare("UPDATE mahasiswa SET $fields WHERE id = ?");
            if ($stmt->execute($params)) {
                // Update session
                $_SESSION['nama'] = $nama;
                $_SESSION['flash'] = "Profil berhasil diperbarui.";
                header('Location: edit_profil.php');
                exit;
            } else {
                $error = "Gagal memperbarui profil.";
            }
        }
    }
}

// Ambil data mahasiswa saat ini
$stmt = $db->prepare("SELECT * FROM mahasiswa WHERE id = ?");
$stmt->execute([$mahasiswa_id]);
$mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mahasiswa) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil - Dashboard Mahasiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .preview-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }
        .preview-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #667eea;
        }
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-cog me-2"></i>Edit Profil</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>

        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['flash'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ✅ PREVIEW PROFIL SAAT INI -->
        <div class="preview-card">
            <h5 class="mb-3"><i class="fas fa-eye me-2"></i>Profil Saat Ini</h5>
            <div class="row align-items-center">
                <div class="col-md-2 text-center mb-3 mb-md-0">
                    <?php
                        $foto_url = !empty($mahasiswa['foto']) 
                            ? '../uploads/' . htmlspecialchars($mahasiswa['foto']) 
                            : 'https://via.placeholder.com/100?text=No+Photo';
                    ?>
                    <img src="<?= $foto_url ?>" alt="Foto Profil" class="preview-photo">
                </div>
                <div class="col-md-10">
                    <p><strong>Nama:</strong> <?= htmlspecialchars($mahasiswa['nama']) ?></p>
                    <p><strong>NIM:</strong> <?= htmlspecialchars($mahasiswa['nim']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($mahasiswa['email']) ?></p>
                    <p><strong>No. Telepon:</strong> <?= htmlspecialchars($mahasiswa['no_telepon'] ?? '—') ?></p>
                    <p><strong>Jurusan:</strong> <?= htmlspecialchars($mahasiswa['jurusan'] ?? '—') ?></p>
                    <p><strong>Angkatan:</strong> <?= htmlspecialchars($mahasiswa['angkatan'] ?? '—') ?></p>
                    <p><strong>Alamat:</strong> <?= htmlspecialchars($mahasiswa['alamat'] ?? '—') ?></p>
                </div>
            </div>
        </div>

        <!-- ✅ FORM EDIT -->
        <div class="form-section">
            <h5 class="mb-4"><i class="fas fa-edit me-2"></i>Perbarui Profil Anda</h5>
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($mahasiswa['nama']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NIM (Tidak Bisa Diubah)</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($mahasiswa['nim']) ?>" disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($mahasiswa['email']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="no_telepon" class="form-control" value="<?= htmlspecialchars($mahasiswa['no_telepon'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jurusan</label>
                        <input type="text" name="jurusan" class="form-control" value="<?= htmlspecialchars($mahasiswa['jurusan'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Angkatan</label>
                        <input type="number" name="angkatan" class="form-control" min="2000" max="<?= date('Y') ?>" value="<?= htmlspecialchars($mahasiswa['angkatan'] ?? '') ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($mahasiswa['alamat'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Foto Profil (opsional)</label>
                        <input type="file" name="foto" class="form-control" accept="image/*">
                        <div class="form-text">Format: JPG, JPEG, PNG, GIF (Max 2MB)</div>
                    </div>
                </div>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="dashboard.php" class="btn btn-secondary me-md-2">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>