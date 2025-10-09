<?php
// views/stores/details.php
// Incluir arquivos de configuração
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../controllers/ClientController.php';
require_once '../../controllers/StoreController.php';

// Iniciar sessão
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$isClient = $isLoggedIn && $_SESSION['user_type'] === USER_TYPE_CLIENT;
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Obter ID da loja da URL
$storeId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($storeId <= 0) {
    header('Location: ' . CLIENT_STORES_URL);
    exit;
}

// Obter detalhes da loja
$store = null;
$error = '';

try {
    // Usar StoreController para obter detalhes da loja
    if ($isClient) {
        // Se for um cliente, obter informações específicas para o cliente
        $result = ClientController::getPartnerStores($userId, ['loja_id' => $storeId], 1);
        if ($result['status'] && !empty($result['data']['lojas'])) {
            $store = $result['data']['lojas'][0];
        }
    } else {
        // Se for um visitante ou admin, obter informações gerais
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM lojas WHERE id = :id AND status = 'aprovado'");
        $stmt->bindParam(':id', $storeId);
        $stmt->execute();
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obter endereço da loja
        if ($store) {
            $addrStmt = $db->prepare("SELECT * FROM lojas_endereco WHERE loja_id = :loja_id LIMIT 1");
            $addrStmt->bindParam(':loja_id', $storeId);
            $addrStmt->execute();
            $store['endereco'] = $addrStmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$store) {
        $error = 'Loja não encontrada ou não disponível.';
    }
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados da loja. Tente novamente.';
    error_log('Erro ao obter detalhes da loja: ' . $e->getMessage());
}

// Processar ação de adicionar aos favoritos
$favoriteMessage = '';
if ($isClient && isset($_POST['toggleFavorite'])) {
    $favorite = isset($_POST['favorite']) ? (bool)$_POST['favorite'] : true;
    $result = ClientController::toggleFavoriteStore($userId, $storeId, $favorite);
    $favoriteMessage = $result['message'];
    
    // Atualizar estado favorito
    if ($result['status']) {
        $store['is_favorite'] = $favorite;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $store ? htmlspecialchars($store['nome_fantasia']) : 'Detalhes da Loja'; ?> - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <link rel="stylesheet" href="../../assets/css/views/stores/details.css">
    
</head>
<body>
    <!-- Incluir navbar -->
    <?php include_once '../components/navbar.php'; ?>
    
    <div class="container">
        <a href="<?php echo CLIENT_STORES_URL; ?>" class="back-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Voltar para lojas parceiras
        </a>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($favoriteMessage)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($favoriteMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($store): ?>
            <div class="store-header">
                <div class="store-logo">
                    <?php 
                    if (!empty($store['logo'])) {
                        echo '<img src="' . htmlspecialchars($store['logo']) . '" alt="' . htmlspecialchars($store['nome_fantasia']) . '">';
                    } else {
                        echo strtoupper(substr($store['nome_fantasia'], 0, 1));
                    }
                    ?>
                </div>
                
                <div class="store-info">
                    <h1 class="store-name"><?php echo htmlspecialchars($store['nome_fantasia']); ?></h1>
                    <span class="store-category"><?php echo htmlspecialchars($store['categoria'] ?? 'Outros'); ?></span>
                    
                    <div class="store-actions">
                        <?php if ($isClient): ?>
                            <form method="post" action="" style="display: inline;">
                                <input type="hidden" name="favorite" value="<?php echo isset($store['is_favorite']) && $store['is_favorite'] ? '0' : '1'; ?>">
                                <button type="submit" name="toggleFavorite" class="btn btn-outline btn-favorite <?php echo isset($store['is_favorite']) && $store['is_favorite'] ? 'active' : ''; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                    <?php echo isset($store['is_favorite']) && $store['is_favorite'] ? 'Remover dos Favoritos' : 'Adicionar aos Favoritos'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if (!empty($store['website'])): ?>
                            <a href="<?php echo htmlspecialchars($store['website']); ?>" target="_blank" class="btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                                Visitar Site
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="cashback-card">
                <div class="cashback-info">
                    <h2>Cashback disponível nesta loja</h2>
                    <p>Ganhe dinheiro de volta em todas as suas compras</p>
                </div>
                
                <div class="cashback-value">
                    <span class="cashback-percent"><?php echo number_format($store['porcentagem_cashback'], 2, ',', '.'); ?>%</span>
                </div>
            </div>
            
            <div class="store-details">
                <div class="card">
                    <h3 class="card-title">Informações da Loja</h3>
                    
                    <div class="details-item">
                        <div class="details-label">Razão Social</div>
                        <div class="details-value"><?php echo htmlspecialchars($store['razao_social']); ?></div>
                    </div>
                    
                    <div class="details-item">
                        <div class="details-label">CNPJ</div>
                        <div class="details-value">
                            <?php
                            $cnpj = preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $store['cnpj']);
                            echo htmlspecialchars($cnpj);
                            ?>
                        </div>
                    </div>
                    
                    <div class="details-item">
                        <div class="details-label">Email</div>
                        <div class="details-value"><?php echo htmlspecialchars($store['email']); ?></div>
                    </div>
                    
                    <div class="details-item">
                        <div class="details-label">Telefone</div>
                        <div class="details-value"><?php echo htmlspecialchars($store['telefone']); ?></div>
                    </div>
                    
                    <?php if (!empty($store['descricao'])): ?>
                        <div class="details-item">
                            <div class="details-label">Descrição</div>
                            <div class="details-value"><?php echo nl2br(htmlspecialchars($store['descricao'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isClient && isset($store['cashback_recebido'])): ?>
                        <div class="details-item">
                            <div class="details-label">Cashback Total Recebido</div>
                            <div class="details-value">R$ <?php echo number_format($store['cashback_recebido'], 2, ',', '.'); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isClient && isset($store['compras_realizadas'])): ?>
                        <div class="details-item">
                            <div class="details-label">Compras Realizadas</div>
                            <div class="details-value"><?php echo $store['compras_realizadas']; ?></div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h3 class="card-title">Endereço</h3>
                    
                    <?php if (isset($store['endereco']) && $store['endereco']): ?>
                        <div class="details-item">
                            <div class="details-label">Endereço Completo</div>
                            <div class="details-value">
                                <?php 
                                $endereco = $store['endereco'];
                                echo htmlspecialchars($endereco['logradouro'] . ', ' . $endereco['numero']);
                                
                                if (!empty($endereco['complemento'])) {
                                    echo ' - ' . htmlspecialchars($endereco['complemento']);
                                }
                                
                                echo '<br>' . htmlspecialchars($endereco['bairro'] . ', ' . $endereco['cidade'] . ' - ' . $endereco['estado']);
                                echo '<br>CEP: ' . htmlspecialchars($endereco['cep']);
                                ?>
                            </div>
                        </div>
                        
                        <div class="map-container">
                            <p>Mapa indisponível</p>
                        </div>
                    <?php else: ?>
                        <p>Endereço não disponível</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Script para possível funcionalidade de mapas, se disponível
    </script>
</body>
</html>