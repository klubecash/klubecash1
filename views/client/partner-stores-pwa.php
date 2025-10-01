<?php
// views/client/partner-stores-pwa.php
// PÁGINA PWA OTIMIZADA - LOJAS PARCEIRAS

// Definir o menu ativo
$activeMenu = 'lojas';

// Incluir arquivos necessários
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/ClientController.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é cliente
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== USER_TYPE_CLIENT) {
    header("Location: ../auth/login.php?error=acesso_restrito");
    exit;
}

// Obter dados do usuário
$userId = $_SESSION['user_id'];

// Processar toggle de favoritos via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
    header('Content-Type: application/json');
    
    $storeId = isset($_POST['store_id']) ? (int)$_POST['store_id'] : 0;
    $isFavorite = isset($_POST['is_favorite']) ? (bool)$_POST['is_favorite'] : false;
    
    $result = ClientController::toggleFavoriteStore($userId, $storeId, !$isFavorite);
    echo json_encode($result);
    exit;
}

// Processar busca via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    
    $searchQuery = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';
    $latitude = $_GET['lat'] ?? null;
    $longitude = $_GET['lng'] ?? null;
    $radius = $_GET['radius'] ?? 50; // raio em km
    
    $filters = [];
    if (!empty($searchQuery)) $filters['nome'] = $searchQuery;
    if (!empty($category) && $category !== 'todas') $filters['categoria'] = $category;
    
    // Geolocalização (se fornecida)
    if ($latitude && $longitude) {
        $filters['latitude'] = $latitude;
        $filters['longitude'] = $longitude;
        $filters['radius'] = $radius;
    }
    
    $result = ClientController::getPartnerStores($userId, $filters, 1, 50);
    echo json_encode($result);
    exit;
}

// Valores padrão para filtros
$filters = [];
$searchTerm = $_GET['busca'] ?? '';
$selectedCategory = $_GET['categoria'] ?? 'todas';

// Aplicar filtros se existirem
if (!empty($searchTerm)) {
    $filters['nome'] = $searchTerm;
}
if (!empty($selectedCategory) && $selectedCategory !== 'todas') {
    $filters['categoria'] = $selectedCategory;
}

try {
    // Obter lojas parceiras
    $storesResult = ClientController::getPartnerStores($userId, $filters, 1, 50);
    $stores = $storesResult['status'] ? $storesResult['data'] : [];
    
    // Obter categorias para filtros
    $categoriesQuery = "SELECT DISTINCT categoria FROM lojas WHERE status = 'aprovado' ORDER BY categoria";
    $db = Database::getConnection();
    $categoriesStmt = $db->query($categoriesQuery);
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $stores = [];
    $categories = [];
    $errorMessage = "Erro ao carregar lojas: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2D7D32">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    
    <title>Lojas Parceiras - Klube Cash</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="../../pwa/manifest.json">
    
    <!-- Ícones PWA -->
    <link rel="apple-touch-icon" href="../../assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="../../assets/icons/icon-192x192.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/client.css">
    <link rel="stylesheet" href="../../assets/css/pwa.css">
    <link rel="stylesheet" href="../../assets/css/mobile-first.css">
    
    <!-- Preload de recursos críticos -->
    <link rel="preload" href="../../assets/css/main.css" as="style">
    <link rel="preload" href="../../assets/js/pwa-stores.js" as="script">
</head>
<style> 
/* assets/css/pwa-stores.css */
/* PWA LOJAS PARCEIRAS - OTIMIZADO PARA MOBILE */

/* ==================== VARIÁVEIS CSS ==================== */
:root {
    --pwa-primary: #2D7D32;
    --pwa-primary-light: #4CAF50;
    --pwa-primary-dark: #1B5E20;
    --pwa-secondary: #FF6B35;
    --pwa-background: #F8F9FA;
    --pwa-surface: #FFFFFF;
    --pwa-text: #212121;
    --pwa-text-secondary: #757575;
    --pwa-border: #E0E0E0;
    --pwa-shadow: 0 2px 8px rgba(0,0,0,0.1);
    --pwa-radius: 12px;
    --pwa-spacing: 16px;
    
    /* Touch targets */
    --touch-target: 44px;
    --touch-target-sm: 36px;
    
    /* Z-index layers */
    --z-header: 100;
    --z-modal: 200;
    --z-toast: 300;
}

/* ==================== BASE PWA ==================== */
.pwa-body {
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--pwa-background);
    color: var(--pwa-text);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    overscroll-behavior: none;
    height: 100vh;
    height: calc(var(--vh, 1vh) * 100);
}

/* ==================== HEADER PWA ==================== */
.pwa-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: var(--pwa-surface);
    border-bottom: 1px solid var(--pwa-border);
    z-index: var(--z-header);
    box-shadow: var(--pwa-shadow);
}

.pwa-header-content {
    display: flex;
    align-items: center;
    padding: 12px var(--pwa-spacing);
    min-height: var(--touch-target);
}

.pwa-back-btn {
    width: var(--touch-target);
    height: var(--touch-target);
    border: none;
    background: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pwa-text);
    cursor: pointer;
    margin-right: 8px;
    transition: background-color 0.2s ease;
}

.pwa-back-btn:active {
    background: var(--pwa-border);
    transform: scale(0.95);
}

.pwa-header-title {
    flex: 1;
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: var(--pwa-text);
}

.pwa-header-actions {
    display: flex;
    gap: 4px;
}

.pwa-action-btn {
    width: var(--touch-target);
    height: var(--touch-target);
    border: none;
    background: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pwa-text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.pwa-action-btn:active {
    background: var(--pwa-border);
    transform: scale(0.95);
}

.pwa-action-btn.active {
    color: var(--pwa-primary);
    background: rgba(77, 175, 80, 0.1);
}

.pwa-action-btn.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border: 2px solid var(--pwa-border);
    border-top: 2px solid var(--pwa-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* ==================== BUSCA PWA ==================== */
.pwa-search-container {
    padding: 0 var(--pwa-spacing) 12px;
}

.pwa-search-box {
    position: relative;
    display: flex;
    align-items: center;
    background: var(--pwa-background);
    border: 1px solid var(--pwa-border);
    border-radius: var(--pwa-radius);
    overflow: hidden;
}

.pwa-search-icon {
    margin: 0 12px;
    color: var(--pwa-text-secondary);
    flex-shrink: 0;
}

.pwa-search-input {
    flex: 1;
    border: none;
    background: none;
    padding: 12px 0;
    font-size: 16px;
    color: var(--pwa-text);
    outline: none;
}

.pwa-search-input::placeholder {
    color: var(--pwa-text-secondary);
}

.pwa-search-clear {
    width: var(--touch-target-sm);
    height: var(--touch-target-sm);
    border: none;
    background: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pwa-text-secondary);
    margin-right: 8px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.pwa-search-clear:active {
    background: var(--pwa-border);
}

/* ==================== CATEGORIAS ==================== */
.pwa-categories-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.pwa-categories-scroll::-webkit-scrollbar {
    display: none;
}

.pwa-categories-container {
    display: flex;
    gap: 8px;
    padding: 0 var(--pwa-spacing) 16px;
    min-width: max-content;
}

.pwa-category-chip {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    background: var(--pwa-surface);
    border: 1px solid var(--pwa-border);
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    color: var(--pwa-text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    min-height: var(--touch-target-sm);
}

.pwa-category-chip:active {
    transform: scale(0.95);
}

.pwa-category-chip.active {
    background: var(--pwa-primary);
    border-color: var(--pwa-primary);
    color: white;
}

/* ==================== MAIN CONTENT ==================== */
.pwa-main {
    padding-top: 160px; /* Header + search + categories */
    padding-bottom: 80px; /* Bottom nav */
    min-height: 100vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* ==================== STATES ==================== */
.pwa-loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px var(--pwa-spacing);
    color: var(--pwa-text-secondary);
}

.pwa-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid var(--pwa-border);
    border-top: 3px solid var(--pwa-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

.pwa-location-result {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 var(--pwa-spacing) 16px;
    padding: 12px 16px;
    background: var(--pwa-primary-light);
    color: white;
    border-radius: var(--pwa-radius);
    font-size: 14px;
}

.location-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pwa-btn-text {
    background: none;
    border: none;
    color: white;
    text-decoration: underline;
    cursor: pointer;
    font-size: 14px;
    padding: 4px 8px;
}

.pwa-result-stats {
    margin: 0 var(--pwa-spacing) 16px;
    font-size: 14px;
    color: var(--pwa-text-secondary);
    font-weight: 500;
}

/* ==================== GRID DE LOJAS ==================== */
.pwa-stores-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    padding: 0 var(--pwa-spacing);
    margin-bottom: 24px;
}

@media (max-width: 640px) {
    .pwa-stores-grid {
        grid-template-columns: 1fr;
    }
}

/* ==================== CARD DA LOJA ==================== */
.pwa-store-card {
    position: relative;
    background: var(--pwa-surface);
    border-radius: var(--pwa-radius);
    padding: 16px;
    box-shadow: var(--pwa-shadow);
    border: 1px solid var(--pwa-border);
    transition: all 0.2s ease;
    cursor: pointer;
}

.pwa-store-card:active {
    transform: scale(0.98);
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}

.store-distance-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: var(--pwa-primary);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    z-index: 2;
}

.pwa-favorite-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: var(--touch-target-sm);
    height: var(--touch-target-sm);
    border: none;
    background: var(--pwa-surface);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--pwa-shadow);
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 2;
}

.pwa-favorite-btn .heart-filled {
    display: none;
}

.pwa-favorite-btn.active .heart-outline {
    display: none;
}

.pwa-favorite-btn.active .heart-filled {
    display: block;
    color: #E91E63;
}

.pwa-favorite-btn:active {
    transform: scale(0.9);
}

.pwa-favorite-btn.loading {
    pointer-events: none;
}

.pwa-favorite-btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    border: 2px solid var(--pwa-border);
    border-top: 2px solid var(--pwa-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* ==================== LOGO DA LOJA ==================== */
.pwa-store-logo {
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
    border-radius: var(--pwa-radius);
    overflow: hidden;
    background: var(--pwa-background);
    display: flex;
    align-items: center;
    justify-content: center;
}

.pwa-store-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.store-logo-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: var(--pwa-text-secondary);
}

/* ==================== INFO DA LOJA ==================== */
.pwa-store-info {
    text-align: center;
    margin-bottom: 16px;
}

.pwa-store-name {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 4px;
    color: var(--pwa-text);
    line-height: 1.3;
}

.pwa-store-category {
    font-size: 14px;
    color: var(--pwa-text-secondary);
    margin-bottom: 8px;
}

.pwa-cashback-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--pwa-primary);
    color: white;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.pwa-store-address,
.pwa-store-balance {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    font-size: 12px;
    color: var(--pwa-text-secondary);
    margin-bottom: 4px;
    line-height: 1.3;
}

.pwa-store-balance {
    color: var(--pwa-primary);
    font-weight: 600;
}

/* ==================== AÇÕES DA LOJA ==================== */
.pwa-store-actions {
    text-align: center;
}

.pwa-btn-primary {
    background: var(--pwa-primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: var(--touch-target-sm);
}

.pwa-btn-primary:active {
    background: var(--pwa-primary-dark);
    transform: scale(0.95);
}

.pwa-btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

/* ==================== ESTADO VAZIO ==================== */
.pwa-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px var(--pwa-spacing);
    text-align: center;
    color: var(--pwa-text-secondary);
}

.empty-icon {
    margin-bottom: 16px;
    opacity: 0.5;
}

.pwa-empty-state h3 {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 8px;
    color: var(--pwa-text);
}

.pwa-empty-state p {
    font-size: 14px;
    margin: 0 0 20px;
    line-height: 1.5;
    max-width: 280px;
}

.pwa-btn-outline {
    background: none;
    color: var(--pwa-primary);
    border: 1px solid var(--pwa-primary);
    border-radius: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: var(--touch-target-sm);
}

.pwa-btn-outline:active {
    background: var(--pwa-primary);
    color: white;
    transform: scale(0.95);
}

/* ==================== MODAL PWA ==================== */
.pwa-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: var(--z-modal);
    display: flex;
    align-items: flex-end;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.pwa-modal.show {
    opacity: 1;
}

.pwa-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
}

.pwa-modal-content {
    position: relative;
    background: var(--pwa-surface);
    border-radius: var(--pwa-radius) var(--pwa-radius) 0 0;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    transform: translateY(100%);
    transition: transform 0.3s ease;
}

.pwa-modal.show .pwa-modal-content {
    transform: translateY(0);
}

.pwa-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px var(--pwa-spacing);
    border-bottom: 1px solid var(--pwa-border);
    position: sticky;
    top: 0;
    background: var(--pwa-surface);
    z-index: 1;
}

.pwa-modal-header h3 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: var(--pwa-text);
}

.pwa-modal-close {
    width: var(--touch-target);
    height: var(--touch-target);
    border: none;
    background: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pwa-text-secondary);
    cursor: pointer;
}

.pwa-modal-close:active {
    background: var(--pwa-border);
    transform: scale(0.95);
}

.pwa-modal-body {
    padding: 20px var(--pwa-spacing);
}

.pwa-modal-footer {
    padding: 16px var(--pwa-spacing);
    border-top: 1px solid var(--pwa-border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    position: sticky;
    bottom: 0;
    background: var(--pwa-surface);
}

/* ==================== FILTROS ==================== */
.filter-section {
    margin-bottom: 24px;
}

.filter-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--pwa-text);
    margin-bottom: 8px;
}

.pwa-select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--pwa-border);
    border-radius: var(--pwa-radius);
    background: var(--pwa-surface);
    color: var(--pwa-text);
    font-size: 16px;
    outline: none;
    transition: border-color 0.2s ease;
}

.pwa-select:focus {
    border-color: var(--pwa-primary);
}

.range-container {
    margin-top: 8px;
}

.pwa-range {
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: var(--pwa-border);
    outline: none;
    -webkit-appearance: none;
    appearance: none;
}

.pwa-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--pwa-primary);
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.pwa-range::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--pwa-primary);
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.pwa-range:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.range-labels {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
    font-size: 12px;
    color: var(--pwa-text-secondary);
}

.filter-note {
    display: block;
    font-size: 12px;
    color: var(--pwa-text-secondary);
    margin-top: 4px;
    font-style: italic;
}

/* ==================== CHECKBOX PWA ==================== */
.pwa-checkbox {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    user-select: none;
    padding: 8px 0;
    font-size: 14px;
    color: var(--pwa-text);
}

.pwa-checkbox input[type="checkbox"] {
    display: none;
}

.checkmark {
    width: 20px;
    height: 20px;
    border: 2px solid var(--pwa-border);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.pwa-checkbox input[type="checkbox"]:checked + .checkmark {
    background: var(--pwa-primary);
    border-color: var(--pwa-primary);
}

.pwa-checkbox input[type="checkbox"]:checked + .checkmark::after {
    content: '✓';
    color: white;
    font-size: 12px;
    font-weight: bold;
}

/* ==================== PULL TO REFRESH ==================== */
.pwa-pull-refresh {
    position: absolute;
    top: -80px;
    left: 0;
    right: 0;
    height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--pwa-surface);
    color: var(--pwa-text-secondary);
    font-size: 14px;
    z-index: 10;
    transition: all 0.3s ease;
}

.pwa-pull-refresh.active {
    color: var(--pwa-primary);
}

.pull-refresh-spinner {
    width: 24px;
    height: 24px;
    border: 2px solid var(--pwa-border);
    border-top: 2px solid currentColor;
    border-radius: 50%;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.pwa-pull-refresh.refreshing .pull-refresh-spinner {
    animation: spin 1s linear infinite;
}

/* ==================== TOAST PWA ==================== */
.pwa-toast {
    position: fixed;
    bottom: 100px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--pwa-text);
    color: white;
    border-radius: var(--pwa-radius);
    padding: 12px 16px;
    max-width: 300px;
    z-index: var(--z-toast);
    box-shadow: var(--pwa-shadow);
    animation: toastSlideUp 0.3s ease;
}

.pwa-toast.success {
    background: var(--pwa-primary);
}

.pwa-toast.error {
    background: #F44336;
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toast-icon {
    flex-shrink: 0;
}

.toast-message {
    font-size: 14px;
    font-weight: 500;
}

/* ==================== ANIMAÇÕES ==================== */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes toastSlideUp {
    0% { 
        opacity: 0; 
        transform: translateX(-50%) translateY(20px); 
    }
    100% { 
        opacity: 1; 
        transform: translateX(-50%) translateY(0); 
    }
}

/* ==================== MEDIA QUERIES ==================== */
@media (max-width: 480px) {
    .pwa-header-content {
        padding: 8px 12px;
    }
    
    .pwa-search-container,
    .pwa-categories-container {
        padding-left: 12px;
        padding-right: 12px;
    }
    
    .pwa-stores-grid {
        padding: 0 12px;
        gap: 12px;
    }
    
    .pwa-store-card {
        padding: 12px;
    }
    
    .pwa-main {
        padding-top: 150px;
    }
}

@media (min-width: 768px) {
    .pwa-stores-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

@media (min-width: 1024px) {
    .pwa-stores-grid {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 24px;
    }
}

/* ==================== SCROLL BEHAVIOR ==================== */
.pwa-main {
    scroll-behavior: smooth;
}

/* ==================== ACESSIBILIDADE ==================== */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

/* ==================== DARK MODE ==================== */
@media (prefers-color-scheme: dark) {
    :root {
        --pwa-background: #121212;
        --pwa-surface: #1E1E1E;
        --pwa-text: #FFFFFF;
        --pwa-text-secondary: #AAAAAA;
        --pwa-border: #333333;
    }
}

/* ==================== PRINT ==================== */
@media print {
    .pwa-header,
    .pwa-pull-refresh,
    .pwa-toast,
    .pwa-modal {
        display: none !important;
    }
    
    .pwa-main {
        padding-top: 0 !important;
    }
}
</style>
<body class="pwa-body">
    <!-- PWA Header -->
    <header class="pwa-header">
        <div class="pwa-header-content">
            <button class="pwa-back-btn" onclick="history.back()" aria-label="Voltar">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 18l-6-6 6-6"/>
                </svg>
            </button>
            
            <h1 class="pwa-header-title">Lojas Parceiras</h1>
            
            <div class="pwa-header-actions">
                <button class="pwa-action-btn" id="locationBtn" aria-label="Ativar localização">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <circle cx="12" cy="11" r="3"/>
                    </svg>
                </button>
                
                <button class="pwa-action-btn" id="filterBtn" aria-label="Filtros">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Barra de busca otimizada -->
        <div class="pwa-search-container">
            <div class="pwa-search-box">
                <svg class="pwa-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35"/>
                </svg>
                
                <input 
                    type="text" 
                    id="searchInput" 
                    class="pwa-search-input" 
                    placeholder="Buscar lojas parceiras..."
                    value="<?php echo htmlspecialchars($searchTerm); ?>"
                    autocomplete="off"
                    spellcheck="false"
                >
                
                <button class="pwa-search-clear" id="clearSearch" style="display: none;" aria-label="Limpar busca">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Chips de categorias -->
        <div class="pwa-categories-scroll">
            <div class="pwa-categories-container">
                <button class="pwa-category-chip <?php echo $selectedCategory === 'todas' ? 'active' : ''; ?>" 
                        data-category="todas">
                    Todas
                </button>
                
                <?php foreach ($categories as $category): ?>
                <button class="pwa-category-chip <?php echo $selectedCategory === $category ? 'active' : ''; ?>" 
                        data-category="<?php echo htmlspecialchars($category); ?>">
                    <?php echo htmlspecialchars($category); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pwa-main" role="main">
        <!-- Loading State -->
        <div id="loadingState" class="pwa-loading-state" style="display: none;">
            <div class="pwa-spinner"></div>
            <p>Carregando lojas...</p>
        </div>
        
        <!-- Resultado da busca por localização -->
        <div id="locationResult" class="pwa-location-result" style="display: none;">
            <div class="location-info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <circle cx="12" cy="11" r="3"/>
                </svg>
                <span id="locationText">Buscando lojas próximas...</span>
            </div>
            
            <button id="clearLocation" class="pwa-btn-text">
                Limpar localização
            </button>
        </div>
        
        <!-- Stats do resultado -->
        <div class="pwa-result-stats" id="resultStats">
            <span id="storeCount"><?php echo count($stores); ?></span> lojas encontradas
        </div>
        
        <!-- Grid de lojas -->
        <div class="pwa-stores-grid" id="storesGrid">
            <?php if (!empty($stores)): ?>
                <?php foreach ($stores as $store): ?>
                <div class="pwa-store-card" data-store-id="<?php echo $store['id']; ?>">
                    <!-- Badge de distância (se disponível) -->
                    <?php if (isset($store['distancia'])): ?>
                    <div class="store-distance-badge">
                        <?php echo number_format($store['distancia'], 1); ?> km
                    </div>
                    <?php endif; ?>
                    
                    <!-- Botão de favorito -->
                    <button class="pwa-favorite-btn <?php echo $store['e_favorita'] ? 'active' : ''; ?>" 
                            data-store-id="<?php echo $store['id']; ?>"
                            data-is-favorite="<?php echo $store['e_favorita'] ? '1' : '0'; ?>"
                            aria-label="<?php echo $store['e_favorita'] ? 'Remover dos favoritos' : 'Adicionar aos favoritos'; ?>">
                        <svg class="heart-outline" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 8.5c0-2.485-2.239-4.5-5-4.5-1.964 0-3.612 1.043-4 2.5C11.612 5.043 9.964 4 8 4 5.239 4 3 6.015 3 8.5c0 6 9 12 9 12s9-6 9-12z"/>
                        </svg>
                        <svg class="heart-filled" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21 8.5c0-2.485-2.239-4.5-5-4.5-1.964 0-3.612 1.043-4 2.5C11.612 5.043 9.964 4 8 4 5.239 4 3 6.015 3 8.5c0 6 9 12 9 12s9-6 9-12z"/>
                        </svg>
                    </button>
                    
                    <!-- Logo da loja -->
                    <div class="pwa-store-logo">
                        <?php if (!empty($store['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($store['logo']); ?>" 
                                 alt="Logo <?php echo htmlspecialchars($store['nome_fantasia']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="store-logo-placeholder">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informações da loja -->
                    <div class="pwa-store-info">
                        <h3 class="pwa-store-name">
                            <?php echo htmlspecialchars($store['nome_fantasia']); ?>
                        </h3>
                        
                        <div class="pwa-store-category">
                            <?php echo htmlspecialchars($store['categoria']); ?>
                        </div>
                        
                        <!-- Cashback destacado -->
                        <div class="pwa-cashback-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="12" cy="12" r="10"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                            </svg>
                            <?php echo number_format($store['porcentagem_cashback'], 1); ?>% cashback
                        </div>
                        
                        <!-- Endereço (se disponível) -->
                        <?php if (!empty($store['endereco_completo'])): ?>
                        <div class="pwa-store-address">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <circle cx="12" cy="11" r="3"/>
                            </svg>
                            <?php echo htmlspecialchars($store['endereco_completo']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Saldo disponível (se houver) -->
                        <?php if (isset($store['saldo_disponivel']) && $store['saldo_disponivel'] > 0): ?>
                        <div class="pwa-store-balance">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            Você tem R$ <?php echo number_format($store['saldo_disponivel'], 2, ',', '.'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Botão de ação -->
                    <div class="pwa-store-actions">
                        <button class="pwa-btn-primary pwa-btn-sm" onclick="viewStoreDetails(<?php echo $store['id']; ?>)">
                            Ver loja
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Estado vazio -->
        <div class="pwa-empty-state" id="emptyState" style="display: <?php echo empty($stores) ? 'flex' : 'none'; ?>;">
            <div class="empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h3>Nenhuma loja encontrada</h3>
            <p>Tente ajustar os filtros de busca ou explorar outras categorias.</p>
            <button class="pwa-btn-outline" onclick="clearAllFilters()">
                Limpar filtros
            </button>
        </div>
        
        <!-- Pulll to refresh indicator -->
        <div class="pwa-pull-refresh" id="pullRefresh" style="display: none;">
            <div class="pull-refresh-spinner"></div>
            <span>Solte para atualizar</span>
        </div>
    </main>

    <!-- Modal de filtros avançados -->
    <div class="pwa-modal" id="filtersModal" style="display: none;">
        <div class="pwa-modal-backdrop"></div>
        <div class="pwa-modal-content">
            <div class="pwa-modal-header">
                <h3>Filtros</h3>
                <button class="pwa-modal-close" aria-label="Fechar">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            
            <div class="pwa-modal-body">
                <div class="filter-section">
                    <label class="filter-label">Ordenar por</label>
                    <select class="pwa-select" id="sortFilter">
                        <option value="nome">Nome (A-Z)</option>
                        <option value="cashback">Maior cashback</option>
                        <option value="distancia">Mais próxima</option>
                        <option value="favoritas">Favoritas primeiro</option>
                    </select>
                </div>
                
                <div class="filter-section">
                    <label class="filter-label">Cashback mínimo</label>
                    <div class="range-container">
                        <input type="range" id="cashbackRange" min="0" max="20" step="0.5" value="0" class="pwa-range">
                        <div class="range-labels">
                            <span>0%</span>
                            <span id="cashbackValue">0%</span>
                            <span>20%</span>
                        </div>
                    </div>
                </div>
                
                <div class="filter-section">
                    <label class="filter-label">Distância máxima</label>
                    <div class="range-container">
                        <input type="range" id="distanceRange" min="1" max="100" step="1" value="50" class="pwa-range" disabled>
                        <div class="range-labels">
                            <span>1km</span>
                            <span id="distanceValue">50km</span>
                            <span>100km</span>
                        </div>
                    </div>
                    <small class="filter-note">Ative a localização para usar este filtro</small>
                </div>
                
                <div class="filter-section">
                    <label class="pwa-checkbox">
                        <input type="checkbox" id="onlyFavorites">
                        <span class="checkmark"></span>
                        Apenas favoritas
                    </label>
                </div>
                
                <div class="filter-section">
                    <label class="pwa-checkbox">
                        <input type="checkbox" id="withBalance">
                        <span class="checkmark"></span>
                        Apenas com saldo disponível
                    </label>
                </div>
            </div>
            
            <div class="pwa-modal-footer">
                <button class="pwa-btn-outline" onclick="clearFilters()">
                    Limpar
                </button>
                <button class="pwa-btn-primary" onclick="applyFilters()">
                    Aplicar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Toast para notificações -->
    <div class="pwa-toast" id="toast" style="display: none;">
        <div class="toast-content">
            <svg class="toast-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span class="toast-message">Mensagem</span>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Configurações globais
        const CONFIG = {
            userId: <?php echo $userId; ?>,
            apiUrl: '<?php echo SITE_URL; ?>',
            currentLocation: null,
            searchDebounceTime: 300,
            isSearching: false
        };
        
        // User location data
        let userLocation = null;
        let locationWatchId = null;
    </script>
    
    <!-- Scripts PWA específicos -->
    <script src="../../assets/js/pwa-main.js"></script>
    <script src="../../assets/js/ui-interactions.js"></script>
    <script>
    // PWA Stores Script - Otimizado para performance e UX mobile
    
    // ==================== INICIALIZAÇÃO ====================
    document.addEventListener('DOMContentLoaded', function() {
        initializeStoresPage();
        setupEventListeners();
        setupPullToRefresh();
        loadUserLocation();
    });
    
    // ==================== CONFIGURAÇÃO INICIAL ====================
    function initializeStoresPage() {
        // Configurar height dinâmico para mobile
        setViewportHeight();
        window.addEventListener('resize', setViewportHeight);
        
        // Registrar service worker se disponível
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../../pwa/sw.js');
        }
        
        // Configurar lazy loading de imagens
        setupLazyLoading();
        
        // Aplicar filtros salvos
        applySavedFilters();
    }
    
    function setViewportHeight() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
    
    // ==================== EVENT LISTENERS ====================
    function setupEventListeners() {
        // Busca em tempo real
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;
        
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            // Mostrar/ocultar botão de limpar
            document.getElementById('clearSearch').style.display = query ? 'flex' : 'none';
            
            // Debounce da busca
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, CONFIG.searchDebounceTime);
        });
        
        // Limpar busca
        document.getElementById('clearSearch').addEventListener('click', function() {
            searchInput.value = '';
            this.style.display = 'none';
            performSearch('');
        });
        
        // Chips de categoria
        document.querySelectorAll('.pwa-category-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                selectCategory(this.dataset.category);
            });
        });
        
        // Botões de ação
        document.getElementById('locationBtn').addEventListener('click', toggleLocation);
        document.getElementById('filterBtn').addEventListener('click', openFiltersModal);
        
        // Favoritos
        document.querySelectorAll('.pwa-favorite-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleFavorite(this);
            });
        });
        
        // Modal de filtros
        setupFiltersModal();
    }
    
    // ==================== BUSCA E FILTROS ====================
    function performSearch(query = '', category = '') {
        if (CONFIG.isSearching) return;
        
        CONFIG.isSearching = true;
        showLoadingState();
        
        // Obter categoria ativa se não especificada
        if (!category) {
            const activeChip = document.querySelector('.pwa-category-chip.active');
            category = activeChip ? activeChip.dataset.category : 'todas';
        }
        
        // Construir parâmetros da busca
        const params = new URLSearchParams({
            action: 'search',
            q: query,
            category: category
        });
        
        // Adicionar localização se disponível
        if (userLocation) {
            params.append('lat', userLocation.latitude);
            params.append('lng', userLocation.longitude);
            params.append('radius', getDistanceFilter());
        }
        
        // Realizar busca
        fetch(window.location.href + '?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    displayStores(data.data || []);
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                console.error('Erro na busca:', error);
                showError('Erro ao buscar lojas. Tente novamente.');
            })
            .finally(() => {
                CONFIG.isSearching = false;
                hideLoadingState();
            });
    }
    
    function selectCategory(category) {
        // Atualizar chips visuais
        document.querySelectorAll('.pwa-category-chip').forEach(chip => {
            chip.classList.toggle('active', chip.dataset.category === category);
        });
        
        // Salvar preferência
        localStorage.setItem('selectedCategory', category);
        
        // Realizar busca
        const searchQuery = document.getElementById('searchInput').value.trim();
        performSearch(searchQuery, category);
    }
    
    // ==================== GEOLOCALIZAÇÃO ====================
    function loadUserLocation() {
        const savedLocation = localStorage.getItem('userLocation');
        if (savedLocation) {
            userLocation = JSON.parse(savedLocation);
            updateLocationUI(true);
        }
    }
    
    function toggleLocation() {
        if (userLocation) {
            // Limpar localização
            clearUserLocation();
        } else {
            // Solicitar localização
            requestUserLocation();
        }
    }
    
    function requestUserLocation() {
        if (!navigator.geolocation) {
            showToast('Geolocalização não disponível', 'error');
            return;
        }
        
        const locationBtn = document.getElementById('locationBtn');
        locationBtn.classList.add('loading');
        
        showToast('Obtendo sua localização...', 'info');
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                userLocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy
                };
                
                localStorage.setItem('userLocation', JSON.stringify(userLocation));
                updateLocationUI(true);
                
                // Realizar nova busca com localização
                const searchQuery = document.getElementById('searchInput').value.trim();
                performSearch(searchQuery);
                
                showToast('Localização ativada!', 'success');
            },
            function(error) {
                console.error('Erro de geolocalização:', error);
                let message = 'Erro ao obter localização';
                
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'Permissão de localização negada';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = 'Localização indisponível';
                        break;
                    case error.TIMEOUT:
                        message = 'Timeout na localização';
                        break;
                }
                
                showToast(message, 'error');
                updateLocationUI(false);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 600000 // 10 minutos
            }
        );
        
        locationBtn.classList.remove('loading');
    }
    
    function clearUserLocation() {
        userLocation = null;
        localStorage.removeItem('userLocation');
        updateLocationUI(false);
        
        // Realizar nova busca sem localização
        const searchQuery = document.getElementById('searchInput').value.trim();
        performSearch(searchQuery);
        
        showToast('Localização removida', 'info');
    }
    
    function updateLocationUI(hasLocation) {
        const locationBtn = document.getElementById('locationBtn');
        const locationResult = document.getElementById('locationResult');
        const distanceRange = document.getElementById('distanceRange');
        
        locationBtn.classList.toggle('active', hasLocation);
        locationResult.style.display = hasLocation ? 'flex' : 'none';
        
        if (distanceRange) {
            distanceRange.disabled = !hasLocation;
        }
        
        if (hasLocation) {
            document.getElementById('locationText').textContent = 'Lojas ordenadas por proximidade';
        }
    }
    
    // ==================== FAVORITOS ====================
    function toggleFavorite(button) {
        const storeId = button.dataset.storeId;
        const isFavorite = button.dataset.isFavorite === '1';
        
        // Feedback visual imediato
        button.classList.add('loading');
        
        // Requisição AJAX
        const formData = new FormData();
        formData.append('action', 'toggle_favorite');
        formData.append('store_id', storeId);
        formData.append('is_favorite', isFavorite);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                // Atualizar estado do botão
                const newIsFavorite = !isFavorite;
                button.dataset.isFavorite = newIsFavorite ? '1' : '0';
                button.classList.toggle('active', newIsFavorite);
                button.setAttribute('aria-label', 
                    newIsFavorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos'
                );
                
                // Haptic feedback se disponível
                if (navigator.vibrate) {
                    navigator.vibrate(50);
                }
                
                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao alterar favorito:', error);
            showToast('Erro ao alterar favorito', 'error');
        })
        .finally(() => {
            button.classList.remove('loading');
        });
    }
    
    // ==================== EXIBIÇÃO DE DADOS ====================
    function displayStores(stores) {
        const grid = document.getElementById('storesGrid');
        const emptyState = document.getElementById('emptyState');
        const resultStats = document.getElementById('resultStats');
        
        // Atualizar contador
        document.getElementById('storeCount').textContent = stores.length;
        
        if (stores.length === 0) {
            grid.innerHTML = '';
            emptyState.style.display = 'flex';
            return;
        }
        
        emptyState.style.display = 'none';
        
        // Renderizar lojas
        grid.innerHTML = stores.map(store => createStoreCard(store)).join('');
        
        // Reconfigurar event listeners
        setupStoreCardListeners();
        setupLazyLoading();
    }
    
    function createStoreCard(store) {
        const distanceBadge = store.distancia ? 
            `<div class="store-distance-badge">${parseFloat(store.distancia).toFixed(1)} km</div>` : '';
        
        const logo = store.logo ? 
            `<img src="${store.logo}" alt="Logo ${store.nome_fantasia}" loading="lazy">` :
            `<div class="store-logo-placeholder">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>`;
        
        const address = store.endereco_completo ? 
            `<div class="pwa-store-address">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <circle cx="12" cy="11" r="3"/>
                </svg>
                ${store.endereco_completo}
            </div>` : '';
        
        const balance = store.saldo_disponivel > 0 ? 
            `<div class="pwa-store-balance">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                Você tem R$ ${parseFloat(store.saldo_disponivel).toFixed(2).replace('.', ',')}
            </div>` : '';
        
        return `
            <div class="pwa-store-card" data-store-id="${store.id}">
                ${distanceBadge}
                
                <button class="pwa-favorite-btn ${store.e_favorita ? 'active' : ''}" 
                        data-store-id="${store.id}"
                        data-is-favorite="${store.e_favorita ? '1' : '0'}"
                        aria-label="${store.e_favorita ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}">
                    <svg class="heart-outline" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 8.5c0-2.485-2.239-4.5-5-4.5-1.964 0-3.612 1.043-4 2.5C11.612 5.043 9.964 4 8 4 5.239 4 3 6.015 3 8.5c0 6 9 12 9 12s9-6 9-12z"/>
                    </svg>
                    <svg class="heart-filled" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M21 8.5c0-2.485-2.239-4.5-5-4.5-1.964 0-3.612 1.043-4 2.5C11.612 5.043 9.964 4 8 4 5.239 4 3 6.015 3 8.5c0 6 9 12 9 12s9-6 9-12z"/>
                    </svg>
                </button>
                
                <div class="pwa-store-logo">
                    ${logo}
                </div>
                
                <div class="pwa-store-info">
                    <h3 class="pwa-store-name">${store.nome_fantasia}</h3>
                    
                    <div class="pwa-store-category">${store.categoria}</div>
                    
                    <div class="pwa-cashback-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                        </svg>
                        ${parseFloat(store.porcentagem_cashback).toFixed(1)}% cashback
                    </div>
                    
                    ${address}
                    ${balance}
                </div>
                
                <div class="pwa-store-actions">
                    <button class="pwa-btn-primary pwa-btn-sm" onclick="viewStoreDetails(${store.id})">
                        Ver loja
                    </button>
                </div>
            </div>
        `;
    }
    
    function setupStoreCardListeners() {
        // Reconfigurar favoritos
        document.querySelectorAll('.pwa-favorite-btn').forEach(btn => {
            btn.removeEventListener('click', toggleFavorite); // Remove listener anterior
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleFavorite(this);
            });
        });
    }
    
    // ==================== MODAL DE FILTROS ====================
    function setupFiltersModal() {
        const modal = document.getElementById('filtersModal');
        const closeBtn = modal.querySelector('.pwa-modal-close');
        const backdrop = modal.querySelector('.pwa-modal-backdrop');
        const clearBtn = modal.querySelector('.pwa-btn-outline');
        
        // Fechar modal
        [closeBtn, backdrop].forEach(element => {
            element.addEventListener('click', closeFiltersModal);
        });
        
        // Limpar filtros
        clearBtn.addEventListener('click', clearFilters);
        
        // Range sliders
        const cashbackRange = document.getElementById('cashbackRange');
        const distanceRange = document.getElementById('distanceRange');
        
        cashbackRange.addEventListener('input', function() {
            document.getElementById('cashbackValue').textContent = this.value + '%';
        });
        
        distanceRange.addEventListener('input', function() {
            document.getElementById('distanceValue').textContent = this.value + 'km';
        });
    }
    
    function openFiltersModal() {
        const modal = document.getElementById('filtersModal');
        modal.style.display = 'flex';
        modal.offsetHeight; // Force reflow
        modal.classList.add('show');
        
        // Prevenir scroll do body
        document.body.style.overflow = 'hidden';
        
        // Carregar valores salvos
        loadSavedFilters();
    }
    
    function closeFiltersModal() {
        const modal = document.getElementById('filtersModal');
        modal.classList.remove('show');
        
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }
    
    function loadSavedFilters() {
        const savedFilters = JSON.parse(localStorage.getItem('storeFilters') || '{}');
        
        if (savedFilters.sort) {
            document.getElementById('sortFilter').value = savedFilters.sort;
        }
        
        if (savedFilters.cashbackMin) {
            const range = document.getElementById('cashbackRange');
            range.value = savedFilters.cashbackMin;
            document.getElementById('cashbackValue').textContent = savedFilters.cashbackMin + '%';
        }
        
        if (savedFilters.distanceMax) {
            const range = document.getElementById('distanceRange');
            range.value = savedFilters.distanceMax;
            document.getElementById('distanceValue').textContent = savedFilters.distanceMax + 'km';
        }
        
        document.getElementById('onlyFavorites').checked = savedFilters.onlyFavorites || false;
        document.getElementById('withBalance').checked = savedFilters.withBalance || false;
    }
    
    function applySavedFilters() {
        const savedCategory = localStorage.getItem('selectedCategory');
        if (savedCategory) {
            selectCategory(savedCategory);
        }
    }
    
    function applyFilters() {
        const filters = {
            sort: document.getElementById('sortFilter').value,
            cashbackMin: document.getElementById('cashbackRange').value,
            distanceMax: document.getElementById('distanceRange').value,
            onlyFavorites: document.getElementById('onlyFavorites').checked,
            withBalance: document.getElementById('withBalance').checked
        };
        
        // Salvar filtros
        localStorage.setItem('storeFilters', JSON.stringify(filters));
        
        // Aplicar filtros
        const searchQuery = document.getElementById('searchInput').value.trim();
        performSearchWithFilters(searchQuery, filters);
        
        closeFiltersModal();
        showToast('Filtros aplicados!', 'success');
    }
    
    function clearFilters() {
        // Resetar inputs
        document.getElementById('sortFilter').value = 'nome';
        document.getElementById('cashbackRange').value = '0';
        document.getElementById('distanceRange').value = '50';
        document.getElementById('onlyFavorites').checked = false;
        document.getElementById('withBalance').checked = false;
        
        // Resetar labels
        document.getElementById('cashbackValue').textContent = '0%';
        document.getElementById('distanceValue').textContent = '50km';
        
        // Limpar storage
        localStorage.removeItem('storeFilters');
    }
    
    function clearAllFilters() {
        // Limpar busca
        document.getElementById('searchInput').value = '';
        document.getElementById('clearSearch').style.display = 'none';
        
        // Resetar categoria
        selectCategory('todas');
        
        // Limpar filtros avançados
        clearFilters();
        
        // Limpar localização
        if (userLocation) {
            clearUserLocation();
        }
        
        // Recarregar lojas
        performSearch('');
    }
    
    function getDistanceFilter() {
        const savedFilters = JSON.parse(localStorage.getItem('storeFilters') || '{}');
        return savedFilters.distanceMax || 50;
    }
    
    // ==================== PULL TO REFRESH ====================
    function setupPullToRefresh() {
        let isRefreshing = false;
        let startY = 0;
        let currentY = 0;
        let pullDistance = 0;
        const threshold = 100; // pixels para ativar refresh
        
        const main = document.querySelector('.pwa-main');
        const pullRefresh = document.getElementById('pullRefresh');
        
        main.addEventListener('touchstart', function(e) {
            if (main.scrollTop === 0) {
                startY = e.touches[0].clientY;
            }
        }, { passive: true });
        
        main.addEventListener('touchmove', function(e) {
            if (isRefreshing || main.scrollTop > 0) return;
            
            currentY = e.touches[0].clientY;
            pullDistance = currentY - startY;
            
            if (pullDistance > 0) {
                e.preventDefault();
                
                const opacity = Math.min(pullDistance / threshold, 1);
                pullRefresh.style.display = 'flex';
                pullRefresh.style.opacity = opacity;
                pullRefresh.style.transform = `translateY(${Math.min(pullDistance, threshold)}px)`;
                
                if (pullDistance >= threshold) {
                    pullRefresh.classList.add('active');
                } else {
                    pullRefresh.classList.remove('active');
                }
            }
        }, { passive: false });
        
        main.addEventListener('touchend', function(e) {
            if (pullDistance >= threshold && !isRefreshing) {
                performRefresh();
            } else {
                resetPullRefresh();
            }
        });
        
        function performRefresh() {
            isRefreshing = true;
            pullRefresh.classList.add('refreshing');
            
            // Recarregar dados
            const searchQuery = document.getElementById('searchInput').value.trim();
            performSearch(searchQuery).finally(() => {
                setTimeout(() => {
                    resetPullRefresh();
                    isRefreshing = false;
                    showToast('Lojas atualizadas!', 'success');
                }, 500);
            });
        }
        
        function resetPullRefresh() {
            pullRefresh.style.transform = 'translateY(0)';
            pullRefresh.style.opacity = '0';
            pullRefresh.classList.remove('active', 'refreshing');
            
            setTimeout(() => {
                if (pullRefresh.style.opacity === '0') {
                    pullRefresh.style.display = 'none';
                }
            }, 300);
            
            pullDistance = 0;
        }
    }
    
    // ==================== LAZY LOADING ====================
    function setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
    
    // ==================== UTILITY FUNCTIONS ====================
    function showLoadingState() {
        document.getElementById('loadingState').style.display = 'flex';
        document.getElementById('storesGrid').style.opacity = '0.5';
    }
    
    function hideLoadingState() {
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('storesGrid').style.opacity = '1';
    }
    
    function showError(message) {
        console.error('Erro:', message);
        showToast(message, 'error');
    }
    
    function showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        const toastMessage = toast.querySelector('.toast-message');
        const toastIcon = toast.querySelector('.toast-icon');
        
        // Definir ícone baseado no tipo
        let iconSvg = '';
        switch(type) {
            case 'success':
                iconSvg = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
                break;
            case 'error':
                iconSvg = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
                break;
            case 'info':
            default:
                iconSvg = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
        }
        
        toastIcon.innerHTML = iconSvg;
        toastMessage.textContent = message;
        
        toast.className = `pwa-toast ${type}`;
        toast.style.display = 'flex';
        
        // Auto hide após 3 segundos
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }
    
    function viewStoreDetails(storeId) {
        // Implementar navegação para detalhes da loja
        window.location.href = `../../views/stores/details.php?id=${storeId}`;
    }
    
    // ==================== PERFORMSEARCH WITH FILTERS ====================
    function performSearchWithFilters(query, filters) {
        // Esta função será implementada para aplicar filtros avançados
        // Por enquanto, usa a busca normal
        performSearch(query);
    }
    </script>

    <!-- Footer PWA (Bottom Navigation) -->
    <?php include '../../views/components/pwa-bottom-nav.php'; ?>
</body>
</html>