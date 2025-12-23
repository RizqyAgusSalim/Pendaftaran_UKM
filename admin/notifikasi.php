<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Koneksi database gagal.");
}

$user_id = $_SESSION['user_id'];

// Tandai semua notifikasi sebagai dibaca
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $stmt = $db->prepare("UPDATE notifikasi SET dibaca = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    redirect('notifikasi.php');
}

// Ambil semua notifikasi
$stmt = $db->prepare("
    SELECT id, pesan, url, dibaca, created_at 
    FROM notifikasi 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$notifikasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set judul halaman
$page_title = "Notifikasi - Admin";
?>

<?php include 'layout.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-bell me-2"></i>Notifikasi</h2>
    <?php if (!empty($notifikasi_list)): ?>
        <a href="?action=mark_all_read" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-check me-1"></i> Tandai Semua Sudah Dibaca
        </a>
    <?php endif; ?>
</div>

<?php displayAlert(); ?>

<?php if (empty($notifikasi_list)): ?>
    <div class="text-center py-5">
        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">Tidak ada notifikasi</h5>
        <p class="text-muted">Semua notifikasi Anda sudah dibaca.</p>
    </div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($notifikasi_list as $n): ?>
            <?php
            // Tandai sebagai dibaca saat diklik (redirect ke URL)
            $url = $n['url'] ? $n['url'] : '#';
            if (!$n['dibaca']) {
                // Update status dibaca saat halaman notifikasi ini dimuat (opsional)
                // Atau biarkan hanya saat diklik — lebih baik lakukan saat klik via JS (lihat bawah)
            }
            ?>
            <a href="<?= htmlspecialchars($url) ?>" 
               class="list-group-item list-group-item-action <?= $n['dibaca'] ? 'text-muted' : 'fw-bold' ?>"
               style="border-left: 4px solid <?= $n['dibaca'] ? '#dee2e6' : '#0d6efd' ?>;"
               onclick="markAsRead(<?= $n['id'] ?>)">
                <div class="d-flex w-100 justify-content-between">
                    <div><?= htmlspecialchars($n['pesan']) ?></div>
                    <small class="text-muted"><?= date('d M H:i', strtotime($n['created_at'])) ?></small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function markAsRead(notifId) {
    fetch('notifikasi_read.php?id=' + notifId)
        .then(response => {
            if (!response.ok) console.error('Gagal tandai dibaca');
        })
        .catch(err => console.error(err));
    // Biarkan redirect tetap terjadi
}
</script>

<?php include 'footer.php'; ?>