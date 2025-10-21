<?php
/**
 * API para buscar lojas (autocomplete)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

session_start();

// Verificar autenticação admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Obter query
$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $db = (new Database())->getConnection();

    // Buscar lojas por nome ou email
    $sql = "SELECT id, nome_fantasia, razao_social, email, status, cnpj
            FROM lojas
            WHERE (nome_fantasia LIKE ? OR razao_social LIKE ? OR email LIKE ?)
            AND status IN ('aprovado', 'pendente')
            ORDER BY
                CASE
                    WHEN status = 'aprovado' THEN 1
                    ELSE 2
                END,
                nome_fantasia ASC
            LIMIT 20";

    $searchTerm = '%' . $query . '%';
    $stmt = $db->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);

    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($stores);

} catch (Exception $e) {
    error_log("Erro em search-stores.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar lojas']);
}
