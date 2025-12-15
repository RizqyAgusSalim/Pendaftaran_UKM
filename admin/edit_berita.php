<?php
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Hanya admin UKM (bukan superadmin)
if ($_SESSION['user_role'] === 'superadmin') {
    showAlert('Akses ditolak.', 'danger');
    redirect('dashboard.php');
}

if (!isset($_SESSION['ukm_id'])) {
    redirect('../auth/logout.php');
}

$ukm_id = (int)$_SESSION['ukm_id'];
$berita = null;
$error = '';

// Ambil ID berita
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    showAlert('Berita tidak ditemukan.', 'danger');
    redirect('kelola_berita.php');
}

$berita_id = (int)$_GET['id'];

// Cek apakah berita milik UKM ini
$stmt_check = $db->prepare("SELECT * FROM berita WHERE id = ? AND ukm_id = ?");
$stmt_check->execute([$berita_id, $ukm_id]);
$berita = $stmt_check->fetch(PDO::FETCH_ASSOC);

if (!$berita) {
    showAlert('Akses ditolak: Berita tidak ditemukan atau bukan milik UKM Anda.', 'danger');
    redirect('kelola_berita.php');
}

// Proses update jika form dikirim
if ($_POST) {
    $judul = sanitize($_POST['judul']);
    $konten = $_POST['konten'];
    $penulis = sanitize($_POST['penulis']);
    $status = $_POST['status'] ?? 'draft';

    if (empty($judul) || empty($konten)) {
        $error = 'Judul dan konten wajib diisi.';
    } else {
        $gambar_baru = $berita['gambar']; // pertahankan gambar lama jika tidak diubah

        // Jika ada upload gambar baru
        if (!empty($_FILES['gambar']['name'])) {
            // Hapus gambar lama
            if ($berita['gambar'] && file_exists('../uploads/' . $berita['gambar'])) {
                unlink('../uploads/' . $berita['gambar']);
            }

            // Validasi & upload gambar baru
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_types)) {
                $error = 'Hanya file JPG, JPEG, PNG, GIF yang diizinkan.';
            } else {
                $filename = 'berita_' . uniqid() . '.' . $file_ext;
                $upload_path = '../uploads/' . $filename;
                
                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                    $gambar_baru = $filename;
                } else {
                    $error = 'Gagal mengunggah gambar.';
                }
            }
        }

        // Opsi: hapus gambar jika centang "hapus gambar"
        if (isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] == '1') {
            if ($berita['gambar'] && file_exists('../uploads/' . $berita['gambar'])) {
                unlink('../uploads/' . $berita['gambar']);
            }
            $gambar_baru = '';
        }

        if (!isset($error)) {
            $stmt_update = $db->prepare("
                UPDATE berita 
                SET judul = ?, konten = ?, gambar = ?, penulis = ?, status = ? 
                WHERE id = ? AND ukm_id = ?
            ");
            $updated = $stmt_update->execute([$judul, $konten, $gambar_baru, $penulis, $status, $berita_id, $ukm_id]);

            if ($updated) {
                showAlert('Berita berhasil diperbarui!', 'success');
                redirect('kelola_berita.php');
            } else {
                $error = 'Gagal memperbarui berita.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Berita - Admin UKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2><i class="fas fa-edit"></i> Edit Berita</h2>
        <hr>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="judul" class="form-label">Judul Berita</label>
                <input type="text" class="form-control" id="judul" name="judul" 
                       value="<?= htmlspecialchars($berita['judul']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="konten" class="form-label">Konten Berita</label>
                <textarea class="form-control" id="konten" name="konten" rows="8" required><?= htmlspecialchars($berita['konten']) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="penulis" class="form-label">Penulis</label>
                <input type="text" class="form-control" id="penulis" name="penulis" 
                       value="<?= htmlspecialchars($berita['penulis']) ?>" required>
            </div>

            <!-- Preview Gambar Saat Ini -->
            <div class="mb-3">
                <label class="form-label">Gambar Saat Ini</label>
                <?php if (!empty($berita['gambar']) && file_exists('../uploads/' . $berita['gambar'])): ?>
                    <div class="mb-2">
                        <img src="../uploads/<?= htmlspecialchars($berita['gambar']) ?>" 
                             alt="Gambar Berita" class="img-thumbnail" style="max-height: 200px;">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="hapus_gambar" name="hapus_gambar" value="1">
                        <label class="form-check-label" for="hapus_gambar">
                            Hapus gambar ini
                        </label>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Tidak ada gambar.</p>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="gambar" class="form-label">Ganti Gambar (Opsional)</label>
                <input type="file" class="form-control" id="gambar" name="gambar" accept="image/*">
                <div class="form-text">Format: JPG, JPEG, PNG, GIF (max 2MB direkomendasikan)</div>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="draft" <?= $berita['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $berita['status'] === 'published' ? 'selected' : '' ?>>Dipublikasikan</option>
                </select>
            </div>

            <div class="d-grid gap-2 d-md-block">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <a href="kelola_berita.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Batal
                </a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>