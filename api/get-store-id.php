<?php
// api/get-store-id.php
header('Content-Type: application/json');
session_start();

require_once '../config/database.php';

$storeId = 0;

try {
    if (isset($_SESSION['store_id']) && $_SESSION['store_id'] > 0) {
        $storeId = $_SESSION['store_id'];
    } else if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'loja') {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM lojas WHERE usuario_id = ? AND status = 'aprovado' LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $loja = $stmt->fetch();
        if ($loja) {
            $storeId = $loja['id'];
            $_SESSION['store_id'] = $storeId;
        }
    }
    
    // Se não encontrou, pegar primeira loja ativa
    if ($storeId <= 0) {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT id FROM lojas WHERE status = 'aprovado' ORDER BY id LIMIT 1");
        $loja = $stmt->fetch();
        if ($loja) {
            $storeId = $loja['id'];
        }
    }
    
} catch (Exception $e) {
    error_log("Erro ao detectar store_id: " . $e->getMessage());
    $storeId = 34; // Fallback
}

echo json_encode(['store_id' => $storeId]);
?>