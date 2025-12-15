<?php
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Hanya admin UKM atau superadmin yang bisa akses
if ($_SESSION['user_role'] !== 'superadmin' && !isset($_SESSION['ukm_id'])) {
    showAlert('Akses ditolak: Anda bukan admin UKM.', 'danger');
    redirect('dashboard.php');
}

$ukm_id = $_SESSION['ukm_id'] ?? null;

if ($_POST) {
    $judul = sanitize($_POST['judul']);
    $konten = $_POST['konten'];
    $penulis = sanitize($_POST['penulis']);
    $status = $_POST['status'];

    // Validasi wajib
    if (empty($judul) || empty($konten)) {
        $error = 'Judul dan konten wajib diisi.';
    } else {
        // Upload gambar jika ada
        $gambar = '';
        if (!empty($_FILES['gambar']['name'])) {
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_types)) {
                $error = 'Hanya file JPG, JPEG, PNG, GIF yang diizinkan.';
            } else {
                $filename = uniqid() . '.' . $file_ext;
                $upload_path = '../uploads/' . $filename;
                
                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                    $gambar = $filename;
                } else {
                    $error = 'Gagal mengunggah gambar.';
                }
            }
        }

        if (!isset($error)) {
            // Siapkan query
            $query = "INSERT INTO berita (ukm_id, judul, konten, gambar, penulis, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$ukm_id, $judul, $konten, $gambar, $penulis, $status]);

            showAlert('Berita berhasil disimpan!', 'success');
            redirect('kelola_berita.php');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Berita - Admin UKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2><i class="fas fa-newspaper"></i> Tambah Berita Baru</h2>
        <hr>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="judul" class="form-label">Judul Berita</label>
                <input type="text" class="form-control" id="judul" name="judul" required>
            </div>

            <div class="mb-3">
                <label for="konten" class="form-label">Konten Berita</label>
                <textarea class="form-control" id="konten" name="konten" rows="8" required></textarea>
            </div>

            <div class="mb-3">
                <label for="penulis" class="form-label">Penulis</label>
                <input type="text" class="form-control" id="penulis" name="penulis" value="<?= htmlspecialchars($_SESSION['nama']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="gambar" class="form-label">Gambar (Opsional)</label>
                <input type="file" class="form-control" id="gambar" name="gambar" accept="image/*">
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="draft">Draft</option>
                    <option value="published" selected>Dipublikasikan</option>
                </select>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Berita
                </button>
                <a href="kelola_berita.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>