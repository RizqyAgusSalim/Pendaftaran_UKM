<?php
// check_update.php - cek update sederhana
session_start();
if (!isset($_SESSION['user_id'])) exit;

header('Content-Type: application/json');
echo json_encode(['updated' => false]); // Bisa dikembangkan dengan logika timestamp