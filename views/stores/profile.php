<?php
// views/stores/profile.php - VERSÃO COM PADRÃO PRG
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../utils/Validator.php';
require_once '../../utils/Security.php';

// Iniciar sessão
session_start();
$activeMenu = 'perfil';

// Verificar se o usuário está logado e é uma loja
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'loja') {
    header("Location: " . LOGIN_URL . "?error=acesso_restrito");
    exit;
}

// Obter ID do usuário logado
$userId = $_SESSION['user_id'];

// Obter dados da loja associada ao usuário
$db = Database::getConnection();

// Função para buscar dados atualizados da loja com validações
function buscarDadosLoja($db, $userId) {
    try {
        // Primeiro, buscar os dados básicos da loja e usuário
        $storeQuery = $db->prepare("
            SELECT l.id, l.nome_fantasia, l.razao_social, l.cnpj, l.telefone, 
                   l.website, l.descricao, l.porcentagem_cashback, l.status, l.data_cadastro,
                   u.email as usuario_email, u.nome as usuario_nome
            FROM lojas l
            INNER JOIN usuarios u ON l.usuario_id = u.id
            WHERE l.usuario_id = :usuario_id
        ");
        $storeQuery->bindParam(':usuario_id', $userId, PDO::PARAM_INT);
        $storeQuery->execute();
        
        $store = $storeQuery->fetch(PDO::FETCH_ASSOC);
        
        // Verificar se encontrou a loja e se tem ID válido
        if (!$store || !isset($store['id']) || empty($store['id']) || $store['id'] <= 0) {
            error_log("ERRO: Loja não encontrada ou ID inválido para usuário $userId");
            return false;
        }
        
        // Agora buscar o endereço separadamente
        $addressQuery = $db->prepare("
            SELECT cep, logradouro, numero, complemento, bairro, cidade, estado
            FROM lojas_endereco 
            WHERE loja_id = :loja_id
        ");
        $addressQuery->bindParam(':loja_id', $store['id'], PDO::PARAM_INT);
        $addressQuery->execute();
        
        $address = $addressQuery->fetch(PDO::FETCH_ASSOC);
        
        // Mesclar os dados do endereço com os dados da loja
        if ($address) {
            $store = array_merge($store, $address);
        }
        
        return $store;
        
    } catch (PDOException $e) {
        error_log("Erro PDO ao buscar dados da loja: " . $e->getMessage());
        return false;
    }
}

// Buscar dados iniciais com validação robusta
$store = buscarDadosLoja($db, $userId);

// Validação mais rigorosa dos dados da loja
if (!$store || !isset($store['id']) || empty($store['id'])) {
    error_log("ERRO CRÍTICO: Loja não encontrada ou dados inválidos para usuário $userId");
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('Sua conta não está associada a nenhuma loja válida. Entre em contato com o suporte.'));
    exit;
}

// Garantir que storeId é um inteiro válido
$storeId = (int)$store['id'];
if ($storeId <= 0) {
    error_log("ERRO CRÍTICO: ID da loja inválido - valor: " . $store['id']);
    header('Location: ' . LOGIN_URL . '?error=' . urlencode('ID da loja inválido. Entre em contato com o suporte.'));
    exit;
}

// Processar formulário se enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Validar storeId antes de qualquer operação
    if ($storeId <= 0) {
        $_SESSION['profile_error'] = 'ID da loja inválido. Não é possível continuar.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    try {
        switch ($action) {
            case 'update_contact':
                // Atualizar informações de contato
                $telefone = preg_replace('/\D/', '', trim($_POST['telefone'] ?? ''));
                $website = trim($_POST['website'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                
                // Validações
                if (empty($telefone)) {
                    throw new Exception('Telefone é obrigatório.');
                }
                
                if (strlen($telefone) < 10 || strlen($telefone) > 11) {
                    throw new Exception('Telefone inválido. Digite um telefone com DDD.');
                }
                
                if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
                    throw new Exception('Website deve ser uma URL válida.');
                }
                
                // Atualizar no banco com binding explícito
                $updateStmt = $db->prepare("
                    UPDATE lojas 
                    SET telefone = :telefone, website = :website, descricao = :descricao
                    WHERE id = :id
                ");
                
                $updateStmt->bindParam(':telefone', $telefone, PDO::PARAM_STR);
                $updateStmt->bindParam(':website', $website, PDO::PARAM_STR);
                $updateStmt->bindParam(':descricao', $descricao, PDO::PARAM_STR);
                $updateStmt->bindParam(':id', $storeId, PDO::PARAM_INT);
                
                if (!$updateStmt->execute()) {
                    throw new Exception('Erro ao atualizar informações de contato.');
                }
                
                // SOLUÇÃO PRG: Redirecionar com mensagem de sucesso na sessão
                $_SESSION['profile_success'] = 'Informações de contato atualizadas com sucesso!';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                
            case 'update_address':
                // Validação e logs detalhados para endereço
                error_log("INICIANDO atualização de endereço para loja ID: $storeId");
                
                $cep = preg_replace('/\D/', '', trim($_POST['cep'] ?? ''));
                $logradouro = trim($_POST['logradouro'] ?? '');
                $numero = trim($_POST['numero'] ?? '');
                $complemento = trim($_POST['complemento'] ?? '');
                $bairro = trim($_POST['bairro'] ?? '');
                $cidade = trim($_POST['cidade'] ?? '');
                $estado = trim($_POST['estado'] ?? '');
                
                // Validações
                if (strlen($cep) != 8) {
                    throw new Exception('CEP deve ter 8 dígitos.');
                }
                
                if (empty($logradouro) || empty($numero) || empty($bairro) || empty($cidade) || empty($estado)) {
                    throw new Exception('Todos os campos de endereço são obrigatórios, exceto complemento.');
                }
                
                // Verificar se já existe endereço com log detalhado
                $checkAddressStmt = $db->prepare("SELECT id FROM lojas_endereco WHERE loja_id = :loja_id");
                $checkAddressStmt->bindParam(':loja_id', $storeId, PDO::PARAM_INT);
                
                if (!$checkAddressStmt->execute()) {
                    throw new Exception('Erro ao verificar endereço existente.');
                }
                
                $enderecoExiste = $checkAddressStmt->rowCount() > 0;
                error_log("Endereço existe para loja $storeId: " . ($enderecoExiste ? 'SIM' : 'NÃO'));
                
                // Preparar statement com validação de parâmetros
                if ($enderecoExiste) {
                    // Atualizar endereço existente
                    $updateAddressStmt = $db->prepare("
                        UPDATE lojas_endereco 
                        SET cep = :cep, logradouro = :logradouro, numero = :numero, 
                            complemento = :complemento, bairro = :bairro, cidade = :cidade, estado = :estado
                        WHERE loja_id = :loja_id
                    ");
                    error_log("Preparando UPDATE para loja ID: $storeId");
                } else {
                    // Inserir novo endereço
                    $updateAddressStmt = $db->prepare("
                        INSERT INTO lojas_endereco (loja_id, cep, logradouro, numero, complemento, bairro, cidade, estado)
                        VALUES (:loja_id, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado)
                    ");
                    error_log("Preparando INSERT para loja ID: $storeId");
                }
                
                // Binding com validação de tipos e logs
                $updateAddressStmt->bindParam(':loja_id', $storeId, PDO::PARAM_INT);
                $updateAddressStmt->bindParam(':cep', $cep, PDO::PARAM_STR);
                $updateAddressStmt->bindParam(':logradouro', $logradouro, PDO::PARAM_STR);
                $updateAddressStmt->bindParam(':numero', $numero, PDO::PARAM_STR);
                $updateAddressStmt->bindParam(':complemento', $complemento, PDO::PARAM_STR);
                $updateAddressStmt->bindParam(':bairro', $bairro, PDO::PARAM_STR);
                $updateAddressStmt->bindParam(':cidade', $cidade, PDO::PARAM_STR);
                $updateAddressStmt->bindParam(':estado', $estado, PDO::PARAM_STR);
                
                // Executar com tratamento de erro
                if (!$updateAddressStmt->execute()) {
                    $errorInfo = $updateAddressStmt->errorInfo();
                    error_log("ERRO SQL ao salvar endereço: " . implode(' - ', $errorInfo));
                    throw new Exception('Erro ao salvar endereço no banco de dados: ' . $errorInfo[2]);
                }
                
                error_log("Endereço salvo com SUCESSO para loja ID: $storeId");
                
                // SOLUÇÃO PRG: Redirecionar com mensagem de sucesso na sessão
                $_SESSION['profile_success'] = 'Endereço atualizado com sucesso!';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                
            case 'change_password':
                // Alterar senha
                $senhaAtual = $_POST['senha_atual'] ?? '';
                $novaSenha = $_POST['nova_senha'] ?? '';
                $confirmarSenha = $_POST['confirmar_senha'] ?? '';
                
                // Validações
                if (empty($senhaAtual) || empty($novaSenha) || empty($confirmarSenha)) {
                    throw new Exception('Todos os campos de senha são obrigatórios.');
                }
                
                if ($novaSenha !== $confirmarSenha) {
                    throw new Exception('A confirmação de senha não confere.');
                }
                
                if (strlen($novaSenha) < 8) {
                    throw new Exception('Nova senha deve ter pelo menos 8 caracteres.');
                }
                
                // Verificar senha atual
                $checkPasswordStmt = $db->prepare("SELECT senha_hash FROM usuarios WHERE id = :id");
                $checkPasswordStmt->bindParam(':id', $userId, PDO::PARAM_INT);
                $checkPasswordStmt->execute();
                
                if ($checkPasswordStmt->rowCount() === 0) {
                    throw new Exception('Usuário não encontrado.');
                }
                
                $currentPasswordHash = $checkPasswordStmt->fetchColumn();
                
                if (!password_verify($senhaAtual, $currentPasswordHash)) {
                    throw new Exception('Senha atual incorreta.');
                }
                
                // Atualizar senha
                $newPasswordHash = password_hash($novaSenha, PASSWORD_DEFAULT, ['cost' => 12]);
                $updatePasswordStmt = $db->prepare("UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id");
                $updatePasswordStmt->bindParam(':senha_hash', $newPasswordHash, PDO::PARAM_STR);
                $updatePasswordStmt->bindParam(':id', $userId, PDO::PARAM_INT);
                
                if (!$updatePasswordStmt->execute()) {
                    throw new Exception('Erro ao atualizar senha.');
                }
                
                // SOLUÇÃO PRG: Redirecionar com mensagem de sucesso na sessão
                $_SESSION['profile_success'] = 'Senha alterada com sucesso!';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                
            default:
                throw new Exception('Ação inválida.');
        }
        
    } catch (Exception $e) {
        // SOLUÇÃO PRG: Redirecionar com mensagem de erro na sessão
        $_SESSION['profile_error'] = $e->getMessage();
        error_log("Erro na operação $action: " . $e->getMessage());
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        // SOLUÇÃO PRG: Redirecionar com mensagem de erro na sessão
        $_SESSION['profile_error'] = 'Erro interno do banco de dados. Tente novamente.';
        error_log('Erro PDO no profile da loja: ' . $e->getMessage());
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// SOLUÇÃO PRG: Recuperar mensagens da sessão e limpar
$success = '';
$error = '';

if (isset($_SESSION['profile_success'])) {
    $success = $_SESSION['profile_success'];
    unset($_SESSION['profile_success']); // Limpar da sessão
}

if (isset($_SESSION['profile_error'])) {
    $error = $_SESSION['profile_error'];
    unset($_SESSION['profile_error']); // Limpar da sessão
}

// Recarregar dados da loja após possíveis alterações
$store = buscarDadosLoja($db, $userId);
$storeId = (int)$store['id'];
 
// Definir menu ativo
$activeMenu = 'profile';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil da Loja - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <?php
    // Determinar qual CSS carregar baseado no campo senat do usuário
    $profileCssFile = 'profile.css'; // CSS padrão
    $sidebarCssFile = 'sidebar-lojista.css'; // CSS da sidebar padrão

    if (isset($_SESSION['user_senat']) && ($_SESSION['user_senat'] === 'sim' || $_SESSION['user_senat'] === 'Sim')) {
        $profileCssFile = 'profile_sest.css'; // CSS para usuários senat=sim
        $sidebarCssFile = 'sidebar-lojista_sest.css'; // CSS da sidebar para usuários senat=sim
    }
    ?>
    <link rel="stylesheet" href="../../assets/css/views/stores/<?php echo htmlspecialchars($profileCssFile); ?>">
    <link rel="stylesheet" href="/assets/css/<?php echo htmlspecialchars($sidebarCssFile); ?>">

</head>
<body>
    <!-- Incluir sidebar da loja -->
    <?php include '../../views/components/sidebar-lojista-responsiva.php'; ?>
    
    <div class="main-content" id="mainContent">
        <!-- Cabeçalho da página -->
        <div class="page-header">
            <h1 class="page-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Perfil da Loja
            </h1>
            <p class="page-subtitle">Gerencie as informações da sua loja e mantenha seus dados sempre atualizados</p>
        </div>
        
        <!-- Alertas de sucesso/erro -->
        <?php if (!empty($success)): ?>
            <div class="alert success">
                <div class="alert-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert error">
                <div class="alert-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- DEBUG: Mostrar ID da loja temporariamente (remover em produção) -->
        <?php if (isset($_GET['debug'])): ?>
            <div class="alert" style="background: #e3f2fd; border-color: #2196f3; color: #0d47a1;">
                <strong>DEBUG:</strong> Store ID: <?php echo $storeId; ?> | User ID: <?php echo $userId; ?>
            </div>
        <?php endif; ?>
        
        <!-- Informações básicas da loja (não editáveis) -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Informações da Loja
                </h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nome Fantasia</div>
                        <div class="info-value"><?php echo htmlspecialchars($store['nome_fantasia']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Razão Social</div>
                        <div class="info-value"><?php echo htmlspecialchars($store['razao_social']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">CNPJ</div>
                        <div class="info-value">
                            <?php 
                            $cnpj = $store['cnpj'];
                            if (strlen($cnpj) === 14) {
                                $formattedCnpj = preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $cnpj);
                                echo htmlspecialchars($formattedCnpj);
                            } else {
                                echo htmlspecialchars($cnpj);
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($store['usuario_email']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $store['status']; ?>">
                                <?php echo ucfirst($store['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Porcentagem de Cashback</div>
                        <div class="info-value"><?php echo number_format($store['porcentagem_cashback'], 2, ',', '.'); ?>%</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Data de Cadastro</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($store['data_cadastro'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Seções editáveis -->
        <div class="form-sections">
            <!-- Seção 1: Informações de Contato -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        Informações de Contato
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_contact">
                        
                        <div class="form-group">
                            <label for="telefone" class="form-label">Telefone *</label>
                            <input type="text" id="telefone" name="telefone" class="form-input" 
                                   value="<?php echo htmlspecialchars($store['telefone']); ?>" required 
                                   placeholder="(11) 99999-9999">
                            <small class="form-help">Formato: (XX) XXXXX-XXXX</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" id="website" name="website" class="form-input" 
                                   value="<?php echo htmlspecialchars($store['website'] ?? ''); ?>" 
                                   placeholder="https://www.suaempresa.com.br">
                            <small class="form-help">URL completa do seu site (opcional)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="descricao" class="form-label">Descrição da Loja</label>
                            <textarea id="descricao" name="descricao" class="form-textarea" rows="4" 
                                      placeholder="Descreva sua loja, produtos e serviços..."><?php echo htmlspecialchars($store['descricao'] ?? ''); ?></textarea>
                            <small class="form-help">Descreva sua loja para que os clientes a conheçam melhor</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Salvar Informações
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Seção 2: Endereço -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Endereço
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_address">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cep" class="form-label">CEP *</label>
                                <input type="text" id="cep" name="cep" class="form-input" 
                                       value="<?php echo htmlspecialchars($store['cep'] ?? ''); ?>" required 
                                       placeholder="00000-000" maxlength="9">
                            </div>
                            
                            <div class="form-group">
                                <label for="estado" class="form-label">Estado *</label>
                                <select id="estado" name="estado" class="form-select" required>
                                    <option value="">Selecione o estado</option>
                                    <?php
                                    $estados = [
                                        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                                        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
                                        'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                                        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                                        'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
                                    ];
                                    foreach ($estados as $sigla => $nome) {
                                        $selected = (($store['estado'] ?? '') === $sigla) ? 'selected' : '';
                                        echo "<option value=\"$sigla\" $selected>$nome</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cidade" class="form-label">Cidade *</label>
                            <input type="text" id="cidade" name="cidade" class="form-input" 
                                   value="<?php echo htmlspecialchars($store['cidade'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bairro" class="form-label">Bairro *</label>
                            <input type="text" id="bairro" name="bairro" class="form-input" 
                                   value="<?php echo htmlspecialchars($store['bairro'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="logradouro" class="form-label">Logradouro *</label>
                            <input type="text" id="logradouro" name="logradouro" class="form-input" 
                                   value="<?php echo htmlspecialchars($store['logradouro'] ?? ''); ?>" required 
                                   placeholder="Rua, Avenida, etc.">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="numero" class="form-label">Número *</label>
                                <input type="text" id="numero" name="numero" class="form-input" 
                                       value="<?php echo htmlspecialchars($store['numero'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="complemento" class="form-label">Complemento</label>
                                <input type="text" id="complemento" name="complemento" class="form-input" 
                                       value="<?php echo htmlspecialchars($store['complemento'] ?? ''); ?>" 
                                       placeholder="Sala, Andar, etc.">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Salvar Endereço
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Seção 3: Alteração de Senha (largura completa) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <circle cx="12" cy="16" r="1"></circle>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Alterar Senha
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" style="max-width: 500px;">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="senha_atual" class="form-label">Senha Atual *</label>
                        <input type="password" id="senha_atual" name="senha_atual" class="form-input" required>
                        <small class="form-help">Digite sua senha atual para confirmar a alteração</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="nova_senha" class="form-label">Nova Senha *</label>
                        <input type="password" id="nova_senha" name="nova_senha" class="form-input" required>
                        <small class="form-help">Mínimo de 8 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmar_senha" class="form-label">Confirmar Nova Senha *</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-input" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <circle cx="12" cy="16" r="1"></circle>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Alterar Senha
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Máscara para CEP (apenas formatação, sem busca automática)
        const cepInput = document.getElementById('cep');
        if (cepInput) {
            cepInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            });
        }
        
        // Máscara para telefone
        const telefoneInput = document.getElementById('telefone');
        if (telefoneInput) {
            telefoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 10) {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }
                e.target.value = value;
            });
        }
        
        // Validação de confirmação de senha
        const passwordForm = document.querySelector('input[name="action"][value="change_password"]').closest('form');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const novaSenha = document.getElementById('nova_senha').value;
                const confirmarSenha = document.getElementById('confirmar_senha').value;
                
                if (novaSenha !== confirmarSenha) {
                    e.preventDefault();
                    alert('A confirmação de senha não confere.');
                    return false;
                }
                
                if (novaSenha.length < 8) {
                    e.preventDefault();
                    alert('A nova senha deve ter pelo menos 8 caracteres.');
                    return false;
                }
            });
        }
        
        // Loading nos botões ao submeter formulário
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span>Salvando...</span>';
                    btn.disabled = true;
                    
                    // Restaurar após 5 segundos (caso não recarregue a página)
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }, 5000);
                }
            });
        });
        
        // Auto-hide dos alertas após 5 segundos
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });
    });
    </script>
    <script src="/assets/js/sidebar-lojista.js"></script>
</body>
</html>