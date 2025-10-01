<?php
/**
 * Painel de Email Marketing - Klube Cash
 * 
 * Esta página permite ao administrador criar, agendar e gerenciar campanhas de email.
 * Funciona como o "centro de comando" para toda comunicação com os cadastrados.
 */

session_start();

// Verificação de segurança - só admin pode acessar
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . LOGIN_URL);
    exit;
}

require_once '../../config/database.php';
require_once '../../config/constants.php';

// Processar ações do formulário (criação, agendamento, etc.)
if ($_POST) {
    try {
        if ($_POST['action'] === 'criar_campanha') {
            criarCampanha($_POST);
        } elseif ($_POST['action'] === 'agendar_campanha') {
            agendarCampanha($_POST['campaign_id'], $_POST['data_agendamento']);
        } elseif ($_POST['action'] === 'cancelar_campanha') {
            cancelarCampanha($_POST['campaign_id']);
        }
    } catch (Exception $e) {
        $_SESSION['erro'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Buscar dados para exibição no dashboard
$db = Database::getConnection();

// Campanhas existentes com estatísticas
$campanhas = $db->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM email_envios e WHERE e.campaign_id = c.id AND e.status = 'enviado') as enviados,
    (SELECT COUNT(*) FROM email_envios e WHERE e.campaign_id = c.id AND e.status = 'falhou') as falharam
    FROM email_campaigns c 
    ORDER BY c.data_criacao DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Contar total de emails na base (usuários + landing page)
$totalEmailsUsuarios = $db->query("SELECT COUNT(DISTINCT email) FROM usuarios WHERE email IS NOT NULL AND email != ''")->fetchColumn();

// Contar emails da landing page "em breve"
$totalEmailsLanding = 0;
$emailsFile = '../../embreve/emails.json';
if (file_exists($emailsFile)) {
    $emailsData = json_decode(file_get_contents($emailsFile), true);
    if ($emailsData) {
        // Extrair apenas emails únicos
        $emailsUnicos = array_unique(array_column($emailsData, 'email'));
        $totalEmailsLanding = count(array_filter($emailsUnicos));
    }
}

$totalEmails = $totalEmailsUsuarios + $totalEmailsLanding;

/**
 * Função para criar uma nova campanha
 * Como um chef preparando uma receita especial para servir a vários convidados
 */
function criarCampanha($dados) {
    $db = Database::getConnection();
    
    // Validações básicas (como verificar os ingredientes antes de cozinhar)
    if (empty($dados['titulo']) || empty($dados['assunto']) || empty($dados['conteudo_html'])) {
        throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
    }
    
    // Criar a campanha no banco
    $stmt = $db->prepare("
        INSERT INTO email_campaigns (titulo, assunto, conteudo_html, conteudo_texto, data_agendamento, status) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $dataAgendamento = !empty($dados['data_agendamento']) ? $dados['data_agendamento'] : null;
    $status = $dataAgendamento ? 'agendado' : 'rascunho';
    
    // Converter HTML para texto puro (para clientes de email que não suportam HTML)
    $conteudoTexto = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $dados['conteudo_html']));
    
    $stmt->execute([
        $dados['titulo'],
        $dados['assunto'], 
        $dados['conteudo_html'],
        $conteudoTexto,
        $dataAgendamento,
        $status
    ]);
    
    $campaignId = $db->lastInsertId();
    
    // Se foi agendada, preparar a lista de destinatários
    if ($dataAgendamento) {
        prepararListaEmails($campaignId);
    }
    
    $_SESSION['sucesso'] = 'Campanha "' . $dados['titulo'] . '" criada com sucesso!';
}

/**
 * Função para preparar lista de emails únicos
 * Como fazer uma lista de convidados sem repetições para uma festa
 */
function prepararListaEmails($campaignId) {
    $db = Database::getConnection();
    
    $emails = [];
    
    // Buscar emails dos usuários cadastrados no sistema
    $usuariosEmails = $db->query("SELECT DISTINCT email FROM usuarios WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN);
    $emails = array_merge($emails, $usuariosEmails);
    
    // Buscar emails da landing page "em breve"
    $emailsFile = '../../embreve/emails.json';
    if (file_exists($emailsFile)) {
        $emailsData = json_decode(file_get_contents($emailsFile), true);
        if ($emailsData) {
            foreach ($emailsData as $entry) {
                if (!empty($entry['email']) && filter_var($entry['email'], FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $entry['email'];
                }
            }
        }
    }
    
    // Remover duplicatas (mesmo email em ambas as listas)
    $emails = array_unique(array_filter($emails));
    
    // Inserir na tabela de envios (IGNORE evita duplicatas se executar novamente)
    $stmt = $db->prepare("INSERT IGNORE INTO email_envios (campaign_id, email) VALUES (?, ?)");
    foreach ($emails as $email) {
        $stmt->execute([$campaignId, $email]);
    }
    
    // Atualizar contador total na campanha
    $total = count($emails);
    $db->prepare("UPDATE email_campaigns SET total_emails = ? WHERE id = ?")->execute([$total, $campaignId]);
    
    return $total;
}

function agendarCampanha($campaignId, $dataAgendamento) {
    $db = Database::getConnection();
    
    // Atualizar campanha
    $stmt = $db->prepare("UPDATE email_campaigns SET data_agendamento = ?, status = 'agendado' WHERE id = ? AND status = 'rascunho'");
    $stmt->execute([$dataAgendamento, $campaignId]);
    
    if ($stmt->rowCount() > 0) {
        // Preparar lista de emails
        $total = prepararListaEmails($campaignId);
        $_SESSION['sucesso'] = "Campanha agendada para " . date('d/m/Y H:i', strtotime($dataAgendamento)) . " ({$total} emails)";
    } else {
        $_SESSION['erro'] = 'Não foi possível agendar a campanha';
    }
}

function cancelarCampanha($campaignId) {
    $db = Database::getConnection();
    
    $stmt = $db->prepare("UPDATE email_campaigns SET status = 'cancelado' WHERE id = ? AND status IN ('agendado', 'rascunho')");
    $stmt->execute([$campaignId]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['sucesso'] = 'Campanha cancelada com sucesso';
    } else {
        $_SESSION['erro'] = 'Não foi possível cancelar a campanha';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/jpg" href="../../assets/images/icons/KlubeCashLOGO.ico"/>
    <title>📧 Email Marketing - Klube Cash Admin</title>
    <style>
        :root {
            --primary-orange: #FF7A00;
            --primary-dark: #E86E00;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-600: #4B5563;
            --gray-900: #111827;
            --success: #10B981;
            --error: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Header principal com gradiente */
        .header {
            background: linear-gradient(135deg, var(--primary-orange), #FF9A40);
            color: var(--white);
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(255, 122, 0, 0.2);
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        /* Grid de estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid var(--gray-200);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 1rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }
        
        .stat-card small {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        /* Container para formulários */
        .form-container {
            background: var(--white);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: 1px solid var(--gray-200);
        }
        
        .form-container h2 {
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        /* Estilos de formulário */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(255, 122, 0, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 200px;
        }
        
        .form-group small {
            color: var(--gray-600);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        /* Botões */
        .btn {
            background: var(--primary-orange);
            color: var(--white);
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(255, 122, 0, 0.3);
        }
        
        .btn-secondary {
            background: var(--gray-600);
        }
        
        .btn-secondary:hover {
            background: var(--gray-900);
        }
        
        .btn-danger {
            background: var(--error);
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Tabela de campanhas */
        .campaigns-table {
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid var(--gray-200);
        }
        
        .campaigns-table h2 {
            padding: 2rem 2rem 0;
            color: var(--gray-900);
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .campaigns-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .campaigns-table th,
        .campaigns-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .campaigns-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .campaigns-table tr:hover td {
            background: var(--gray-50);
        }
        
        /* Badges de status */
        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .status-rascunho { 
            background: #FEF3C7; 
            color: #92400E; 
        }
        
        .status-agendado { 
            background: #DBEAFE; 
            color: #1E40AF; 
        }
        
        .status-enviando { 
            background: #FEF0FF; 
            color: #A21CAF; 
        }
        
        .status-enviado { 
            background: #D1FAE5; 
            color: #065F46; 
        }
        
        .status-cancelado { 
            background: #FEE2E2; 
            color: #991B1B; 
        }
        
        /* Mensagens de feedback */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .message.success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        
        .message.error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
        
        /* Templates grid */
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .template-card {
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--white);
        }
        
        .template-card:hover {
            border-color: var(--primary-orange);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 122, 0, 0.1);
        }
        
        .template-card.selected {
            border-color: var(--primary-orange);
            background: #FFF7ED;
        }
        
        .template-card h4 {
            color: var(--primary-orange);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .template-card p {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .campaigns-table th,
            .campaigns-table td {
                padding: 0.75rem;
                font-size: 0.875rem;
            }
            
            .btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.9rem;
            }
            
            .templates-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../components/sidebar.php'; ?>
    <div class="container">
        <!-- Header Principal -->
        <div class="header">
            <h1>📧 Email Marketing - Klube Cash</h1>
            <p>Gerencie campanhas de email para manter seus futuros clientes engajados até o lançamento</p>
        </div>

        <!-- Mensagens de Feedback -->
        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="message success">
                ✅ <?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['erro'])): ?>
            <div class="message error">
                ❌ <?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📬 Total de Emails Cadastrados</h3>
                <div class="stat-number" style="color: var(--primary-orange);"><?php echo number_format($totalEmails); ?></div>
                <small>
                    <?php echo number_format($totalEmailsUsuarios); ?> do sistema + 
                    <?php echo number_format($totalEmailsLanding); ?> da landing page
                </small>
            </div>
            
            <div class="stat-card">
                <h3>📨 Campanhas Ativas</h3>
                <div class="stat-number" style="color: var(--info);">
                    <?php echo count(array_filter($campanhas, fn($c) => in_array($c['status'], ['agendado', 'enviando']))); ?>
                </div>
                <small>Agendadas ou sendo enviadas</small>
            </div>
            
            <div class="stat-card">
                <h3>✅ Emails Enviados Hoje</h3>
                <div class="stat-number" style="color: var(--success);">
                    <?php 
                    $enviadosHoje = $db->query("
                        SELECT COUNT(*) FROM email_envios 
                        WHERE status = 'enviado' AND DATE(data_envio) = CURDATE()
                    ")->fetchColumn();
                    echo number_format($enviadosHoje);
                    ?>
                </div>
                <small>Enviados nas últimas 24h</small>
            </div>
            
            <div class="stat-card">
                <h3>📊 Taxa de Sucesso</h3>
                <div class="stat-number" style="color: var(--success);">
                    <?php
                    $totalEnviados = $db->query("SELECT COUNT(*) FROM email_envios WHERE status IN ('enviado', 'falhou')")->fetchColumn();
                    $sucessos = $db->query("SELECT COUNT(*) FROM email_envios WHERE status = 'enviado'")->fetchColumn();
                    $taxa = $totalEnviados > 0 ? round(($sucessos / $totalEnviados) * 100) : 100;
                    echo $taxa;
                    ?>%
                </div>
                <small>Média de entregas bem-sucedidas</small>
            </div>
        </div>

        <!-- Formulário para Nova Campanha -->
        <div class="form-container">
            <h2>🎯 Criar Nova Campanha de Email</h2>
            <p style="color: var(--gray-600); margin-bottom: 2rem;">
                Crie uma nova newsletter para enviar aos seus cadastrados. Você pode salvar como rascunho ou agendar para envio automático.
            </p>
            
            <!-- Seletor de Templates -->
            <h3 style="margin-bottom: 1rem; color: var(--gray-900);">📝 Escolha um Template</h3>
            <div class="templates-grid">
                <div class="template-card" data-template="lancamento_proximo">
                    <h4>🚀 Lançamento se Aproxima</h4>
                    <p>Template para criar expectativa nos últimos dias antes do lançamento. Inclui contagem regressiva e benefícios exclusivos.</p>
                </div>
                
                <div class="template-card" data-template="novidades_desenvolvimento">
                    <h4>🔧 Novidades do Desenvolvimento</h4>
                    <p>Mostre o progresso do desenvolvimento e funcionalidades que estão sendo preparadas.</p>
                </div>
                
                <div class="template-card" data-template="dicas_cashback">
                    <h4>💡 Dicas de Cashback</h4>
                    <p>Eduque seus futuros usuários sobre como maximizar o cashback e aproveitar melhor a plataforma.</p>
                </div>
                
                <div class="template-card" data-template="personalizado">
                    <h4>✏️ Template Personalizado</h4>
                    <p>Comece do zero com um template básico para criar seu próprio conteúdo personalizado.</p>
                </div>
            </div>
            
            <form method="POST" id="campaignForm">
                <input type="hidden" name="action" value="criar_campanha">
                
                <div class="form-group">
                    <label for="titulo">Título da Campanha (para controle interno)</label>
                    <input type="text" id="titulo" name="titulo" required 
                           placeholder="Ex: Newsletter Semanal #1 - Contagem Regressiva">
                    <small>Este título é apenas para organização interna, não aparece no email</small>
                </div>
                
                <div class="form-group">
                    <label for="assunto">Assunto do Email</label>
                    <input type="text" id="assunto" name="assunto" required 
                           placeholder="Ex: 🚀 Faltam poucos dias para o lançamento da Klube Cash!">
                    <small>Este texto aparecerá na caixa de entrada do destinatário</small>
                </div>
                
                <div class="form-group">
                    <label for="conteudo_html">Conteúdo do Email (HTML)</label>
                    <textarea id="conteudo_html" name="conteudo_html" rows="20" required 
                              placeholder="O conteúdo será preenchido automaticamente quando você selecionar um template acima..."><?php echo getTemplatePersonalizado(); ?></textarea>
                    <small>Você pode usar HTML para formatação. O sistema criará automaticamente uma versão em texto puro.</small>
                </div>
                
                <div class="form-group">
                    <label for="data_agendamento">Agendar Envio (opcional)</label>
                    <input type="datetime-local" id="data_agendamento" name="data_agendamento">
                    <small>Deixe em branco para salvar como rascunho. Agendamentos são processados automaticamente pelo sistema.</small>
                </div>
                
                <button type="submit" class="btn">💌 Criar Campanha</button>
            </form>
        </div>

        <!-- Lista de Campanhas Existentes -->
        <div class="campaigns-table">
            <h2>📋 Campanhas de Email</h2>
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Assunto</th>
                        <th>Status</th>
                        <th>Agendamento</th>
                        <th>Progresso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campanhas)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: var(--gray-600);">
                            📪 Nenhuma campanha criada ainda.<br>
                            <small>Crie sua primeira campanha usando o formulário acima.</small>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($campanhas as $campanha): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($campanha['titulo']); ?></strong></td>
                            <td><?php echo htmlspecialchars($campanha['assunto']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $campanha['status']; ?>">
                                    <?php echo ucfirst($campanha['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($campanha['data_agendamento']) {
                                    echo date('d/m/Y H:i', strtotime($campanha['data_agendamento']));
                                } else {
                                    echo '<span style="color: var(--gray-600);">-</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($campanha['total_emails'] > 0) {
                                    $progresso = ($campanha['emails_enviados'] / $campanha['total_emails']) * 100;
                                    echo '<div style="display: flex; align-items: center; gap: 0.5rem;">';
                                    echo '<span>' . $campanha['emails_enviados'] . '/' . $campanha['total_emails'] . '</span>';
                                    echo '<div style="background: var(--gray-200); height: 6px; width: 60px; border-radius: 3px; overflow: hidden;">';
                                    echo '<div style="background: var(--success); height: 100%; width: ' . round($progresso) . '%; border-radius: 3px;"></div>';
                                    echo '</div>';
                                    echo '<span style="font-size: 0.8rem; color: var(--gray-600);">(' . round($progresso) . '%)</span>';
                                    echo '</div>';
                                } else {
                                    echo '<span style="color: var(--gray-600);">-</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php if ($campanha['status'] === 'rascunho'): ?>
                                        <button class="btn btn-small" onclick="agendarCampanha(<?php echo $campanha['id']; ?>)">
                                            📅 Agendar
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($campanha['status'], ['agendado', 'rascunho'])): ?>
                                        <button class="btn btn-small btn-danger" onclick="cancelarCampanha(<?php echo $campanha['id']; ?>)">
                                            ❌ Cancelar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Templates disponíveis
        const templates = {
            lancamento_proximo: {
                assunto: '🚀 Últimos dias antes do lançamento da Klube Cash!',
                html: getTemplateLancamentoProximo()
            },
            novidades_desenvolvimento: {
                assunto: '🔧 Veja o que estamos preparando para você na Klube Cash',
                html: getTemplateDesenvolvimento()
            },
            dicas_cashback: {
                assunto: '💰 Como maximizar seu cashback - Dicas exclusivas da Klube Cash',
                html: getTemplateDicas()
            },
            personalizado: {
                assunto: '📧 Newsletter Klube Cash - [Substitua pelo seu assunto]',
                html: `<?php echo addslashes(getTemplatePersonalizado()); ?>`
            }
        };

        // Adicionar event listeners aos cards de template
        document.addEventListener('DOMContentLoaded', function() {
            const templateCards = document.querySelectorAll('.template-card');
            
            templateCards.forEach(card => {
                card.addEventListener('click', function() {
                    const templateKey = this.dataset.template;
                    selecionarTemplate(templateKey);
                });
            });
        });

        function selecionarTemplate(templateKey) {
            if (!templates[templateKey]) return;
            
            const template = templates[templateKey];
            
            // Preencher formulário
            document.getElementById('assunto').value = template.assunto;
            document.getElementById('conteudo_html').value = template.html;
            
            // Highlight visual
            document.querySelectorAll('.template-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-template="${templateKey}"]`).classList.add('selected');
            
            // Sugerir título baseado no template
            const tituloInput = document.getElementById('titulo');
            if (!tituloInput.value) {
                const sugestoesTitulo = {
                    lancamento_proximo: 'Newsletter - Contagem Regressiva Final',
                    novidades_desenvolvimento: 'Newsletter - Updates de Desenvolvimento',
                    dicas_cashback: 'Newsletter - Dicas de Cashback',
                    personalizado: 'Newsletter Personalizada'
                };
                tituloInput.value = sugestoesTitulo[templateKey] || 'Nova Newsletter';
            }
        }

        function agendarCampanha(id) {
            // Criar modal simples para agendar
            const dataAgendamento = prompt(
                'Digite a data e hora para envio automático:\n\n' +
                'Formato: AAAA-MM-DD HH:MM\n' +
                'Exemplo: 2025-06-05 09:00\n\n' +
                'Dica: Quartas e sextas-feiras entre 9h-11h têm melhor engajamento!'
            );
            
            if (dataAgendamento && dataAgendamento.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="agendar_campanha">
                    <input type="hidden" name="campaign_id" value="${id}">
                    <input type="hidden" name="data_agendamento" value="${dataAgendamento}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (dataAgendamento) {
                alert('Formato de data inválido. Use: AAAA-MM-DD HH:MM');
            }
        }

        function cancelarCampanha(id) {
            if (confirm('Tem certeza que deseja cancelar esta campanha?\n\nEsta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancelar_campanha">
                    <input type="hidden" name="campaign_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Funções para obter templates (simulando o PHP no JavaScript)
        function getTemplateLancamentoProximo() {
            return `<div style="max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif; background: #ffffff;">
    <!-- Header com gradiente -->
    <div style="background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;">
        <img src="https://klubecash.com/assets/images/logobranco.png" alt="Klube Cash" style="height: 60px; margin-bottom: 1rem;">
        <h1 style="margin: 0; font-size: 2rem; font-weight: 800;">⏰ ÚLTIMA SEMANA!</h1>
        <p style="margin: 1rem 0 0; font-size: 1.2rem; opacity: 0.95;">O lançamento da Klube Cash está chegando!</p>
    </div>
    
    <!-- Conteúdo principal -->
    <div style="background: white; padding: 2rem;">
        <h2 style="color: #FF7A00; text-align: center; margin-bottom: 1.5rem; font-size: 1.5rem;">🎯 Faltam apenas alguns dias!</h2>
        
        <!-- Contagem regressiva visual -->
        <div style="background: #FFF7ED; border: 2px solid #FF7A00; border-radius: 12px; padding: 2rem; margin: 1.5rem 0; text-align: center;">
            <h3 style="color: #FF7A00; margin: 0 0 1rem; font-size: 1.2rem;">📅 Data de Lançamento Oficial:</h3>
            <p style="font-size: 2rem; font-weight: 800; color: #FF7A00; margin: 0; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">9 de Junho • 18:00</p>
            <p style="color: #666; margin: 0.5rem 0 0; font-size: 1rem;">Horário de Brasília</p>
        </div>
        
        <h3 style="color: #333; margin: 2rem 0 1rem; font-size: 1.3rem;">🎁 Benefícios exclusivos para primeiros cadastrados:</h3>
        <div style="background: #F8FAFC; border-left: 4px solid #FF7A00; padding: 1.5rem; border-radius: 0 8px 8px 0;">
            <ul style="color: #444; line-height: 2; margin: 0; padding-left: 1.5rem; font-size: 1rem;">
                
                <li><strong style="color: #FF7A00;">Cashback Garantido</strong> de 5%</li>
                <li><strong style="color: #FF7A00;">Acesso antecipado</strong> às melhores ofertas</li>
                <li><strong style="color: #FF7A00;">Suporte premium</strong></li>
                <li><strong style="color: #FF7A00;">Zero taxas
            </ul>
        </div>
        
        <!-- Call to action -->
        <div style="text-align: center; margin: 2.5rem 0;">
            <a href="https://klubecash.com" style="background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 1.2rem 2.5rem; text-decoration: none; border-radius: 30px; font-weight: 700; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(255, 122, 0, 0.3); transition: transform 0.2s ease;">
                🚀 Estar Pronto no Lançamento
            </a>
        </div>
        
        <!-- Informações adicionais -->
        <div style="background: #F0F9FF; border-left: 4px solid #3B82F6; padding: 1.5rem; border-radius: 0 8px 8px 0; margin: 2rem 0;">
            <h4 style="color: #1E40AF; margin: 0 0 0.5rem; font-size: 1.1rem;">📱 Como funciona:</h4>
            <p style="color: #1E3A8A; margin: 0; line-height: 1.6;">
                1. Faça suas compras normalmente<br>
                2. Apresente seu email ou codigo cadastrado na Klube Cash<br>
                3. Receba dinheiro de volta automaticamente na sua Conta Klube Cash<br>
                4. Use seu cashback em novas compras
            </p>
        </div>
    </div>
    
    <!-- Footer -->
    <div style="background: #FFF7ED; padding: 2rem; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #FFE4B5;">
        <p style="color: #666; font-size: 0.9rem; margin: 0 0 1rem;">
            Siga-nos nas redes sociais para acompanhar todas as novidades:
        </p>
        <div style="margin-bottom: 1rem;">
            <a href="https://instagram.com/klubecash" style="color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;">📸 Instagram</a>
            <a href="https://tiktok.com/@klube.cash" style="color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;">🎵 TikTok</a>
        </div>
        <p style="color: #999; font-size: 0.8rem; margin: 0;">
            &copy; 2025 Klube Cash. Todos os direitos reservados.<br>
            Você está recebendo este email porque se cadastrou em nossa lista de lançamento.
        </p>
    </div>
</div>`;
        }

        function getTemplateDesenvolvimento() {
            return `<div style="max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif; background: #ffffff;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #3B82F6, #60A5FA); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;">
        <img src="https://klubecash.com/assets/images/logobranco.png" alt="Klube Cash" style="height: 60px; margin-bottom: 1rem;">
        <h1 style="margin: 0; font-size: 1.8rem; font-weight: 800;">🔧 Bastidores do Desenvolvimento</h1>
        <p style="margin: 1rem 0 0; font-size: 1.1rem; opacity: 0.95;">Veja o que estamos preparando para você!</p>
    </div>
    
    <!-- Conteúdo -->
    <div style="background: white; padding: 2rem;">
        <h2 style="color: #333; margin-bottom: 1.5rem; font-size: 1.4rem;">👋 Olá, futuro membro da Klube Cash!</h2>
        
        <p style="color: #666; line-height: 1.8; margin-bottom: 2rem; font-size: 1rem;">
            Queremos compartilhar com você alguns detalhes emocionantes sobre o que estamos construindo. Nossa equipe trabalha incansavelmente para criar a melhor experiência de cashback do Brasil!
        </p>
        
        <!-- Novidades -->
        <div style="background: #F8FAFC; border-radius: 12px; padding: 2rem; margin: 2rem 0;">
            <h3 style="color: #3B82F6; margin: 0 0 1.5rem; font-size: 1.3rem;">🆕 Novidades desta semana:</h3>
            
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: #1F2937; margin: 0 0 0.5rem; font-size: 1.1rem;">📱 App Mobile em Desenvolvimento</h4>
                <p style="color: #666; margin: 0; line-height: 1.6;">
                    Estamos finalizando o aplicativo para Android e iOS. Você poderá gerenciar seu cashback direto do celular!
                </p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: #1F2937; margin: 0 0 0.5rem; font-size: 1.1rem;">🏪 +200 Lojas Parceiras</h4>
                <p style="color: #666; margin: 0; line-height: 1.6;">
                    Fechamos parcerias com grandes redes nacionais. Você terá cashback em praticamente tudo que comprar!
                </p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: #1F2937; margin: 0 0 0.5rem; font-size: 1.1rem;">⚡ Sistema de Pagamento Instantâneo</h4>
                <p style="color: #666; margin: 0; line-height: 1.6;">
                    Seu cashback será processado em tempo real. Comprou agora? O dinheiro já está na sua conta!
                </p>
            </div>
        </div>
        
        <!-- Prévia do sistema -->
        <div style="background: linear-gradient(135deg, #FFF7ED, #FFEDD5); border: 2px solid #FF7A00; border-radius: 12px; padding: 2rem; margin: 2rem 0;">
            <h3 style="color: #EA580C; margin: 0 0 1rem; font-size: 1.3rem;">👀 Prévia Exclusiva</h3>
            <p style="color: #9A3412; margin: 0 0 1.5rem; line-height: 1.6; font-weight: 500;">
                Que tal dar uma espiada em como será seu dashboard pessoal?
            </p>
            
            <!-- Simulação de dashboard -->
            <div style="background: white; border-radius: 8px; padding: 1.5rem; border: 1px solid #FED7AA;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span style="color: #666; font-size: 0.9rem;">Seu Saldo de Cashback</span>
                    <span style="color: #059669; font-weight: 700; font-size: 1.5rem;">R$ 247,85</span>
                </div>
                <div style="background: #F3F4F6; height: 6px; border-radius: 3px; overflow: hidden;">
                    <div style="background: #10B981; height: 100%; width: 75%; border-radius: 3px;"></div>
                </div>
                <p style="color: #666; font-size: 0.8rem; margin: 0.5rem 0 0;">75% da meta mensal atingida!</p>
            </div>
        </div>
        
        <!-- CTA -->
        <div style="text-align: center; margin: 2.5rem 0;">
            <a href="https://klubecash.com" style="background: linear-gradient(135deg, #3B82F6, #60A5FA); color: white; padding: 1.2rem 2.5rem; text-decoration: none; border-radius: 30px; font-weight: 700; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                🔍 Ver Mais Novidades
            </a>
        </div>
    </div>
    
    <!-- Footer -->
    <div style="background: #F1F5F9; padding: 2rem; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #E2E8F0;">
        <p style="color: #666; font-size: 0.9rem; margin: 0 0 1rem;">
            Continue acompanhando nosso progresso nas redes sociais!
        </p>
        <div style="margin-bottom: 1rem;">
            <a href="https://instagram.com/klubecash" style="color: #3B82F6; text-decoration: none; margin: 0 1rem; font-weight: 600;">📸 Instagram</a>
            <a href="https://tiktok.com/@klube.cash" style="color: #3B82F6; text-decoration: none; margin: 0 1rem; font-weight: 600;">🎵 TikTok</a>
        </div>
        <p style="color: #999; font-size: 0.8rem; margin: 0;">
            &copy; 2025 Klube Cash. Todos os direitos reservados.
        </p>
    </div>
</div>`;
        }

        function getTemplateDicas() {
            return `<div style="max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif; background: #ffffff;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #10B981, #34D399); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;">
        <img src="https://klubecash.com/assets/images/logobranco.png" alt="Klube Cash" style="height: 60px; margin-bottom: 1rem;">
        <h1 style="margin: 0; font-size: 1.8rem; font-weight: 800;">💡 Dicas de Ouro para Cashback</h1>
        <p style="margin: 1rem 0 0; font-size: 1.1rem; opacity: 0.95;">Aprenda a maximizar seus ganhos!</p>
    </div>
    
    <!-- Conteúdo -->
    <div style="background: white; padding: 2rem;">
        <h2 style="color: #059669; margin-bottom: 1.5rem; font-size: 1.4rem;">🎯 Como ganhar ainda mais dinheiro de volta</h2>
        
        <p style="color: #666; line-height: 1.8; margin-bottom: 2rem; font-size: 1rem;">
            Preparamos dicas exclusivas para você se tornar um expert em cashback e maximizar seus ganhos desde o primeiro dia na Klube Cash!
        </p>
        
        <!-- Dicas -->
        <div style="margin: 2rem 0;">
            <!-- Dica 1 -->
            <div style="background: #F0FDF4; border-left: 4px solid #10B981; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0;">
                <h3 style="color: #065F46; margin: 0 0 1rem; font-size: 1.2rem;">🛒 Dica #1: Planeje suas Compras</h3>
                <p style="color: #064E3B; margin: 0; line-height: 1.6;">
                    <strong>Concentre suas compras</strong> em dias específicos da semana. Muitas lojas oferecem cashback extra às quartas e sextas-feiras. Você pode ganhar até <strong>12% de volta</strong> em vez dos 5% padrão!
                </p>
            </div>
            
            <!-- Dica 2 -->
            <div style="background: #FEF7FF; border-left: 4px solid #A855F7; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0;">
                <h3 style="color: #7C2D12; margin: 0 0 1rem; font-size: 1.2rem;">💳 Dica #2: Combine Promoções</h3>
                <p style="color: #92400E; margin: 0; line-height: 1.6;">
                    Use cupons de desconto das lojas <strong>junto</strong> com o cashback da Klube Cash. É desconto duplo! Já tivemos clientes que economizaram 30% em uma única compra combinando ofertas.
                </p>
            </div>
            
            <!-- Dica 3 -->
            <div style="background: #FFF7ED; border-left: 4px solid #FF7A00; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0;">
                <h3 style="color: #C2410C; margin: 0 0 1rem; font-size: 1.2rem;">📱 Dica #3: Use o App (em breve)</h3>
                <p style="color: #EA580C; margin: 0; line-height: 1.6;">
                    Nosso app móvel terá <strong>notificações em tempo real</strong> quando você estiver perto de lojas parceiras. Você nunca mais vai esquecer de usar seu cashback!
                </p>
            </div>
            
            <!-- Dica 4 -->
            <div style="background: #EFF6FF; border-left: 4px solid #3B82F6; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0;">
                <h3 style="color: #1D4ED8; margin: 0 0 1rem; font-size: 1.2rem;">🎁 Dica #4: Indique Amigos</h3>
                <p style="color: #1E40AF; margin: 0; line-height: 1.6;">
                    Para cada amigo que você indicar, <strong>ambos ganham R$ 15 de bônus</strong>. É uma maneira fácil de aumentar seu saldo sem gastar nada!
                </p>
            </div>
        </div>
        
        <!-- Exemplo prático -->
        <div style="background: #F8FAFC; border: 2px solid #E2E8F0; border-radius: 12px; padding: 2rem; margin: 2rem 0;">
            <h3 style="color: #374151; margin: 0 0 1rem; font-size: 1.3rem;">📊 Exemplo Prático</h3>
            <p style="color: #6B7280; margin: 0 0 1rem; line-height: 1.6;">
                <strong>Situação:</strong> Compra de R$ 200 em roupas numa quarta-feira
            </p>
            <ul style="color: #4B5563; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
                <li>Cashback padrão (5%): R$ 10</li>
                <li>Bônus dia da semana (+2%): R$ 4</li>
                <li>Cupom da loja (15% desconto): R$ 30</li>
                <li><strong style="color: #10B981;">Total economizado: R$ 44 (22% da compra!)</strong></li>
            </ul>
        </div>
        
        <!-- CTA -->
        <div style="text-align: center; margin: 2.5rem 0;">
            <a href="https://klubecash.com" style="background: linear-gradient(135deg, #10B981, #34D399); color: white; padding: 1.2rem 2.5rem; text-decoration: none; border-radius: 30px; font-weight: 700; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">
                💰 Quero Começar a Economizar
            </a>
        </div>
        
        <div style="background: #FFFBEB; border: 2px solid #F59E0B; border-radius: 8px; padding: 1.5rem; margin: 2rem 0;">
            <p style="color: #92400E; margin: 0; text-align: center; font-weight: 600;">
                💡 <strong>Lembre-se:</strong> Essas dicas funcionam melhor quando usadas em conjunto. 
                Teste diferentes combinações e descubra qual funciona melhor para seu perfil de compras!
            </p>
        </div>
    </div>
    
    <!-- Footer -->
    <div style="background: #F0FDF4; padding: 2rem; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #BBF7D0;">
        <p style="color: #166534; font-size: 0.9rem; margin: 0 0 1rem; font-weight: 600;">
            🏆 Compartilhe essas dicas e ajude seus amigos a economizar também!
        </p>
        <div style="margin-bottom: 1rem;">
            <a href="https://instagram.com/klubecash" style="color: #10B981; text-decoration: none; margin: 0 1rem; font-weight: 600;">📸 Instagram</a>
            <a href="https://tiktok.com/@klube.cash" style="color: #10B981; text-decoration: none; margin: 0 1rem; font-weight: 600;">🎵 TikTok</a>
        </div>
        <p style="color: #999; font-size: 0.8rem; margin: 0;">
            &copy; 2025 Klube Cash. Todos os direitos reservados.
        </p>
    </div>
</div>`;
        }
    </script>
</body>
</html>

<?php
function getTemplatePersonalizado() {
    return '
<div style="max-width: 600px; margin: 0 auto; font-family: Inter, sans-serif;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 2rem; text-align: center; border-radius: 12px 12px 0 0;">
        <img src="https://klubecash.com/assets/images/logobranco.png" alt="Klube Cash" style="height: 60px; margin-bottom: 1rem;">
        <h1 style="margin: 0; font-size: 1.8rem; font-weight: 800;">[SEU TÍTULO AQUI]</h1>
        <p style="margin: 1rem 0 0; font-size: 1.1rem; opacity: 0.95;">[Seu subtítulo personalizado]</p>
    </div>
    
    <!-- Conteúdo -->
    <div style="background: white; padding: 2rem;">
        <h2 style="color: #333; margin-bottom: 1.5rem;">Olá, futuro membro da Klube Cash! 👋</h2>
        
        <p style="color: #666; line-height: 1.8; margin-bottom: 2rem;">
            [Escreva aqui sua mensagem personalizada para os cadastrados. 
            Você pode falar sobre novidades, promoções, dicas ou qualquer conteúdo relevante.]
        </p>
        
        <!-- Conteúdo destacado -->
        <div style="background: #FFF7ED; border-left: 4px solid #FF7A00; padding: 1.5rem; border-radius: 0 8px 8px 0; margin: 2rem 0;">
            <h3 style="color: #EA580C; margin: 0 0 1rem;">✨ Destaque da Semana</h3>
            <p style="color: #9A3412; margin: 0; line-height: 1.6;">
                [Adicione aqui informações importantes, promoções especiais ou novidades que merecem destaque]
            </p>
        </div>
        
        <!-- Lista de itens -->
        <h3 style="color: #FF7A00; margin: 2rem 0 1rem;">📋 O que você precisa saber:</h3>
        <ul style="color: #666; line-height: 1.8; margin: 0 0 2rem; padding-left: 1.5rem;">
            <li>[Primeiro ponto importante]</li>
            <li>[Segundo ponto importante]</li>
            <li>[Terceiro ponto importante]</li>
        </ul>
        
        <!-- Call to action -->
        <div style="text-align: center; margin: 2.5rem 0;">
            <a href="https://klubecash.com" style="background: linear-gradient(135deg, #FF7A00, #FF9A40); color: white; padding: 1.2rem 2.5rem; text-decoration: none; border-radius: 30px; font-weight: 700; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(255, 122, 0, 0.3);">
                🚀 [Texto do seu botão]
            </a>
        </div>
        
        <p style="color: #666; line-height: 1.6; margin: 1rem 0;">
            [Mensagem final ou informações adicionais que você gostaria de compartilhar]
        </p>
    </div>
    
    <!-- Footer -->
    <div style="background: #FFF7ED; padding: 2rem; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #FFE4B5;">
        <p style="color: #666; font-size: 0.9rem; margin: 0 0 1rem;">
            Siga-nos nas redes sociais para mais novidades!
        </p>
        <div style="margin-bottom: 1rem;">
            <a href="https://instagram.com/klubecash" style="color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;">📸 Instagram</a>
            <a href="https://tiktok.com/@klube.cash" style="color: #FF7A00; text-decoration: none; margin: 0 1rem; font-weight: 600;">🎵 TikTok</a>
        </div>
        <p style="color: #999; font-size: 0.8rem; margin: 0;">
            &copy; 2025 Klube Cash. Todos os direitos reservados.<br>
            Você está recebendo este email porque se cadastrou em nossa lista de lançamento.
        </p>
    </div>
</div>';
}
?>