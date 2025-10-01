<?php
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/TransactionController.php';

header('Content-Type: application/json; charset=UTF-8');
session_start();

if (!AuthController::isAuthenticated() || !AuthController::isStore()) {
    echo json_encode(['status' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = TransactionController::registerPayment($_POST);
    echo json_encode($result);
} else {
    echo json_encode(['status' => false, 'message' => 'Método não permitido']);
}
?>