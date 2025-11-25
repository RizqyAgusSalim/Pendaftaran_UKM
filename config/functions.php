<?php
// =====================================================
// FIX: SESSION HANDLER (Mencegah double session_start())
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Placeholder Class Database
 * Hapus jika kamu sudah punya database.php resmi
 */
if (!class_exists('Database')) {
    class Database {
        private $host = "localhost";
        private $db_name = "ukm_polinela";
        private $username = "root";
        private $password = "";
        public $conn;

        public function getConnection() {
            $this->conn = null;
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password
                );
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $exception) {
                return null;
            }
            return $this->conn;
        }
    }
}


// =====================================================
// 1. Sanitasi Input & Upload File
// =====================================================
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function uploadFile($file, $target_dir = "uploads/") {
    if (!isset($file) || $file["error"] !== UPLOAD_ERR_OK) {
        return false;
    }

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "gif");
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return false;
    }
    
    if ($file["size"] > 5000000) { 
        return false;
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $new_filename;
    }
    
    return false;
}


// =====================================================
// 2. Autentikasi & Role User
// =====================================================
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

function isMahasiswa() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'mahasiswa';
}

function isPengurusUKM($ukm_id) {
    if (!isMahasiswa() || !isLoggedIn()) return false;
    if (!class_exists('Database')) return false;

    $database = new Database();
    $db = $database->getConnection();
    if ($db === null) return false;

    try {
        $query = "SELECT COUNT(*) FROM pengurus_ukm 
                WHERE ukm_id = ? AND mahasiswa_id = ? AND status = 'aktif'";
        $stmt = $db->prepare($query);
        $stmt->execute([$ukm_id, $_SESSION['user_id']]);

        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}


// =====================================================
// 3. Format, Redirect & Alert
// =====================================================
function formatTanggal($tanggal) {
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    if (empty($tanggal)) return '-';

    $split = explode('-', date('Y-n-j', strtotime($tanggal)));
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

function formatRupiah($angka) {
    return is_numeric($angka)
        ? 'Rp ' . number_format($angka, 0, ',', '.')
        : 'Rp 0';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function showAlert($message, $type = 'info') {
    $_SESSION['alert'] = ['message' => $message, 'type' => $type];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        echo '<div class="alert alert-' . $alert['type'] . ' alert-dismissible fade show">
                ' . htmlspecialchars($alert['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['alert']);
    }
}


// =====================================================
// 4. Database Helper
// =====================================================
function getAllKategoriUKM() {
    if (!class_exists('Database')) return [];
    $db = (new Database())->getConnection();
    if (!$db) return [];

    try {
        $stmt = $db->prepare("SELECT * FROM kategori_ukm ORDER BY nama_kategori");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getUKMByKategori($kategori_id = null) {
    if (!class_exists('Database')) return [];
    $db = (new Database())->getConnection();
    if (!$db) return [];

    try {
        $params = [];
        $query = "SELECT u.*, k.nama_kategori 
                  FROM ukm u
                  LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
                  WHERE u.status = 'aktif'";

        if ($kategori_id) {
            $query .= " AND u.kategori_id = ?";
            $params[] = $kategori_id;
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        return [];
    }
}

function sudahMendaftar($mahasiswa_id, $ukm_id) {
    if (!class_exists('Database')) return false;
    $db = (new Database())->getConnection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM pendaftaran WHERE mahasiswa_id = ? AND ukm_id = ?");
        $stmt->execute([$mahasiswa_id, $ukm_id]);
        return $stmt->fetchColumn() > 0;

    } catch (PDOException $e) {
        return false;
    }
}

function getStatusPendaftaran($mahasiswa_id, $ukm_id) {
    if (!class_exists('Database')) return null;
    $db = (new Database())->getConnection();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("SELECT status FROM pendaftaran WHERE mahasiswa_id = ? AND ukm_id = ?");
        $stmt->execute([$mahasiswa_id, $ukm_id]);
        return $stmt->fetchColumn() ?: null;

    } catch (PDOException $e) {
        return null;
    }
}

function getUKMById($ukm_id) {
    if (!class_exists('Database')) return null;
    $db = (new Database())->getConnection();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("SELECT u.*, k.nama_kategori 
                             FROM ukm u 
                             LEFT JOIN kategori_ukm k ON u.kategori_id = k.id 
                             WHERE u.id = ?");
        $stmt->execute([$ukm_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        return null;
    }
}

function getMahasiswaById($mahasiswa_id) {
    if (!class_exists('Database')) return null;
    $db = (new Database())->getConnection();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("SELECT * FROM mahasiswa WHERE id = ?");
        $stmt->execute([$mahasiswa_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        return null;
    }
}
