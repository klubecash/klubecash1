<?php
/**
 * Página de Gestão de Funcionários - Sistema Klube Cash
 * 
 * Esta página foi expandida para trabalhar com um sistema de controle de acesso
 * granular, permitindo que tanto lojistas quanto funcionários específicos
 * (do tipo gerente) possam gerenciar a equipe.
 * 
 * Localização: views/stores/employees.php
 * Estrutura: Mantém compatibilidade total com o sistema existente
 */

// Definir o menu ativo para a sidebar
$activeMenu = 'funcionarios';

// Incluir dependências necessárias para o funcionamento
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/StoreController.php';

// Iniciar sessão apenas se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// NOVA VERIFICAÇÃO DE ACESSO
AuthController::requireStoreAccess();

// Verificar se pode gerenciar funcionários (apenas lojistas e gerentes)
if (!AuthController::canManageEmployees()) {
    header("Location: " . STORE_DASHBOARD_URL . "?error=permission_denied");
    exit;
}

// Obter dados da loja
$storeId = AuthController::getStoreId();
$storeData = AuthController::getStoreData();

// Informações do usuário atual
$isLojista = AuthController::isStore();
$isGerente = AuthController::isEmployee() && $_SESSION['employee_subtype'] === EMPLOYEE_TYPE_MANAGER;
$accessLevel = $isLojista ? 'total' : 'limitado';
$userName = $_SESSION['user_name'] ?? 'Usuário';
$userType = $_SESSION['user_type'];
$userId = $_SESSION['user_id'];
$subtipoFuncionario = $_SESSION['employee_subtype'] ?? '';

// Registrar o acesso para fins de auditoria
error_log("Acesso à gestão de funcionários - Usuário: {$userName} (ID: {$userId}), Tipo: {$userType}, Nível: {$accessLevel}");

// === PROCESSAMENTO DE FILTROS E PAGINAÇÃO ===
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$filters = [];

// Processar filtros vindos da URL
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    if (!empty($_GET['subtipo']) && $_GET['subtipo'] !== 'todos') {
        $filters['subtipo'] = $_GET['subtipo'];
    }
    
    if (!empty($_GET['status']) && $_GET['status'] !== 'todos') {
        $filters['status'] = $_GET['status'];
    }
    
    if (!empty($_GET['busca'])) {
        $filters['busca'] = trim($_GET['busca']);
    }
}

// === CARREGAMENTO DE DADOS COM TRATAMENTO DE ERROS ===
try {
    // Chamar o controller para obter dados dos funcionários
    $result = StoreController::getEmployees($filters, $page);
    
    // Verificar se a operação foi bem-sucedida
    $hasError = !$result['status'];
    $errorMessage = $hasError ? $result['message'] : '';
    
    // Extrair dados do resultado ou definir arrays vazios em caso de erro
    $employees = $hasError ? [] : ($result['data']['funcionarios'] ?? []);
    $statistics = $hasError ? [] : ($result['data']['estatisticas'] ?? []);
    $pagination = $hasError ? [] : ($result['data']['paginacao'] ?? []);
    
    // Adicionar informações sobre o nível de acesso aos dados
    $pageData = [
        'access_level' => $accessLevel,
        'is_lojista' => $isLojista,
        'is_gerente' => $isGerente,
        'user_name' => $userName,
        'can_create' => ($accessLevel === 'total' || $accessLevel === 'limitado'),
        'can_edit' => ($accessLevel === 'total' || $accessLevel === 'limitado'),
        'can_delete' => ($accessLevel === 'total'), // Apenas lojistas podem desativar
    ];
    
} catch (Exception $e) {
    // Em caso de exceção, registrar o erro e preparar dados de fallback
    $hasError = true;
    $errorMessage = "Erro ao processar a requisição: " . $e->getMessage();
    
    // Definir arrays vazios para evitar erros no template
    $employees = [];
    $statistics = [];
    $pagination = [];
    
    // Registrar o erro para análise posterior
    error_log("Erro na página de funcionários - Usuário: {$userName}, Erro: " . $e->getMessage());
    
    // Definir dados de página mesmo em caso de erro
    $pageData = [
        'access_level' => $accessLevel,
        'is_lojista' => $isLojista,
        'is_gerente' => $isGerente,
        'user_name' => $userName,
        'can_create' => false, // Em caso de erro, desabilitar ações críticas
        'can_edit' => false,
        'can_delete' => false,
    ];
}

// === PREPARAÇÃO DE DADOS PARA O TEMPLATE ===
// Preparar mensagens contextuais baseadas no tipo de usuário
if ($isLojista) {
    $pageTitle = "Gerenciar Funcionários";
    $pageSubtitle = "Gerencie todos os funcionários da sua loja";
    $welcomeMessage = "Como lojista, você tem controle total sobre sua equipe.";
} elseif ($isGerente) {
    $pageTitle = "Gerenciar Equipe";
    $pageSubtitle = "Gerencie funcionários (acesso de gerente)";
    $welcomeMessage = "Como gerente, você pode cadastrar e editar funcionários da equipe.";
}

// Preparar dados de estatísticas com valores padrão
$stats = [
    'total_funcionarios' => $statistics['total_funcionarios'] ?? 0,
    'total_financeiro' => $statistics['total_financeiro'] ?? 0,
    'total_gerente' => $statistics['total_gerente'] ?? 0,
    'total_vendedor' => $statistics['total_vendedor'] ?? 0,
    'funcionarios_ativos' => $statistics['funcionarios_ativos'] ?? 0,
    'funcionarios_inativos' => $statistics['funcionarios_inativos'] ?? 0,
];

// Preparar informações de paginação com valores padrão
$paginationInfo = [
    'current_page' => $page,
    'total_pages' => $pagination['total_paginas'] ?? 1,
    'per_page' => $pagination['por_pagina'] ?? 10,
    'total_records' => $pagination['total'] ?? 0,
];

// Definir permissões específicas para uso no JavaScript
$permissions = [
    'can_create_employee' => $pageData['can_create'],
    'can_edit_employee' => $pageData['can_edit'],
    'can_delete_employee' => $pageData['can_delete'],
    'can_create_manager' => $isLojista, // Apenas lojistas podem criar outros gerentes
    'access_level' => $accessLevel,
    'user_type' => $userType,
    'subtipo_funcionario' => $subtipoFuncionario,
];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Funcionários - Klube Cash</title>
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/views/stores/employees.css">
    <link rel="stylesheet" href="/assets/css/sidebar-lojista.css">
</head>
<body>
    <?php include '../../views/components/sidebar-lojista-responsiva.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-wrapper">
            <!-- Cabeçalho -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-user-tie"></i> Gerenciar Funcionários</h1>
                    <p>Gerencie os funcionários da sua loja</p>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary" onclick="showEmployeeModal()">
                        <i class="fas fa-plus"></i> Novo Funcionário
                    </button>
                </div>
            </div>

            <!-- Estatísticas -->
            <?php if (!$hasError && !empty($statistics)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($statistics['total_funcionarios']); ?></h3>
                        <p>Total de Funcionários</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon financial">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($statistics['total_financeiro']); ?></h3>
                        <p>Financeiro</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon manager">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($statistics['total_gerente']); ?></h3>
                        <p>Gerentes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon seller">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($statistics['total_vendedor']); ?></h3>
                        <p>Vendedores</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Container de mensagens -->
            <div id="messageContainer" class="alert-container"></div>
            
            <!-- Filtros -->
            <div class="filters-section">
                <form method="GET" class="filters-form" id="filtersForm">
                    <div class="filter-group">
                        <div class="search-bar">
                            <input type="text" 
                                   name="busca" 
                                   id="searchInput"
                                   placeholder="Buscar por nome ou email..." 
                                   value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <select name="subtipo" id="subtipoFilter">
                            <option value="todos">Todos os tipos</option>
                            <option value="financeiro" <?php echo (($_GET['subtipo'] ?? '') === 'financeiro') ? 'selected' : ''; ?>>Financeiro</option>
                            <option value="gerente" <?php echo (($_GET['subtipo'] ?? '') === 'gerente') ? 'selected' : ''; ?>>Gerente</option>
                            <option value="vendedor" <?php echo (($_GET['subtipo'] ?? '') === 'vendedor') ? 'selected' : ''; ?>>Vendedor</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" id="statusFilter">
                            <option value="todos">Todos os status</option>
                            <option value="ativo" <?php echo (($_GET['status'] ?? '') === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo (($_GET['status'] ?? '') === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($hasError): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php else: ?>
            
            <!-- Tabela de Funcionários -->
            <div class="card">
                <div class="card-header">
                    <h3>Lista de Funcionários</h3>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Funcionário</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Data de Cadastro</th>
                                <th>Último Login</th>
                                <th class="actions-column">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="6" class="no-data">
                                        <div class="no-data-content">
                                            <i class="fas fa-user-tie"></i>
                                            <h4>Nenhum funcionário encontrado</h4>
                                            <p>Você ainda não cadastrou funcionários.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                                <div class="user-details">
                                                    <div class="user-name"><?php echo htmlspecialchars($employee['nome']); ?></div>
                                                    <div class="user-email"><?php echo htmlspecialchars($employee['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="type-badge type-<?php echo $employee['subtipo_funcionario']; ?>">
                                                <?php echo ucfirst($employee['subtipo_funcionario']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = $employee['status'] === 'ativo' ? 'badge-success' : 'badge-warning';
                                                $statusIcon = $employee['status'] === 'ativo' ? 'fas fa-check' : 'fas fa-pause';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <i class="<?php echo $statusIcon; ?>"></i>
                                                <?php echo ucfirst($employee['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <div class="date-primary">
                                                    <?php echo date('d/m/Y', strtotime($employee['data_criacao'])); ?>
                                                </div>
                                                <div class="date-secondary">
                                                    <?php echo date('H:i', strtotime($employee['data_criacao'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?php if ($employee['ultimo_login']): ?>
                                                    <div class="date-primary">
                                                        <?php echo date('d/m/Y', strtotime($employee['ultimo_login'])); ?>
                                                    </div>
                                                    <div class="date-secondary">
                                                        <?php echo date('H:i', strtotime($employee['ultimo_login'])); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Nunca</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <button class="action-btn edit" 
                                                        onclick="editEmployee(<?php echo $employee['id']; ?>)"
                                                        title="Editar funcionário">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn delete" 
                                                        onclick="deleteEmployee(<?php echo $employee['id']; ?>, '<?php echo addslashes($employee['nome']); ?>')"
                                                        title="Desativar funcionário">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if (!empty($pagination) && $pagination['total_paginas'] > 1): ?>
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Mostrando <?php echo (($page - 1) * $pagination['por_pagina']) + 1; ?>-<?php echo min($page * $pagination['por_pagina'], $pagination['total']); ?> 
                            de <?php echo $pagination['total']; ?> funcionários
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo isset($_GET) ? '&' . http_build_query($_GET) : ''; ?>" class="pagination-arrow">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET) ? '&' . http_build_query($_GET) : ''; ?>" class="pagination-arrow">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                                $startPage = max(1, $page - 2);
                                $endPage = min($pagination['total_paginas'], $startPage + 4);
                                
                                for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <a href="?page=<?php echo $i; ?><?php echo isset($_GET) ? '&' . http_build_query($_GET) : ''; ?>" 
                                   class="pagination-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $pagination['total_paginas']): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET) ? '&' . http_build_query($_GET) : ''; ?>" class="pagination-arrow">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?page=<?php echo $pagination['total_paginas']; ?><?php echo isset($_GET) ? '&' . http_build_query($_GET) : ''; ?>" class="pagination-arrow">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Funcionário -->
    <div class="modal" id="employeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="employeeModalTitle">
                    <i class="fas fa-user-plus"></i> Adicionar Funcionário
                </h3>
                <button class="modal-close" onclick="hideEmployeeModal()" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="employeeForm" onsubmit="submitEmployeeForm(event)">
                    <input type="hidden" id="employeeId" name="id" value="">
                    
                    <div class="form-group">
                        <label class="form-label required" for="employeeName">Nome Completo</label>
                        <input type="text" 
                               class="form-control" 
                               id="employeeName" 
                               name="nome" 
                               required 
                               placeholder="Digite o nome completo">
                    </div>

                    <div class="form-group">
                        <label class="form-label required" for="employeeEmail">E-mail</label>
                        <input type="email" 
                               class="form-control" 
                               id="employeeEmail" 
                               name="email" 
                               required 
                               placeholder="Digite o e-mail">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="employeePhone">Telefone</label>
                        <input type="tel" 
                               class="form-control" 
                               id="employeePhone" 
                               name="telefone" 
                               placeholder="(00) 00000-0000">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required" for="employeeType">Tipo de Funcionário</label>
                        <select class="form-select" id="employeeType" name="subtipo_funcionario" required>
                            <option value="">Selecione o tipo...</option>
                            <option value="financeiro">Financeiro</option>
                            <option value="gerente">Gerente</option>
                            <option value="vendedor">Vendedor</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="passwordGroup">
                        <label class="form-label required" for="employeePassword">Senha</label>
                        <div class="password-input">
                            <input type="password" 
                                   class="form-control" 
                                   id="employeePassword" 
                                   name="senha"
                                   placeholder="Digite a senha">
                            <button type="button" class="password-toggle" onclick="togglePassword('employeePassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-text">
                            Mínimo de 8 caracteres (deixe em branco para manter a senha atual ao editar)
                        </small>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEmployeeModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" form="employeeForm" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </div>
        </div>
    </div>
    <script>
        let currentPage = 1;
        let isLoading = false;

        function loadEmployees(page = 1, resetTable = true) {
            if (isLoading) return;
            isLoading = true;
            
            if (resetTable) {
                currentPage = 1;
                page = 1;
            }
            
            // Mostrar loading
            showLoading();
            
            // Construir URL com filtros
            const params = new URLSearchParams({
                page: page,
                subtipo: document.getElementById('filterType').value || 'todos',
                status: document.getElementById('filterStatus').value || 'todos',
                busca: document.getElementById('searchInput').value || ''
            });
            
            fetch(`/api/employees?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        updateEmployeeTable(data.data.funcionarios);
                        updateStatistics(data.data.estatisticas);
                        updatePagination(data.data.paginacao);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar funcionários:', error);
                    showError('Erro ao carregar dados dos funcionários');
                })
                .finally(() => {
                    hideLoading();
                    isLoading = false;
                });
        }

        function showEmployeeModal(employee = null) {
            const modal = document.getElementById('employeeModal');
            const form = document.getElementById('employeeForm');
            const title = document.getElementById('modalTitle');
            const passwordGroup = document.getElementById('passwordGroup');
            
            // Resetar formulário
            form.reset();
            
            if (employee) {
                // Editando funcionário
                title.textContent = 'Editar Funcionário';
                document.getElementById('employeeName').value = employee.nome || '';
                document.getElementById('employeeEmail').value = employee.email || '';
                document.getElementById('employeePhone').value = employee.telefone || '';
                document.getElementById('employeeType').value = employee.subtipo_funcionario || '';
                form.dataset.employeeId = employee.id;
                passwordGroup.style.display = 'none'; // Ocultar senha ao editar
            } else {
                // Novo funcionário
                title.textContent = 'Novo Funcionário';
                delete form.dataset.employeeId;
                passwordGroup.style.display = 'block'; // Mostrar senha ao criar
            }
            
            modal.style.display = 'block';
        }

        function closeEmployeeModal() {
            document.getElementById('employeeModal').style.display = 'none';
        }

        function submitEmployeeForm() {
            const form = document.getElementById('employeeForm');
            const formData = new FormData(form);
            const isEditing = !!form.dataset.employeeId;
            
            // Converter FormData para objeto
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            
            // Validações básicas
            if (!data.nome || data.nome.trim().length < 3) {
                showError('Nome deve ter pelo menos 3 caracteres');
                return;
            }
            
            if (!data.email || !isValidEmail(data.email)) {
                showError('E-mail inválido');
                return;
            }
            
            if (!data.subtipo_funcionario) {
                showError('Selecione o tipo de funcionário');
                return;
            }
            
            if (!isEditing && (!data.senha || data.senha.length < 8)) {
                showError('Senha deve ter pelo menos 8 caracteres');
                return;
            }
            
            // Determinar URL e método
            let url = '/api/employees';
            let method = 'POST';
            
            if (isEditing) {
                url += `?id=${form.dataset.employeeId}`;
                method = 'PUT';
                delete data.senha; // Não enviar senha ao editar
            }
            
            // Enviar requisição
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.status) {
                    closeEmployeeModal();
                    loadEmployees(); // Recarregar lista
                    showSuccess(result.message);
                } else {
                    showError(result.message);
                }
            })
            .catch(error => {
                console.error('Erro ao salvar funcionário:', error);
                showError('Erro ao salvar funcionário');
            });
        }

        function deleteEmployee(id, name) {
            if (!confirm(`Tem certeza que deseja desativar o funcionário "${name}"?`)) {
                return;
            }
            
            fetch(`/api/employees?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(result => {
                if (result.status) {
                    loadEmployees(); // Recarregar lista
                    showSuccess(result.message);
                } else {
                    showError(result.message);
                }
            })
            .catch(error => {
                console.error('Erro ao desativar funcionário:', error);
                showError('Erro ao desativar funcionário');
            });
        }

        function updateEmployeeTable(employees) {
            const tbody = document.querySelector('#employeesTable tbody');
            
            if (!employees || employees.length === 0) {
                tbody.innerHTML = `
                    <tr class="empty-state">
                        <td colspan="6">
                            <div class="empty-message">
                                <i class="fas fa-users"></i>
                                <p>Nenhum funcionário encontrado</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = employees.map(employee => `
                <tr>
                    <td>
                        <div class="user-info">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="user-details">
                                <div class="user-name">${escapeHtml(employee.nome)}</div>
                                <div class="user-email">${escapeHtml(employee.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="type-badge type-funcionario">
                            ${getEmployeeTypeLabel(employee.subtipo_funcionario)}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-${employee.status}">
                            ${getStatusLabel(employee.status)}
                        </span>
                    </td>
                    <td>${formatDate(employee.data_criacao)}</td>
                    <td>${employee.ultimo_login ? formatDate(employee.ultimo_login) : 'Nunca'}</td>
                    <td>
                        <div class="actions">
                            <button onclick="showEmployeeModal(${JSON.stringify(employee).replace(/"/g, '&quot;')})" 
                                    class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteEmployee(${employee.id}, '${escapeHtml(employee.nome)}')" 
                                    class="btn btn-sm btn-outline-danger" title="Desativar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function getEmployeeTypeLabel(type) {
            const types = {
                'financeiro': 'Financeiro',
                'gerente': 'Gerente',
                'vendedor': 'Vendedor'
            };
            return types[type] || type;
        }

        function getStatusLabel(status) {
            const statuses = {
                'ativo': 'Ativo',
                'inativo': 'Inativo',
                'bloqueado': 'Bloqueado'
            };
            return statuses[status] || status;
        }

        function updateStatistics(stats) {
            if (!stats) return;
            
            document.querySelector('.stat-total .stat-number').textContent = stats.total || 0;
            document.querySelector('.stat-financeiro .stat-number').textContent = stats.financeiro || 0;
            document.querySelector('.stat-gerentes .stat-number').textContent = stats.gerente || 0;
            document.querySelector('.stat-vendedores .stat-number').textContent = stats.vendedor || 0;
        }

        // Funções auxiliares
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + '<br><small>' + 
                date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'}) + '</small>';
        }

        function showLoading() {
            // Implementar loading
        }

        function hideLoading() {
            // Implementar hide loading
        }

        function showError(message) {
            alert('Erro: ' + message);
        }

        function showSuccess(message) {
            alert('Sucesso: ' + message);
        }

        // Event listeners para filtros
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput')?.addEventListener('input', () => {
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(() => loadEmployees(), 500);
            });
            
            document.getElementById('filterType')?.addEventListener('change', () => loadEmployees());
            document.getElementById('filterStatus')?.addEventListener('change', () => loadEmployees());
        });
    </script>                            
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Carregando...</p>
        </div>
    </div>
    <script src="/assets/js/sidebar-lojista.js"></script>
    <script src="../../assets/js/stores/employees.js"></script>
</body>
</html>