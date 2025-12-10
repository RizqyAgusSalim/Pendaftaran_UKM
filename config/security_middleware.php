<?php
/**
 * Security Middleware untuk Admin UKM
 * Memastikan admin hanya bisa akses data UKM mereka sendiri
 */

class SecurityMiddleware {
    private $db;
    private $admin_id;
    private $ukm_id;
    
    public function __construct($database, $admin_id, $ukm_id) {
        $this->db = $database;
        $this->admin_id = $admin_id;
        $this->ukm_id = $ukm_id;
    }
    
    /**
     * Validasi apakah pendaftaran tertentu milik UKM admin ini
     */
    public function validatePendaftaranAccess($pendaftaran_id) {
        $query = "SELECT ukm_id FROM pendaftaran WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $pendaftaran_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            $this->logSecurityEvent('INVALID_PENDAFTARAN_ID', $pendaftaran_id);
            return false;
        }
        
        if ($result['ukm_id'] != $this->ukm_id) {
            $this->logSecurityEvent('UNAUTHORIZED_PENDAFTARAN_ACCESS', $pendaftaran_id, $result['ukm_id']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validasi apakah anggota tertentu milik UKM admin ini
     */
    public function validateAnggotaAccess($anggota_id) {
        $query = "SELECT ukm_id FROM anggota_ukm WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $anggota_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            $this->logSecurityEvent('INVALID_ANGGOTA_ID', $anggota_id);
            return false;
        }
        
        if ($result['ukm_id'] != $this->ukm_id) {
            $this->logSecurityEvent('UNAUTHORIZED_ANGGOTA_ACCESS', $anggota_id, $result['ukm_id']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validasi apakah kegiatan tertentu milik UKM admin ini
     */
    public function validateKegiatanAccess($kegiatan_id) {
        $query = "SELECT ukm_id FROM kegiatan WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $kegiatan_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            $this->logSecurityEvent('INVALID_KEGIATAN_ID', $kegiatan_id);
            return false;
        }
        
        if ($result['ukm_id'] != $this->ukm_id) {
            $this->logSecurityEvent('UNAUTHORIZED_KEGIATAN_ACCESS', $kegiatan_id, $result['ukm_id']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Log security events ke database atau file
     */
    private function logSecurityEvent($event_type, $target_id, $target_ukm_id = null) {
        $log_message = sprintf(
            "[%s] Admin ID: %d (UKM ID: %d) - Event: %s - Target ID: %s - Target UKM ID: %s - IP: %s - User Agent: %s",
            date('Y-m-d H:i:s'),
            $this->admin_id,
            $this->ukm_id,
            $event_type,
            $target_id,
            $target_ukm_id ?? 'N/A',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        );
        
        // Log ke file
        error_log($log_message . "\n", 3, '../logs/security.log');
        
        // Optional: Log ke database
        try {
            $query = "INSERT INTO security_log (admin_id, ukm_id, event_type, target_id, target_ukm_id, ip_address, user_agent, created_at) 
                    VALUES (:admin_id, :ukm_id, :event_type, :target_id, :target_ukm_id, :ip, :ua, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':admin_id' => $this->admin_id,
                ':ukm_id' => $this->ukm_id,
                ':event_type' => $event_type,
                ':target_id' => $target_id,
                ':target_ukm_id' => $target_ukm_id,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (PDOException $e) {
            // Jika tabel security_log belum ada, skip
            error_log("Security log to DB failed: " . $e->getMessage());
        }
    }
    
    /**
     * Block dan redirect jika akses tidak sah
     */
    public function blockUnauthorizedAccess($message = 'Akses ditolak!') {
        if (function_exists('setAlert')) {
            showAlert('danger', $message);
        }
        
        if (function_exists('redirect')) {
            redirect($_SERVER['PHP_SELF']);
        } else {
            header('Location: ' . $_SERVER['PHP_SELF']);
        }
        exit;
    }
}

/**
 * Helper function untuk mudah digunakan
 */
function validateUKMAccess($db, $admin_id, $ukm_id, $resource_type, $resource_id) {
    $security = new SecurityMiddleware($db, $admin_id, $ukm_id);
    
    $valid = false;
    switch ($resource_type) {
        case 'pendaftaran':
            $valid = $security->validatePendaftaranAccess($resource_id);
            break;
        case 'anggota':
            $valid = $security->validateAnggotaAccess($resource_id);
            break;
        case 'kegiatan':
            $valid = $security->validateKegiatanAccess($resource_id);
            break;
    }
    
    if (!$valid) {
        $security->blockUnauthorizedAccess(
            "AKSES DITOLAK! Anda tidak memiliki izin untuk mengakses {$resource_type} ini."
        );
    }
    
    return $valid;
}

/**
 * Tabel security_log (optional, untuk tracking)
 * 
 * CREATE TABLE IF NOT EXISTS security_log (
 *     id INT PRIMARY KEY AUTO_INCREMENT,
 *     admin_id INT NOT NULL,
 *     ukm_id INT NOT NULL,
 *     event_type VARCHAR(50),
 *     target_id VARCHAR(50),
 *     target_ukm_id INT,
 *     ip_address VARCHAR(45),
 *     user_agent TEXT,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     INDEX idx_admin (admin_id),
 *     INDEX idx_event (event_type),
 *     INDEX idx_created (created_at)
 * );
 */
?>