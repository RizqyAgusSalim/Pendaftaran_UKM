<?php
// admin/laporan.php — VERSI DENGAN LAYOUT TERPADU
session_start(); // ← WAJIB DI AWAL
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("<div class='alert alert-danger'>Koneksi database gagal.</div>");
}

// Tentukan apakah ini admin UKM atau superadmin
$is_ukm_admin = isset($_SESSION['ukm_id']) && $_SESSION['ukm_id'] !== null;
$ukm_id = $is_ukm_admin ? (int)$_SESSION['ukm_id'] : null;

// ✅ AMBIL NAMA UKM UNTUK NAVBAR
$nama_ukm = "Laporan";
if ($is_ukm_admin) {
    $stmt_ukm = $db->prepare("SELECT nama_ukm FROM ukm WHERE id = ?");
    $stmt_ukm->execute([$ukm_id]);
    $nama_ukm = $stmt_ukm->fetchColumn() ?? "UKM Anda";
} elseif ($_SESSION['user_role'] === 'superadmin') {
    $nama_ukm = "Super Admin";
}

// Ambil data UKM untuk filter (jika superadmin)
$ukm_list = [];
if (!$is_ukm_admin) {
    $stmt_ukm = $db->query("SELECT id, nama_ukm FROM ukm ORDER BY nama_ukm");
    $ukm_list = $stmt_ukm->fetchAll(PDO::FETCH_ASSOC);
}

// Ambil filter dari URL
$selected_ukm = $_GET['ukm'] ?? null;
if ($is_ukm_admin) {
    $selected_ukm = $ukm_id;
}

// Bangun query dasar
$conditions = [];
$params = [];

if ($selected_ukm) {
    $conditions[] = "p.ukm_id = :ukm_id";
    $params[':ukm_id'] = (int)$selected_ukm;
} elseif ($is_ukm_admin) {
    $conditions[] = "p.ukm_id = :ukm_id";
    $params[':ukm_id'] = $ukm_id;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Query: Total pendaftaran
$stmt_total = $db->prepare("SELECT COUNT(*) FROM pendaftaran p $where_clause");
$stmt_total->execute($params);
$total_pendaftaran = $stmt_total->fetchColumn();

// Query: Pendaftaran per status
$stmt_status = $db->prepare("
    SELECT status, COUNT(*) as jumlah 
    FROM pendaftaran p 
    $where_clause 
    GROUP BY status
");
$stmt_status->execute($params);
$status_counts = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR);

// Query: Data pendaftaran lengkap
$stmt_data = $db->prepare("
    SELECT 
        m.nama, m.nim, m.email, m.jurusan,
        u.nama_ukm,
        p.status, p.created_at
    FROM pendaftaran p
    JOIN mahasiswa m ON p.mahasiswa_id = m.id
    JOIN ukm u ON p.ukm_id = u.id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT 100
");
$stmt_data->execute($params);
$pendaftaran_list = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

// =================================================================
// PROSES DOWNLOAD EXCEL
// =================================================================
if (isset($_GET['download']) && $_GET['download'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_pendaftaran_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Nama</th><th>NIM</th><th>Jurusan</th><th>UKM</th><th>Status</th><th>Tanggal Daftar</th></tr>";

    foreach ($pendaftaran_list as $p) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($p['nama']) . "</td>";
        echo "<td>" . htmlspecialchars($p['nim']) . "</td>";
        echo "<td>" . htmlspecialchars($p['jurusan']) . "</td>";
        echo "<td>" . htmlspecialchars($p['nama_ukm']) . "</td>";
        echo "<td>" . ucfirst($p['status']) . "</td>";
        echo "<td>" . formatTanggal($p['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// =================================================================
// PROSES DOWNLOAD PDF
// =================================================================
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';

    $pdf = new \TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 8, 'Laporan Pendaftaran UKM', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 6, 'Politeknik Negeri Lampung', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Tanggal: ' . date('d M Y'), 0, 1, 'C');
    $pdf->Ln(6);

    $w = array(40, 23, 37, 42, 18, 20);
    $pageWidth = $pdf->getPageWidth();
    $tableWidth = array_sum($w);
    $startX = ($pageWidth - $tableWidth) / 2;

    $pdf->SetX($startX);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetLineWidth(0.3);
    $pdf->Cell($w[0], 8, 'Nama', 1, 0, 'C', true);
    $pdf->Cell($w[1], 8, 'NIM', 1, 0, 'C', true);
    $pdf->Cell($w[2], 8, 'Jurusan', 1, 0, 'C', true);
    $pdf->Cell($w[3], 8, 'UKM', 1, 0, 'C', true);
    $pdf->Cell($w[4], 8, 'Status', 1, 0, 'C', true);
    $pdf->Cell($w[5], 8, 'Tanggal', 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 8);
    foreach ($pendaftaran_list as $index => $p) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
            $pdf->SetX($startX);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell($w[0], 8, 'Nama', 1, 0, 'C', true);
            $pdf->Cell($w[1], 8, 'NIM', 1, 0, 'C', true);
            $pdf->Cell($w[2], 8, 'Jurusan', 1, 0, 'C', true);
            $pdf->Cell($w[3], 8, 'UKM', 1, 0, 'C', true);
            $pdf->Cell($w[4], 8, 'Status', 1, 0, 'C', true);
            $pdf->Cell($w[5], 8, 'Tanggal', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 8);
        }

        $fill = ($index % 2 == 0) ? [255,255,255] : [248,248,248];
        $y = $pdf->GetY();
        $h = 7;

        $pdf->MultiCell($w[0], $h, htmlspecialchars($p['nama']), 1, 'L', true, 0, $startX, $y, true, 0, false, true, $h, 'M');
        $pdf->MultiCell($w[1], $h, htmlspecialchars($p['nim']), 1, 'C', true, 0, $startX + $w[0], $y, true, 0, false, true, $h, 'M');
        $pdf->MultiCell($w[2], $h, htmlspecialchars($p['jurusan']), 1, 'L', true, 0, $startX + $w[0] + $w[1], $y, true, 0, false, true, $h, 'M');
        $pdf->MultiCell($w[3], $h, htmlspecialchars($p['nama_ukm']), 1, 'L', true, 0, $startX + $w[0] + $w[1] + $w[2], $y, true, 0, false, true, $h, 'M');
        $pdf->MultiCell($w[4], $h, ucfirst($p['status']), 1, 'C', true, 0, $startX + $w[0] + $w[1] + $w[2] + $w[3], $y, true, 0, false, true, $h, 'M');
        $pdf->MultiCell($w[5], $h, formatTanggal($p['created_at']), 1, 'C', true, 0, $startX + $w[0] + $w[1] + $w[2] + $w[3] + $w[4], $y, true, 0, false, true, $h, 'M');

        $pdf->Ln($h);
        $pdf->SetX($startX);
    }

    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX($startX);
    $pdf->Cell($tableWidth, 6, 'Total Pendaftaran: ' . count($pendaftaran_list), 0, 1, 'L');

    if (ob_get_contents()) ob_end_clean();
    $pdf->Output('laporan_pendaftaran_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// ✅ SET JUDUL HALAMAN
$page_title = "Laporan - " . ($is_ukm_admin ? htmlspecialchars($nama_ukm) : "Semua UKM");
?>

<?php include 'layout.php'; ?>

<style>
    .stat-card {
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    .badge-pending { background: #ffc107 !important; color: #212529 !important; }
    .badge-diterima { background: #28a745 !important; color: #fff !important; }
    .badge-ditolak { background: #dc3545 !important; color: #fff !important; }
</style>

<!-- KONTEN UTAMA -->
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-bar text-primary"></i> Laporan Sistem UKM</h2>
        <small class="text-muted">
            <i class="fas fa-calendar"></i> <?= formatTanggal(date('Y-m-d')) ?>
        </small>
    </div>

    <?php displayAlert(); ?>

    <!-- Filter UKM (Hanya untuk superadmin) -->
    <?php if (!$is_ukm_admin): ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label">Filter UKM</label>
                    <select name="ukm" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua UKM</option>
                        <?php foreach ($ukm_list as $ukm): ?>
                            <option value="<?= $ukm['id'] ?>" <?= ($selected_ukm == $ukm['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ukm['nama_ukm']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistik -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card stat-card text-white bg-primary">
                <div class="card-body">
                    <h5>Total Pendaftaran</h5>
                    <h2 class="mb-0"><?= $total_pendaftaran ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card stat-card text-white bg-warning">
                <div class="card-body">
                    <h5>Pending</h5>
                    <h2 class="mb-0"><?= $status_counts['pending'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card stat-card text-white bg-success">
                <div class="card-body">
                    <h5>Diterima</h5>
                    <h2 class="mb-0"><?= $status_counts['diterima'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-table me-2"></i> Data Pendaftaran</h5>
            <div>
                <a href="?download=excel<?= $selected_ukm ? '&ukm=' . $selected_ukm : '' ?>" class="btn btn-outline-success btn-sm me-2">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="?download=pdf<?= $selected_ukm ? '&ukm=' . $selected_ukm : '' ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>NIM</th>
                            <th>Jurusan</th>
                            <th>UKM</th>
                            <th>Status</th>
                            <th>Tanggal Daftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendaftaran_list)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                    Tidak ada data pendaftaran.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendaftaran_list as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nama']) ?></td>
                                    <td><?= htmlspecialchars($p['nim']) ?></td>
                                    <td><?= htmlspecialchars($p['jurusan']) ?></td>
                                    <td><?= htmlspecialchars($p['nama_ukm']) ?></td>
                                    <td>
                                        <?php
                                        $status = strtolower($p['status']);
                                        $badgeClass = match($status) {
                                            'pending' => 'badge-pending',
                                            'diterima' => 'badge-diterima',
                                            'ditolak' => 'badge-ditolak',
                                            default => 'bg-secondary text-white'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($p['status']) ?></span>
                                    </td>
                                    <td><?= formatTanggal($p['created_at']) ?></td>
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